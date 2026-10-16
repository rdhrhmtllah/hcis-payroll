<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaCalibrationService;
use App\Services\KPI\PerformanceAppraisal\PaParticipantReadService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\TestCase;

/**
 * Kalibrasi hasil — menggeser grade akhir seseorang, dengan jejaknya.
 *
 * KENAPA BERKAS INI ADA
 *
 * Sampai 1 September 2026, `PaCalibrationService` tidak punya satu pun uji. Akibatnya
 * sebuah cacat berjalan tanpa terdeteksi: jalur aplikasi menulis NAMA pelaku ke
 * `Performed_By`, sebuah kolom `int NOT NULL`. SQL Server menolak konversinya, transaksi
 * batal, dan galat sebenarnya tertutup pesan rollback sehingga muncul sebagai **422** —
 * seolah aturan bisnis yang menolak, bukan sistem yang rusak. Setiap kalibrasi lewat API
 * gagal, dan tidak ada yang tahu.
 *
 * Karena itu uji pertama di bawah memeriksa hal yang tampak sepele: bahwa kalibrasi
 * benar-benar TERSIMPAN, dan bahwa baris riwayatnya memuat id sekaligus nama pelakunya.
 * Cacat yang lolos bukan karena rumit, melainkan karena tidak ada yang memanggilnya.
 *
 * `Kode_Grade_Sistem` TIDAK PERNAH DISENTUH
 *
 * Itu angka yang dihitung mesin, dan ia satu-satunya pembanding yang tersisa untuk tahu
 * seberapa jauh manusia menggesernya. Menimpanya membuat kalibrasi tak bisa dibedakan
 * dari perhitungan.
 */
class CalibrationTest extends TestCase
{
    use DatabaseTransactions;

    private int $cycleId;

    private int $resultId;

    private int $participantId;

    private array $stok = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->stok = DB::table('Karyawan')->where('Aktif', 'Y')
            ->orderBy('Kode_Karyawan')
            ->limit(6)
            ->get(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan'])
            ->all();

        $this->assertGreaterThanOrEqual(3, count($this->stok), 'Uji ini butuh beberapa karyawan.');

        $this->cycleId = $this->buatSiklus();
        [$this->participantId, $this->resultId] = $this->buatHasil('B', 82.5);

        /* Prasyarat dinyatakan di depan. Tanpa pita salinan, `pastikanGradeSah()` menolak
           grade apa pun dan seluruh uji di bawah merah karena alasan yang sama sekali
           berbeda dari yang hendak diperiksa. */
        $this->assertGreaterThanOrEqual(2, DB::table('N_PA_Cycle_Score_Range_Snapshot')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('Score_Usage', 'GRADE')
            ->count(), 'Siklus uji harus punya pita nilai untuk digeser.');
    }

    public function test_kalibrasi_tersimpan_dan_riwayatnya_memuat_id_dan_nama_pelaku(): void
    {
        $olehUserId = (int) DB::table('KPI_Users')->orderBy('Id_Users')->value('Id_Users');

        $this->kalibrasi('A', 'penyesuaian hasil rapat kalibrasi divisi', $olehUserId, 'Budi Santoso');

        $hasil = DB::table('N_PA_Result')->where('Id_PA_Result', $this->resultId)
            ->first(['Kode_Grade_Final', 'Kode_Grade_Sistem']);

        $this->assertSame('A', $hasil->Kode_Grade_Final, 'Grade akhir harus bergeser.');
        $this->assertSame('B', $hasil->Kode_Grade_Sistem, 'Grade sistem TIDAK boleh tersentuh.');

        $riwayat = DB::table('N_PA_Calibration_History')
            ->where('PA_Result_Id', $this->resultId)
            ->first();

        $this->assertNotNull($riwayat, 'Kalibrasi wajib meninggalkan jejak.');

        /* Keduanya, bukan salah satu. Id menyambung ke daftar user selama user itu masih
           ada; namanya tetap terbaca ketika ia sudah keluar dari perusahaan — dan justru
           baris itulah yang paling perlu dijelaskan setahun kemudian. */
        $this->assertSame($olehUserId, (int) $riwayat->Performed_By);
        $this->assertSame('Budi Santoso', $riwayat->Performed_By_Nama);

        $this->assertSame('B', $riwayat->Kode_Grade_Sebelum);
        $this->assertSame('A', $riwayat->Kode_Grade_Sesudah);
        $this->assertStringContainsString('rapat kalibrasi', (string) $riwayat->Alasan);
    }

