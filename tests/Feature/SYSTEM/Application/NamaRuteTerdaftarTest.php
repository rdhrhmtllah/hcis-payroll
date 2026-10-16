<?php

namespace Tests\Feature\SYSTEM\Application;

use FilesystemIterator;
use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Setiap route('x') harus menunjuk nama rute yang benar-benar terdaftar.
 *
 * ── Cacat yang ditutup ───────────────────────────────────────────────────────
 *
 * Sudah ada penjaga untuk arah SEBALIKNYA — rute yang menunjuk metode tak ada.
 * Pada 6 September penjaga itu dipakai membersihkan 31 rute rusak, dan salah
 * satunya:
 *
 *   Route::get('/crossFeedbackDetail/{no_review}',
 *       [CrossDivisionReviewController::class, 'ESS/Workflow/CrossDivision/feedbackDetail'])
 *       ->name('cross.feedback.Detail');
 *
 * Nama metodenya tertimpa jalur view Inertia, jadi rutenya memang menunjuk
 * metode yang tidak ada dan pantas ditandai. Tetapi menghapusnya MEMINDAHKAN
 * kerusakan ke hulu: cross/feedback.blade.php memanggil
 * route('cross.feedback.Detail') di dalam @foreach, sehingga sejak penghapusan
 * itu halaman DAFTARnya yang melempar RouteNotFoundException — dan hanya kalau
 * ada data, karena @foreach atas larik kosong tak pernah menjalankan isinya.
 * Satu kerusakan pada satu tombol berubah menjadi satu kerusakan pada seluruh
 * halaman, dan tak ada penjaga yang melihatnya karena tak ada yang memeriksa
 * arah ini.
 *
 * ── Yang TIDAK dijaga ────────────────────────────────────────────────────────
 *
 * Hanya nama literal. route($var) dan route("prefix.{$x}") tidak bisa dinilai
 * tanpa menjalankan kodenya. Sisi JavaScript juga di luar cakupan: Ziggy tidak
 * terpasang di proyek ini dan @routes tidak pernah dipancarkan, jadi route()
 * memang tidak ada di browser — keenam pemanggilnya di resources/js sudah
 * dijaga typeof/window dengan URL cadangan.
 */
class NamaRuteTerdaftarTest extends TestCase
{
    /** @var list<string> */
    private const AKAR = ['app', 'routes', 'resources/views'];

    public function test_setiap_nama_rute_yang_dirujuk_benar_benar_terdaftar(): void
    {
        $terdaftar = array_keys(Route::getRoutes()->getRoutesByName());

        $this->assertNotEmpty($terdaftar, 'Tidak ada satu rute bernama pun — rutenya tidak termuat.');

        $hilang = [];

        foreach (self::AKAR as $akar) {
            foreach ($this->berkasPhp(base_path($akar)) as $jalur) {
                $isi = file_get_contents($jalur);

                if (! preg_match_all('/(?<![A-Za-z0-9_>:])route\(\s*[\x27\x22]([a-zA-Z0-9_\-.]+)[\x27\x22]/', $isi, $cocok, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                    continue;
                }

                foreach ($cocok as $satu) {
                    $nama = $satu[1][0];

                    if (in_array($nama, $terdaftar, true)) {
                        continue;
                    }

                    $baris = substr_count(substr($isi, 0, $satu[0][1]), "\n") + 1;
                    $rel = str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], $jalur);
                    $hilang[] = sprintf("'%s' di %s:%d", $nama, $rel, $baris);
                }
            }
        }

        sort($hilang);

        $this->assertSame(
            [],
            $hilang,
            'route() menunjuk nama yang tidak terdaftar. Pemanggilan semacam ini melempar '
            .'RouteNotFoundException saat barisnya dijalankan — di dalam @foreach itu berarti '
            .'halamannya baru rusak ketika ada data, jadi jangan andalkan halaman kosong yang '
            ."terlihat sehat. Daftarkan rutenya, atau perbaiki nama yang dirujuk.\n  "
            .implode("\n  ", $hilang)
        );
    }

    /** @return list<string> */
    private function berkasPhp(string $akar): array
    {
        $hasil = [];
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($akar, FilesystemIterator::SKIP_DOTS));

        foreach ($rii as $berkas) {
            if ($berkas->getExtension() === 'php') {
                $hasil[] = $berkas->getPathname();
            }
        }

        return $hasil;
    }
}
