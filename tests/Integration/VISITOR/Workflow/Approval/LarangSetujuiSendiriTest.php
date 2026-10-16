<?php

namespace Tests\Integration\VISITOR\Workflow\Approval;

use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Workflow\Approval\VisitorApprovalDecisionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pengaju tidak boleh menyetujui pengajuannya sendiri (audit 27 Sep 2026, M3,
 * keputusan user: opsi b). Berlaku untuk permit karyawan dan kunjungan tamu.
 * Permit COKLAT dikecualikan: PIC tercatat sebagai pemohonnya hanya sebagai
 * pencatatan, dan kunjungannya sudah diputus lebih dulu — lihat
 * InspeksiPermitCoklatTest yang menyetujui COKLAT atas nama PIC yang sama.
 */
class LarangSetujuiSendiriTest extends TestCase
{
    use DatabaseTransactions;

    private const KODE = 'A1';

    public function test_pemohon_tidak_bisa_menyetujui_permit_karyawannya_sendiri(): void
    {
        $no = 'PMT-SDR-'.Str::upper(Str::random(8));
        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $no, 'Kode_Karyawan_Pemohon' => self::KODE, 'Sumber_Pengajuan' => 'HCIS',
            'Flag_Aktif' => 'Y', 'Flag_Approval' => null, 'Created_At' => now(), 'Created_By' => self::KODE,
            'Idempotency_Key' => 'SDR-'.Str::upper(Str::random(20)),
        ]);
        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert(['Id_Transaksi_Permit' => $id, 'No_Transaksi' => $no]);
        $this->approver($no);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('pengajuan Anda sendiri');

        app(VisitorApprovalDecisionService::class)->approvePermit($no, self::KODE, [], false);
    }

    public function test_pic_tidak_bisa_menyetujui_kunjungan_tamunya_sendiri(): void
    {
        $no = 'VSR-SDR-'.Str::upper(Str::random(8));
        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $no, 'Kode_Karyawan_PIC' => self::KODE,
            'Status_Kunjungan' => StatusKunjunganTamu::PENDING_APPROVAL, 'Flag_Approval' => null,
            'Tanggal_Kunjungan' => now()->toDateString(), 'Flag_Aktif' => 'Y',
            'Created_At' => now(), 'Created_By' => self::KODE, 'Idempotency_Key' => 'SDR-'.Str::upper(Str::random(20)),
        ]);
        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id, 'No_Transaksi' => $no, 'Nama_Lengkap_Tamu' => 'Tamu Sendiri',
            'Tipe_Tamu' => 'REGULAR', 'Instansi_Atau_Asal' => 'PT Uji', 'Deksripsi_Keperluan' => 'Meeting',
            'Jumlah_Tamu' => 1, 'Jenis_Kendaraan' => 'Tidak Berkendara',
        ]);
        $this->approver($no);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('pengajuan Anda sendiri');

        app(VisitorApprovalDecisionService::class)->approveTamu($no, self::KODE);
    }

    private function approver(string $no): void
    {
        DB::table('HRIS_Approval_Request')->insert([
            'No_Transaksi' => $no, 'Kode_Karyawan' => self::KODE, 'Request_Order_Flow' => 1,
            'Approver_Karyawan_Kode' => self::KODE, 'Flag_Approval' => null,
            'Expired_At' => now()->addDays(3), 'created_at' => now(), 'update_at' => now(),
        ]);
    }
}
