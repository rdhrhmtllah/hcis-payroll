<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Akses & Peran per modul (DML-2026-183): SYSTEM khusus System Admin; Super
 * Admin & Admin modul masuk lewat menu modulnya (/admin/{modul}/akses-peran),
 * dan semua data/perubahan di halaman itu terkunci ke modul tersebut.
 */
class AksesPeranModulTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');
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
            ->whereNotIn('u.Id_Users', $kecuali ?: [0])->orderBy('u.Id_Users')->value('u.Id_Users');

        return User::where('Id_Users', $id)->firstOrFail();
    }

    private function idRole(string $kode): int
    {
        return (int) DB::table('N_HRIS_Role_Functional')->where('Role_Code', $kode)->value('Id_Role');
    }

    private function pegang(User $u, string $kode): void
    {
        $id = DB::table('N_HRIS_Role_User')->insertGetId([
            'UserId_Web' => $u->Id_Users, 'Id_Role' => $this->idRole($kode), 'Assigned_At' => now(), 'Assigned_By' => 'UJI', 'Flag_Aktif' => 'Y',
        ], 'Id');
        DB::table('N_HRIS_Role_User_Tanggung_Jawab')->insert(['Id_Role_User' => $id, 'Kode' => 'AKSES']);
        KeputusanAkses::lupakanCache();
    }

    public function test_halaman_system_khusus_system_admin(): void
    {
        $s = $this->karyawan();
        $this->pegang($s, 'DMS_SUPER_ADMIN');

        $this->actingAs($s)->get('/system/akses-peran')->assertForbidden();
        $this->actingAs($this->sa())->get('/system/akses-peran')->assertOk();
    }

    public function test_halaman_modul_hanya_untuk_admin_modul_itu(): void
    {
        $s = $this->karyawan();
        $this->pegang($s, 'DMS_SUPER_ADMIN');

        $props = $this->actingAs($s)->get('/admin/dms/akses-peran')->assertOk()->viewData('page')['props'];
        $this->assertSame('dms', $props['lingkup']['modul']['kode'] ?? null);
        $this->assertFalse($props['lingkup']['system_admin'], 'Katalog/tingkat tak tampil di halaman modul.');
        $this->actingAs($s)->get('/admin/kpi/akses-peran')->assertForbidden();
        $this->actingAs($this->sa())->get('/admin/kpi/akses-peran')->assertOk();
    }

    public function test_api_dengan_konteks_modul_terkunci_ke_modul_itu(): void
    {
        $s = $this->karyawan();
        $this->pegang($s, 'DMS_SUPER_ADMIN');
        $this->pegang($s, 'KPI_ADMIN');

        $modul = array_values(array_unique(array_column($this->actingAs($s)->getJson('/api/v1/akses-peran/peran?konteks=dms')->assertOk()->json('result'), 'modul')));
        $this->assertSame(['DMS'], $modul);
        // System Admin di halaman modul juga hanya melihat modul itu.
        $modulSa = array_values(array_unique(array_column($this->actingAs($this->sa())->getJson('/api/v1/akses-peran/peran?konteks=dms')->assertOk()->json('result'), 'modul')));
        $this->assertSame(['DMS'], $modulSa);
        $this->actingAs($s)->getJson('/api/v1/akses-peran/peran?konteks=lms')->assertForbidden();
    }

    public function test_perubahan_di_halaman_modul_tak_bisa_ke_modul_lain(): void
    {
        $t = $this->karyawan();

        $this->actingAs($this->sa())->postJson('/api/v1/akses-peran/penugasan?konteks=dms', [
            'id_user' => $t->Id_Users, 'id_role' => $this->idRole('KPI_ADMIN'), 'cakupan' => [], 'alasan' => 'salah halaman',
        ])->assertForbidden()->assertJsonPath('message', 'Peran ini bukan milik modul halaman ini.');

        $pilihan = collect($this->actingAs($this->sa())->getJson("/api/v1/akses-peran/pengguna/{$t->Id_Users}?konteks=dms")->assertOk()
            ->json('result.peran_tersedia'))->pluck('modul')->unique()->values()->all();
        $this->assertSame(['DMS'], $pilihan);
    }
}
