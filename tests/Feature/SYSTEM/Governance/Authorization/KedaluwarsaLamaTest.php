<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RBAC Fase 3b: Berlaku_Sampai berlaku juga di aturan LAMA. Hanya baris
 * bertanggal yang terpengaruh; data lama (NULL) tetap seperti semula.
 */
class KedaluwarsaLamaTest extends TestCase
{
    use DatabaseTransactions;

    private function user(): User
    {
        $c = new \ReflectionProperty(User::class, 'menuAccessCache');
        $c->setAccessible(true);
        $c->setValue(null, []);
        $id = DB::table('Karyawan as k')->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')->where('k.Aktif', 'Y')
            ->where(fn ($q) => $q->whereNull('u.Is_System_Admin')->orWhere('u.Is_System_Admin', '<>', 'Y'))
            ->whereNotExists(fn ($q) => $q->from('N_HRIS_Role_User as ru')->whereColumn('ru.UserId_Web', 'u.Id_Users'))
            ->whereNotExists(fn ($q) => $q->from('HRIS_Page_Access as pa')->whereColumn('pa.UserID_Web', 'u.Id_Users')->where('pa.Jenis_page', 'IzinPageAdmin'))
            ->orderBy('u.Id_Users')->value('u.Id_Users');

        return User::where('Id_Users', $id)->firstOrFail();
    }

    private function role(User $u, ?string $sampai): void
    {
        DB::table('N_HRIS_Role_User')->insert([
            'UserId_Web' => $u->Id_Users, 'Id_Role' => DB::table('N_HRIS_Role_Functional')->where('Role_Code', 'HCIS_LEADER')->value('Id_Role'),
            'Assigned_At' => now(), 'Assigned_By' => 'UJI', 'Flag_Aktif' => 'Y', 'Berlaku_Sampai' => $sampai,
        ]);
    }

    private function grant(User $u, ?string $sampai): void
    {
        DB::table('HRIS_Page_Access')->insert([
            'Kode_Perusahaan' => '001', 'Jenis_page' => 'IzinPageAdmin', 'UserID_Web' => $u->Id_Users,
            'ID_Divisi' => 0, 'ID_Level' => 0, 'Flag_Aktif' => 'Y', 'Berlaku_Sampai' => $sampai,
        ]);
    }

    public function test_penugasan_kedaluwarsa_ditolak_aturan_lama(): void
    {
        $u = $this->user();
        $this->role($u, now()->subDay()->toDateTimeString());

        $this->assertFalse($u->memilikiJabatan('IzinPageAdmin'));
        $this->assertSame(['boleh' => false, 'mode' => 'LAMA', 'jalur' => []], app(KeputusanAkses::class)->telusuri($u, 'IzinPageAdmin'));
    }

    public function test_grant_kedaluwarsa_ditolak_aturan_lama(): void
    {
        $u = $this->user();
        $this->grant($u, now()->subDay()->toDateTimeString());

        $this->assertFalse($u->memilikiJabatan('IzinPageAdmin'));
    }

    public function test_belum_kedaluwarsa_dan_tanpa_tanggal_tetap_berlaku(): void
    {
        $u = $this->user();
        $this->role($u, now()->addDay()->toDateTimeString());
        $this->assertTrue($u->memilikiJabatan('IzinPageAdmin'));

        $v = $this->user();
        $this->grant($v, null);
        $this->assertTrue($v->memilikiJabatan('IzinPageAdmin'));
    }
}
