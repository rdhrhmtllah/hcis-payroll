<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Akses\AuditAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\ModeAkses;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** RBAC Fase 3b: tombol ubah di layar Akses & Peran (API). */
class PengelolaanAksesTest extends TestCase
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
            ->whereNotExists(fn ($w) => $w->from('HRIS_Page_Access as pa')->whereColumn('pa.UserID_Web', 'u.Id_Users')->whereIn('pa.Jenis_page', ['IzinPageAdmin', 'MasterFormula']))
            ->whereNotIn('u.Id_Users', $kecuali ?: [0])->orderBy('u.Id_Users')->value('u.Id_Users');

        return User::where('Id_Users', $id)->firstOrFail();
    }

    private function idRole(string $kode): int
    {
        return (int) DB::table('N_HRIS_Role_Functional')->where('Role_Code', $kode)->value('Id_Role');
    }

    private function adminKpi(): User
    {
        $u = $this->karyawan();
        $id = DB::table('N_HRIS_Role_User')->insertGetId([
            'UserId_Web' => $u->Id_Users, 'Id_Role' => $this->idRole('KPI_ADMIN'),
            'Assigned_At' => now(), 'Assigned_By' => 'UJI', 'Flag_Aktif' => 'Y',
        ], 'Id');
        DB::table('N_HRIS_Role_User_Tanggung_Jawab')->insert(['Id_Role_User' => $id, 'Kode' => 'AKSES']);

        return $u;
    }

    private function auditTerakhir(string $aksi): ?object
    {
        return DB::table(AuditAkses::TABEL)->where('Aksi', $aksi)->orderByDesc('Id_Akses_Audit')->first();
    }

    private function segar(User $u): User
    {
        $c = new \ReflectionProperty(User::class, 'menuAccessCache');
        $c->setAccessible(true);
        $c->setValue(null, []);

        return User::where('Id_Users', $u->Id_Users)->firstOrFail();
    }

    public function test_tugaskan_dan_cabut_peran(): void
    {
        $t = $this->karyawan();
        $sampai = now()->addMonth()->format('Y-m-d');

        $id = $this->actingAs($this->sa())->postJson('/api/v1/akses-peran/penugasan', [
            'id_user' => $t->Id_Users, 'id_role' => $this->idRole('HCIS_LEADER'), 'cakupan' => [],
            'berlaku_sampai' => $sampai, 'alasan' => 'uji penugasan peran',
        ])->assertOk()->json('result.id_penugasan');

        $ru = DB::table('N_HRIS_Role_User')->where('Id', $id)->first();
        $this->assertSame('Y', $ru->Flag_Aktif);
        $this->assertSame('uji penugasan peran', $ru->Alasan);
        $this->assertStringStartsWith($sampai, (string) $ru->Berlaku_Sampai);
        $this->assertTrue($this->segar($t)->memilikiJabatan('IzinPageAdmin'));
        $a = $this->auditTerakhir('TUGASKAN_PERAN');
        $this->assertSame((int) $t->Id_Users, (int) $a->Target_Id_User);
        $this->assertContains('HCIS_LEADER', json_decode($a->Nilai_Sesudah, true)['peran']);

        $this->actingAs($this->sa())->postJson("/api/v1/akses-peran/penugasan/{$id}/cabut", ['alasan' => 'uji cabut peran'])->assertOk();
        $this->assertSame('T', DB::table('N_HRIS_Role_User')->where('Id', $id)->value('Flag_Aktif'));
        $this->assertFalse($this->segar($t)->memilikiJabatan('IzinPageAdmin'));
        $this->assertNotNull($this->auditTerakhir('CABUT_PERAN'));
    }

    public function test_tugaskan_divalidasi_dan_ganda_ditolak(): void
    {
        $t = $this->karyawan();
        $isi = ['id_user' => $t->Id_Users, 'id_role' => $this->idRole('HCIS_LEADER'), 'cakupan' => []];
        $this->actingAs($this->sa())->postJson('/api/v1/akses-peran/penugasan', $isi)->assertStatus(422);
        $this->actingAs($this->sa())->postJson('/api/v1/akses-peran/penugasan', $isi + ['alasan' => 'pertama kali'])->assertOk();
        $this->actingAs($this->sa())->postJson('/api/v1/akses-peran/penugasan', $isi + ['alasan' => 'kedua kalinya'])
            ->assertStatus(422)->assertJsonPath('message', 'Karyawan ini sudah memegang peran tersebut.');
    }

    public function test_tanggung_jawab_peran_admin_oleh_system_admin(): void
    {
        $t = $this->karyawan();
        $id = $this->actingAs($this->sa())->postJson('/api/v1/akses-peran/penugasan', [
            'id_user' => $t->Id_Users, 'id_role' => $this->idRole('KPI_SUPER_ADMIN'),
            'cakupan' => [['jenis' => 'PHL', 'nilai' => null]], 'tanggung_jawab' => ['AKSES', 'APPROVAL'], 'alasan' => 'angkat admin KPI',
        ])->assertOk()->json('result.id_penugasan');

        $this->assertSame(['AKSES', 'APPROVAL'], DB::table('N_HRIS_Role_User_Tanggung_Jawab')->where('Id_Role_User', $id)->orderBy('Kode')->pluck('Kode')->all());
        $this->assertSame(['PHL'], DB::table('N_HRIS_Role_User_Cakupan')->where('Id_Role_User', $id)->pluck('Jenis_Cakupan')->all());
    }

    public function test_admin_modul_ditolak_dengan_pesan(): void
    {
        $a = $this->adminKpi();
        $t = $this->karyawan([(int) $a->Id_Users]);

        $this->actingAs($a)->postJson('/api/v1/akses-peran/penugasan', [
            'id_user' => $t->Id_Users, 'id_role' => $this->idRole('HCIS_LEADER'), 'cakupan' => [], 'alasan' => 'coba modul lain',
        ])->assertForbidden()->assertJsonPath('message', 'Peran atau halaman ini di luar modul yang Anda kelola.');
        $kpiAkses = (int) DB::table('N_HRIS_Role_Functional')->insertGetId([
            'Role_Code' => 'UJI_KPI_DIRI', 'Role_Name' => 'Uji', 'Flag_Aktif' => 'Y', 'Created_At' => now(), 'Jenis_Role' => 'AKSES',
            'Id_Grup_Fitur' => DB::table('N_HRIS_Master_Grup_Fitur')->where('Kode_Grup_Fitur', 'KPI')->value('Id_Grup_Fitur'),
        ], 'Id_Role');
        $this->actingAs($a)->postJson('/api/v1/akses-peran/penugasan', [
            'id_user' => $a->Id_Users, 'id_role' => $kpiAkses, 'cakupan' => [], 'alasan' => 'coba diri sendiri',
        ])->assertForbidden()->assertJsonPath('message', 'Anda tidak dapat mengubah akses diri sendiri.');
    }

    public function test_grant_beri_dan_cabut(): void
    {
        $t = $this->karyawan();
        $this->actingAs($this->sa())->postJson('/api/v1/akses-peran/grant', [
            'id_user' => $t->Id_Users, 'jenis_page' => 'MasterFormula', 'berlaku_sampai' => now()->addWeek()->format('Y-m-d'), 'alasan' => 'uji grant sementara',
        ])->assertOk();
        $pa = DB::table('HRIS_Page_Access')->where('UserID_Web', $t->Id_Users)->where('Jenis_page', 'MasterFormula')->first();
        $this->assertSame('Y', $pa->Flag_Aktif);
        $this->assertSame('uji grant sementara', $pa->Alasan);
        $this->assertTrue($this->segar($t)->memilikiJabatan('MasterFormula'));
        $this->assertNotNull($this->auditTerakhir('BERI_GRANT'));

        $this->actingAs($this->sa())->postJson('/api/v1/akses-peran/grant/cabut', [
            'id_user' => $t->Id_Users, 'jenis_page' => 'MasterFormula', 'alasan' => 'uji cabut grant',
        ])->assertOk();
        $this->assertFalse($this->segar($t)->memilikiJabatan('MasterFormula'));
        $this->assertNotNull($this->auditTerakhir('CABUT_GRANT'));
    }

    public function test_ubah_hak_hanya_modul_baru_dan_tercatat(): void
    {
        $kpiRole = $this->idRole('KPI_ADMIN');
        $url = "/api/v1/akses-peran/peran/{$kpiRole}/hak";
        $isi = ['hak' => [['jenis_page' => 'MasterFormula', 'aksi' => ['VIEW', 'EDIT']]], 'alasan' => 'uji ubah matriks'];
        $this->actingAs($this->sa())->putJson($url, $isi)->assertForbidden();

        $kpi = (int) DB::table('N_HRIS_Master_Grup_Fitur')->where('Kode_Grup_Fitur', 'KPI')->value('Id_Grup_Fitur');
        DB::table('N_HRIS_Akses_Mode_Modul')->updateOrInsert(['Id_Grup_Fitur' => $kpi], ['Mode' => 'BARU']);
        ModeAkses::lupakan();

        $this->actingAs($this->sa())->putJson($url, $isi)->assertOk();

        $aktif = DB::table('N_HRIS_Role_Hak as h')->join('N_HRIS_Klasifikasi_Aksi as a', 'a.Id_Klasifikasi_Actions', '=', 'h.Id_Aksi')
            ->where('h.Id_Role', $kpiRole)->where('h.Flag_Aktif', 'Y')->get(['h.Jenis_page', 'a.Nama_Aksi'])
            ->map(fn ($r) => $r->Jenis_page.':'.$r->Nama_Aksi)->sort()->values()->all();
        $this->assertSame(['MasterFormula:EDIT', 'MasterFormula:VIEW'], $aktif);
        $a = $this->auditTerakhir('UBAH_HAK');
        $this->assertSame('ROLE', $a->Subjek_Jenis);
        $this->assertNotEmpty(json_decode($a->Nilai_Sebelum, true));
    }

    public function test_pratinjau_dampak(): void
    {
        $t = $this->karyawan();
        $r = $this->actingAs($this->sa())->postJson('/api/v1/akses-peran/pratinjau', [
            'jenis' => 'penugasan', 'id_user' => $t->Id_Users, 'id_role' => $this->idRole('HCIS_LEADER'),
        ])->assertOk()->json('result');

        $this->assertContains('IzinPageAdmin', array_column($r['halaman_baru'], 'jenis_page'));
    }

    public function test_bendera_kelola_di_detail_pengguna(): void
    {
        $a = $this->adminKpi();
        $t = $this->karyawan([(int) $a->Id_Users]);

        $r = $this->actingAs($a)->getJson("/api/v1/akses-peran/pengguna/{$t->Id_Users}")->assertOk()->json('result');
        $this->assertTrue($r['bisa_kelola']);
        $kode = array_column($r['peran_tersedia'], 'kode');
        $this->assertNotContains('KPI_SUPER_ADMIN', $kode, 'Peran ADMIN ditawarkan ke admin modul.');
        $this->assertNotContains('HCIS_LEADER', $kode, 'Peran modul lain ditawarkan.');
        $this->assertNotContains('ManajemenAksesDivisi', array_column($r['halaman_tersedia'], 'jenis_page'));

        $diri = $this->actingAs($a)->getJson("/api/v1/akses-peran/pengguna/{$a->Id_Users}")->assertOk()->json('result');
        $this->assertFalse($diri['bisa_kelola']);
    }
}
