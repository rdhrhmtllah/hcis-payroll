<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\ModeAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\SinkronHakRole;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RBAC Fase 3a: N_HRIS_Role_Hak mengikuti kondisi LAMA (hibah tersaring Fase 0)
 * selama modulnya belum BARU. Temuan review Fase 2: tanpa ini aturan BARU
 * menyimpang begitu ada hibah/role yang berubah.
 */
class SinkronHakRoleTest extends TestCase
{
    use DatabaseTransactions;

    private int $ess;

    private int $leader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ess = (int) DB::table('N_HRIS_Master_Grup_Fitur')->where('Kode_Grup_Fitur', 'ESS')->value('Id_Grup_Fitur');
        $this->leader = (int) DB::table('N_HRIS_Role_Functional')->where('Role_Code', 'HCIS_LEADER')->value('Id_Role');
        ModeAkses::lupakan();
        KeputusanAkses::lupakanCache();
        app(SinkronHakRole::class)->jalankan(); // mulai dari keadaan sinkron
    }

    private function hakAktif(string $page): bool
    {
        return DB::table('N_HRIS_Role_Hak')->where('Id_Role', $this->leader)->where('Jenis_page', $page)->where('Flag_Aktif', 'Y')->exists();
    }

    private function petakan(string $page, string $hibah = 'Y'): void
    {
        DB::table('N_HRIS_Grup_Fitur_Map')->insert([
            'Id_Grup_Fitur' => $this->ess, 'Jenis_page' => $page, 'Is_Default_Landing' => 'T',
            'Is_Show_Sidebar' => 'T', 'Flag_Aktif' => 'Y', 'Flag_Hibah_Role' => $hibah,
        ]);
    }

    public function test_hibah_baru_ditambahkan_dan_dimatikan_dinonaktifkan(): void
    {
        $this->petakan('ujiSinkronPage');

        $h = app(SinkronHakRole::class)->jalankan();
        $this->assertGreaterThan(0, $h['tambah']);
        $this->assertTrue($this->hakAktif('ujiSinkronPage'));

        DB::table('N_HRIS_Grup_Fitur_Map')->where('Jenis_page', 'ujiSinkronPage')->update(['Flag_Hibah_Role' => 'T']);
        $h = app(SinkronHakRole::class)->jalankan();
        $this->assertGreaterThan(0, $h['nonaktif']);
        $this->assertFalse($this->hakAktif('ujiSinkronPage'));
        $this->assertTrue(DB::table('N_HRIS_Role_Hak')->where('Jenis_page', 'ujiSinkronPage')->exists(), 'Baris dihapus, bukan dinonaktifkan.');

        DB::table('N_HRIS_Grup_Fitur_Map')->where('Jenis_page', 'ujiSinkronPage')->update(['Flag_Hibah_Role' => 'Y']);
        $h = app(SinkronHakRole::class)->jalankan();
        $this->assertGreaterThan(0, $h['aktifkan']);
        $this->assertTrue($this->hakAktif('ujiSinkronPage'));
    }

    public function test_idempoten(): void
    {
        $this->assertSame(['tambah' => 0, 'aktifkan' => 0, 'nonaktif' => 0], app(SinkronHakRole::class)->jalankan());
    }

    public function test_modul_baru_tidak_disentuh(): void
    {
        DB::table('N_HRIS_Akses_Mode_Modul')->updateOrInsert(['Id_Grup_Fitur' => $this->ess], ['Mode' => 'BARU']);
        ModeAkses::lupakan();
        $this->petakan('ujiSinkronBaruPage');

        app(SinkronHakRole::class)->jalankan();

        $this->assertFalse($this->hakAktif('ujiSinkronBaruPage'), 'Sinkron menimpa modul yang sudah BARU.');
    }

    public function test_setelah_sinkron_aturan_baru_setara_lama(): void
    {
        $this->petakan('ujiSinkronSetaraPage');
        app(SinkronHakRole::class)->jalankan();
        $uid = (int) DB::table('N_HRIS_Role_User')->where('Id_Role', $this->leader)->where('Flag_Aktif', 'Y')->value('UserId_Web');
        $u = User::where('Id_Users', $uid)->firstOrFail();
        $c = new \ReflectionProperty(User::class, 'menuAccessCache');
        $c->setAccessible(true);
        $c->setValue(null, []);
        KeputusanAkses::lupakanCache();

        $k = app(KeputusanAkses::class);
        $this->assertTrue($k->boleh($u, 'ujiSinkronSetaraPage'));
        $this->assertTrue($k->bolehBaru($u, 'ujiSinkronSetaraPage'));
    }

    public function test_sinkron_bersamaan_ditolak(): void
    {
        $kunci = \Illuminate\Support\Facades\Cache::lock('rbac:sinkron-hak', 60);
        $this->assertTrue($kunci->get());
        try {
            $this->expectException(\App\Services\SYSTEM\Governance\Authorization\Akses\SinkronSedangBerjalan::class);
            app(SinkronHakRole::class)->jalankan();
        } finally {
            $kunci->release();
        }
    }

    public function test_perintah_kering_tidak_mengubah(): void
    {
        $this->petakan('ujiSinkronKeringPage');

        Artisan::call('rbac:sinkron-hak', ['--kering' => true]);

        $this->assertStringContainsString('tambah', Artisan::output());
        $this->assertFalse($this->hakAktif('ujiSinkronKeringPage'));
    }
}
