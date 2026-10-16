<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Akses\AksesDitolak;
use App\Services\SYSTEM\Governance\Authorization\Akses\AturanDelegasi;
use App\Services\SYSTEM\Governance\Authorization\Akses\ModeAkses;
use App\Services\SYSTEM\Governance\Authorization\Scope\CakupanKaryawan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** RBAC Fase 3b: aturan anti-eskalasi & cakupan sebagai kode. */
class AturanDelegasiTest extends TestCase
{
    use DatabaseTransactions;

    private function karyawan(array $kecuali = [], ?\Closure $saring = null): User
    {
        $q = DB::table('Karyawan as k')->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')->where('k.Aktif', 'Y')
            ->where(fn ($w) => $w->whereNull('u.Is_System_Admin')->orWhere('u.Is_System_Admin', '<>', 'Y'))
            ->whereNotExists(fn ($w) => $w->from('N_HRIS_Role_User as ru')->whereColumn('ru.UserId_Web', 'u.Id_Users'))
            ->whereNotIn('u.Id_Users', $kecuali ?: [0]);
        if ($saring) {
            $saring($q);
        }

        return User::where('Id_Users', $q->orderBy('u.Id_Users')->value('u.Id_Users'))->firstOrFail();
    }

    private function idRole(string $kode): int
    {
        return (int) DB::table('N_HRIS_Role_Functional')->where('Role_Code', $kode)->value('Id_Role');
    }

    private function adminKpi(array $cakupan = []): User
    {
        $u = $this->karyawan();
        $id = DB::table('N_HRIS_Role_User')->insertGetId([
            'UserId_Web' => $u->Id_Users, 'Id_Role' => $this->idRole('KPI_ADMIN'),
            'Assigned_At' => now(), 'Assigned_By' => 'UJI', 'Flag_Aktif' => 'Y',
        ], 'Id');
        // Sesuai konversi DML-2026-166: Menu Administrator = tanggung jawab AKSES.
        DB::table('N_HRIS_Role_User_Tanggung_Jawab')->insert(['Id_Role_User' => $id, 'Kode' => 'AKSES']);
        foreach ($cakupan as [$jenis, $nilai]) {
            DB::table('N_HRIS_Role_User_Cakupan')->insert(['Id_Role_User' => $id, 'Jenis_Cakupan' => $jenis, 'Nilai' => $nilai]);
        }

        return $u;
    }

    private function aturan(): AturanDelegasi
    {
        return app(AturanDelegasi::class);
    }

    private function ditolak(callable $f, string $pesan): void
    {
        try {
            $f();
            $this->fail("Seharusnya ditolak: {$pesan}");
        } catch (AksesDitolak $e) {
            $this->assertStringContainsString($pesan, $e->getMessage());
        }
    }

    private function kpiAkses(): int
    {
        // Peran KPI berjenis AKSES (bukan admin); bila tak ada, buat sementara.
        $id = DB::table('N_HRIS_Role_Functional as r')->join('N_HRIS_Master_Grup_Fitur as g', 'g.Id_Grup_Fitur', '=', 'r.Id_Grup_Fitur')
            ->where('g.Kode_Grup_Fitur', 'KPI')->where('r.Jenis_Role', 'AKSES')->where('r.Flag_Aktif', 'Y')->value('r.Id_Role');

        return (int) ($id ?: DB::table('N_HRIS_Role_Functional')->insertGetId([
            'Role_Code' => 'UJI_KPI_AKSES', 'Role_Name' => 'Uji KPI', 'Flag_Aktif' => 'Y', 'Created_At' => now(), 'Jenis_Role' => 'AKSES',
            'Id_Grup_Fitur' => DB::table('N_HRIS_Master_Grup_Fitur')->where('Kode_Grup_Fitur', 'KPI')->value('Id_Grup_Fitur'),
        ], 'Id_Role'));
    }

    public function test_tidak_bisa_mengubah_diri_sendiri(): void
    {
        $a = $this->adminKpi();
        $this->ditolak(fn () => $this->aturan()->periksaPenugasan($a, $this->kpiAkses(), (int) $a->Id_Users, []), 'diri sendiri');
    }

