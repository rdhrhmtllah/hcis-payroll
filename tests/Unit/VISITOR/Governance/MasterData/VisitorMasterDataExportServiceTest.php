<?php

namespace Tests\Unit\VISITOR\Governance\MasterData;

use App\Services\VISITOR\Governance\MasterData\VisitorMasterDataExportService;
use App\Services\VISITOR\Governance\MasterData\VisitorMasterDataService;
use Mockery;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Bentuk berkas export master data permit.
 *
 * Datanya dipalsukan supaya yang diuji adalah bentuk berkasnya — kolom, urutan
 * lembar, penerjemahan flag, dan lembar mana yang mengikuti saringan layar —
 * bukan isi basis data pengembangan yang berubah-ubah.
 */
class VisitorMasterDataExportServiceTest extends TestCase
{
    private const BARIS = [
        'vehicle-type' => [
            ['Nama_Tipe_Kendaraan' => 'Mobil Penumpang', 'Flag_Aktif' => 'Y'],
            ['Nama_Tipe_Kendaraan' => 'Sepeda Motor', 'Flag_Aktif' => 'T'],
        ],
        'vehicle-model' => [
            ['Id_Master_Tipe_label' => 'Mobil Penumpang', 'Nama_Model_Kendaraan' => 'Daihatsu Ayla', 'Flag_Aktif' => 'Y'],
        ],
        'approval-question' => [
            [
                'Teks_Pertanyaan' => 'Apakah fuel gauge berfungsi normal?',
                'Target_Id_Kategori_Permit_label' => 'Semua',
                'Target_Id_Tipe_Kendaraan_label' => 'Semua',
                'Wajib_Diisi' => 'Y',
                'Flag_Aktif' => 'Y',
            ],
        ],
        'vehicle-color' => [
            ['Nama_Warna' => 'Hitam', 'Flag_Aktif' => 'Y'],
        ],
        'kategori-permit' => [
            ['Nama_Kategori' => 'HIJAU', 'Deskripsi_Akses' => 'Akses penuh', 'Flag_Muncul_Pengajuan' => 'T', 'Flag_Aktif' => 'Y'],
        ],
        'plat-kendaraan' => [
            ['Kode_Plat' => 'B', 'Flag_Aktif' => 'Y'],
        ],
        'durasi-permit' => [
            ['Nama_Durasi' => '6 Bulan', 'Jumlah_Angka' => 6, 'Satuan_Waktu' => 'MONTH', 'Keterangan_Durasi' => 'Standar', 'Flag_Aktif' => 'Y'],
        ],
    ];

    public function test_lembar_permit_lengkap_dan_berurutan(): void
    {
        $pratinjau = $this->service()->pratinjau('permit', null, []);

        $this->assertSame(
            ['Tipe Kendaraan', 'Model Kendaraan', 'Pertanyaan', 'Warna Kendaraan', 'Kategori Permit', 'Kode Plat', 'Durasi Permit'],
            array_column($pratinjau['sheets'], 'sheet'),
        );
        $this->assertSame(8, $pratinjau['total_baris']);
        $this->assertStringStartsWith('Master_Data_Permit_', $pratinjau['nama_berkas']);
        $this->assertStringEndsWith('.xlsx', $pratinjau['nama_berkas']);
    }

    public function test_kolom_nomor_urut_tidak_ikut_diekspor(): void
    {
        $pratinjau = $this->service()->pratinjau('permit', null, []);
        $kolom = array_column($pratinjau['sheets'], 'kolom', 'sheet');

        foreach ($kolom as $sheet => $header) {
            $this->assertNotContains('No', $header, "Lembar {$sheet} masih membawa kolom nomor urut.");
        }

        // Header mengikuti berkas acuan, dikurangi kolom No.
        $this->assertSame(['Tipe Kendaraan', 'Status'], $kolom['Tipe Kendaraan']);
        $this->assertSame(['Tipe Kendaraan', 'Model Kendaraan', 'Status'], $kolom['Model Kendaraan']);
        $this->assertSame(
            ['Pertanyaan', 'Kategori Permit', 'Tipe Kendaraan', 'Wajib Diisi', 'Status'],
            $kolom['Pertanyaan'],
        );
        $this->assertSame(['Warna Kendaraan', 'Status'], $kolom['Warna Kendaraan']);
    }

