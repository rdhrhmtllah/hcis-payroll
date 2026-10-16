<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaDistribusiService;
use App\Services\KPI\PerformanceAppraisal\PaResultPublishService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sebaran nilai akhir — distribusi grade, kuadran, dan kurva per tingkat jabatan.
 *
 * Dua keputusan di layar ini gampang terbalik kalau tidak dikunci uji:
 *
 *  1. Hasil yang BELUM terbit ikut dihitung. Kelihatannya seperti kebocoran, padahal
 *     justru maksudnya — layar ini dibaca sebelum penerbitan untuk memutuskan kalibrasi.
 *     Menyaringnya jadi "terbit saja" membuatnya kosong persis saat paling dibutuhkan.
 *  2. Kurva ditahan ketika datanya sedikit. Angkanya tetap benar; yang ditahan hanya
 *     penggambarannya, karena kurva lonceng di atas segelintir orang terlihat meyakinkan
 *     tentang sesuatu yang tidak diketahui.
 */
class DistribusiTest extends TestCase
{
    use DatabaseTransactions;

    private int $cycleId;

    private int $levelId;

    private int $levelLain;

    private array $stok = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->stok = DB::table('Karyawan')->where('Aktif', 'Y')
            ->orderBy('Kode_Karyawan')
            ->limit(40)
            ->get(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan'])
            ->all();

        $this->assertGreaterThanOrEqual(35, count($this->stok), 'Uji kurva butuh 30+ karyawan.');

        $level = DB::table('HRIS_Level')->orderBy('ID_Level')->limit(2)->pluck('ID_Level');
        $this->levelId = (int) $level[0];
        $this->levelLain = (int) $level[1];

        $this->cycleId = $this->buatSiklus();
    }

    public function test_hasil_belum_terbit_ikut_dihitung(): void
    {
        $this->buatHasil(80.0, 'B', 'DIHITUNG');
        $this->buatHasil(90.0, 'A', PaResultPublishService::STATUS_TERBIT);

        $d = $this->service()->distribusi($this->cycleId);

        $this->assertSame(2, $d['keseluruhan']['jumlah'], 'Keduanya masuk sebaran.');
        $this->assertSame(1, $d['asal_angka']['per_status']['DIHITUNG'] ?? 0);
        $this->assertSame(1, $d['asal_angka']['per_status'][PaResultPublishService::STATUS_TERBIT] ?? 0);
    }

    public function test_hasil_tertahan_di_luar_sebaran_tetapi_tetap_dilaporkan(): void
    {
        $this->buatHasil(80.0, 'B', 'DIHITUNG');
        $this->buatHasil(null, null, 'TERTAHAN');

        $d = $this->service()->distribusi($this->cycleId);

        // Tertahan tidak punya nilai akhir, jadi ia tidak bisa masuk sebaran. Tetapi
        // jumlahnya wajib disebut, kalau tidak selisihnya terhadap jumlah peserta jadi
        // teka-teki yang dicari orang berjam-jam.
        $this->assertSame(1, $d['keseluruhan']['jumlah']);
        $this->assertSame(1, $d['asal_angka']['tertahan_tanpa_nilai']);
    }

    public function test_kurva_ditahan_saat_datanya_sedikit_dan_dibuka_saat_cukup(): void
    {
        $this->buatHasil(80.0, 'B', 'DIHITUNG');

        $sedikit = $this->service()->distribusi($this->cycleId)['keseluruhan'];

        $this->assertFalse($sedikit['cukup_untuk_kurva']);
        $this->assertNotNull($sedikit['alasan_kurva_ditahan']);
        // Simpangan baku satu nilai memang tidak ada. Itu bukan kegagalan.
        $this->assertNull($sedikit['standar_deviasi']);

        // Arah sebaliknya, kalau tidak penegasan di atas juga hijau seandainya kurvanya
        // ditahan selamanya.
        for ($i = 1; $i < PaDistribusiService::MIN_UNTUK_KURVA; $i++) {
            $this->buatHasil(70.0 + ($i % 20), 'B', 'DIHITUNG');
        }

        $cukup = $this->service()->distribusi($this->cycleId)['keseluruhan'];

        $this->assertSame(PaDistribusiService::MIN_UNTUK_KURVA, $cukup['jumlah']);
        $this->assertTrue($cukup['cukup_untuk_kurva']);
        $this->assertNull($cukup['alasan_kurva_ditahan']);
        $this->assertNotNull($cukup['standar_deviasi']);
    }

    public function test_statistik_berupa_angka_bukan_teks(): void
    {
        $this->buatHasil(80.0, 'B', 'DIHITUNG');
        $this->buatHasil(90.0, 'A', 'DIHITUNG');

        $k = $this->service()->distribusi($this->cycleId)['keseluruhan'];

        /* Kolom decimal SQL Server tiba sebagai string, dan yang menerimanya JavaScript —
           tempat "9.00" > "82.50" bernilai true. Statistik yang dikirim sebagai teks akan
           terurut salah di layar. */
        $this->assertIsFloat($k['rata_rata']);
        $this->assertIsFloat($k['standar_deviasi']);
        $this->assertIsFloat($k['terendah']);
        $this->assertIsFloat($k['tertinggi']);
        $this->assertSame(85.0, $k['rata_rata']);
    }

    public function test_histogram_memakai_batas_atas_eksklusif(): void
    {
        // 80 dan 84,99 sekelompok; 85 masuk kelompok berikutnya.
        $this->buatHasil(80.0, 'B', 'DIHITUNG');
        $this->buatHasil(84.99, 'B', 'DIHITUNG');
        $this->buatHasil(85.0, 'A', 'DIHITUNG');

        $h = collect($this->service()->distribusi($this->cycleId)['histogram'])
            ->keyBy(fn ($b) => (int) $b['dari']);

        $this->assertSame(2, $h[80]['jumlah'], 'Batas atas eksklusif: 85 tidak masuk sini.');
        $this->assertSame(85.0, $h[80]['sampai']);
        $this->assertSame(1, $h[85]['jumlah']);
    }

