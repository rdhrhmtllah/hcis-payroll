<?php

namespace Tests\Feature\SYSTEM\Application;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Satu kunci literal hanya boleh muncul sekali di dalam satu array literal.
 *
 * ── Cacat yang ditutup ───────────────────────────────────────────────────────
 *
 * PHP menyimpan nilai TERAKHIR dan tidak mengeluh. Di aturan validasi itu
 * berarti sebuah aturan hilang; di payload respons itu berarti nilai yang
 * dikirim bukan yang ditulis lebih dulu.
 *
 * Yang terjadi di repo ini (sebelum ditutup):
 *
 *   CrossDivisionReviewController  'User_Id' => Hashids::encode(...)   <- mati
 *                                  'User_Id' => $item->Employee_Id     <- berlaku
 *
 * Id karyawan mentah terkirim ke klien menggantikan bentuk ter-Hashids yang
 * jelas-jelas dimaksudkan oleh baris di atasnya, dan panggilan encode-nya
 * dijalankan lalu dibuang setiap baris.
 *
 * ── Yang TIDAK dijaga ────────────────────────────────────────────────────────
 *
 * Hanya kunci literal (string dan angka). Kunci hasil ekspresi — $var, konstanta,
 * pemanggilan fungsi — tidak bisa dinilai tanpa menjalankan kodenya, jadi
 * dilewati. Akses indeks seperti $a['x']['x'] bukan array literal dan tidak
 * ikut terhitung.
 */
class KunciArrayKembarTest extends TestCase
{
    /** @var list<string> */
    private const AKAR = ['app', 'config', 'routes', 'database'];

    public function test_tidak_ada_kunci_kembar_di_satu_array_literal(): void
    {
        $temuan = [];

        foreach (self::AKAR as $akar) {
            foreach ($this->berkasPhp(base_path($akar)) as $jalur) {
                $rel = str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], $jalur);

                foreach ($this->kunciKembar($jalur) as [$kunci, $baris]) {
                    $temuan[] = sprintf(
                        "'%s' %dx di %s baris %s -> yang berlaku: baris %d",
                        $kunci,
                        count($baris),
                        $rel,
                        implode(', ', $baris),
                        end($baris)
                    );
                }
            }
        }

        sort($temuan);

        $this->assertSame(
            [],
            $temuan,
            'Kunci yang sama ditulis lebih dari sekali di dalam satu array literal. '
            .'PHP menyimpan yang TERAKHIR tanpa peringatan, jadi entri sebelumnya '
            .'adalah kode mati — dan bila nilainya berbeda, yang berlaku bukan yang '
            ."tampak lebih dulu saat dibaca.\n  "
            .implode("\n  ", $temuan)
        );
    }

    /**
     * Kunci kembar per array literal, memakai token_get_all supaya kurung di
     * dalam string, komentar, dan heredoc tidak ikut terhitung.
     *
     * @return list<array{0: string, 1: list<int>}>
     */
    private function kunciKembar(string $jalur): array
    {
        $token = @token_get_all(file_get_contents($jalur));

        if ($token === false) {
            return [];
        }

        $hasil = [];
        /** @var list<array<string, list<int>>> tiap array literal yang sedang terbuka */
        $tumpukan = [];
        /** @var list<int> kurung non-array yang terbuka di dalam array terkini */
        $dalam = [];

        $n = count($token);

        for ($i = 0; $i < $n; $i++) {
            $t = $token[$i];

            if ($t === '[') {
                if ($this->aksesIndeks($token, $i)) {
                    if ($tumpukan !== []) {
                        $dalam[count($dalam) - 1]++;
                    }
                } else {
                    $tumpukan[] = [];
                    $dalam[] = 0;
                }

                continue;
            }

            if (is_array($t) && $t[0] === T_ARRAY) {
                $j = $i + 1;
                while ($j < $n && is_array($token[$j]) && $token[$j][0] === T_WHITESPACE) {
                    $j++;
                }

                if (($token[$j] ?? null) === '(') {
                    $tumpukan[] = [];
                    $dalam[] = 0;
                    $i = $j;
                }

                continue;
            }

            if ($t === ']' || $t === ')') {
                if ($tumpukan === []) {
                    continue;
                }

                if ($dalam[count($dalam) - 1] > 0) {
                    $dalam[count($dalam) - 1]--;

                    continue;
                }

                $selesai = array_pop($tumpukan);
                array_pop($dalam);

                foreach ($selesai as $kunci => $baris) {
                    if (count($baris) > 1) {
                        $hasil[] = [(string) $kunci, $baris];
                    }
                }

                continue;
            }

            if ($t === '(' || $t === '{') {
                if ($tumpukan !== []) {
                    $dalam[count($dalam) - 1]++;
                }

                continue;
            }

            if ($t === '}') {
                if ($tumpukan !== [] && $dalam[count($dalam) - 1] > 0) {
                    $dalam[count($dalam) - 1]--;
                }

                continue;
            }

            if ($tumpukan !== [] && $dalam[count($dalam) - 1] === 0 && is_array($t)
                && in_array($t[0], [T_CONSTANT_ENCAPSED_STRING, T_LNUMBER], true)) {
                $j = $i + 1;
                while ($j < $n && is_array($token[$j]) && in_array($token[$j][0], [T_WHITESPACE, T_COMMENT], true)) {
                    $j++;
                }

                if (is_array($token[$j] ?? null) && $token[$j][0] === T_DOUBLE_ARROW) {
                    $tumpukan[count($tumpukan) - 1][trim($t[1], "'\"")][] = $t[2];
                }
            }
        }

        return $hasil;
    }

    /** '[' yang mengikuti nilai adalah akses indeks, bukan pembuka array literal. */
    private function aksesIndeks(array $token, int $i): bool
    {
        $j = $i - 1;
        while ($j >= 0 && is_array($token[$j]) && in_array($token[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $j--;
        }

        $sebelum = $token[$j] ?? null;

        return $sebelum === ']' || $sebelum === ')'
            || (is_array($sebelum) && in_array($sebelum[0], [T_VARIABLE, T_STRING, T_CONSTANT_ENCAPSED_STRING], true));
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
