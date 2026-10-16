<?php

namespace Tests\Integration\VISITOR\Operations;

use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Permit\VehiclePermit\PermitCoklatTamuService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\VISITOR\MenerbitkanPermitCoklat;
use Tests\TestCase;

/**
 * Permit COKLAT yang masih menunggu saat kunjungannya sudah berlalu ditutup
 * otomatis oleh penyapu (celah #2, keputusan user 26 Sep 2026: opsi a).
 * Tamunya tidak akan datang lagi; pengajuan yang hidup terus hanya menambah
 * antrean verifikator.
 */
class PermitCoklatKunjunganBerlaluTest extends TestCase
{
    use DatabaseTransactions;
    use MenerbitkanPermitCoklat;

    /** Kode karyawan NYATA di Web_HRIS development (lihat PermitCoklatBerakhirTest). */
    private const PIC = 'A1';

    public function test_permit_menunggu_ditutup_setelah_kunjungannya_berlalu(): void
    {
        $noPermit = $this->ajukanPermitCoklat($this->kunjungan(now()->subDays(2)));

        Artisan::call('visitor:expire-efektif');

        $this->assertSame('T', DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $noPermit)->value('Flag_Approval'));
        $catatan = DB::table('HRIS_Approval_Request')->where('No_Transaksi', $noPermit)->pluck('Catatan')->filter()->first();
        $this->assertSame(PermitCoklatTamuService::ALASAN_KUNJUNGAN_BERLALU, $catatan);
        $this->assertSame(0, DB::table('HRIS_Approval_Request')->where('No_Transaksi', $noPermit)->whereNull('Flag_Approval')->count());
    }

    public function test_kunjungan_hari_ini_tidak_disentuh(): void
    {
        $noPermit = $this->ajukanPermitCoklat($this->kunjungan(now()));

        Artisan::call('visitor:expire-efektif');

        $this->assertNull(DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $noPermit)->value('Flag_Approval'));
    }

    public function test_kunjungan_yang_diperpanjang_tidak_disentuh(): void
    {
        // Tanggalnya sudah lewat, tetapi estimasi keluarnya diperpanjang ke besok.
        $noPermit = $this->ajukanPermitCoklat($this->kunjungan(now()->subDays(2), now()->subDays(2)->setTime(8, 0), now()->addDay()->setTime(17, 0)));

        Artisan::call('visitor:expire-efektif');

        $this->assertNull(DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $noPermit)->value('Flag_Approval'));
    }

    public function test_permit_yang_sudah_diputus_tidak_disentuh(): void
    {
        $noPermit = $this->terbitkanPermitCoklat($this->kunjungan(now()->subDays(2)), self::PIC);

        Artisan::call('visitor:expire-efektif');

        $this->assertSame('Y', DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $noPermit)->value('Flag_Approval'));
    }

    private function kunjungan(Carbon $tanggal, ?Carbon $mulai = null, ?Carbon $selesai = null): string
    {
        $nomor = 'VSR-BRL-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_PIC' => self::PIC,
            'Status_Kunjungan' => StatusKunjunganTamu::SCHEDULED,
            'Flag_Approval' => 'Y',
            'Tanggal_Kunjungan' => $tanggal->toDateString(),
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Idempotency_Key' => 'BRL-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id,
            'No_Transaksi' => $nomor,
            'Nama_Lengkap_Tamu' => 'Tamu Berlalu',
            'Tipe_Tamu' => 'B2B',
            'Instansi_Atau_Asal' => 'PT Uji',
            'Deksripsi_Keperluan' => 'Meeting',
            'Jumlah_Tamu' => 1,
            'Jenis_Kendaraan' => 'Mobil',
            'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(10000, 99999),
            'Plat_Nomor_Suffix' => 'BR',
            'Waktu_Mulai_Kunjungan' => $mulai?->format('Y-m-d H:i:s'),
            'Waktu_Selesai_Kunjungan' => $selesai?->format('Y-m-d H:i:s'),
        ]);

        return $nomor;
    }
}
