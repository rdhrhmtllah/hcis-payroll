<?php

namespace Tests\Feature\SYSTEM\Application;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Setiap Log::channel('x') harus punya 'x' di config/logging.php.
 *
 * ── Cacat yang ditutup ───────────────────────────────────────────────────────
 *
 * LogManager melempar InvalidArgumentException("Log [x] is not defined.") untuk
 * kanal yang tak dikenal. Karena hampir semua pemanggilan Log::channel() berada
 * di JALUR GALAT, lemparan itu tidak pernah terlihat saat semuanya berjalan
 * normal — ia hanya muncul persis pada saat sesuatu sudah salah, lalu menimpa
 * penanganannya:
 *
 *   } catch (\Exception $e) {
 *       DB::rollBack();
 *       Log::channel('satuanKpiController')->error(...);   // <- melempar di sini
 *
 *       return ResponseHelper::error('Terjadi Kesalahan', 500);   // tak pernah dicapai
 *   }
 *
 * Rollback tetap berjalan, tetapi responsnya bukan yang dimaksud, dan baris log
 * yang seharusnya merekam sebab aslinya tidak pernah ditulis. Sebabnya hilang.
 *
 * Saat penjaga ini ditulis ada 7 kanal tak terdefinisi di 12 tempat. Yang paling
 * merugikan: di LmsModulAbsensiController::store() panggilan itu berada TEPAT DI
 * ATAS cabang penanganan deadlock, sehingga cabang 409 "silakan coba lagi" tidak
 * pernah bisa dijangkau sama sekali.
 *
 * Lemparan dari dalam blok catch tidak ditangkap oleh klausa catch berikutnya
 * pada try yang sama — jadi tidak ada jaring apa pun di bawahnya.
 */
class KanalLogTerdefinisiTest extends TestCase
{
    /** Kanal bawaan Laravel yang selalu ada, di luar daftar aplikasi. */
    private const AKAR = 'app';

    public function test_setiap_kanal_yang_dirujuk_kode_terdefinisi_di_config(): void
    {
        $terdefinisi = array_keys(config('logging.channels', []));

        $this->assertNotEmpty($terdefinisi, 'config/logging.php tidak memuat satu kanal pun — konfigurasinya tidak termuat.');

        $hilang = [];

        foreach ($this->berkasPhp(base_path(self::AKAR)) as $jalur) {
            $isi = file_get_contents($jalur);

            if (! preg_match_all('/Log::channel\(\s*[\'"]([A-Za-z0-9_\-]+)[\'"]\s*\)/', $isi, $cocok, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                continue;
            }

            foreach ($cocok as $satu) {
                $nama = $satu[1][0];

                if (in_array($nama, $terdefinisi, true)) {
                    continue;
                }

                $baris = substr_count(substr($isi, 0, $satu[0][1]), "\n") + 1;
                $rel = str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], $jalur);
                $hilang[] = sprintf("'%s' di %s:%d", $nama, $rel, $baris);
            }
        }

        $this->assertSame(
            [],
            $hilang,
            'Log::channel() menunjuk kanal yang tidak ada di config/logging.php. Pemanggilan '
            .'semacam ini melempar InvalidArgumentException, dan karena hampir selalu berada '
            .'di dalam blok catch, ia MENGGANTIKAN penanganan galat aslinya. Tambahkan '
            ."kanalnya di config/logging.php, atau tunjuk kanal yang sudah ada.\n  "
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
