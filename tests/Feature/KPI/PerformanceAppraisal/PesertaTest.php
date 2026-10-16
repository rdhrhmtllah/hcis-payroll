<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaPesertaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\TestCase;

/**
 * Sisi peserta — layar yang dilihat seluruh karyawan.
 *
 * SETIAP PENJAGA DI SINI DIUJI LEWAT DUA ARAH
 *
 * "Hasil belum terbit tidak terlihat" akan hijau juga ketika tidak ada hasil sama sekali,
 * ketika kuerinya salah tabel, atau ketika servicenya melempar dan tertangkap di tempat
 * lain. Uji yang hanya memeriksa arah TERSEMBUNYI karena itu tidak membuktikan apa pun.
 *
 * Karena itu tiap penjaga di berkas ini dibuktikan dengan membalik satu hal: baris yang
 * sama, status yang berbeda, dan hasil yang harus berubah. Kalau penjaganya dicabut, uji
 * ini merah — dan itulah satu-satunya alasan uji ini layak ada.
 */
class PesertaTest extends TestCase
{
    use DatabaseTransactions;

    private int $cycleId;

    /** User yang berperan sebagai PENILAI dalam uji ini. */
    private int $userId;

    private object $penilai;

    private object $orangLain;

    private int $participantSaya;

    private int $participantOrangLain;

    private int $kategoriId;

    private int $levelId;

    protected function setUp(): void
    {
        parent::setUp();

        $dua = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->whereNotNull('UserID_Web')
            ->orderBy('Kode_Karyawan')
            ->limit(2)
            ->get(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'UserID_Web', 'ID_Level_Jabatan']);

        $this->assertCount(2, $dua, 'Uji ini butuh dua karyawan aktif yang tersambung ke user.');

        $this->penilai = $dua[0];
        $this->orangLain = $dua[1];
        $this->userId = (int) $this->penilai->UserID_Web;

        $this->kategoriId = (int) DB::table('N_PA_Rater_Category')
            ->where('Kode_Kategori', 'SELF')
            ->value('Id_PA_Rater_Category');

        $this->levelId = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');

