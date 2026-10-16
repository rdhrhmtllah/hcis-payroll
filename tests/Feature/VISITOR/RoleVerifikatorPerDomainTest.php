<?php

namespace Tests\Feature\VISITOR;

use App\Services\VISITOR\Workflow\Approval\VisitorApprovalPolicyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Antrean verifikasi dikirim ke role per domain (DML-2026-108).
 *
 * Sebelumnya kedua config approval menunjuk role yang sama, sehingga pemisahan
 * halaman (DML-2026-105) hanya memisahkan siapa boleh MENEKAN TOMBOL — bukan
 * kepada siapa pengajuan DIKIRIM. Mencabut kunci halaman dari seseorang membuat
 * antreannya menggantung, bukan berpindah, dan tidak ada galat yang
 * menjelaskannya.
 *
 * Yang ditagih di sini adalah PERILAKU pengajuan barunya, bukan isi tabel
 * config: config yang benar tetapi resolusi approver yang salah menghasilkan
 * pengajuan yang terbit ke antrean kosong.
 */
class RoleVerifikatorPerDomainTest extends TestCase
{
    use DatabaseTransactions;

    /** Kode karyawan NYATA di Web_HRIS development. */
    private const PEMOHON = 'A1';

    /** @return array<string, array{0:string,1:string}> */
    public static function petaDomainRole(): array
    {
        return [
            'permit' => ['permit', 'ADMIN_PERMIT'],
            'tamu' => ['tamu', 'ADMIN_TAMU'],
            // DDL-2026-121: tamu B2B punya pool verifikator sendiri.
            'tamu_b2b' => ['tamu_b2b', 'ADMIN_TAMU_B2B'],
        ];
    }

    /** @dataProvider petaDomainRole */
    public function test_pengajuan_baru_terbit_ke_role_domainnya(string $modul, string $roleDiharapkan): void
    {
        $kebijakan = app(VisitorApprovalPolicyService::class);
        $config = $kebijakan->config($modul);

        $approver = $kebijakan->resolveApprovers(self::PEMOHON, $config);

        $this->assertNotEmpty($approver, "Modul {$modul} tidak punya approver; pengajuannya akan gagal dibuat.");
        $this->assertSame(
            [$roleDiharapkan],
            $approver->pluck('source_reference')->unique()->values()->all(),
            "Pengajuan {$modul} terbit ke role yang salah.",
        );
    }

    /** @dataProvider petaDomainRole */
    public function test_config_menunjuk_role_yang_sama_dengan_yang_dipakai_aplikasi(string $modul, string $roleDiharapkan): void
    {
        $kodeConfig = strtoupper($modul);

        $sumber = DB::table('N_HRIS_Approval_Config as cfg')
            ->join('N_HRIS_Approval_Config_Detail as d', 'd.Id_Approval_Config', '=', 'cfg.Id')
            ->join('N_HRIS_Jenis_Approval as j', 'j.Id_Jenis_Approval', '=', 'd.Id_Jenis_Approval')
            ->where('cfg.Kode_Config', $kodeConfig)
            ->pluck('j.Source_Reference')
            ->unique()
            ->values()
            ->all();

        $this->assertSame([$roleDiharapkan], $sumber, "Config {$kodeConfig} tidak menunjuk {$roleDiharapkan}.");
    }

    public function test_ketiga_role_ada_dan_berpenghuni(): void
    {
        // Role tanpa anggota aktif berarti antrean yang tidak dapat disetujui
        // siapa pun. Kegagalannya tidak menyalakan galat — pengajuannya hanya
        // berhenti diproses. ADMIN_TAMU_B2B ditambahkan DDL-2026-121.
        foreach (['ADMIN_PERMIT', 'ADMIN_TAMU', 'ADMIN_TAMU_B2B'] as $kode) {
            $role = DB::table('N_HRIS_Role_Functional')->where('Role_Code', $kode)->first();

            $this->assertNotNull($role, "Role {$kode} tidak ada.");
            $this->assertSame('Y', $role->Flag_Aktif, "Role {$kode} nonaktif.");

            $this->assertGreaterThan(
                0,
                DB::table('N_HRIS_Role_User')->where('Id_Role', $role->Id_Role)->where('Flag_Aktif', 'Y')->count(),
                "Role {$kode} tidak punya anggota aktif; antreannya tidak dapat disetujui siapa pun.",
            );
        }
    }

