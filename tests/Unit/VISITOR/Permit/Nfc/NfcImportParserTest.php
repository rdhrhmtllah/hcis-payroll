<?php

namespace Tests\Unit\VISITOR\Permit\Nfc;

use App\Services\VISITOR\Permit\Nfc\NfcImportParser;
use PHPUnit\Framework\TestCase;

/**
 * Uji parser berkas Excel pairing NFC ID Card (Karyawan & PHL).
 * Uji unit murni: memverifikasi normalisasi header, ekstraksi nilai,
 * dan penanganan baris tanpa NFC tanpa menyentuh basis data.
 */
class NfcImportParserTest extends TestCase
{
    private NfcImportParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new NfcImportParser();
    }

    public function test_membedakan_baris_valid_dan_baris_tanpa_kartu(): void
    {
        $rawRows = [
            ['NIK', 'Kode Karyawan', 'Nama', 'NFC HEX', 'NFC PARSE', 'No Mitra'],
            ['2502118', 'AALAMSYAH', 'ALAMSYAH', '9bdac6a6', '13032091', ''],
            ['2501061', 'ABDUL RACHMAN', 'ABDUL RACHMAN', null, null, null],
            ['2402036', 'ABDUL JABAR', 'ABDUL JABAR', ' 3B67F2A6 ', 15886139, ''],
        ];

        $hasil = $this->parser->parseRows($rawRows, 'karyawan');

        $this->assertCount(2, $hasil['valid']);
        $this->assertCount(1, $hasil['skipped_tanpa_nfc']);

        // Nilai HEX dinormalkan ke huruf kapital dan tanpa spasi
        $this->assertSame('9BDAC6A6', $hasil['valid'][0]['nfc_hex']);
        $this->assertSame(13032091, $hasil['valid'][0]['nfc_inbio']);
        $this->assertSame('AALAMSYAH', $hasil['valid'][0]['kode_karyawan']);
        $this->assertSame('ALAMSYAH', $hasil['valid'][0]['nama']);
        $this->assertSame('2502118', $hasil['valid'][0]['nik']);
        $this->assertNull($hasil['valid'][0]['no_mitra']);

        $this->assertSame('3B67F2A6', $hasil['valid'][1]['nfc_hex']);
        $this->assertSame(15886139, $hasil['valid'][1]['nfc_inbio']);

        // Baris tanpa NFC dicatat
        $this->assertSame('ABDUL RACHMAN', $hasil['skipped_tanpa_nfc'][0]['kode_karyawan']);
    }

    public function test_memproses_kolom_phl_dengan_no_mitra(): void
    {
        $rawRows = [
            ['NIK', 'Kode Karyawan', 'Nama', 'No Mitra', 'NFC HEX', 'NFC PARSE'],
            ['', 'ABDUL AZIS', 'ABDUL AZIS', '03-V067', '001DBB5F', 12262656],
            ['', 'KOSONG', 'TANPA NFC', '03-V999', '', ''],
        ];

        $hasil = $this->parser->parseRows($rawRows, 'phl');

        $this->assertCount(1, $hasil['valid']);
        $this->assertCount(1, $hasil['skipped_tanpa_nfc']);

        $this->assertSame('001DBB5F', $hasil['valid'][0]['nfc_hex']);
        $this->assertSame(12262656, $hasil['valid'][0]['nfc_inbio']);
        $this->assertSame('03-V067', $hasil['valid'][0]['no_mitra']);
        $this->assertNull($hasil['valid'][0]['nik']);
    }

    public function test_melempar_eksepsi_bila_header_wajib_hilang(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Kolom wajib tidak ditemukan');

        $rawRows = [
            ['Nama', 'Jabatan', 'Departemen'],
            ['Budi', 'Staff', 'IT'],
        ];

        $this->parser->parseRows($rawRows, 'karyawan');
    }

    public function test_membaca_berkas_nyata_karyawan_dan_phl_bila_tersedia(): void
    {
        $fKaryawan = 'C:\Users\EVO\Downloads\Pairing_NFC_Karyawan update 15 SEP 2026 (1).xlsx';
        $fPhl = 'C:\Users\EVO\Downloads\Pairing_NFC_PHL update 15 SEP 2026 (1).xlsx';

        if (file_exists($fKaryawan)) {
            $hasilK = $this->parser->parseFile($fKaryawan, 'karyawan');
            $this->assertSame(462, count($hasilK['valid']));
            $this->assertSame(198, count($hasilK['skipped_tanpa_nfc']));
            $this->assertSame(660, $hasilK['total_baris']);
        }

        if (file_exists($fPhl)) {
            $hasilP = $this->parser->parseFile($fPhl, 'phl');
            $this->assertSame(354, count($hasilP['valid']));
            $this->assertSame(63, count($hasilP['skipped_tanpa_nfc']));
            $this->assertSame(417, $hasilP['total_baris']);
        }
    }
}
