<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Shared\HibahTataKelola;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RBAC Fase 0: halaman yang memberi/mengatur akses tidak boleh didapat lewat
 * hibah role operasional. Temuan 9 Okt 2026: HCIS_LEADER, GA_PHL_ADMIN,
 * ADMIN_HC_CUTI_VERIFIKATOR membuka Page Access Setup lalu bisa memberi diri
 * sendiri halaman apa pun.
 */
class HibahTataKelolaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lupakanCacheAkses();
    }

    private function lupakanCacheAkses(): void
    {
        $cache = new \ReflectionProperty(User::class, 'menuAccessCache');
        $cache->setAccessible(true);
        $cache->setValue(null, []);
    }

    /** User aktif tanpa role apa pun dan tanpa grant ke halaman tata kelola. */
    private function userBersih(): User
    {
        $id = DB::table('Karyawan as k')
            ->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')
            ->where('k.Aktif', 'Y')
            ->where(fn ($q) => $q->whereNull('u.Is_System_Admin')->orWhere('u.Is_System_Admin', '<>', 'Y'))
            ->whereNotExists(fn ($q) => $q->from('N_HRIS_Role_User as ru')->whereColumn('ru.UserId_Web', 'u.Id_Users'))
            ->whereNotExists(fn ($q) => $q->from('HRIS_Page_Access as pa')->whereColumn('pa.UserID_Web', 'u.Id_Users')
                ->whereIn('pa.Jenis_page', ['adminAllPagesAccess', 'systemAuthManagerPage', 'superAdminManajemenPeranPage']))
            ->orderBy('u.Id_Users')
            ->value('u.Id_Users');
        $this->assertNotNull($id, 'Tak ada user bersih untuk uji.');

        return User::where('Id_Users', $id)->firstOrFail();
    }

    private function beriRole(User $u, string $kode): void
    {
        $idRole = DB::table('N_HRIS_Role_Functional')->where('Role_Code', $kode)->where('Flag_Aktif', 'Y')->value('Id_Role');
        $this->assertNotNull($idRole, "Role {$kode} tidak ada/aktif di dev.");
        DB::table('N_HRIS_Role_User')->insert([
            'UserId_Web' => $u->Id_Users, 'Id_Role' => $idRole, 'Assigned_At' => now(),
            'Assigned_By' => 'UJI', 'Flag_Aktif' => 'Y',
        ]);
        $this->lupakanCacheAkses();
    }

    public function test_role_operasional_tidak_membuka_halaman_tata_kelola(): void
    {
        $u = $this->userBersih();
        $this->beriRole($u, 'HCIS_LEADER');

        $this->assertFalse($u->memilikiJabatan('adminAllPagesAccess'));
        $this->assertFalse($u->memilikiJabatan('systemAuthManagerPage'));
        $this->assertFalse($u->memilikiJabatan('superAdminManajemenPeranPage'));
    }

    public function test_role_operasional_tetap_membuka_halaman_non_tata_kelola(): void
    {
        $u = $this->userBersih();
        $this->beriRole($u, 'HCIS_LEADER');

        // Halaman admin ESS biasa — bukan tata kelola — tidak boleh ikut hilang.
        $this->assertTrue($u->memilikiJabatan('IzinPageAdmin'));
    }

    public function test_role_admin_sah_membuka_halaman_tata_kelola(): void
    {
        $u = $this->userBersih();
        $this->beriRole($u, 'ESS_ADMIN');

        $this->assertTrue($u->memilikiJabatan('adminAllPagesAccess'));
    }

    public function test_role_admin_tetap_menang_walau_ada_role_operasional(): void
    {
        $u = $this->userBersih();
        $this->beriRole($u, 'HCIS_LEADER');
        $this->beriRole($u, 'ESS_ADMIN');

        $this->assertTrue($u->memilikiJabatan('adminAllPagesAccess'));
    }

    public function test_grant_langsung_tetap_berlaku(): void
    {
        $u = $this->userBersih();
        DB::table('HRIS_Page_Access')->insert([
            'Kode_Perusahaan' => '001', 'Jenis_page' => 'adminAllPagesAccess', 'UserID_Web' => $u->Id_Users,
            'ID_Divisi' => 0, 'ID_Level' => 0, 'Flag_Aktif' => 'Y',
        ]);
        $this->lupakanCacheAkses();

        $this->assertTrue($u->memilikiJabatan('adminAllPagesAccess'));
    }

    public function test_normalisasi_kunci_halaman(): void
    {
        $this->assertTrue(HibahTataKelola::apakahHalamanTataKelola('superadminmanajemenperanpage'));
        $this->assertTrue(HibahTataKelola::apakahHalamanTataKelola(' Super AdminManajemenPeranPage '));
        $this->assertFalse(HibahTataKelola::apakahHalamanTataKelola('IzinPageAdmin'));
    }

    public function test_halaman_lewat_peran_admin(): void
    {
        $u = $this->userBersih();
        $this->beriRole($u, 'HCIS_LEADER');
        $this->assertSame([], HibahTataKelola::halamanLewatPeranAdmin((int) $u->Id_Users));

        $this->beriRole($u, 'ESS_ADMIN');
        $this->assertContains('adminallpagesaccess', HibahTataKelola::halamanLewatPeranAdmin((int) $u->Id_Users));
    }

    private function sidebarBoleh(User $u, string $page): bool
    {
        \App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationScopeResolver::clearCache($u);

        return \App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationScopeResolver::canAccessPage($u, $page);
    }

    public function test_sidebar_sejalan_dengan_gerbang_untuk_role_operasional(): void
    {
        $u = $this->userBersih();
        $this->beriRole($u, 'HCIS_LEADER');

        foreach (['adminAllPagesAccess', 'systemAuthManagerPage', 'superAdminManajemenPeranPage'] as $p) {
            $this->assertSame($u->memilikiJabatan($p), $this->sidebarBoleh($u, $p), "Sidebar & gerbang berselisih untuk {$p}.");
            $this->assertFalse($this->sidebarBoleh($u, $p));
        }
    }

    public function test_sidebar_role_admin_sah_tetap_melihat(): void
    {
        $u = $this->userBersih();
        $this->beriRole($u, 'ESS_ADMIN');

        $this->assertTrue($this->sidebarBoleh($u, 'adminAllPagesAccess'));
    }

    public function test_gerbang_induk_menu_approval_matrix_ikut_tata_kelola(): void
    {
        // Gerbang rute Menu & Matrix diawali menuApprovalMatrixPage.
        $u = $this->userBersih();
        $this->beriRole($u, 'HCIS_LEADER');

        $this->assertFalse($u->memilikiJabatan('menuApprovalMatrixPage'));
    }

    public function test_jalur_delegasi_menu_mengunci_halaman_tata_kelola_untuk_non_system_admin(): void
    {
        $u = $this->userBersih();
        $idKpi = (int) DB::table('N_HRIS_Master_Grup_Fitur')->where('Kode_Grup_Fitur', 'KPI')->value('Id_Grup_Fitur');
        $svc = app(\App\Services\SYSTEM\Governance\Authorization\Menu\MenuDelegationPolicyService::class);

        $kebijakan = $svc->policiesForGroup($u, $idKpi)->keyBy('Jenis_page');
        $this->assertFalse((bool) $kebijakan['ManajemenAksesDivisi']->can_delegate, 'Admin modul bisa mendelegasikan halaman pengatur akses.');
        $biasa = $kebijakan->first(fn ($m) => ! HibahTataKelola::apakahHalamanTataKelola($m->Jenis_page) && $m->policy_mode === 'OPEN');
        $this->assertNotNull($biasa);
        $this->assertTrue((bool) $biasa->can_delegate, 'Halaman biasa ikut terkunci.');

        DB::table('KPI_Users')->where('Id_Users', $u->Id_Users)->update(['Is_System_Admin' => 'Y']);
        $sa = User::where('Id_Users', $u->Id_Users)->firstOrFail();
        \App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationScopeResolver::clearCache($sa);
        $this->assertTrue((bool) $svc->policiesForGroup($sa, $idKpi)->keyBy('Jenis_page')['ManajemenAksesDivisi']->can_delegate);
    }
}