    public function test_per_level_terpisah_dan_saringan_level_mempersempit(): void
    {
        $this->buatHasil(80.0, 'B', 'DIHITUNG', $this->levelId);
        $this->buatHasil(90.0, 'A', 'DIHITUNG', $this->levelLain);

        $d = $this->service()->distribusi($this->cycleId);
        $this->assertCount(2, $d['per_level']);

        $satu = $this->service()->distribusi($this->cycleId, ['id_level' => $this->levelId]);
        $this->assertSame(1, $satu['keseluruhan']['jumlah'], 'Saringan harus mempersempit.');
        $this->assertSame(80.0, $satu['keseluruhan']['rata_rata']);
    }

    public function test_hasil_tanpa_kuadran_tetap_dilaporkan(): void
    {
        $this->buatHasil(80.0, 'B', 'DIHITUNG');

        $kuadran = $this->service()->distribusi($this->cycleId)['kuadran'];

        // Nilai tanpa kuadran berarti salah satu pilar tertahan — keadaan yang justru
        // perlu dilihat admin, bukan dibuang dari grafik.
        $this->assertCount(1, $kuadran);
        $this->assertSame('(Belum berkuadran)', $kuadran[0]['kode_kuadran']);
    }

    /* ------------------------------------------------------------------ *
     * Bangunan uji                                                        *
     * ------------------------------------------------------------------ */

    /**
     * Peringkat kinerja ikut GRADE (dua pita teratas/terbawah), bukan nilai akhir mati;
     * potensi ikut kuadran HAV berkompetensi TINGGI, bukan nomor kotak.
     */
    public function test_sorotan_talenta_ikut_grade_dan_hav_bukan_angka_mati(): void
    {
        $kuadran = DB::table('N_PA_Hav_Quadrant')->where('Flag_Aktif', 'Y')
            ->pluck('Id_PA_Hav_Quadrant', 'Kode_Kuadran');

        $aRendah = $this->buatHasil(70.0, 'A', 'DIHITUNG');      // grade teratas walau nilainya 70
        $baik = $this->buatHasil(79.0, 'B', 'DIHITUNG');         // "Baik": bukan teratas, bukan terbawah
        $cTinggi = $this->buatHasil(95.0, 'C', 'DIHITUNG');      // grade terbawah walau nilainya 95
        $potensi = $this->buatHasil(75.0, 'B', 'DIHITUNG');
        $problem = $this->buatHasil(74.0, 'B', 'DIHITUNG');

        DB::table('N_PA_Result')->where('Id_PA_Result', $potensi)
            ->update(['PA_Hav_Quadrant_Id' => $kuadran['RAW_DIAMOND'], 'Nilai_Kompetensi' => 90]);
        DB::table('N_PA_Result')->where('Id_PA_Result', $problem)
            ->update(['PA_Hav_Quadrant_Id' => $kuadran['PROBLEM_EMPLOYEE'], 'Nilai_Kompetensi' => 80]);

        $t = $this->service()->distribusi($this->cycleId)['talent_highlights'];
        $nilai = fn (string $daftar) => array_column($t[$daftar], 'skor');

        $this->assertSame([70.0], $nilai('top_performers'));
        $this->assertSame([95.0], $nilai('need_improvement'), 'Nilai 79 bergrade Baik tidak boleh tercap perlu pembinaan.');
        $this->assertSame([75.0], $nilai('high_potential'), 'Problem Employee bukan potensi tinggi.');
        $this->assertSame(['A', 'B_PLUS'], $t['kriteria']['grade_teratas']);
    }

    private function service(): PaDistribusiService
    {
        return $this->app->make(PaDistribusiService::class);
    }

    private function buatSiklus(): int
    {
        $nama = 'Siklus Uji Distribusi '.substr(bin2hex(random_bytes(4)), 0, 8);

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => 'UJI-DIST-'.substr(bin2hex(random_bytes(4)), 0, 8),
            'Nama_Cycle' => $nama,
            'Tahun' => (int) date('Y'),
            'Status_Cycle' => 'CLOSED',
        ]);

        return (int) DB::table('N_PA_Cycle')->where('Nama_Cycle', $nama)->value('Id_PA_Cycle');
    }

    private function buatHasil(?float $skor, ?string $grade, string $status, ?int $levelId = null): int
    {
        $k = array_shift($this->stok);
        $this->assertNotNull($k, 'Stok karyawan uji habis.');

        DB::table('N_PA_Participant')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'Kode_Perusahaan' => $k->Kode_Perusahaan,
            'Kode_Karyawan' => $k->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $k->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $k->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $levelId ?? $this->levelId,
            'Nama_Level_Saat_Cycle' => 'LEVEL '.($levelId ?? $this->levelId),
            'Status_Peserta' => 'DIIKUTKAN',
        ]);

        $participantId = (int) DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('Kode_Karyawan', $k->Kode_Karyawan)
            ->value('Id_PA_Participant');

        DB::table('N_PA_Result')->insert([
            'PA_Participant_Id' => $participantId,
            'Final_Score' => $skor,
            'Kode_Grade_Final' => $grade,
            'Kode_Grade_Sistem' => $grade,
            'Status_Hasil' => $status,
        ]);

        return (int) DB::table('N_PA_Result')
            ->where('PA_Participant_Id', $participantId)
            ->value('Id_PA_Result');
    }
}
