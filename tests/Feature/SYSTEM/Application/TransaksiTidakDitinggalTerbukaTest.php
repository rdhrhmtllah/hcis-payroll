<?php

namespace Tests\Feature\SYSTEM\Application;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * `return` di antara beginTransaction() dan commit(), tanpa rollBack().
 *
 * ── Kenapa ini merugikan di sini ─────────────────────────────────────────────
 *
 * RCSI mati di Web_HRIS, jadi pembaca mengantre di belakang penulis. Transaksi
 * yang ditinggalkan terbuka menahan kuncinya sampai koneksinya dilepas — sampai
 * akhir permintaan pada FPM, dan JAUH lebih lama pada worker antrean yang
 * prosesnya berumur panjang. Gejalanya menyamar jadi "aplikasi lambat", bukan
 * galat, sehingga tak pernah ditelusuri sebagai cacat.
 *
 * Semua jalan keluar dini yang terdaftar di bawah adalah jalur GALAT (400/404/
 * 422): tidak ada data yang seharusnya bertahan, jadi perbaikannya selalu
 * menambahkan DB::rollBack() sebelum return-nya — atau memindahkan
 * beginTransaction() ke bawah blok validasinya.
 *
 * ── Kenapa daftar, bukan angka ───────────────────────────────────────────────
 *
 * Dua belas metode tidak muat dalam satu perubahan yang masih bisa dibaca, dan
 * tiap tempat menuntut pembacaan sendiri. Daftar bernama membuat dua arah
 * terlihat: satu tempat baru langsung merah, dan satu tempat yang DIPERBAIKI
 * juga merah sampai namanya dicoret di sini. Ini tempat singgah, bukan tempat
 * menetap — angkanya hanya boleh turun.
 *
 * ── Yang TIDAK dijaga ────────────────────────────────────────────────────────
 *
 * return di dalam closure dikecualikan: ia keluar dari closure-nya, bukan dari
 * metode, jadi transaksinya tidak ditinggalkan. Versi pertama penjaga ini juga
 * memeriksa setiap catch di dalam metode dan melaporkan 37 tempat; sesudah
 * dipersempit ke catch milik try yang benar-benar mengurung transaksinya,
 * sisanya 2 — 95% laporan pertama itu derau.
 */
class TransaksiTidakDitinggalTerbukaTest extends TestCase
{
    /**
     * Metode yang masih meninggalkan transaksinya terbuka pada jalan keluar dini.
     * HANYA BOLEH BERKURANG, dan sekarang KOSONG -- kedua belas tempat yang
     * terdaftar saat penjaga ini dipasang sudah ditutup.
     *
     * @var list<string>
     */
    private const UTANG = [];

    public function test_tidak_ada_transaksi_baru_yang_ditinggalkan_terbuka(): void
    {
        $sekarang = $this->pindai();
        $baru = array_values(array_diff($sekarang, self::UTANG));

        $this->assertSame(
            [],
            $baru,
            'Ada jalan keluar dini baru di antara beginTransaction() dan commit() tanpa '
            .'rollBack(). Dengan RCSI mati, transaksi yang ditinggalkan terbuka menahan kunci '
            .'dan mengantrekan pembaca. Tambahkan DB::rollBack() sebelum return-nya, atau '
            ."pindahkan beginTransaction() ke bawah blok validasinya.\n  "
            .implode("\n  ", $baru)
        );
    }

    public function test_daftar_utang_tidak_menyimpan_yang_sudah_diperbaiki(): void
    {
        $sekarang = $this->pindai();
        $sudahBeres = array_values(array_diff(self::UTANG, $sekarang));

        $this->assertSame(
            [],
            $sudahBeres,
            'Metode berikut sudah tidak meninggalkan transaksinya terbuka — coret dari UTANG '
            ."supaya daftar ini tidak berubah jadi tempat sampah abadi.\n  "
            .implode("\n  ", $sudahBeres)
        );
    }

    /** @return list<string> */
    private function pindai(): array
    {
        $temuan = [];

        foreach ($this->berkasPhp(base_path('app')) as $jalur) {
            $token = @token_get_all(file_get_contents($jalur));

            if ($token === false) {
                continue;
            }

            $rel = str_replace([base_path('app').DIRECTORY_SEPARATOR, '\\'], ['', '/'], $jalur);
            $n = count($token);

            for ($i = 0; $i < $n; $i++) {
                if (! is_array($token[$i]) || $token[$i][0] !== T_FUNCTION) {
                    continue;
                }

                $nama = '(anon)';
                for ($k = $i + 1; $k < $n && $k < $i + 5; $k++) {
                    if (is_array($token[$k]) && $token[$k][0] === T_STRING) {
                        $nama = $token[$k][1];
                        break;
                    }
                }

                [$buka, $tutup] = $this->badan($token, $i);

                if ($buka < 0) {
                    continue;
                }

                $teks = '';
                foreach (array_slice($token, $buka, $tutup - $buka + 1) as $t) {
                    $teks .= is_array($t) ? $t[1] : $t;
                }

                $i = $tutup;

                $posBegin = strpos($teks, 'beginTransaction');
                $posCommit = strpos($teks, 'commit');

                if ($posBegin === false || $posCommit === false || $posCommit < $posBegin) {
                    continue;
                }

                $antara = substr($teks, $posBegin, $posCommit - $posBegin);
                $bersih = preg_replace('/function\s*\([^)]*\)[^{]*\{(?:[^{}]|\{[^{}]*\})*\}/', 'CLOSURE', $antara);
                $bersih = preg_replace('/fn\s*\([^)]*\)\s*=>[^,;)]*/', 'ARROW', (string) $bersih);

                if (preg_match('/\breturn\b/', (string) $bersih) && ! preg_match('/roll[Bb]ack/', (string) $bersih)) {
                    $temuan[] = $rel.'::'.$nama;
                }
            }
        }

        sort($temuan);

        return array_values(array_unique($temuan));
    }

    /** @return array{0: int, 1: int} */
    private function badan(array $token, int $i): array
    {
        $n = count($token);
        $buka = -1;
        $paren = 0;

        for ($k = $i + 1; $k < $n; $k++) {
            $t = $token[$k];

            if ($t === '(') {
                $paren++;
            } elseif ($t === ')') {
                $paren--;
            } elseif ($t === '{' && $paren === 0) {
                $buka = $k;
                break;
            } elseif ($t === ';' && $paren === 0) {
                return [-1, $i];
            }
        }

        if ($buka < 0) {
            return [-1, $i];
        }

        $d = 0;

        for ($k = $buka; $k < $n; $k++) {
            if ($token[$k] === '{') {
                $d++;
            } elseif ($token[$k] === '}') {
                $d--;

                if ($d === 0) {
                    return [$buka, $k];
                }
            }
        }

        return [$buka, $n - 1];
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
