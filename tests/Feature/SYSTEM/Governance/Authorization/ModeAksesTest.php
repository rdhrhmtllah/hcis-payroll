<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\ModeAkses;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RBAC Fase 3a: sakelar per modul. LAMA = aturan lama; BAYANGAN = lama
 * memutuskan + selisih dicatat; BARU = aturan baru memutuskan.
 * Skenario: hak VIEW IzinPageAdmin milik HCIS_LEADER dinonaktifkan di model
 * baru, sehingga lama = boleh, baru = tidak.
 */
class ModeAksesTest extends TestCase
{
    use DatabaseTransactions;

    private User $u;

    private int $ess;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ess = (int) DB::table('N_HRIS_Master_Grup_Fitur')->where('Kode_Grup_Fitur', 'ESS')->value('Id_Grup_Fitur');
        $id = DB::table('Karyawan as k')->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')
            ->where('k.Aktif', 'Y')
            ->where(fn ($q) => $q->whereNull('u.Is_System_Admin')->orWhere('u.Is_System_Admin', '<>', 'Y'))
            ->whereNotExists(fn ($q) => $q->from('N_HRIS_Role_User as ru')->whereColumn('ru.UserId_Web', 'u.Id_Users'))
            ->whereNotExists(fn ($q) => $q->from('HRIS_Page_Access as pa')->whereColumn('pa.UserID_Web', 'u.Id_Users')->where('pa.Jenis_page', 'IzinPageAdmin'))
            ->orderBy('u.Id_Users')->value('u.Id_Users');
        $this->u = User::where('Id_Users', $id)->firstOrFail();
        $leader = DB::table('N_HRIS_Role_Functional')->where('Role_Code', 'HCIS_LEADER')->value('Id_Role');
        DB::table('N_HRIS_Role_User')->insert([
            'UserId_Web' => $id, 'Id_Role' => $leader, 'Assigned_At' => now(), 'Assigned_By' => 'UJI', 'Flag_Aktif' => 'Y',
        ]);
        DB::table('N_HRIS_Role_Hak')->where('Id_Role', $leader)->where('Jenis_page', 'IzinPageAdmin')->update(['Flag_Aktif' => 'T']);
        $this->lupakan();
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

    private function selisih(): ?object
    {
        return DB::table('N_HRIS_Akses_Selisih')->where('Id_User', $this->u->Id_Users)->where('Jenis_page', 'IzinPageAdmin')->first();
    }

    public function test_lama_memakai_aturan_lama_tanpa_mencatat(): void
    {
        $this->assertTrue(app(KeputusanAkses::class)->boleh($this->u, 'IzinPageAdmin'));
        $this->assertNull($this->selisih());
    }

    public function test_bayangan_tetap_lama_dan_mencatat_selisih(): void
    {
        $this->setMode('BAYANGAN');

        $this->assertTrue(app(KeputusanAkses::class)->boleh($this->u, 'IzinPageAdmin'));
        $this->assertNull($this->selisih(), 'Selisih ditulis di jalur keputusan, bukan setelah respons.');
        KeputusanAkses::kirimSelisih(DB::transactionLevel());
        $s = $this->selisih();
        $this->assertNotNull($s, 'Selisih tidak tercatat di mode BAYANGAN.');
        $this->assertSame(['Y', 'T'], [$s->Keputusan_Lama, $s->Keputusan_Baru]);

        // Request berikutnya dalam 10 menit: tidak ditulis ulang.
        KeputusanAkses::lupakanCache();
        app(KeputusanAkses::class)->boleh($this->u, 'IzinPageAdmin');
        KeputusanAkses::kirimSelisih(DB::transactionLevel());
        $this->assertSame(1, (int) $this->selisih()->Jumlah);
    }

    public function test_bayangan_tidak_menulis_di_dalam_transaksi(): void
    {
        $this->setMode('BAYANGAN');
        app(KeputusanAkses::class)->boleh($this->u, 'IzinPageAdmin');

        DB::transaction(fn () => KeputusanAkses::kirimSelisih());

        $this->assertNull($this->selisih());
    }

    public function test_bayangan_tanpa_beda_tidak_mencatat(): void
    {
        $this->setMode('BAYANGAN');

        app(KeputusanAkses::class)->boleh($this->u, 'IzinPage');

        $this->assertSame(0, DB::table('N_HRIS_Akses_Selisih')->where('Id_User', $this->u->Id_Users)->where('Jenis_page', 'IzinPage')->count());
    }

    public function test_baru_memakai_aturan_baru(): void
    {
        $this->setMode('BARU');

        $this->assertFalse(app(KeputusanAkses::class)->boleh($this->u, 'IzinPageAdmin'), 'BARU masih memakai aturan lama.');
        $this->assertTrue(app(KeputusanAkses::class)->boleh($this->u, 'IzinPage'), 'Hak role yang aktif tidak terbaca di BARU.');
    }

    public function test_system_admin_tetap_boleh_di_mode_baru(): void
    {
        $this->setMode('BARU');
        $this->assertFalse(app(KeputusanAkses::class)->boleh($this->u, 'systemAuthManagerPage'), 'Prasyarat: non-SA ditolak.');
        DB::table('KPI_Users')->where('Id_Users', $this->u->Id_Users)->update(['Is_System_Admin' => 'Y']);
        $sa = User::where('Id_Users', $this->u->Id_Users)->firstOrFail();

        $this->assertTrue(app(KeputusanAkses::class)->boleh($sa, 'systemAuthManagerPage'));
    }

    public function test_modul_lain_tak_terpengaruh(): void
    {
        $this->setMode('BARU'); // hanya ESS
        $kpiPage = DB::table('N_HRIS_Grup_Fitur_Map as g')->join('N_HRIS_Master_Grup_Fitur as m', 'm.Id_Grup_Fitur', '=', 'g.Id_Grup_Fitur')
            ->where('m.Kode_Grup_Fitur', 'KPI')->value('g.Jenis_page');

        $this->assertSame(ModeAkses::LAMA, app(ModeAkses::class)->modeHalaman($kpiPage));
        $this->assertSame(ModeAkses::BARU, app(ModeAkses::class)->modeHalaman('IzinPageAdmin'));
    }
}
