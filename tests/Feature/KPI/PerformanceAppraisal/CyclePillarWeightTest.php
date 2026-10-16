<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaCyclePillarWeightService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\TestCase;

/**
 * Bobot pilar per siklus (K23) — satu-satunya salinan yang boleh disunting sesudah kunci.
 *
 * Dua hal yang diuji di sini punya bobot berbeda. Penjumlahan 100 dan larangan negatif
 * adalah aturan biasa: salah, ditolak, selesai. Larangan menyunting ketika sudah ada hasil
 * TERBIT bukan aturan biasa — ia menjaga satu-satunya keadaan yang TIDAK BISA DIURUNGKAN,
 * karena hasil terbit tidak pernah dihitung ulang. Karena itu penjaga itu diuji dua arah:
 * ada hasil terbit maka ditolak, hasil terbitnya dicabut maka diterima.
 */
class CyclePillarWeightTest extends TestCase
{
    use DatabaseTransactions;

    private int $cycleId;

    private int $levelId;

    private int $levelLain;

    /**
     * Karyawan NYATA yang dipakai bergantian, satu per peserta.
     *
     * `N_PA_Participant` punya FK ke `Karyawan`, jadi kode karangan seperti "A1-6e1"
     * ditolak. Sekaligus unique (siklus, perusahaan, karyawan) melarang orang yang sama
     * dua kali dalam satu siklus - maka yang dibutuhkan adalah beberapa orang BERBEDA
     * yang benar-benar ada, bukan satu orang bernama macam-macam.
     */
    private array $stokKaryawan = [];

