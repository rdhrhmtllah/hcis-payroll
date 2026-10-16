<?php

namespace Tests\Integration\VISITOR\Workflow\Approval;

use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Workflow\Approval\VisitorApprovalDecisionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\VISITOR\MenerbitkanPermitCoklat;
use Tests\TestCase;

/**
 * Menyetujui kunjungan Tamu B2B MENGAJUKAN permit COKLAT-nya, bukan menerbitkan.
 *
 * Izin gerbangnya baru hidup setelah permit itu sendiri disetujui. Kalau uji ini
 * merah pada bagian "belum ada di gerbang", berarti persetujuan kunjungan kembali
 * menerbitkan akses fisik tanpa seorang pun memutuskannya.
 */
class ApprovalTamuB2BPermitTest extends TestCase
{
    use DatabaseTransactions;
    use MenerbitkanPermitCoklat;

    private const PIC = 'A1';

    /**
     * Karyawan NYATA lain sebagai verifikator: sejak audit 27 Sep (M3) PIC tidak
     * boleh menyetujui kunjungannya sendiri. Permit COKLAT-nya tetap boleh
     * disetujui atas nama PIC (dikecualikan dari larangan).
     */
    private const VERIFIKATOR = 'H45';

    public function test_approval_tamu_b2b_mengajukan_permit_tanpa_menerbitkannya(): void
    {
        $nomor = $this->buatTamuB2BPending();

        app(VisitorApprovalDecisionService::class)->approveTamu($nomor, self::VERIFIKATOR, 'Disetujui');

        // Approve akhir menulis SCHEDULED (dulu TERJADWAL, DDL-2026-121).
        $this->assertSame(
            StatusKunjunganTamu::SCHEDULED,
            DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $nomor)->value('Status_Kunjungan'),
        );

        $noPermit = $this->nomorPermitCoklat($nomor);

        $this->assertNotNull($noPermit, 'Permit COKLAT B2B tidak diajukan setelah kunjungannya disetujui.');
        $this->assertNull(
            DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $noPermit)->value('Flag_Approval'),
            'Permit COKLAT langsung disetujui tanpa melewati verifikator.',
        );
        $this->assertSame(
            0,
            DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $noPermit)->count(),
            'Izin gerbang sudah hidup padahal permitnya belum disetujui.',
        );
    }

    public function test_permit_satu_hari_setelah_permitnya_sendiri_disetujui(): void
    {
        // Kunjungan lama tanpa Estimasi Masuk/Keluar: permit jatuh ke fallback
        // satu hari kalender (DDL-2026-121 memakai estimasi bila ada).
        $nomor = $this->buatTamuB2BPending();

        app(VisitorApprovalDecisionService::class)->approveTamu($nomor, self::VERIFIKATOR, 'Disetujui');

        $noPermit = $this->nomorPermitCoklat($nomor);
        $this->setujuiPermitCoklat($noPermit, self::PIC);

        $permit = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $noPermit)->first();

        $this->assertNotNull($permit, 'Permit COKLAT B2B harus terbit setelah disetujui.');
        $this->assertSame('1 Hari', $permit->Nama_Durasi);
        $this->assertSame('2026-09-20 00:00:00', substr((string) $permit->Waktu_Mulai_Berlaku, 0, 19));
        $this->assertSame('2026-09-21 00:00:00', substr((string) $permit->Waktu_Berakhir, 0, 19));
        $this->assertSame('2026-09-20', substr((string) $permit->Tanggal_Mulai_Berlaku, 0, 10));
        $this->assertSame('2026-09-20', substr((string) $permit->Tanggal_Kadaluarsa, 0, 10));
    }

    private function buatTamuB2BPending(): string
    {
        $nomor = 'VSR-B2B-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_PIC' => self::PIC,
            'Status_Kunjungan' => StatusKunjunganTamu::PENDING_APPROVAL,
            'Needs_Approval' => 'Y',
            'Flag_Approval' => null,
            'Tanggal_Kunjungan' => '2026-09-20',
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Idempotency_Key' => 'B2B-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id,
            'No_Transaksi' => $nomor,
            'Nama_Lengkap_Tamu' => 'Tamu B2B Mitra',
            'Tipe_Tamu' => 'B2B',
            'Instansi_Atau_Asal' => 'PT Mitra B2B',
            'Deksripsi_Keperluan' => 'Meeting Bisnis',
            'Jumlah_Tamu' => 1,
            'Jenis_Kendaraan' => 'Tidak Berkendara',
        ]);

        DB::table('HRIS_Approval_Request')->insert([
            'Kode_Karyawan' => self::PIC,
            'update_at' => now(),
            'No_Transaksi' => $nomor,
            'Approver_Karyawan_Kode' => self::VERIFIKATOR,
            'Request_Order_Flow' => 1,
            'Flag_Approval' => null,
            'created_at' => now(),
        ]);

        return $nomor;
    }
}