    /**
     * Pilihan yang DITAWARKAN layar harus sama dengan yang DITERIMA server.
     *
     * Dialog kalibrasi dulu meminta admin mengetik kode grade dan id kuadran. Sejak
     * keduanya menjadi daftar pilihan, muncul kegagalan baru yang tidak pernah ada pada
     * kotak teks: daftar yang bersumber salah akan menawarkan grade yang lalu ditolak
     * server — dan pilihan yang ditawarkan berbunyi seperti janji, sehingga penolakannya
     * terbaca sebagai kerusakan sistem, bukan sebagai pilihan yang memang tidak berlaku.
     */
    public function test_pilihan_grade_yang_ditawarkan_benar_benar_diterima_server(): void
    {
        $pilihan = $this->pilihanKalibrasi();

        $this->assertNotEmpty($pilihan['grade'], 'Prasyarat: siklus uji punya pita salinan.');

        /* Dibuktikan dengan MENJALANKANNYA, bukan dengan membandingkan dua kueri yang
           kebetulan ditulis sama. Kalibrasi sungguhan ke grade yang ditawarkan adalah
           satu-satunya bukti bahwa penawaran itu bisa ditepati. */
        $lain = null;

        foreach ($pilihan['grade'] as $g) {
            if ($g['Kode_Range'] !== 'B') {
                $lain = $g['Kode_Range'];
                break;
            }
        }

        $this->assertNotNull($lain, 'Butuh satu grade selain grade sekarang untuk digeser.');

        $this->kalibrasi($lain, 'menguji bahwa pilihan yang ditawarkan memang diterima');

        $this->assertSame($lain, DB::table('N_PA_Result')
            ->where('Id_PA_Result', $this->resultId)->value('Kode_Grade_Final'));
    }

    public function test_pilihan_grade_datang_dari_salinan_siklus_bukan_master_hidup(): void
    {
        /* Grade yang ada di MASTER tetapi tidak ada di salinan siklus ini. Server menolaknya
           — `pastikanGradeSah()` memang memeriksa salinan — jadi menawarkannya berarti
           menjanjikan sesuatu yang pasti gagal. Master boleh berubah sesudah siklus
           berjalan; yang mengikat hasil adalah salinan yang dibekukan saat penyiapan. */
        $kode = 'UJI'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

        DB::table('N_PA_Score_Range')->insert([
            'Score_Type' => 'FINAL',
            'Score_Usage' => 'GRADE',
            'Kode_Range' => $kode,
            'Nama_Range' => 'Grade Master Yang Tidak Disalin',
            'Nilai_Dari' => 95,
            'Nilai_Sampai' => 96,
            'Urutan_Tampil' => 99,
        ]);

        $ditawarkan = array_column($this->pilihanKalibrasi()['grade'], 'Kode_Range');

        $this->assertNotContains(
            $kode,
            $ditawarkan,
            'Grade master yang TIDAK ada di salinan siklus ikut ditawarkan. Server akan '
            .'menolaknya, dan admin akan mengira sistemnya rusak.'
        );

        // Penerimaan berpasangan: yang MEMANG disalin harus tetap ada, kalau tidak uji di
        // atas hijau hanya karena daftarnya kosong.
        $this->assertContains('A', $ditawarkan);
    }

    public function test_pilihan_kuadran_membawa_nomor_box_karena_namanya_bisa_kembar(): void
    {
        $kuadran = $this->pilihanKalibrasi()['kuadran'];

        $this->assertNotEmpty($kuadran, 'Master kuadran HAV kosong.');

        foreach ($kuadran as $k) {
            $this->assertArrayHasKey('Nomor_Box', $k);
            $this->assertArrayHasKey('Id_PA_Hav_Quadrant', $k);
        }

        /* Bukan kehati-hatian berlebihan: pada development #2 FUTURE_STAR_A dan #3
           FUTURE_STAR_B sama-sama bernama "Future Star". Tanpa nomor box, dua baris
           dropdown berbunyi identik dan yang memilih tidak punya cara membedakannya. */
        $nama = array_column($kuadran, 'Nama_Kuadran');

        if (count($nama) !== count(array_unique($nama))) {
            $label = array_map(
                fn ($k) => 'Box '.$k['Nomor_Box'].' · '.$k['Nama_Kuadran'],
                $kuadran
            );

            $this->assertSame(
                count($label),
                count(array_unique($label)),
                'Ada kuadran bernama kembar, dan nomor box tidak cukup membedakannya.'
            );
        }
    }

