<?php

namespace Tests\Integration\VISITOR\Workflow\Notification;

use App\Services\VISITOR\Workflow\Notification\VisitorNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VisitorNotificationRecipientTest extends TestCase
{
    use DatabaseTransactions;

    public function test_penerima_admin_mengambil_pemegang_role_she_admin_yang_aktif(): void
    {
        $idRole = $this->idRoleVerifikatorTamu();
        $service = app(VisitorNotificationService::class);

        $sebelum = $service->penerimaAdmin(VisitorNotificationService::DOMAIN_TAMU);
        $kandidat = $this->kandidatKaryawanDiLuar($sebelum, 1);

        $this->assertCount(1, $kandidat, 'Butuh minimal satu karyawan ber-UserID_Web di luar penerima saat ini.');
        $baru = $kandidat[0];

        DB::table('N_HRIS_Role_User')->insert([
            'UserId_Web' => $baru->UserID_Web,
            'Id_Role' => $idRole,
            'Assigned_At' => now(),
            'Assigned_By' => 'TEST',
            'Flag_Aktif' => 'Y',
        ]);

        $sesudah = $service->penerimaAdmin(VisitorNotificationService::DOMAIN_TAMU);

        $this->assertContains($baru->Kode_Karyawan, $sesudah);
        $this->assertSame(count($sebelum) + 1, count($sesudah));
    }

    public function test_penerima_admin_membuang_keanggotaan_nonaktif_dan_baris_kembar(): void
    {
        $idRole = $this->idRoleVerifikatorTamu();
        $service = app(VisitorNotificationService::class);

        $sebelum = $service->penerimaAdmin(VisitorNotificationService::DOMAIN_TAMU);
        $kandidat = $this->kandidatKaryawanDiLuar($sebelum, 2);

        $this->assertCount(2, $kandidat, 'Butuh dua karyawan ber-UserID_Web di luar penerima saat ini.');
        [$kembar, $nonaktif] = $kandidat;

        // Dua baris keanggotaan aktif untuk orang yang sama -> tetap satu penerima.
        DB::table('N_HRIS_Role_User')->insert([
            ['UserId_Web' => $kembar->UserID_Web, 'Id_Role' => $idRole, 'Assigned_At' => now(), 'Assigned_By' => 'TEST', 'Flag_Aktif' => 'Y'],
            ['UserId_Web' => $kembar->UserID_Web, 'Id_Role' => $idRole, 'Assigned_At' => now(), 'Assigned_By' => 'TEST', 'Flag_Aktif' => 'Y'],
            ['UserId_Web' => $nonaktif->UserID_Web, 'Id_Role' => $idRole, 'Assigned_At' => now(), 'Assigned_By' => 'TEST', 'Flag_Aktif' => 'T'],
        ]);

        $sesudah = $service->penerimaAdmin(VisitorNotificationService::DOMAIN_TAMU);

        $this->assertSame(
            1,
            count(array_keys($sesudah, $kembar->Kode_Karyawan, true)),
            'Dua baris keanggotaan aktif harus menghasilkan satu penerima, bukan notif dobel.',
        );
        $this->assertNotContains($nonaktif->Kode_Karyawan, $sesudah);
        $this->assertSame(count($sebelum) + 1, count($sesudah));
    }

    private function idRoleVerifikatorTamu(): int
    {
        $role = DB::table('N_HRIS_Role_Functional')
            ->where('Role_Code', 'ADMIN_TAMU')
            ->where('Flag_Aktif', 'Y')
            ->first();

        $this->assertNotNull($role, 'Role ADMIN_TAMU aktif tidak ditemukan; jalankan DML-2026-108 lebih dulu.');

        return (int) $role->Id_Role;
    }

    /**
     * @param  array<int,string>  $dikecualikan
     * @return array<int,object>
     */
    private function kandidatKaryawanDiLuar(array $dikecualikan, int $jumlah): array
    {
        return DB::table('Karyawan')
            ->whereNotNull('UserID_Web')
            ->whereNotIn('Kode_Karyawan', $dikecualikan ?: ['__none__'])
            ->orderBy('Kode_Karyawan')
            ->limit($jumlah)
            ->get(['Kode_Karyawan', 'UserID_Web'])
            ->all();
    }
}