    public function test_role_domain_tidak_berbagi_definisi_jenis_approval(): void
    {
        // Satu jenis approval yang dipakai dua config akan mengembalikan
        // keadaan sebelum change ini tanpa terlihat di daftar role. Config
        // TAMU_B2B ditambahkan DDL-2026-121.
        $jenis = DB::table('N_HRIS_Approval_Config as cfg')
            ->join('N_HRIS_Approval_Config_Detail as d', 'd.Id_Approval_Config', '=', 'cfg.Id')
            ->whereIn('cfg.Kode_Config', ['PERMIT', 'TAMU', 'TAMU_B2B'])
            ->pluck('d.Id_Jenis_Approval')
            ->unique()
            ->values();

        $this->assertCount(3, $jenis, 'Config Visitor berbagi jenis approval yang sama.');
    }

    public function test_tidak_ada_lagi_pengajuan_visitor_yang_menggantung_di_role_lama(): void
    {
        // Config hanya mengatur pengajuan BARU. Pengajuan yang sudah terbit
        // tetap menunjuk role lamanya, dan begitu keanggotaannya dipangkas,
        // sisa antrean itu tidak dapat disetujui siapa pun.
        $she = DB::table('N_HRIS_Role_Functional')->where('Role_Code', 'SHE_ADMIN')->value('Id_Role');

        if (! $she) {
            $this->markTestSkipped('Role SHE_ADMIN tidak ada di database ini.');
        }

        $tertinggal = DB::table('HRIS_Approval_Request')
            ->whereNull('Flag_Approval')
            ->where('Group_Reference_Id', $she)
            ->where(function ($q) {
                $q->where('No_Transaksi', 'LIKE', 'PMT%')->orWhere('No_Transaksi', 'LIKE', 'VSR%');
            })
            ->count();

        $this->assertSame(0, $tertinggal, 'Masih ada pengajuan Visitor menggantung di SHE_ADMIN.');
    }

    public function test_pengajuan_menggantung_berada_di_role_sesuai_domainnya(): void
    {
        $roleVisitor = ['ADMIN_PERMIT', 'ADMIN_TAMU', 'ADMIN_TAMU_B2B', 'SHE_ADMIN'];

        $salahDomain = DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Role_Functional as r', 'r.Id_Role', '=', 'ar.Group_Reference_Id')
            ->whereNull('ar.Flag_Approval')
            ->where('ar.No_Transaksi', 'LIKE', 'PMT%')
            ->whereIn('r.Role_Code', $roleVisitor)
            ->where('r.Role_Code', '<>', 'ADMIN_PERMIT')
            ->count();

        $this->assertSame(0, $salahDomain, 'Ada pengajuan PMT% yang tidak berada di ADMIN_PERMIT.');

        // Kunjungan dipilah per tipe (DDL-2026-121): B2B menggantung di
        // ADMIN_TAMU_B2B, selebihnya (Regular/NULL) di ADMIN_TAMU.
        foreach ([[true, 'ADMIN_TAMU_B2B'], [false, 'ADMIN_TAMU']] as [$b2b, $kode]) {
            $salahDomain = DB::table('HRIS_Approval_Request as ar')
                ->join('N_HRIS_Role_Functional as r', 'r.Id_Role', '=', 'ar.Group_Reference_Id')
                ->join('N_EMI_VISITOR_Transaksi_Tamu_Detail as d', 'd.No_Transaksi', '=', 'ar.No_Transaksi')
                ->whereNull('ar.Flag_Approval')
                ->where('ar.No_Transaksi', 'LIKE', 'VSR%')
                ->where(function ($q) use ($b2b) {
                    $b2b
                        ? $q->where('d.Tipe_Tamu', 'B2B')
                        : $q->whereNull('d.Tipe_Tamu')->orWhere('d.Tipe_Tamu', '<>', 'B2B');
                })
                ->whereIn('r.Role_Code', $roleVisitor)
                ->where('r.Role_Code', '<>', $kode)
                ->count();

            $this->assertSame(0, $salahDomain, 'Ada pengajuan tamu '.($b2b ? 'B2B' : 'Regular')." yang tidak berada di {$kode}.");
        }
    }
}
