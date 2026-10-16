<?php

namespace Tests\Feature\SYSTEM;

use App\Services\SYSTEM\Application\Security\GerbangToken;
use Illuminate\Support\Facades\Route;
use ReflectionFunction;
use Tests\TestCase;

/**
 * Setiap rute TULIS punya gerbang — middleware auth, middleware kustom, atau
 * pemeriksaan kredensial di penanganannya sendiri.
 *
 * ── Kenapa penjaga ini ada ───────────────────────────────────────────────────
 *
 * Sebagian endpoint dipanggil mesin, bukan orang: pemicu penjadwal, webhook
 * Cloud Tasks, pemeriksa koneksi. Mereka tidak bisa memakai middleware `auth`,
 * jadi masing-masing memeriksa bearer token sendiri. Pola itu bekerja — tetapi
 * ia bertumpu pada setiap penulis endpoint berikutnya mengingatnya.
 *
 * Yang lupa memang ada: POST /trigger-task, sebuah closure tanpa gerbang apa pun
 * yang mengantre Cloud Task ke URL publik dengan muatan uji. Ia lolos dari
 * pemindaian pertama saya justru karena berupa CLOSURE, bukan controller — jadi
 * penjaga ini sengaja membaca closure lewat refleksi.
 *
 * ── Yang TIDAK dijamin penjaga ini ───────────────────────────────────────────
 *
 * Ia memeriksa ADANYA gerbang, bukan benarnya. Endpoint yang memanggil
 * bearerToken() lalu mengabaikan hasilnya tetap lolos. Yang dicegah di sini
 * adalah endpoint yang tidak memeriksa apa pun — kelas kesalahan yang
 * benar-benar terjadi.
 */
class RuteTulisSelaluBergerbangTest extends TestCase
{
    /**
     * Awalan URI milik paket pihak ketiga, dinilai terpisah dari kode ini.
     *
     * log-viewer memakai middleware otorisasinya sendiri; _ignition hanya hidup
     * saat RunnableSolutions menyala (mati di produksi); handle-task diverifikasi
     * paket Cloud Tasks lewat token OIDC-nya sendiri.
     */
    private const PAKET_PIHAK_KETIGA = ['log-viewer/', '_ignition/', 'handle-task'];

    /**
     * Penanganan yang tidak menulis apa pun.
     *
     * Route::redirect() terdaftar untuk SEMUA kata kerja, jadi ia muncul sebagai
     * rute tulis padahal hanya membalas 302 tanpa menyentuh basis data.
     */
    private const BUKAN_PENULISAN = ['Illuminate\Routing\RedirectController'];

    /** Middleware yang TIDAK dihitung sebagai gerbang. */
    private const BUKAN_GERBANG = ['api', 'web'];

    /** Tanda bahwa penanganannya memeriksa kredensial sendiri. */
    private const POLA_GERBANG = [
        'bearerToken', 'GerbangToken::sah', 'X-Scheduler-Token', 'hash_equals',
        'abort(403', 'abort(401', '], 403)', '], 401)', 'Unauthorized',
    ];

    private function punyaGerbangMiddleware(array $mw): bool
    {
        foreach ($mw as $x) {
            if (! is_string($x)) {
                continue;
            }
            if (preg_match('/^(auth|auth:|auth\.)/', $x) || str_contains($x, 'Authenticate')) {
                return true;
            }
            if (! in_array($x, self::BUKAN_GERBANG, true) && ! str_contains($x, 'Illuminate\\')) {
                return true;   // middleware kustom: gerbang tersendiri
            }
        }

        return false;
    }

