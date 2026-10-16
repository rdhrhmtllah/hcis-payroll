<?php

namespace Tests\Feature\SYSTEM\Application;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Hasil kueri yang ditugaskan di dalam perulangan lalu tidak pernah dibaca.
 *
 * ── Cacat yang ditutup ───────────────────────────────────────────────────────
 *
 *     foreach ($employees as $item) {
 *         $lembur = LemburDetail::whereDate(...)->exists();
 *         // dd($lembur);
 *         ...
 *     }
 *
 * Biayanya dibayar sekali per iterasi, hasilnya dibuang. Tidak ada galat dan
 * tidak ada peringatan — yang terlihat hanya "halamannya lambat". Semuanya
 * berjalan di dalam transaksi, dan dengan RCSI mati di Web_HRIS setiap kueri
 * mubazir memperpanjang waktu kunci ditahan untuk SELURUH operasi, bukan hanya
 * untuk barisnya.
 *
 * Empat yang ditemukan saat penjaga ini ditulis semuanya berupa sisa dd() yang
 * lupa dibuang: ShiftController::submitAdmin dan ::submit ($lembur),
 * OvertimeController::submitAdmin ($userInsert dan $userInsertNoHp — dua kueri
 * Karyawan per baris), dan TicketController::display_detail_ticket ($review).
 *
 * ── Kenapa berjangkar pada AKAR kueri ────────────────────────────────────────
 *
 * Hanya DB::table/select/scalar dan pemanggilan statis Model::where/find/first/
 * ... yang dihitung. ->get(), ->first(), ->count(), ->pluck() juga milik
 * Collection dan gratis; menghitungnya membuat setiap perulangan atas hasil
 * kueri terlihat seperti N+1.
 *
 * ── Yang TIDAK dijaga ────────────────────────────────────────────────────────
 *
 * Penugasan kedua atas variabel yang sama dianggap "pemakaian", jadi metode
 * yang menugasi $x di dua cabang lalu tak pernah membacanya tidak tertangkap.
 * Itu memang membuat penjaga ini lebih sempit daripada namanya, tetapi arahnya
 * disengaja: lebih baik melewatkan sebagian daripada menuduh kode yang benar.
 */
class KueriMubazirDalamPerulanganTest extends TestCase
{
    /** Metode statis yang menandai awal rantai kueri Eloquent. */
    private const AWAL_ELOQUENT = ['where', 'find', 'findOrFail', 'first', 'firstOrFail', 'firstWhere', 'whereIn', 'whereDate', 'query', 'all', 'select'];

    /** Metode DB yang benar-benar menyentuh basis data. */
    private const AWAL_DB = ['table', 'select', 'scalar'];

    public function test_tidak_ada_hasil_kueri_yang_dibuang_di_dalam_perulangan(): void
    {
        $temuan = [];

        foreach ($this->berkasPhp(base_path('app')) as $jalur) {
            $rel = str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], $jalur);

