<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\ModeAkses;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Minor tertunda dari review RBAC Fase 3b (layar ubah akses). Tiap uji gagal
 * sebelum perbaikannya.
 */
class PerbaikanMinorRbacTest extends TestCase
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

    private function grup(string $kode): int
    {
        return (int) DB::table('N_HRIS_Master_Grup_Fitur')->where('Kode_Grup_Fitur', $kode)->value('Id_Grup_Fitur');
    }

    private function peranUji(string $modul, string $kode = 'UJI_MINOR_AKSES'): int
    {
        return (int) DB::table('N_HRIS_Role_Functional')->insertGetId([
            'Role_Code' => $kode, 'Role_Name' => 'Uji Minor', 'Flag_Aktif' => 'Y',
            'Created_At' => now(), 'Jenis_Role' => 'AKSES', 'Id_Grup_Fitur' => $this->grup($modul),
        ], 'Id_Role');
    }

    private function pegang(User $u, int $idRole, array $tj = [], ?string $sampai = null): int
    {
        $id = DB::table('N_HRIS_Role_User')->insertGetId([
            'UserId_Web' => $u->Id_Users, 'Id_Role' => $idRole, 'Assigned_At' => now(), 'Assigned_By' => 'UJI', 'Flag_Aktif' => 'Y',
            'Berlaku_Sampai' => $sampai,
        ], 'Id');
        foreach ($tj as $k) {
            DB::table('N_HRIS_Role_User_Tanggung_Jawab')->insert(['Id_Role_User' => $id, 'Kode' => $k]);
        }
        KeputusanAkses::lupakanCache();

        return $id;
    }

    private function idRole(string $kode): int
    {
        return (int) DB::table('N_HRIS_Role_Functional')->where('Role_Code', $kode)->value('Id_Role');
    }

    /** Penugasan kedaluwarsa tak dihitung "sudah memegang" — memperpanjang cukup tugaskan ulang. */
    public function test_penugasan_kedaluwarsa_bisa_ditugaskan_ulang(): void
    {
        $role = $this->peranUji('KPI');
        $t = $this->karyawan();
        $lama = $this->pegang($t, $role, [], now()->subDay()->toDateTimeString());

        $this->actingAs($this->sa())->postJson('/api/v1/akses-peran/penugasan', [
            'id_user' => $t->Id_Users, 'id_role' => $role, 'cakupan' => [], 'alasan' => 'perpanjang peran',
        ])->assertOk();

        $this->assertSame('T', DB::table('N_HRIS_Role_User')->where('Id', $lama)->value('Flag_Aktif'), 'Penugasan kedaluwarsa lama tak ditutup.');
    }

    /** Admin modul bisa mencabut penugasan yang perannya sudah dinonaktifkan. */
    public function test_cabut_penugasan_peran_nonaktif(): void
    {
        $a = $this->karyawan();
        $this->pegang($a, $this->idRole('KPI_ADMIN'), ['AKSES']);
        $role = $this->peranUji('KPI');
        $t = $this->karyawan([(int) $a->Id_Users]);
        $id = $this->pegang($t, $role);
        DB::table('N_HRIS_Role_Functional')->where('Id_Role', $role)->update(['Flag_Aktif' => 'T']);

        $this->actingAs($a)->postJson("/api/v1/akses-peran/penugasan/{$id}/cabut", ['alasan' => 'peran sudah mati'])->assertOk();
    }

    /** Dua baris hak yang hanya beda spasi/huruf: menyimpan matriks menonaktifkan keduanya. */
    public function test_ubah_hak_menutup_semua_baris_kembar(): void
    {
        DB::table('N_HRIS_Akses_Mode_Modul')->updateOrInsert(['Id_Grup_Fitur' => $this->grup('KPI')], ['Mode' => 'BARU']);
        ModeAkses::lupakan();
        $role = $this->peranUji('KPI');
        $view = DB::table('N_HRIS_Klasifikasi_Aksi')->where('Nama_Aksi', 'VIEW')->value('Id_Klasifikasi_Actions');
        $page = (string) DB::table('N_HRIS_Grup_Fitur_Map')->where('Id_Grup_Fitur', $this->grup('KPI'))->value('Jenis_page');
        foreach ([$page, substr($page, 0, 2).' '.substr($page, 2)] as $p) {
            DB::table('N_HRIS_Role_Hak')->insert(['Id_Role' => $role, 'Jenis_page' => $p, 'Id_Aksi' => $view, 'Flag_Aktif' => 'Y', 'Created_By' => 'UJI']);
        }

        $this->actingAs($this->sa())->putJson("/api/v1/akses-peran/peran/{$role}/hak", ['hak' => [], 'alasan' => 'kosongkan matriks'])->assertOk();

        $this->assertSame(0, DB::table('N_HRIS_Role_Hak')->where('Id_Role', $role)->where('Flag_Aktif', 'Y')->count(), 'Baris hak kembar tertinggal aktif.');
    }

    /** Konkurensi optimistis: matriks yang sudah diubah admin lain → 409, bukan menimpa. */
    public function test_ubah_hak_dengan_versi_basi_ditolak(): void
    {
        DB::table('N_HRIS_Akses_Mode_Modul')->updateOrInsert(['Id_Grup_Fitur' => $this->grup('KPI')], ['Mode' => 'BARU']);
        ModeAkses::lupakan();
        $role = $this->peranUji('KPI');
        $versi = $this->actingAs($this->sa())->getJson("/api/v1/akses-peran/peran/{$role}")->assertOk()->json('result.versi');
        $this->assertNotEmpty($versi);
        $page = (string) DB::table('N_HRIS_Grup_Fitur_Map')->where('Id_Grup_Fitur', $this->grup('KPI'))->value('Jenis_page');
        $this->actingAs($this->sa())->putJson("/api/v1/akses-peran/peran/{$role}/hak", ['hak' => [['jenis_page' => $page, 'aksi' => ['VIEW']]], 'alasan' => 'admin pertama', 'versi' => $versi])->assertOk();

        $this->actingAs($this->sa())->putJson("/api/v1/akses-peran/peran/{$role}/hak", ['hak' => [], 'alasan' => 'admin kedua', 'versi' => $versi])
            ->assertStatus(409);
    }

    /** Pratinjau matriks menghitung perubahan aksi, bukan hanya halaman. */
    public function test_pratinjau_hak_menghitung_perubahan_aksi(): void
    {
        DB::table('N_HRIS_Akses_Mode_Modul')->updateOrInsert(['Id_Grup_Fitur' => $this->grup('KPI')], ['Mode' => 'BARU']);
        ModeAkses::lupakan();
        $role = $this->peranUji('KPI');
        $page = (string) DB::table('N_HRIS_Grup_Fitur_Map')->where('Id_Grup_Fitur', $this->grup('KPI'))->value('Jenis_page');
        DB::table('N_HRIS_Role_Hak')->insert(['Id_Role' => $role, 'Jenis_page' => $page, 'Created_By' => 'UJI', 'Flag_Aktif' => 'Y',
            'Id_Aksi' => DB::table('N_HRIS_Klasifikasi_Aksi')->where('Nama_Aksi', 'VIEW')->value('Id_Klasifikasi_Actions')]);

        $r = $this->actingAs($this->sa())->postJson('/api/v1/akses-peran/pratinjau', [
            'jenis' => 'hak', 'id_role' => $role, 'hak' => [['jenis_page' => $page, 'aksi' => ['VIEW', 'EDIT']]],
        ])->assertOk();

        $this->assertCount(1, $r->json('result.aksi_berubah') ?? [], 'Perubahan aksi tak terlihat di pratinjau.');
    }

    /** Cakupan yang ditawarkan ditandai modulnya; System Admin mendapat pilihan nilai konkret. */
    public function test_cakupan_per_modul_dan_nilai_untuk_system_admin(): void
    {
        $t = $this->karyawan();
        $sa = $this->actingAs($this->sa())->getJson("/api/v1/akses-peran/pengguna/{$t->Id_Users}")->assertOk();
        $this->assertNotEmpty($sa->json('result.nilai_cakupan.DIVISI'), 'System Admin tak mendapat pilihan divisi.');

        $a = $this->karyawan([(int) $t->Id_Users]);
        $id = $this->pegang($a, $this->idRole('KPI_ADMIN'), ['AKSES']);
        $divisi = DB::table('Karyawan as k')->join('View_Divisi_Sub_Divisi as d', 'd.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->where('k.UserID_Web', $t->Id_Users)->value('d.ID_Divisi');
        DB::table('N_HRIS_Role_User_Cakupan')->insert(['Id_Role_User' => $id, 'Jenis_Cakupan' => 'DIVISI', 'Nilai' => (string) $divisi]);
        KeputusanAkses::lupakanCache();

        $cakupan = $this->actingAs($a)->getJson("/api/v1/akses-peran/pengguna/{$t->Id_Users}")->assertOk()->json('result.cakupan_tersedia');
        $this->assertNotEmpty($cakupan);
        $this->assertSame(['KPI'], collect($cakupan)->pluck('modul')->unique()->values()->all(), 'Cakupan tak ditandai modulnya.');
    }

    /** Detail pengguna tidak lagi menjalankan pemeriksaan penuh (±7 query) untuk SETIAP baris grant. */
    public function test_detail_pengguna_query_per_baris_terbatas(): void
    {
        $a = $this->karyawan();
        $this->pegang($a, $this->idRole('KPI_ADMIN'), ['AKSES']);
        $t = $this->karyawan([(int) $a->Id_Users]);
        $hitung = function () use ($a, $t) {
            KeputusanAkses::lupakanCache();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($a)->getJson("/api/v1/akses-peran/pengguna/{$t->Id_Users}")->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };
        $halaman = DB::table('N_HRIS_Grup_Fitur_Map')->where('Id_Grup_Fitur', $this->grup('KPI'))->where('Flag_Aktif', 'Y')
            ->distinct()->limit(6)->pluck('Jenis_page');
        $grant = fn ($p) => DB::table('HRIS_Page_Access')->insert([
            'Kode_Perusahaan' => '001', 'Jenis_page' => $p, 'UserID_Web' => $t->Id_Users, 'ID_Divisi' => 0, 'ID_Level' => 0, 'Flag_Aktif' => 'Y',
        ]);
        $grant($halaman[0]);
        $hitung(); // pemanasan cache skema/mode
        $satu = $hitung();
        $halaman->slice(1)->each($grant);
        $enam = $hitung();

        $this->assertLessThanOrEqual(10, $enam - $satu, "Lima baris grant menambah {$enam} − {$satu} query.");
    }
}