    public function test_hanya_master_yang_tabnya_terbuka_mengikuti_saringan_layar(): void
    {
        $data = Mockery::mock(VisitorMasterDataService::class);
        foreach (self::BARIS as $master => $baris) {
            $data->shouldReceive('rowsForExport')
                ->with($master, $master === 'vehicle-type' ? ['Flag_Aktif' => 'Y'] : [])
                ->andReturn($master === 'vehicle-type' ? [$baris[0]] : $baris);
        }
        $data->shouldReceive('countAll')->with('vehicle-type')->andReturn(4);

        $pratinjau = (new VisitorMasterDataExportService($data))
            ->pratinjau('permit', 'vehicle-type', ['Flag_Aktif' => 'Y']);
        $lembar = array_column($pratinjau['sheets'], null, 'master');

        $this->assertTrue($lembar['vehicle-type']['tersaring']);
        $this->assertSame(1, $lembar['vehicle-type']['baris']);
        $this->assertSame(4, $lembar['vehicle-type']['total'], 'Pembanding tanpa saringan hilang.');
        $this->assertFalse($lembar['vehicle-model']['tersaring']);
    }

    public function test_pengurutan_bukan_saringan(): void
    {
        // Halaman selalu mengirim sort_by; kalau itu dihitung sebagai saringan,
        // setiap lembar tertandai "mengikuti saringan layar" dan penandanya
        // kehilangan arti.
        $pratinjau = $this->service()->pratinjau('permit', 'vehicle-type', [
            'sort_by' => 'Nama_Tipe_Kendaraan',
            'sort_direction' => 'asc',
            'search' => '',
        ]);

        $this->assertSame([], array_values(array_filter(array_column($pratinjau['sheets'], 'tersaring'))));
    }

    public function test_master_di_luar_domain_tidak_menyeret_saringannya(): void
    {
        // 'purpose' milik halaman tamu. Kalau kuncinya diterima apa adanya,
        // saringan halaman lain bisa menyaring lembar permit yang salah.
        $pratinjau = $this->service()->pratinjau('permit', 'purpose', ['search' => 'apa pun']);

        $this->assertNull($pratinjau['master_tersaring']);
        $this->assertSame([], array_values(array_filter(array_column($pratinjau['sheets'], 'tersaring'))));
    }

    public function test_berkas_xlsx_berisi_lembar_dan_nilai_yang_terbaca(): void
    {
        $berkas = tempnam(sys_get_temp_dir(), 'visitor-master-export').'.xlsx';

        try {
            (new Xlsx($this->service()->spreadsheet('permit', null, [])))->save($berkas);
            $dibaca = IOFactory::load($berkas);

            $this->assertSame(
                ['Tipe Kendaraan', 'Model Kendaraan', 'Pertanyaan', 'Warna Kendaraan', 'Kategori Permit', 'Kode Plat', 'Durasi Permit'],
                $dibaca->getSheetNames(),
            );

            $tipe = $dibaca->getSheetByName('Tipe Kendaraan')->toArray();
            $this->assertSame(['Tipe Kendaraan', 'Status'], $tipe[0]);
            $this->assertSame(['Mobil Penumpang', 'Aktif'], $tipe[1]);
            $this->assertSame(['Sepeda Motor', 'Nonaktif'], $tipe[2]);

            // Flag mentah Y/T tidak boleh bocor ke berkas yang dibaca manusia.
            $pertanyaan = $dibaca->getSheetByName('Pertanyaan')->toArray();
            $this->assertSame('Ya', $pertanyaan[1][3]);

            $durasi = $dibaca->getSheetByName('Durasi Permit')->toArray();
            $this->assertSame('Bulan', $durasi[1][2]);

            $kategori = $dibaca->getSheetByName('Kategori Permit')->toArray();
            $this->assertSame('Tidak', $kategori[1][2]);
        } finally {
            @unlink($berkas);
        }
    }

    private function service(): VisitorMasterDataExportService
    {
        $data = Mockery::mock(VisitorMasterDataService::class);
        foreach (self::BARIS as $master => $baris) {
            $data->shouldReceive('rowsForExport')->with($master, [])->andReturn($baris);
        }
        $data->shouldReceive('countAll')->andReturnUsing(
            fn (string $master): int => count(self::BARIS[$master] ?? []),
        );

        return new VisitorMasterDataExportService($data);
    }
}