    public function test_peran_admin_hanya_system_admin(): void
    {
        $a = $this->adminKpi();
        $t = $this->karyawan([(int) $a->Id_Users]);
        // Admin modul tak mengangkat peran setingkat admin (tingkat peran, DDL-2026-181).
        $this->ditolak(fn () => $this->aturan()->periksaPenugasan($a, $this->idRole('KPI_SUPER_ADMIN'), (int) $t->Id_Users, []), 'hanya dapat ditugaskan tingkat di atasnya');

        $sa = User::where('Id_Users', 4)->firstOrFail();
        $this->aturan()->periksaPenugasan($sa, $this->idRole('KPI_SUPER_ADMIN'), (int) $t->Id_Users, [], ['AKSES']);
        $this->addToAssertionCount(1);
    }

    public function test_peran_modul_lain_ditolak(): void
    {
        $a = $this->adminKpi();
        $t = $this->karyawan([(int) $a->Id_Users]);
        $this->ditolak(fn () => $this->aturan()->periksaPenugasan($a, $this->idRole('HCIS_LEADER'), (int) $t->Id_Users, []), 'di luar modul');
    }

    public function test_admin_tanpa_batas_boleh_peran_modulnya(): void
    {
        $a = $this->adminKpi();
        $t = $this->karyawan([(int) $a->Id_Users]);

        $this->aturan()->periksaPenugasan($a, $this->kpiAkses(), (int) $t->Id_Users, []);
        $this->addToAssertionCount(1);
    }

    public function test_admin_phl_hanya_karyawan_phl_dan_cakupan_phl(): void
    {
        $a = $this->adminKpi([['PHL', null]]);
        $bukanPhl = $this->karyawan([(int) $a->Id_Users], fn ($q) => $q->whereNotExists(fn ($w) => $w->from('View_Golongan_Sub_Golongan_Level_Jabatan as g')
            ->whereColumn('g.ID_Level_Jabatan', 'k.ID_Level_Jabatan')->where('g.Id_Level', 21)));
        $phl = $this->karyawan([(int) $a->Id_Users], fn ($q) => $q->whereExists(fn ($w) => $w->from('View_Golongan_Sub_Golongan_Level_Jabatan as g')
            ->whereColumn('g.ID_Level_Jabatan', 'k.ID_Level_Jabatan')->where('g.Id_Level', 21)));

        $this->ditolak(fn () => $this->aturan()->periksaPenugasan($a, $this->kpiAkses(), (int) $bukanPhl->Id_Users, [['jenis' => 'PHL', 'nilai' => null]]), 'di luar cakupan');
        $this->ditolak(fn () => $this->aturan()->periksaPenugasan($a, $this->kpiAkses(), (int) $phl->Id_Users, []), 'tanpa batas');
        $this->ditolak(fn () => $this->aturan()->periksaPenugasan($a, $this->kpiAkses(), (int) $phl->Id_Users, [['jenis' => 'DIVISI', 'nilai' => null]]), 'melebihi');
        $this->aturan()->periksaPenugasan($a, $this->kpiAkses(), (int) $phl->Id_Users, [['jenis' => 'PHL', 'nilai' => null]]);
    }

    public function test_grant_halaman_tata_kelola_dan_modul_lain_ditolak(): void
    {
        $a = $this->adminKpi();
        $t = $this->karyawan([(int) $a->Id_Users]);
        $this->ditolak(fn () => $this->aturan()->periksaGrant($a, 'ManajemenAksesDivisi', (int) $t->Id_Users), '');
        $essBebas = DB::table('N_HRIS_Grup_Fitur_Map as m')->join('N_HRIS_Master_Grup_Fitur as g', 'g.Id_Grup_Fitur', '=', 'm.Id_Grup_Fitur')
            ->join('N_HRIS_Menus as mn', 'mn.Jenis_page', '=', 'm.Jenis_page')
            ->where('g.Kode_Grup_Fitur', 'ESS')->whereNotIn('m.Jenis_page', (array) config('authorization.tata_kelola.halaman'))
            ->whereNotExists(fn ($w) => $w->from('N_HRIS_Menu_Delegation_Policy as p')->whereColumn('p.Jenis_page', 'm.Jenis_page')->where('p.Policy_Mode', 'SYSTEM_ONLY'))
            ->value('m.Jenis_page');
        $this->assertNotNull($essBebas);
        $this->ditolak(fn () => $this->aturan()->periksaGrant($a, $essBebas, (int) $t->Id_Users), 'di luar modul');
        $this->ditolak(fn () => $this->aturan()->periksaGrant($a, 'IzinPageAdmin', (int) $t->Id_Users), 'di luar modul');
        $this->aturan()->periksaGrant($a, 'MasterFormula', (int) $t->Id_Users);
    }

