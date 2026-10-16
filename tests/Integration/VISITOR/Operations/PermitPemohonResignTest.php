<?php

namespace Tests\Integration\VISITOR\Operations;

use App\Console\Commands\VISITOR\Operations\Expiration\ExpireVisitorEfektif;
use App\Jobs\VISITOR\Workflow\Notification\PublishVisitorNotificationsJob;
use App\Services\VISITOR\Permit\Inspection\JadwalInspeksiService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Permit karyawan berakhir bersama hari kerja terakhirnya (Tanggal_Resign).
 *
 * Akses masih berlaku sepanjang hari resign dan berhenti esoknya. Izin tamu
 * dan permit mitra yang hanya menumpang nama PIC tidak ikut mati.
 */
class PermitPemohonResignTest extends TestCase
{
    use DatabaseTransactions;

    /** Karyawan NYATA di Web_HRIS development yang belum resign. */
    private const PEMOHON = 'B6';

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertNull(
            DB::table('Karyawan')->where('Kode_Karyawan', self::PEMOHON)->value('Tanggal_Resign'),
            'Karyawan uji sudah resign di dev; fixture perlu ditinjau.',
        );
    }

    public function test_akses_berlaku_sampai_akhir_hari_resign(): void
    {
        $no = $this->permitDisetujui();

        $this->resign(now());
        $this->assertSame('APPROVED', $this->statusPermit($no), 'Akses mati pada hari kerja terakhirnya sendiri.');

        $this->resign(now()->subDay());
        $this->assertSame('EXPIRED', $this->statusPermit($no));
    }

    public function test_penyapu_mematikan_akses_gerbang_dan_mengunci_pengajuan(): void
    {
        Bus::fake([PublishVisitorNotificationsJob::class]);
        $aktif = $this->permitDisetujui();
        $menunggu = $this->permitMenunggu();
        $this->resign(now()->subDays(2));

        $this->artisan('visitor:expire-efektif')->assertExitCode(0);

        $akses = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $aktif)->first();
        $this->assertSame('EXPIRED', $akses->Status_Efektif);
        $this->assertSame(ExpireVisitorEfektif::ALASAN_RESIGN, $akses->Alasan_Status);
        $this->assertSame('T', $akses->Flag_Sync, 'Palang tidak menarik ulang statusnya.');

        $langkah = DB::table('HRIS_Approval_Request')->where('No_Transaksi', $menunggu)->first();
        $this->assertSame(now()->subDays(2)->format('Y-m-d').' 23:59:59', Carbon::parse($langkah->Expired_At)->format('Y-m-d H:i:s'));
        $this->assertSame(ExpireVisitorEfektif::EXPIRED_OLEH_RESIGN, $langkah->Expired_by);
        $this->assertSame('EXPIRED', $this->statusPermit($menunggu));
        $this->assertTrue(app(JadwalInspeksiService::class)->approvalKedaluwarsa($menunggu), 'Penjaga approve/batal/pindah tidak ikut menolak.');

        // Orang yang sudah keluar tidak dikirimi kabar "permit kedaluwarsa".
        Bus::assertNotDispatched(PublishVisitorNotificationsJob::class, fn ($job) => collect($job->events)
            ->contains(fn ($e) => $e['no_transaksi'] === $menunggu));
    }

    public function test_izin_tamu_atas_nama_pic_yang_resign_tidak_ikut_mati(): void
    {
        $tamu = $this->permitDisetujui(noTransaksiTamu: 'VSR-UJI-'.Str::upper(Str::random(6)), sumberEfektif: 'TAMU');
        $this->resign(now()->subDay());

        $this->artisan('visitor:expire-efektif')->assertExitCode(0);

        $this->assertSame('APPROVED', $this->statusPermit($tamu));
        $this->assertSame('AKTIF', DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $tamu)->value('Status_Efektif'));
    }

    public function test_karyawan_resign_tidak_bisa_mengajukan(): void
    {
        $query = app(VisitorPermitQueryService::class);
        $this->assertFalse($query->pemohonSudahResign(self::PEMOHON));

        $this->resign(now()->subDay());
        $this->assertTrue($query->pemohonSudahResign(self::PEMOHON));
    }

    private function resign(\DateTimeInterface $tanggal): void
    {
        DB::table('Karyawan')->where('Kode_Karyawan', self::PEMOHON)
            ->update(['Tanggal_Resign' => Carbon::instance($tanggal)->startOfDay()]);
    }

    private function statusPermit(string $no): string
    {
        $q = app(VisitorPermitQueryService::class);

        return DB::table('N_EMI_VISITOR_Transaksi_Permit as h')
            ->leftJoin('N_EMI_VISITOR_Transaksi_Permit_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->where('h.No_Transaksi', $no)
            ->value(DB::raw($q->permitStatusSql().' as s'));
    }

    private function permitDisetujui(?string $noTransaksiTamu = null, string $sumberEfektif = 'KARYAWAN'): string
    {
        $no = $this->header('Y', $noTransaksiTamu);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->where('No_Transaksi', $no)->update([
            'Tanggal_Mulai_Berlaku' => now()->subMonth()->toDateString(),
            'Tanggal_Kadaluarsa' => now()->addMonths(5)->toDateString(),
        ]);

        $contoh = DB::table('N_VISITOR_Permit_Efektif')->orderByDesc('Id_Permit_Efektif')->first();
        $this->assertNotNull($contoh, 'Butuh minimal satu baris Permit_Efektif di dev sebagai cetakan kolom wajib.');
        $baris = (array) $contoh;
        unset($baris['Id_Permit_Efektif']);
        DB::table('N_VISITOR_Permit_Efektif')->insert(array_merge($baris, [
            'No_Transaksi' => $no,
            'Kode_Karyawan_Pemohon' => self::PEMOHON,
            'Status_Efektif' => 'AKTIF',
            'Alasan_Status' => null,
            'Sumber_Permit' => $sumberEfektif,
            'Jenis_Akses' => 'PERMIT',
            'Waktu_Mulai_Berlaku' => null,
            'Waktu_Berakhir' => null,
            'Tanggal_Kadaluarsa' => now()->addMonths(5)->toDateString(),
            'Flag_Sync' => 'Y',
            'NFC_Inbio' => null,
        ]));

        return $no;
    }

    private function permitMenunggu(): string
    {
        $no = $this->header(null, null);

        DB::table('HRIS_Approval_Request')->insert([
            'No_Transaksi' => $no,
            'Kode_Karyawan' => self::PEMOHON,
            'Request_Order_Flow' => 1,
            'Flag_Approval' => null,
            'Expired_At' => now()->addDays(3),
            'created_at' => now(),
            'update_at' => now(),
        ]);

        return $no;
    }

    private function header(?string $flagApproval, ?string $noTransaksiTamu): string
    {
        $no = 'PMT-UJI-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $no,
            'No_Transaksi_Tamu' => $noTransaksiTamu,
            'Kode_Karyawan_Pemohon' => self::PEMOHON,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => $flagApproval,
            'Sumber_Pengajuan' => 'HCIS',
            'Created_At' => now(),
            'Created_By' => self::PEMOHON,
            'Updated_At' => now(),
            'Idempotency_Key' => 'UJI-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'Id_Transaksi_Permit' => $id,
            'No_Transaksi' => $no,
            'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(10000, 99999),
            'Plat_Nomor_Suffix' => 'RS',
        ]);

        return $no;
    }
}
