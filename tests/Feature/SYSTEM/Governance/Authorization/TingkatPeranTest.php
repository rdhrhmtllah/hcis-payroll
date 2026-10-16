<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\ModeAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\TingkatPeran;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Master tingkat peran & Super Admin per modul (DDL-2026-181, DML-2026-182).
 * Tiap modul seperti aplikasi tersendiri: Super Admin modul mengangkat admin
 * modulnya dan mengatur matriks hak; admin modul menugaskan peran pengguna.
 */
class TingkatPeranTest extends TestCase
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

    private function peranAkses(string $modul, string $kode): string
    {
        DB::table('N_HRIS_Role_Functional')->insert([
            'Role_Code' => $kode, 'Role_Name' => 'Uji '.$kode, 'Flag_Aktif' => 'Y', 'Created_At' => now(),
            'Jenis_Role' => 'AKSES', 'Kode_Tingkat' => 'PENGGUNA', 'Id_Grup_Fitur' => $this->grup($modul),
        ]);

        return $kode;
    }

    private function tugaskan(User $admin, User $t, string $kode, array $tambah = [])
    {
        return $this->actingAs($admin)->postJson('/api/v1/akses-peran/penugasan', $tambah + [
            'id_user' => $t->Id_Users, 'id_role' => $this->idRole($kode), 'cakupan' => [], 'alasan' => 'uji tingkat peran',
        ]);
    }

    private function kpiBaru(): void
    {
        DB::table('N_HRIS_Akses_Mode_Modul')->updateOrInsert(['Id_Grup_Fitur' => $this->grup('KPI')], ['Mode' => 'BARU']);
        ModeAkses::lupakan();
    }

    public function test_tingkat_peran_dari_master_dan_bawaan(): void
    {
        $t = app(TingkatPeran::class);
        $this->assertSame('SUPER_ADMIN', $t->dariPeran((object) ['Role_Code' => 'KPI_SUPER_ADMIN', 'Id_Grup_Fitur' => 2, 'Jenis_Role' => 'ADMIN', 'Kode_Tingkat' => null]));
        $this->assertSame('ADMIN', $t->dariPeran((object) ['Role_Code' => 'KPI_ADMIN', 'Id_Grup_Fitur' => 2, 'Jenis_Role' => 'ADMIN']));
        $this->assertSame('PENGGUNA', $t->dariPeran((object) ['Role_Code' => 'HCIS_LEADER', 'Id_Grup_Fitur' => 1, 'Jenis_Role' => 'AKSES']));
        $this->assertGreaterThan($t->peringkat('ADMIN'), $t->peringkat('SUPER_ADMIN'));
        $this->assertTrue($t->boleh('SUPER_ADMIN', TingkatPeran::UBAH_MATRIKS));
        $this->assertFalse($t->boleh('ADMIN', TingkatPeran::UBAH_MATRIKS));
    }

    public function test_super_admin_modul_mengangkat_dan_mencabut_admin_modulnya(): void
    {
        $s = $this->karyawan();
        $this->pegang($s, 'KPI_SUPER_ADMIN', ['AKSES', 'APPROVAL', 'KEANGGOTAAN']);
        $t = $this->karyawan([(int) $s->Id_Users]);

        $id = $this->tugaskan($s, $t, 'KPI_ADMIN', ['tanggung_jawab' => ['AKSES']])->assertOk()->json('result.id_penugasan');
        $this->actingAs($s)->postJson("/api/v1/akses-peran/penugasan/{$id}/cabut", ['alasan' => 'uji tingkat peran'])->assertOk();
        // Setingkat (Super Admin lain) tidak.
        $this->tugaskan($s, $t, 'KPI_SUPER_ADMIN', ['tanggung_jawab' => ['AKSES']])->assertForbidden();
    }

    public function test_super_admin_hanya_di_modulnya_dan_bisa_di_dua_modul(): void
    {
        $s = $this->karyawan();
        $this->pegang($s, 'KPI_SUPER_ADMIN');
        $t = $this->karyawan([(int) $s->Id_Users]);

        $this->tugaskan($s, $t, 'LMS_ADMIN', ['tanggung_jawab' => ['AKSES']])->assertForbidden()
            ->assertJsonPath('message', 'Peran atau halaman ini di luar modul yang Anda kelola.');

        $this->pegang($s, 'LMS_SUPER_ADMIN');
        $this->tugaskan($s, $t, 'LMS_ADMIN', ['tanggung_jawab' => ['AKSES']])->assertOk();
    }

    public function test_admin_modul_tak_bisa_mengangkat_admin_tapi_bisa_menugaskan_pengguna(): void
    {
        $a = $this->karyawan();
        $this->pegang($a, 'KPI_ADMIN');
        $t = $this->karyawan([(int) $a->Id_Users]);

        $this->tugaskan($a, $t, 'KPI_ADMIN', ['tanggung_jawab' => ['APPROVAL']])->assertForbidden();
        $this->tugaskan($a, $t, $this->peranAkses('KPI', 'UJI_TP_KPI'))->assertOk();
    }

    public function test_matriks_hanya_super_admin_modul(): void
    {
        $this->kpiBaru();
        $role = $this->idRole($this->peranAkses('KPI', 'UJI_TP_MATRIKS'));
        $url = "/api/v1/akses-peran/peran/{$role}/hak";
        $isi = ['hak' => [['jenis_page' => 'MasterFormula', 'aksi' => ['VIEW', 'EDIT']]], 'alasan' => 'atur hak tulis'];
        $a = $this->karyawan();
        $this->pegang($a, 'KPI_ADMIN');
        $s = $this->karyawan([(int) $a->Id_Users]);
        $this->pegang($s, 'KPI_SUPER_ADMIN');

        $this->actingAs($a)->putJson($url, $isi)->assertForbidden()
            ->assertJsonPath('message', 'Matriks hak hanya dapat diubah Super Admin modul atau System Admin.');
        $this->actingAs($s)->putJson($url, $isi)->assertOk();
        // Peran admin modul (tingkat di bawahnya) juga boleh — sel tata kelolanya dibiarkan; peran Super Admin (setingkat) tidak.
        $adminKpi = $this->idRole('KPI_ADMIN');
        $matriks = collect($this->actingAs($this->sa())->getJson("/api/v1/akses-peran/peran/{$adminKpi}")->assertOk()->json('result.matriks'))
            ->map(fn ($m) => ['jenis_page' => $m['jenis_page'], 'aksi' => $m['aksi']])->all();
        $matriks[] = ['jenis_page' => 'MasterTarget', 'aksi' => ['VIEW']];
        $this->actingAs($s)->putJson("/api/v1/akses-peran/peran/{$adminKpi}/hak", ['hak' => $matriks, 'alasan' => 'tambah menu admin'])->assertOk();
        $this->actingAs($s)->putJson('/api/v1/akses-peran/peran/'.$this->idRole('KPI_SUPER_ADMIN').'/hak', $isi)->assertForbidden();
    }

    public function test_admin_tak_bisa_mengubah_akses_super_admin_modulnya(): void
    {
        $s = $this->karyawan();
        $this->pegang($s, 'KPI_SUPER_ADMIN');
        $a = $this->karyawan([(int) $s->Id_Users]);
        $this->pegang($a, 'KPI_ADMIN');

        $this->tugaskan($a, $s, $this->peranAkses('KPI', 'UJI_TP_BALAS'))->assertForbidden()
            ->assertJsonPath('message', 'Akses karyawan setingkat atau di atas Anda di modul ini hanya dapat diubah tingkat di atasnya.');
    }

    public function test_tanggung_jawab_tak_melebihi_milik_pengangkat(): void
    {
        $s = $this->karyawan();
        $this->pegang($s, 'KPI_SUPER_ADMIN', ['AKSES']);
        $t = $this->karyawan([(int) $s->Id_Users]);

        $this->tugaskan($s, $t, 'KPI_ADMIN', ['tanggung_jawab' => ['AKSES', 'APPROVAL']])->assertForbidden()
            ->assertJsonPath('message', 'Tanggung jawab yang diberikan melebihi tanggung jawab Anda.');
    }

    public function test_super_admin_ess_tak_bisa_mengangkat_admin_pembawa_layar_sistem(): void
    {
        $s = $this->karyawan();
        $this->pegang($s, 'ESS_SUPER_ADMIN');
        $t = $this->karyawan([(int) $s->Id_Users]);

        $res = $this->tugaskan($s, $t, 'ESS_ADMIN', ['tanggung_jawab' => ['AKSES']])->assertForbidden();
        $this->assertStringContainsString('khusus System Admin', (string) $res->json('message'));
    }

    public function test_super_admin_lintas_modul_lama_tak_berwenang(): void
    {
        $s = $this->karyawan();
        $this->pegang($s, 'HCIS_SUPER_ADMIN');

        $this->assertSame([], app(KeputusanAkses::class)->modulKelolaAkses($s));
    }

    public function test_pilihan_peran_super_admin_modul(): void
    {
        $s = $this->karyawan();
        $this->pegang($s, 'KPI_SUPER_ADMIN');
        $t = $this->karyawan([(int) $s->Id_Users]);

        $kode = collect($this->actingAs($s)->getJson("/api/v1/akses-peran/pengguna/{$t->Id_Users}")->assertOk()->json('result.peran_tersedia'))->pluck('kode');

        $this->assertContains('KPI_ADMIN', $kode);
        $this->assertNotContains('KPI_SUPER_ADMIN', $kode);
        $this->assertNotContains('LMS_ADMIN', $kode);
    }

    public function test_master_tingkat_diubah_system_admin(): void
    {
        $this->kpiBaru();
        $role = $this->idRole($this->peranAkses('KPI', 'UJI_TP_MASTER'));
        $a = $this->karyawan();
        $this->pegang($a, 'KPI_ADMIN');
        $isiHak = ['hak' => [['jenis_page' => 'MasterFormula', 'aksi' => ['VIEW']]], 'alasan' => 'atur hak'];

        $this->actingAs($a)->getJson('/api/v1/akses-peran/tingkat')->assertForbidden();
        $master = collect($this->actingAs($this->sa())->getJson('/api/v1/akses-peran/tingkat')->assertOk()->json('result'))->keyBy('kode');
        $this->assertSame(['SYSTEM_ADMIN', 'SUPER_ADMIN', 'ADMIN', 'PENGGUNA'], $master->keys()->all());

        $this->actingAs($this->sa())->putJson('/api/v1/akses-peran/tingkat/SYSTEM_ADMIN', ['boleh_ubah_matriks' => false, 'alasan' => 'coba ubah'])->assertStatus(422);
        $this->actingAs($this->sa())->putJson('/api/v1/akses-peran/tingkat/ADMIN', ['boleh_ubah_matriks' => true, 'alasan' => 'admin boleh atur matriks'])->assertOk();
        KeputusanAkses::lupakanCache();

        $this->actingAs($a)->putJson("/api/v1/akses-peran/peran/{$role}/hak", $isiHak)->assertOk();
    }

    public function test_tingkat_peran_diubah_system_admin(): void
    {
        $kode = $this->peranAkses('KPI', 'UJI_TP_NAIK');
        $s = $this->karyawan();
        $this->pegang($s, 'KPI_SUPER_ADMIN');

        $this->actingAs($s)->putJson('/api/v1/akses-peran/peran/'.$this->idRole($kode).'/tingkat', ['tingkat' => 'ADMIN', 'alasan' => 'jadikan admin'])->assertForbidden();
        $this->actingAs($this->sa())->putJson('/api/v1/akses-peran/peran/'.$this->idRole($kode).'/tingkat', ['tingkat' => 'ADMIN', 'alasan' => 'jadikan admin'])->assertOk();

        $r = DB::table('N_HRIS_Role_Functional')->where('Role_Code', $kode)->first(['Kode_Tingkat', 'Jenis_Role']);
        $this->assertSame(['ADMIN', 'ADMIN'], [$r->Kode_Tingkat, $r->Jenis_Role]);
    }
}
