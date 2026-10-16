<?php

namespace Tests\Feature\SYSTEM\Application;

use FilesystemIterator;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Nama kolom yang disebut kode harus benar-benar ada di tabelnya.
 *
 * ── Kenapa dijaga ────────────────────────────────────────────────────────────
 *
 * Kolom yang salah nama tidak ketahuan saat menulis kode maupun saat review; ia
 * ketahuan saat SQL Server menolaknya di runtime — dan hanya bila jalur itu
 * kebetulan dilewati. Jalur yang jarang dilewati justru yang paling lama
 * menyimpannya.
 *
 * Dua yang ditemukan 9 September 2026, keduanya tersembunyi bertahun-tahun:
 *
 *   - ApprovalRequestService menyaring N_HRIS_HC_Approval dengan
 *     `Group_Reference_Id` dan `Kode_Karyawan`; kolomnya `Group_Id` dan
 *     `Kode_Karyawan_Approver`. Tanpa try/catch di jalur itu, SETIAP approval
 *     bergrup non-ROLE berakhir 500.
 *   - KonversiLemburService mengurutkan N_HRIS_Master_Konversi_Lembur menurut
 *     `Created_At`, kolom yang tak pernah ada. Galatnya ditelan try/catch dan
 *     fungsinya diam-diam mengembalikan nilai default — jadi aturan konversi
 *     yang diatur admin tidak akan pernah terpakai.
 *
 * ── Yang TIDAK diperiksa, dan kenapa ─────────────────────────────────────────
 *
 * Rantai yang memuat join, select/selectRaw, atau DB::raw dilewati: yang pertama
 * membawa kolom tabel lain, dua terakhir membawa ALIAS hasil hitungan
 * (`COUNT(*) as jumlah`) yang memang bukan kolom tabel. Memaksakan pemeriksaan
 * di sana menghasilkan daftar berisi kolom yang sebenarnya benar — dan sekali
 * orang menemukan satu yang keliru, ia berhenti membaca sisanya.
 *
 * Hanya pemanggilan di kedalaman kurung NOL yang dihitung. Tanpa itu,
 * `whereIn('Id', $rows->pluck('Id_Workgroup'))` terbaca seolah `Id_Workgroup`
 * kolom tabel, padahal itu pluck milik Collection.
 *
 * Komentar dibuang lebih dulu lewat token_get_all: tiga dari empat belas
 * kandidat pertama ternyata kode yang sudah dikomentari.
 */
class KolomAdaDiSkemaTest extends TestCase
{
    /** Metode yang argumen pertamanya nama kolom polos. */
    private const METODE_KOLOM = [
        'where', 'orWhere', 'whereNot', 'whereIn', 'whereNotIn', 'whereNull', 'whereNotNull',
        'orderBy', 'orderByDesc', 'groupBy', 'value', 'max', 'min', 'sum', 'avg',
        'whereBetween', 'whereDate', 'increment', 'decrement',
    ];

    public function test_setiap_kolom_yang_disebut_ada_di_tabelnya(): void
    {
        $skema = $this->skema();
        $this->assertNotEmpty($skema, 'Katalog kolom kosong — pemindai ini tak menguji apa pun.');

        $temuan = [];

        foreach ($this->berkasPhp(base_path('app')) as $jalur) {
            $isi = $this->tanpaKomentar((string) file_get_contents($jalur));
            $rel = str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], $jalur);

