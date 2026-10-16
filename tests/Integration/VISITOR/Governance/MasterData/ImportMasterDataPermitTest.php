<?php

namespace Tests\Integration\VISITOR\Governance\MasterData;

use App\Services\VISITOR\Governance\MasterData\VisitorMasterDataImportService;
use App\Services\VISITOR\Governance\MasterData\VisitorMasterDataService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Import master data permit dari ujung ke ujung, di atas basis data development.
 *
 * Yang dijaga bukan sekadar "barisnya masuk", melainkan janji yang dibuat
 * layar: dry run tidak menulis apa pun, dan angka yang dijanjikannya adalah
 * angka yang benar-benar terjadi. Nama data uji diacak supaya uji ini tidak
 * lulus hanya karena kebetulan cocok dengan isi basis data pengembangan.
 */
class ImportMasterDataPermitTest extends TestCase
{
    use DatabaseTransactions;

    private const TABEL_TIPE = 'N_EMI_VISITOR_Master_Vehicle_Type';

    private const TABEL_MODEL = 'N_EMI_VISITOR_Master_Vehicle_Model';

    private const TABEL_WARNA = 'N_EMI_VISITOR_Master_Vehicle_Color';

    private array $berkasSementara = [];

    protected function tearDown(): void
    {
        foreach ($this->berkasSementara as $berkas) {
            @unlink($berkas);
        }

        parent::tearDown();
    }

    public function test_pratinjau_tidak_menulis_satu_baris_pun(): void
    {
        $tipe = $this->namaUji('TIPE');
        $path = $this->berkas([
            'Tipe Kendaraan' => [['Tipe Kendaraan', 'Status'], [$tipe, 'Aktif']],
        ]);

        $sebelum = DB::table(self::TABEL_TIPE)->count();
        $laporan = $this->service()->pratinjau('permit', $path, 'uji.xlsx');

        $this->assertSame(1, $laporan['ringkas']['ditambah']);
        $this->assertFalse($laporan['dijalankan']);
        $this->assertSame($sebelum, DB::table(self::TABEL_TIPE)->count(), 'Dry run menulis ke basis data.');
        $this->assertSame(0, DB::table(self::TABEL_TIPE)->where('Nama_Tipe_Kendaraan', $tipe)->count());
    }

    public function test_angka_pratinjau_sama_dengan_hasil_import(): void
    {
        $path = $this->berkas([
            'Warna Kendaraan' => [
                ['Warna Kendaraan', 'Status'],
                [$this->namaUji('WARNA'), 'Aktif'],
                [$this->namaUji('WARNA'), 'Aktif'],
            ],
        ]);

        $service = $this->service();
        $pratinjau = $service->pratinjau('permit', $path, 'uji.xlsx');
        $hasil = $service->jalankan('permit', $path, 'uji.xlsx');

        $this->assertSame($pratinjau['ringkas'], $hasil['ringkas']);
        $this->assertTrue($hasil['dijalankan']);
        $this->assertSame(2, $hasil['ringkas']['ditambah']);
    }

    public function test_tipe_baru_di_berkas_yang_sama_bisa_dirujuk_lembar_model(): void
    {
        // Inilah sebab lembar induk diproses lebih dulu: tanpa itu, baris
        // model di bawah ini ditolak padahal tipenya ada di berkas yang sama.
        $tipe = $this->namaUji('TIPE');
        $model = $this->namaUji('MODEL');
        $path = $this->berkas([
            'Tipe Kendaraan' => [['Tipe Kendaraan', 'Status'], [$tipe, 'Aktif']],
            'Model Kendaraan' => [['No', 'Tipe Kendaraan', 'Model Kendaraan', 'Status'], [1, $tipe, $model, 'Aktif']],
        ]);

        $hasil = $this->service()->jalankan('permit', $path, 'uji.xlsx');

        $idTipe = DB::table(self::TABEL_TIPE)->where('Nama_Tipe_Kendaraan', $tipe)->value('Id_Vehicle_Type');
        $this->assertNotNull($idTipe);
        $this->assertSame(2, $hasil['ringkas']['ditambah']);
        $this->assertSame(
            (int) $idTipe,
            (int) DB::table(self::TABEL_MODEL)->where('Nama_Model_Kendaraan', $model)->value('Id_Master_Tipe'),
        );
    }

    public function test_referensi_asing_menolak_barisnya_saja(): void
    {
        $tipe = $this->namaUji('TIPE');
        $modelSah = $this->namaUji('MODEL');
        $path = $this->berkas([
            'Tipe Kendaraan' => [['Tipe Kendaraan', 'Status'], [$tipe, 'Aktif']],
            'Model Kendaraan' => [
                ['Tipe Kendaraan', 'Model Kendaraan', 'Status'],
                [$tipe, $modelSah, 'Aktif'],
                [$this->namaUji('ENTAH'), $this->namaUji('MODEL'), 'Aktif'],
            ],
        ]);

        $laporan = $this->service()->jalankan('permit', $path, 'uji.xlsx');
        $lembarModel = collect($laporan['lembar'])->firstWhere('master', 'vehicle-model');

        $this->assertSame(1, $lembarModel['ditambah']);
        $this->assertSame(1, $lembarModel['ditolak']);
        $this->assertSame('RELASI', $lembarModel['masalah'][0]['jenis']);
        $this->assertSame(3, $lembarModel['masalah'][0]['baris'], 'Nomor baris Excel tidak terbawa ke laporan.');
        $this->assertSame(1, DB::table(self::TABEL_MODEL)->where('Nama_Model_Kendaraan', $modelSah)->count());
    }

