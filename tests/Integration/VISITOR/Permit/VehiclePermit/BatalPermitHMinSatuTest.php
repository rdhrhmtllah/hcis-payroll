<?php

namespace Tests\Integration\VISITOR\Permit\VehiclePermit;

use App\Http\Controllers\VISITOR\Permit\VehiclePermit\VehiclePermitController;
use App\Models\ESS\Employee\Models\User;
use App\Services\VISITOR\Permit\Inspection\JadwalInspeksiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pemohon membatalkan permitnya paling lambat H-1 sebelum jadwal inspeksi,
 * sama seperti jadwal ulang. Pada hari H servernya menolak walau tombolnya
 * sempat terlihat di layar yang belum dimuat ulang.
 */
class BatalPermitHMinSatuTest extends TestCase
{
    use DatabaseTransactions;

    /** Kode karyawan NYATA di Web_HRIS development. */
    private const PIC = 'A1';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_pembatalan_pada_hari_h_ditolak(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-22 07:00'));
        [$nomor, $id] = $this->buatPermitBerjadwal('2026-09-22 08:00');

        $respons = $this->batalkan($id);

        $this->assertSame(409, $respons->getStatusCode());
        $this->assertSame('VISITOR_PERMIT_CANCEL_LOCKED', json_decode($respons->getContent(), true)['error_code'] ?? null);
        $this->assertSame('Y', DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $nomor)->value('Flag_Aktif'));
        $this->assertSame(JadwalInspeksiService::TERJADWAL, DB::table('N_VISITOR_Inspeksi_Booking')
            ->where('No_Transaksi_Permit', $nomor)->value('Status'), 'Kursi slotnya ikut dilepas walau pembatalan ditolak.');
    }

    public function test_pembatalan_h_min_1_tetap_bisa(): void
    {
        // Penjaga yang terlalu lebar akan mengunci pembatalan untuk semua orang.
        Carbon::setTestNow(Carbon::parse('2026-09-21 23:59'));
        [$nomor, $id] = $this->buatPermitBerjadwal('2026-09-22 08:00');

        $this->assertSame(200, $this->batalkan($id)->getStatusCode());
        $this->assertSame('N', DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $nomor)->value('Flag_Aktif'));
    }

    public function test_pengajuan_kedaluwarsa_tidak_bisa_dibatalkan_maupun_dipindah(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-24 08:00'));
        [$nomor, $id] = $this->buatPermitBerjadwal('2026-09-22 08:00', '2026-09-23 23:59:59');

        $batal = $this->batalkan($id);
        $this->assertSame(409, $batal->getStatusCode());
        $this->assertSame('VISITOR_PERMIT_EXPIRED', json_decode($batal->getContent(), true)['error_code'] ?? null);
        $this->assertSame('Y', DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $nomor)->value('Flag_Aktif'));

        $request = Request::create('/transaksipermit/'.$id.'/pindah-inspeksi', 'POST', [
            'inspeksi_id_pos' => 1, 'inspeksi_mulai' => '2026-09-28 08:00',
        ]);
        $request->setUserResolver(fn () => $this->user());
        $pindah = app(VehiclePermitController::class)->pindahInspeksi($request, (string) $id);

        $this->assertSame(409, $pindah->getStatusCode());
        $this->assertSame('VISITOR_PERMIT_EXPIRED', json_decode($pindah->getContent(), true)['error_code'] ?? null);
    }

    private function batalkan(int $id)
    {
        $request = Request::create('/transaksipermit/'.$id.'/cancel', 'POST');
        $request->setUserResolver(fn () => $this->user());

        return app(VehiclePermitController::class)->cancel($request, (string) $id);
    }

    private function user(): User
    {
        $userId = DB::table('Karyawan')->where('Kode_Karyawan', self::PIC)->value('UserID_Web');
        $user = User::find($userId);

        $this->assertNotNull($user, 'Karyawan '.self::PIC.' tidak punya baris login; fixture perlu ditinjau.');

        return $user;
    }

    /** @return array{0:string,1:int} */
    private function buatPermitBerjadwal(string $mulai, ?string $expiredAt = null): array
    {
        $nomor = 'PMT-UJI-'.Str::upper(Str::random(8));

        $id = (int) DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => self::PIC,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => null,
            'Sumber_Pengajuan' => 'HCIS',
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Updated_At' => now(),
            'Idempotency_Key' => 'UJI-'.Str::upper(Str::random(20)),
        ]);

        DB::table('HRIS_Approval_Request')->insert([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan' => self::PIC,
            'Request_Order_Flow' => 1,
            'Approver_Karyawan_Kode' => self::PIC,
            'Flag_Approval' => null,
            'Expired_At' => $expiredAt ? Carbon::parse($expiredAt) : now()->addDays(3),
            'created_at' => now(),
            'update_at' => now(),
        ]);

        $pos = DB::table(JadwalInspeksiService::T_POS)->first();
        $this->assertNotNull($pos, 'Butuh minimal satu pos inspeksi di dev.');

        DB::table('N_VISITOR_Inspeksi_Booking')->insert([
            'No_Transaksi_Permit' => $nomor,
            'Id_Pos_Inspeksi' => $pos->Id_Pos_Inspeksi,
            'Snapshot_Nama_Pos' => $pos->Nama_Pos,
            'Waktu_Mulai' => Carbon::parse($mulai),
            'Waktu_Selesai' => Carbon::parse($mulai)->addMinutes(30),
            'Status' => JadwalInspeksiService::TERJADWAL,
            'Created_At' => now(),
            'Created_By' => self::PIC,
        ]);

        return [$nomor, $id];
    }
}
