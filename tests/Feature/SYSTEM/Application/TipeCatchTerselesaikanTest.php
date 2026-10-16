<?php

namespace Tests\Feature\SYSTEM\Application;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Setiap tipe di klausa catch harus menunjuk kelas atau antarmuka yang ada.
 *
 * ── Cacat yang ditutup ───────────────────────────────────────────────────────
 *
 * `catch (Foo $e)` dengan Foo yang tidak pernah di-import diselesaikan ke
 * NAMESPACE BERKAS ITU SENDIRI. Bila kelasnya tidak ada di sana, klausanya tidak
 * akan pernah cocok — dan PHP tidak mengeluh, tidak saat parse maupun saat
 * jalan. Penanganan khusus yang ditulis di dalamnya adalah kode mati.
 *
 * Yang terjadi di MasterPeriodeController::tambah_periode():
 *
 *     } catch (\Throwable $th) {          // menangkap segalanya
 *         DB::rollBack();
 *         ...
 *     } catch (ValidationException $e) {  // tak pernah jalan
 *         return back()->withErrors($e->validator)->withInput();
 *     }
 *
 * Mati karena tiga hal sekaligus: ditulis sesudah \Throwable; tipenya
 * terselesaikan ke App\Http\Controllers\KPI\Planning\Period\ValidationException
 * yang tidak ada; dan $request->validate() dipanggil di LUAR try sehingga tak
 * ada ValidationException yang lewat sana sejak awal.
 *
 * ── Kenapa berbasis token ────────────────────────────────────────────────────
 *
 * Versi regex atas teks mentah ikut menghitung kata "catch (...)" yang muncul di
 * dalam KOMENTAR — termasuk komentar yang menjelaskan perbaikan ini sendiri,
 * sehingga penjaganya tetap merah sesudah cacatnya ditutup. token_get_all
 * membedakan kode dari komentar.
 */
class TipeCatchTerselesaikanTest extends TestCase
{
    /** @var list<string> */
    private const AKAR = ['app'];

    public function test_setiap_tipe_catch_menunjuk_kelas_yang_ada(): void
    {
        $temuan = [];

        foreach (self::AKAR as $akar) {
            foreach ($this->berkasPhp(base_path($akar)) as $jalur) {
                $rel = str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], $jalur);

                foreach ($this->tipeCatch($jalur) as [$tulisan, $terselesaikan, $baris]) {
                    if (class_exists($terselesaikan) || interface_exists($terselesaikan)) {
                        continue;
                    }

                    $petunjuk = (class_exists('\\'.$tulisan) || interface_exists('\\'.$tulisan))
                        ? ' (kelas global bernama sama ADA — kurang backslash atau use)'
                        : '';

                    $temuan[] = sprintf('%s:%d catch (%s) -> %s tidak ada%s', $rel, $baris, $tulisan, $terselesaikan, $petunjuk);
                }
            }
        }

        sort($temuan);

        $this->assertSame(
            [],
            $temuan,
            'Klausa catch menunjuk tipe yang tidak ada, jadi ia tidak akan pernah cocok dan '
            .'isinya adalah kode mati. Tambahkan use yang benar, tulis nama global dengan '
            ."backslash, atau buang klausanya bila memang tidak diperlukan.\n  "
            .implode("\n  ", $temuan)
        );
    }

    /**
     * Tipe pada setiap klausa catch, beserta bentuk terselesaikannya.
     *
     * @return list<array{0: string, 1: string, 2: int}>
     */
    private function tipeCatch(string $jalur): array
    {
        $token = @token_get_all(file_get_contents($jalur));

        if ($token === false) {
            return [];
        }

        $ns = '';
        $peta = [];
        $n = count($token);

        for ($i = 0; $i < $n; $i++) {
            if (! is_array($token[$i])) {
                continue;
            }

            if ($token[$i][0] === T_NAMESPACE) {
                $ns = trim($this->namaSampaiTitikKoma($token, $i + 1));
            }

            if ($token[$i][0] === T_USE && $ns !== '' && $this->diTingkatAtas($token, $i)) {
                $isi = trim($this->namaSampaiTitikKoma($token, $i + 1));

                if ($isi === '' || str_contains($isi, '(')) {
                    continue;
                }

                if (preg_match('/^(.+?)\s+as\s+(\w+)$/i', $isi, $m)) {
                    $peta[$m[2]] = ltrim($m[1], '\\');
                } else {
                    $fq = ltrim($isi, '\\');
                    $pos = strrpos($fq, '\\');
                    $peta[$pos === false ? $fq : substr($fq, $pos + 1)] = $fq;
                }
            }
        }

        $hasil = [];

        for ($i = 0; $i < $n; $i++) {
            if (! is_array($token[$i]) || $token[$i][0] !== T_CATCH) {
                continue;
            }

            $baris = $token[$i][2];
            $daftar = [''];

            for ($k = $i + 1; $k < $n; $k++) {
                $t = $token[$k];

                if ($t === ')') {
                    break;
                }

                if ($t === '|') {
                    $daftar[] = '';

                    continue;
                }

                if (is_array($t) && in_array($t[0], [T_STRING, T_NS_SEPARATOR], true)) {
                    $daftar[count($daftar) - 1] .= $t[1];
                }

                if (is_array($t) && $t[0] === T_VARIABLE) {
                    break;
                }
            }

            foreach ($daftar as $nama) {
                $nama = trim($nama);

                if ($nama === '') {
                    continue;
                }

                $hasil[] = [$nama, $this->selesaikan($nama, $ns, $peta), $baris];
            }
        }

        return $hasil;
    }

    private function selesaikan(string $nama, string $ns, array $peta): string
    {
        if (str_starts_with($nama, '\\')) {
            return ltrim($nama, '\\');
        }

        $awal = explode('\\', $nama)[0];

        if (isset($peta[$awal])) {
            return $peta[$awal].substr($nama, strlen($awal));
        }

        return $ns === '' ? $nama : $ns.'\\'.$nama;
    }

    private function namaSampaiTitikKoma(array $token, int $mulai): string
    {
        $teks = '';

        for ($k = $mulai, $n = count($token); $k < $n; $k++) {
            $t = $token[$k];

            if ($t === ';' || $t === '{') {
                break;
            }

            $teks .= is_array($t) ? $t[1] : $t;
        }

        return $teks;
    }

    /** use di dalam badan kelas adalah trait, bukan impor. */
    private function diTingkatAtas(array $token, int $i): bool
    {
        $d = 0;

        for ($k = 0; $k < $i; $k++) {
            if ($token[$k] === '{') {
                $d++;
            } elseif ($token[$k] === '}') {
                $d--;
            }
        }

        return $d === 0;
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
