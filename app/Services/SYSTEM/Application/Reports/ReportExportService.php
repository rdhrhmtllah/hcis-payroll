<?php

namespace App\Services\SYSTEM\Application\Reports;

use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Menyambung mesin laporan ke infrastruktur export yang sudah ada.
 *
 * Tidak ada infrastruktur async baru: lifecycle job, progress, pembatalan,
 * Activity Center, penyimpanan GCS, dan export:cleanup seluruhnya milik
 * AbstractExportService.
 *
 * ABSTRAK dengan sengaja. Kelas ini milik SYSTEM, jadi ia tidak boleh menyebut
 * satu pun modul bisnis: `moduleKey()` dan `dispatchJob()` ditinggalkan untuk
 * subkelas per modul (lihat SopReportExportService). Sebelumnya kelas ini
 * meng-import Job milik LMS/SOP dan mengunci Module ke 'sop-studio' — persis
 * inversi yang membuat modul kedua harus menyunting mesin untuk ikut bergabung.
 */
abstract class ReportExportService extends AbstractExportService
{
    public function __construct(
        private DatasetRegistry $registry,
        private ReportRunner $runner,
    ) {}

    protected function exportIdPrefix(): string
    {
        return 'rpt-exp';
    }

    protected function storageDir(): string
    {
        return 'report-exports';
    }

    protected function buildDataset(object $job): array
    {
        $p = $this->payload($job);

        // Definisi diambil dari PAYLOAD, bukan dari tabel template: mengubah
        // atau menghapus template tidak boleh merusak job yang sudah antre.
        $definisi = DefinisiLaporan::dariArray($p['definisi'] ?? []);

        // Modul ikut di payload, ditulis controller dari route. Kelas ini milik
        // SYSTEM; menuliskan 'sop' di sini berarti modul kedua harus mengubah
        // mesin untuk bergabung.
        $dataset = $this->registry->ambil((string) ($p['module'] ?? ''), $definisi->dataset);

        $l = $p['lingkup'] ?? [];

        $batas = $this->maxRowsFor($job);
        $jumlahKolom = $definisi->mode === 'rinci'
            ? count($definisi->kolom)
            : count(array_merge($definisi->dimensi, $definisi->ukuran));

        // CSV dapat ditulis sambil cursor bergerak. XLSX memakai objek sel
        // PhpSpreadsheet sehingga plafonnya harus berbasis JUMLAH SEL, bukan
        // baris. PDF juga dibatasi sebelum query dijalankan, bukan sesudah
        // seluruh dataset telanjur dimuat ke memori.
        $batasFormat = match ((string) $job->Format) {
            'pdf' => 200,
            'csv' => $batas,
            default => max(1, intdiv(
                (int) config('exports.report_xlsx_max_cells', 150000),
                max(1, $jumlahKolom),
            )),
        };
        $batasEfektif = $batas > 0 ? min($batas, $batasFormat) : $batasFormat;

        $jalankan = (string) $job->Format === 'csv'
            ? 'jalankanStreaming'
            : 'jalankan';

        return $this->runner->{$jalankan}(
            $definisi,
            $dataset,
            new Lingkup(
                (string) ($l['kode_karyawan'] ?? ''),
                (bool) ($l['admin_penuh'] ?? false),
                (array) ($l['anggota_tim'] ?? []),
                (bool) ($l['boleh_field_terbatas'] ?? false),
            ),
            (string) ($p['judul'] ?? 'LAPORAN'),
            $batasEfektif > 0 ? $batasEfektif : null,

            // bumpProgress() BUKAN sekadar penggerak bar. Di dalamnya ada
            // pemeriksaan pembatalan berkala; selama pemanggilan ini tidak ada,
            // tombol "Batalkan" tidak berpengaruh sepanjang pengambilan data --
            // bagian terlama dari seluruh job.
            fn (int $diproses, int $total) => $this->bumpProgress($job->Export_Id, $diproses, $total),
        );
    }

    protected function maxRowsFor(object $job): int
    {
        return (int) config('exports.report_max_rows', 200000);
    }

    protected function renderFile(object $job, array $dataset): array
    {
        return match ($job->Format) {
            'csv' => $this->renderCsv($job, $dataset),
            'pdf' => $this->renderPdf($job, $dataset),
            default => $this->renderExcel($job, $dataset),
        };
    }