    private function sumberPenanganan($rute): ?string
    {
        $aksi = $rute->getAction('controller');

        if (is_string($aksi) && str_contains($aksi, '@')) {
            [$kelas, $metode] = explode('@', $aksi, 2);
            if (! class_exists($kelas)) {
                return null;
            }
            $berkas = (new \ReflectionClass($kelas))->getFileName();
            if (! $berkas || ! is_file($berkas)) {
                return null;
            }
            $isi = (string) file_get_contents($berkas);
            if (! preg_match('/\n[ \t]*(?:public|protected|private) function '
                .preg_quote($metode, '/').'\s*\(/', $isi, $m, PREG_OFFSET_CAPTURE)) {
                return null;
            }
            $buka = strpos($isi, '{', $m[0][1]);
            $d = 0;
            for ($i = $buka; $i < strlen($isi); $i++) {
                if ($isi[$i] === '{') {
                    $d++;
                } elseif ($isi[$i] === '}') {
                    $d--;
                    if ($d === 0) {
                        return substr($isi, $m[0][1], $i - $m[0][1] + 1);
                    }
                }
            }

            return null;
        }

        /*
         * CLOSURE. Inilah yang dilewatkan pemindaian pertama — dan justru di
         * sinilah endpoint tanpa gerbang itu bersembunyi.
         */
        $uses = $rute->getAction('uses');
        if (! $uses instanceof \Closure) {
            return null;
        }
        $r = new ReflectionFunction($uses);
        $berkas = $r->getFileName();
        if (! $berkas || ! is_file($berkas)) {
            return null;
        }
        $baris = file($berkas);

        return implode('', array_slice($baris, $r->getStartLine() - 1,
            $r->getEndLine() - $r->getStartLine() + 1));
    }

