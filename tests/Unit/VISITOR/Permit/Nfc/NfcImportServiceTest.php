<?php

namespace Tests\Unit\VISITOR\Permit\Nfc;

use App\Services\VISITOR\Permit\Nfc\NfcImportService;
use PHPUnit\Framework\TestCase;

/**
 * Uji logika proses import NFC: validasi masukan, pemetaan kolom, dan struktur payload.
 */
class NfcImportServiceTest extends TestCase
{
    public function test_memvalidasi_tipe_yang_diterima(): void
    {
        $service = new NfcImportService();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tipe impor tidak valid');

        $service->import([], 'asing', 'UJI');
    }

    public function test_mengabaikan_baris_tanpa_hex_atau_tanpa_identitas(): void
    {
        $service = new NfcImportService();

        $rows = [
            ['nfc_hex' => null, 'nfc_inbio' => 12345, 'kode_karyawan' => 'ABC', 'nama' => 'Test'],
            ['nfc_hex' => 'ABCD1234', 'nfc_inbio' => 12345, 'kode_karyawan' => '', 'nama' => ''],
        ];

        // Seharusnya tidak melempar eksepsi dan mengembalikan hitungan 0
        $hasil = $service->filterValidRows($rows);
        $this->assertEmpty($hasil);
    }

    public function test_menjaga_tipe_dan_label_tetap_null(): void
    {
        $service = new NfcImportService();
        $payload = $service->formatMasterPayload('9BDAC6A6', 13032091, 'UJI');

        $this->assertNull($payload['tipe']);
        $this->assertNull($payload['label']);
        $this->assertSame('9BDAC6A6', $payload['nfc_hex']);
        $this->assertSame(13032091, $payload['nfc_inbio']);
        $this->assertSame('Y', $payload['flag_validasi_sistem']);
        $this->assertSame('Y', $payload['flag_aktif']);
    }

    public function test_memvalidasi_data_manual_sebelum_disimpan(): void
    {
        $service = new NfcImportService();

        // Hex kosong harus ditolak
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('NFC HEX wajib diisi');

        $service->validasiManual([
            'tipe' => 'karyawan',
            'kode_karyawan' => 'K123',
            'nama' => 'Budi',
            'nfc_hex' => '',
        ]);
    }

    public function test_membuat_pratinjau_analisis_impor(): void
    {
        $service = new NfcImportService();
        $valid = [
            ['nfc_hex' => '9BDAC6A6', 'nfc_inbio' => 13032091, 'kode_karyawan' => 'AALAMSYAH', 'nama' => 'ALAMSYAH', 'nik' => '2502118', 'no_mitra' => null],
            ['nfc_hex' => 'FB5CF2A6', 'nfc_inbio' => 15883515, 'kode_karyawan' => 'ABADII', 'nama' => 'ABADI', 'nik' => '2502431', 'no_mitra' => null],
        ];
        $skipped = [
            ['nfc_hex' => null, 'nfc_inbio' => null, 'kode_karyawan' => 'ABDUL', 'nama' => 'ABDUL RACHMAN', 'nik' => '2501061', 'no_mitra' => null],
        ];

        $pratinjau = $service->pratinjau($valid, $skipped, 3, NfcImportService::konteksKosong());

        $this->assertSame(3, $pratinjau['total_baris']);
        $this->assertSame(2, $pratinjau['jumlah_siap_impor']);
        $this->assertSame(0, $pratinjau['jumlah_konflik_aktif']);
        $this->assertSame(1, $pratinjau['jumlah_tanpa_nfc']);
        $this->assertCount(2, $pratinjau['cuplikan_data']);
        $this->assertCount(0, $pratinjau['cuplikan_konflik']);
        $this->assertCount(1, $pratinjau['cuplikan_tanpa_nfc']);
    }

    public function test_mendeteksi_konflik_pemegang_lain_pada_kartu_yang_sama(): void
    {
        $service = new NfcImportService();

        $bindingLama = (object) [
            'id' => 1,
            'id_nfc' => 10,
            'nama' => 'Budi Santoso',
            'kode_karyawan' => 'K001',
            'nik' => '1001',
            'no_mitra' => null,
            'flag_aktif' => 'Y',
        ];

        // 1. Orang yang sama (kode_karyawan cocok) -> BUKAN konflik
        $rowSama = ['kode_karyawan' => 'K001', 'nama' => 'Budi Santoso', 'nik' => '1001', 'no_mitra' => null];
        $this->assertFalse($service->apakahKonflikPemegangLain($bindingLama, $rowSama));

        // 2. Orang berbeda (kode_karyawan beda) -> KONFLIK
        $rowBeda = ['kode_karyawan' => 'K002', 'nama' => 'Ahmad', 'nik' => '1002', 'no_mitra' => null];
        $this->assertTrue($service->apakahKonflikPemegangLain($bindingLama, $rowBeda));

        // 3. Dipasangkan ke mitra padahal masih aktif di karyawan -> KONFLIK
        $rowMitra = ['kode_karyawan' => null, 'nama' => 'Vendor A', 'nik' => null, 'no_mitra' => '03-V001'];
        $this->assertTrue($service->apakahKonflikPemegangLain($bindingLama, $rowMitra));
    }

