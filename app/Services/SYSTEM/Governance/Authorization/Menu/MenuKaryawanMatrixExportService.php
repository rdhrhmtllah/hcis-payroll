<?php

namespace App\Services\SYSTEM\Governance\Authorization\Menu;

use App\Jobs\SYSTEM\Governance\Authorization\Menu\GenerateMenuKaryawanMatrixExportJob;
use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Ekspor matrix menu x karyawan — menumpang mesin ekspor terpusat.
 *
 * Tiga sheet: Matrix (karyawan x menu), Belum Terima (satu baris per
 * karyawan yang belum lengkap beserta daftar menunya), dan Rekap Menu.
 * Sheet kedua SENGAJA satu baris per karyawan, bukan per pasangan
 * karyawan-menu: bentuk panjang di modul terbesar mencapai ~40 ribu baris dan
 * menembus plafon sel XLSX yang menjaga worker 512 MB dari OOM. Menyaring per
 * menu tetap bisa di sheet Matrix lewat filter kolom Excel.
 *
 * Cakupan karyawan bergantung pada admin yang meminta, jadi job membangun
 * ulang User-nya dari payload lalu memanggil MenuKaryawanMatrixService yang
 * juga dipakai layar — angka berkas dan layar tidak bisa berselisih.
 */
class MenuKaryawanMatrixExportService extends AbstractExportService
{
    public function __construct(private readonly MenuKaryawanMatrixService $matrix) {}

    protected function moduleKey(): string
    {
        return 'menu-karyawan-matrix';
    }

    protected function exportIdPrefix(): string
    {
        return 'mkm-exp';
    }

    protected function storageDir(): string
    {
        return 'menu-karyawan-matrix-exports';
    }

    public function dispatchJob(string $exportId): void
    {
        GenerateMenuKaryawanMatrixExportJob::dispatch($exportId);
    }

    protected function listLabel(object $job): string
    {
        $payload = $this->payload($job);

        return 'Matrix Menu × Karyawan '.($payload['grup_kode'] ?? '');
    }

    /**
     * Plafon sel sama dengan ekspor laporan lain. Diperiksa SEBELUM job
     * dibuat supaya admin langsung diminta mempersempit saringan, bukan
     * menunggu job yang pasti gagal.
     */
    public function melebihiPlafon(int $jumlahKaryawan, int $jumlahMenu): bool
    {
        $sel = $jumlahKaryawan * (8 + $jumlahMenu) + $jumlahKaryawan * 8 + $jumlahMenu * 4;

        return $sel > (int) config('exports.report_xlsx_max_cells', 150000);
    }

    protected function buildDataset(object $job): array
    {
        $p = $this->payload($job);

        $admin = User::query()->find((int) ($p['id_users'] ?? 0));
        if (! $admin) {
            throw new \RuntimeException('Admin peminta ekspor tidak ditemukan.');
        }

        $data = $this->matrix->semua($admin, (int) $p['grup_id'], (array) ($p['filter'] ?? []));

        return $data + ['grup_kode' => (string) ($p['grup_kode'] ?? '')];
    }

    protected function datasetCount(array $dataset): int
    {
        return count($dataset['rows'] ?? []);
    }

    protected function renderFile(object $job, array $dataset): array
    {
        $spreadsheet = new Spreadsheet;

        $this->sheetMatrix($spreadsheet->getActiveSheet(), $dataset);
        $this->sheetBelum($spreadsheet->createSheet(), $dataset);
        $this->sheetRekap($spreadsheet->createSheet(), $dataset);
        $spreadsheet->setActiveSheetIndex(0);

        $tmp = tempnam(sys_get_temp_dir(), 'mkmexp');
        (new Xlsx($spreadsheet))->save($tmp);
        $spreadsheet->disconnectWorksheets();

        $path = $this->filePathFor($job, 'xlsx');
        $size = $this->putFileFromTemp($path, $tmp);

        $nama = 'matrix-menu-karyawan-'.strtolower($dataset['grup_kode'] ?: 'grup').'-'.now()->format('Ymd-His').'.xlsx';

        return [$path, $nama, $size];
    }

    private const IDENTITAS = [
        ['Kode_Karyawan', 'Kode Karyawan'],
        ['Nama', 'Nama'],
        ['Divisi', 'Divisi'],
        ['SubDivisi', 'Departemen'],
        ['Level', 'Level'],
        ['Jabatan', 'Jabatan'],
        ['Lokasi', 'Lokasi'],
    ];

