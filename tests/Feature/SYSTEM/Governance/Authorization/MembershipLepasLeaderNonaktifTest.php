<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Membership\MembershipManagementService;
use App\Services\SYSTEM\Governance\Authorization\Scope\EmployeeTargetScopeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Melepas anggota aktif dari tim milik leader yang sudah NONAKTIF.
 *
 * Pemeriksaan scope hanya meloloskan karyawan aktif, sehingga leader yang
 * sudah keluar dulu selalu terbaca "di luar target scope" (kasus production
 * 6 Okt 2026: AHMAD YUSUF) dan "Terapkan perubahan" gagal 403. Leader asal /
 * yang dilepas hanya diperiksa bila masih aktif; anggota dan leader tujuan
 * tetap wajib lolos scope.
 */
class MembershipLepasLeaderNonaktifTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private int $grupId;

    private object $config;

    private string $leaderNonaktif;

    private string $leaderTujuan;

    private string $anggota;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::query()->where('Flag_Active', 'Y')->where('Is_System_Admin', 'Y')->first();
        $grupId = (int) DB::table('N_HRIS_Master_Grup_Fitur')->where('Kode_Grup_Fitur', 'KPI')->value('Id_Grup_Fitur');
        $config = $grupId
            ? DB::table('N_HRIS_Membership_Config')->where('Id_Grup_Fitur', $grupId)->where('Flag_Aktif', 'Y')->first()
            : null;
        if (! $admin || ! $config) {
            $this->markTestSkipped('Data dev tidak punya admin sistem atau config keanggotaan KPI.');
        }

        $nonaktif = DB::table('Karyawan')->where('Aktif', '<>', 'Y')->whereNotNull('Kode_Karyawan')
            ->orderByDesc('UserID_Web')->value('Kode_Karyawan');
        $aktif = DB::table('Karyawan')->where('Aktif', 'Y')->whereNotNull('Kode_Karyawan')->whereNotNull('UserID_Web')
            ->orderByDesc('UserID_Web')->limit(2)->pluck('Kode_Karyawan');
        if (! $nonaktif || $aktif->count() < 2) {
            $this->markTestSkipped('Data dev butuh satu karyawan nonaktif dan dua karyawan aktif.');
        }

        $this->admin = $admin;
        $this->grupId = $grupId;
        $this->config = $config;
        $this->leaderNonaktif = trim($nonaktif);
        $this->leaderTujuan = trim($aktif[0]);
        $this->anggota = trim($aktif[1]);

        $this->hapusRelasi($this->anggota);
        $this->tempelkan($this->leaderNonaktif, $this->anggota);
    }

    private function svc(): MembershipManagementService
    {
        return app(MembershipManagementService::class);
    }

    private function hapusRelasi(string $anggota): void
    {
        DB::table($this->config->Source_Table)->where($this->config->Member_Column, $anggota)->delete();
    }

    private function tempelkan(string $leader, string $anggota): void
    {
        $payload = [$this->config->Leader_Column => $leader, $this->config->Member_Column => $anggota];
        if ($this->config->Active_Status_Column) {
            $payload[$this->config->Active_Status_Column] = $this->config->Active_Status_Value;
        }
        DB::table($this->config->Source_Table)->insert($payload);
    }

    private function relasiAktif(string $leader, string $anggota): bool
    {
        $query = DB::table($this->config->Source_Table)
            ->where($this->config->Leader_Column, $leader)
            ->where($this->config->Member_Column, $anggota);
        if ($this->config->Active_Status_Column) {
            $query->where($this->config->Active_Status_Column, $this->config->Active_Status_Value);
        }

        return $query->exists();
    }

    public function test_tambah_sambil_melepas_dari_leader_nonaktif_berhasil(): void
    {
        $hasil = $this->svc()->assign(
            $this->admin, $this->grupId, (int) $this->config->Id, $this->leaderTujuan, [$this->anggota],
            'uji lepas leader nonaktif', null, 'add', null, [$this->anggota => [$this->leaderNonaktif]],
        );

        $this->assertSame(2, $hasil['affected'], 'harus 1 tambah + 1 lepas');
        $this->assertSame([], $hasil['conflicts']);
        $this->assertTrue($this->relasiAktif($this->leaderTujuan, $this->anggota));
        $this->assertFalse($this->relasiAktif($this->leaderNonaktif, $this->anggota));
    }

    public function test_pindah_dari_leader_asal_nonaktif_berhasil(): void
    {
        $hasil = $this->svc()->assign(
            $this->admin, $this->grupId, (int) $this->config->Id, $this->leaderTujuan, [$this->anggota],
            null, null, 'move', $this->leaderNonaktif,
        );

        $this->assertSame(1, $hasil['affected']);
        $this->assertTrue($this->relasiAktif($this->leaderTujuan, $this->anggota));
        $this->assertFalse($this->relasiAktif($this->leaderNonaktif, $this->anggota));
    }

    public function test_preview_dan_lepas_dari_leader_asal_nonaktif_berhasil(): void
    {
        $preview = $this->svc()->preview(
            $this->admin, $this->grupId, (int) $this->config->Id, 'remove', [$this->anggota], null, 'remove', $this->leaderNonaktif,
        );
        $this->assertSame(1, $preview['counts']['remove']);

        $hasil = $this->svc()->remove(
            $this->admin, $this->grupId, (int) $this->config->Id, [$this->anggota], null, null, $this->leaderNonaktif,
        );

        $this->assertSame(1, $hasil['affected']);
        $this->assertFalse($this->relasiAktif($this->leaderNonaktif, $this->anggota));
    }

    public function test_leader_aktif_yang_dilepas_tetap_diperiksa_scope(): void
    {
        $leaderAktifLain = trim((string) DB::table('Karyawan')->where('Aktif', 'Y')->whereNotNull('Kode_Karyawan')
            ->whereNotIn('Kode_Karyawan', [$this->leaderTujuan, $this->anggota])
            ->orderByDesc('UserID_Web')->value('Kode_Karyawan'));
        $this->assertNotSame('', $leaderAktifLain);

        $diperiksa = null;
        $this->partialMock(EmployeeTargetScopeService::class, function ($mock) use (&$diperiksa) {
            $mock->shouldReceive('assertEmployeeCodesAllowed')
                ->andReturnUsing(function ($user, $grupId, $codes) use (&$diperiksa) {
                    $diperiksa = array_values($codes);
                });
        });

        $this->svc()->assign(
            $this->admin, $this->grupId, (int) $this->config->Id, $this->leaderTujuan, [$this->anggota],
            null, null, 'add', null, [$this->anggota => [$this->leaderNonaktif, $leaderAktifLain]],
        );

        $this->assertNotNull($diperiksa, 'pemeriksaan scope tidak pernah dipanggil');
        $this->assertContains($this->anggota, $diperiksa);
        $this->assertContains($this->leaderTujuan, $diperiksa);
        $this->assertContains($leaderAktifLain, $diperiksa, 'leader aktif yang dilepas wajib tetap diperiksa');
        $this->assertNotContains($this->leaderNonaktif, $diperiksa, 'leader nonaktif tidak boleh memblokir');
    }
}