    protected function setUp(): void
    {
        parent::setUp();

        $level = DB::table('HRIS_Level')->orderBy('ID_Level')->limit(2)->pluck('ID_Level');
        $this->assertCount(2, $level, 'Uji ini butuh dua level.');

        $this->levelId = (int) $level[0];
        $this->levelLain = (int) $level[1];

        $this->stokKaryawan = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->orderBy('Kode_Karyawan')
            ->limit(6)
            ->get(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan'])
            ->all();

        $this->assertGreaterThanOrEqual(3, count($this->stokKaryawan),
            'Uji ini butuh beberapa karyawan aktif yang berbeda.');

        $this->cycleId = $this->buatSiklus('DRAFT');
        $this->buatSalinan($this->levelId, 80, 20);
        $this->buatSalinan($this->levelLain, 60, 40);
    }

    /* ------------------------------------------------------------------ *
     * Aturan biasa                                                        *
     * ------------------------------------------------------------------ */

    public function test_jumlah_bukan_seratus_ditolak(): void
    {
        $this->tolak(
            ['bobot_kpi' => 60, 'bobot_kompetensi' => 60],
            'berjumlah 120',
        );

        // Dan yang berjumlah 100 diterima, supaya penolakan di atas terbukti bergantung
        // pada jumlahnya, bukan pada sesuatu yang lain.
        $hasil = $this->service()->simpan($this->cycleId, $this->levelId, [
            'bobot_kpi' => 70, 'bobot_kompetensi' => 30,
        ], 1);

        $this->assertTrue($hasil['berubah']);
        $this->assertSame(70.0, $hasil['bobot_kpi']);
    }

    public function test_bobot_negatif_ditolak(): void
    {
        // Jumlahnya tetap 100, jadi yang menolak pasti penjaga negatifnya.
        $this->tolak(
            ['bobot_kpi' => -10, 'bobot_kompetensi' => 110],
            'tidak boleh negatif',
        );
    }

    public function test_level_tanpa_salinan_ditolak_dengan_jalan_keluarnya(): void
    {
        $levelAsing = (int) DB::table('HRIS_Level')->max('ID_Level') + 9999;

        try {
            $this->service()->simpan($this->cycleId, $levelAsing, [
                'bobot_kpi' => 50, 'bobot_kompetensi' => 50,
            ], 1);
            $this->fail('Level tanpa salinan seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('belum punya salinan bobot pilar', $e->getMessage());

            /* Pesannya harus menyebut apa yang BISA DILAKUKAN, bukan hanya apa yang salah.
               Ditegaskan pada tindakannya ("Salin dari master") dan tempatnya ("Aturan
               Penilai"), bukan pada kalimat utuhnya: versi sebelumnya memaku frasa
               "siapkan siklusnya" dan memerah ketika pesannya justru DIPERBAIKI menjadi
               lebih menunjuk. Uji yang memerah karena perbaikan mengajari orang
               mengabaikannya. */
            $this->assertStringContainsString('Salin dari master', $e->getMessage());
            $this->assertStringContainsString('Aturan Penilai', $e->getMessage());
        }
    }

    /* ------------------------------------------------------------------ *
     * Yang tidak bisa diurungkan                                          *
     * ------------------------------------------------------------------ */

    public function test_hasil_terbit_menghalangi_penyuntingan_dan_pencabutannya_membuka_lagi(): void
    {
        $resultId = $this->buatHasil($this->levelId, 'TERBIT');

        try {
            $this->service()->simpan($this->cycleId, $this->levelId, [
                'bobot_kpi' => 70, 'bobot_kompetensi' => 30, 'alasan' => 'coba',
            ], 1);
            $this->fail('Level dengan hasil TERBIT seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('TERBIT', $e->getMessage());
            // Menolak tanpa menyebut jalan keluarnya membuat orang buntu.
            $this->assertStringContainsString('kalibrasi', $e->getMessage());
        }

        /* Arah sebaliknya. Tanpa ini, penegasan di atas juga akan hijau seandainya
           servicenya menolak SEGALANYA. */
        DB::table('N_PA_Result')->where('Id_PA_Result', $resultId)
            ->update(['Status_Hasil' => 'DIHITUNG']);

        $hasil = $this->service()->simpan($this->cycleId, $this->levelId, [
            'bobot_kpi' => 70, 'bobot_kompetensi' => 30, 'alasan' => 'coba',
        ], 1);

        $this->assertTrue($hasil['berubah']);
    }

    public function test_hasil_terbit_level_lain_tidak_ikut_mengunci(): void
    {
        // Penjaganya per LEVEL, bukan per siklus. Kalau salah, satu hasil terbit akan
        // membekukan bobot seluruh level dan tidak ada yang mengerti kenapa.
        $this->buatHasil($this->levelLain, 'TERBIT');

        $hasil = $this->service()->simpan($this->cycleId, $this->levelId, [
            'bobot_kpi' => 70, 'bobot_kompetensi' => 30,
        ], 1);

        $this->assertTrue($hasil['berubah']);
    }

    public function test_hasil_dihitung_dilaporkan_sebagai_perlu_hitung_ulang(): void
    {
        $this->buatHasil($this->levelId, 'DIHITUNG');
        $this->buatHasil($this->levelId, 'DIHITUNG');

        $hasil = $this->service()->simpan($this->cycleId, $this->levelId, [
            'bobot_kpi' => 70, 'bobot_kompetensi' => 30,
        ], 1);

        $this->assertSame(2, $hasil['hasil_perlu_hitung_ulang']);
        $this->assertStringContainsString('perlu dihitung ulang', $hasil['pesan']);
    }

    public function test_bobot_yang_tidak_berubah_tidak_menuntut_hitung_ulang(): void
    {
        $this->buatHasil($this->levelId, 'DIHITUNG');

        // Nilai yang sama dengan yang tersimpan. Tidak ada yang menjadi basi.
        $hasil = $this->service()->simpan($this->cycleId, $this->levelId, [
            'bobot_kpi' => 80, 'bobot_kompetensi' => 20,
        ], 1);

        $this->assertFalse($hasil['berubah']);
        $this->assertSame(0, $hasil['hasil_perlu_hitung_ulang']);
    }

    /* ------------------------------------------------------------------ *
     * Alasan dan gerbang                                                  *
     * ------------------------------------------------------------------ */

    public function test_alasan_wajib_hanya_sesudah_siklus_dikunci(): void
    {
        // DRAFT: tanpa alasan pun boleh, karena bobot masih bagian dari penyiapan.
        $this->service()->simpan($this->cycleId, $this->levelId, [
            'bobot_kpi' => 70, 'bobot_kompetensi' => 30,
        ], 1);

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)
            ->update(['Status_Cycle' => 'LOCKED']);

        $this->tolak(
            ['bobot_kpi' => 60, 'bobot_kompetensi' => 40],
            'wajib menyertakan alasan',
        );

        // Dengan alasan, diterima — sehingga penolakan di atas terbukti soal alasannya.
        $hasil = $this->service()->simpan($this->cycleId, $this->levelId, [
            'bobot_kpi' => 60, 'bobot_kompetensi' => 40, 'alasan' => 'penyesuaian arahan direksi',
        ], 1);

        $this->assertTrue($hasil['berubah']);
        $this->assertSame('penyesuaian arahan direksi', DB::table('N_PA_Cycle_Pillar_Weight_Snapshot')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('ID_Level', $this->levelId)
            ->value('Alasan_Perubahan'));
    }

    public function test_siklus_published_menolak_penyuntingan(): void
    {
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)
            ->update(['Status_Cycle' => 'PUBLISHED']);

        $this->tolak(
            ['bobot_kpi' => 70, 'bobot_kompetensi' => 30, 'alasan' => 'coba'],
            'tidak diizinkan',
        );
    }

    /* ------------------------------------------------------------------ *
     * Regresi                                                             *
     * ------------------------------------------------------------------ */

    public function test_cacah_hasil_per_level_tidak_pernah_nol_palsu(): void
    {
        /* REGRESI. Versi pertama memakai pluck(DB::raw('COUNT(*)'), ...), yang
           menghasilkan kolom bernama harfiah "COUNT(*)" sehingga petanya selalu KOSONG.
           Kegagalannya tidak melempar apa pun — ia melaporkan nol hasil terbit, yang
           terbaca sebagai "boleh disunting" untuk baris yang justru terkunci. Angka nol
           yang meyakinkan adalah kegagalan yang paling lama tidak diperbaiki. */
        $this->buatHasil($this->levelId, 'TERBIT');
        $this->buatHasil($this->levelId, 'TERBIT');
        $this->buatHasil($this->levelLain, 'DIHITUNG');

        $daftar = collect($this->service()->daftar($this->cycleId)['bobot'])
            ->keyBy(fn ($b) => (int) $b['ID_Level']);

        $this->assertSame(2, $daftar[$this->levelId]['hasil_terbit']);
        $this->assertFalse($daftar[$this->levelId]['dapat_disunting']);
        $this->assertNotNull($daftar[$this->levelId]['alasan_terkunci']);

        $this->assertSame(0, $daftar[$this->levelLain]['hasil_terbit']);
        $this->assertSame(1, $daftar[$this->levelLain]['hasil_dihitung']);
        $this->assertTrue($daftar[$this->levelLain]['dapat_disunting']);
    }

    /* ------------------------------------------------------------------ *
     * Bangunan uji                                                        *
     * ------------------------------------------------------------------ */

    private function service(): PaCyclePillarWeightService
    {
        return $this->app->make(PaCyclePillarWeightService::class);
    }

    /**
     * @param  array<string, mixed>  $nilai
     */
    private function tolak(array $nilai, string $potonganPesan): void
    {
        try {
            $this->service()->simpan($this->cycleId, $this->levelId, $nilai, 1);
            $this->fail("Seharusnya ditolak dengan pesan memuat '{$potonganPesan}'.");
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($potonganPesan, $e->getMessage());
        }
    }

    private function buatSiklus(string $status): int
    {
        $nama = 'Siklus Uji Bobot Pilar '.substr(bin2hex(random_bytes(4)), 0, 8);

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => 'UJI-PILAR-'.substr(bin2hex(random_bytes(4)), 0, 8),
            'Nama_Cycle' => $nama,
            'Tahun' => (int) date('Y'),
            'Status_Cycle' => $status,
        ]);

