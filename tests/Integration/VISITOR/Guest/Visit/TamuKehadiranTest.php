<?php

namespace Tests\Integration\VISITOR\Guest\Visit;

use App\Http\Controllers\VISITOR\Guest\Visit\GuestRegistrationController;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Guest\Visit\VisitorKehadiranQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pencatatan kehadiran tamu.
 *
 * checkIn()/checkOut() TIDAK TERJANGKAU dari HTTP dan itu disengaja: kehadiran
 * dicatat aplikasi palang di gerbang, dan route-nya dicabut pada commit
 * d641d350 lalu dijaga VisitorRouteContractTest.
 *
 * Uji ini tetap ada untuk menjaga penjaga di dalam kedua method itu — kalau
 * suatu saat jalur manual dihidupkan sebagai cadangan, aturannya harus sudah
 * benar sejak awal: tidak ada jam keluar tanpa jam masuk, dan jam masuk asli
 * tidak boleh tertimpa.
 *
 * Sejak DDL-2026-121 kedua method itu TIDAK lagi menulis Status_Kunjungan:
 * kolom itu status keputusan (SCHEDULED dst.), sedangkan CHECKED_IN/CHECKED_OUT
 * adalah status TAMPIL yang diturunkan dari jam masuk/keluar.
 */
class TamuKehadiranTest extends TestCase
{
    use DatabaseTransactions;

    public function test_check_in_mencatat_jam_masuk_dan_status_tampil(): void
    {
        [$id, $noTransaksi] = $this->buatTamu(StatusKunjunganTamu::SCHEDULED);

        $response = $this->controller()->checkIn(Request::create('/', 'POST'), (string) $id);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($this->detail($noTransaksi)->Waktu_Aktual_Masuk);
        // DDL-2026-121: status tersimpan tetap keputusan; CHECKED_IN diturunkan.
        $this->assertSame(StatusKunjunganTamu::SCHEDULED, $this->header($id)->Status_Kunjungan);
        $this->assertSame(StatusKunjunganTamu::CHECKED_IN, $this->statusTampil($noTransaksi));
    }

    public function test_check_in_kedua_ditolak_dan_jam_masuk_asli_tidak_berubah(): void
    {
        [$id, $noTransaksi] = $this->buatTamu(StatusKunjunganTamu::SCHEDULED);
        $this->controller()->checkIn(Request::create('/', 'POST'), (string) $id);
        $jamAsli = $this->detail($noTransaksi)->Waktu_Aktual_Masuk;

        $response = $this->controller()->checkIn(Request::create('/', 'POST'), (string) $id);

        // Menimpa jam masuk adalah kehilangan data yang senyap: tak ada galat,
        // tak ada jejak, jam kedatangan sebenarnya lenyap.
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame($jamAsli, $this->detail($noTransaksi)->Waktu_Aktual_Masuk);
    }

    public function test_check_out_tanpa_check_in_ditolak(): void
    {
        [$id, $noTransaksi] = $this->buatTamu(StatusKunjunganTamu::SCHEDULED);

        $response = $this->controller()->checkOut(Request::create('/', 'POST'), (string) $id);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertNull(
            $this->detail($noTransaksi)->Waktu_Aktual_Keluar,
            'Jam keluar tanpa jam masuk adalah riwayat yang mustahil.',
        );
        $this->assertSame(StatusKunjunganTamu::SCHEDULED, $this->header($id)->Status_Kunjungan);
        $this->assertSame(StatusKunjunganTamu::SCHEDULED, $this->statusTampil($noTransaksi));
    }

    public function test_check_out_sesudah_check_in_mencatat_jam_keluar(): void
    {
        [$id, $noTransaksi] = $this->buatTamu(StatusKunjunganTamu::SCHEDULED);
        $this->controller()->checkIn(Request::create('/', 'POST'), (string) $id);

        $response = $this->controller()->checkOut(Request::create('/', 'POST'), (string) $id);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($this->detail($noTransaksi)->Waktu_Aktual_Keluar);
        // DDL-2026-121: status tersimpan tetap keputusan; CHECKED_OUT diturunkan.
        $this->assertSame(StatusKunjunganTamu::SCHEDULED, $this->header($id)->Status_Kunjungan);
        $this->assertSame(StatusKunjunganTamu::CHECKED_OUT, $this->statusTampil($noTransaksi));
    }