    private function sheetMatrix(Worksheet $sheet, array $dataset): void
    {
        $sheet->setTitle('Matrix');

        $judul = array_map(fn ($i) => $i[1], self::IDENTITAS);
        $judul[] = 'Jumlah Belum';
        foreach ($dataset['menus'] as $m) {
            $judul[] = $m['Header_Label'].' › '.$m['Nama_Menu'];
        }
        $this->tulisJudul($sheet, $judul);

        foreach ($dataset['rows'] as $n => $r) {
            $baris = $n + 2;
            $kolom = 1;
            foreach (self::IDENTITAS as [$kunci]) {
                $this->teks($sheet, $kolom++, $baris, $r[$kunci]);
            }
            $sheet->setCellValue($this->huruf($kolom++).$baris, $r['jumlah_belum']);
            foreach ($dataset['menus'] as $m) {
                $this->teks($sheet, $kolom++, $baris, isset($r['pages'][$m['Jenis_page']]) ? '✓' : '—');
            }
        }

        $this->rapikan($sheet, count($judul), 'C2');
    }

    private function sheetBelum(Worksheet $sheet, array $dataset): void
    {
        $sheet->setTitle('Belum Terima');

        $judul = array_map(fn ($i) => $i[1], self::IDENTITAS);
        $judul[] = 'Jumlah Belum';
        $judul[] = 'Menu yang Belum Diterima';
        $this->tulisJudul($sheet, $judul);

        $baris = 2;
        foreach ($dataset['rows'] as $r) {
            if ($r['jumlah_belum'] <= 0) {
                continue;
            }

            $belum = [];
            foreach ($dataset['menus'] as $m) {
                if (! isset($r['pages'][$m['Jenis_page']])) {
                    $belum[] = $m['Nama_Menu'];
                }
            }

            $kolom = 1;
            foreach (self::IDENTITAS as [$kunci]) {
                $this->teks($sheet, $kolom++, $baris, $r[$kunci]);
            }
            $sheet->setCellValue($this->huruf($kolom++).$baris, $r['jumlah_belum']);
            $this->teks($sheet, $kolom, $baris, implode(', ', $belum));
            $baris++;
        }

        $this->rapikan($sheet, count($judul), 'C2');
    }

    private function sheetRekap(Worksheet $sheet, array $dataset): void
    {
        $sheet->setTitle('Rekap Menu');
        $this->tulisJudul($sheet, ['Header', 'Menu', 'Sudah Terima', 'Belum Terima']);

        foreach ($dataset['menus'] as $n => $m) {
            $baris = $n + 2;
            $this->teks($sheet, 1, $baris, $m['Header_Label']);
            $this->teks($sheet, 2, $baris, $m['Nama_Menu']);
            $sheet->setCellValue('C'.$baris, $m['jumlah_terima']);
            $sheet->setCellValue('D'.$baris, $m['jumlah_belum']);
        }

        $this->rapikan($sheet, 4, 'A2');
    }

    private function tulisJudul(Worksheet $sheet, array $judul): void
    {
        foreach ($judul as $i => $label) {
            $sheet->setCellValue($this->huruf($i + 1).'1', $label);
        }

        $gaya = $sheet->getStyle('A1:'.$this->huruf(max(1, count($judul))).'1');
        $gaya->getFont()->setBold(true);
        $gaya->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
        $gaya->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    /** Kode karyawan bisa berawalan nol; ditulis sebagai teks. */
    private function teks(Worksheet $sheet, int $kolom, int $baris, ?string $nilai): void
    {
        $sheet->setCellValueExplicit($this->huruf($kolom).$baris, (string) $nilai, DataType::TYPE_STRING);
    }

    private function rapikan(Worksheet $sheet, int $jumlahKolom, string $beku): void
    {
        foreach (range(1, max(1, $jumlahKolom)) as $i) {
            $sheet->getColumnDimension($this->huruf($i))->setAutoSize(true);
        }
        $sheet->setAutoFilter('A1:'.$this->huruf(max(1, $jumlahKolom)).'1');
        $sheet->freezePane($beku);
    }

    private function huruf(int $indeks): string
    {
        return Coordinate::stringFromColumnIndex($indeks);
    }

    /** Dipakai controller untuk menampilkan kode grup di label. */
    public function kodeGrup(int $grupId): string
    {
        return (string) DB::table('N_HRIS_Master_Grup_Fitur')->where('Id_Grup_Fitur', $grupId)->value('Kode_Grup_Fitur');
    }
}
