<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** RBAC Fase 2a: konversi kondisi sekarang ke model baru (DML-2026-166). */
class KonversiRbacTest extends TestCase
{
    use DatabaseTransactions;

    private const BERKAS = 'database/dml/DML-2026-166-rbac-konversi-role-hak/data-change.sql';

    private function sql(): string
    {
        return file_get_contents(base_path(self::BERKAS));
    }

    private function jalankan(): void
    {
        DB::unprepared($this->sql());
    }

    private function hitung(): array
    {
        return [
            DB::table('N_HRIS_Role_Hak')->count(),
            DB::table('N_HRIS_Role_User_Cakupan')->count(),
            DB::table('N_HRIS_Role_User_Tanggung_Jawab')->count(),
        ];
    }

    public function test_idempoten(): void
    {
        $this->jalankan();
        $sekali = $this->hitung();
        $this->jalankan();

        $this->assertSame($sekali, $this->hitung());
        $this->assertGreaterThan(0, $sekali[0], 'Konversi tidak menghasilkan satu hak pun.');
    }

    public function test_daftar_di_sql_sama_dengan_config(): void
    {
        $sql = $this->sql();
        preg_match('/-- HALAMAN_TATA_KELOLA\n(.*?)\n/s', $sql, $h);
        preg_match('/-- PERAN_ADMIN\n(.*?)\n/s', $sql, $p);
        $daftar = function ($baris) {
            preg_match_all("/'([^']+)'/", (string) $baris, $m);

            return $m[1];
        };

        $this->assertEqualsCanonicalizing(
            array_map(fn ($x) => strtolower($x), config('authorization.tata_kelola.halaman')),
            $daftar($h[1] ?? ''),
            'Daftar halaman tata kelola di DML-2026-166 berbeda dari config.',
        );
        // Peran admin yang lahir SETELAH DML-2026-166 (DML-2026-180/182) tak ada di daftarnya.
        $sesudah166 = ['DMS_ADMIN', 'ESS_SUPER_ADMIN', 'LMS_SUPER_ADMIN', 'VISITOR_SUPER_ADMIN', 'DMS_SUPER_ADMIN', 'VISITOR_ADMIN', 'LMS_ADMIN', 'KPI_ADMIN', 'ESS_ADMIN'];
        $this->assertEqualsCanonicalizing(
            array_values(array_diff(array_map('strtoupper', config('authorization.tata_kelola.peran_admin')), $sesudah166)),
            $daftar($p[1] ?? ''),
            'Daftar role admin di DML-2026-166 berbeda dari config.',
        );
    }

    public function test_role_dan_penugasan_nonaktif_tidak_dikonversi(): void
    {
        $ess = DB::table('N_HRIS_Master_Grup_Fitur')->where('Kode_Grup_Fitur', 'ESS')->value('Id_Grup_Fitur');
        $idRole = DB::table('N_HRIS_Role_Functional')->insertGetId([
            'Role_Code' => 'UJI_NONAKTIF_'.random_int(1000, 9999), 'Role_Name' => 'Uji', 'Flag_Aktif' => 'T',
            'Created_At' => now(), 'Id_Grup_Fitur' => $ess,
        ], 'Id_Role');
        $scope = DB::table('N_HRIS_Scope_Definition')->where('Scope_Code', 'GLOBAL_ALL')->value('Id_Scope');
        $roleAktif = DB::table('N_HRIS_Role_Functional')->where('Role_Code', 'ESS_MENU_ADMINISTRATOR')->value('Id_Role');
        $idPenugasan = DB::table('N_HRIS_Role_User')->insertGetId([
            'UserId_Web' => 4, 'Id_Role' => $roleAktif, 'Assigned_At' => now(), 'Assigned_By' => 'UJI', 'Flag_Aktif' => 'T',
        ], 'Id');

        $this->jalankan();

        $this->assertSame(0, DB::table('N_HRIS_Role_Hak')->where('Id_Role', $idRole)->count());
        $this->assertSame(0, DB::table('N_HRIS_Role_User_Cakupan')->where('Id_Role_User', $idPenugasan)->count());
        $this->assertSame(0, DB::table('N_HRIS_Role_User_Tanggung_Jawab')->where('Id_Role_User', $idPenugasan)->count());
        $this->assertNotNull($scope);
    }

    public function test_hasil_mengikuti_aturan(): void
    {
        $this->jalankan();
        $hak = fn (string $role, string $page, string $aksi = 'VIEW') => DB::table('N_HRIS_Role_Hak as h')
            ->join('N_HRIS_Role_Functional as r', 'r.Id_Role', '=', 'h.Id_Role')
            ->join('N_HRIS_Klasifikasi_Aksi as a', 'a.Id_Klasifikasi_Actions', '=', 'h.Id_Aksi')
            ->where('r.Role_Code', $role)->where('h.Jenis_page', $page)->where('a.Nama_Aksi', $aksi)->exists();

        $this->assertFalse($hak('HCIS_LEADER', 'adminAllPagesAccess'), 'Role operasional mendapat halaman tata kelola.');
        $this->assertTrue($hak('ESS_MENU_ADMINISTRATOR', 'adminAllPagesAccess'));
        $this->assertTrue($hak('HCIS_LEADER', 'IzinPageAdmin'));
        $this->assertSame('ADMIN', DB::table('N_HRIS_Role_Functional')->where('Role_Code', 'KPI_SUPER_ADMIN')->value('Jenis_Role'));
        $this->assertSame('POOL_APPROVAL', DB::table('N_HRIS_Role_Functional')->where('Role_Code', 'ADMIN_PERMIT')->value('Jenis_Role'));
        $this->assertSame('AKSES', DB::table('N_HRIS_Role_Functional')->where('Role_Code', 'HCIS_LEADER')->value('Jenis_Role'));

        // Tanpa halaman ber-hibah yang kehilangan VIEW.
        $tanpaView = DB::table('N_HRIS_Role_Hak as h')
            ->whereNotExists(fn ($q) => $q->from('N_HRIS_Role_Hak as v')->whereColumn('v.Id_Role', 'h.Id_Role')
                ->whereColumn('v.Jenis_page', 'h.Jenis_page')
                ->where('v.Id_Aksi', DB::table('N_HRIS_Klasifikasi_Aksi')->where('Nama_Aksi', 'VIEW')->value('Id_Klasifikasi_Actions')))
            ->count();
        $this->assertSame(0, $tanpaView);

        // Super admin KPI memegang ketiga tanggung jawab.
        $tj = DB::table('N_HRIS_Role_User_Tanggung_Jawab as t')->join('N_HRIS_Role_User as ru', 'ru.Id', '=', 't.Id_Role_User')
            ->join('N_HRIS_Role_Functional as r', 'r.Id_Role', '=', 'ru.Id_Role')->where('r.Role_Code', 'KPI_SUPER_ADMIN')
            ->where('ru.Flag_Aktif', 'Y')->distinct()->pluck('t.Kode')->sort()->values()->all();
        $this->assertSame(['AKSES', 'APPROVAL', 'KEANGGOTAAN'], $tj);
    }
}