            if (! preg_match_all("/DB::table\(\s*'([A-Za-z0-9_]+)'\s*\)/", $isi, $cocok, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($cocok as $i => $satu) {
                $tabel = strtolower($satu[1][0]);
                if (! isset($skema[$tabel])) {
                    continue;   // nama tabel dinamis atau tabel di luar katalog
                }

                $mulai = $satu[0][1] + strlen($satu[0][0]);
                $batasKoma = strpos($isi, ';', $mulai);
                $batasTabel = $cocok[$i + 1][0][1] ?? PHP_INT_MAX;
                $akhir = min($batasKoma === false ? PHP_INT_MAX : $batasKoma, $batasTabel);
                $rantai = substr($isi, $mulai, $akhir - $mulai);

                if (preg_match('/->(join|leftJoin|rightJoin|crossJoin|joinSub|select|selectRaw|selectSub|fromSub|union)\(/', $rantai)
                    || str_contains($rantai, 'DB::raw(')) {
                    continue;
                }

                /* `get([...])`, `first([...])` dan `pluck()` juga daftar select — dan
                   ketika salah satunya memuat alias `Kolom as x`, nama `x` dipakai lagi
                   di rantai berikutnya (`->groupBy('x')`) sebagai kunci COLLECTION, bukan
                   kolom tabel. Alasannya sama persis dengan `select(` di atas; yang lolos
                   di sini cuma karena bentuk penulisannya berbeda. Terukur pada
                   PaAssignmentHealthService: `get([... as lvl])->groupBy('lvl')` dituduh
                   menyebut kolom "lvl" milik N_PA_Participant. */
                if (preg_match('/->(get|first|pluck)\(\s*\[[^\]]*as\s/i', $rantai)) {
                    continue;
                }

                $baris = substr_count(substr($isi, 0, $satu[0][1]), "\n") + 1;

                foreach ($this->kolomDiKedalamanNol($rantai) as $kolom) {
                    if (! isset($skema[$tabel][strtolower($kolom)])) {
                        $temuan[] = sprintf('%s:%d  %s tidak punya kolom "%s"', $rel, $baris, $satu[1][0], $kolom);
                    }
                }
            }
        }

        sort($temuan);

        $this->assertSame(
            [],
            array_values(array_unique($temuan)),
            "Kode menyebut kolom yang tidak ada di tabelnya. SQL Server menolaknya saat\n".
            "dijalankan; kalau jalurnya dibungkus try/catch, ia gagal tanpa suara.\n  "
            .implode("\n  ", array_unique($temuan))
        );
    }

    /**
     * Nama kolom pada pemanggilan di kedalaman kurung NOL.
     *
     * @return list<string>
     */
    private function kolomDiKedalamanNol(string $rantai): array
    {
        $hasil = [];
        $dalam = 0;
        $n = strlen($rantai);

        for ($i = 0; $i < $n; $i++) {
            $c = $rantai[$i];

            if ($c === ')') {
                $dalam = max(0, $dalam - 1);

                continue;
            }

            if ($c !== '-' || ($rantai[$i + 1] ?? '') !== '>') {
                if ($c === '(') {
                    $dalam++;
                }

                continue;
            }

            if (! preg_match("/^->([A-Za-z]+)\(\s*'([^']+)'/", substr($rantai, $i, 120), $m)) {
                continue;
            }

            if ($dalam === 0 && in_array($m[1], self::METODE_KOLOM, true)) {
                $kolom = $m[2];
                if (! str_contains($kolom, '.') && ! str_contains($kolom, ' ') && ! str_contains($kolom, '(')) {
                    $hasil[] = $kolom;
                }
            }
        }

        return $hasil;
    }

    /** @return array<string,array<string,true>> */
    private function skema(): array
    {
        $peta = [];

        foreach (DB::select("
            SELECT o.name AS tabel, c.name AS kolom
              FROM sys.columns c
              JOIN sys.objects o ON o.object_id = c.object_id
             WHERE o.type IN ('U','V')") as $r) {
            $peta[strtolower($r->tabel)][strtolower($r->kolom)] = true;
        }

        return $peta;
    }

    /** Komentar dibuang, jumlah barisnya dipertahankan agar nomor barisnya tetap benar. */
    private function tanpaKomentar(string $isi): string
    {
        $token = @token_get_all($isi);

        if ($token === false) {
            return $isi;
        }

        $keluar = '';
        foreach ($token as $t) {
            if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $keluar .= str_repeat("\n", substr_count($t[1], "\n"));

                continue;
            }
            $keluar .= is_array($t) ? $t[1] : $t;
        }

        return $keluar;
    }

    /** @return list<string> */
    private function berkasPhp(string $akar): array
    {
        $hasil = [];
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($akar, FilesystemIterator::SKIP_DOTS));

        foreach ($rii as $f) {
            if ($f->getExtension() !== 'php') {
                continue;
            }

            /* `app/Services/snapshot/` menyimpan SALINAN BEKU kode sebelum migrasi, dan
               isinya di-gitignore — ia tidak pernah dimuat, tidak pernah dirujuk, dan tidak
               ada di CI. Memindainya berarti penjaga ini merah di mesin yang kebetulan
               menyimpan salinannya dan hijau di mesin yang tidak: kegagalan yang bergantung
               pada isi cakram, bukan pada isi repositori. */
            if (str_contains(str_replace(DIRECTORY_SEPARATOR, '/', $f->getPathname()), '/app/Services/snapshot/')) {
                continue;
            }

            $hasil[] = $f->getPathname();
        }

        sort($hasil);

        return $hasil;
    }
}