    /**
     * @return array{grade:list<array<string, mixed>>, kuadran:list<array<string, mixed>>}
     */
    private function pilihanKalibrasi(): array
    {
        $detail = $this->app->make(PaParticipantReadService::class)
            ->detailHasil($this->participantId);

        $this->assertArrayHasKey(
            'pilihan_kalibrasi',
            $detail,
            'Dialog kalibrasi mengambil pilihannya dari payload detail; tanpa kunci ini '
            .'kedua dropdown-nya kosong.'
        );

        return $detail['pilihan_kalibrasi'];
    }

    public function test_kalibrasi_tanpa_alasan_ditolak(): void
    {
        try {
            $this->kalibrasi('A', '   ');
            $this->fail('Kalibrasi tanpa alasan seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('alasan', mb_strtolower($e->getMessage()));
        }

        // Tidak boleh menyisakan jejak setengah jadi.
        $this->assertSame('B', DB::table('N_PA_Result')
            ->where('Id_PA_Result', $this->resultId)->value('Kode_Grade_Final'));
        $this->assertSame(0, DB::table('N_PA_Calibration_History')
            ->where('PA_Result_Id', $this->resultId)->count());
    }

    public function test_kalibrasi_berulang_menumpuk_jejak_bukan_menimpanya(): void
    {
        $this->kalibrasi('A', 'geser pertama');
        $this->kalibrasi('C', 'ternyata kelewat tinggi, dikoreksi');

        $riwayat = DB::table('N_PA_Calibration_History')
            ->where('PA_Result_Id', $this->resultId)
            ->orderBy('Id_PA_Calibration_History')
            ->get(['Kode_Grade_Sebelum', 'Kode_Grade_Sesudah']);

        /* Dua baris, bukan satu yang ditimpa. Riwayat yang menimpa dirinya sendiri
           menghapus persis pertanyaan yang paling sering ditanyakan: sudah berapa kali
           angka ini digeser, dan oleh siapa. */
        $this->assertCount(2, $riwayat);
        $this->assertSame('B', $riwayat[0]->Kode_Grade_Sebelum);
        $this->assertSame('A', $riwayat[0]->Kode_Grade_Sesudah);
        $this->assertSame('A', $riwayat[1]->Kode_Grade_Sebelum, 'Yang kedua bergeser dari hasil pertama.');
        $this->assertSame('C', $riwayat[1]->Kode_Grade_Sesudah);

        // Dan grade sistem tetap utuh sesudah dua kali kalibrasi.
        $this->assertSame('B', DB::table('N_PA_Result')
            ->where('Id_PA_Result', $this->resultId)->value('Kode_Grade_Sistem'));
    }

    public function test_hasil_yang_tidak_ada_ditolak(): void
    {
        $hantu = (int) DB::table('N_PA_Result')->max('Id_PA_Result') + 99999;

        $this->expectException(RuntimeException::class);

        $this->app->make(PaCalibrationService::class)
            ->kalibrasi($hantu, 'A', null, 'coba', 1, 'Penguji');
    }

    /* ------------------------------------------------------------------ *
     * Bangunan uji                                                        *
     * ------------------------------------------------------------------ */

    /*
     | PERHITUNGAN ULANG TIDAK MENGHAPUS KEPUTUSAN FORUM.
     |
     | `Kode_Grade_Sistem` dan `Kode_Grade_Final` sengaja dua kolom: yang pertama apa kata
     | mesin, yang kedua apa yang diputuskan forum. Sampai 7 September 2026 perhitungan ulang
     | menimpa KEDUANYA dan menyetel `Flag_Dikalibrasi` kembali ke 'T' — sehingga satu kiriman
     | susulan dari seorang penilai cukup untuk mengembalikan grade yang sudah disepakati,
     | otomatis, tanpa ada yang menekan apa pun.
     |
     | Yang diuji di sini cabang TERTAHAN, karena di situlah kalibrasi memang HARUS dilepas:
     | hasil tertahan tidak punya grade sama sekali. Yang ditegaskan bukan sekadar
     | pelepasannya, melainkan bahwa pelepasan itu MENINGGALKAN JEJAK — nilai sebelumnya
     | hanya tersimpan di riwayat kalibrasi, dan tanpa baris itu grade yang pernah disepakati
     | lenyap tanpa keterangan.
     */
    public function test_perhitungan_ulang_yang_menahan_melepas_kalibrasi_dan_mencatatnya(): void
    {
        [$participantId, $resultId] = $this->buatHasil('B', 80.0);

        DB::table('N_PA_Result')->where('Id_PA_Result', $resultId)->update([
            'Kode_Grade_Final' => 'A',
            'Flag_Dikalibrasi' => 'Y',
        ]);

        /* Siklus uji berkas ini dibuat tanpa rentang periode — cukup untuk menguji kalibrasi,
           tetapi perhitungan menolak berjalan tanpanya karena KPI YTD tidak punya batas untuk
           diakumulasi. Diisi di sini supaya yang diuji pelepasan kalibrasinya, bukan penjaga
           periode yang kebetulan menghadang lebih dulu. */
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)->update([
            'Periode_Dinilai_Dari' => date('Y').'-01-01',
            'Periode_Dinilai_Sampai' => date('Y').'-06-30',
        ]);

        /* Salinan bobot pilar untuk level peserta ini. Perhitungan menolak berjalan tanpanya,
           dan tanpa baris ini yang diuji berhenti menjadi pelepasan kalibrasi. */
        $levelId = (int) DB::table('N_PA_Participant')
            ->where('Id_PA_Participant', $participantId)->value('ID_Level_Saat_Cycle');

        if (! DB::table('N_PA_Cycle_Pillar_Weight_Snapshot')
            ->where('PA_Cycle_Id', $this->cycleId)->where('ID_Level', $levelId)->exists()) {
            DB::table('N_PA_Cycle_Pillar_Weight_Snapshot')->insert([
                'PA_Cycle_Id' => $this->cycleId,
                'ID_Level' => $levelId,
                'Bobot_KPI' => 60,
                'Bobot_Kompetensi' => 40,
            ]);
        }

        $sebelum = DB::table('N_PA_Calibration_History')->where('PA_Result_Id', $resultId)->count();

        /* Peserta ini tidak punya penugasan maupun KPI, jadi perhitungannya pasti TERTAHAN —
           itulah keadaan yang ingin diuji, bukan kebetulan. */
        app(\App\Services\KPI\PerformanceAppraisal\PaResultCalculationService::class)
            ->hitung($participantId, 1);

        $hasil = DB::table('N_PA_Result')->where('Id_PA_Result', $resultId)
            ->first(['Status_Hasil', 'Kode_Grade_Final', 'Flag_Dikalibrasi']);

        $this->assertSame('TERTAHAN', $hasil->Status_Hasil);
        $this->assertSame('T', $hasil->Flag_Dikalibrasi);
        $this->assertNull($hasil->Kode_Grade_Final);

        $jejak = DB::table('N_PA_Calibration_History')
            ->where('PA_Result_Id', $resultId)
            ->orderByDesc('Id_PA_Calibration_History')
            ->first(['Kode_Grade_Sebelum', 'Kode_Grade_Sesudah', 'Alasan']);

        $this->assertSame(
            $sebelum + 1,
            DB::table('N_PA_Calibration_History')->where('PA_Result_Id', $resultId)->count(),
            'Pelepasan kalibrasi tidak meninggalkan jejak; grade yang pernah disepakati lenyap.'
        );
        $this->assertSame('A', $jejak->Kode_Grade_Sebelum);
        $this->assertNull($jejak->Kode_Grade_Sesudah);
        $this->assertStringContainsString('dilepas otomatis', (string) $jejak->Alasan);
    }