        $this->cycleId = $this->buatSiklus();
        $this->participantSaya = $this->buatPeserta($this->penilai);
        $this->participantOrangLain = $this->buatPeserta($this->orangLain);
    }

    /* ------------------------------------------------------------------ *
     * Lingkup: siapa yang menilai, dan milik siapa hasilnya               *
     * ------------------------------------------------------------------ */

    public function test_ringkasan_hanya_menghitung_penugasan_tempat_saya_menjadi_penilai(): void
    {
        // Saya menilai dua orang.
        $this->buatPenugasan($this->participantSaya, $this->penilai, 'SELESAI');
        $this->buatPenugasan($this->participantOrangLain, $this->penilai, 'BELUM_MULAI');

        // Orang lain menilai dirinya sendiri. Ini tidak boleh masuk hitungan saya — dan
        // tanpa penyaring penilai, penugasan ini akan ikut terhitung.
        $this->buatPenugasan($this->participantOrangLain, $this->orangLain, 'BELUM_MULAI');

        $r = $this->service()->ringkasan($this->userId, $this->cycleId);

        $this->assertSame(2, $r['total_assignment'], 'Hanya penugasan tempat saya menilai.');
        $this->assertSame(1, $r['sudah_selesai']);
        $this->assertSame(1, $r['belum_dimulai']);
        $this->assertSame(50, $r['progres_persen']);

        // Arah sebaliknya: dari sudut pandang orang lain, angkanya harus berbeda.
        $lain = $this->service()->ringkasan((int) $this->orangLain->UserID_Web, $this->cycleId);
        $this->assertSame(1, $lain['total_assignment'], 'Penilai lain melihat miliknya sendiri.');
    }

    public function test_ringkasan_memisahkan_terisi_dari_terkirim(): void
    {
        $this->buatPenugasan($this->participantSaya, $this->penilai, 'SELESAI');
        $this->buatPenugasan($this->participantOrangLain, $this->penilai, 'TERKUMPUL');

        $r = $this->service()->ringkasan($this->userId, $this->cycleId);

        $this->assertSame(2, $r['sudah_selesai'], 'Lembar terkumpul sudah terisi.');
        $this->assertSame(1, $r['sudah_terkirim'], 'Hanya SELESAI yang terkirim.');
        $this->assertSame(1, $r['belum_dikirim']);

        // MENUNGGU_VERIFIKASI sudah diisi; dulu tidak masuk ember mana pun.
        DB::table('N_PA_Assignment')->where('PA_Participant_Id', $this->participantOrangLain)
            ->update(['Status_Penugasan' => 'MENUNGGU_VERIFIKASI']);

        $r = $this->service()->ringkasan($this->userId, $this->cycleId);
        $this->assertSame(2, $r['sudah_selesai']);
        $this->assertSame(100, $r['progres_persen']);
    }

    public function test_penugasan_dibatalkan_tidak_ikut_dihitung(): void
    {
        $id = $this->buatPenugasan($this->participantSaya, $this->penilai, 'BELUM_MULAI');

        $this->assertSame(
            1,
            $this->service()->ringkasan($this->userId, $this->cycleId)['total_assignment']
        );

        DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $id)
            ->update(['Status_Penugasan' => 'DIBATALKAN']);

        $this->assertSame(
            0,
            $this->service()->ringkasan($this->userId, $this->cycleId)['total_assignment'],
            'Penugasan yang dibatalkan bukan pekerjaan siapa pun lagi.'
        );
    }

    public function test_daftar_assignment_menandai_penilaian_diri(): void
    {
        $this->buatPenugasan($this->participantSaya, $this->penilai, 'BELUM_MULAI');
        $this->buatPenugasan($this->participantOrangLain, $this->penilai, 'BELUM_MULAI');

        $baris = collect($this->service()
            ->daftarAssignment($this->userId, ['cycle_id' => $this->cycleId])['assignment']);

        $this->assertCount(2, $baris);
        $this->assertTrue(
            $baris->firstWhere('Kode_Karyawan', $this->penilai->Kode_Karyawan)['diri_sendiri']
        );
        $this->assertFalse(
            $baris->firstWhere('Kode_Karyawan', $this->orangLain->Kode_Karyawan)['diri_sendiri']
        );
    }

    public function test_kiriman_final_yang_tertahan_ditandai_untuk_penilai(): void
    {
        $id = $this->buatPenugasan($this->participantSaya, $this->penilai, 'BELUM_MULAI');

        $integrasi = (int) DB::table('N_PA_Assignment_Integration')->insertGetId([
            'PA_Assignment_Id' => $id,
            'Api_Ref' => bin2hex(random_bytes(16)),
            'Short_Token' => 'UJIKT'.substr(bin2hex(random_bytes(2)), 0, 3),
            'Status_Integrasi' => 'BERHASIL',
            'Flag_Aktif' => 'Y',
        ], 'Id_PA_Assignment_Integration');

        $tertahan = fn () => collect($this->service()
            ->daftarAssignment($this->userId, ['cycle_id' => $this->cycleId])['assignment'])
            ->firstWhere('Id_PA_Assignment', $id)['kiriman_tertahan'];

        $this->assertFalse($tertahan(), 'Belum ada kiriman, belum ada yang tertahan.');

        DB::table('N_PA_Assignment_Payload')->insert([
            'PA_Assignment_Integration_Id' => $integrasi,
            'Payload_Hash' => hash('sha256', (string) $integrasi),
            'Payload_Json' => '{}',
            'Diterima_At' => now(),
            'Flag_Terpeta' => 'Y',
            'Mapper_Version' => 1,
            'Status_Skor' => 'FINAL',
        ]);

        $this->assertTrue($tertahan(), 'Kiriman FINAL masuk tetapi status masih BELUM_MULAI.');

        // Arah sebaliknya: begitu penugasannya naik, penandanya padam.
        DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $id)
            ->update(['Status_Penugasan' => 'TERKUMPUL']);

        $this->assertFalse($tertahan(), 'Kiriman yang sudah terpetakan bukan kiriman tertahan.');
    }

    /* ------------------------------------------------------------------ *
     * Penjaga penerbitan — dibuktikan dua arah                            *
     * ------------------------------------------------------------------ */

    public function test_hasil_hanya_terlihat_setelah_terbit(): void
    {
        $resultId = $this->buatHasil($this->participantSaya, 82.5, 'B');

        foreach (['BELUM_DIHITUNG', 'DIHITUNG', 'TERTAHAN'] as $status) {
            DB::table('N_PA_Result')->where('Id_PA_Result', $resultId)
                ->update(['Status_Hasil' => $status]);

            $this->assertFalse(
                $this->service()->hasilSaya($this->userId)['tersedia'],
                "Hasil berstatus {$status} tidak boleh terlihat peserta."
            );
        }

        /* Arah sebaliknya. Tanpa langkah ini, ketiga penegasan di atas juga akan hijau
           bila servicenya sekadar selalu mengembalikan tersedia=false. */
        DB::table('N_PA_Result')->where('Id_PA_Result', $resultId)
            ->update(['Status_Hasil' => 'TERBIT']);

        $terbit = $this->service()->hasilSaya($this->userId);

        $this->assertTrue($terbit['tersedia'], 'Hasil TERBIT harus terlihat.');
        $this->assertSame('B', $terbit['hasil']['Kode_Grade_Final']);
    }

    public function test_riwayat_menerapkan_penjaga_terbit_yang_sama(): void
    {
        $resultId = $this->buatHasil($this->participantSaya, 82.5, 'B');

        DB::table('N_PA_Result')->where('Id_PA_Result', $resultId)
            ->update(['Status_Hasil' => 'DIHITUNG']);

        $this->assertSame(
            0,
            $this->service()->riwayatSaya($this->userId)['jumlah_periode'],
            'Riwayat tidak boleh jadi pintu belakang bagi hasil yang belum terbit.'
        );

        DB::table('N_PA_Result')->where('Id_PA_Result', $resultId)
            ->update(['Status_Hasil' => 'TERBIT']);

        $this->assertSame(1, $this->service()->riwayatSaya($this->userId)['jumlah_periode']);
    }

    public function test_hasil_orang_lain_tidak_pernah_terbawa(): void
    {
        // Keduanya TERBIT, dan skornya sengaja dibedakan supaya tertukar akan terlihat.
        $this->buatHasil($this->participantSaya, 70.0, 'C', 'TERBIT');
        $this->buatHasil($this->participantOrangLain, 95.0, 'A', 'TERBIT');

        $saya = $this->service()->hasilSaya($this->userId);
        $lain = $this->service()->hasilSaya((int) $this->orangLain->UserID_Web);

        $this->assertSame('C', $saya['hasil']['Kode_Grade_Final']);
        $this->assertSame('A', $lain['hasil']['Kode_Grade_Final']);
        $this->assertSame($this->penilai->Nama, $saya['hasil']['Nama_Karyawan_Saat_Cycle']);
    }

    public function test_hasil_peserta_tidak_memuat_nilai_per_penilai(): void
    {
        $this->buatHasil($this->participantSaya, 82.5, 'B', 'TERBIT');

        $hasil = $this->service()->hasilSaya($this->userId);

        /* Prinsip 4: peserta melihat kesimpulan, bukan siapa memberi berapa. Dinyatakan
           sebagai uji supaya penambahan field kelak harus melewatinya dengan sadar,
           bukan diam-diam. */
        foreach (['kategori', 'per_penilai', 'rater', 'penilai'] as $terlarang) {
            $this->assertArrayNotHasKey($terlarang, $hasil);
        }

        foreach ($hasil['catatan'] as $catatan) {
            $this->assertArrayNotHasKey('Nama_Penilai_Saat_Cycle', $catatan);
        }
    }

    /**
     * Observasi mentah penilai tidak boleh sampai ke peramban orang yang dinilai, walau layar
     * menyembunyikannya: yang dikirim hanya kesimpulan, dan pertanyaan tanpa kesimpulan tetap
     * disebut dengan isi kosong.
     */
    public function test_hasil_hanya_membawa_kesimpulan_bukan_observasi_penilai(): void
    {
        $resultId = $this->buatHasil($this->participantSaya, 82.5, 'B', 'TERBIT');
        $penugasan = $this->buatPenugasan($this->participantSaya, $this->orangLain, 'SELESAI');
        $ql = DB::table('N_PA_Competency')->whereIn('Kode_Competency', ['QL01', 'QL03'])
            ->pluck('Id_PA_Competency', 'Kode_Competency');
        $this->assertCount(2, $ql, 'Uji ini butuh kompetensi QL01 dan QL03 di master.');

        $catat = fn (string $kode, ?int $assignment, string $isi, int $urut) => DB::table('N_PA_Result_Note')->insert([
            'PA_Result_Id' => $resultId, 'PA_Competency_Id' => $ql[$kode], 'PA_Assignment_Id' => $assignment,
            'Isi' => $isi, 'Urutan_Tampil' => $urut, 'Flag_Aktif' => 'Y',
        ]);
        $catat('QL01', null, 'Konsisten', 1);
        $catat('QL01', null, 'Terbuka menerima masukan', 2);
        $catat('QL01', $penugasan, 'RAHASIA-PENILAI-1', 3);
        $catat('QL03', $penugasan, 'RAHASIA-PENILAI-2', 1);

        $catatan = collect($this->service()->hasilSaya($this->userId)['catatan'])->keyBy('Kode_Type');

        $this->assertStringNotContainsString('RAHASIA-PENILAI', json_encode($catatan->all()));
        $this->assertStringContainsString('Konsisten', $catatan['QL01']['Isi']);
        $this->assertStringContainsString('Terbuka menerima masukan', $catatan['QL01']['Isi']);
        $this->assertNull($catatan['QL03']['Isi'], 'Pertanyaan tanpa kesimpulan tetap disebut, isinya kosong.');
    }

    /* ------------------------------------------------------------------ *
     * Keterlambatan dan bentuk angka                                      *
     * ------------------------------------------------------------------ */

    public function test_terlambat_diturunkan_dari_tenggat_bukan_dari_status(): void
    {
        $id = $this->buatPenugasan($this->participantSaya, $this->penilai, 'BELUM_MULAI');

        $this->setTenggat($id, now()->subDays(3));

        $r = $this->service()->ringkasan($this->userId, $this->cycleId);
        $this->assertSame(1, $r['terlambat']);
        $this->assertSame(0, $r['deadline_dekat'], 'Yang terlambat tidak ikut terhitung "dekat".');

        /* Tenggat diperpanjang: keterlambatannya harus hilang sendiri, tanpa pekerjaan
           apa pun yang membalik baris. Itulah alasan ia diturunkan dan bukan disimpan. */
        $this->setTenggat($id, now()->addDays(30));

        $r = $this->service()->ringkasan($this->userId, $this->cycleId);
        $this->assertSame(0, $r['terlambat']);
        $this->assertSame(0, $r['deadline_dekat']);

        // Dan yang tinggal dua hari masuk hitungan "dekat".
        $this->setTenggat($id, now()->addDays(2));

        $this->assertSame(
            1,
            $this->service()->ringkasan($this->userId, $this->cycleId)['deadline_dekat']
        );
    }

    public function test_tenggat_hari_ini_belum_terlambat(): void
    {
        /* REGRESI. `Due_At` bertipe DATE, sehingga strtotime('2026-09-06') jatuh di TENGAH
           MALAM. Tanpa penyesuaian akhir-hari, orang yang tenggatnya hari ini ditandai
           terlambat sejak pukul 00:00 - pada hari yang justru masih miliknya, dengan
           lencana merah bertuliskan "Terlambat 0 hari". Uji ini yang membuat penyesuaian
           itu tidak bisa dicabut diam-diam. */
        $id = $this->buatPenugasan($this->participantSaya, $this->penilai, 'BELUM_MULAI');

        $this->setTenggat($id, now());

        $r = $this->service()->ringkasan($this->userId, $this->cycleId);

        $this->assertSame(0, $r['terlambat'], 'Tenggat hari ini belum lewat.');
        $this->assertSame(1, $r['deadline_dekat'], 'Tenggat hari ini adalah yang paling dekat.');

        $baris = $this->service()
            ->daftarAssignment($this->userId, ['cycle_id' => $this->cycleId])['assignment'][0];

        $this->assertFalse($baris['terlambat']);
        $this->assertSame(0, $baris['sisa_hari'], 'Sisa harinya nol, bukan negatif.');
    }

    public function test_hari_terlambat_dihitung_dalam_hari_kalender(): void
    {
        $id = $this->buatPenugasan($this->participantSaya, $this->penilai, 'BELUM_MULAI');

        // Tenggat KEMARIN. Selisih jamnya bisa saja belum 24, tetapi manusia menghitung
        // keterlambatan dalam hari kalender - dan "Terlambat 0 hari" pada lencana merah
        // membuat orang berhenti mempercayai layarnya.
        $this->setTenggat($id, now()->subDay());

        $baris = $this->service()
            ->daftarAssignment($this->userId, ['cycle_id' => $this->cycleId])['assignment'][0];

        $this->assertTrue($baris['terlambat']);
        $this->assertSame(1, $baris['hari_terlambat']);

        $this->setTenggat($id, now()->subDays(7));

        $baris = $this->service()
            ->daftarAssignment($this->userId, ['cycle_id' => $this->cycleId])['assignment'][0];

        $this->assertSame(7, $baris['hari_terlambat']);
    }

    public function test_penugasan_selesai_tidak_pernah_terlambat(): void
    {
        $id = $this->buatPenugasan($this->participantSaya, $this->penilai, 'SELESAI');

        $this->setTenggat($id, now()->subDays(10));

        $this->assertSame(
            0,
            $this->service()->ringkasan($this->userId, $this->cycleId)['terlambat'],
            'Yang sudah dikerjakan tidak lagi ditunggu siapa pun.'
        );
    }

    public function test_field_turunan_berupa_angka_bukan_teks(): void
    {
        $this->buatHasil($this->participantSaya, 82.5, 'B', 'TERBIT');

        $riwayat = $this->service()->riwayatSaya($this->userId);

        /* Kolom decimal SQL Server tiba sebagai string. Field turunan harus sudah dicetak
           ke angka, karena yang menerimanya JavaScript — dan di sana "9.00" > "82.50"
           bernilai true. */
        $this->assertIsFloat($riwayat['rata_rata']);
        $this->assertIsFloat($riwayat['terbaik']);
        $this->assertIsInt(
            $this->service()->ringkasan($this->userId, $this->cycleId)['progres_persen']
        );
    }

    /**
     * Urutan riwayat mengikuti akhir PERIODE yang dinilai, bukan id siklus: siklus Jan–Jun
     * yang dibuat lebih dulu tetap datang SESUDAH siklus Jan–Mar yang dibuat belakangan.
     */
    public function test_riwayat_diurutkan_menurut_akhir_periode_dinilai(): void
    {
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)
            ->update(['Periode_Dinilai_Dari' => '2026-01-01', 'Periode_Dinilai_Sampai' => '2026-06-30']);
        $this->buatHasil($this->participantSaya, 90.0, 'A', 'TERBIT');

        // Dibuat BELAKANGAN (id lebih besar) tetapi periodenya lebih awal.
        $awal = $this->buatSiklus();
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $awal)
            ->update(['Periode_Dinilai_Dari' => '2026-01-01', 'Periode_Dinilai_Sampai' => '2026-03-31']);
        $asli = $this->cycleId;
        $this->cycleId = $awal;
        $this->buatHasil($this->buatPeserta($this->penilai), 80.0, 'B', 'TERBIT');
        $this->cycleId = $asli;

        $r = $this->service()->riwayatSaya($this->userId);
        $urutan = array_map('intval', array_column($r['periode'], 'Id_PA_Cycle'));

        $this->assertSame([$awal, $asli], array_slice($urutan, -2));
        $this->assertEqualsWithDelta(10.0, $r['perubahan_terakhir'], 0.001, 'Tren dibanding periode sebelumnya.');
        $this->assertSame($asli, (int) DB::table('N_PA_Participant')
            ->where('Id_PA_Participant', $this->service()->hasilSaya($this->userId)['hasil']['Id_PA_Participant'])
            ->value('PA_Cycle_Id'), 'Hasil terbaru = periode yang berakhir paling akhir.');
    }

    public function test_perubahan_terakhir_kosong_saat_baru_satu_periode(): void
    {
        $this->buatHasil($this->participantSaya, 82.5, 'B', 'TERBIT');

        $this->assertNull(
            $this->service()->riwayatSaya($this->userId)['perubahan_terakhir'],
            'Satu periode berarti orang baru, bukan perubahan nol.'
        );
    }

    /* ------------------------------------------------------------------ *
     * Akun yang tidak tersambung                                          *
     * ------------------------------------------------------------------ */

    public function test_akun_tanpa_karyawan_ditolak_dengan_pesan_yang_bisa_ditindaklanjuti(): void
    {
        // Tabelnya `KPI_Users`, bukan `Users` - lihat $table di model User.
        $hantu = (int) DB::table('KPI_Users')->max('Id_Users') + 99999;

        try {
            $this->service()->ringkasan($hantu);
            $this->fail('Akun tanpa karyawan seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            // Pesannya harus menyebut apa yang bisa dilakukan orangnya, bukan sekadar
            // menyatakan ada yang salah.
            $this->assertStringContainsString('belum tersambung', $e->getMessage());
            $this->assertStringContainsString('HC', $e->getMessage());
        }
    }

    /* ------------------------------------------------------------------ *
     * Bangunan uji                                                        *
     * ------------------------------------------------------------------ */

    private function service(): PaPesertaService
    {
        // Dibuat baru tiap panggilan: service ini tidak menyimpan memo, dan uji yang
        // memakai satu instans akan menyembunyikannya bila kelak memo ditambahkan.
        return $this->app->make(PaPesertaService::class);
    }

    /**
     * Kolomnya bertipe DATE, jadi hanya tanggalnya yang tersimpan. Ditulis apa adanya
     * supaya uji ini memakai bentuk yang sama dengan yang sungguh ada di kolom.
     */
    private function setTenggat(int $assignmentId, \DateTimeInterface $waktu): void
    {
        DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $assignmentId)
            ->update(['Due_At' => $waktu->format('Y-m-d')]);
    }

    private function buatSiklus(): int
    {
        $nama = 'Siklus Uji Sisi Peserta '.substr(bin2hex(random_bytes(4)), 0, 8);

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => 'UJI-PESERTA-'.substr(bin2hex(random_bytes(4)), 0, 8),
            'Nama_Cycle' => $nama,
            'Tahun' => (int) date('Y'),
            'Status_Cycle' => 'OPEN',
        ]);

        return (int) DB::table('N_PA_Cycle')
            ->where('Nama_Cycle', $nama)
            ->value('Id_PA_Cycle');
    }

    private function buatPeserta(object $karyawan): int
    {
        DB::table('N_PA_Participant')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'Kode_Perusahaan' => $karyawan->Kode_Perusahaan,
            'Kode_Karyawan' => $karyawan->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $karyawan->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $karyawan->ID_Level_Jabatan,
            // NOT NULL, dan mudah terlewat karena namanya mirip ID_Level_Jabatan di
            // atasnya. Yang ini LEVEL (band jabatan), yang di atas JABATAN.
            'ID_Level_Saat_Cycle' => $this->levelId,
            'Status_Peserta' => 'DIIKUTKAN',
        ]);

        return (int) DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('Kode_Karyawan', $karyawan->Kode_Karyawan)
            ->value('Id_PA_Participant');
    }

    private function buatPenugasan(int $participantId, object $penilai, string $status): int
    {
        DB::table('N_PA_Assignment')->insert([
            'PA_Participant_Id' => $participantId,
            'Kode_Perusahaan_Penilai' => $penilai->Kode_Perusahaan,
            'Kode_Karyawan_Penilai' => $penilai->Kode_Karyawan,
            'Nama_Penilai_Saat_Cycle' => $penilai->Nama,
            'PA_Rater_Category_Id' => $this->kategoriId,
            'Bobot_Dalam_Kategori' => 100,
            'Status_Penugasan' => $status,
        ]);

        return (int) DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $participantId)
            ->where('Kode_Karyawan_Penilai', $penilai->Kode_Karyawan)
            ->orderByDesc('Id_PA_Assignment')
            ->value('Id_PA_Assignment');
    }

    private function buatHasil(
        int $participantId,
        float $skor,
        string $grade,
        string $status = 'DIHITUNG',
    ): int {
        DB::table('N_PA_Result')->insert([
            'PA_Participant_Id' => $participantId,
            'Nilai_KPI' => $skor,
            'Nilai_Kompetensi' => $skor,
            'Final_Score' => $skor,
            'Kode_Grade_Final' => $grade,
            'Kode_Grade_Sistem' => $grade,
            'Status_Hasil' => $status,
            'Published_At' => now()->format('Y-m-d H:i:s'),
        ]);

        return (int) DB::table('N_PA_Result')
            ->where('PA_Participant_Id', $participantId)
            ->orderByDesc('Id_PA_Result')
            ->value('Id_PA_Result');
    }
}
