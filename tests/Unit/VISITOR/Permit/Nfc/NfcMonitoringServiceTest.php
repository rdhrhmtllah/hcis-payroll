<?php

namespace Tests\Unit\VISITOR\Permit\Nfc;

use App\Services\VISITOR\Permit\Nfc\NfcMonitoringService;
use PHPUnit\Framework\TestCase;

/**
 * Uji logika klasifikasi status dan filter pada layanan monitoring NFC.
 */
class NfcMonitoringServiceTest extends TestCase
{
    private NfcMonitoringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NfcMonitoringService();
    }

    public function test_mengklasifikasikan_status_kartu_sesuai_aturan_bisnis(): void
    {
        // 1. Kartu normal tervalidasi dan aktif
        $rowNormal = (object) [
            'master_aktif' => 'Y',
            'binding_aktif' => 'Y',
            'flag_validasi_sistem' => 'Y',
            'pemegang_lain' => 0,
        ];
        $this->assertSame('TERDAFTAR', $this->service->klasifikasiStatus($rowNormal));

        // 2. Kartu nonaktif / diblokir
        $rowBlokir = (object) [
            'master_aktif' => 'T',
            'binding_aktif' => 'Y',
            'flag_validasi_sistem' => 'Y',
            'pemegang_lain' => 0,
        ];
        $this->assertSame('KARTU_DIBLOKIR', $this->service->klasifikasiStatus($rowBlokir));

        // 3. Menunggu validasi sistem
        $rowBelumValid = (object) [
            'master_aktif' => 'Y',
            'binding_aktif' => 'Y',
            'flag_validasi_sistem' => 'T',
            'pemegang_lain' => 0,
        ];
        $this->assertSame('MENUNGGU_VALIDASI', $this->service->klasifikasiStatus($rowBelumValid));

        // 4. Kartu ganda (lebih dari 1 pemegang aktif)
        $rowGanda = (object) [
            'master_aktif' => 'Y',
            'binding_aktif' => 'Y',
            'flag_validasi_sistem' => 'Y',
            'pemegang_lain' => 1,
        ];
        $this->assertSame('KARTU_GANDA', $this->service->klasifikasiStatus($rowGanda));
    }

    public function test_menentukan_kategori_pemegang_karyawan_vs_phl(): void
    {
        $karyawan = (object) ['no_mitra' => null, 'nik' => '2502118'];
        $this->assertSame('KARYAWAN', $this->service->kategoriPemegang($karyawan));

        $phl = (object) ['no_mitra' => '03-V067', 'nik' => null];
        $this->assertSame('PHL', $this->service->kategoriPemegang($phl));
    }

    public function test_suggest_karyawan_mengabaikan_kueri_terlalu_pendek(): void
    {
        $this->assertEmpty($this->service->suggestKaryawan(''));
        $this->assertEmpty($this->service->suggestKaryawan('a'));
    }

    public function test_pairing_nonaktif_tidak_terbaca_kartu_ganda(): void
    {
        // Kartu sudah dipindah ke pemegang baru: baris lama nonaktif, bukan anomali.
        $row = (object) [
            'master_aktif' => 'Y',
            'binding_aktif' => 'T',
            'flag_validasi_sistem' => 'Y',
            'pemegang_lain' => 1,
        ];
        $this->assertSame('PAIRING_NONAKTIF', $this->service->klasifikasiStatus($row));
    }

    public function test_kata_kunci_pencarian_di_escape_untuk_like_sql_server(): void
    {
        $this->assertSame('03[_]V[%]1[[]', NfcMonitoringService::escapeLike('03_V%1['));
    }
}
