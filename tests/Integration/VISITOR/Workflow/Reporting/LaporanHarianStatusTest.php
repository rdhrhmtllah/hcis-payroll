<?php

namespace Tests\Integration\VISITOR\Workflow\Reporting;

use App\Http\Controllers\VISITOR\Workflow\Reporting\LaporanVisitorController;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Laporan Harian memakai status siklus kunjungan, bukan status gerbang mentah
 * (AKTIF/EXPIRED/DIBATALKAN) yang tak bermakna bagi pembacanya (audit M10).
 */
class LaporanHarianStatusTest extends TestCase
{
    use DatabaseTransactions;

    public function test_status_tamu_di_laporan_harian_adalah_kosakata_siklus(): void
    {
        $request = Request::create('/laporanvisitor-harian', 'GET', [
            'date_from' => now()->subDays(30)->toDateString(),
            'date_to' => now()->toDateString(),
            'kategori' => 'Tamu',
        ]);

        $isi = json_decode(app(LaporanVisitorController::class)->getLaporanHarian($request)->getContent(), true);
        $baris = collect($isi['result']['data'] ?? $isi['result'] ?? []);

        if ($baris->isEmpty()) {
            $this->markTestSkipped('Tidak ada kunjungan 30 hari terakhir di dev.');
        }

        foreach ($baris->pluck('status')->unique() as $status) {
            $this->assertContains($status, StatusKunjunganTamu::TAMPIL, "Status mentah bocor ke Laporan Harian: {$status}");
        }
    }

    public function test_permit_yang_sejak_itu_kedaluwarsa_tetap_ada_di_laporan_historis(): void
    {
        // Laporan masa lalu tidak boleh kehilangan permit hanya karena status
        // SEKARANG-nya bukan APPROVED lagi (audit ulang 28 Sep).
        $no = DB::table('N_EMI_VISITOR_Transaksi_Permit as h')
            ->join('N_EMI_VISITOR_Transaksi_Permit_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->where('h.Flag_Aktif', 'Y')->where('h.Flag_Approval', 'Y')
            ->whereNotNull('d.Tanggal_Mulai_Berlaku')
            ->whereRaw(app(VisitorPermitQueryService::class)->permitStatusSql()." IN ('EXPIRED','REVOKED')")
            ->select('h.No_Transaksi', 'd.Tanggal_Mulai_Berlaku')
            ->first();
        if (! $no) {
            $this->markTestSkipped('Tidak ada permit disetujui yang sudah kedaluwarsa/dicabut di dev.');
        }

        $tanggal = substr((string) $no->Tanggal_Mulai_Berlaku, 0, 10);
        $request = Request::create('/laporanvisitor-harian', 'GET', [
            'date_from' => $tanggal, 'date_to' => $tanggal, 'kategori' => 'Kendaraan Karyawan', 'limit' => 500,
        ]);
        $isi = json_decode(app(LaporanVisitorController::class)->getLaporanHarian($request)->getContent(), true);
        $baris = collect($isi['result']['data'] ?? $isi['result'] ?? []);

        $this->assertNotEmpty($baris, 'Permit yang sempat berlaku pada tanggal itu hilang dari laporan historis.');
    }
}
