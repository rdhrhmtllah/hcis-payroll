<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Support\Facades\DB;

/** Fixture bersama uji Page Access Setup (admin berhalaman kelolaan + target). */
trait FixturePageAccess
{
    /**
     * Admin Page Access dengan halaman kelolaan grup KPI.
     *
     * Kelolaan diatur lewat N_HRIS_Role_Konten_Access yang menempel pada grant
     * adminAllPagesAccess si admin. Di dev seluruh baris itu menunjuk grant
     * yang sudah nonaktif, jadi fixture menyiapkannya sendiri (di-rollback).
     *
     * @return array{0: User, 1: object} [admin, satu halaman kelolaan non-tata-kelola {id, Jenis_page}]
     */
    protected function adminDanHalaman(): array
    {
        $idAdmin = DB::table('Karyawan as k')
            ->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')
            ->where('k.Aktif', 'Y')
            ->where(fn ($q) => $q->whereNull('u.Is_System_Admin')->orWhere('u.Is_System_Admin', '<>', 'Y'))
            ->orderBy('u.Id_Users')
            ->value('u.Id_Users');
        $this->assertNotNull($idAdmin);
        $idGrant = DB::table('HRIS_Page_Access')->insertGetId([
            'Kode_Perusahaan' => '001', 'Jenis_page' => 'adminAllPagesAccess', 'UserID_Web' => $idAdmin,
            'ID_Divisi' => 0, 'ID_Level' => 0, 'Flag_Aktif' => 'Y',
        ], 'Id_Page_Access');
        $idKpi = DB::table('N_HRIS_Master_Grup_Fitur')->where('Kode_Grup_Fitur', 'KPI')->value('Id_Grup_Fitur');
        DB::table('N_HRIS_Role_Konten_Access')->insert([
            'Id_Page_Access' => $idGrant, 'Id_Grup_Fitur' => $idKpi, 'Flag_Aktif' => 'Y', 'Created_At' => now(),
        ]);

        $hal = DB::table('N_HRIS_Grup_Fitur_Map as gfm')
            ->join('N_HRIS_Menus as m', 'm.Jenis_page', '=', 'gfm.Jenis_page')
            ->where('gfm.Id_Grup_Fitur', $idKpi)
            ->whereNotIn('m.Jenis_page', (array) config('authorization.tata_kelola.halaman'))
            ->orderBy('m.id_menu')
            ->first(['m.id_menu as id', 'm.Jenis_page']);
        $this->assertNotNull($hal, 'Grup KPI tidak punya halaman non-tata-kelola.');

        return [User::where('Id_Users', $idAdmin)->firstOrFail(), $hal];
    }

    /** Karyawan aktif (bukan admin) tanpa baris apa pun untuk halaman ini. */
    protected function karyawanTanpaBaris(string $jenisPage, int $kecuali): int
    {
        $id = DB::table('Karyawan as k')
            ->where('k.Aktif', 'Y')->whereNotNull('k.UserID_Web')->where('k.UserID_Web', '<>', $kecuali)
            ->whereNotExists(fn ($q) => $q->from('HRIS_Page_Access as pa')
                ->whereColumn('pa.UserID_Web', 'k.UserID_Web')->where('pa.Jenis_page', $jenisPage))
            ->orderBy('k.UserID_Web')
            ->value('k.UserID_Web');
        $this->assertNotNull($id);

        return (int) $id;
    }
}
