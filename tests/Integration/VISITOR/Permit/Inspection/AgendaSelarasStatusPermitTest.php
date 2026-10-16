<?php

namespace Tests\Integration\VISITOR\Permit\Inspection;

use App\Services\VISITOR\Permit\Inspection\JadwalInspeksiService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Agenda inspeksi dan meja verifikator harus menceritakan hal yang sama.
 *
 * Booking hidup lebih lama daripada permitnya. Jendela approval yang lewat
 * tanpa diputus membuat permit terbaca EXPIRED di meja verifikator, tetapi
 * tidak menyentuh booking-nya sama sekali — ia tetap TERJADWAL. Akibatnya
 * bukan sekadar dua layar yang berselisih:
 *
 *  - SHE menunggu orang yang sudah tidak mungkin disetujui;
 *  - slotnya tetap terhitung penuh, sehingga pemohon lain kehilangan tempat
 *    karena pengajuan yang tidak akan pernah berlanjut.
 *
 * Dijaga dari dua sisi karena keduanya memang perlu: penyapu membereskan
 * DATANYA (kuota kembali), dan agenda menyaring saat MEMBACA (benar di antara
 * dua sapuan, yang jaraknya lima menit).
 */
class AgendaSelarasStatusPermitTest extends TestCase
{
    use DatabaseTransactions;

    public function test_agenda_tidak_memuat_booking_yang_permitnya_sudah_mati(): void
    {
        $hidup = $this->permitDenganBooking(hidup: true);
        $mati = $this->permitDenganBooking(hidup: false);

        $tampil = $this->nomorDiAgenda();

        $this->assertContains($hidup, $tampil, 'Pengajuan yang masih berjalan justru ikut hilang.');
        $this->assertNotContains($mati, $tampil, 'Agenda masih memuat pengajuan yang sudah tidak mungkin disetujui.');
    }

    public function test_penyapu_menutup_booking_yang_permitnya_sudah_mati(): void
    {
        // Menyaring saat membaca saja tidak cukup: selama barisnya TERJADWAL,
        // slotnya tetap terhitung penuh bagi pemohon lain.
        $mati = $this->permitDenganBooking(hidup: false);

        Artisan::call('visitor:expire-efektif');

        $this->assertSame(
            JadwalInspeksiService::DIBATALKAN,
            (string) DB::table('N_VISITOR_Inspeksi_Booking')->where('No_Transaksi_Permit', $mati)->value('Status'),
        );
    }

    public function test_penyapu_tidak_menyentuh_booking_yang_permitnya_masih_hidup(): void
    {
        $hidup = $this->permitDenganBooking(hidup: true);

        Artisan::call('visitor:expire-efektif');

        $this->assertSame(
            JadwalInspeksiService::TERJADWAL,
            (string) DB::table('N_VISITOR_Inspeksi_Booking')->where('No_Transaksi_Permit', $hidup)->value('Status'),
            'Pengajuan yang masih berjalan ikut ditutup penyapu.',
        );
    }

    public function test_aturannya_satu_sumber_untuk_kedua_sisi(): void
    {
        // Penyaring yang ditulis dua kali akan menyimpang diam-diam, dan
        // selisihnya justru muncul sebagai bug yang persis sedang diperbaiki.
        $this->assertStringContainsString(
            'masihPantasBerjadwalSql',
            (string) file_get_contents(base_path('app/Http/Controllers/VISITOR/Permit/Inspection/AgendaInspeksiController.php')),
        );
        $this->assertStringContainsString(
            'masihPantasBerjadwalSql',
            (string) file_get_contents(base_path('app/Console/Commands/VISITOR/Operations/Expiration/ExpireVisitorEfektif.php')),
        );
    }

    // ---------------------------------------------------------------- helpers

    /** @return array<int, string> */
    private function nomorDiAgenda(): array
    {
        $permitQuery = app(VisitorPermitQueryService::class);
        $besok = now()->addDay()->startOfDay();

        return DB::table('N_VISITOR_Inspeksi_Booking as b')
            ->join('N_EMI_VISITOR_Transaksi_Permit as h', 'h.No_Transaksi', '=', 'b.No_Transaksi_Permit')
            ->join('N_EMI_VISITOR_Transaksi_Permit_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->whereIn('b.Status', [JadwalInspeksiService::TERJADWAL, JadwalInspeksiService::SELESAI, JadwalInspeksiService::TIDAK_HADIR])
            ->where('b.Waktu_Mulai', '>=', $besok)
            ->where('b.Waktu_Mulai', '<', $besok->copy()->addDay())
            ->whereRaw($permitQuery->masihPantasBerjadwalSql())
            ->pluck('b.No_Transaksi_Permit')
            ->all();
    }

    /**
     * Permit + booking besok. `hidup: false` memakai jendela approval yang
     * SUDAH LEWAT — bentuk paling sering dari "permit mati diam-diam", sebab ia
     * tidak menyentuh Flag_Approval maupun booking-nya.
     */
    private function permitDenganBooking(bool $hidup): string
    {
        $nomor = 'PMT-AGD-'.Str::upper(Str::random(8));
        // Belum resign: permit karyawan yang sudah lewat hari terakhirnya terbaca EXPIRED.
        $pemohon = (string) DB::table('Karyawan')->whereNull('Tanggal_Resign')->orderBy('Kode_Karyawan')->value('Kode_Karyawan');

        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => $pemohon,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => null,
            'Created_At' => now(),
            'Created_By' => $pemohon,
            'Idempotency_Key' => 'AGD-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'No_Transaksi' => $nomor,
            'Id_Transaksi_Permit' => $id,
            'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(100000, 999999),
            'Plat_Nomor_Suffix' => 'AG',
            'Tanggal_Mulai_Berlaku' => now()->toDateString(),
            'Tanggal_Kadaluarsa' => now()->addMonths(3)->toDateString(),
        ]);

        DB::table('HRIS_Approval_Request')->insert([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan' => $pemohon,
            'Request_Order_Flow' => 1,
            'Approver_Karyawan_Kode' => $pemohon,
            'Flag_Approval' => null,
            'Expired_At' => $hidup ? now()->addDays(3) : now()->subDay(),
            'created_at' => now()->subDays(2),
            'update_at' => now()->subDays(2),
        ]);

        DB::table('N_VISITOR_Inspeksi_Booking')->insert([
            'No_Transaksi_Permit' => $nomor,
            'Id_Pos_Inspeksi' => DB::table(JadwalInspeksiService::T_POS)->where('Flag_Aktif', 'Y')->value('Id_Pos_Inspeksi'),
            'Snapshot_Nama_Pos' => 'Pos Uji Agenda',
            'Waktu_Mulai' => now()->addDay()->setTime(10, 0),
            'Waktu_Selesai' => now()->addDay()->setTime(10, 30),
            'Status' => JadwalInspeksiService::TERJADWAL,
            'Created_At' => now(),
            'Created_By' => 'UJI',
        ]);

        return $nomor;
    }
}