    protected function datasetCount(array $dataset): int
    {
        if (isset($dataset['stream_state'])) {
            return (int) $dataset['stream_state']->count;
        }

        return parent::datasetCount($dataset);
    }

    private function renderCsv(object $job, array $dataset): array
    {
        $columns = $dataset['columns'];
        $tmp = tempnam(sys_get_temp_dir(), 'rptexp');
        $fh = fopen($tmp, 'w');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, array_map(fn ($c) => $c['label'], $columns));

        foreach ($dataset['rows'] as $row) {
            fputcsv($fh, array_map(fn ($c) => (string) ($row[$c['key']] ?? ''), $columns));
        }

        // Catatan ikut ke berkas, bukan hanya ke layar: berkas yang terpotong
        // atau ter-mask tanpa jejak adalah mode gagal terburuk -- isinya tetap
        // terbaca masuk akal.
        $catatan = $dataset['catatan'] ?? [];
        if (isset($dataset['stream_state'])) {
            $catatan = array_merge($catatan, $dataset['stream_state']->catatan);
        }
        foreach ($catatan as $c) {
            fputcsv($fh, [$c]);
        }

        fclose($fh);

        $path = $this->filePathFor($job, 'csv');

        return [$path, $this->fileName($job, 'csv'), $this->putFileFromTemp($path, $tmp)];
    }

    private function renderExcel(object $job, array $dataset): array
    {
        $columns = $dataset['columns'] ?? [];
        $numCols = max(1, count($columns));
        $lastCol = Coordinate::stringFromColumnIndex($numCols);
        $rows = $dataset['rows'] ?? [];
        $totalDataRows = count($rows);

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Laporan');

        // Pastikan garis grid bawaan Excel selalu aktif & terlihat
        $sheet->setShowGridLines(true);

        // Font default sheet (Calibri 10pt yang bersih dan nyaman dibaca)
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        // ═════════════════════════════════════════════════════════════════════
        // 1. BANNER HEADER UTAMA (A1 & A2) - Clean Light Premium Style
        // ═════════════════════════════════════════════════════════════════════
        $sheet->setCellValue('A1', strtoupper((string) ($dataset['title'] ?? 'LAPORAN')));
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getRowDimension(1)->setRowHeight(32);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '1E3A8A']], // Deep Corporate Navy
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F7FF']], // Soft Ice Blue
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'indent' => 1,
            ],
        ]);

        $metaSubtitle = 'Dibuat pada: '.($dataset['generated_at'] ?? now()->format('Y-m-d H:i')).'   •   Total Data: '.number_format($totalDataRows).' baris   •   HCIS Studio Perakit Laporan';
        $sheet->setCellValue('A2', $metaSubtitle);
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getRowDimension(2)->setRowHeight(20);
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '475569']], // Crisp Slate 600
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F7FF']], // Soft Ice Blue
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'indent' => 1,
            ],
            'borders' => [
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '3B82F6']], // Modern Blue Accent Line
            ],
        ]);

        // Breather row 3
        $sheet->getRowDimension(3)->setRowHeight(6);

        // ═════════════════════════════════════════════════════════════════════
        // 2. KEPALA TABEL KOLOM DATA (Row 4) - Vibrant Corporate Blue
        // ═════════════════════════════════════════════════════════════════════
        $barisHeader = 4;
        $sheet->getRowDimension($barisHeader)->setRowHeight(26);

        foreach ($columns as $i => $col) {
            $huruf = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$huruf}{$barisHeader}", $col['label']);

            // Hitung lebar cerdas: min width 12, atau lebar estimasi dari metadata/panjang label
            $baseWidth = max(12.0, (float) ($col['width'] ?? 15.0));
            $labelLen = (float) (mb_strlen((string) $col['label']) + 4);
            $sheet->getColumnDimension($huruf)->setWidth(max($baseWidth, $labelLen));
        }

        $sheet->getStyle("A{$barisHeader}:{$lastCol}{$barisHeader}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']], // Vibrant Royal Blue
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '93C5FD']],
                'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1D4ED8']],
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1D4ED8']],
            ],
        ]);

        // ═════════════════════════════════════════════════════════════════════
        // 3. BARIS DATA & ZEBRA STRIPING (Row 5 to N)
        // ═════════════════════════════════════════════════════════════════════
        $baris = $barisHeader + 1;

        foreach ($rows as $rIdx => $row) {
            $sheet->getRowDimension($baris)->setRowHeight(20);
            $isEven = ($rIdx % 2 === 1);
            $isGrandTotalRow = ($row['dimensi'] ?? null) === 'GRAND TOTAL'
                || ($row[$columns[0]['key'] ?? ''] ?? '') === 'GRAND TOTAL';

            foreach ($columns as $i => $col) {
                $huruf = Coordinate::stringFromColumnIndex($i + 1);
                $val = $row[$col['key']] ?? '';

                // Kolom angka ditulis SEBAGAI angka. Sebelumnya seluruh sel
                // dipaksa TYPE_STRING, sehingga template resmi yang deskripsinya
                // sendiri berbunyi "dibuat untuk di-pivot di Excel" menghasilkan
                // berkas yang kolom angkanya tak bisa dijumlah PivotTable.
                // '-' (nilai kosong) dan '—' (kolom tersamar) tetap teks: keduanya
                // bukan nol, dan menuliskannya sebagai nol memalsukan data.
                if (! empty($col['numerik']) && is_numeric($val)) {
                    $sheet->setCellValueExplicit("{$huruf}{$baris}", (float) $val, DataType::TYPE_NUMERIC);
                } elseif (! empty($col['tautan']) && str_starts_with((string) $val, 'http')) {
                    // Kolom tautan berkas: teks pendek yang bisa diklik, bukan
                    // URL bertanda tangan sepanjang ratusan karakter di sel.
                    $sheet->setCellValueExplicit("{$huruf}{$baris}", 'Lihat berkas', DataType::TYPE_STRING);
                    $sheet->getCell("{$huruf}{$baris}")->getHyperlink()->setUrl((string) $val);
                    $sheet->getStyle("{$huruf}{$baris}")->getFont()->setUnderline(true)->getColor()->setRGB('1D4ED8');
                } else {
                    $sheet->setCellValueExplicit("{$huruf}{$baris}", (string) $val, DataType::TYPE_STRING);
                }
            }

            if ($isGrandTotalRow) {
                // Styling khusus baris Grand Total
                $sheet->getStyle("A{$baris}:{$lastCol}{$baris}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '1E40AF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF6FF']], // Soft Ice Blue
                    'borders' => [
                        'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '3B82F6']],
                        'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '1D4ED8']],
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFDBFE']],
                    ],
                ]);
            } else {
                // Zebra row styling
                if ($isEven) {
                    $sheet->getStyle("A{$baris}:{$lastCol}{$baris}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']], // Soft Slate 50
                    ]);
                }
            }

            $baris++;
        }

        $lastDataRow = $baris - 1;

        if ($totalDataRows > 0) {
            // Pasang border tipis rapi pada seluruh grid data
            $sheet->getStyle("A5:{$lastCol}{$lastDataRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']],
                    'outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            // Alignment kolom per metadata (Center jika col.center true)
            foreach ($columns as $i => $col) {
                $huruf = Coordinate::stringFromColumnIndex($i + 1);
                if (! empty($col['center'])) {
                    $sheet->getStyle("{$huruf}5:{$huruf}{$lastDataRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                } else {
                    $sheet->getStyle("{$huruf}5:{$huruf}{$lastDataRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }
            }

            // Pasang fitur AutoFilter interaktif bawaan Excel
            $sheet->setAutoFilter("A{$barisHeader}:{$lastCol}{$lastDataRow}");
        }

        // Bekukan header tabel agar saat scroll ke bawah kepala kolom tetap terlihat
        $sheet->freezePane('A'.($barisHeader + 1));

        // ═════════════════════════════════════════════════════════════════════
        // 4. FOOTER & CATATAN ATURAN (Jika ada)
        // ═════════════════════════════════════════════════════════════════════
        if (! empty($dataset['catatan'])) {
            $baris += 1; // Spasi
            foreach ($dataset['catatan'] as $c) {
                $sheet->setCellValue("A{$baris}", 'ℹ️  '.$c);
                $sheet->mergeCells("A{$baris}:{$lastCol}{$baris}");
                $sheet->getRowDimension($baris)->setRowHeight(20);
                $sheet->getStyle("A{$baris}")->applyFromArray([
                    'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '475569']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']],
                    ],
                ]);
                $baris++;
            }
        }

        $tmp = tempnam(sys_get_temp_dir(), 'rptexp');
        (new Xlsx($book))->save($tmp);
        $book->disconnectWorksheets();

        $path = $this->filePathFor($job, 'xlsx');

        return [$path, $this->fileName($job, 'xlsx'), $this->putFileFromTemp($path, $tmp)];
    }

    protected function listLabel(object $job): string
    {
        return $this->namaLaporan($job).' · '.strtoupper($job->Format);
    }

    /**
     * Nama laporan untuk label daftar dan nama berkas unduhan.
     *
     * Mundur pada nilai KOSONG, bukan hanya pada null. Kolom "nama kustom" di
     * Export Studio mengirim string kosong ketika tak diisi, dan `??` tidak
     * menganggapnya absen: hasilnya nama berkas yang diawali garis bawah
     * (`_20260826_070656.xlsx`) dan entri Pusat Aktivitas berlabel " · XLSX"
     * tanpa nama sama sekali.
     */
    private function namaLaporan(object $job): string
    {
        $p = $this->payload($job);

        foreach (['custom_name', 'nama', 'judul'] as $kunci) {
            $nilai = trim((string) ($p[$kunci] ?? ''));
            if ($nilai !== '') {
                return $nilai;
            }
        }

        return 'Laporan';
    }

    private function renderPdf(object $job, array $dataset): array
    {
        $colCount = count($dataset['columns'] ?? []);

        // 1. Pilih Ukuran Kertas & Skala Font Otomatis Berdasarkan Jumlah Kolom
        // - Kolom <= 10: Standar A4 Landscape
        // - Kolom > 10: Otomatis beralih ke A3 Landscape (420mm x 297mm) agar kolom tidak berhimpitan/rusak
        if ($colCount <= 7) {
            $paper = 'a4';
            $fontSize = '8.5px';
            $headerFontSize = '8px';
            $cellPadding = '4px 6px';
        } elseif ($colCount <= 11) {
            $paper = 'a4';
            $fontSize = '7.5px';
            $headerFontSize = '7px';
            $cellPadding = '3px 4px';
        } elseif ($colCount <= 18) {
            $paper = 'a3';
            $fontSize = '8px';
            $headerFontSize = '7.5px';
            $cellPadding = '3.5px 5px';
        } else {
            $paper = 'a3';
            $fontSize = '7px';
            $headerFontSize = '6.5px';
            $cellPadding = '2.5px 4px';
        }

        // 2. Batasi Baris Maksimal untuk PDF (Maks 200 baris) agar render cepat & tidak timeout
        $maxPdfRows = 200;
        if (count($dataset['rows'] ?? []) > $maxPdfRows) {
            $dataset['rows'] = array_slice($dataset['rows'], 0, $maxPdfRows);
            $dataset['catatan'][] = "Dokumen cetak PDF dibatasi {$maxPdfRows} baris teratas untuk efisiensi render dan kerapian cetak. Gunakan format Excel (.xlsx) atau CSV untuk mengekspor seluruh baris tanpa batas.";
        }

        $pdf = Pdf::loadView('exports.report-pdf', [
            'dataset' => $dataset,
            'fontSize' => $fontSize,
            'headerFontSize' => $headerFontSize,
            'cellPadding' => $cellPadding,
            'paper' => $paper,
        ])
            ->setPaper($paper, 'landscape')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isFontSubsettingEnabled', true);

        $path = $this->filePathFor($job, 'pdf');

        return [$path, $this->fileName($job, 'pdf'), $this->putFile($path, $pdf->output())];
    }

    private function fileName(object $job, string $ext): string
    {
        // Nama non-latin (mis. hanya angka atau simbol) bisa membuat slug kosong
        // meski namanya terisi, jadi pengamannya di sini juga, bukan hanya di
        // namaLaporan().
        $slug = Str::slug($this->namaLaporan($job), '_') ?: 'laporan';

        return $slug.'_'.now()->format('Ymd_His').'.'.$ext;
    }
}
