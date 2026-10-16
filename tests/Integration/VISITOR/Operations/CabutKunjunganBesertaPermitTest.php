<?php

namespace Tests\Integration\VISITOR\Operations;

use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Permit\VehiclePermit\PermitCoklatTamuService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\VISITOR\MenerbitkanPermitCoklat;
use Tests\TestCase;

/**
 * Mencabut kunjungan menutup permit COKLAT-nya dalam SATU transaksi, termasuk
 * ketika baris kunjungan di gerbang sudah EXPIRED sementara izin COKLAT-nya
 * (jendela lebih panjang) masih AKTIF (audit 27 Sep 2026, M5).
 */
class CabutKunjunganBesertaPermitTest extends TestCase
{
    use DatabaseTransactions;
    use MenerbitkanPermitCoklat;

    private const PIC = 'A1';

    public function test_cabut_menutup_kunjungan_dan_permit_coklat(): void
    {
        $tamu = $this->kunjungan();
        $noPermit = $this->terbitkanPermitCoklat($tamu, self::PIC);

        $hasil = app(PermitCoklatTamuService::class)->cabutKunjunganBesertaPermit($tamu, 'uji', self::PIC);

        $this->assertSame(['tamu' => 1, 'permit' => 1], $hasil);
        $this->assertSame('DIBATALKAN', DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $tamu)->value('Status_Efektif'));
        $this->assertSame('DIBATALKAN', DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $noPermit)->value('Status_Efektif'));
    }

    public function test_izin_coklat_tetap_dicabut_walau_baris_kunjungannya_sudah_kedaluwarsa(): void
    {
        $tamu = $this->kunjungan();
        $noPermit = $this->terbitkanPermitCoklat($tamu, self::PIC);
        DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $tamu)->update(['Status_Efektif' => 'EXPIRED']);

        $hasil = app(PermitCoklatTamuService::class)->cabutKunjunganBesertaPermit($tamu, 'uji', self::PIC);

        $this->assertSame(['tamu' => 0, 'permit' => 1], $hasil);
        $this->assertSame('DIBATALKAN', DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $noPermit)->value('Status_Efektif'));
    }

    private function kunjungan(): string
    {
        $nomor = 'VSR-CBK-'.Str::upper(Str::random(8));
        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor, 'Kode_Karyawan_PIC' => self::PIC,
            'Status_Kunjungan' => StatusKunjunganTamu::SCHEDULED, 'Flag_Approval' => 'Y',
            'Tanggal_Kunjungan' => now()->toDateString(), 'Flag_Aktif' => 'Y',
            'Created_At' => now(), 'Created_By' => self::PIC, 'Idempotency_Key' => 'CBK-'.Str::upper(Str::random(20)),
        ]);
        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id, 'No_Transaksi' => $nomor, 'Nama_Lengkap_Tamu' => 'Tamu Cabut',
            'Tipe_Tamu' => 'B2B', 'Instansi_Atau_Asal' => 'PT Uji', 'Deksripsi_Keperluan' => 'Meeting',
            'Jumlah_Tamu' => 1, 'Jenis_Kendaraan' => 'Mobil', 'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(10000, 99999), 'Plat_Nomor_Suffix' => 'CK',
        ]);
        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);

        return $nomor;
    }
}
