<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Application\Shell\InertiaShellService;
use App\Services\SYSTEM\Governance\Authorization\Akses\AuditAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * RBAC Fase 2b: layar "Akses & Peran" (baca-saja). Terbuka untuk System Admin
 * dan pemegang role ADMIN; admin modul hanya melihat modulnya sendiri.
 */
class AksesPeranTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');
        $this->lupakan();
    }

    private function lupakan(): void
    {
        $c = new \ReflectionProperty(User::class, 'menuAccessCache');
        $c->setAccessible(true);
        $c->setValue(null, []);
        KeputusanAkses::lupakanCache();
    }

    private function userBersih(int $lewati = 0): User
    {
        $id = DB::table('Karyawan as k')->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')
            ->where('k.Aktif', 'Y')->where('u.Id_Users', '<>', $lewati)
            ->where(fn ($q) => $q->whereNull('u.Is_System_Admin')->orWhere('u.Is_System_Admin', '<>', 'Y'))
            ->whereNotExists(fn ($q) => $q->from('N_HRIS_Role_User as ru')->whereColumn('ru.UserId_Web', 'u.Id_Users'))
            ->orderBy('u.Id_Users')->value('u.Id_Users');

        return User::where('Id_Users', $id)->firstOrFail();
    }

    private function beriRole(User $u, string $kode): void
    {
        DB::table('N_HRIS_Role_User')->insert([
            'UserId_Web' => $u->Id_Users, 'Id_Role' => DB::table('N_HRIS_Role_Functional')->where('Role_Code', $kode)->value('Id_Role'),
            'Assigned_At' => now(), 'Assigned_By' => 'UJI', 'Flag_Aktif' => 'Y',
        ]);
        $this->lupakan();
    }

    private function sa(): User
    {
        return User::where('Id_Users', 4)->firstOrFail();
    }

    public function test_role_operasional_ditolak(): void
    {
        $u = $this->userBersih();
        $this->beriRole($u, 'HCIS_LEADER');

        $this->actingAs($u)->get('/system/akses-peran')->assertForbidden();
        $this->actingAs($u)->getJson('/api/v1/akses-peran/ringkasan')->assertForbidden();
    }

    public function test_system_admin_melihat_semua_modul(): void
    {
        $this->actingAs($this->sa())->get('/system/akses-peran')->assertOk();
        $peran = $this->actingAs($this->sa())->getJson('/api/v1/akses-peran/peran')->assertOk()->json('result');

        $this->assertGreaterThan(1, count(array_unique(array_column($peran, 'modul'))));
    }

    public function test_admin_modul_hanya_melihat_modulnya(): void
    {
        $u = $this->userBersih();
        $this->beriRole($u, 'KPI_ADMIN');

        // Admin modul masuk lewat menu modulnya; SYSTEM khusus System Admin.
        $this->actingAs($u)->get('/system/akses-peran')->assertForbidden();
        $this->actingAs($u)->get('/admin/kpi/akses-peran')->assertOk();
        $modul = array_unique(array_column($this->actingAs($u)->getJson('/api/v1/akses-peran/peran?konteks=kpi')->assertOk()->json('result'), 'modul'));

        $this->assertSame(['KPI'], array_values($modul));
    }

    public function test_admin_modul_tidak_bisa_membuka_peran_modul_lain(): void
    {
        $u = $this->userBersih();
        $this->beriRole($u, 'KPI_ADMIN');
        $leader = DB::table('N_HRIS_Role_Functional')->where('Role_Code', 'HCIS_LEADER')->value('Id_Role');

        $this->actingAs($u)->getJson("/api/v1/akses-peran/peran/{$leader}")->assertNotFound();
        // Kontrol positif: peran KPI miliknya sendiri terbuka (404 di atas bukan karena rute hilang).
        $kpi = DB::table('N_HRIS_Role_Functional')->where('Role_Code', 'KPI_ADMIN')->value('Id_Role');
        $this->actingAs($u)->getJson("/api/v1/akses-peran/peran/{$kpi}")->assertOk();
    }

    public function test_admin_modul_tidak_bisa_menelusuri_halaman_modul_lain(): void
    {
        $admin = $this->userBersih();
        $this->beriRole($admin, 'KPI_ADMIN');
        $sasaran = $this->userBersih((int) $admin->Id_Users);
        $this->beriRole($sasaran, 'ESS_ADMIN');
        $this->beriRole($sasaran, 'KPI_SUPER_ADMIN');

        $this->actingAs($admin)->getJson("/api/v1/akses-peran/pengguna/{$sasaran->Id_Users}/telusuri?halaman=adminAllPagesAccess")
            ->assertNotFound();

        $jalur = $this->actingAs($admin)->getJson("/api/v1/akses-peran/pengguna/{$sasaran->Id_Users}/telusuri?halaman=MasterFormula")
            ->assertOk()->json('result.jalur');
        $this->assertContains('HIBAH_ROLE:KPI_SUPER_ADMIN', $jalur);
        $this->assertSame([], array_values(array_filter($jalur, fn ($j) => str_contains($j, 'ESS_'))), 'Peran modul lain ikut terbuka.');
    }

    public function test_admin_modul_tidak_melihat_status_system_admin(): void
    {
        $admin = $this->userBersih();
        $this->beriRole($admin, 'KPI_ADMIN');

        $this->actingAs($admin)->getJson('/api/v1/akses-peran/pengguna/4')->assertOk()->assertJsonPath('result.system_admin', null);
        $this->actingAs($this->sa())->getJson('/api/v1/akses-peran/pengguna/4')->assertOk()->assertJsonPath('result.system_admin', true);
    }

    public function test_audit_admin_modul_tidak_memuat_modul_lain(): void
    {
        $admin = $this->userBersih();
        $this->beriRole($admin, 'KPI_ADMIN');
        $x = $this->userBersih((int) $admin->Id_Users);
        // X pernah memegang peran KPI (kini nonaktif), lalu grant ESS-nya diubah orang lain.
        DB::table('N_HRIS_Role_User')->insert([
            'UserId_Web' => $x->Id_Users, 'Id_Role' => DB::table('N_HRIS_Role_Functional')->where('Role_Code', 'KPI_ADMIN')->value('Id_Role'),
            'Assigned_At' => now(), 'Assigned_By' => 'UJI', 'Flag_Aktif' => 'T',
        ]);
        $audit = app(AuditAkses::class);
        $audit->catat('GRANT', 'adminAllPagesAccess', 'UJI_ESS', ['T'], ['Y'], (int) $x->Id_Users, null, 'UI', 4);
        $audit->catat('GRANT', 'MasterFormula', 'UJI_KPI', ['T'], ['Y'], (int) $x->Id_Users, null, 'UI', 4);

        $aksi = array_column($this->actingAs($admin)->getJson('/api/v1/akses-peran/audit')->assertOk()->json('result.data'), 'aksi');

        $this->assertContains('UJI_KPI', $aksi);
        $this->assertNotContains('UJI_ESS', $aksi, 'Jejak modul ESS terbaca admin KPI.');
    }

    public function test_daftar_admin_config_sama_dengan_jenis_role_admin(): void
    {
        if (! \App\Services\SYSTEM\Application\Database\SkemaTersedia::kolom('N_HRIS_Role_Functional', 'Jenis_Role')) {
            $this->markTestSkipped('DDL-2026-165 belum dijalankan.');
        }
        $db = DB::table('N_HRIS_Role_Functional')->where('Jenis_Role', 'ADMIN')->where('Flag_Aktif', 'Y')
            ->pluck('Role_Code')->map(fn ($k) => strtoupper($k))->sort()->values()->all();
        $config = collect(config('authorization.tata_kelola.peran_admin'))->map(fn ($k) => strtoupper($k))
            ->filter(fn ($k) => DB::table('N_HRIS_Role_Functional')->whereRaw('UPPER(Role_Code) = ?', [$k])->where('Flag_Aktif', 'Y')->exists())
            ->sort()->values()->all();

        $this->assertSame($config, $db, 'Role admin di config (Fase 0) dan Jenis_Role=ADMIN (Fase 2) berselisih — perbarui salah satunya.');
    }

    public function test_detail_peran_berisi_matriks_dan_pemegang(): void
    {
        $leader = DB::table('N_HRIS_Role_Functional')->where('Role_Code', 'HCIS_LEADER')->value('Id_Role');

        $r = $this->actingAs($this->sa())->getJson("/api/v1/akses-peran/peran/{$leader}")->assertOk()->json('result');

        $baris = collect($r['matriks'])->firstWhere('jenis_page', 'IzinPageAdmin');
        $this->assertNotNull($baris, 'Matriks HCIS_LEADER tidak memuat IzinPageAdmin.');
        $this->assertContains('VIEW', $baris['aksi']);
        $this->assertContains('VIEW', $r['kolom_aksi']);
        $this->assertIsArray($r['pemegang'], 'pemegang harus daftar penugasan, bukan angka.');
        $this->assertNotEmpty($r['pemegang']);
        $this->assertArrayHasKey('nama', $r['pemegang'][0]);
    }

    public function test_detail_pengguna_dan_telusuri(): void
    {
        $u = $this->userBersih();
        $this->beriRole($u, 'HCIS_LEADER');

        $r = $this->actingAs($this->sa())->getJson("/api/v1/akses-peran/pengguna/{$u->Id_Users}")->assertOk()->json('result');
        $this->assertContains('HCIS_LEADER', array_column($r['peran'], 'kode'));
        $this->assertContains('IzinPageAdmin', array_column($r['halaman_efektif'], 'jenis_page'));

        $t = $this->actingAs($this->sa())->getJson("/api/v1/akses-peran/pengguna/{$u->Id_Users}/telusuri?halaman=IzinPageAdmin")
            ->assertOk()->json('result');
        $this->assertTrue($t['boleh']);
        $this->assertContains('HIBAH_ROLE:HCIS_LEADER', $t['jalur']);
    }

    public function test_cari_pengguna(): void
    {
        $kode = DB::table('Karyawan')->where('UserID_Web', 4)->value('Kode_Karyawan');

        $hasil = $this->actingAs($this->sa())->getJson('/api/v1/akses-peran/pengguna?cari='.$kode)->assertOk()->json('result');

        $this->assertContains(4, array_map('intval', array_column($hasil, 'id_user')));
    }

    public function test_ringkasan_admin_dan_audit(): void
    {
        app(AuditAkses::class)->catat('PENUGASAN', '1', 'UJI_RINGKASAN', [], ['X'], 4);

        $ring = $this->actingAs($this->sa())->getJson('/api/v1/akses-peran/ringkasan')->assertOk()->json('result');
        $this->assertNotEmpty($ring['modul']);
        $this->assertArrayHasKey('jenis_role', $ring);

        $admin = $this->actingAs($this->sa())->getJson('/api/v1/akses-peran/admin')->assertOk()->json('result');
        $this->assertNotEmpty($admin);
        $this->assertArrayHasKey('tanggung_jawab', $admin[0]);

        $audit = $this->actingAs($this->sa())->getJson('/api/v1/akses-peran/audit')->assertOk()->json('result');
        $this->assertSame('UJI_RINGKASAN', $audit['data'][0]['aksi']);
    }

    public function test_menu_tampil_untuk_admin_modul_dan_tidak_untuk_leader(): void
    {
        $shell = app(InertiaShellService::class);
        $tampil = new \ReflectionMethod($shell, 'menuIsVisible');
        $tampil->setAccessible(true);
        $baris = DB::table('N_HRIS_Menus')->where('Jenis_page', 'systemAksesPeranPage')->first();
        $this->assertNotNull($baris, 'Menu systemAksesPeranPage belum terdaftar (DML-2026-167).');

        $admin = $this->userBersih();
        $this->beriRole($admin, 'KPI_ADMIN');
        $leader = $this->userBersih((int) $admin->Id_Users);
        $this->beriRole($leader, 'HCIS_LEADER');

        // Menu SYSTEM khusus System Admin; admin modul memakai menu modulnya.
        $this->assertFalse($tampil->invoke($shell, $baris, $admin));
        $this->assertFalse($tampil->invoke($shell, $baris, $leader));
        $this->assertTrue($tampil->invoke($shell, $baris, $this->sa()));
        $kpi = DB::table('N_HRIS_Menus')->where('Jenis_page', 'kpiAksesPeranPage')->first();
        $this->assertNotNull($kpi, 'Menu kpiAksesPeranPage belum terdaftar (DML-2026-183).');
        $this->assertTrue($tampil->invoke($shell, $kpi, $admin));
        $this->assertFalse($tampil->invoke($shell, $kpi, $leader));
    }
}
