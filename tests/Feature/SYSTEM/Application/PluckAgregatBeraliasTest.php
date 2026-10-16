<?php

namespace Tests\Feature\SYSTEM\Application;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * pluck() atas hasil agregat harus memakai alias, bukan ekspresi mentah.
 *
 * ── Cacat yang ditutup ───────────────────────────────────────────────────────
 *
 *     ->pluck(DB::raw('COUNT(*)'), 'Kode_Karyawan')
 *
 * pluck memakai argumen pertamanya sebagai NAMA PROPERTI pada tiap baris hasil.
 * Di sini namanya menjadi teks "COUNT(*)", sedangkan SQL Server mengembalikan
 * kolom agregat tanpa nama. Hasilnya:
 *
 *     Undefined property: stdClass::$COUNT(*)
 *
 * Yang membuatnya berbahaya: galat itu baru muncul ketika ADA baris yang cocok.
 * Pada data uji yang kosong, atau pada kondisi yang kebetulan tidak menghasilkan
 * baris, semuanya terlihat hijau.
 *
 * Repo ini sudah dua kali menuliskan peringatannya di komentar -- di
 * EntitasService dan SopApqcService -- dan tetap terulang saat saya sendiri
 * memusatkan dua COUNT per-baris pada perubahan ini. Komentar bukan penjaga;
 * karena itu berkas ini ada.
 *
 * Perbaikannya: beri alias eksplisit lalu pluck alias itu.
 *
 *     ->selectRaw('Kode_Karyawan, COUNT(*) AS jumlah')
 *     ->pluck('jumlah', 'Kode_Karyawan')
 */
class PluckAgregatBeraliasTest extends TestCase
{
    /** @var list<string> */
    private const AKAR = ['app'];

    /** Fungsi agregat yang hasilnya tidak punya nama kolom sendiri. */
    private const AGREGAT = 'COUNT|SUM|AVG|MIN|MAX';

    public function test_pluck_tidak_memakai_ekspresi_agregat_mentah(): void
    {
        $temuan = [];

        foreach (self::AKAR as $akar) {
            foreach ($this->berkasPhp(base_path($akar)) as $jalur) {
                $isi = $this->tanpaKomentar(file_get_contents($jalur));
                $rel = str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], $jalur);

                $pola = '/->pluck\s*\(\s*(?:DB::raw|raw)\s*\(\s*[\'"]\s*(?:'.self::AGREGAT.')/i';

                if (! preg_match_all($pola, $isi, $cocok, PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                foreach ($cocok[0] as $satu) {
                    $baris = substr_count(substr($isi, 0, $satu[1]), "\n") + 1;
                    $temuan[] = sprintf('%s:%d', $rel, $baris);
                }
            }
        }

        sort($temuan);

        $this->assertSame(
            [],
            $temuan,
            'pluck() menerima ekspresi agregat mentah sebagai nama properti. SQL Server '
            .'mengembalikan kolom agregat tanpa nama, jadi hasilnya "Undefined property" — '
            .'dan itu baru muncul ketika ADA baris yang cocok, bukan saat hitungannya nol. '
            ."Beri alias lewat selectRaw lalu pluck alias itu.\n  "
            .implode("\n  ", $temuan)
        );
    }

    /** Komentar dibuang tetapi jumlah barisnya dipertahankan. */
    private function tanpaKomentar(string $teks): string
    {
        $token = @token_get_all($teks);

        if ($token === false) {
            return $teks;
        }

        $hasil = '';
        foreach ($token as $t) {
            if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $hasil .= str_repeat("\n", substr_count($t[1], "\n"));

                continue;
            }

            $hasil .= is_array($t) ? $t[1] : $t;
        }

        return $hasil;
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
