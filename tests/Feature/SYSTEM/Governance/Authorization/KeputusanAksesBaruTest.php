<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RBAC Fase 2a: penilai aturan BARU (role → N_HRIS_Role_Hak) harus setara
 * dengan aturan LAMA tepat setelah konversi — syarat sebelum mode bayangan.
 */
class KeputusanAksesBaruTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lupakan();
    }

    private function lupakan(): void
    {
        $c = new \ReflectionProperty(User::class, 'menuAccessCache');
        $c->setAccessible(true);
        $c->setValue(null, []);
        KeputusanAkses::lupakanCache();
    }

    private function userBersih(): User
    {
        $id = DB::table('Karyawan as k')->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')
            ->where('k.Aktif', 'Y')
            ->where(fn ($q) => $q->whereNull('u.Is_System_Admin')->orWhere('u.Is_System_Admin', '<>', 'Y'))
            ->whereNotExists(fn ($q) => $q->from('N_HRIS_Role_User as ru')->whereColumn('ru.UserId_Web', 'u.Id_Users'))
            ->orderBy('u.Id_Users')->value('u.Id_Users');

        return User::where('Id_Users', $id)->firstOrFail();
    }

    private function beriRole(User $u, string $kode, ?string $sampai = null): void
    {
        DB::table('N_HRIS_Role_User')->insert([
            'UserId_Web' => $u->Id_Users, 'Id_Role' => DB::table('N_HRIS_Role_Functional')->where('Role_Code', $kode)->value('Id_Role'),
            'Assigned_At' => now(), 'Assigned_By' => 'UJI', 'Flag_Aktif' => 'Y', 'Berlaku_Sampai' => $sampai,
        ]);
        $this->lupakan();
    }

    public function test_setara_aturan_lama_untuk_semua_pemegang_role(): void
    {
        $k = app(KeputusanAkses::class);
        $menus = DB::table('N_HRIS_Menus')->pluck('Jenis_page');
        $ids = DB::table('N_HRIS_Role_User')->where('Flag_Aktif', 'Y')->pluck('UserId_Web')
            ->merge(DB::table('KPI_Users')->where('Is_System_Admin', 'Y')->pluck('Id_Users'))->unique();

        $selisih = [];
        foreach ($ids as $id) {
            $u = User::where('Id_Users', $id)->first();
            if (! $u) {
                continue;
            }
            foreach ($menus as $p) {
                if ($k->bolehBaru($u, (string) $p) !== $k->boleh($u, (string) $p)) {
                    $selisih[] = "#{$id} {$p} lama=".($k->boleh($u, (string) $p) ? 'ya' : 'tidak');
                }
            }
        }

        $this->assertSame([], $selisih, "Aturan BARU berselisih dengan LAMA:\n".implode("\n", array_slice($selisih, 0, 30)));
    }

    public function test_aksi_dari_hak_role_terbaca(): void
    {
        $u = $this->userBersih();
        $this->beriRole($u, 'HCIS_LEADER');
        $baris = DB::table('N_HRIS_Role_Hak as h')->join('N_HRIS_Role_Functional as r', 'r.Id_Role', '=', 'h.Id_Role')
            ->join('N_HRIS_Klasifikasi_Aksi as a', 'a.Id_Klasifikasi_Actions', '=', 'h.Id_Aksi')
            ->where('r.Role_Code', 'HCIS_LEADER')->where('a.Nama_Aksi', '<>', 'VIEW')->first(['h.Jenis_page', 'a.Nama_Aksi']);
        $this->assertNotNull($baris, 'HCIS_LEADER tak punya satu pun aksi selain VIEW hasil konversi.');
        $tanpaCap = DB::table('N_HRIS_Role_Hak as h')->join('N_HRIS_Role_Functional as r', 'r.Id_Role', '=', 'h.Id_Role')
            ->where('r.Role_Code', 'HCIS_LEADER')
            ->whereNotExists(fn ($q) => $q->from('N_HRIS_Menu_Capabilities as mc')->whereColumn('mc.Jenis_page', 'h.Jenis_page'))
            ->value('h.Jenis_page');

        $k = app(KeputusanAkses::class);
        $this->assertTrue($k->bolehBaru($u, $baris->Jenis_page, $baris->Nama_Aksi));
        if ($tanpaCap) {
            $this->assertTrue($k->bolehBaru($u, $tanpaCap, 'VIEW'));
            $this->assertFalse($k->bolehBaru($u, $tanpaCap, 'DELETE'), 'Halaman tanpa capability memberi aksi selain VIEW.');
        }
    }

    public function test_penugasan_kedaluwarsa_tidak_memberi_hak(): void
    {
        $u = $this->userBersih();
        $this->beriRole($u, 'HCIS_LEADER', now()->subDay()->toDateTimeString());

        $this->assertFalse(app(KeputusanAkses::class)->bolehBaru($u, 'IzinPageAdmin'));
    }

    public function test_grant_langsung_kedaluwarsa_tidak_memberi_hak(): void
    {
        $u = $this->userBersih();
        DB::table('HRIS_Page_Access')->insert([
            'Kode_Perusahaan' => '001', 'Jenis_page' => 'IzinPageAdmin', 'UserID_Web' => $u->Id_Users,
            'ID_Divisi' => 0, 'ID_Level' => 0, 'Flag_Aktif' => 'Y', 'Berlaku_Sampai' => now()->subDay(),
        ]);
        $this->lupakan();

        $this->assertFalse(app(KeputusanAkses::class)->bolehBaru($u, 'IzinPageAdmin'));
    }

    public function test_tanpa_model_baru_sama_dengan_lama(): void
    {
        $u = $this->userBersih();
        $this->beriRole($u, 'HCIS_LEADER');
        $k = new class extends KeputusanAkses
        {
            protected function modelBaruTersedia(): bool
            {
                return false;
            }
        };

        $this->assertSame($k->boleh($u, 'IzinPageAdmin'), $k->bolehBaru($u, 'IzinPageAdmin'));
        $this->assertSame($k->boleh($u, 'adminAllPagesAccess'), $k->bolehBaru($u, 'adminAllPagesAccess'));
    }
}
