<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Akses\AuditAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\ModeAkses;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** RBAC Fase 3a: tab Katalog — mode per modul, selisih, sinkron. Khusus System Admin. */
class KatalogAksesTest extends TestCase
{
    use DatabaseTransactions;

    private int $ess;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');
        $this->ess = (int) DB::table('N_HRIS_Master_Grup_Fitur')->where('Kode_Grup_Fitur', 'ESS')->value('Id_Grup_Fitur');
        ModeAkses::lupakan();
        KeputusanAkses::lupakanCache();
    }

    private function sa(): User
    {
        return User::where('Id_Users', 4)->firstOrFail();
    }

    private function adminModul(): User
    {
        $id = DB::table('Karyawan as k')->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')->where('k.Aktif', 'Y')
            ->where(fn ($q) => $q->whereNull('u.Is_System_Admin')->orWhere('u.Is_System_Admin', '<>', 'Y'))
            ->whereNotExists(fn ($q) => $q->from('N_HRIS_Role_User as ru')->whereColumn('ru.UserId_Web', 'u.Id_Users'))
            ->orderBy('u.Id_Users')->value('u.Id_Users');
        DB::table('N_HRIS_Role_User')->insert([
            'UserId_Web' => $id, 'Id_Role' => DB::table('N_HRIS_Role_Functional')->where('Role_Code', 'KPI_MENU_ADMINISTRATOR')->value('Id_Role'),
            'Assigned_At' => now(), 'Assigned_By' => 'UJI', 'Flag_Aktif' => 'Y',
        ]);

        return User::where('Id_Users', $id)->firstOrFail();
    }

    public function test_admin_modul_ditolak(): void
    {
        $u = $this->adminModul();

        $this->actingAs($u)->getJson('/api/v1/akses-peran/katalog')->assertForbidden();
        $this->actingAs($u)->putJson("/api/v1/akses-peran/katalog/{$this->ess}/mode", ['mode' => 'BARU', 'alasan' => 'coba eskalasi'])->assertForbidden();
        $this->actingAs($u)->postJson('/api/v1/akses-peran/sinkron')->assertForbidden();
        $this->actingAs($u)->getJson('/api/v1/akses-peran/selisih')->assertForbidden();
        $this->actingAs($u)->postJson('/api/v1/akses-peran/selisih/bersihkan', ['modul' => $this->ess, 'alasan' => 'coba bersihkan'])->assertForbidden();
    }

    private function modeEss(): ?string
    {
        return DB::table('N_HRIS_Akses_Mode_Modul')->where('Id_Grup_Fitur', $this->ess)->value('Mode');
    }

    private function keBaru(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->sa())->putJson("/api/v1/akses-peran/katalog/{$this->ess}/mode", ['mode' => 'BARU', 'alasan' => 'pilot mode baru']);
    }

    /** RBAC Fase 4: BARU hanya lewat BAYANGAN — selisihnya harus sempat terlihat dulu. */
    public function test_baru_langsung_dari_lama_ditolak(): void
    {
        DB::table('N_HRIS_Akses_Mode_Modul')->where('Id_Grup_Fitur', $this->ess)->delete();
        ModeAkses::lupakan();

        $this->keBaru()->assertStatus(422)->assertJsonPath('message', 'Pindahkan modul ke BAYANGAN dulu dan tinjau selisihnya sebelum BARU.');
        $this->assertNotSame('BARU', $this->modeEss());
    }

    /** RBAC Fase 4: hak peran disamakan dengan hibah terakhir sebelum BARU berlaku. */
    public function test_baru_dari_bayangan_didahului_sinkron(): void
    {
        DB::table('N_HRIS_Akses_Mode_Modul')->updateOrInsert(['Id_Grup_Fitur' => $this->ess], ['Mode' => 'BAYANGAN']);
        ModeAkses::lupakan();
        $leader = DB::table('N_HRIS_Role_Functional')->where('Role_Code', 'HCIS_LEADER')->value('Id_Role');
        $hak = fn () => DB::table('N_HRIS_Role_Hak')->where('Id_Role', $leader)->where('Jenis_page', 'IzinPageAdmin')->where('Flag_Aktif', 'Y')->count();
        DB::table('N_HRIS_Role_Hak')->where('Id_Role', $leader)->where('Jenis_page', 'IzinPageAdmin')->update(['Flag_Aktif' => 'T']);
        $this->assertSame(0, $hak());

        $this->keBaru()->assertOk();

        $this->assertSame('BARU', $this->modeEss());
        $this->assertGreaterThan(0, $hak(), 'Hak dari hibah tidak disinkronkan sebelum BARU.');
    }

    public function test_baru_ditunda_bila_sinkron_sedang_berjalan(): void
    {
        DB::table('N_HRIS_Akses_Mode_Modul')->updateOrInsert(['Id_Grup_Fitur' => $this->ess], ['Mode' => 'BAYANGAN']);
        ModeAkses::lupakan();
        $kunci = \Illuminate\Support\Facades\Cache::lock('rbac:sinkron-hak', 60);
        $this->assertTrue($kunci->get());
        try {
            $this->keBaru()->assertStatus(409);
        } finally {
            $kunci->release();
        }
        $this->assertSame('BAYANGAN', $this->modeEss());
    }

    public function test_ubah_mode_tanpa_perubahan_tidak_diaudit(): void
    {
        $awal = (int) DB::table(AuditAkses::TABEL)->max('Id_Akses_Audit');

        $this->actingAs($this->sa())->putJson("/api/v1/akses-peran/katalog/{$this->ess}/mode", ['mode' => 'LAMA', 'alasan' => 'tetap lama saja'])->assertOk();

        $this->assertSame(0, DB::table(AuditAkses::TABEL)->where('Id_Akses_Audit', '>', $awal)->where('Aksi', 'UBAH_MODE')->count());
    }

    public function test_bersihkan_hanya_yang_sudah_terlihat(): void
    {
        DB::table('N_HRIS_Akses_Selisih')->insert([
            ['Id_User' => 4, 'Jenis_page' => 'IzinPageAdmin', 'Aksi' => 'VIEW', 'Keputusan_Lama' => 'Y', 'Keputusan_Baru' => 'T', 'Terakhir_At' => now()->subMinutes(5)],
            ['Id_User' => 4, 'Jenis_page' => 'IzinPage', 'Aksi' => 'VIEW', 'Keputusan_Lama' => 'Y', 'Keputusan_Baru' => 'T', 'Terakhir_At' => now()->addMinutes(5)],
        ]);

        $this->actingAs($this->sa())->postJson('/api/v1/akses-peran/selisih/bersihkan', [
            'modul' => $this->ess, 'alasan' => 'sudah ditinjau', 'sampai' => now()->toDateTimeString(),
        ])->assertOk();

        $this->assertSame(0, DB::table('N_HRIS_Akses_Selisih')->where('Id_User', 4)->where('Jenis_page', 'IzinPageAdmin')->count());
        $this->assertSame(1, DB::table('N_HRIS_Akses_Selisih')->where('Id_User', 4)->where('Jenis_page', 'IzinPage')->count(), 'Selisih yang belum terlihat ikut terhapus.');
    }

    public function test_katalog_memuat_modul_dengan_mode_bawaan_lama(): void
    {
        $r = $this->actingAs($this->sa())->getJson('/api/v1/akses-peran/katalog')->assertOk()->json('result');

        $ess = collect($r)->firstWhere('kode', 'ESS');
        $this->assertSame('LAMA', $ess['mode']);
        $this->assertArrayHasKey('selisih', $ess);
    }

    public function test_ubah_mode_tercatat_dan_divalidasi(): void
    {
        $url = "/api/v1/akses-peran/katalog/{$this->ess}/mode";
        $this->actingAs($this->sa())->putJson($url, ['mode' => 'ACAK', 'alasan' => 'uji validasi'])->assertStatus(422);
        $this->actingAs($this->sa())->putJson($url, ['mode' => 'BAYANGAN'])->assertStatus(422);

        $this->actingAs($this->sa())->putJson($url, ['mode' => 'BAYANGAN', 'alasan' => 'uji mode bayangan'])->assertOk();

        $this->assertSame(\App\Services\SYSTEM\Governance\Authorization\Akses\ModeAkses::BAYANGAN, app(ModeAkses::class)->modeModul($this->ess), 'Cache mode lintas-request tidak dibuang.');
        $this->assertSame('BAYANGAN', DB::table('N_HRIS_Akses_Mode_Modul')->where('Id_Grup_Fitur', $this->ess)->value('Mode'));
        $a = DB::table(AuditAkses::TABEL)->where('Aksi', 'UBAH_MODE')->orderByDesc('Id_Akses_Audit')->first();
        $this->assertNotNull($a);
        $this->assertSame('uji mode bayangan', $a->Alasan);
        $this->assertSame('MODE', $a->Subjek_Jenis);
    }

    public function test_selisih_terdaftar_dan_bisa_dibersihkan(): void
    {
        DB::table('N_HRIS_Akses_Selisih')->insert([
            'Id_User' => 4, 'Jenis_page' => 'IzinPageAdmin', 'Aksi' => 'VIEW', 'Keputusan_Lama' => 'Y', 'Keputusan_Baru' => 'T',
        ]);

        $baris = $this->actingAs($this->sa())->getJson("/api/v1/akses-peran/selisih?modul={$this->ess}")->assertOk()->json('result.data');
        $this->assertContains('IzinPageAdmin', array_column($baris, 'jenis_page'));

        $this->actingAs($this->sa())->postJson('/api/v1/akses-peran/selisih/bersihkan', ['modul' => $this->ess, 'alasan' => 'sudah ditinjau'])->assertOk();
        $this->assertSame(0, DB::table('N_HRIS_Akses_Selisih')->where('Jenis_page', 'IzinPageAdmin')->where('Id_User', 4)->count());
    }

    public function test_sinkron_dari_layar(): void
    {
        $r = $this->actingAs($this->sa())->postJson('/api/v1/akses-peran/sinkron')->assertOk()->json('result');

        $this->assertSame(['tambah', 'aktifkan', 'nonaktif'], array_keys($r));
    }

    /**
     * Review Fase 4 (I1): sejak modul pernah BARU, matriks hak milik admin.
     * Mundur ke BAYANGAN lalu maju lagi tidak boleh menimpanya dengan hibah —
     * baik oleh sinkron terjadwal maupun sinkron terakhir sebelum BARU.
     */
    public function test_matriks_dipertahankan_saat_mundur_dari_baru(): void
    {
        DB::table('N_HRIS_Akses_Mode_Modul')->updateOrInsert(['Id_Grup_Fitur' => $this->ess], ['Mode' => 'BAYANGAN']);
        ModeAkses::lupakan();
        $this->keBaru()->assertOk();
        $leader = DB::table('N_HRIS_Role_Functional')->where('Role_Code', 'HCIS_LEADER')->value('Id_Role');
        $hak = fn () => DB::table('N_HRIS_Role_Hak')->where('Id_Role', $leader)->where('Jenis_page', 'IzinPageAdmin')->where('Flag_Aktif', 'Y')->count();
        DB::table('N_HRIS_Role_Hak')->where('Id_Role', $leader)->where('Jenis_page', 'IzinPageAdmin')->update(['Flag_Aktif' => 'T']);

        $this->actingAs($this->sa())->putJson("/api/v1/akses-peran/katalog/{$this->ess}/mode", ['mode' => 'BAYANGAN', 'alasan' => 'mundur sementara'])->assertOk();
        app(\App\Services\SYSTEM\Governance\Authorization\Akses\SinkronHakRole::class)->jalankan();
        $this->assertSame(0, $hak(), 'Sinkron terjadwal menimpa matriks modul yang pernah BARU.');

        $this->keBaru()->assertOk();
        $this->assertSame(0, $hak(), 'Sinkron sebelum BARU menimpa matriks modul yang pernah BARU.');
    }

    /** RBAC Fase 5 (5c): laporan kesiapan pensiun per modul — sinkron kering, tanpa menulis. */
    public function test_kesiapan_per_modul_tanpa_menulis(): void
    {
        DB::table('N_HRIS_Akses_Mode_Modul')->updateOrInsert(['Id_Grup_Fitur' => $this->ess], ['Mode' => 'BAYANGAN']);
        DB::table('N_HRIS_Akses_Mode_Modul')->updateOrInsert(['Id_Grup_Fitur' => 7], ['Mode' => 'BARU']);
        ModeAkses::lupakan();
        $leader = DB::table('N_HRIS_Role_Functional')->where('Role_Code', 'HCIS_LEADER')->value('Id_Role');
        DB::table('N_HRIS_Role_Hak')->where('Id_Role', $leader)->where('Jenis_page', 'IzinPageAdmin')->update(['Flag_Aktif' => 'T']);

        $baris = collect($this->actingAs($this->sa())->getJson('/api/v1/akses-peran/kesiapan')->assertOk()->json('result'))->keyBy('id');

        $ess = $baris[$this->ess];
        $this->assertSame('BAYANGAN', $ess['mode']);
        $this->assertGreaterThan(0, $ess['beda_hak'], 'Hak peran yang menyimpang dari hibah tidak terhitung.');
        $this->assertSame('PERLU_SINKRON', $ess['status']);
        $this->assertGreaterThan(0, $ess['halaman']);
        $this->assertSame('BARU', $baris[7]['status']);
        $this->assertNull($baris[7]['beda_hak'], 'Matriks modul BARU milik admin — bukan "beda".');
        $this->assertSame(0, DB::table('N_HRIS_Role_Hak')->where('Id_Role', $leader)->where('Jenis_page', 'IzinPageAdmin')->where('Flag_Aktif', 'Y')->count(), 'Laporan kesiapan menulis data.');
    }

    public function test_kesiapan_hanya_system_admin(): void
    {
        // Sinkron kering tanpa kunci: tetap bisa saat sinkron terjadwal berjalan (PerbaikanMinorFase45Test).
        $this->actingAs($this->adminModul())->getJson('/api/v1/akses-peran/kesiapan')->assertForbidden();
    }
}
