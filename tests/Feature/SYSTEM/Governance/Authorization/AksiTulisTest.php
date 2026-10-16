<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\ModeAkses;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * RBAC Fase 5 (5b): aksi tulis. Di modul BARU, request non-GET ke rute
 * check.jabatan butuh aksi non-VIEW di matriks hak peran; grant langsung dan
 * menu global tetap penuh. Mode LAMA tak berubah.
 * Skenario: pemegang HCIS_LEADER (modul ESS); IzinPageAdmin punya aksi CRUD di
 * katalog; adminPengumpulanDataPage hanya VIEW di katalog.
 */
class AksiTulisTest extends TestCase
{
    use DatabaseTransactions;

    private User $u;

    private int $ess;

    private int $leader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ess = (int) DB::table('N_HRIS_Master_Grup_Fitur')->where('Kode_Grup_Fitur', 'ESS')->value('Id_Grup_Fitur');
        $this->leader = (int) DB::table('N_HRIS_Role_Functional')->where('Role_Code', 'HCIS_LEADER')->value('Id_Role');
        $this->u = $this->userBaru();
        DB::table('N_HRIS_Role_User')->insert([
            'UserId_Web' => $this->u->Id_Users, 'Id_Role' => $this->leader, 'Assigned_At' => now(), 'Assigned_By' => 'UJI', 'Flag_Aktif' => 'Y',
        ]);
        foreach (['IzinPageAdmin', 'adminPengumpulanDataPage'] as $p) {
            Route::middleware(['web', 'auth', "check.jabatan:{$p}"])->match(['get', 'post', 'delete'], "/_uji/tulis/{$p}", fn () => response('ok'));
        }
        $this->lupakan();
        $this->assertSame($this->ess, app(ModeAkses::class)->modulHalaman('adminPengumpulanDataPage'), 'Prasyarat: halaman milik ESS.');
    }

    private function userBaru(): User
    {
        $id = DB::table('Karyawan as k')->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')
            ->where('k.Aktif', 'Y')
            ->where(fn ($q) => $q->whereNull('u.Is_System_Admin')->orWhere('u.Is_System_Admin', '<>', 'Y'))
            ->whereNotExists(fn ($q) => $q->from('N_HRIS_Role_User as ru')->whereColumn('ru.UserId_Web', 'u.Id_Users'))
            ->whereNotExists(fn ($q) => $q->from('HRIS_Page_Access as pa')->whereColumn('pa.UserID_Web', 'u.Id_Users')->where('pa.Flag_Aktif', 'Y'))
            ->when(isset($this->u), fn ($q) => $q->where('u.Id_Users', '<>', $this->u->Id_Users))
            ->orderBy('u.Id_Users')->value('u.Id_Users');

        return User::where('Id_Users', $id)->firstOrFail();
    }

    private function lupakan(): void
    {
        $c = new \ReflectionProperty(User::class, 'menuAccessCache');
        $c->setAccessible(true);
        $c->setValue(null, []);
        KeputusanAkses::lupakanCache();
        ModeAkses::lupakan();
    }

    private function setMode(string $mode): void
    {
        DB::table('N_HRIS_Akses_Mode_Modul')->updateOrInsert(['Id_Grup_Fitur' => $this->ess], ['Mode' => $mode]);
        $this->lupakan();
    }

    /** Cabut semua aksi non-VIEW peran Leader pada IzinPageAdmin (VIEW tetap). */
    private function cabutTulis(): void
    {
        DB::table('N_HRIS_Role_Hak')->where('Id_Role', $this->leader)->where('Jenis_page', 'IzinPageAdmin')
            ->where('Id_Aksi', '<>', DB::table('N_HRIS_Klasifikasi_Aksi')->where('Nama_Aksi', 'VIEW')->value('Id_Klasifikasi_Actions'))
            ->update(['Flag_Aktif' => 'T']);
        $this->lupakan();
    }

    public function test_lama_tulis_tidak_berubah(): void
    {
        $this->cabutTulis();

        $this->actingAs($this->u)->post('/_uji/tulis/IzinPageAdmin')->assertOk();
        $this->actingAs($this->u)->delete('/_uji/tulis/IzinPageAdmin')->assertOk();
    }

    public function test_baru_peran_tanpa_aksi_tulis_hanya_bisa_membaca(): void
    {
        $this->setMode('BARU');
        $this->cabutTulis();

        $this->actingAs($this->u)->get('/_uji/tulis/IzinPageAdmin')->assertOk();
        $this->actingAs($this->u)->post('/_uji/tulis/IzinPageAdmin')->assertForbidden();
        $this->actingAs($this->u)->delete('/_uji/tulis/IzinPageAdmin')->assertForbidden();
    }

    public function test_baru_peran_dengan_aksi_tulis_tetap_bisa_menulis(): void
    {
        $this->setMode('BARU');

        $this->actingAs($this->u)->post('/_uji/tulis/IzinPageAdmin')->assertOk();
    }

    public function test_baru_grant_langsung_tetap_penuh(): void
    {
        $this->setMode('BARU');
        $this->cabutTulis();
        $g = $this->userBaru();
        DB::table('HRIS_Page_Access')->insert([
            'Kode_Perusahaan' => '001', 'Jenis_page' => 'IzinPageAdmin', 'UserID_Web' => $g->Id_Users,
            'ID_Divisi' => 0, 'ID_Level' => 0, 'Flag_Aktif' => 'Y',
        ]);
        $this->lupakan();

        $this->actingAs($g)->post('/_uji/tulis/IzinPageAdmin')->assertOk();
    }

    public function test_baru_halaman_tanpa_aksi_tulis_di_katalog_cukup_view(): void
    {
        $this->setMode('BARU');

        $this->actingAs($this->u)->post('/_uji/tulis/adminPengumpulanDataPage')->assertOk();
    }

    public function test_bayangan_tetap_lama_dan_mencatat_selisih_tulis(): void
    {
        $this->setMode('BAYANGAN');
        $this->cabutTulis();

        $this->actingAs($this->u)->post('/_uji/tulis/IzinPageAdmin')->assertOk();
        // Lewat HTTP antrian dikirim saat terminate (di luar jangkauan transaksi uji);
        // keputusan yang sama dipanggil langsung untuk memeriksa pencatatannya.
        \Illuminate\Support\Facades\Cache::forget('rbac:selisih:'.$this->u->Id_Users.'|izinpageadmin|TULIS');
        $this->assertTrue(app(KeputusanAkses::class)->bolehTulis($this->u, 'IzinPageAdmin'));
        KeputusanAkses::kirimSelisih(DB::transactionLevel());

        $this->assertSame(1, DB::table('N_HRIS_Akses_Selisih')->where('Id_User', $this->u->Id_Users)
            ->where('Jenis_page', 'IzinPageAdmin')->where('Aksi', 'TULIS')->count(), 'Selisih aksi tulis tidak tercatat.');
    }

    public function test_middleware_akses_aksi_spesifik(): void
    {
        Route::middleware(['web', 'auth', 'akses:IzinPageAdmin,DELETE'])->post('/_uji/aksi/hapus', fn () => response('ok'));
        $this->setMode('BARU');
        DB::table('N_HRIS_Role_Hak')->where('Id_Role', $this->leader)->where('Jenis_page', 'IzinPageAdmin')
            ->where('Id_Aksi', DB::table('N_HRIS_Klasifikasi_Aksi')->where('Nama_Aksi', 'DELETE')->value('Id_Klasifikasi_Actions'))
            ->update(['Flag_Aktif' => 'T']);
        $this->lupakan();

        $this->actingAs($this->u)->post('/_uji/aksi/hapus')->assertForbidden();
        $this->actingAs($this->u)->post('/_uji/tulis/IzinPageAdmin')->assertOk();
    }

    /**
     * Review Fase 5 (I1): katalog halaman di modul BARU yang baru mendapat aksi
     * tulis tidak boleh mengunci pemegang peran yang tadinya cukup VIEW.
     */
    public function test_katalog_mendapat_aksi_tulis_tidak_mengunci_peran(): void
    {
        $this->setMode('BARU');
        $this->actingAs($this->u)->post('/_uji/tulis/adminPengumpulanDataPage')->assertOk();
        $aksi = DB::table('N_HRIS_Klasifikasi_Aksi')->pluck('Id_Klasifikasi_Actions', 'Nama_Aksi');

        $this->actingAs(User::where('Id_Users', 4)->firstOrFail())
            ->postJson('/api/v1/system-auth/menu-capabilities/adminPengumpulanDataPage', ['capabilities' => [
                ['id_aksi' => (int) $aksi['VIEW'], 'enabled' => true, 'is_active_default' => 'Y'],
                ['id_aksi' => (int) $aksi['CREATE'], 'enabled' => true, 'is_active_default' => 'Y'],
                ['id_aksi' => (int) $aksi['EDIT'], 'enabled' => true, 'is_active_default' => 'Y'],
            ]])->assertOk();
        $this->lupakan();

        $this->actingAs($this->u)->post('/_uji/tulis/adminPengumpulanDataPage')->assertOk();
        $this->assertSame(2, DB::table('N_HRIS_Role_Hak')->where('Id_Role', $this->leader)->where('Jenis_page', 'adminPengumpulanDataPage')
            ->whereIn('Id_Aksi', [$aksi['CREATE'], $aksi['EDIT']])->where('Flag_Aktif', 'Y')->count(), 'Aksi tulis baru tidak diberikan ke peran pemegang VIEW.');
    }

    /** Review Fase 5 (I2): rute POST yang sebenarnya membaca ditandai rbac_aksi=VIEW. */
    public function test_rute_post_baca_cukup_view(): void
    {
        Route::middleware(['web', 'auth', 'check.jabatan:IzinPageAdmin'])->post('/_uji/baca/izin', fn () => response('ok'))->defaults('rbac_aksi', 'VIEW');
        $this->setMode('BARU');
        $this->cabutTulis();

        $this->actingAs($this->u)->post('/_uji/baca/izin')->assertOk();
        $this->actingAs($this->u)->post('/_uji/tulis/IzinPageAdmin')->assertForbidden();
    }
}
