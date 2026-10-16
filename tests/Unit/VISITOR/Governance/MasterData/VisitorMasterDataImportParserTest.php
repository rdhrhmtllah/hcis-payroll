<?php

namespace Tests\Unit\VISITOR\Governance\MasterData;

use App\Services\VISITOR\Governance\MasterData\VisitorMasterDataImportParser;
use DomainException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Pembacaan berkas import, tanpa satu pun sambungan basis data.
 *
 * Bentuk berkas yang datang dari pemakai selalu lebih berantakan dari contoh:
 * kolom "No" di depan, kolom tertukar, lembar catatan sendiri, baris kosong
 * sisa sunting. Semuanya diuji di sini supaya kegagalannya tidak menyamar
 * sebagai galat aturan bisnis di lapisan atas.
 */
class VisitorMasterDataImportParserTest extends TestCase
{
    private array $berkasSementara = [];

    protected function tearDown(): void
    {
        foreach ($this->berkasSementara as $berkas) {
            @unlink($berkas);
        }

        parent::tearDown();
    }

    public function test_kolom_dikenali_dari_nama_bukan_posisi(): void
    {
        // Kolom No di depan dan urutan yang tertukar: keduanya tidak boleh
        // menggeser arti kolom di sebelahnya.
        $path = $this->berkas([
            'Tipe Kendaraan' => [
                ['No', 'Status', 'Tipe Kendaraan'],
                [1, 'Nonaktif', 'Mobil Listrik'],
            ],
        ]);

        $hasil = (new VisitorMasterDataImportParser)->baca($path, 'permit');
        $baris = $hasil['lembar']['vehicle-type']['baris'];

        $this->assertCount(1, $baris);
        $this->assertSame('Mobil Listrik', $baris[0]['nilai']['Nama_Tipe_Kendaraan']);
        $this->assertSame('Nonaktif', $baris[0]['nilai']['Flag_Aktif']);
        $this->assertNotContains('No', $hasil['lembar']['vehicle-type']['kolom']);
    }

    public function test_lembar_asing_diabaikan_bukan_menolak_berkas(): void
    {
        $path = $this->berkas([
            'Catatan Saya' => [['apa pun'], ['isinya bebas']],
            'Warna Kendaraan' => [['Warna Kendaraan', 'Status'], ['Emas', 'Aktif']],
        ]);

        $hasil = (new VisitorMasterDataImportParser)->baca($path, 'permit');

        $this->assertSame(['Catatan Saya'], $hasil['diabaikan']);
        $this->assertCount(1, $hasil['lembar']['vehicle-color']['baris']);
    }

    public function test_baris_kosong_dilewati_dan_nomor_baris_tetap_nomor_excel(): void
    {
        // Nomor baris adalah satu-satunya petunjuk pemakainya menemukan
        // barisnya kembali; menomori ulang setelah membuang baris kosong
        // membuat laporan menunjuk baris yang salah.
        $path = $this->berkas([
            'Warna Kendaraan' => [
                ['Warna Kendaraan', 'Status'],
                ['Emas', 'Aktif'],
                ['', ''],
                ['Perak', 'Aktif'],
            ],
        ]);

        $baris = (new VisitorMasterDataImportParser)->baca($path, 'permit')['lembar']['vehicle-color']['baris'];

        $this->assertCount(2, $baris);
        $this->assertSame([2, 4], array_column($baris, 'nomor'));
    }

    public function test_kolom_wajib_yang_hilang_dilaporkan_per_lembar(): void
    {
        $path = $this->berkas([
            'Model Kendaraan' => [['Tipe Kendaraan', 'Status'], ['Mobil Penumpang', 'Aktif']],
            'Warna Kendaraan' => [['Warna Kendaraan'], ['Emas']],
        ]);

        $hasil = (new VisitorMasterDataImportParser)->baca($path, 'permit');

        $this->assertStringContainsString('Model Kendaraan', $hasil['lembar']['vehicle-model']['galat']);
        $this->assertSame([], $hasil['lembar']['vehicle-model']['baris']);
        // Lembar lain pada berkas yang sama tetap terbaca.
        $this->assertNull($hasil['lembar']['vehicle-color']['galat']);
        $this->assertCount(1, $hasil['lembar']['vehicle-color']['baris']);
    }

    public function test_kolom_status_boleh_tidak_ada(): void
    {
        $path = $this->berkas([
            'Kode Plat' => [['Kode Plat'], ['BM']],
        ]);

        $lembar = (new VisitorMasterDataImportParser)->baca($path, 'permit')['lembar']['plat-kendaraan'];

        $this->assertNull($lembar['galat']);
        $this->assertSame('', $lembar['baris'][0]['nilai']['Flag_Aktif']);
    }

    public function test_berkas_rusak_ditolak_dengan_pesan_yang_bisa_dibaca(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'visitor-import').'.xlsx';
        file_put_contents($path, 'ini jelas bukan xlsx');
        $this->berkasSementara[] = $path;

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Berkas tidak dapat dibaca sebagai Excel.');

        (new VisitorMasterDataImportParser)->baca($path, 'permit');
    }

    public function test_lembar_melebihi_batas_baris_dilaporkan(): void
    {
        $baris = [['Warna Kendaraan', 'Status']];
        for ($nomor = 1; $nomor <= VisitorMasterDataImportParser::MAKS_BARIS + 5; $nomor++) {
            $baris[] = ['Warna '.$nomor, 'Aktif'];
        }

        $lembar = (new VisitorMasterDataImportParser)
            ->baca($this->berkas(['Warna Kendaraan' => $baris]), 'permit')['lembar']['vehicle-color'];

        $this->assertSame(VisitorMasterDataImportParser::MAKS_BARIS, count($lembar['baris']));
        $this->assertStringContainsString('melebihi', $lembar['galat']);
    }

    /**
     * @param  array<string, list<list<mixed>>>  $lembar
     */
    private function berkas(array $lembar): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        foreach (array_values($lembar) as $index => $isi) {
            $sheet = $spreadsheet->createSheet($index);
            $sheet->setTitle(array_keys($lembar)[$index]);
            $sheet->fromArray($isi, null, 'A1');
        }

        $path = tempnam(sys_get_temp_dir(), 'visitor-import').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $this->berkasSementara[] = $path;

        return $path;
    }
}
