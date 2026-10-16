<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\ModeAkses;
use App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationScopeResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Minor tertunda dari review RBAC Fase 4 & 5.
 * Skenario dasar: pemegang HCIS_LEADER (modul ESS); seluruh hak IzinPageAdmin
 * Leader dinonaktifkan di model baru (lama = boleh, baru = tidak).
 */
class PerbaikanMinorFase45Test extends TestCase
{
    use DatabaseTransactions;

    private User $u;

    private int $ess;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');
        $this->ess = (int) DB::table('N_HRIS_Master_Grup_Fitur')->where('Kode_Grup_Fitur', 'ESS')->value('Id_Grup_Fitur');
        $this->u = $this->karyawan();
        $leader = (int) DB::table('N_HRIS_Role_Functional')->where('Role_Code', 'HCIS_LEADER')->value('Id_Role');
        DB::table('N_HRIS_Role_User')->insert([
            'UserId_Web' => $this->u->Id_Users, 'Id_Role' => $leader, 'Assigned_At' => now(), 'Assigned_By' => 'UJI', 'Flag_Aktif' => 'Y',
        ]);
        DB::table('N_HRIS_Role_Hak')->where('Id_Role', $leader)->where('Jenis_page', 'IzinPageAdmin')->update(['Flag_Aktif' => 'T']);
        $this->lupakan();
    }

    private function karyawan(array $kecuali = []): User
    {
        $id = DB::table('Karyawan as k')->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')->where('k.Aktif', 'Y')
            ->where(fn ($w) => $w->whereNull('u.Is_System_Admin')->orWhere('u.Is_System_Admin', '<>', 'Y'))
            ->whereNotExists(fn ($w) => $w->from('N_HRIS_Role_User as ru')->whereColumn('ru.UserId_Web', 'u.Id_Users'))
            ->whereNotExists(fn ($q) => $q->from('HRIS_Page_Access as pa')->whereColumn('pa.UserID_Web', 'u.Id_Users')->where('pa.Flag_Aktif', 'Y'))
            ->whereNotIn('u.Id_Users', $kecuali ?: [0])->orderBy('u.Id_Users')->value('u.Id_Users');

        return User::where('Id_Users', $id)->firstOrFail();
    }

    private function lupakan(): void
    {
        $c = new \ReflectionProperty(User::class, 'menuAccessCache');
        $c->setAccessible(true);
        $c->setValue(null, []);
        KeputusanAkses::lupakanCache();
        ModeAkses::lupakan();
        AuthorizationScopeResolver::clearAllCache();
    }

    private function setMode(string $mode): void
    {
        DB::table('N_HRIS_Akses_Mode_Modul')->updateOrInsert(['Id_Grup_Fitur' => $this->ess], ['Mode' => $mode]);
        $this->lupakan();
    }

    /* ── Fase 4 ── */

    /** Rem darurat sungguhan: RBAC_MODE_BARU_DIIZINKAN=false membuat modul BARU kembali diputuskan aturan lama. */
    public function test_rem_darurat_mengembalikan_aturan_lama(): void
    {
        $this->setMode('BARU');
        $this->assertFalse(app(KeputusanAkses::class)->boleh($this->u, 'IzinPageAdmin'), 'Prasyarat: BARU menolak.');

        config(['authorization.mode_baru_diizinkan' => false]);
        $this->lupakan();

        $this->assertTrue(app(KeputusanAkses::class)->boleh($this->u, 'IzinPageAdmin'), 'Rem darurat tak menghentikan keputusan BARU.');
    }

    /** Cache keputusan authorize.resource ikut berganti saat mode modul berubah. */
    public function test_cache_authorize_resource_ikut_mode(): void
    {
        config(['authorization.cache.cross_request_enabled' => true, 'authorization.cache.ttl_seconds' => 60]);
        $masuk = ['pageType' => 'IzinPageAdmin'];
        $this->assertNotSame('page-not-accessible', AuthorizationScopeResolver::evaluateAccess($this->u, $masuk)['reason'], 'Prasyarat: LAMA membuka halaman.');

        $this->setMode('BARU');

        $this->assertSame('page-not-accessible', AuthorizationScopeResolver::evaluateAccess($this->u, $masuk)['reason'], 'Keputusan LAMA basi dari cache.');
    }

    /** Uji positif BARU (review Fase 4): hak peran tanpa hibah membuka Panduan & lapis halaman authorize.resource. */
    public function test_baru_hak_peran_membuka_panduan_dan_authorize_resource(): void
    {
        $leader = (int) DB::table('N_HRIS_Role_Functional')->where('Role_Code', 'HCIS_LEADER')->value('Id_Role');
        $view = DB::table('N_HRIS_Klasifikasi_Aksi')->where('Nama_Aksi', 'VIEW')->value('Id_Klasifikasi_Actions');
        DB::table('N_HRIS_Role_Hak')->where('Id_Role', $leader)->where('Jenis_page', 'IzinPageAdmin')->where('Id_Aksi', $view)->update(['Flag_Aktif' => 'Y']);
        $this->setMode('BARU');

        $this->assertTrue(AuthorizationScopeResolver::canAccessPage($this->u, 'IzinPageAdmin'));
        $this->assertNotSame('page-not-accessible', AuthorizationScopeResolver::evaluateAccess($this->u, ['pageType' => 'IzinPageAdmin'])['reason']);
        $panduan = new \ReflectionMethod(\App\Services\SYSTEM\Application\Panduan\PanduanService::class, 'bolehBukaHalaman');
        $panduan->setAccessible(true);
        $this->assertTrue($panduan->invoke(app(\App\Services\SYSTEM\Application\Panduan\PanduanService::class), $this->u, 'IzinPageAdmin'));
    }

    /* ── Fase 5 ── */

    /** BAYANGAN: request tulis juga mencatat selisih VIEW (halaman yang hanya dipakai lewat POST). */
    public function test_bayangan_post_mencatat_selisih_view(): void
    {
        $this->setMode('BAYANGAN');
        Cache::forget('rbac:selisih:'.$this->u->Id_Users.'|izinpageadmin|VIEW');
        Cache::forget('rbac:selisih:'.$this->u->Id_Users.'|izinpageadmin|TULIS');

        $this->assertTrue(app(KeputusanAkses::class)->bolehTulis($this->u, 'IzinPageAdmin'));
        KeputusanAkses::kirimSelisih(DB::transactionLevel());

        $this->assertSame(1, DB::table('N_HRIS_Akses_Selisih')->where('Id_User', $this->u->Id_Users)
            ->where('Jenis_page', 'IzinPageAdmin')->where('Aksi', 'VIEW')->count(), 'Selisih VIEW tak tercatat dari request tulis.');
    }

    public function test_lama_post_tanpa_akses_tetap_403_dan_akses_middleware_lama(): void
    {
        Route::middleware(['web', 'auth', 'check.jabatan:systemAuthManagerPage'])->post('/_uji/minor/tolak', fn () => response('ok'));
        Route::middleware(['web', 'auth', 'akses:IzinPageAdmin,DELETE'])->post('/_uji/minor/akses', fn () => response('ok'));

        $this->actingAs($this->u)->post('/_uji/minor/tolak')->assertForbidden();
        // LAMA: aksi tak dibedakan — pemegang halaman lolos akses:...,DELETE.
        $this->actingAs($this->u)->post('/_uji/minor/akses')->assertOk();
    }

    public function test_baru_menu_global_bisa_post(): void
    {
        $global = (string) DB::table('N_HRIS_Menus')->where('Is_Global_Menu', 'Y')->pluck('Jenis_page')
            ->first(fn ($p) => app(ModeAkses::class)->modulHalaman((string) $p) === $this->ess);
        $this->assertNotSame('', $global, 'Tak ada menu global milik ESS.');
        Route::middleware(['web', 'auth', "check.jabatan:{$global}"])->post('/_uji/minor/global', fn () => response('ok'));
        $this->setMode('BARU');

        $this->actingAs($this->u)->post('/_uji/minor/global')->assertOk();
    }

    /** Layar lama: tombol ke Akses & Peran hanya bagi yang bisa membukanya. */
    public function test_banner_tahu_siapa_bisa_membuka_akses_peran(): void
    {
        $g = $this->karyawan([(int) $this->u->Id_Users]);
        DB::table('HRIS_Page_Access')->insert([
            'Kode_Perusahaan' => '001', 'Jenis_page' => 'adminAllPagesAccess', 'UserID_Web' => $g->Id_Users, 'ID_Divisi' => 0, 'ID_Level' => 0, 'Flag_Aktif' => 'Y',
        ]);
        $this->lupakan();

        $props = $this->actingAs($g)->get('/page-access')->assertOk()->viewData('page')['props'];
        $this->assertArrayHasKey('tautanAksesPeran', $props);
        $this->assertNull($props['tautanAksesPeran']);
        $sa = $this->actingAs(User::where('Id_Users', 4)->firstOrFail())->get('/admin/ess/menu-approval-matrix')->assertOk()->viewData('page')['props'];
        $this->assertSame('/admin/ess/akses-peran', $sa['tautanAksesPeran']);
        $this->assertSame('LAMA', $sa['modeGrup']);
    }

    /** Binding modul: baris nonaktif tak tampil terikat, dan memilihnya lagi mengaktifkannya. */
    public function test_binding_baris_nonaktif_bisa_diaktifkan_lagi(): void
    {
        $sa = User::where('Id_Users', 4)->firstOrFail();
        $baris = DB::table('N_HRIS_Grup_Fitur_Map')->where('Id_Grup_Fitur', 7)->where('Flag_Aktif', 'Y')->first(['Id_Grup_Map', 'Jenis_page']);
        DB::table('N_HRIS_Grup_Fitur_Map')->where('Id_Grup_Map', $baris->Id_Grup_Map)->update(['Flag_Aktif' => 'T']);

        $terikat = $this->actingAs($sa)->getJson('/api/v1/system-auth/menu-binding/7')->assertOk()->json('boundPages');
        $this->assertNotContains($baris->Jenis_page, $terikat, 'Baris nonaktif tampil sebagai terikat.');

        $halaman = DB::table('N_HRIS_Grup_Fitur_Map')->where('Id_Grup_Fitur', 7)->pluck('Jenis_page')->all();
        $this->actingAs($sa)->postJson('/api/v1/system-auth/menu-binding/7', ['pages' => $halaman])->assertOk();

        $this->assertSame('Y', DB::table('N_HRIS_Grup_Fitur_Map')->where('Id_Grup_Map', $baris->Id_Grup_Map)->value('Flag_Aktif'), 'Baris nonaktif tak diaktifkan lagi.');
    }

    /** Laporan kesiapan tak lagi terhalang sinkron terjadwal (sinkron kering tanpa kunci). */
    public function test_kesiapan_tetap_bisa_saat_sinkron_berjalan(): void
    {
        $kunci = Cache::lock('rbac:sinkron-hak', 60);
        $this->assertTrue($kunci->get());
        try {
            $this->actingAs(User::where('Id_Users', 4)->firstOrFail())->getJson('/api/v1/akses-peran/kesiapan')->assertOk();
        } finally {
            $kunci->release();
        }
    }
}