    public function test_check_out_kedua_ditolak(): void
    {
        [$id, $noTransaksi] = $this->buatTamu(StatusKunjunganTamu::SCHEDULED);
        $this->controller()->checkIn(Request::create('/', 'POST'), (string) $id);
        $this->controller()->checkOut(Request::create('/', 'POST'), (string) $id);
        $jamKeluar = $this->detail($noTransaksi)->Waktu_Aktual_Keluar;

        $response = $this->controller()->checkOut(Request::create('/', 'POST'), (string) $id);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame($jamKeluar, $this->detail($noTransaksi)->Waktu_Aktual_Keluar);
    }

    public function test_tamu_yang_belum_disetujui_tidak_bisa_check_in(): void
    {
        config(['services.visitor.tamu.check_in_requires_approval' => true]);
        [$id, $noTransaksi] = $this->buatTamu(StatusKunjunganTamu::PENDING_APPROVAL, needsApproval: 'Y');

        $response = $this->controller()->checkIn(Request::create('/', 'POST'), (string) $id);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertNull($this->detail($noTransaksi)->Waktu_Aktual_Masuk);
    }

    public function test_lewat_jam_di_hari_yang_sama_tetap_scheduled_dan_baru_cancelled_saat_lewat_tanggal(): void
    {
        [$id, $noTransaksi] = $this->buatTamu(StatusKunjunganTamu::SCHEDULED);

        // Kasus 1: Jadwal hari ini jam 08:00 - 10:00 (sudah lewat jam estimasi keluar tapi masih tanggal yang sama)
        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $noTransaksi)->update([
            'Waktu_Mulai_Kunjungan' => now()->startOfDay()->addHours(8),
            'Waktu_Selesai_Kunjungan' => now()->startOfDay()->addHours(10),
        ]);
        $this->assertSame(StatusKunjunganTamu::SCHEDULED, $this->statusTampil($noTransaksi));

        // Kasus 2: Jadwal kemarin (sudah lewat tanggal kalender)
        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $noTransaksi)->update([
            'Waktu_Mulai_Kunjungan' => now()->subDay()->startOfDay()->addHours(8),
            'Waktu_Selesai_Kunjungan' => now()->subDay()->startOfDay()->addHours(10),
        ]);
        $this->assertSame(StatusKunjunganTamu::CANCELLED, $this->statusTampil($noTransaksi));
    }

    // ---------------------------------------------------------------- helpers

    private function controller(): GuestRegistrationController
    {
        return app(GuestRegistrationController::class);
    }

    private function header(int $id): object
    {
        return DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('Id_Transaksi_Tamu', $id)->first();
    }

    private function detail(string $noTransaksi): object
    {
        return DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $noTransaksi)->first();
    }

    /** Status tampil lewat satu definisinya (VisitorKehadiranQueryService). */
    private function statusTampil(string $noTransaksi): string
    {
        $kehadiran = app(VisitorKehadiranQueryService::class);

        return (string) $kehadiran->join(
            DB::table('N_EMI_VISITOR_Transaksi_Tamu as h')
                ->join('N_EMI_VISITOR_Transaksi_Tamu_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
                ->where('h.No_Transaksi', $noTransaksi)
        )->value(DB::raw($kehadiran->siklusKunjunganSql()));
    }

    /**
     * @return array{0:int,1:string}
     */
    private function buatTamu(string $status, string $needsApproval = 'T'): array
    {
        $noTransaksi = 'VSR-KHD-'.Str::upper(Str::random(10));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $noTransaksi,
            'Kode_Karyawan_PIC' => 'ZZKHD1',
            'Status_Kunjungan' => $status,
            'Tanggal_Kunjungan' => now()->toDateString(),
            'Needs_Approval' => $needsApproval,
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => 'ZZKHD1',
            'Idempotency_Key' => 'KHD-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'No_Transaksi' => $noTransaksi,
            'Id_Transaksi_Tamu' => $id,
            'Nama_Lengkap_Tamu' => 'Tamu Uji Kehadiran',
            'Jumlah_Tamu' => 1,
        ]);

        return [(int) $id, $noTransaksi];
    }
}