    public function test_mendeteksi_baris_sudah_terdaftar_identik(): void
    {
        $service = new NfcImportService();

        $master = (object) [
            'id' => 10,
            'nfc_hex' => '9BDAC6A6',
            'nfc_inbio' => 13032091,
            'flag_aktif' => 'Y',
            'flag_validasi_sistem' => 'Y',
        ];

        $binding = (object) [
            'id' => 1,
            'id_nfc' => 10,
            'nama' => 'ALAMSYAH',
            'kode_karyawan' => 'AALAMSYAH',
            'nik' => '2502118',
            'no_mitra' => null,
            'flag_aktif' => 'Y',
        ];

        // 1. Data persis sama -> IDENTIK
        $rowSama = [
            'nfc_hex' => '9BDAC6A6',
            'nfc_inbio' => 13032091,
            'kode_karyawan' => 'AALAMSYAH',
            'nama' => 'ALAMSYAH',
            'nik' => '2502118',
            'no_mitra' => null,
        ];
        $this->assertTrue($service->apakahIdentik($binding, $master, $rowSama));

        // 2. HEX beda -> BUKAN identik
        $rowBedaHex = $rowSama;
        $rowBedaHex['nfc_hex'] = 'FB5CF2A6';
        $this->assertFalse($service->apakahIdentik($binding, $master, $rowBedaHex));

        // 3. Binding nonaktif -> BUKAN identik (perlu diaktifkan ulang)
        $bindingNonaktif = clone $binding;
        $bindingNonaktif->flag_aktif = 'T';
        $this->assertFalse($service->apakahIdentik($bindingNonaktif, $master, $rowSama));
    }

    public function test_kartu_sama_untuk_dua_orang_dalam_satu_berkas_baris_kedua_konflik(): void
    {
        $service = new NfcImportService();
        $rows = [
            ['nfc_hex' => 'AAAA0001', 'nfc_inbio' => 1, 'kode_karyawan' => 'BUDI', 'nama' => 'Budi', 'nik' => null, 'no_mitra' => null],
            ['nfc_hex' => 'AAAA0001', 'nfc_inbio' => 1, 'kode_karyawan' => 'ANI', 'nama' => 'Ani', 'nik' => null, 'no_mitra' => null],
        ];

        $hasil = $service->klasifikasi($rows, NfcImportService::konteksKosong());

        $this->assertCount(1, $hasil['siap']);
        $this->assertSame('BUDI', $hasil['siap'][0]['kode_karyawan']);
        $this->assertCount(1, $hasil['konflik']);
        $this->assertSame('ANI', $hasil['konflik'][0]['row']['kode_karyawan']);
    }

    public function test_baris_kembar_orang_dan_kartu_sama_tidak_diimpor_dua_kali(): void
    {
        $service = new NfcImportService();
        $baris = ['nfc_hex' => 'AAAA0002', 'nfc_inbio' => 2, 'kode_karyawan' => 'BUDI', 'nama' => 'Budi', 'nik' => null, 'no_mitra' => null];

        $hasil = $service->klasifikasi([$baris, $baris], NfcImportService::konteksKosong());

        $this->assertCount(1, $hasil['siap']);
        $this->assertCount(1, $hasil['sudah']);
    }

    public function test_dua_phl_berbagi_no_mitra_tetap_dua_orang_berbeda(): void
    {
        // Data nyata 15 Sep 2026: No Mitra 03-V227 dipakai dua PHL berbeda.
        $service = new NfcImportService();
        $a = ['kode_karyawan' => 'PHL A', 'nama' => 'A', 'nik' => null, 'no_mitra' => '03-V227'];
        $b = ['kode_karyawan' => 'PHL B', 'nama' => 'B', 'nik' => null, 'no_mitra' => '03-V227'];

        $this->assertNotSame($service->kunciOrang($a), $service->kunciOrang($b));
        $this->assertTrue($service->apakahKonflikPemegangLain((object) $a, $b));

        $hasil = $service->klasifikasi([
            ['nfc_hex' => 'BBBB0001', 'nfc_inbio' => 11] + $a,
            ['nfc_hex' => 'BBBB0002', 'nfc_inbio' => 12] + $b,
        ], NfcImportService::konteksKosong());
        $this->assertCount(2, $hasil['siap']);
    }
}
