<?php

namespace Tests\Feature\LMS\SOP;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Gerbang skema Fase 1. DDL modul SOP dijalankan manual (tak ada Laravel
 * migration), jadi tes inilah satu-satunya yang membuktikan 26A sudah benar
 * dijalankan di basis data yang sedang dipakai.
 */
class SopDokumenSkemaTest extends TestCase
{
    private function kolom(string $tabel): array
    {
        return array_map(
            fn ($r) => $r->COLUMN_NAME,
            DB::select('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ?', [$tabel])
        );
    }

    public function test_tabel_dokumen_ada_dengan_kolom_yang_dibutuhkan(): void
    {
        $kolom = $this->kolom('N_SOP_Dokumen');
        $this->assertNotEmpty($kolom, 'Tabel N_SOP_Dokumen belum ada — jalankan plans/sop/sql/26A_sop_dokumen_ddl.sql');

        foreach ([
            'Id_SOP_Dokumen', 'Nomor_SOP_Resmi', 'Judul', 'Tipe', 'Deskripsi',
            'Id_Versi_Aktif', 'Flag_Aktif', 'Created_By', 'Created_At', 'Updated_By', 'Updated_At',
        ] as $wajib) {
            $this->assertContains($wajib, $kolom, "Kolom {$wajib} hilang di N_SOP_Dokumen.");
        }
    }

    public function test_kolom_rujukan_dokumen_ditambahkan_ke_dua_tabel(): void
    {
        $this->assertContains('Id_SOP_Dokumen', $this->kolom('N_SOP_Item_Versi'),
            'N_SOP_Item_Versi.Id_SOP_Dokumen belum ada — jalankan 26A.');
        $this->assertContains('Id_SOP_Dokumen', $this->kolom('N_SOP_Paket_Item'),
            'N_SOP_Paket_Item.Id_SOP_Dokumen belum ada — jalankan 26A.');
    }

    public function test_kolom_rujukan_masih_nullable_di_fase_1(): void
    {
        // Wajib nullable: tipe PARAGRAF/TAUTAN/ABSENSI tak punya dokumen, dan
        // backfill (Task 2) harus bisa berjalan bertahap tanpa melanggar constraint.
        foreach ([['N_SOP_Item_Versi'], ['N_SOP_Paket_Item']] as [$tabel]) {
            $nullable = DB::selectOne(
                'SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$tabel, 'Id_SOP_Dokumen']
            );
            $this->assertSame('YES', $nullable->IS_NULLABLE, "{$tabel}.Id_SOP_Dokumen harus nullable di Fase 1.");
        }
    }
}