    private function kalibrasi(
        string $grade,
        string $alasan,
        ?int $olehUserId = null,
        string $olehNama = 'Penguji',
    ): mixed {
        return $this->app->make(PaCalibrationService::class)->kalibrasi(
            $this->resultId,
            $grade,
            // Kuadran tidak digeser di uji ini; yang diperiksa jejak grade-nya.
            null,
            $alasan,
            $olehUserId ?? (int) DB::table('KPI_Users')->orderBy('Id_Users')->value('Id_Users'),
            $olehNama,
        );
    }

    private function buatSiklus(): int
    {
        $nama = 'Siklus Uji Kalibrasi '.substr(bin2hex(random_bytes(4)), 0, 8);

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => 'UJI-KAL-'.substr(bin2hex(random_bytes(4)), 0, 8),
            'Nama_Cycle' => $nama,
            'Tahun' => (int) date('Y'),
            // CLOSED: kalibrasi memang pekerjaan sesudah pengisian ditutup.
            'Status_Cycle' => 'CLOSED',
        ]);

        $cycleId = (int) DB::table('N_PA_Cycle')->where('Nama_Cycle', $nama)->value('Id_PA_Cycle');

        $this->semaiPitaNilai($cycleId);

        return $cycleId;
    }

    /**
     * Menyemai pita nilai SALINAN siklus.
     *
     * `pastikanGradeSah()` sengaja memeriksa salinan siklus, bukan master hidup — master
     * boleh berubah sesudah siklus berjalan, dan yang mengikat hasil adalah salinan yang
     * dibekukan saat siklus disiapkan. Siklus uji yang dibuat dengan satu INSERT mentah
     * karena itu punya NOL pita, dan kalibrasi ke grade apa pun ditolak.
     *
     * Disemai di sini, bukan dengan memanggil `siapkan`: penyiapan menyentuh seluruh
     * karyawan aktif dan memakan menit-menit, sedangkan yang dibutuhkan uji ini hanya
     * enam baris.
     */
    private function semaiPitaNilai(int $cycleId): void
    {
        $pita = [
            ['A', 'Istimewa', 90, 999.99, 1],
            ['B_PLUS', 'Sangat Baik', 80, 90, 2],
            ['B', 'Baik', 70, 80, 3],
            ['C_PLUS', 'Cukup', 60, 70, 4],
            ['C', 'Kurang', 50, 60, 5],
            ['D', 'Sangat Kurang', 0, 50, 6],
        ];

        foreach ($pita as [$kode, $nama, $dari, $sampai, $urutan]) {
            DB::table('N_PA_Cycle_Score_Range_Snapshot')->insert([
                'PA_Cycle_Id' => $cycleId,
                'Score_Type' => 'FINAL',
                'Score_Usage' => 'GRADE',
                'Kode_Range' => $kode,
                'Nama_Range' => $nama,
                'Nilai_Dari' => $dari,
                // Batas atas EKSKLUSIF, mengikuti ketentuan pita di modul ini.
                'Nilai_Sampai' => $sampai,
                'Urutan_Tampil' => $urutan,
            ]);
        }
    }

    /** @return array{0:int, 1:int} */
    private function buatHasil(string $grade, float $skor): array
    {
        $k = array_shift($this->stok);
        $levelId = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');

        DB::table('N_PA_Participant')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'Kode_Perusahaan' => $k->Kode_Perusahaan,
            'Kode_Karyawan' => $k->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $k->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $k->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $levelId,
            'Status_Peserta' => 'DIIKUTKAN',
        ]);

        $participantId = (int) DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('Kode_Karyawan', $k->Kode_Karyawan)
            ->value('Id_PA_Participant');

        DB::table('N_PA_Result')->insert([
            'PA_Participant_Id' => $participantId,
            'Nilai_KPI' => $skor,
            'Nilai_Kompetensi' => $skor,
            'Final_Score' => $skor,
            'Kode_Grade_Final' => $grade,
            'Kode_Grade_Sistem' => $grade,
            'Status_Hasil' => 'DIHITUNG',
        ]);

        $resultId = (int) DB::table('N_PA_Result')
            ->where('PA_Participant_Id', $participantId)
            ->value('Id_PA_Result');

        return [$participantId, $resultId];
    }
}
