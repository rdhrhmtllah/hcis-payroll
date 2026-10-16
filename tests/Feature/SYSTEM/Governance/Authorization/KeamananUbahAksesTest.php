<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Akses\AuditAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\ModeAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\SkemaRbac;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Review Fase 3b: celah eskalasi & integritas pada tombol ubah akses.
 * Tiap uji di sini gagal sebelum perbaikannya.
 */
class KeamananUbahAksesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');
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

    private function grup(string $kode): int
    {
        return (int) DB::table('N_HRIS_Master_Grup_Fitur')->where('Kode_Grup_Fitur', $kode)->value('Id_Grup_Fitur');
    }

    /** Pegang peran admin; tanggung jawab sesuai konversi (MENU→AKSES, APPROVAL→APPROVAL). */
    private function pegang(User $u, string $kode, ?string $sampai = null, array $tj = []): int
    {
        $id = DB::table('N_HRIS_Role_User')->insertGetId([
            'UserId_Web' => $u->Id_Users, 'Id_Role' => $this->idRole($kode), 'Assigned_At' => now(),
            'Assigned_By' => 'UJI', 'Flag_Aktif' => 'Y', 'Berlaku_Sampai' => $sampai,
        ], 'Id');
        foreach ($tj as $k) {
            DB::table('N_HRIS_Role_User_Tanggung_Jawab')->insert(['Id_Role_User' => $id, 'Kode' => $k]);
        }

        return $id;
    }

    private function tugaskan(User $admin, User $t, string $kode, array $tambah = [])
    {
        return $this->actingAs($admin)->postJson('/api/v1/akses-peran/penugasan', $tambah + [
            'id_user' => $t->Id_Users, 'id_role' => $this->idRole($kode), 'cakupan' => [], 'alasan' => 'uji keamanan',
        ]);
    }

    private function kpiAkses(): int
    {
        return (int) DB::table('N_HRIS_Role_Functional')->insertGetId([
            'Role_Code' => 'UJI_KPI_AKSES_'.random_int(100, 999), 'Role_Name' => 'Uji KPI', 'Flag_Aktif' => 'Y',
            'Created_At' => now(), 'Jenis_Role' => 'AKSES', 'Id_Grup_Fitur' => $this->grup('KPI'),
        ], 'Id_Role');
    }

    /** C1 — peran yang membawa halaman SYSTEM_ONLY/tata kelola tak bisa ditugaskan admin modul. */
    public function test_c1_peran_pembawa_halaman_system_only_ditolak(): void
    {
        $a = $this->karyawan();
        $this->pegang($a, 'ESS_ADMIN', null, ['AKSES']);
        $t = $this->karyawan([(int) $a->Id_Users]);

        $this->tugaskan($a, $t, 'HCIS_LEADER')->assertForbidden();
        $this->assertSame(0, DB::table('N_HRIS_Role_User')->where('UserId_Web', $t->Id_Users)->count());
    }

    /** C2 — admin approval/keanggotaan (tanpa tanggung jawab AKSES) tak bisa mengubah akses. */
    public function test_c2_admin_tanpa_tanggung_jawab_akses_ditolak(): void
    {
        $a = $this->karyawan();
        $this->pegang($a, 'KPI_ADMIN', null, ['APPROVAL']);
        $t = $this->karyawan([(int) $a->Id_Users]);
        $role = $this->kpiAkses();

        $this->actingAs($a)->postJson('/api/v1/akses-peran/penugasan', [
            'id_user' => $t->Id_Users, 'id_role' => $role, 'cakupan' => [], 'alasan' => 'uji tanggung jawab',
        ])->assertForbidden();
        $this->actingAs($a)->postJson('/api/v1/akses-peran/grant', [
            'id_user' => $t->Id_Users, 'jenis_page' => 'MasterFormula', 'alasan' => 'uji tanggung jawab',
        ])->assertForbidden();
        // Tetap bisa melihat layar (baca-saja).
        $this->actingAs($a)->getJson('/api/v1/akses-peran/ringkasan')->assertOk();
    }

    /** C3 — editor matriks: halaman modul lain / SYSTEM_ONLY / peran yang dipegang sendiri ditolak. */
    public function test_c3_editor_matriks_dibatasi(): void
    {
        DB::table('N_HRIS_Akses_Mode_Modul')->updateOrInsert(['Id_Grup_Fitur' => $this->grup('KPI')], ['Mode' => 'BARU']);
        ModeAkses::lupakan();
        $a = $this->karyawan();
        // Matriks hanya Super Admin modul (master tingkat peran); admin modul ditolak.
        $admin = $this->karyawan([(int) $a->Id_Users]);
        $this->pegang($admin, 'KPI_ADMIN', null, ['AKSES']);
        $this->pegang($a, 'KPI_SUPER_ADMIN', null, ['AKSES']);
        $role = $this->kpiAkses();
        $url = "/api/v1/akses-peran/peran/{$role}/hak";
        $this->actingAs($admin)->putJson($url, ['hak' => [['jenis_page' => 'MasterFormula', 'aksi' => ['VIEW']]], 'alasan' => 'admin modul'])->assertForbidden();

        $this->actingAs($a)->putJson($url, ['hak' => [['jenis_page' => 'IzinPage', 'aksi' => ['VIEW']]], 'alasan' => 'halaman ESS'])->assertForbidden();
        $this->actingAs($a)->putJson($url, ['hak' => [['jenis_page' => 'MasterFormula', 'aksi' => ['VIEW']]], 'alasan' => 'halaman KPI'])->assertOk();

        DB::table('N_HRIS_Role_User')->insert(['UserId_Web' => $a->Id_Users, 'Id_Role' => $role, 'Assigned_At' => now(), 'Assigned_By' => 'UJI', 'Flag_Aktif' => 'Y']);
        $this->actingAs($a)->putJson($url, ['hak' => [['jenis_page' => 'MasterTarget', 'aksi' => ['VIEW']]], 'alasan' => 'peran sendiri'])->assertForbidden();
    }

    /** C4 — tanpa DDL-2026-165: layar detail tetap 200, tulis 409. */
    public function test_c4_tanpa_ddl_detail_tetap_jalan_tulis_409(): void
    {
        $sa = User::where('Id_Users', 4)->firstOrFail();
        $r = new \ReflectionProperty(SkemaRbac::class, 'bendera');
        $r->setAccessible(true);
        $r->setValue(null, ['model_baru' => false, 'kedaluwarsa_penugasan' => false, 'kedaluwarsa_grant' => false, 'audit' => false]);

        $this->actingAs($sa)->getJson('/api/v1/akses-peran/peran/'.$this->idRole('HCIS_LEADER'))->assertOk();
        $t = $this->karyawan();
        $this->actingAs($sa)->getJson("/api/v1/akses-peran/pengguna/{$t->Id_Users}")->assertOk();
        $this->tugaskan($sa, $t, 'HCIS_LEADER')->assertStatus(409);
    }

    /** I1 — admin yang penugasannya kedaluwarsa tak lagi lolos gerbang layar. */
    public function test_i1_admin_kedaluwarsa_ditolak_gerbang(): void
    {
        $a = $this->karyawan();
        $this->pegang($a, 'KPI_ADMIN', now()->subDay()->toDateTimeString(), ['AKSES']);

        $this->actingAs($a)->getJson('/api/v1/akses-peran/ringkasan')->assertForbidden();
    }

    /** I3 — layar lama tak lagi menghapus batas yang dipasang layar baru. */
    public function test_i3_simpan_role_lama_mempertahankan_batas(): void
    {
        $t = $this->karyawan();
        $id = $this->pegang($t, 'HCIS_LEADER', now()->addMonth()->toDateTimeString());
        DB::table('N_HRIS_Role_User_Cakupan')->insert(['Id_Role_User' => $id, 'Jenis_Cakupan' => 'PHL']);

        $this->actingAs(User::where('Id_Users', 4)->firstOrFail())
            ->postJson("/api/v1/admin/role-manager/user-roles/{$t->Id_Users}", ['roleIds' => [$this->idRole('HCIS_LEADER')]])->assertOk();

        $aktif = DB::table('N_HRIS_Role_User')->where('UserId_Web', $t->Id_Users)->where('Flag_Aktif', 'Y')->get();
        $this->assertCount(1, $aktif);
        $this->assertSame($id, (int) $aktif[0]->Id, 'Penugasan dibuat ulang tanpa batasnya.');
    }

    /** I4 — grant kembar: semua baris ikut kedaluwarsa. */
    public function test_i4_grant_kembar_ikut_diperbarui(): void
    {
        $t = $this->karyawan();
        foreach ([1, 2] as $_) {
            DB::table('HRIS_Page_Access')->insert(['Kode_Perusahaan' => '001', 'Jenis_page' => 'MasterFormula', 'UserID_Web' => $t->Id_Users, 'ID_Divisi' => 0, 'ID_Level' => 0, 'Flag_Aktif' => 'Y']);
        }

        $this->actingAs(User::where('Id_Users', 4)->firstOrFail())->postJson('/api/v1/akses-peran/grant', [
            'id_user' => $t->Id_Users, 'jenis_page' => 'MasterFormula', 'berlaku_sampai' => now()->addDay()->format('Y-m-d'), 'alasan' => 'uji grant kembar',
        ])->assertOk();

        $this->assertSame(0, DB::table('HRIS_Page_Access')->where('UserID_Web', $t->Id_Users)->where('Jenis_page', 'MasterFormula')
            ->where('Flag_Aktif', 'Y')->whereNull('Berlaku_Sampai')->count(), 'Baris kembar tanpa tanggal tetap memberi akses selamanya.');
    }

    /** I7 — pratinjau mengikuti lingkup & memvalidasi isian. */
    public function test_i7_pratinjau_dibatasi_dan_divalidasi(): void
    {
        $a = $this->karyawan();
        $this->pegang($a, 'KPI_ADMIN', null, ['AKSES']);
        $t = $this->karyawan([(int) $a->Id_Users]);

        $this->actingAs($a)->postJson('/api/v1/akses-peran/pratinjau', ['jenis' => 'grant', 'id_user' => $t->Id_Users, 'jenis_page' => 'IzinPageAdmin'])->assertForbidden();
        $this->actingAs($a)->postJson('/api/v1/akses-peran/pratinjau', ['jenis' => 'hak', 'id_role' => $this->idRole('HCIS_LEADER'), 'hak' => []])->assertForbidden();
        $this->actingAs($a)->postJson('/api/v1/akses-peran/pratinjau', ['jenis' => 'penugasan'])->assertStatus(422);
    }

    /** I8 — "berlaku sampai" mencakup hari yang dipilih. */
    public function test_i8_berlaku_sampai_akhir_hari(): void
    {
        $t = $this->karyawan();
        $tgl = now()->addDays(3)->format('Y-m-d');
        $id = $this->tugaskan(User::where('Id_Users', 4)->firstOrFail(), $t, 'HCIS_LEADER', ['berlaku_sampai' => $tgl])->assertOk()->json('result.id_penugasan');

        $this->assertStringStartsWith($tgl.' 23:59:59', (string) DB::table('N_HRIS_Role_User')->where('Id', $id)->value('Berlaku_Sampai'));
    }

    /** I6 — audit penugasan memuat rincian penugasan. */
    public function test_i6_audit_penugasan_rinci(): void
    {
        $t = $this->karyawan();
        $this->tugaskan(User::where('Id_Users', 4)->firstOrFail(), $t, 'HCIS_LEADER', ['cakupan' => [['jenis' => 'PHL', 'nilai' => null]]])->assertOk();

        $isi = json_decode(DB::table(AuditAkses::TABEL)->where('Aksi', 'TUGASKAN_PERAN')->orderByDesc('Id_Akses_Audit')->value('Nilai_Sesudah'), true);
        $this->assertSame('HCIS_LEADER', $isi['penugasan']['kode'] ?? null);
        $this->assertSame('PHL', $isi['penugasan']['cakupan'][0]['jenis'] ?? null);
        $this->assertContains('HCIS_LEADER', $isi['peran'] ?? []);
    }

    /** I9 — cakupan "TIM (kosong)" dari admin terbatas disimpan sebagai tim ADMIN, bukan tim penerima. */
    public function test_i9_cakupan_kosong_disimpan_nilai_admin(): void
    {
        $a = $this->karyawan();
        $idPen = $this->pegang($a, 'KPI_ADMIN', null, ['AKSES']);
        DB::table('N_HRIS_Role_User_Cakupan')->insert(['Id_Role_User' => $idPen, 'Jenis_Cakupan' => 'TIM']);
        $t = $this->karyawan([(int) $a->Id_Users]);
        $kodeA = DB::table('Karyawan')->where('UserID_Web', $a->Id_Users)->value('Kode_Karyawan');
        DB::table('HRIS_Karyawan_Team')->insert(['Kode_Karyawan_Team' => $kodeA, 'Kode_Karyawan' => DB::table('Karyawan')->where('UserID_Web', $t->Id_Users)->value('Kode_Karyawan')]);

        $id = $this->actingAs($a)->postJson('/api/v1/akses-peran/penugasan', [
            'id_user' => $t->Id_Users, 'id_role' => $this->kpiAkses(), 'cakupan' => [['jenis' => 'TIM', 'nilai' => null]], 'alasan' => 'uji cakupan tim',
        ])->assertOk()->json('result.id_penugasan');

        $this->assertSame($kodeA, DB::table('N_HRIS_Role_User_Cakupan')->where('Id_Role_User', $id)->value('Nilai'));
    }

    /** Admin modul tak bisa mencabut peran ADMIN atau grant di luar modulnya. */
    public function test_cabut_dibatasi_untuk_admin_modul(): void
    {
        $a = $this->karyawan();
        $this->pegang($a, 'KPI_ADMIN', null, ['AKSES']);
        $t = $this->karyawan([(int) $a->Id_Users]);
        $idAdminT = $this->pegang($t, 'KPI_SUPER_ADMIN', null, ['AKSES']);
        DB::table('HRIS_Page_Access')->insert(['Kode_Perusahaan' => '001', 'Jenis_page' => 'IzinPage', 'UserID_Web' => $t->Id_Users, 'ID_Divisi' => 0, 'ID_Level' => 0, 'Flag_Aktif' => 'Y']);

        $this->actingAs($a)->postJson("/api/v1/akses-peran/penugasan/{$idAdminT}/cabut", ['alasan' => 'coba cabut admin'])
            ->assertForbidden()->assertJsonPath('message', 'Akses karyawan setingkat atau di atas Anda di modul ini hanya dapat diubah tingkat di atasnya.');
        $this->actingAs($a)->postJson('/api/v1/akses-peran/grant/cabut', ['id_user' => $t->Id_Users, 'jenis_page' => 'IzinPage', 'alasan' => 'coba modul lain'])
            ->assertForbidden();
        $this->assertSame('Y', DB::table('N_HRIS_Role_User')->where('Id', $idAdminT)->value('Flag_Aktif'));
    }

    /** I2 — penugasan kedaluwarsa tak lagi memberi kode izin; pool approval tak menerima batas waktu. */
    public function test_i2_kedaluwarsa_di_pembaca_lain(): void
    {
        $t = $this->karyawan();
        $this->pegang($t, 'HC_OPS_ADMIN', now()->subDay()->toDateTimeString());
        $this->assertFalse((new \App\Services\SYSTEM\Governance\Authorization\Shared\FunctionalRole((int) $t->Id_Users))->can('SHIFT_BYPASS_APPROVAL'), 'Izin dari penugasan kedaluwarsa masih berlaku.');

        $u = $this->karyawan([(int) $t->Id_Users]);
        $this->tugaskan(User::where('Id_Users', 4)->firstOrFail(), $u, 'ADMIN_PERMIT', ['berlaku_sampai' => now()->addWeek()->format('Y-m-d')])
            ->assertStatus(422)->assertJsonPath('message', 'Batas waktu belum didukung untuk peran pool approval.');
    }
}
