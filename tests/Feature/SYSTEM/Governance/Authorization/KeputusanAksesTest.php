<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Http\Middleware\CheckJabatan;
use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** RBAC Fase 1: satu titik keputusan halaman (mode LAMA). */
class KeputusanAksesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $c = new \ReflectionProperty(User::class, 'menuAccessCache');
        $c->setAccessible(true);
        $c->setValue(null, []);
    }

    private function userBiasa(): User
    {
        $id = DB::table('Karyawan as k')->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')
            ->where('k.Aktif', 'Y')
            ->where(fn ($q) => $q->whereNull('u.Is_System_Admin')->orWhere('u.Is_System_Admin', '<>', 'Y'))
            ->whereNotExists(fn ($q) => $q->from('N_HRIS_Role_User as ru')->whereColumn('ru.UserId_Web', 'u.Id_Users'))
            ->orderBy('u.Id_Users')->value('u.Id_Users');

        return User::where('Id_Users', $id)->firstOrFail();
    }

    private function lewatGerbang(User $u, string ...$halaman): bool
    {
        Auth::setUser($u);
        $req = Request::create('/x');
        $req->setUserResolver(fn () => $u);
        try {
            (new CheckJabatan)->handle($req, fn () => response('ok'), ...$halaman);

            return true;
        } catch (HttpException $e) {
            return false;
        }
    }

    public function test_sama_dengan_memiliki_jabatan_untuk_user_biasa(): void
    {
        $u = $this->userBiasa();
        $k = app(KeputusanAkses::class);

        foreach (DB::table('N_HRIS_Menus')->pluck('Jenis_page') as $p) {
            $this->assertSame($u->memilikiJabatan($p), $k->boleh($u, $p), "Beda untuk {$p}.");
        }
    }

    public function test_system_admin_boleh_semua_halaman(): void
    {
        $u = $this->userBiasa();
        DB::table('KPI_Users')->where('Id_Users', $u->Id_Users)->update(['Is_System_Admin' => 'Y']);
        $u = User::where('Id_Users', $u->Id_Users)->firstOrFail();

        $this->assertTrue(app(KeputusanAkses::class)->boleh($u, 'systemAuthManagerPage'));
        $this->assertTrue($this->lewatGerbang($u, 'kpiEnforcementMonitoring'));
    }

    public function test_gerbang_menolak_user_tanpa_akses(): void
    {
        $this->assertFalse($this->lewatGerbang($this->userBiasa(), 'systemAuthManagerPage'));
    }

    public function test_gerbang_lolos_bila_salah_satu_alternatif_boleh(): void
    {
        $u = $this->userBiasa();
        DB::table('HRIS_Page_Access')->insert([
            'Kode_Perusahaan' => '001', 'Jenis_page' => 'IzinPageAdmin', 'UserID_Web' => $u->Id_Users,
            'ID_Divisi' => 0, 'ID_Level' => 0, 'Flag_Aktif' => 'Y',
        ]);

        $this->assertTrue($this->lewatGerbang($u, 'systemAuthManagerPage', ' IzinPageAdmin'));
    }

    public function test_telusuri_menyebut_jalurnya(): void
    {
        $u = $this->userBiasa();
        DB::table('HRIS_Page_Access')->insert([
            'Kode_Perusahaan' => '001', 'Jenis_page' => 'IzinPageAdmin', 'UserID_Web' => $u->Id_Users,
            'ID_Divisi' => 0, 'ID_Level' => 0, 'Flag_Aktif' => 'Y',
        ]);

        $t = app(KeputusanAkses::class)->telusuri($u, 'IzinPageAdmin');

        $this->assertTrue($t['boleh']);
        $this->assertContains('GRANT_LANGSUNG', $t['jalur']);
        $this->assertSame(['boleh' => false, 'mode' => 'LAMA', 'jalur' => []], app(KeputusanAkses::class)->telusuri($u, 'systemAuthManagerPage'));
    }

    public function test_gate_di_dalam_halaman_ikut_system_admin(): void
    {
        $u = $this->userBiasa();
        DB::table('HRIS_Page_Access')->where('UserID_Web', $u->Id_Users)->where('Jenis_page', 'adminHCKuotaManagementPage')->delete();
        DB::table('KPI_Users')->where('Id_Users', $u->Id_Users)->update(['Is_System_Admin' => 'Y']);
        $u = User::where('Id_Users', $u->Id_Users)->firstOrFail();
        $this->assertFalse($u->memilikiJabatan('adminHCKuotaManagementPage'), 'Prasyarat: tanpa grant halaman kuota.');

        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($u)->allows('manageKuotaRecovery'));
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($u)->allows('akses-spesial', 'adminHCKuotaManagementPage'));
    }
}
