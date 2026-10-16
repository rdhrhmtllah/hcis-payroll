<?php

namespace Tests\Integration\VISITOR\Operations;

use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Permit\VehiclePermit\PermitCoklatTamuService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\VISITOR\MenerbitkanPermitCoklat;
use Tests\TestCase;

/**
 * Masa berlaku izin tamu B2B = jendela permit COKLAT dari admin
 * (keputusan user 29 Sep 2026).
 *
 *  - Usulan jendela berakhir 23:59 di hari terakhir kunjungan, bukan di jam
 *    estimasi keluar. Izin sehari tidak boleh mati siang hari hanya karena
 *    tamunya memperkirakan pulang pukul 12:00.
 *  - Jendela yang DITETAPKAN verifikator berlaku juga untuk akses tamunya:
 *    baris gerbang tamu, status kunjungan, dan penyapu kedaluwarsa. Sebelum
 *    ini kunjungan yang izinnya diperpanjang terbaca "batal"/"kadaluarsa"
 *    begitu tanggal kunjungan aslinya lewat, dan akses gerbang tamunya mati
 *    di jam estimasi keluar kunjungan — padahal permitnya masih AKTIF.
 */
class JendelaAksesTamuIkutPermitTest extends TestCase
{
    use DatabaseTransactions;
    use MenerbitkanPermitCoklat;

    /** Kode karyawan NYATA di Web_HRIS development (Nama_PIC efektif NOT NULL). */
    private const PIC = 'A1';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_usulan_jendela_berakhir_2359_di_hari_terakhir_kunjungan(): void
    {
        $noPermit = $this->ajukanPermitCoklat($this->kunjungan('2026-10-10 08:00:00', '2026-10-10 12:00:00'));

        $this->assertSame('2026-10-10 08:00:00', $this->jendela($noPermit)[0]);
        $this->assertSame('2026-10-10 23:59:00', $this->jendela($noPermit)[1]);
    }

    public function test_estimasi_keluar_tepat_tengah_malam_milik_hari_sebelumnya(): void
    {
        $noPermit = $this->ajukanPermitCoklat($this->kunjungan('2026-10-10 20:00:00', '2026-10-11 00:00:00'));

        $this->assertSame('2026-10-10 23:59:00', $this->jendela($noPermit)[1]);
    }

    public function test_akses_dan_status_tamu_mengikuti_jendela_yang_ditetapkan_admin(): void
    {
        $lalu = now()->subDays(2);
        $noTamu = $this->kunjungan($lalu->copy()->setTime(8, 0)->format('Y-m-d H:i:s'), $lalu->copy()->setTime(12, 0)->format('Y-m-d H:i:s'));
        $noPermit = $this->ajukanPermitCoklat($noTamu);

        // Verifikator memindahkan izinnya: berlaku sampai lusa.
        $akhir = now()->addDays(2)->setTime(17, 0)->format('Y-m-d H:i:s');
        $this->tetapkanJendela($noPermit, $lalu->copy()->setTime(8, 0)->format('Y-m-d H:i:s'), $akhir);
        $this->setujuiPermitCoklat($noPermit, self::PIC);

        $efektif = DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $noTamu)->first();
        $this->assertNotNull($efektif, 'Baris akses gerbang tamunya tidak terbit.');
        $this->assertSame($akhir, substr((string) $efektif->Waktu_Estimasi_Keluar, 0, 19));

        $baris = $this->barisTamu($noTamu);
        $this->assertSame(StatusKunjunganTamu::SCHEDULED, $baris->Status_Kunjungan, 'Kunjungan terbaca batal padahal izinnya masih berlaku.');
        $this->assertSame('AKTIF', $baris->Status_Permit_Coklat);

        Artisan::call('visitor:expire-efektif');

