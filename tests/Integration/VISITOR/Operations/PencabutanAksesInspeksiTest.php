<?php

namespace Tests\Integration\VISITOR\Operations;

use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Permit\Inspection\JadwalInspeksiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pencabutan izin permit tidak boleh mengenai akses INSPEKSI sementara milik
 * pengajuan yang belum diputus, dan akses yang sudah dicabut tidak boleh
 * dihidupkan lagi oleh penjadwalan ulang (audit 27 Sep 2026, M4).
 *
 * Tanpa penjaga: verifikator mencabut baris INSPEKSI, permitnya lalu disetujui,
 * dan syncPermitEfektif menolak menimpa DIBATALKAN — permit yang disetujui tak
 * pernah sampai ke palang dan terbaca REVOKED.
 */
class PencabutanAksesInspeksiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cabut_tunggal_tidak_mengenai_akses_inspeksi(): void
    {
        $no = $this->permitDenganAksesInspeksi();

        $jumlah = app(VisitorEfektifService::class)->revokePermitEfektif($no, 'uji', 'A1');

        $this->assertSame(0, $jumlah);
        $this->assertSame('AKTIF', $this->statusGerbang($no));
    }

    public function test_cabut_massal_tidak_mengenai_akses_inspeksi(): void
    {
        $no = $this->permitDenganAksesInspeksi();

        $dicabut = app(VisitorEfektifService::class)->revokePermitEfektifMany([$no], 'uji', 'A1');

        $this->assertSame([], $dicabut);
        $this->assertSame('AKTIF', $this->statusGerbang($no));
    }

    public function test_akses_yang_sudah_dicabut_tidak_dihidupkan_lagi_oleh_jadwal_baru(): void
    {
        $no = $this->permitDenganAksesInspeksi();
        DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $no)->update(['Status_Efektif' => 'DIBATALKAN']);

        $mulai = now()->addDays(2)->setTime(9, 0);
        app(VisitorEfektifService::class)->terbitkanAksesInspeksi($no, $mulai, $mulai->copy()->addMinutes(30));

        $this->assertSame('DIBATALKAN', $this->statusGerbang($no));
    }

    public function test_persetujuan_mengganti_penyetuju_sementara_akses_inspeksi(): void
    {
        // Baris INSPEKSI membawa penyetuju "... (belum disetujui)"; begitu permit
        // disetujui, nama penyetuju & riwayatnya harus ikut diganti.
        $no = $this->permitDenganAksesInspeksi();
        DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $no)->update(['Flag_Approval' => 'Y']);
        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->where('No_Transaksi', $no)
            ->update(['Tanggal_Mulai_Berlaku' => now()->toDateString(), 'Tanggal_Kadaluarsa' => now()->addMonth()->toDateString()]);
        DB::table('HRIS_Approval_Request')->insert([
            'Kode_Karyawan' => 'A1', 'No_Transaksi' => $no, 'Flag_Approval' => 'Y', 'Request_Order_Flow' => 1,
            'Approved_By_Kode' => 'A1', 'Tanggal_Approval' => now(), 'created_at' => now(), 'update_at' => now(),
        ]);

        app(VisitorEfektifService::class)->syncPermitEfektif($no);

        $baris = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $no)->first();
        $this->assertSame('PERMIT', $baris->Jenis_Akses);
        $this->assertStringNotContainsString('belum disetujui', (string) $baris->Disetujui_Oleh_Nama);
        $this->assertStringNotContainsString('AKSES_INSPEKSI', (string) $baris->Riwayat_Approval);
    }

    public function test_penyapu_mengakhiri_akses_inspeksi_booking_yang_dibatalkannya(): void
    {
        // Permit yang sudah mati tapi slotnya masih di depan: booking dibatalkan
        // penyapu, dan baris palang INSPEKSI-nya harus ikut berakhir.
        $no = $this->permitDenganAksesInspeksi();
        DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $no)->update(['Flag_Approval' => 'T']);

        $this->artisan('visitor:expire-efektif')->assertExitCode(0);

        $this->assertSame(JadwalInspeksiService::DIBATALKAN, DB::table('N_VISITOR_Inspeksi_Booking')->where('No_Transaksi_Permit', $no)->value('Status'));
        $this->assertNotSame('AKTIF', $this->statusGerbang($no), 'Palang tetap terbuka untuk pengajuan yang sudah mati.');
    }

    private function statusGerbang(string $no): ?string
    {
        return DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $no)->value('Status_Efektif');
    }

    /** Permit karyawan PENDING milik 'A1' dengan baris palang INSPEKSI yang AKTIF. */
    private function permitDenganAksesInspeksi(): string
    {
        $no = 'PMT-CBT-'.Str::upper(Str::random(8));
        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $no, 'Kode_Karyawan_Pemohon' => 'A1', 'Sumber_Pengajuan' => 'HCIS',
            'Flag_Aktif' => 'Y', 'Flag_Approval' => null, 'Created_At' => now(), 'Created_By' => 'A1',
            'Idempotency_Key' => 'CBT-'.Str::upper(Str::random(20)),
        ]);
        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'Id_Transaksi_Permit' => $id, 'No_Transaksi' => $no,
            'Plat_Nomor_Prefix' => 'BG', 'Plat_Nomor_Angka' => (string) random_int(1000, 9999), 'Plat_Nomor_Suffix' => 'CB',
            'Id_Master_Model' => 3, 'Id_Master_Warna' => 1, 'Tahun_Kendaraan' => 2020,
        ]);
        $mulai = now()->addDay()->setTime(10, 0);
        DB::table('N_VISITOR_Inspeksi_Booking')->insert([
            'No_Transaksi_Permit' => $no,
            'Id_Pos_Inspeksi' => DB::table(JadwalInspeksiService::T_POS)->where('Flag_Aktif', 'Y')->value('Id_Pos_Inspeksi'),
            'Snapshot_Nama_Pos' => 'Pos Uji Cabut', 'Waktu_Mulai' => $mulai, 'Waktu_Selesai' => $mulai->copy()->addMinutes(30),
            'Status' => JadwalInspeksiService::TERJADWAL, 'Created_At' => now(), 'Created_By' => 'UJI',
        ]);
        app(VisitorEfektifService::class)->terbitkanAksesInspeksi($no, $mulai, $mulai->copy()->addMinutes(30));
        $this->assertSame('INSPEKSI', DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $no)->value('Jenis_Akses'));

        return $no;
    }
}