            foreach ($this->mubazir($jalur) as [$metode, $var, $baris]) {
                $temuan[] = sprintf('%s:%d %s() -> %s tidak pernah dibaca', $rel, $baris, $metode, $var);
            }
        }

        sort($temuan);

        $this->assertSame(
            [],
            $temuan,
            'Sebuah kueri dijalankan sekali per iterasi dan hasilnya tidak pernah dibaca. '
            .'Buang kueri-nya, atau pakai hasilnya. Bila memang perlu dijalankan demi efek '
            ."sampingnya, jangan tugaskan ke variabel — panggil saja.\n  "
            .implode("\n  ", $temuan)
        );
    }

    /** @return list<array{0: string, 1: string, 2: int}> */
    private function mubazir(string $jalur): array
    {
        $token = @token_get_all(file_get_contents($jalur));

        if ($token === false) {
            return [];
        }

        $hasil = [];
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

            [$buka, $tutup] = $this->blok($token, $i);

            if ($buka < 0) {
                continue;
            }

            $i = $tutup;
            $loop = $this->rentangPerulangan($token, $buka, $tutup);

            if ($loop === []) {
                continue;
            }

            for ($k = $buka; $k <= $tutup; $k++) {
                if (! is_array($token[$k]) || $token[$k][0] !== T_VARIABLE) {
                    continue;
                }

                if (! $this->diDalam($k, $loop)) {
                    continue;
                }

                $z = $this->lewatiSpasi($token, $k + 1, $tutup);

                if (($token[$z] ?? null) !== '=') {
                    continue;
                }

                $akhir = $z;
                for ($y = $z; $y <= $tutup; $y++) {
                    if ($token[$y] === ';') {
                        $akhir = $y;
                        break;
                    }
                }

                if (! $this->adaAkarKueri($token, $z, $akhir)) {
                    continue;
                }

                if (! $this->dipakaiLagi($token, $buka, $tutup, $k, $akhir, $token[$k][1])) {
                    $hasil[] = [$nama, $token[$k][1], $token[$k][2]];
                }
            }
        }

        return $hasil;
    }

    /** @return list<array{0: int, 1: int}> */
    private function rentangPerulangan(array $token, int $buka, int $tutup): array
    {
        $loop = [];

        for ($k = $buka; $k <= $tutup; $k++) {
            if (! is_array($token[$k]) || ! in_array($token[$k][0], [T_FOREACH, T_FOR, T_WHILE], true)) {
                continue;
            }

            $z = $k;
            while ($z <= $tutup && $token[$z] !== '(') {
                $z++;
            }

            $p = 0;
            for (; $z <= $tutup; $z++) {
                if ($token[$z] === '(') {
                    $p++;
                } elseif ($token[$z] === ')') {
                    $p--;

                    if ($p === 0) {
                        break;
                    }
                }
            }

            $z = $this->lewatiSpasi($token, $z + 1, $tutup);

            if (($token[$z] ?? null) !== '{') {
                continue;
            }

            $d = 0;
            for ($y = $z; $y <= $tutup; $y++) {
                if ($this->bukaKurawal($token[$y])) {
                    $d++;
                } elseif ($token[$y] === '}') {
                    $d--;

                    if ($d === 0) {
                        $loop[] = [$z, $y];
                        break;
                    }
                }
            }
        }

        return $loop;
    }

    private function adaAkarKueri(array $token, int $dari, int $sampai): bool
    {
        for ($y = $dari; $y < $sampai; $y++) {
            if (! is_array($token[$y]) || $token[$y][0] !== T_STRING) {
                continue;
            }

            $s = $this->lewatiSpasi($token, $y + 1, $sampai);

            if (! is_array($token[$s] ?? null) || $token[$s][0] !== T_DOUBLE_COLON) {
                continue;
            }

            $metode = null;
            for ($w = $s + 1; $w < $sampai && $w < $s + 4; $w++) {
                if (is_array($token[$w]) && $token[$w][0] === T_STRING) {
                    $metode = $token[$w][1];
                    break;
                }
            }

            if ($metode === null) {
                continue;
            }

            $kelas = $token[$y][1];

            if ($kelas === 'DB' && in_array($metode, self::AWAL_DB, true)) {
                return true;
            }

            if ($kelas !== 'DB' && preg_match('/^[A-Z]/', $kelas) && in_array($metode, self::AWAL_ELOQUENT, true)) {
                return true;
            }
        }

        return false;
    }

    private function dipakaiLagi(array $token, int $buka, int $tutup, int $dari, int $sampai, string $var): bool
    {
        for ($y = $buka; $y <= $tutup; $y++) {
            if ($y >= $dari && $y <= $sampai) {
                continue;
            }

            if (is_array($token[$y]) && $token[$y][0] === T_VARIABLE && $token[$y][1] === $var) {
                return true;
            }
        }

        return false;
    }

    private function diDalam(int $i, array $loop): bool
    {
        foreach ($loop as $l) {
            if ($i > $l[0] && $i < $l[1]) {
                return true;
            }
        }

        return false;
    }

    private function lewatiSpasi(array $token, int $i, int $batas): int
    {
        while ($i <= $batas && is_array($token[$i]) && in_array($token[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $i++;
        }

        return $i;
    }

    /** @return array{0: int, 1: int} */
    /**
     * Apakah token ini membuka blok kurung kurawal.
     *
     * PENTING: interpolasi string `"...{$var}..."` menyumbang `}` MENTAH tanpa `{`
     * mentah pasangannya — pembukanya bertipe T_CURLY_OPEN (dan T_DOLLAR_OPEN_CURLY_BRACES
     * untuk bentuk `${var}`), keduanya token BER-ARRAY. Menghitung `{` mentah saja
     * membuat kedalaman turun lebih cepat daripada seharusnya, sehingga blok fungsi
     * dinyatakan tutup sebelum waktunya dan pemakaian variabel SESUDAH string itu tidak
     * pernah terlihat. Terukur pada PaParticipantRuleResolver::muatOverrideSiklus():
     * blok tutup di token 1913 padahal seharusnya 1944, dan pemakaian $baris di 1919
     * dilaporkan "tidak pernah dibaca" padahal dibaca tepat di baris berikutnya.
     */
    private function bukaKurawal(mixed $token): bool
    {
        return $token === '{'
            || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true));
    }

    private function blok(array $token, int $i): array
    {
        $n = count($token);
        $buka = -1;
        $p = 0;

        for ($k = $i + 1; $k < $n; $k++) {
            $t = $token[$k];

            if ($t === '(') {
                $p++;
            } elseif ($t === ')') {
                $p--;
            } elseif ($t === '{' && $p === 0) {
                $buka = $k;
                break;
            } elseif ($t === ';' && $p === 0) {
                return [-1, $i];
            }
        }

        if ($buka < 0) {
            return [-1, $i];
        }

        $d = 0;
        for ($k = $buka; $k < $n; $k++) {
            if ($this->bukaKurawal($token[$k])) {
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