    public function test_setiap_rute_tulis_punya_gerbang(): void
    {
        $telanjang = [];

        foreach (Route::getRoutes() as $rute) {
            $metode = array_diff($rute->methods(), ['HEAD']);
            if (! array_intersect($metode, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                continue;
            }

            $uri = $rute->uri();
            foreach (self::PAKET_PIHAK_KETIGA as $awalan) {
                if (str_starts_with($uri, $awalan)) {
                    continue 2;
                }
            }

            $aksiKelas = (string) $rute->getAction('controller');
            foreach (self::BUKAN_PENULISAN as $abai) {
                if (str_contains($aksiKelas, $abai)) {
                    continue 2;
                }
            }

            if ($this->punyaGerbangMiddleware($rute->gatherMiddleware())) {
                continue;
            }

            /*
             * Bendera, bukan `continue N`: hitungan levelnya mudah salah
             * (blok `if` tidak dihitung) dan salahnya berupa fatal error, bukan
             * uji merah.
             */
            $sumber = $this->sumberPenanganan($rute);
            $bergerbangDiPenanganan = false;
            if ($sumber !== null) {
                foreach (self::POLA_GERBANG as $p) {
                    if (str_contains($sumber, $p)) {
                        $bergerbangDiPenanganan = true;
                        break;
                    }
                }
            }
            if ($bergerbangDiPenanganan) {
                continue;
            }

            $telanjang[] = implode('|', $metode).' /'.$uri
                .'  ('.(is_string($rute->getAction('controller'))
                    ? str_replace('App\Http\Controllers\\', '', $rute->getAction('controller'))
                    : 'closure').')';
        }

        sort($telanjang);

        $this->assertSame(
            [],
            $telanjang,
            "Rute TULIS tanpa gerbang apa pun:\n  ".implode("\n  ", $telanjang)."\n"
            .'Endpoint yang tidak lewat middleware auth WAJIB memeriksa kredensialnya '
            .'sendiri. Pakai GerbangToken::sah() supaya pemeriksaannya gagal-tertutup '
            .'dan berwaktu-tetap.',
        );
    }

    public function test_tidak_ada_rahasia_cadangan_di_pemanggilan_config(): void
    {
        /*
         * `config('...TOKEN', '<literal panjang>')` menaruh rahasia di dalam kode
         * dan membuatnya berlaku diam-diam bila kuncinya tak diset. Yang
         * diperiksa di sini KODE AKTIF di routes/ dan app/ — bukan
         * config/services.php, yang defaultnya keputusan operasional tersendiri.
         */
        $pelanggar = [];

        foreach (['routes', 'app'] as $akar) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($akar), \FilesystemIterator::SKIP_DOTS),
            );
            foreach ($it as $berkas) {
                if ($berkas->getExtension() !== 'php') {
                    continue;
                }
                $isi = (string) php_strip_whitespace($berkas->getPathname());
                if (preg_match('/config\(\s*[\'"][^\'"]*(TOKEN|SECRET)[^\'"]*[\'"]\s*,\s*[\'"][A-Za-z0-9]{16,}[\'"]/', $isi)) {
                    $pelanggar[] = str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'],
                        $berkas->getPathname());
                }
            }
        }

        sort($pelanggar);

        $this->assertSame(
            [],
            $pelanggar,
            "Rahasia cadangan tertulis di pemanggilan config():\n  ".implode("\n  ", $pelanggar)."\n"
            .'Nilai cadangan semacam itu berlaku diam-diam ketika konfigurasinya belum '
            .'diisi. Pakai GerbangToken::sah(), yang menolak bila konfigurasinya kosong.',
        );
    }

    public function test_gerbang_menolak_ketika_konfigurasinya_kosong(): void
    {
        /*
         * INI YANG MEMBUAT PERBAIKANNYA BUKAN SEKADAR KOSMETIK.
         *
         * Membuang literal cadangan begitu saja akan membuat gerbangnya
         * GAGAL-TERBUKA: config() jadi null, bearerToken() juga null pada
         * permintaan tanpa Authorization, dan `null !== null` bernilai false —
         * yang artinya lolos.
         */
        config(['services.uji_gerbang' => null]);

        $this->assertFalse(
            GerbangToken::sah(null, 'services.uji_gerbang'),
            'Konfigurasi kosong + token kosong harus DITOLAK. Inilah kondisi yang '.
            'membuat perbaikan naif berubah menjadi gerbang terbuka.',
        );
        $this->assertFalse(GerbangToken::sah('apa-saja', 'services.uji_gerbang'));

        config(['services.uji_gerbang' => '']);
        $this->assertFalse(GerbangToken::sah('', 'services.uji_gerbang'));
    }

    public function test_gerbang_menerima_hanya_token_yang_tepat(): void
    {
        config(['services.uji_gerbang' => 'token-benar-untuk-uji']);

        $this->assertTrue(GerbangToken::sah('token-benar-untuk-uji', 'services.uji_gerbang'));

        foreach ([null, '', 'token-benar-untuk-uj', 'token-benar-untuk-ujiX', 'TOKEN-BENAR-UNTUK-UJI'] as $salah) {
            $this->assertFalse(
                GerbangToken::sah($salah, 'services.uji_gerbang'),
                'Token '.var_export($salah, true).' seharusnya ditolak.',
            );
        }
    }

    public function test_perbandingannya_berwaktu_tetap(): void
    {
        /*
         * hash_equals dipakai karena endpoint ini bisa dipanggil berulang tanpa
         * batas oleh siapa pun yang tahu URL-nya. Yang ditegaskan bentuknya —
         * mengukur waktu di dalam uji akan berayun-ayun dan menghasilkan uji
         * yang kadang merah tanpa sebab.
         */
        /*
         * Komentar dibuang lebih dulu: docblock kelas itu MENJELASKAN kenapa
         * `!==` bermasalah, dan versi pertama penjaga ini merah karena membaca
         * penjelasannya sendiri.
         */
        $kode = (string) php_strip_whitespace(base_path(
            'app/Services/SYSTEM/Application/Security/GerbangToken.php',
        ));

        $this->assertStringContainsString('hash_equals(', $kode);
        $this->assertStringNotContainsString('!==', $kode, 'Perbandingan langsung kembali dipakai.');
    }

    public function test_berkas_penjaga_tidak_kosong(): void
    {
        $this->assertGreaterThan(6000, (int) filesize(__FILE__), 'Berkas penjaga menyusut drastis.');
    }
}
