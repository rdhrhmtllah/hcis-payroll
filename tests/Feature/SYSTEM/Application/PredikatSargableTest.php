<?php

namespace Tests\Feature\SYSTEM\Application;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * whereMonth / whereYear / whereDay membungkus KOLOMNYA dalam fungsi.
 *
 * ── Kenapa dijaga ────────────────────────────────────────────────────────────
 *
 *     ->whereMonth('Tanggal_Approval', now()->month)
 *     ->whereYear('Tanggal_Approval', now()->year)
 *
 * menjadi `MONTH(Tanggal_Approval) = 9 AND YEAR(...) = 2026`. Karena kolomnya
 * yang dibungkus fungsi, SQL Server tidak bisa men-seek indeks apa pun di
 * kolom itu dan terpaksa memindai. Pada tabel yang tumbuh, biayanya naik terus
 * tanpa ada yang berubah di kodenya.
 *
 * Bentuk yang setara dan sargable adalah rentang SETENGAH TERBUKA:
 *
 *     ->where('Tanggal_Approval', '>=', now()->startOfMonth())
 *     ->where('Tanggal_Approval', '<', now()->startOfMonth()->addMonth())
 *
 * Batas atas EKSKLUSIF, bukan `<=` pada 23:59:59.999 — kolom bertipe `datetime`
 * membulatkan nilai itu ke tengah malam berikutnya, jadi `<=` diam-diam ikut
 * memuat baris hari berikutnya.
 *
 * ── Dua yang tidak bisa diubah ───────────────────────────────────────────────
 *
 * whereMonth TANPA whereYear menanyakan "bulan masuknya sama, tahun berapa pun"
 * — kueri ulang tahun. Tidak ada rentang yang setara; yang setara hanya kolom
 * terhitung ber-indeks, dan itu perubahan skema. Keduanya berjalan atas tabel
 * Karyawan yang berisi ratusan baris, jadi pemindaiannya murah. Didaftarkan,
 * bukan dipaksa.
 */
class PredikatSargableTest extends TestCase
{
    /**
     * Tempat yang masih memakai whereMonth/whereYear/whereDay, beserta sebabnya.
     * HANYA BOLEH BERKURANG.
     *
     * @var array<string, string>
     */
    private const DIIZINKAN = [
        'Console/Commands/ESS/Attendance/Leave/Admin/HakCutiTambahan.php' => 'whereMonth(Tgl_Masuk) tanpa tahun: ulang tahun masuk, tak punya bentuk rentang',
        'Services/ESS/Attendance/Leave/Dashboard/CutiDashboardService.php' => 'whereMonth(Tgl_Masuk) tanpa tahun: ulang tahun masuk, tak punya bentuk rentang',
    ];

    public function test_tidak_ada_predikat_tanggal_baru_yang_mematikan_indeks(): void
    {
        $temuan = [];

        foreach ($this->berkasPhp(base_path('app')) as $jalur) {
            $isi = $this->tanpaKomentar(file_get_contents($jalur));
            $rel = str_replace([base_path('app').DIRECTORY_SEPARATOR, '\\'], ['', '/'], $jalur);

            if (! preg_match_all('/->where(Month|Year|Day)\s*\(/', $isi, $cocok, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            if (array_key_exists($rel, self::DIIZINKAN)) {
                continue;
            }

            foreach ($cocok[0] as $satu) {
                $baris = substr_count(substr($isi, 0, $satu[1]), "\n") + 1;
                $temuan[] = sprintf('%s:%d %s', $rel, $baris, trim($satu[0]));
            }
        }

        sort($temuan);

        $this->assertSame(
            [],
            $temuan,
            'Predikat tanggal membungkus kolomnya dengan MONTH()/YEAR()/DAY(), sehingga '
            .'indeks di kolom itu tidak bisa di-seek. Pakai rentang setengah terbuka '
            .'(>= awal, < awal berikutnya) — batas atas EKSKLUSIF, karena kolom datetime '
            ."membulatkan 23:59:59.999 ke tengah malam berikutnya.\n  "
            .implode("\n  ", $temuan)
        );
    }

    public function test_daftar_pengecualian_tidak_menyimpan_yang_sudah_diperbaiki(): void
    {
        $basi = [];

        foreach (array_keys(self::DIIZINKAN) as $rel) {
            $jalur = base_path('app'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel));

            if (! is_file($jalur)) {
                $basi[] = "{$rel} — berkasnya sudah tidak ada";

                continue;
            }

            if (! preg_match('/->where(Month|Year|Day)\s*\(/', $this->tanpaKomentar(file_get_contents($jalur)))) {
                $basi[] = "{$rel} — sudah tidak memakai predikat itu";
            }
        }

        sort($basi);

        $this->assertSame(
            [],
            $basi,
            "Coret dari DIIZINKAN supaya daftar ini tidak berubah jadi tempat sampah abadi.\n  "
            .implode("\n  ", $basi)
        );
    }

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
            if ($berkas->getExtension() !== 'php') {
                continue;
            }

            /* `app/Services/snapshot/` menyimpan SALINAN BEKU kode sebelum migrasi, dan
               isinya di-gitignore -- ia tidak pernah dimuat, tidak pernah dirujuk, dan tidak
               ada di CI. Memindainya berarti penjaga ini merah di mesin yang kebetulan
               menyimpan salinannya dan hijau di mesin yang tidak: kegagalan yang bergantung
               pada isi cakram, bukan pada isi repositori. */
            if (str_contains(str_replace(DIRECTORY_SEPARATOR, '/', $berkas->getPathname()), '/app/Services/snapshot/')) {
                continue;
            }

            $hasil[] = $berkas->getPathname();
        }

        return $hasil;
    }
}
