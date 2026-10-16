<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Katalog\PaLevelDikecualikan;
use App\Services\KPI\PerformanceAppraisal\PaCompetencyRequirementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pengecualian level jabatan dari Performance Appraisal — sekarang DATA, bukan konstanta.
 *
 * ── Apa yang sebenarnya dijaga ────────────────────────────────────────────────
 *
 * Sampai 9 September 2026 jawabannya `private const LEVEL_FREELANCE = 21` yang DISALIN di
 * tiga service dan dibaca di ENAM titik bertugas berbeda. Kelemahannya bukan pengulangannya,
 * melainkan bahwa pengulangan itu bisa BELAH tanpa satu pun uji memerah — keenamnya benar
 * sendiri-sendiri, dan yang salah hanya hubungan di antaranya:
 *
 *   - lupa di penanda layar  -> level yang sengaja dikecualikan berteriak selamanya sebagai
 *                               "berisi orang tetapi tanpa dimensi";
 *   - lupa di penyiapan      -> orangnya tetap menjadi peserta yang tidak mungkin dinilai,
 *                               sementara setiap layar lain berlagak levelnya tidak ada.
 *
 * Karena itu uji ini TIDAK memeriksa isi tabelnya. Ia menambah pengecualian baru pada level
 * yang BERISI ORANG, lalu menegaskan seluruh permukaan ikut berubah bersamaan. Satu titik
 * yang tertinggal membuatnya merah.
 *
 * ── Cara membuatnya merah ─────────────────────────────────────────────────────
 *
 * Kembalikan salah satu pemanggilan `PaLevelDikecualikan` menjadi angka mati, atau hapus
 * `lupakan()` dari jalur yang menulis daftarnya.
 */
class LevelDikecualikanTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        PaLevelDikecualikan::lupakan();
    }

    protected function tearDown(): void
    {
        PaLevelDikecualikan::lupakan();

        parent::tearDown();
    }

    /** Level yang punya karyawan aktif — pengecualian atas level kosong tidak membuktikan apa pun. */
    private function levelBerisi(): ?int
    {
        $id = DB::table('Karyawan as k')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('k.Aktif', 'Y')
            ->whereNotIn('v.ID_Level', PaLevelDikecualikan::daftar() ?: [0])
            ->groupBy('v.ID_Level')
            ->orderByRaw('COUNT(*) DESC')
            ->value('v.ID_Level');

        return $id === null ? null : (int) $id;
    }

    private function kecualikan(int $idLevel): void
    {
        DB::table('N_PA_Level_Exclusion')->insert([
            'ID_Level' => $idLevel,
            'Alasan' => 'Dikecualikan oleh uji otomatis.',
        ]);

        PaLevelDikecualikan::lupakan();
    }

    public function test_daftar_terbaca_dari_tabel_bukan_dari_konstanta(): void
    {
        /* Seed DDL-2026-091 memindahkan FREELANCE apa adanya. Kalau baris ini hilang,
           98 orang yang selama ini tidak pernah dinilai akan mendadak ikut. */
        $this->assertTrue(
            PaLevelDikecualikan::apakah(21),
            'Level 21 (FREELANCE) harus tetap dikecualikan — seed DDL-2026-091 hilang?'
        );

        $this->assertNotNull(PaLevelDikecualikan::alasan(21), 'Pengecualian wajib berasalan.');
        $this->assertFalse(PaLevelDikecualikan::apakah(null));
    }

    public function test_memo_dilupakan_sesudah_daftarnya_berubah(): void
    {
        $level = $this->levelBerisi();

        if ($level === null) {
            $this->markTestSkipped('Tidak ada level berisi karyawan pada basis data ini.');
        }

        $this->assertFalse(PaLevelDikecualikan::apakah($level));

        $this->kecualikan($level);

        $this->assertTrue(
            PaLevelDikecualikan::apakah($level),
            'Memo tidak dilupakan: daftarnya sudah berubah tetapi jawabannya masih yang lama.'
        );
    }

    public function test_mengecualikan_level_mengubah_seluruh_permukaan_sekaligus(): void
    {
        $level = $this->levelBerisi();

        if ($level === null) {
            $this->markTestSkipped('Tidak ada level berisi karyawan pada basis data ini.');
        }

        /* Dimensinya DIKOSONGKAN lebih dulu, dan itu bukan hiasan.

           Alarm "berisi orang tetapi tanpa dimensi" hanya menyala pada level yang cacah
           dimensinya NOL. Tanpa langkah ini, level uji mana pun yang punya syarat kompetensi
           tidak akan pernah muncul di daftar alarm — sabotase pada penyaring alarmnya pun
           tetap hijau, dan penegasan di bawah tidak menguji apa pun. Terbukti: versi
           pertama uji ini LOLOS ketika penyaringnya sengaja dikembalikan ke angka mati.

           Seluruhnya di dalam transaksi uji dan dibalik sesudahnya. */
        DB::table('N_PA_Competency_Requirement')
            ->where('ID_Level', $level)
            ->where('Flag_Aktif', 'Y')
            ->update(['Flag_Aktif' => 'T']);

        $sebelum = app(PaCompetencyRequirementService::class)->matriks();
        $barisSebelum = collect($sebelum['level'])->firstWhere('id_level', $level);

        $this->assertNotNull($barisSebelum, "Level {$level} tidak ada di matriks.");
        $this->assertFalse($barisSebelum['dikecualikan_pa']);
        $this->assertSame(0, $barisSebelum['jumlah_dimensi'], 'Prasyarat uji gagal disiapkan.');

        /* Sekarang ia MEMANG diteriaki. Kalau tidak, penegasan sesudah pengecualian tidak
           membuktikan apa-apa. */
        $this->assertContains(
            $level,
            array_column($sebelum['level_berisi_tanpa_dimensi'], 'id_level'),
            'Prasyarat uji gagal: level tanpa dimensi seharusnya masuk daftar alarm.'
        );

        $this->kecualikan($level);

        $sesudah = app(PaCompetencyRequirementService::class)->matriks();
        $barisSesudah = collect($sesudah['level'])->firstWhere('id_level', $level);

        // 1. Layar Matriks Kompetensi menandainya Non-PA, berikut sebabnya.
        $this->assertTrue($barisSesudah['dikecualikan_pa'], 'Penanda layar tidak ikut berubah.');
        $this->assertSame('Dikecualikan oleh uji otomatis.', $barisSesudah['alasan_dikecualikan']);

        // 2. Alarm "berisi orang tanpa dimensi" tidak lagi menunjuknya.
        $this->assertNotContains(
            $level,
            array_column($sesudah['level_berisi_tanpa_dimensi'], 'id_level'),
            'Level yang sengaja dikecualikan masih diteriaki sebagai kekurangan konfigurasi.'
        );

        // 3. Cakupan level pada langkah penyiapan tidak lagi memuatnya.
        $dipakai = DB::table('Karyawan as k')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('k.Aktif', 'Y')
            ->whereNotIn('v.ID_Level', PaLevelDikecualikan::daftar() ?: [0])
            ->distinct()
            ->pluck('v.ID_Level')
            ->map(fn ($x) => (int) $x)
            ->all();

        $this->assertNotContains($level, $dipakai, 'Level yang dikecualikan masih ikut dihitung.');
    }

    public function test_tidak_ada_lagi_level_yang_ditulis_mati_di_service_pa(): void
    {
        /* Penjaga statis, sengaja kasar: yang dijaga bukan angkanya melainkan KEBIASAANNYA.
           Konstanta baru bernama LEVEL_* di service PA berarti seseorang menuliskan kembali
           kebijakan yang barusan dijadikan data, dan belahan yang sama akan terulang. */
        $ditemukan = [];

        $dir = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Services/KPI/PerformanceAppraisal'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($dir as $berkas) {
            if ($berkas->getExtension() !== 'php') {
                continue;
            }

            $nama = basename($berkas->getPathname());

            if ($nama === 'PaLevelDikecualikan.php') {
                continue;   // pemilik daftarnya; komentarnya memang menyebut nama lamanya
            }

            if (preg_match('/const\s+LEVEL_[A-Z_]*\s*=\s*\d+/', (string) file_get_contents($berkas->getPathname()))) {
                $ditemukan[] = $nama;
            }
        }

        $this->assertSame([], $ditemukan,
            'Ada service PA yang menuliskan id level sebagai konstanta lagi. Pengecualian level '
            .'adalah DATA di N_PA_Level_Exclusion; bacalah lewat PaLevelDikecualikan.');
    }
}