        return (int) DB::table('N_PA_Cycle')->where('Nama_Cycle', $nama)->value('Id_PA_Cycle');
    }

    private function buatSalinan(int $levelId, float $kpi, float $kompetensi): void
    {
        DB::table('N_PA_Cycle_Pillar_Weight_Snapshot')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'ID_Level' => $levelId,
            'Bobot_KPI' => $kpi,
            'Bobot_Kompetensi' => $kompetensi,
        ]);
    }

    private function buatHasil(int $levelId, string $status): int
    {
        $karyawan = array_shift($this->stokKaryawan);

        $this->assertNotNull($karyawan, 'Stok karyawan uji habis; tambah limit di setUp().');

        DB::table('N_PA_Participant')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'Kode_Perusahaan' => $karyawan->Kode_Perusahaan,
            // Kode ASLI. Ada FK ke Karyawan, jadi kode karangan pasti ditolak.
            'Kode_Karyawan' => $karyawan->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $karyawan->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $karyawan->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $levelId,
            'Status_Peserta' => 'DIIKUTKAN',
        ]);

        $participantId = (int) DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->orderByDesc('Id_PA_Participant')
            ->value('Id_PA_Participant');

        DB::table('N_PA_Result')->insert([
            'PA_Participant_Id' => $participantId,
            'Final_Score' => 80.00,
            'Status_Hasil' => $status,
        ]);

        return (int) DB::table('N_PA_Result')
            ->where('PA_Participant_Id', $participantId)
            ->value('Id_PA_Result');
    }
}
