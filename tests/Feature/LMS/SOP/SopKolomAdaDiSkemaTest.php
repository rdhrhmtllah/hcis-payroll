<?php

namespace Tests\Feature\LMS\SOP;

use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Penjaga: nama kolom SOP yang ditulis di query builder harus benar-benar ada.
 *
 * Lahir dari galat 500 saat menyunting Section di SOP Builder, 20 Agustus 2026:
 *
 *   Invalid column name 'Id_SOP_Paket_Section'
 *   select top 1 * from [N_SOP_Paket_Section] where [Id_SOP_Paket_Section] = 1184
 *
 * PK tabel itu bernama `Id_SOP_Section`. Seluruh kode lain memakainya dengan
 * benar; satu baris memakai nama yang tidak pernah ada. Ia lolos berbulan-bulan
 * karena berada di cabang non-draft sebuah ternary — cabang yang tak pernah
 * dilewati sampai ada yang menyunting section di luar mode draft.
 *
 * Salah paham yang sama pernah muncul dan dikoreksi di rencana Export Studio
 * fase 1 (`ps.Id_SOP_Paket_Section` -> `ps.Id_SOP_Section`), tetapi baris ini
 * tak ikut terbawa. Karena itu penjaganya di sini, bukan perbaikan satu baris
 * saja: yang berulang adalah salah pahamnya, bukan barisnya.
 *
 * Pemeriksaan ini READ-ONLY — satu kueri INFORMATION_SCHEMA, tanpa kunci baris.
 */
class SopKolomAdaDiSkemaTest extends TestCase
{
    /** Baris yang benar-benar menyusun query, bukan yang membaca payload. */
    private const PEMAKAI_KOLOM = '/->(where|orWhere|whereIn|value|orderBy|groupBy|join|leftJoin|select|pluck)\s*\(/';

    /**
     * Nama seperti `Id_SOP_Paket_Tujuan` dan `Id_SOP_Section_Tujuan` adalah
     * field permintaan, bukan kolom. Tanpa pengecualian ini penjaga menuduh
     * keduanya dan orang belajar mengabaikan warna merahnya.
     */
    private const BUKAN_KOLOM = '/\$request->|Hashids|::encode|->input\(/';

    public function test_kolom_id_sop_yang_dipakai_query_ada_di_skema(): void
    {
        $rows = DB::select(
            "SELECT DISTINCT COLUMN_NAME c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME LIKE 'N[_]SOP[_]%'"
        );

        $adaDiSkema = array_flip(array_map(static fn ($r) => $r->c, $rows));

        $this->assertNotEmpty(
            $adaDiSkema,
            'Skema N_SOP_* tidak terbaca. Penjaga yang tak bisa membaca skema akan lulus tanpa memeriksa apa pun — '
            .'itu lebih berbahaya daripada tidak ada penjaga.'
        );

        $pelanggaran = [];

        foreach ($this->berkasSop() as $jalur) {
            foreach (explode("\n", file_get_contents($jalur)) as $i => $baris) {
                if (! preg_match(self::PEMAKAI_KOLOM, $baris) || preg_match(self::BUKAN_KOLOM, $baris)) {
                    continue;
                }

                preg_match_all("/'(Id_SOP_[A-Za-z_]+)'/", $baris, $cocok);

                foreach ($cocok[1] as $nama) {
                    if (! isset($adaDiSkema[$nama])) {
                        $pelanggaran[] = sprintf(
                            '%s:%d memakai kolom "%s" yang tidak ada di skema N_SOP_*',
                            str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], $jalur),
                            $i + 1,
                            $nama
                        );
                    }
                }
            }
        }

        $this->assertSame([], $pelanggaran, implode("\n", $pelanggaran));
    }

    /** @return list<string> berkas PHP di app/ yang menyentuh tabel SOP. */
    private function berkasSop(): array
    {
        $hasil = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('app')));

        foreach ($it as $f) {
            if (! $f->isFile() || $f->getExtension() !== 'php') {
                continue;
            }
            if (str_contains(file_get_contents($f->getPathname()), 'N_SOP_')) {
                $hasil[] = $f->getPathname();
            }
        }

        return $hasil;
    }
}
