<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use App\Services\SYSTEM\Governance\Authorization\Akses\AuditAkses;
use App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationAuditLogger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** RBAC Fase 1: jejak perubahan akses ke N_HRIS_Akses_Audit. */
class AuditAksesTest extends TestCase
{
    use DatabaseTransactions;
    use FixturePageAccess;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');
        if (! SkemaTersedia::tabel(AuditAkses::TABEL)) {
            $this->markTestSkipped('DDL-2026-164 belum dijalankan di database ini.');
        }
    }

    private function terakhir(): ?object
    {
        return DB::table(AuditAkses::TABEL)->orderByDesc('Id_Akses_Audit')->first();
    }

    public function test_mencatat_sebelum_dan_sesudah(): void
    {
        $ok = app(AuditAkses::class)->catat('PENUGASAN', '12', 'UBAH_ROLE', ['KPI_ADMIN'], ['KPI_ADMIN', 'ESS_MENU_ADMINISTRATOR'], 630, 'uji', 'UI');

        $this->assertTrue($ok);
        $b = $this->terakhir();
        $this->assertSame('PENUGASAN', $b->Subjek_Jenis);
        $this->assertSame(630, (int) $b->Target_Id_User);
        $this->assertSame(['KPI_ADMIN'], json_decode($b->Nilai_Sebelum, true));
        $this->assertSame(['KPI_ADMIN', 'ESS_MENU_ADMINISTRATOR'], json_decode($b->Nilai_Sesudah, true));
    }

    public function test_nilai_besar_tersimpan_utuh(): void
    {
        $besar = array_map(fn ($i) => 'HALAMAN_'.$i, range(1, 400));

        app(AuditAkses::class)->catat('GRANT', null, 'BERI', [], $besar, 630);

        $this->assertCount(400, json_decode($this->terakhir()->Nilai_Sesudah, true));
    }

    public function test_log_mutation_ikut_tercatat_di_tabel(): void
    {
        AuthorizationAuditLogger::logMutation([
            'event' => 'menu-binding.save', 'actor_user_id' => 4, 'target_user_id' => 630,
            'jenis_page' => 'IzinPageAdmin', 'old_value' => 'T', 'new_value' => 'Y', 'source' => 'uji',
        ]);

        $b = $this->terakhir();
        $this->assertSame('menu-binding.save', $b->Aksi);
        $this->assertSame(630, (int) $b->Target_Id_User);
        $this->assertSame('uji', $b->Sumber);
    }

    public function test_simpan_role_user_tercatat(): void
    {
        $admin = \App\Models\ESS\Employee\Models\User::where('Id_Users', 4)->firstOrFail();
        $target = (int) DB::table('Karyawan')->where('Aktif', 'Y')->whereNotNull('UserID_Web')
            ->where('UserID_Web', '<>', 4)->orderBy('UserID_Web')->value('UserID_Web');
        $role = DB::table('N_HRIS_Role_Functional')->where('Role_Code', 'HCIS_LEADER')->value('Id_Role');
        $awal = (int) DB::table(AuditAkses::TABEL)->max('Id_Akses_Audit');

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/role-manager/user-roles/{$target}", ['roleIds' => [$role]])
            ->assertOk();

        $b = DB::table(AuditAkses::TABEL)->where('Id_Akses_Audit', '>', $awal)->where('Subjek_Jenis', 'PENUGASAN')->first();
        $this->assertNotNull($b, 'Simpan role user tidak tercatat.');
        $this->assertSame($target, (int) $b->Target_Id_User);
        $this->assertContains('HCIS_LEADER', json_decode($b->Nilai_Sesudah, true));
    }

    public function test_page_access_tercatat_ringkas(): void
    {
        [$admin, $hal] = $this->adminDanHalaman();
        $target = $this->karyawanTanpaBaris($hal->Jenis_page, (int) $admin->Id_Users);
        $awal = (int) DB::table(AuditAkses::TABEL)->max('Id_Akses_Audit');

        $this->actingAs($admin)->postJson('/api/v1/page-access/update-access',
            ['all' => false, 'employeeIds' => [$target], 'pageIds' => [$hal->id], 'action' => 'grant'])->assertOk();

        $b = DB::table(AuditAkses::TABEL)->where('Id_Akses_Audit', '>', $awal)->where('Subjek_Jenis', 'GRANT')->first();
        $this->assertNotNull($b, 'Page Access Setup tidak tercatat di N_HRIS_Akses_Audit.');
        $this->assertSame('grant', $b->Aksi);
    }

    public function test_log_mutation_membawa_isi_lengkap(): void
    {
        AuthorizationAuditLogger::logMutation([
            'event' => 'menu_access.granted', 'actor_user_id' => 4, 'target_user_id' => 630,
            'page_access_id' => 123, 'details' => ['pages' => ['IzinPageAdmin', 'absensiPage']], 'affected_count' => 2,
        ]);

        $b = $this->terakhir();
        $this->assertSame('GRANT', $b->Subjek_Jenis, 'Pemberian menu per user bukan perubahan katalog.');
        $this->assertSame('123', $b->Subjek_Id);
        $this->assertSame(4, (int) $b->Oleh_Id_User);
        $isi = json_decode((string) $b->Nilai_Sesudah, true);
        $this->assertSame(['IzinPageAdmin', 'absensiPage'], $isi['details']['pages'] ?? null);
        $this->assertSame(2, $isi['affected_count'] ?? null);
    }

    public function test_log_mutation_katalog_tetap_katalog(): void
    {
        AuthorizationAuditLogger::logMutation(['event' => 'menu_capabilities.default_updated', 'jenis_page' => 'IzinPageAdmin']);

        $this->assertSame('KATALOG', $this->terakhir()->Subjek_Jenis);
    }

    /** Karyawan aktif yang identitas divisi/levelnya lengkap (syarat simpan izin admin). */
    private function targetLengkap(): int
    {
        return (int) DB::table('Karyawan as a')
            ->join('View_Divisi_Sub_Divisi as b', 'a.ID_Divisi_Sub_Divisi', '=', 'b.ID_DIVISI_SUB_DIVISI')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as c', 'a.ID_Level_Jabatan', '=', 'c.ID_Level_Jabatan')
            ->where('a.Aktif', 'Y')->whereNotNull('a.UserID_Web')->where('a.UserID_Web', '<>', 4)
            ->orderBy('a.UserID_Web')->value('a.UserID_Web');
    }

    public function test_simpan_izin_admin_tercatat(): void
    {
        $admin = \App\Models\ESS\Employee\Models\User::where('Id_Users', 4)->firstOrFail();
        $target = $this->targetLengkap();
        $awal = (int) DB::table(AuditAkses::TABEL)->max('Id_Akses_Audit');

        $this->actingAs($admin)->postJson("/api/v1/admin/role-manager/permissions/{$target}", [
            'pages' => ['IzinPageAdmin'], 'actions' => [], 'featureGroups' => [], 'contentTabs' => [],
        ])->assertOk();

        $b = DB::table(AuditAkses::TABEL)->where('Id_Akses_Audit', '>', $awal)->where('Aksi', 'SIMPAN_IZIN_ADMIN')->first();
        $this->assertNotNull($b, 'Simpan izin admin tidak tercatat.');
        $this->assertSame($target, (int) $b->Target_Id_User);
        $this->assertContains('IzinPageAdmin', json_decode((string) $b->Nilai_Sesudah, true));
    }

    public function test_distribusi_beri_dan_cabut_tercatat(): void
    {
        $admin = \App\Models\ESS\Employee\Models\User::where('Id_Users', 4)->firstOrFail();
        $target = $this->targetLengkap();
        DB::table('HRIS_Page_Access')->where('UserID_Web', $target)->where('Jenis_page', 'IzinPageAdmin')->delete();
        $awal = (int) DB::table(AuditAkses::TABEL)->max('Id_Akses_Audit');
        $isi = ['grup_id' => 0, 'mode' => 'individual', 'user_ids' => [$target], 'pages' => ['IzinPageAdmin']];

        $this->actingAs($admin)->postJson('/api/v1/system-auth/distribution/execute', $isi)->assertOk();
        $this->actingAs($admin)->postJson('/api/v1/system-auth/distribution/revoke', $isi)->assertOk();

        $aksi = DB::table(AuditAkses::TABEL)->where('Id_Akses_Audit', '>', $awal)->pluck('Aksi')->all();
        $this->assertContains('DISTRIBUSI_BERI', $aksi);
        $this->assertContains('DISTRIBUSI_CABUT', $aksi);
    }
}
