<?php

namespace Tests\Integration\VISITOR\Permit\VehiclePermit;

use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQuotaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Kuota dihitung dari status yang diturunkan permitStatusSql(), bukan dari
 * Flag_Approval mentah. Bedanya nyata: Flag_Approval='Y' juga menandai permit
 * yang sudah kedaluwarsa, dan itu tidak boleh terus menahan jatah orangnya.
 */
class VisitorPermitQuotaTest extends TestCase
{
    use DatabaseTransactions;

    private string $kode = 'ZZQUOTA1';

    public function test_hanya_pengajuan_yang_masih_hidup_yang_memakan_kuota(): void
    {
        $this->buatPermit('PENDING');
        $this->buatPermit('NO_FLOW');
        $this->buatPermit('VALIDITY_REQUIRED');
        $this->buatPermit('APPROVED');
        $this->buatPermit('REJECTED');
        $this->buatPermit('EXPIRED');

        $this->assertSame(
            4,
            app(VisitorPermitQuotaService::class)->terpakai($this->kode),
            'REJECTED dan EXPIRED harus mengembalikan jatah.',
        );
    }

    public function test_permit_kedaluwarsa_membebaskan_jatah_meski_flag_approval_masih_y(): void
    {
        $this->buatPermit('EXPIRED');
        $this->buatPermit('EXPIRED');

        $this->assertSame(0, app(VisitorPermitQuotaService::class)->terpakai($this->kode));
    }

    public function test_kuota_dihitung_per_karyawan(): void
    {
        $this->buatPermit('PENDING');
        $this->buatPermit('PENDING', 'ZZQUOTA2');

        $quota = app(VisitorPermitQuotaService::class);

        $this->assertSame(1, $quota->terpakai($this->kode));
        $this->assertSame(1, $quota->terpakai('ZZQUOTA2'));
    }

    public function test_permit_yang_dinonaktifkan_tidak_memakan_kuota(): void
    {
        $this->buatPermit('PENDING');
        $this->buatPermit('PENDING', null, flagAktif: 'T');

        $this->assertSame(1, app(VisitorPermitQuotaService::class)->terpakai($this->kode));
    }

    public function test_karyawan_tanpa_permit_berkuota_nol(): void
    {
        $this->assertSame(0, app(VisitorPermitQuotaService::class)->terpakai($this->kode));
    }

    public function test_kode_kosong_tidak_menghitung_permit_siapa_pun(): void
    {
        $this->buatPermit('PENDING');

        $this->assertSame(0, app(VisitorPermitQuotaService::class)->terpakai(''));
    }

    // ---------------------------------------------------------------- helpers

    private function buatPermit(string $status, ?string $kode = null, string $flagAktif = 'Y'): string
    {
        $kode ??= $this->kode;
        $noTransaksi = 'PMT-Q-'.Str::upper(Str::random(10));

        $flagApproval = match ($status) {
            'APPROVED', 'EXPIRED', 'VALIDITY_REQUIRED' => 'Y',
            'REJECTED' => 'T',
            default => null,
        };

        DB::table('N_EMI_VISITOR_Transaksi_Permit')->insert([
            'No_Transaksi' => $noTransaksi,
            'Kode_Karyawan_Pemohon' => $kode,
            'Flag_Aktif' => $flagAktif,
            'Flag_Approval' => $flagApproval,
            'Created_At' => now(),
            'Created_By' => $kode,
            'Idempotency_Key' => 'QUOTA-'.Str::upper(Str::random(20)),
        ]);

        [$mulai, $kadaluarsa] = match ($status) {
            'APPROVED' => [now()->subDay()->toDateString(), now()->addMonth()->toDateString()],
            'EXPIRED' => [now()->subMonths(2)->toDateString(), now()->subDay()->toDateString()],
            default => [null, null],
        };

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'No_Transaksi' => $noTransaksi,
            'Tanggal_Mulai_Berlaku' => $mulai,
            'Tanggal_Kadaluarsa' => $kadaluarsa,
        ]);

        // PENDING butuh langkah approval yang belum diputus; NO_FLOW justru
        // ditandai oleh ketiadaan baris ini.
        if ($status === 'PENDING') {
            DB::table('HRIS_Approval_Request')->insert([
                'Kode_Karyawan' => $kode,
                'No_Transaksi' => $noTransaksi,
                'Flag_Approval' => null,
                'update_at' => now(),
                'created_at' => now(),
            ]);
        }

        return $noTransaksi;
    }
}