    public function test_baris_yang_sudah_ada_dilewati_bukan_diperbarui(): void
    {
        // Dibuat lewat jalur tulis yang sama dengan layar, bukan INSERT mentah:
        // baris uji yang kehilangan kolom bawaan akan membuat uji ini menguji
        // bentuk baris yang tidak pernah ada di produksi.
        $warna = $this->namaUji('WARNA');
        app(VisitorMasterDataService::class)->create('vehicle-color', ['Nama_Warna' => $warna]);

        $path = $this->berkas([
            'Warna Kendaraan' => [
                ['Warna Kendaraan', 'Status'],
                [mb_strtolower($warna), 'Nonaktif'],
            ],
        ]);

        $laporan = $this->service()->jalankan('permit', $path, 'uji.xlsx');

        $this->assertSame(0, $laporan['ringkas']['ditambah']);
        $this->assertSame(1, $laporan['ringkas']['dilewati']);
        // Status baris lama TIDAK ikut berubah: import tidak pernah menyentuh
        // baris yang sudah ada, termasuk lewat kolom Status.
        $this->assertSame('Y', DB::table(self::TABEL_WARNA)->where('Nama_Warna', $warna)->value('Flag_Aktif'));
        $this->assertSame(1, DB::table(self::TABEL_WARNA)->whereRaw('LOWER(Nama_Warna) = ?', [mb_strtolower($warna)])->count());
    }

    public function test_status_nonaktif_diterapkan_setelah_seluruh_baris_masuk(): void
    {
        // Kalau status disetel saat barisnya dibuat, model di bawah ini ditolak
        // penjaga "relasi sudah nonaktif" — padahal keduanya datang dari berkas
        // yang sama dan sudah dinyatakan sah pada pratinjau.
        $tipe = $this->namaUji('TIPE');
        $model = $this->namaUji('MODEL');
        $path = $this->berkas([
            'Tipe Kendaraan' => [['Tipe Kendaraan', 'Status'], [$tipe, 'Nonaktif']],
            'Model Kendaraan' => [['Tipe Kendaraan', 'Model Kendaraan', 'Status'], [$tipe, $model, 'Aktif']],
        ]);

        $laporan = $this->service()->jalankan('permit', $path, 'uji.xlsx');

        $this->assertSame(2, $laporan['ringkas']['ditambah']);
        $this->assertSame(1, $laporan['ringkas']['dinonaktifkan']);
        $this->assertSame('T', DB::table(self::TABEL_TIPE)->where('Nama_Tipe_Kendaraan', $tipe)->value('Flag_Aktif'));
        $this->assertSame('Y', DB::table(self::TABEL_MODEL)->where('Nama_Model_Kendaraan', $model)->value('Flag_Aktif'));
    }

    public function test_baris_kembar_di_dalam_berkas_ditolak_sekali(): void
    {
        $warna = $this->namaUji('WARNA');
        $path = $this->berkas([
            'Warna Kendaraan' => [
                ['Warna Kendaraan', 'Status'],
                [$warna, 'Aktif'],
                [$warna, 'Aktif'],
            ],
        ]);

        $laporan = $this->service()->jalankan('permit', $path, 'uji.xlsx');
        $lembar = collect($laporan['lembar'])->firstWhere('master', 'vehicle-color');

        $this->assertSame(1, $lembar['ditambah']);
        $this->assertSame(1, $lembar['ditolak']);
        $this->assertSame('KEMBAR', $lembar['masalah'][0]['jenis']);
        $this->assertSame(1, DB::table(self::TABEL_WARNA)->where('Nama_Warna', $warna)->count());
    }

    public function test_nilai_kolom_yang_tak_dikenal_menolak_barisnya(): void
    {
        $path = $this->berkas([
            'Warna Kendaraan' => [
                ['Warna Kendaraan', 'Status'],
                [$this->namaUji('WARNA'), 'Mungkin'],
            ],
        ]);

        $laporan = $this->service()->pratinjau('permit', $path, 'uji.xlsx');
        $lembar = collect($laporan['lembar'])->firstWhere('master', 'vehicle-color');

        $this->assertSame(0, $lembar['ditambah']);
        $this->assertSame('NILAI', $lembar['masalah'][0]['jenis']);
        $this->assertStringContainsString('Status', $lembar['masalah'][0]['pesan']);
    }

    public function test_kolom_yang_tak_ada_di_berkas_disebut_pada_laporan(): void
    {
        // Tipe kendaraan baru diam-diam berlaku untuk flow Tamu juga; itu harus
        // terbaca di layar, bukan hanya di kode.
        $path = $this->berkas([
            'Tipe Kendaraan' => [['Tipe Kendaraan', 'Status'], [$this->namaUji('TIPE'), 'Aktif']],
        ]);

        $laporan = $this->service()->pratinjau('permit', $path, 'uji.xlsx');

        $this->assertNotEmpty($laporan['catatan']);
        $this->assertStringContainsString('Digunakan Untuk', implode(' ', $laporan['catatan']));
    }

    private function service(): VisitorMasterDataImportService
    {
        return app(VisitorMasterDataImportService::class);
    }

    private function namaUji(string $awalan): string
    {
        return 'UJI '.$awalan.' '.strtoupper(bin2hex(random_bytes(4)));
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
