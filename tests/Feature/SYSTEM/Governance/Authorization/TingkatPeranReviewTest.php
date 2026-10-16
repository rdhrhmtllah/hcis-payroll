<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\ModeAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\SkemaRbac;
use App\Services\SYSTEM\Governance\Authorization\Akses\TingkatPeran;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Temuan review master tingkat peran. Tiap uji gagal sebelum perbaikannya. */
class TingkatPeranReviewTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');
        TingkatPeran::lupakan();
    }

    private function sa(): User
    {
        return User::where('Id_Users', 4)->firstOrFail();
    }

    private function karyawan(array $kecuali = []): User
    {
        $id = DB::table('Karyawan as k')->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')->where('k.Aktif', 'Y')
            ->where(fn ($w) => $w->whereNull('u.Is_System_Admin')->orWhere('u.Is_System_Admin', '<>', 'Y'))
            ->whereNotExists(fn ($w) => $w->from('N_HRIS_Role_User as ru')->whereColumn('ru.UserId_Web', 'u.Id_Users'))
            ->whereNotExists(fn ($w) => $w->from('HRIS_Page_Access as pa')->whereColumn('pa.UserID_Web', 'u.Id_Users')->where('pa.Flag_Aktif', 'Y'))
            ->whereNotIn('u.Id_Users', $kecuali ?: [0])->orderBy('u.Id_Users')->value('u.Id_Users');

        return User::where('Id_Users', $id)->firstOrFail();
    }

    private function idRole(string $kode): int
    {
        return (int) DB::table('N_HRIS_Role_Functional')->where('Role_Code', $kode)->value('Id_Role');
    }

    private function grup(string $kode): int
    {
        return (int) DB::table('N_HRIS_Master_Grup_Fitur')->where('Kode_Grup_Fitur', $kode)->value('Id_Grup_Fitur');
    }

    private function pegang(User $u, string $kode, array $tj = ['AKSES']): int
    {
        $id = DB::table('N_HRIS_Role_User')->insertGetId([
            'UserId_Web' => $u->Id_Users, 'Id_Role' => $this->idRole($kode), 'Assigned_At' => now(), 'Assigned_By' => 'UJI', 'Flag_Aktif' => 'Y',
        ], 'Id');
        foreach ($tj as $k) {
            DB::table('N_HRIS_Role_User_Tanggung_Jawab')->insert(['Id_Role_User' => $id, 'Kode' => $k]);
        }
        KeputusanAkses::lupakanCache();

        return $id;
    }

    private function essBaru(): void
    {
        DB::table('N_HRIS_Akses_Mode_Modul')->updateOrInsert(['Id_Grup_Fitur' => $this->grup('ESS')], ['Mode' => 'BARU']);
        ModeAkses::lupakan();
    }

    /** I1: tanpa DDL-2026-165 (Jenis_Role belum ada) sidebar tak boleh menyebut kolom itu. */
    public function test_i1_tanpa_model_baru_tak_memilih_jenis_role(): void
    {
        $a = $this->karyawan();
        $this->pegang($a, 'KPI_MENU_ADMINISTRATOR');
        $r = new \ReflectionProperty(SkemaRbac::class, 'bendera');
        $r->setAccessible(true);
        $r->setValue(null, ['model_baru' => false, 'kedaluwarsa_penugasan' => false, 'kedaluwarsa_grant' => false, 'audit' => false, 'tingkat' => false]);
        KeputusanAkses::lupakanCache();

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(KeputusanAkses::class)->bolehKelolaAkses($a);
        $sql = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        DB::disableQueryLog();

        $this->assertStringNotContainsString('Jenis_Role', $sql, 'Sidebar memilih Jenis_Role padahal DDL-2026-165 belum ada.');
    }

    /** I2: Super Admin modul tak bisa mengubah matriks peran admin pembawa halaman SYSTEM_ONLY. */
    public function test_i2_matriks_admin_pembawa_halaman_sistem_hanya_system_admin(): void
    {
        $this->essBaru();
        $s = $this->karyawan();
        $this->pegang($s, 'ESS_SUPER_ADMIN');

        // Peran admin ESS buatan: membawa IzinPageAdmin (SYSTEM_ONLY) + satu halaman biasa.
        $view = DB::table('N_HRIS_Klasifikasi_Aksi')->where('Nama_Aksi', 'VIEW')->value('Id_Klasifikasi_Actions');
        $admin = (int) DB::table('N_HRIS_Role_Functional')->insertGetId([
            'Role_Code' => 'UJI_TPR_ADMIN_ESS', 'Role_Name' => 'Uji', 'Flag_Aktif' => 'Y', 'Created_At' => now(),
            'Jenis_Role' => 'ADMIN', 'Kode_Tingkat' => 'ADMIN', 'Id_Grup_Fitur' => $this->grup('ESS'),
        ], 'Id_Role');
        foreach (['IzinPageAdmin', 'IzinPage'] as $p) {
            DB::table('N_HRIS_Role_Hak')->insert(['Id_Role' => $admin, 'Jenis_page' => $p, 'Id_Aksi' => $view, 'Flag_Aktif' => 'Y', 'Created_By' => 'UJI']);
        }

        // Membuang halaman sistemnya = membuat peran itu "lolos" diangkat Super Admin — tak boleh.
        $this->actingAs($s)->putJson("/api/v1/akses-peran/peran/{$admin}/hak", ['hak' => [['jenis_page' => 'IzinPage', 'aksi' => ['VIEW']]], 'alasan' => 'buang halaman sistem'])
            ->assertForbidden()->assertJsonPath('message', fn ($m) => str_contains((string) $m, 'khusus System Admin'));
    }

    /** I2: aksi baru pada halaman SYSTEM_ONLY yang sudah ada tetap diperiksa. */
    public function test_i2_aksi_baru_di_halaman_lama_diperiksa(): void
    {
        $this->essBaru();
        $role = (int) DB::table('N_HRIS_Role_Functional')->insertGetId([
            'Role_Code' => 'UJI_TPR_ESS', 'Role_Name' => 'Uji', 'Flag_Aktif' => 'Y', 'Created_At' => now(),
            'Jenis_Role' => 'AKSES', 'Kode_Tingkat' => 'PENGGUNA', 'Id_Grup_Fitur' => $this->grup('ESS'),
        ], 'Id_Role');
        DB::table('N_HRIS_Role_Hak')->insert(['Id_Role' => $role, 'Jenis_page' => 'IzinPageAdmin', 'Flag_Aktif' => 'Y', 'Created_By' => 'UJI',
            'Id_Aksi' => DB::table('N_HRIS_Klasifikasi_Aksi')->where('Nama_Aksi', 'VIEW')->value('Id_Klasifikasi_Actions')]);
        $s = $this->karyawan();
        $this->pegang($s, 'ESS_SUPER_ADMIN');

        $this->actingAs($s)->putJson("/api/v1/akses-peran/peran/{$role}/hak", ['hak' => [['jenis_page' => 'IzinPageAdmin', 'aksi' => ['VIEW', 'DELETE']]], 'alasan' => 'tambah hapus'])
            ->assertForbidden();
    }

    /** I4: layar lama Role Manager memakai aturan delegasi yang sama (tak bisa mengangkat diri jadi Super Admin). */
    public function test_i4_role_manager_lama_ikut_aturan_delegasi(): void
    {
        // Lapis resource authorize.resource bergantung data; pertahanan controller sendiri yang diuji.
        $this->withoutMiddleware(\App\Http\Middleware\AuthorizeResourceAccess::class);
        $a = $this->karyawan();
        $this->pegang($a, 'ESS_MENU_ADMINISTRATOR');
        $t = $this->karyawan([(int) $a->Id_Users]);
        $peranA = DB::table('N_HRIS_Role_User')->where('UserId_Web', $a->Id_Users)->where('Flag_Aktif', 'Y')->pluck('Id_Role')->all();

        $this->actingAs($a)->postJson("/api/v1/admin/role-manager/user-roles/{$a->Id_Users}", ['roleIds' => [...$peranA, $this->idRole('ESS_SUPER_ADMIN')]])
            ->assertForbidden();
        $this->actingAs($a)->postJson("/api/v1/admin/role-manager/user-roles/{$t->Id_Users}", ['roleIds' => [$this->idRole('ESS_SUPER_ADMIN')]])
            ->assertForbidden();
        $this->assertSame(0, DB::table('N_HRIS_Role_User')->where('Id_Role', $this->idRole('ESS_SUPER_ADMIN'))->whereIn('UserId_Web', [$a->Id_Users, $t->Id_Users])->where('Flag_Aktif', 'Y')->count());
    }

    /** I4: layar lama "permissions" (grant halaman) memakai aturan delegasi yang sama. */
    public function test_i4_grant_lama_ikut_aturan_delegasi(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\AuthorizeResourceAccess::class);
        $a = $this->karyawan();
        $this->pegang($a, 'ESS_MENU_ADMINISTRATOR');

        $this->actingAs($a)->postJson("/api/v1/admin/role-manager/permissions/{$a->Id_Users}", [
            'pages' => ['systemAuthManagerPage'], 'actions' => [], 'featureGroups' => [], 'contentTabs' => [],
        ])->assertForbidden();
        $this->assertSame(0, DB::table('HRIS_Page_Access')->where('UserID_Web', $a->Id_Users)->where('Flag_Aktif', 'Y')->count());
    }

    /** M1: mencentang "angkat" untuk Admin tak menghapus syarat "bisa membuka halaman yang diberikan". */
    public function test_m1_angkat_admin_tak_melonggarkan_syarat_buka(): void
    {
        DB::table('N_HRIS_Tingkat_Peran')->where('Kode_Tingkat', 'ADMIN')->update(['Boleh_Angkat' => 'Y']);
        TingkatPeran::lupakan();
        $a = $this->karyawan();
        $this->pegang($a, 'VISITOR_MENU_ADMINISTRATOR');
        $t = $this->karyawan([(int) $a->Id_Users]);
        $tutup = 'visitorDaftarMitraPage';
        $this->assertFalse(app(KeputusanAkses::class)->boleh($a, $tutup), 'Prasyarat: admin Visitor tak membuka halaman ini.');

        $this->actingAs($a)->postJson('/api/v1/akses-peran/grant', ['id_user' => $t->Id_Users, 'jenis_page' => $tutup, 'alasan' => 'uji m1'])->assertForbidden();
    }

    /** M2: tingkat peran pool approval tak bisa diubah (jenisnya akan hilang). */
    public function test_m2_tingkat_pool_approval_tak_bisa_diubah(): void
    {
        $this->actingAs($this->sa())->putJson('/api/v1/akses-peran/peran/'.$this->idRole('ADMIN_PERMIT').'/tingkat', ['tingkat' => 'ADMIN', 'alasan' => 'coba naikkan'])
            ->assertStatus(422);
        $this->assertSame('POOL_APPROVAL', DB::table('N_HRIS_Role_Functional')->where('Role_Code', 'ADMIN_PERMIT')->value('Jenis_Role'));
    }

    /** M3: kolom tingkat SYSTEM_ADMIN pada peran diabaikan (System Admin hanya bendera identitas). */
    public function test_m3_peran_tak_bisa_bertingkat_system_admin(): void
    {
        $this->assertSame('PENGGUNA', app(TingkatPeran::class)->dariPeran((object) ['Role_Code' => 'X', 'Jenis_Role' => 'AKSES', 'Id_Grup_Fitur' => 1, 'Kode_Tingkat' => 'SYSTEM_ADMIN']));
    }

    /** M7: Super Admin lintas modul lama — perannya diaktifkan pun tak memberi wewenang (tanpa modul). */
    public function test_m7_peran_tanpa_modul_tak_memberi_wewenang(): void
    {
        DB::table('N_HRIS_Role_Functional')->where('Role_Code', 'HCIS_SUPER_ADMIN')->update(['Flag_Aktif' => 'Y']);
        $s = $this->karyawan();
        $this->pegang($s, 'HCIS_SUPER_ADMIN');

        $this->assertSame([], app(KeputusanAkses::class)->modulKelolaAkses($s));
        $this->assertFalse(app(KeputusanAkses::class)->bolehKelolaAkses($s));
    }
}
