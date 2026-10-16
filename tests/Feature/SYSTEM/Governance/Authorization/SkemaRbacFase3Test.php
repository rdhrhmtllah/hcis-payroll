<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** RBAC Fase 3a: objek DDL-2026-168 ada. */
class SkemaRbacFase3Test extends TestCase
{
    private function kolom(string $t): array
    {
        return array_column(DB::select('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ?', [$t]), 'COLUMN_NAME');
    }

    public function test_tabel_mode_dan_selisih_ada(): void
    {
        $this->assertSame(['Id_Grup_Fitur', 'Mode', 'Updated_At', 'Updated_By'], $this->kolom('N_HRIS_Akses_Mode_Modul'));
        $this->assertSame(
            ['Id_Akses_Selisih', 'Id_User', 'Jenis_page', 'Aksi', 'Keputusan_Lama', 'Keputusan_Baru', 'Jumlah', 'Pertama_At', 'Terakhir_At'],
            $this->kolom('N_HRIS_Akses_Selisih'),
        );
    }
}
