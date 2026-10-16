<?php

namespace Tests\Feature\SYSTEM;

use Tests\TestCase;

/**
 * Validasi masukan tidak boleh tertelan oleh `catch` generik.
 *
 * MENGAPA INI PENTING.
 * `ValidationException` adalah turunan `\Exception`. Selama `$request->validate()`
 * dipanggil DI DALAM `try` yang ditutup `catch (\Exception)` atau `catch (\Throwable)`,
 * ia tertangkap di sana dan dua hal buruk terjadi sekaligus:
 *
 *   1. Kesalahan PEMANGGIL (422, "kode_karyawan wajib diisi") berubah menjadi
 *      kegagalan SISTEM (500, "Gagal mengambil data"). Pemanggil tak pernah tahu
 *      apa yang kurang, dan tak ada yang bisa ia perbaiki sendiri.
 *   2. Tiap salah ketik parameter tercatat sebagai `Log::error`. Log kegagalan
 *      sistem terisi hal yang bukan kegagalan sistem, dan galat sungguhan
 *      tenggelam di antaranya.
 *
 * Cacat ini ditemukan lewat penyisiran rute ESS · Attendance · Leave: enam dari
 * 53 rute GET menjawab 500, dan semuanya karena sebab yang sama. Penyisiran
 * lanjutan menemukan 38 titik lintas ESS, KPI, LMS, dan SYSTEM.
 *
 * DUA BENTUK YANG DITERIMA.
 * Yang mana dipakai ditentukan oleh apakah validasinya boleh berpindah tempat.
 *
 *   a. Validasi dinaikkan ke LUAR try — bentuk baku, dipakai bila validasi memang
 *      pekerjaan pertama metode itu:
 *
 *          $validated = $request->validate([...]);
 *
 *          try { ... } catch (\Exception $e) { ... }
 *
 *   b. `catch (ValidationException $e)` tersendiri SEBELUM catch generik —
 *      dipakai bila validasi TIDAK boleh pindah: transaksi sudah terbuka, ada
 *      gerbang perizinan yang harus dijawab lebih dulu, atau aturannya berlaku
 *      pada `$request` sesudah dimodifikasi.
 *
 * MENGAPA PENJAGA STRUKTURAL, BUKAN PENJAGA PERILAKU.
 * Menulis uji perilaku per titik menuntut otentikasi, perizinan, dan fikstur
 * untuk puluhan endpoint lintas modul. Penjaga ini menempuh jalan lain: ia
 * membaca kodenya. Yang dijaga bukan "422 dikembalikan dengan benar" melainkan
 * sesuatu yang lebih sempit dan bisa diperiksa mesin — TIDAK ADA `->validate()`
 * yang berada di dalam try bercatch generik tanpa klausa ValidationException.
 *
 * Perilakunya sendiri tetap diuji terpisah, lihat ValidasiMenjawab422Test.
 */
