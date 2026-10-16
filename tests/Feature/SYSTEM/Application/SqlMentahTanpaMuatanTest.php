<?php

namespace Tests\Feature\SYSTEM\Application;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * SQL mentah tidak boleh menyisipkan variabel yang berisi muatan permintaan.
 *
 * ── Kenapa dijaga ────────────────────────────────────────────────────────────
 *
 * Repo ini memakai SQL mentah dengan sangat banyak: 198 pemanggilan di 51 berkas
 * menyisipkan variabel ke dalam whereRaw/selectRaw/DB::raw dan kerabatnya. Itu
 * bukan cacat dengan sendirinya -- hampir semuanya menyisipkan nama tabel atau
 * potongan SQL yang dibangun di berkas itu juga.
 *
 * Yang membuatnya berbahaya hanya satu hal: bila nilainya pernah berasal dari
 * permintaan. Saat sapuan ini dibuat, hasilnya NOL, dan yang paling berisiko
 * pun aman karena dirancang begitu: ReportRunner menyisipkan nama kolom ke
 * DB::raw("[sub].[{$col}]"), tetapi DefinisiValidator menolak 422 untuk kunci
 * kolom yang tidak ada di katalog dataset sebelum sampai ke sana.
 *
 * Penjaga ini menahan angka itu di nol, karena satu baris baru yang meneruskan
 * $request->input(...) ke whereRaw tidak akan terlihat berbeda saat review.
 *
 * ── Yang TIDAK dijaga ────────────────────────────────────────────────────────
 *
 * Metode bernama `raw()` yang BUKAN milik query builder tidak ikut dihitung --
 * SopCloudTaskWebhookController punya $this->berkas->raw($id) yang hanya
 * mengambil satu baris berkas, dan versi pertama pemindai ini melaporkannya
 * sebagai temuan. Hanya nama yang tak bermakna lain yang dijaga: whereRaw,
 * selectRaw, havingRaw, orderByRaw, groupByRaw, joinRaw, dan DB::raw eksplisit.
 *
 * Argumen kedua (binding) juga tidak dihitung: justru itu bentuk yang benar.
 */
class SqlMentahTanpaMuatanTest extends TestCase
{
    /** Nama yang tidak punya makna lain selain SQL mentah. */
    private const RAW_TEGAS = ['whereRaw', 'selectRaw', 'havingRaw', 'orderByRaw', 'groupByRaw', 'joinRaw', 'whereRawColumn'];

    /** Pola variabel yang isinya datang dari permintaan. */
    private const SUMBER_MUATAN = '/\$(\w+)\s*=\s*[^;]{0,160}(\$request->|\$validated\[|request\(\)->|\$_GET|\$_POST|->input\(|->query\()/s';

    public function test_sql_mentah_tidak_menyisipkan_muatan_permintaan(): void
    {
        $temuan = [];

        foreach ($this->berkasPhp(base_path('app')) as $jalur) {
            $isi = file_get_contents($jalur);
            $rel = str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], $jalur);

            $dariPermintaan = [];
            if (preg_match_all(self::SUMBER_MUATAN, $isi, $cocok, PREG_SET_ORDER)) {
                foreach ($cocok as $satu) {
                    $dariPermintaan[$satu[1]] = true;
                }
            }

            if ($dariPermintaan === []) {
                continue;
            }

            foreach ($this->argumenSqlMentah($isi) as [$nama, $variabel, $baris]) {
                foreach ($variabel as $v) {
                    if (isset($dariPermintaan[$v])) {
                        $temuan[] = sprintf('%s:%d %s() menyisipkan $%s', $rel, $baris, $nama, $v);
                    }
                }
            }
        }

        sort($temuan);
        $temuan = array_values(array_unique($temuan));

        $this->assertSame(
            [],
            $temuan,
            'SQL mentah menyisipkan variabel yang isinya berasal dari permintaan. Pakai '
            .'binding (argumen kedua whereRaw) untuk NILAI; untuk nama kolom atau tabel, '
            ."saring dulu terhadap daftar putih dan tolak yang di luar daftar.\n  "
            .implode("\n  ", $temuan)
        );
    }

    /**
     * Variabel yang diinterpolasi ke argumen PERTAMA tiap pemanggilan SQL mentah.
     *
     * @return list<array{0: string, 1: list<string>, 2: int}>
     */
    private function argumenSqlMentah(string $isi): array
    {
        $token = @token_get_all($isi);

        if ($token === false) {
            return [];
        }

        $hasil = [];
        $n = count($token);

        for ($i = 0; $i < $n; $i++) {
            if (! is_array($token[$i]) || $token[$i][0] !== T_STRING) {
                continue;
            }

            $nama = $token[$i][1];
            $tegas = in_array($nama, self::RAW_TEGAS, true);
            $dbRaw = $nama === 'raw' && $this->didahuluiDb($token, $i);

            if (! $tegas && ! $dbRaw) {
                continue;
            }

            $k = $this->lewatiSpasi($token, $i + 1, $n);

            if (($token[$k] ?? null) !== '(') {
                continue;
            }

            $d = 0;
            $variabel = [];

            for ($y = $k; $y < $n; $y++) {
                $t = $token[$y];

                if ($t === '(') {
                    $d++;

                    continue;
                }

                if ($t === ')') {
                    $d--;

                    if ($d === 0) {
                        break;
                    }

                    continue;
                }

                if ($t === ',' && $d === 1) {
                    break;
                }

                if (is_array($t) && $t[0] === T_VARIABLE && $d === 1) {
                    $variabel[] = ltrim($t[1], '$');
                }
            }

            if ($variabel !== []) {
                $hasil[] = [$nama, $variabel, $token[$i][2]];
            }
        }

        return $hasil;
    }

    /** `raw` hanya dihitung bila penerimanya DB, bukan layanan yang kebetulan senama. */
    private function didahuluiDb(array $token, int $i): bool
    {
        $s = $i - 1;

        while ($s > 0 && is_array($token[$s]) && $token[$s][0] === T_WHITESPACE) {
            $s--;
        }

        if (! is_array($token[$s] ?? null) || $token[$s][0] !== T_DOUBLE_COLON) {
            return false;
        }

        $s--;

        while ($s > 0 && is_array($token[$s]) && $token[$s][0] === T_WHITESPACE) {
            $s--;
        }

        return is_array($token[$s] ?? null) && $token[$s][0] === T_STRING && $token[$s][1] === 'DB';
    }

    private function lewatiSpasi(array $token, int $i, int $batas): int
    {
        while ($i < $batas && is_array($token[$i]) && $token[$i][0] === T_WHITESPACE) {
            $i++;
        }

        return $i;
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
