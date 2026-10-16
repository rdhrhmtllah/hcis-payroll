<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Penjaga `pa:verify` — perintah pemeriksa kesiapan siklus.
 *
 * Yang diuji di sini terutama satu hal: **perintah ini tidak boleh menulis apa pun.**
 * Perintah verifikasi yang diam-diam menyunting data adalah bentuk paling buruk dari
 * perkakas operasional — orang menjalankannya justru ketika sedang ragu, dan pada saat
 * itulah tulisan tak terduga paling mahal.
 *
 * Selebihnya: temuan harus muncul ketika keadaannya memang buruk, dan HILANG ketika
 * keadaannya diperbaiki. Uji yang hanya memeriksa "ada temuan" akan tetap hijau
 * seandainya perintah ini mencetak temuan yang sama untuk siklus apa pun.
 */
class VerifyCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tidak_menulis_apa_pun(): void
    {
        $cycleId = $this->siklusUji();
        $kode = $this->kodeSiklus($cycleId);

        $sebelum = $this->cacahSeluruhTabelPa();

        $this->artisan("pa:verify {$kode}")->run();

        $sesudah = $this->cacahSeluruhTabelPa();

        $this->assertSame(
            $sebelum,
            $sesudah,
            'pa:verify menulis ke basis data. Perintah verifikasi harus read-only — orang '
            .'menjalankannya justru ketika sedang ragu, dan tulisan tak terduga di saat itu '
            .'paling mahal.'
        );
    }

    public function test_menemukan_periode_dinilai_yang_kosong(): void
    {
        // Siklus tanpa periode: nilai KPI tidak punya batas untuk diakumulasi.
        $cycleId = $this->siklusUji(['Periode_Dinilai_Dari' => null, 'Periode_Dinilai_Sampai' => null]);

        $this->artisan('pa:verify '.$this->kodeSiklus($cycleId))
            ->expectsOutputToContain('Periode dinilai kosong')
            ->assertFailed();
    }

    public function test_menemukan_tahun_yang_berselisih_dengan_periode(): void
    {
        /* Salah ketik yang paling mungkin terjadi, dan akibatnya paling luas: SETIAP
           peserta berakhir "Tidak ada KPI disetujui" karena pilar KPI menyaring jadwal
           memakai TAHUN siklus. */
        $cycleId = $this->siklusUji([
            'Tahun' => 2027,
            'Periode_Dinilai_Dari' => '2026-01-01',
            'Periode_Dinilai_Sampai' => '2026-06-30',
        ]);

        $this->artisan('pa:verify '.$this->kodeSiklus($cycleId))
            ->expectsOutputToContain('Tahun siklus tidak sesuai periode dinilai')
            ->assertFailed();
    }

    public function test_siklus_sehat_tidak_dilaporkan_bermasalah(): void
    {
        /* PENJAGA TERPENTING di berkas ini. Tanpa uji ini, perintah yang mencetak temuan
           untuk siklus APA PUN akan lulus seluruh uji di atas — dan perkakas yang selalu
           merah sama tidak bergunanya dengan yang selalu hijau, karena orang berhenti
           membacanya. */
        $cycleId = $this->siklusUji();

        $keluaran = $this->jalankanDanTangkap($this->kodeSiklus($cycleId));

        $this->assertStringNotContainsString('Periode dinilai kosong', $keluaran);
        $this->assertStringNotContainsString('Tahun siklus tidak sesuai', $keluaran);
        $this->assertStringNotContainsString('Jendela pengisian kosong', $keluaran);
    }

    public function test_pintu_yang_jendelanya_sudah_bergeser_dilaporkan(): void
    {
        /* Utang yang lahir bersama izin menggeser jendela di tengah siklus: pintu yang sudah
           dicetak TIDAK ikut bergeser, dan token di HC Learn tetap membawa rentang lama.
           Tanpa penjaga ini, yang gagal adalah sesuatu di sistem orang lain — keluhannya
           datang satu per satu dari penilai, berminggu-minggu kemudian, dan terbaca seperti
           "HC Learn sedang bermasalah". */
        $cycleId = $this->siklusUji();
        $assignmentId = $this->penugasanUji($cycleId);

        $this->pintuUji($assignmentId, 'SIM-PA-LAMA-'.strtoupper(bin2hex(random_bytes(4))), [
            'jendela_dari' => '2026-07-01',
            'jendela_sampai' => '2026-07-31',
        ]);

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)
            ->update(['Pengisian_Sampai' => '2026-08-15']);

        $this->artisan('pa:verify '.$this->kodeSiklus($cycleId))
            ->expectsOutputToContain('1 pintu dicetak untuk jendela yang sudah tidak berlaku');
    }

    public function test_pintu_yang_jendelanya_masih_sama_tidak_dilaporkan(): void
    {
        /* Pasangan penolakan bagi uji di atas. Tanpa ini, penjaga yang melaporkan SETIAP
           pintu tetap terlihat bekerja — dan peringatan yang selalu menyala berhenti dibaca.

           Dipisah menjadi uji sendiri, bukan penegasan kedua di uji sebelumnya: satu uji
           hanya boleh menjalankan perintahnya SEKALI, karena penangkap keluarannya tidak
           memulangkan apa pun pada pemanggilan berikutnya — dan penegasan terhadap teks
           kosong selalu lolos. */
        $cycleId = $this->siklusUji();
        $assignmentId = $this->penugasanUji($cycleId);

        $this->pintuUji($assignmentId, 'SIM-PA-PAS-'.strtoupper(bin2hex(random_bytes(4))), [
            'jendela_dari' => '2026-07-01',
            'jendela_sampai' => '2026-07-31',
        ]);

        $this->artisan('pa:verify '.$this->kodeSiklus($cycleId))
            ->doesntExpectOutputToContain('jendela yang sudah tidak berlaku');
    }

    public function test_pintu_lama_tanpa_catatan_jendela_tidak_dituduh_menyimpang(): void
    {
        /* Pintu yang dicetak sebelum jendelanya mulai dicatat harus DILEWATI, bukan dihitung
           menyimpang — menuduhnya berarti melaporkan setiap siklus lama sebagai bermasalah,
           dan peringatan yang selalu menyala berhenti dibaca orang. */
        $cycleId = $this->siklusUji();
        $assignmentId = $this->penugasanUji($cycleId);

        $this->pintuUji($assignmentId, 'SIM-PA-KUNO-'.strtoupper(bin2hex(random_bytes(4))), []);

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)
            ->update(['Pengisian_Sampai' => '2026-08-15']);

        $this->artisan('pa:verify '.$this->kodeSiklus($cycleId))
            ->doesntExpectOutputToContain('jendela yang sudah tidak berlaku');
    }

    public function test_kode_siklus_tak_dikenal_ditolak_bukan_dilaporkan_bersih(): void
    {
        /* Salah ketik kode siklus tidak boleh menghasilkan "tidak ada temuan" — itu
           jawaban yang menenangkan untuk pertanyaan yang tidak pernah diperiksa. */
        $this->artisan('pa:verify SIKLUS-YANG-TIDAK-ADA-'.bin2hex(random_bytes(3)))
            ->expectsOutputToContain('Tidak ada siklus yang cocok')
            ->assertFailed();
    }

    /* ------------------------------------------------------------------ *
     * Penyiapan                                                           *
     * ------------------------------------------------------------------ */

    /** @param array<string, mixed> $tambahan */
    /*
     | BUTIR TANPA KOMPETENSI: cacat yang tidak pernah muncul sebagai galat.
     |
     | Butir seperti ini diterima basis data, ditanyakan kepada penilai, dan dijawab dengan
     | normal. Jawabannya tersimpan lengkap — lalu tidak muncul di report mana pun. Sudah
     | terjadi: 16 butir, 504 jawaban, ditemukan hanya karena satu peserta tertahan tanpa
     | sebab yang masuk akal (DML-2026-106).
     */
    /**
     * Sejak DDL-2026-090, keadaan ini TIDAK BISA DIBUAT LAGI di development.
     *
     * `PA_Competency_Id` menjadi NOT NULL, jadi jaminannya berpindah dari waktu-LAPOR ke
     * waktu-TULIS: basis data menolaknya, bukan `pa:verify` yang menemukannya belakangan.
     * Itu peningkatan — butir yang ditolak saat disisipkan tidak pernah sempat ditanyakan
     * kepada penilai.
     *
     * Yang ditegaskan uji ini karena itu berubah: bukan lagi "laporannya menyebutkannya",
     * melainkan "penyisipannya ditolak". Menegaskan yang lama akan menuntut keadaan yang
     * mustahil, dan menghapus ujinya sama sekali akan membuang satu-satunya bukti bahwa
     * penjaganya benar-benar terpasang.
     *
     * PEMERIKSAAN DI `pa:verify` SENGAJA DIPERTAHANKAN. Production BELUM menjalankan
     * DDL-2026-090; di sana kolomnya masih nullable dan pemeriksaan itu masih satu-satunya
     * yang menangkapnya. Ia baru boleh dicabut sesudah DDL-nya tercatat DONE di production.
     */
    public function test_butir_soal_tanpa_kompetensi_ditolak_basis_data(): void
    {
        $cycleId = $this->siklusUji();

        try {
            $this->paketUji($cycleId, null);
            $this->fail('Butir soal tanpa kompetensi seharusnya ditolak basis data.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('PA_Competency_Id', $e->getMessage());
        }
    }

    public function test_butir_soal_yang_terpetakan_tidak_dilaporkan(): void
    {
        $cycleId = $this->siklusUji();

        $kompetensiId = (int) DB::table('N_PA_Competency')->where('Flag_Aktif', 'Y')
            ->orderBy('Id_PA_Competency')->value('Id_PA_Competency');

        $this->assertNotSame(0, $kompetensiId, 'Master kompetensi kosong.');

        $this->paketUji($cycleId, $kompetensiId);

        $this->artisan('pa:verify', ['kode' => $this->kodeSiklus($cycleId)])
            ->doesntExpectOutputToContain('tidak menunjuk kompetensi');
    }

    /** Satu paket soal berisi satu butir, dengan atau tanpa kompetensi. */
    private function paketUji(int $cycleId, ?int $kompetensiId): void
    {
        $nama = 'Paket Uji Verify '.substr(bin2hex(random_bytes(3)), 0, 6);
        $levelId = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');

        DB::table('N_PA_Cycle_Question_Package')->insert([
            'PA_Cycle_Id' => $cycleId,
            'ID_Level' => $levelId,
            'Id_Master_Ujian' => 'PKG-'.substr(bin2hex(random_bytes(3)), 0, 6),
            'Nama_Ujian_Sumber' => $nama,
            'Sumber_Kontrak' => 'MANUAL',
            'Flag_Aktif' => 'Y',
        ]);

        $packageId = (int) DB::table('N_PA_Cycle_Question_Package')
            ->where('Nama_Ujian_Sumber', $nama)
            ->value('Id_PA_Cycle_Question_Package');

        DB::table('N_PA_Cycle_Question_Package_Item')->insert([
            'PA_Cycle_Question_Package_Id' => $packageId,
            'Id_Jenis_Indikator' => 'ALT-VERIFY',
            'Id_Jenis_Soal' => 'UJI-'.substr(bin2hex(random_bytes(3)), 0, 6),
            'Nama_Bagian_Sumber' => 'Butir uji verify',
            'Jenis_Soal' => 'uraian',
            'PA_Competency_Id' => $kompetensiId,
            'Urutan_Tampil' => 1,
            'Flag_Aktif' => 'Y',
        ]);
    }

    private function siklusUji(array $tambahan = []): int
    {
        $kode = 'UJI-VERIFY-'.substr(bin2hex(random_bytes(4)), 0, 8);

        DB::table('N_PA_Cycle')->insert(array_merge([
            'Kode_Cycle' => $kode,
            'Nama_Cycle' => 'Siklus Uji Verify',
            'Tahun' => 2026,
            'Status_Cycle' => 'DRAFT',
            'Periode_Dinilai_Dari' => '2026-01-01',
            'Periode_Dinilai_Sampai' => '2026-06-30',
            'Pengisian_Dari' => '2026-07-01',
            'Pengisian_Sampai' => '2026-07-31',
        ], $tambahan));

        return (int) DB::table('N_PA_Cycle')->where('Kode_Cycle', $kode)->value('Id_PA_Cycle');
    }

    /**
     * Satu peserta dan satu penugasan nyata untuk siklus uji.
     *
     * Karyawannya diambil dari data sungguhan: FK ke `Karyawan` melarang kode karangan.
     */
    private function penugasanUji(int $cycleId): int
    {
        $stok = DB::table('Karyawan')->where('Aktif', 'Y')
            ->orderBy('Kode_Karyawan')
            ->limit(2)
            ->get(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan'])
            ->all();

        $this->assertCount(2, $stok, 'Uji ini butuh dua karyawan aktif yang berbeda.');

        $levelId = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $kategoriId = (int) DB::table('N_PA_Rater_Category')
            ->orderBy('Id_PA_Rater_Category')
            ->value('Id_PA_Rater_Category');

        DB::table('N_PA_Participant')->insert([
            'PA_Cycle_Id' => $cycleId,
            'Kode_Perusahaan' => $stok[0]->Kode_Perusahaan,
            'Kode_Karyawan' => $stok[0]->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $stok[0]->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $stok[0]->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $levelId,
            'Status_Peserta' => 'DIIKUTKAN',
        ]);

        $participantId = (int) DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Kode_Karyawan', $stok[0]->Kode_Karyawan)
            ->value('Id_PA_Participant');

        DB::table('N_PA_Assignment')->insert([
            'PA_Participant_Id' => $participantId,
            'Kode_Perusahaan_Penilai' => $stok[1]->Kode_Perusahaan,
            'Kode_Karyawan_Penilai' => $stok[1]->Kode_Karyawan,
            'Nama_Penilai_Saat_Cycle' => $stok[1]->Nama,
            'PA_Rater_Category_Id' => $kategoriId,
            'Bobot_Dalam_Kategori' => 100,
            'Status_Penugasan' => 'BELUM_MULAI',
        ]);

        return (int) DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $participantId)
            ->where('Kode_Karyawan_Penilai', $stok[1]->Kode_Karyawan)
            ->value('Id_PA_Assignment');
    }

    /** @param array<string, mixed> $jejakTambahan */
    private function pintuUji(int $assignmentId, string $token, array $jejakTambahan): void
    {
        DB::table('N_PA_Assignment_Integration')->insert([
            'PA_Assignment_Id' => $assignmentId,
            'Id_Ujian_Token' => $token,
            'Payload_Json' => json_encode(array_merge([
                'simulasi' => true,
                'assignment_id' => $assignmentId,
            ], $jejakTambahan)),
            'Flag_Aktif' => 'Y',
            'Created_At' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    private function kodeSiklus(int $cycleId): string
    {
        return (string) DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->value('Kode_Cycle');
    }

    private function jalankanDanTangkap(string $kode): string
    {
        $this->artisan("pa:verify {$kode}")->run();

        return \Illuminate\Support\Facades\Artisan::output();
    }

    /**
     * Cacah baris SELURUH tabel `N_PA_*`, untuk membuktikan tak ada yang bertambah.
     *
     * Memeriksa seluruhnya, bukan tabel yang "mungkin disentuh": justru tabel yang tidak
     * diduga itulah yang paling mungkin tersentuh tanpa disadari.
     *
     * @return array<string, int>
     */
    private function cacahSeluruhTabelPa(): array
    {
        $tabel = collect(DB::select(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_NAME LIKE 'N[_]PA[_]%'
             ORDER BY TABLE_NAME"
        ))->pluck('TABLE_NAME');

        $this->assertGreaterThan(
            20,
            $tabel->count(),
            'Pemindaian tabel PA gagal; uji read-only ini tidak menjaga apa pun.'
        );

        $cacah = [];

        foreach ($tabel as $t) {
            $cacah[$t] = (int) DB::table($t)->count();
        }

        return $cacah;
    }
}