class DisiplinValidasiTertelanTest extends TestCase
{
    /** Berkas controller PHP. */
    private function berkasController(): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('app/Http/Controllers'), \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $out[] = $f->getPathname();
            }
        }
        sort($out);

        return $out;
    }

    /**
     * Token yang berarti, tanpa komentar dan spasi.
     *
     * Sengaja token_get_all() dan bukan regex: penjaga ini harus mencocokkan
     * kurung kurawal, dan pembuang komentar berbasis regex ikut memotong "//"
     * yang kebetulan berada di dalam string — setiap URL punya satu — sehingga
     * pasangan kurungnya meleset tanpa suara.
     *
     * @return array<int,array{teks:string,id:int,baris:int}>
     */
    private function token(string $src): array
    {
        $out = [];
        foreach (token_get_all($src) as $t) {
            if (is_array($t)) {
                if (in_array($t[0], [T_COMMENT, T_DOC_COMMENT, T_WHITESPACE], true)) {
                    continue;
                }
                $out[] = ['teks' => $t[1], 'id' => $t[0], 'baris' => $t[2]];
            } else {
                $out[] = ['teks' => $t, 'id' => -1, 'baris' => 0];
            }
        }

        return $out;
    }

    /**
     * SATU-SATUNYA tempat aturannya dinyatakan. Uji repo dan penjaga-bagi-penjaga
     * sama-sama memanggil ini; kalau logikanya disalin ke dua tempat, salah satu
     * bisa diperbaiki tanpa yang lain dan penjaganya jadi bohong.
     *
     * @return array<int,array{metode:string,baris:int}>
     */
    private function pindai(string $src): array
    {
        $tok = $this->token($src);
        $n = count($tok);
        $hasil = [];

        for ($i = 0; $i < $n; $i++) {
            if ($tok[$i]['id'] !== T_TRY) {
                continue;
            }

            // Badan try
            $buka = $i + 1;
            while ($buka < $n && $tok[$buka]['teks'] !== '{') {
                $buka++;
            }
            $depth = 0;
            $tutup = $buka;
            for ($k = $buka; $k < $n; $k++) {
                if ($tok[$k]['teks'] === '{') {
                    $depth++;
                } elseif ($tok[$k]['teks'] === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $tutup = $k;
                        break;
                    }
                }
            }

            /* Apakah badan try memanggil validasi yang MELEMPAR?
               Validator::make() sendiri tidak melempar — ia mengembalikan
               validator; yang melempar adalah ->validate(). Penjaga yang ikut
               menghitung Validator::make() akan menuduh kode yang memeriksa
               ->fails() sendiri, dan tuduhan palsu membuat orang berhenti
               mempercayai penjaganya. */
            $adaValidate = false;
            $adaSebutValidationException = false;
            for ($k = $buka; $k < $tutup; $k++) {
                if ($tok[$k]['id'] === T_OBJECT_OPERATOR
                    && isset($tok[$k + 1])
                    && in_array($tok[$k + 1]['teks'], ['validate', 'validateWithBag'], true)) {
                    $adaValidate = true;
                }
                if ($tok[$k]['teks'] === 'ValidationException') {
                    $adaSebutValidationException = true;
                }
            }
            if (! $adaValidate || $adaSebutValidationException) {
                continue;
            }

            // Tipe yang ditangkap oleh SEMUA catch milik try ini.
            $ditangkap = [];
            $j = $tutup + 1;
            while ($j < $n && $tok[$j]['id'] === T_CATCH) {
                $j++;
                while ($j < $n && $tok[$j]['teks'] !== ')') {
                    if (in_array($tok[$j]['id'], [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED], true)) {
                        $bagian = explode('\\', $tok[$j]['teks']);
                        $ditangkap[] = end($bagian);
                    }
                    $j++;
                }
                $j++;
                $d = 0;
                while ($j < $n) {
                    if ($tok[$j]['teks'] === '{') {
                        $d++;
                    } elseif ($tok[$j]['teks'] === '}') {
                        $d--;
                        if ($d === 0) {
                            $j++;
                            break;
                        }
                    }
                    $j++;
                }
            }

            $generik = (bool) array_intersect($ditangkap, ['Exception', 'Throwable']);
            $khusus = in_array('ValidationException', $ditangkap, true);
            if (! $generik || $khusus) {
                continue;
            }

            $metode = '?';
            for ($k = $i; $k >= 1; $k--) {
                if ($tok[$k]['id'] === T_FUNCTION && isset($tok[$k + 1])) {
                    $metode = $tok[$k + 1]['teks'];
                    break;
                }
            }
            $hasil[] = ['metode' => $metode, 'baris' => $tok[$i]['baris']];
        }

        return $hasil;
    }

    public function test_tidak_ada_validasi_yang_tertelan_catch_generik(): void
    {
        $pelanggar = [];
        foreach ($this->berkasController() as $path) {
            foreach ($this->pindai((string) file_get_contents($path)) as $t) {
                $rel = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
                $pelanggar[] = str_replace('\\', '/', $rel).':'.$t['baris'].'  '.$t['metode'].'()';
            }
        }

        $this->assertSame(
            [],
            $pelanggar,
            count($pelanggar).' metode memanggil ->validate() di dalam try yang ditutup '
            .'catch generik. ValidationException turunan \\Exception, jadi di titik-titik ini '
            .'kesalahan pemanggil dilaporkan 500 ("gagal") dan bukan 422 ("masukanmu kurang"), '
            ."sekaligus membanjiri Log::error dengan hal yang bukan kegagalan sistem.\n\n"
            .'Perbaikannya: naikkan validasi ke LUAR try, atau — bila ia tak boleh pindah — '
            ."tambahkan catch (ValidationException \$e) sebelum catch generiknya.\n\n"
            ."Yang menelan:\n  ".implode("\n  ", array_slice($pelanggar, 0, 80))
            .(count($pelanggar) > 80 ? "\n  ... dan ".(count($pelanggar) - 80).' lagi' : '')
        );
    }

    /**
     * Penjaga bagi penjaga.
     *
     * Pemindai yang salah baca melaporkan NOL pada kode yang sebenarnya bercacat,
     * dan hijau seperti itu lebih berbahaya daripada tidak ada penjaga sama
     * sekali. Di sini pemindainya disuruh membaca contoh yang isinya sudah
     * diketahui: satu bentuk cacat dan dua bentuk yang sudah benar. Ia harus
     * menemukan yang pertama dan tidak menuduh dua sisanya.
     */
    public function test_pemindai_mengenali_cacat_dan_tidak_menuduh_yang_sudah_benar(): void
    {
        $contoh = <<<'PHP'
<?php
class Contoh
{
    public function cacat($request)
    {
        try {
            $alamat = 'https://contoh.test/a';  // garis miring ganda di dalam string
            $validated = $request->validate(['a' => 'required']);

            return [$alamat, $validated];
        } catch (\Exception $e) {
            return 500;
        }
    }

    public function benarKarenaDinaikkan($request)
    {
        $validated = $request->validate(['a' => 'required']);

        try {
            return $validated;
        } catch (\Exception $e) {
            return 500;
        }
    }

    public function benarKarenaAdaCatchKhusus($request)
    {
        try {
            return $request->validate(['a' => 'required']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return 500;
        }
    }

    public function bukanUrusanKita($request)
    {
        try {
            $v = \Validator::make([], []);

            return $v->fails();
        } catch (\Exception $e) {
            return 500;
        }
    }
}
PHP;

        $temuan = array_column($this->pindai($contoh), 'metode');

        $this->assertSame(
            ['cacat'],
            $temuan,
            'Pemindai harus menemukan tepat satu titik cacat, yaitu cacat(). '
            .'Ditemukan: '.json_encode($temuan)
        );
    }
}
