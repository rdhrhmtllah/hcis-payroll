<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Page Access Setup (`/api/v1/page-access/update-access`). */
class PageAccessUpdateTest extends TestCase
{
    use DatabaseTransactions;
    use FixturePageAccess;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');
        $cache = new \ReflectionProperty(User::class, 'menuAccessCache');
        $cache->setAccessible(true);
        $cache->setValue(null, []);
    }

    protected function kirim(User $admin, array $isi)
    {
        return $this->actingAs($admin)->postJson('/api/v1/page-access/update-access', $isi);
    }

    protected function aktif(int $idUser, string $jenisPage): bool
    {
        return DB::table('HRIS_Page_Access')->where('UserID_Web', $idUser)
            ->where('Jenis_page', $jenisPage)->where('Flag_Aktif', 'Y')->exists();
    }

    public function test_beri_akses_ke_karyawan_yang_belum_punya_baris(): void
    {
        [$admin, $hal] = $this->adminDanHalaman();
        $target = $this->karyawanTanpaBaris($hal->Jenis_page, (int) $admin->Id_Users);

        $this->kirim($admin, ['all' => false, 'employeeIds' => [$target], 'pageIds' => [$hal->id], 'action' => 'grant'])
            ->assertOk();

        $this->assertTrue($this->aktif($target, $hal->Jenis_page));
    }

    public function test_beri_ulang_setelah_dicabut(): void
    {
        [$admin, $hal] = $this->adminDanHalaman();
        $target = $this->karyawanTanpaBaris($hal->Jenis_page, (int) $admin->Id_Users);
        $isi = ['all' => false, 'employeeIds' => [$target], 'pageIds' => [$hal->id]];

        $this->kirim($admin, $isi + ['action' => 'grant'])->assertOk();
        $this->kirim($admin, $isi + ['action' => 'revoke'])->assertOk();
        $this->assertFalse($this->aktif($target, $hal->Jenis_page));

        $this->kirim($admin, $isi + ['action' => 'grant'])->assertOk();
        $this->assertTrue($this->aktif($target, $hal->Jenis_page));
    }

    public function test_tanpa_perubahan_dijawab_422(): void
    {
        [$admin, $hal] = $this->adminDanHalaman();
        $target = $this->karyawanTanpaBaris($hal->Jenis_page, (int) $admin->Id_Users);

        $this->kirim($admin, ['all' => false, 'employeeIds' => [$target], 'pageIds' => [$hal->id], 'action' => 'revoke'])
            ->assertStatus(422);
    }

    public function test_beri_semua_karyawan_berpotongan(): void
    {
        [$admin, $hal] = $this->adminDanHalaman();

        $this->kirim($admin, ['all' => true, 'pageIds' => [$hal->id], 'action' => 'grant'])->assertOk();

        $tanpa = DB::table('Karyawan as k')->where('k.Aktif', 'Y')->whereNotNull('k.UserID_Web')
            ->where('k.UserID_Web', '<>', $admin->Id_Users)
            ->whereNotExists(fn ($q) => $q->from('HRIS_Page_Access as pa')->whereColumn('pa.UserID_Web', 'k.UserID_Web')
                ->where('pa.Jenis_page', $hal->Jenis_page)->where('pa.Flag_Aktif', 'Y'))
            ->count();
        $this->assertSame(0, $tanpa, 'Masih ada karyawan aktif tanpa akses setelah "beri semua".');
    }

    public function test_halaman_di_luar_kelolaan_ditolak(): void
    {
        [$admin] = $this->adminDanHalaman();
        $kelolaan = DB::table('HRIS_Page_Access as pa')
            ->join('N_HRIS_Role_Konten_Access as rka', 'pa.Id_Page_Access', '=', 'rka.Id_Page_Access')
            ->where('pa.UserID_Web', $admin->Id_Users)->where('pa.Flag_Aktif', 'Y')->where('rka.Flag_Aktif', 'Y')
            ->pluck('rka.Id_Grup_Fitur');
        $luar = DB::table('N_HRIS_Menus as m')
            ->whereNotExists(fn ($q) => $q->from('N_HRIS_Grup_Fitur_Map as g')->whereColumn('g.Jenis_page', 'm.Jenis_page')
                ->whereIn('g.Id_Grup_Fitur', $kelolaan))
            ->value('m.id_menu');
        $this->assertNotNull($luar);
        $target = $this->karyawanTanpaBaris('__tidak_ada__', (int) $admin->Id_Users);

        $this->kirim($admin, ['all' => false, 'employeeIds' => [$target], 'pageIds' => [$luar], 'action' => 'grant'])
            ->assertStatus(403);
    }

    public function test_halaman_tata_kelola_hanya_oleh_system_admin(): void
    {
        // ManajemenAksesDivisi milik grup KPI — di dalam kelolaan admin fixture,
        // jadi penolakannya harus datang dari aturan tata kelola, bukan lingkup.
        [$admin] = $this->adminDanHalaman();
        $idTataKelola = DB::table('N_HRIS_Menus')->where('Jenis_page', 'ManajemenAksesDivisi')->value('id_menu');
        $target = $this->karyawanTanpaBaris('ManajemenAksesDivisi', (int) $admin->Id_Users);

        $this->kirim($admin, ['all' => false, 'employeeIds' => [$target], 'pageIds' => [$idTataKelola], 'action' => 'grant'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Halaman pengatur akses hanya dapat dikelola System Admin.');
        $this->assertFalse($this->aktif($target, 'ManajemenAksesDivisi'));
    }

    /** System Admin tanpa baris kelolaan sekalipun tetap bisa memakai Page Access Setup. */
    protected function systemAdmin(): User
    {
        [$admin] = $this->adminDanHalaman();
        DB::table('N_HRIS_Role_Konten_Access as r')
            ->join('HRIS_Page_Access as pa', 'pa.Id_Page_Access', '=', 'r.Id_Page_Access')
            ->where('pa.UserID_Web', $admin->Id_Users)->update(['r.Flag_Aktif' => 'T']);
        DB::table('KPI_Users')->where('Id_Users', $admin->Id_Users)->update(['Is_System_Admin' => 'Y']);

        return User::where('Id_Users', $admin->Id_Users)->firstOrFail();
    }

    public function test_system_admin_mengelola_semua_halaman_termasuk_tata_kelola(): void
    {
        $sa = $this->systemAdmin();
        $id = DB::table('N_HRIS_Menus')->where('Jenis_page', 'systemAuthManagerPage')->value('id_menu');
        $target = $this->karyawanTanpaBaris('systemAuthManagerPage', (int) $sa->Id_Users);

        $ids = collect($this->actingAs($sa)->getJson('/api/v1/page-access/initial-data')
            ->assertOk()->json('result.allPages'))->pluck('id')->map(fn ($v) => (int) $v)->all();
        $this->assertContains((int) $id, $ids, 'Layar System Admin tidak memuat halaman pengatur akses.');

        $this->kirim($sa, ['all' => false, 'employeeIds' => [$target], 'pageIds' => [$id], 'action' => 'grant'])->assertOk();
        $this->assertTrue($this->aktif($target, 'systemAuthManagerPage'));
    }

    public function test_tidak_bisa_mengubah_akses_diri_sendiri(): void
    {
        [$admin, $hal] = $this->adminDanHalaman();
        // Admin memegang halaman itu, jadi "cabut" akan mengubah sesuatu bila tak dicegah.
        DB::table('HRIS_Page_Access')->insert([
            'Kode_Perusahaan' => '001', 'Jenis_page' => $hal->Jenis_page, 'UserID_Web' => $admin->Id_Users,
            'ID_Divisi' => 0, 'ID_Level' => 0, 'Flag_Aktif' => 'Y',
        ]);

        $this->kirim($admin, ['all' => false, 'employeeIds' => [(int) $admin->Id_Users], 'pageIds' => [$hal->id], 'action' => 'revoke'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Anda tidak dapat mengubah akses diri sendiri.');
        $this->assertTrue($this->aktif((int) $admin->Id_Users, $hal->Jenis_page));
    }

    public function test_beri_semua_tidak_menyentuh_diri_sendiri(): void
    {
        [$admin, $hal] = $this->adminDanHalaman();
        // Admin sendiri memegang halaman itu secara aktif.
        DB::table('HRIS_Page_Access')->where('UserID_Web', $admin->Id_Users)->where('Jenis_page', $hal->Jenis_page)->delete();
        DB::table('HRIS_Page_Access')->insert([
            'Kode_Perusahaan' => '001', 'Jenis_page' => $hal->Jenis_page, 'UserID_Web' => $admin->Id_Users,
            'ID_Divisi' => 0, 'ID_Level' => 0, 'Flag_Aktif' => 'Y',
        ]);
        // Pastikan ada karyawan lain yang bisa dicabut.
        $this->kirim($admin, ['all' => true, 'pageIds' => [$hal->id], 'action' => 'grant'])->assertOk();

        $this->kirim($admin, ['all' => true, 'pageIds' => [$hal->id], 'action' => 'revoke'])->assertOk();

        $this->assertTrue(
            $this->aktif((int) $admin->Id_Users, $hal->Jenis_page),
            'Cabut-semua ikut mencabut akses admin sendiri.',
        );
    }

    public function test_data_awal_memuat_halaman_kelolaan(): void
    {
        [$admin, $hal] = $this->adminDanHalaman();

        $ids = collect($this->actingAs($admin)->getJson('/api/v1/page-access/initial-data')
            ->assertOk()->json('result.allPages'))->pluck('id')->map(fn ($v) => (int) $v)->all();

        $this->assertContains((int) $hal->id, $ids);
    }

    /** @return \Illuminate\Support\Collection<int, object> baris Det PAGE_ACCESS milik satu karyawan sejak $awal */
    protected function detAudit(string $kode, int $awal)
    {
        $detail = DB::table('HRIS_Transaksi_Log_Admin_Detail')->where('Urut_Oto', '>', $awal)
            ->where('Jenis', 'PAGE_ACCESS')->where('Kode_Karyawan', $kode)->first();
        $this->assertNotNull($detail, 'Perubahan Page Access tidak tercatat.');

        return DB::table('HRIS_Transaksi_Log_Admin_Det')->where('No_Transaksi', $detail->No_Transaksi)
            ->where('Urut_Detail', $detail->Urut_Oto)->get();
    }

    public function test_perubahan_tercatat_sebelum_dan_sesudah(): void
    {
        [$admin, $hal] = $this->adminDanHalaman();
        $target = $this->karyawanTanpaBaris($hal->Jenis_page, (int) $admin->Id_Users);
        $kode = DB::table('Karyawan')->where('UserID_Web', $target)->value('Kode_Karyawan');
        $awal = (int) DB::table('HRIS_Transaksi_Log_Admin_Detail')->max('Urut_Oto');

        $this->kirim($admin, ['all' => false, 'employeeIds' => [$target], 'pageIds' => [$hal->id], 'action' => 'grant'])
            ->assertOk();

        $det = $this->detAudit($kode, $awal)->firstWhere('Field_Diubah', 'HRIS_Page_Access.'.$hal->Jenis_page);
        $this->assertNotNull($det, 'Tak ada baris audit untuk halaman yang diberikan.');
        $this->assertSame('T', (string) $det->Nilai_Awal);
        $this->assertSame('Y', (string) $det->Nilai_Setelah);
    }

    public function test_banyak_halaman_tercatat_utuh_per_halaman(): void
    {
        [$admin, $hal] = $this->adminDanHalaman();
        $idKpi = DB::table('N_HRIS_Master_Grup_Fitur')->where('Kode_Grup_Fitur', 'KPI')->value('Id_Grup_Fitur');
        $halaman = DB::table('N_HRIS_Grup_Fitur_Map as gfm')
            ->join('N_HRIS_Menus as m', 'm.Jenis_page', '=', 'gfm.Jenis_page')
            ->where('gfm.Id_Grup_Fitur', $idKpi)
            ->whereNotIn('m.Jenis_page', (array) config('authorization.tata_kelola.halaman'))
            ->orderBy('m.id_menu')->limit(15)->get(['m.id_menu', 'm.Jenis_page'])->unique('Jenis_page');
        $this->assertGreaterThanOrEqual(12, $halaman->count(), 'Grup KPI butuh >= 12 halaman untuk uji ini.');
        $target = $this->karyawanTanpaBaris('__tidak_ada__', (int) $admin->Id_Users);
        DB::table('HRIS_Page_Access')->where('UserID_Web', $target)->whereIn('Jenis_page', $halaman->pluck('Jenis_page'))->delete();
        $kode = DB::table('Karyawan')->where('UserID_Web', $target)->value('Kode_Karyawan');
        $awal = (int) DB::table('HRIS_Transaksi_Log_Admin_Detail')->max('Urut_Oto');

        $this->kirim($admin, ['all' => false, 'employeeIds' => [$target], 'pageIds' => $halaman->pluck('id_menu')->all(), 'action' => 'grant'])
            ->assertOk();

        $tercatat = $this->detAudit($kode, $awal)->pluck('Field_Diubah')->sort()->values()->all();
        $diharap = $halaman->pluck('Jenis_page')->map(fn ($p) => 'HRIS_Page_Access.'.$p)->sort()->values()->all();
        $this->assertSame($diharap, $tercatat, 'Audit kehilangan sebagian halaman.');
    }

    public function test_beri_semua_tercatat_per_karyawan(): void
    {
        [$admin, $hal] = $this->adminDanHalaman();
        $awal = (int) DB::table('HRIS_Transaksi_Log_Admin_Detail')->max('Urut_Oto');
        $belum = DB::table('Karyawan as k')->where('k.Aktif', 'Y')->whereNotNull('k.UserID_Web')
            ->where('k.UserID_Web', '<>', $admin->Id_Users)
            ->whereNotExists(fn ($q) => $q->from('HRIS_Page_Access as pa')->whereColumn('pa.UserID_Web', 'k.UserID_Web')
                ->where('pa.Jenis_page', $hal->Jenis_page)->where('pa.Flag_Aktif', 'Y'))
            ->count();

        $this->kirim($admin, ['all' => true, 'pageIds' => [$hal->id], 'action' => 'grant'])->assertOk();

        $this->assertSame($belum, DB::table('HRIS_Transaksi_Log_Admin_Detail')
            ->where('Urut_Oto', '>', $awal)->where('Jenis', 'PAGE_ACCESS')->count());
    }
}