    public function test_ubah_hak_hanya_modul_baru(): void
    {
        $sa = User::where('Id_Users', 4)->firstOrFail();
        $this->ditolak(fn () => $this->aturan()->periksaUbahHak($sa, $this->kpiAkses(), [], []), 'BARU');

        $kpi = (int) DB::table('N_HRIS_Master_Grup_Fitur')->where('Kode_Grup_Fitur', 'KPI')->value('Id_Grup_Fitur');
        DB::table('N_HRIS_Akses_Mode_Modul')->updateOrInsert(['Id_Grup_Fitur' => $kpi], ['Mode' => 'BARU']);
        ModeAkses::lupakan();
        // Matriks hanya Super Admin modul (master tingkat peran); admin modul ditolak.
        $this->ditolak(fn () => $this->aturan()->periksaUbahHak($this->adminKpi(), $this->kpiAkses(), [['jenis_page' => 'MasterFormula', 'aksi' => ['VIEW']]], []), 'Super Admin modul');
        $s = $this->karyawan();
        $id = DB::table('N_HRIS_Role_User')->insertGetId([
            'UserId_Web' => $s->Id_Users, 'Id_Role' => $this->idRole('KPI_SUPER_ADMIN'), 'Assigned_At' => now(), 'Assigned_By' => 'UJI', 'Flag_Aktif' => 'Y',
        ], 'Id');
        DB::table('N_HRIS_Role_User_Tanggung_Jawab')->insert(['Id_Role_User' => $id, 'Kode' => 'AKSES']);
        \App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses::lupakanCache();
        $this->aturan()->periksaUbahHak($s, $this->kpiAkses(), [['jenis_page' => 'MasterFormula', 'aksi' => ['VIEW']]], []);
        $this->ditolak(fn () => $this->aturan()->periksaUbahHak($s, $this->kpiAkses(), [['jenis_page' => 'ManajemenAksesDivisi', 'aksi' => ['VIEW']]], []), 'System Admin');
    }

    public function test_cakupan_sebagai_kode(): void
    {
        $c = app(CakupanKaryawan::class);
        $p = $this->karyawan();
        $profil = DB::table('Karyawan as k')->join('View_Divisi_Sub_Divisi as d', 'd.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->where('k.UserID_Web', $p->Id_Users)->first(['d.ID_Divisi', 'k.Lokasi']);
        $sama = $this->karyawan([(int) $p->Id_Users], fn ($q) => $q->join('View_Divisi_Sub_Divisi as d2', 'd2.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')->where('d2.ID_Divisi', $profil->ID_Divisi));
        $beda = $this->karyawan([(int) $p->Id_Users], fn ($q) => $q->join('View_Divisi_Sub_Divisi as d3', 'd3.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')->where('d3.ID_Divisi', '<>', $profil->ID_Divisi));

        $this->assertTrue($c->mencakup('SEMUA', null, (int) $p->Id_Users, (int) $beda->Id_Users));
        $this->assertTrue($c->mencakup('DIVISI', null, (int) $p->Id_Users, (int) $sama->Id_Users));
        $this->assertFalse($c->mencakup('DIVISI', null, (int) $p->Id_Users, (int) $beda->Id_Users));
        $this->assertFalse($c->mencakup('ENTITAS', 'X', (int) $p->Id_Users, (int) $sama->Id_Users), 'Jenis tanpa sumber data harus gagal tertutup.');
        $this->assertFalse($c->mencakup('ACAK', null, (int) $p->Id_Users, (int) $sama->Id_Users));
    }
}
