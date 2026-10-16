<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Parameter\FormKpiController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Kiriman parameter KPI untuk satu tim tidak mengueri per anggota.
 *
 * `storeTeam()` menjalankan dua kueri di dalam perulangan per anggota:
 *
 *  - jadwal pengisian (`KPI_Master_Penjadwalan`) — padahal jadwalnya SAMA
 *    untuk seluruh anggota; ia diturunkan dari `$PenjadwalanData` yang
 *    ditetapkan sekali di awal permintaan. Satu kueri per orang untuk jawaban
 *    yang tak pernah berbeda.
 *  - pencarian pengajuan lama yang akan digantikan — tahunnya sama, yang
 *    berbeda hanya `Id_User`.
 *
 * Keduanya kini diambil sekali di luar perulangan: tim 20 orang turun dari 40
 * kueri menjadi 2. Diagnostik mencatat `api/v1/form-kpi/store` rata-rata
 * **13,2 detik** dengan 35 kueri.
 *
 * BATAS YANG DIAKUI, BUKAN DISEMBUNYIKAN.
 * Perbandingan di bawah menegaskan ISI dan URUTAN daftar, tetapi urutan hanya
 * bisa terbukti bila ada pengguna berdaftar lebih dari satu. Di data dev
 * sekarang tak ada satu pun — diperiksa: NOL pengguna dengan lebih dari satu
 * pengajuan aktif pada tahun yang sama. Karena itu membalik `ORDER BY` pun
 * tetap hijau; saya mencobanya dan memang begitu. Isinya teruji, urutannya
 * belum, dan itu akan berubah sendiri begitu data dev memuat kasusnya.
 */
class FormKpiStoreTeamKueriTest extends TestCase
{
    use DatabaseTransactions;

    private const BERKAS = 'app/Http/Controllers/KPI/Performance/Parameter/FormKpiController.php';

    /**
     * Bentuk massal harus menjawab SAMA PERSIS dengan bentuk lamanya — bukan
     * sekadar anggota yang sama, tetapi urutan yang sama juga.
     *
     * Urutannya bermakna: pemanggilnya mencatatnya ke log sebagai daftar
     * "digantikan", dan `Id_Pengajuan` menurun berarti yang terbaru lebih dulu.
     * Pengelompokan yang mengacak urutan akan lolos uji jumlah, tapi mengubah
     * apa yang terbaca manusia di log.
     */
    public function test_bentuk_massal_sama_dengan_bentuk_lama_per_pengguna(): void
    {
        $acuan = DB::table('KPI_Pengajuan as kp')
            ->join('KPI_Master_Penjadwalan as mp', 'mp.Id_Master_Penjadwalan', '=', 'kp.Id_Penjadwalan')
            ->whereNull('kp.Status')
            ->where('kp.Flag_Aktif', 'Y')
            ->where('mp.Flag_Form_KPI', 'Y')
            ->select('kp.Id_User', 'mp.Tahun')
            ->groupBy('kp.Id_User', 'mp.Tahun')
            ->orderByRaw('count(*) desc')
            ->first();

        if (! $acuan) {
            $this->markTestSkipped('Tak ada pengajuan parameter aktif di data dev.');
        }

        $tahun = $acuan->Tahun;

        $idPengguna = DB::table('KPI_Pengajuan as kp')
            ->join('KPI_Master_Penjadwalan as mp', 'mp.Id_Master_Penjadwalan', '=', 'kp.Id_Penjadwalan')
            ->whereNull('kp.Status')
            ->where('kp.Flag_Aktif', 'Y')
            ->where('mp.Flag_Form_KPI', 'Y')
            ->where('mp.Tahun', $tahun)
            ->distinct()
            ->limit(8)
            ->pluck('kp.Id_User')
            ->all();

        if (count($idPengguna) < 2) {
            $this->markTestSkipped('Hanya '.count($idPengguna).' pengguna; terlalu sedikit untuk membedakan massal dari per-orang.');
        }

        $m = new ReflectionMethod(FormKpiController::class, 'cariPengajuanParameterDigantikanMassal');
        $m->setAccessible(true);
        $massal = $m->invoke(app(FormKpiController::class), $idPengguna, $tahun);

        $adaIsi = 0;

        foreach ($idPengguna as $id) {
            /* Bentuk LAMA dijalankan ulang di sini — itulah pembandingnya. */
            $lama = DB::table('KPI_Pengajuan as kp')
                ->join('KPI_Master_Penjadwalan as mp', 'mp.Id_Master_Penjadwalan', '=', 'kp.Id_Penjadwalan')
                ->whereNull('kp.Status')
                ->where('kp.Id_User', $id)
                ->where('kp.Flag_Aktif', 'Y')
                ->where('mp.Tahun', $tahun)
                ->where('mp.Flag_Form_KPI', 'Y')
                ->orderByDesc('kp.Id_Pengajuan')
                ->pluck('kp.No_Transaksi')
                ->all();

            $baru = isset($massal[$id]) ? $massal[$id]->all() : [];

            $this->assertSame(
                $lama,
                $baru,
                "Daftar pengajuan digantikan untuk pengguna {$id} berbeda antara bentuk lama dan massal. "
                .'Urutannya ikut dibandingkan: Id_Pengajuan menurun berarti yang terbaru lebih dulu, '
                .'dan itulah yang terbaca di log.'
            );

            if ($lama !== []) {
                $adaIsi++;
            }
        }

        $this->assertGreaterThan(
            0,
            $adaIsi,
            'Seluruh pengguna berdaftar kosong, jadi perbandingan di atas tidak membuktikan apa pun.'
        );
    }