        $this->assertSame('AKTIF', DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $noTamu)->value('Status_Efektif'));
    }

    /** Data yang disetujui sebelum aturan ini diselaraskan oleh command sekali jalan. */
    public function test_command_sinkron_menyelaraskan_data_lama_dan_dry_run_tidak_menulis(): void
    {
        $lalu = now()->subDays(2);
        $noTamu = $this->kunjungan($lalu->copy()->setTime(8, 0)->format('Y-m-d H:i:s'), $lalu->copy()->setTime(12, 0)->format('Y-m-d H:i:s'));
        $noPermit = $this->ajukanPermitCoklat($noTamu);
        $akhir = now()->addDays(2)->setTime(17, 0)->format('Y-m-d H:i:s');
        $this->tetapkanJendela($noPermit, $lalu->copy()->setTime(8, 0)->format('Y-m-d H:i:s'), $akhir);
        $this->setujuiPermitCoklat($noPermit, self::PIC);

        // Keadaan SEBELUM aturan: baris akses masih membawa estimasi kunjungan.
        $lama = $lalu->copy()->setTime(12, 0)->format('Y-m-d H:i:s');
        DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $noTamu)->update(['Waktu_Estimasi_Keluar' => $lama]);

        Artisan::call('visitor:sinkron-akses-tamu-coklat', ['--dry-run' => true, '--kunjungan' => [$noTamu]]);
        $this->assertSame($lama, substr((string) DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $noTamu)->value('Waktu_Estimasi_Keluar'), 0, 19));

        Artisan::call('visitor:sinkron-akses-tamu-coklat', ['--kunjungan' => [$noTamu]]);
        $this->assertSame($akhir, substr((string) DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $noTamu)->value('Waktu_Estimasi_Keluar'), 0, 19));
    }

    public function test_tanpa_perpanjangan_kunjungan_yang_lewat_tetap_berakhir(): void
    {
        $lalu = now()->subDays(2);
        $noTamu = $this->kunjungan($lalu->copy()->setTime(8, 0)->format('Y-m-d H:i:s'), $lalu->copy()->setTime(12, 0)->format('Y-m-d H:i:s'));
        $this->terbitkanPermitCoklat($noTamu, self::PIC);

        $this->assertSame(StatusKunjunganTamu::CANCELLED, $this->barisTamu($noTamu)->Status_Kunjungan);

        Artisan::call('visitor:expire-efektif');

        $this->assertSame('EXPIRED', DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $noTamu)->value('Status_Efektif'));
    }

    public function test_permit_menunggu_masih_bisa_diputus_sampai_2359_hari_itu(): void
    {
        $noPermit = $this->ajukanPermitCoklat($this->kunjungan('2026-10-10 08:00:00', '2026-10-10 12:00:00'));
        $layanan = app(PermitCoklatTamuService::class);

        // Lewat estimasi keluar tamunya, tetapi masih di hari yang sama.
        Carbon::setTestNow(Carbon::parse('2026-10-10 15:00:00'));
        $layanan->tutupKunjunganBerlalu();
        $this->assertNull(DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $noPermit)->value('Flag_Approval'));

        Carbon::setTestNow(Carbon::parse('2026-10-11 00:01:00'));
        $layanan->tutupKunjunganBerlalu();
        $this->assertSame('T', DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $noPermit)->value('Flag_Approval'));
    }

    /** @return array{0: string, 1: string} */
    private function jendela(string $noPermit): array
    {
        $detail = DB::table(PermitCoklatTamuService::T_PERMIT_DETAIL)->where('No_Transaksi', $noPermit)->first();

        return [substr((string) $detail->Waktu_Mulai_Berlaku, 0, 19), substr((string) $detail->Waktu_Berakhir, 0, 19)];
    }

    private function tetapkanJendela(string $noPermit, string $mulai, string $akhir): void
    {
        DB::table(PermitCoklatTamuService::T_PERMIT_DETAIL)->where('No_Transaksi', $noPermit)->update([
            'Waktu_Mulai_Berlaku' => $mulai,
            'Waktu_Berakhir' => $akhir,
            'Tanggal_Mulai_Berlaku' => substr($mulai, 0, 10),
            'Tanggal_Kadaluarsa' => substr($akhir, 0, 10),
        ]);
    }

    private function barisTamu(string $noTamu): object
    {
        $query = app(VisitorPermitQueryService::class);

        return $query->baseTamuQuery()
            ->where('h.No_Transaksi', $noTamu)
            ->addSelect(DB::raw($query->statusPermitKunjunganSql('h.No_Transaksi', 'PROSES').' as Status_Permit_Coklat'))
            ->first();
    }

    private function kunjungan(string $mulai, string $selesai): string
    {
        $nomor = 'VSR-JDL-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_PIC' => self::PIC,
            'Status_Kunjungan' => StatusKunjunganTamu::SCHEDULED,
            'Flag_Approval' => 'Y',
            'Tanggal_Kunjungan' => substr($mulai, 0, 10),
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Idempotency_Key' => 'JDL-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id,
            'No_Transaksi' => $nomor,
            'Nama_Lengkap_Tamu' => 'Tamu Jendela',
            'Tipe_Tamu' => 'B2B',
            'Instansi_Atau_Asal' => 'PT Uji',
            'Deksripsi_Keperluan' => 'Meeting',
            'Jumlah_Tamu' => 1,
            'Jenis_Kendaraan' => 'Mobil',
            'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(10000, 99999),
            'Plat_Nomor_Suffix' => 'JD',
            'Waktu_Mulai_Kunjungan' => $mulai,
            'Waktu_Selesai_Kunjungan' => $selesai,
        ]);

        return $nomor;
    }
}
