<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** RBAC Fase 2a: objek DDL-2026-165 ada di database ini. */
class SkemaRbacFase2Test extends TestCase
{
    private function kolom(string $tabel): array
    {
        return array_column(DB::select('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ?', [$tabel]), 'COLUMN_NAME');
    }

    public function test_tabel_dan_kolom_baru_ada(): void
    {
        $this->assertSame(
            ['Id_Role_Hak', 'Id_Role', 'Jenis_page', 'Id_Aksi', 'Flag_Aktif', 'Created_At', 'Created_By', 'Updated_At', 'Updated_By'],
            $this->kolom('N_HRIS_Role_Hak'),
        );
        $this->assertContains('Jenis_Role', $this->kolom('N_HRIS_Role_Functional'));
        $this->assertContains('Berlaku_Sampai', $this->kolom('N_HRIS_Role_User'));
        $this->assertContains('Alasan', $this->kolom('N_HRIS_Role_User'));
        $this->assertContains('Berlaku_Sampai', $this->kolom('HRIS_Page_Access'));
        $this->assertContains('Alasan', $this->kolom('HRIS_Page_Access'));
        $this->assertSame(['Id_Role_User_Cakupan', 'Id_Role_User', 'Jenis_Cakupan', 'Nilai', 'Flag_Aktif', 'Created_At'], $this->kolom('N_HRIS_Role_User_Cakupan'));
        $this->assertSame(['Id_Role_User_Tanggung_Jawab', 'Id_Role_User', 'Kode', 'Flag_Aktif', 'Created_At'], $this->kolom('N_HRIS_Role_User_Tanggung_Jawab'));
    }

    public function test_jenis_role_berdefault_akses(): void
    {
        $this->assertSame(0, DB::table('N_HRIS_Role_Functional')->whereNull('Jenis_Role')->count());
    }
}