    /**
     * Sifat yang menjaga perbaikannya: kedua kueri itu tidak boleh kembali
     * masuk ke dalam perulangan per anggota.
     *
     * Perbandingan nilai di atas tetap hijau meski kuerinya dikembalikan ke
     * dalam perulangan — hasilnya kan memang sama. Yang membedakan hanya
     * DI MANA ia dijalankan, dan itu hanya bisa dinyatakan dengan membaca
     * bentuknya.
     */
    public function test_tidak_ada_kueri_per_anggota_di_dalam_perulangan(): void
    {
        $baris = explode("\n", (string) file_get_contents(base_path(self::BERKAS)));

        $mulai = null;
        foreach ($baris as $i => $l) {
            if (str_contains($l, 'foreach ($allData as $employeeData)')) {
                $mulai = $i;
                break;
            }
        }

        $this->assertNotNull($mulai, 'Perulangan per anggota di storeTeam() tidak ditemukan — penjaga ini kehilangan sasarannya.');

        $kedalaman = 0;
        $akhir = null;
        for ($j = $mulai; $j < count($baris); $j++) {
            $kedalaman += substr_count($baris[$j], '{') - substr_count($baris[$j], '}');
            if ($j > $mulai && $kedalaman <= 0) {
                $akhir = $j;
                break;
            }
        }

        $this->assertNotNull($akhir, 'Batas akhir perulangan tidak terbaca.');

        $pelanggaran = [];
        for ($j = $mulai; $j <= $akhir; $j++) {
            $bersih = ltrim($baris[$j]);
            if (str_starts_with($bersih, '//') || str_starts_with($bersih, '*')) {
                continue;
            }

            if (str_contains($baris[$j], "DB::table('KPI_Master_Penjadwalan')")) {
                $pelanggaran[] = ($j + 1).': kueri jadwal — jadwalnya sama untuk semua anggota';
            }

            if (str_contains($baris[$j], 'cariPengajuanParameterDigantikanMassal(')) {
                $pelanggaran[] = ($j + 1).': pencarian pengajuan lama — harus sekali untuk seluruh anggota';
            }
        }

        $this->assertSame(
            [],
            $pelanggaran,
            "Kueri yang tidak bergantung anggota kembali masuk ke dalam perulangan:\n  "
            .implode("\n  ", $pelanggaran)
            ."\n\nBiayanya tumbuh lurus mengikuti besar tim, untuk jawaban yang sama."
        );
    }
}
