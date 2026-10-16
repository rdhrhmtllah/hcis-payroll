<?php

namespace App\Services\ESS\Employee\DataCollection;

use App\Jobs\ESS\Employee\DataCollection\GeneratePengumpulanDataExportJob;
use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Ekspor hasil pengumpulan data — menumpang mesin ekspor terpusat
 * (N_HRIS_Export_Jobs + AbstractExportService), bukan membangun jalur sendiri.
 *
 * Inilah berkas yang dibawa HR ke Coretax, jadi kolomnya sengaja mengikuti
 * urutan pertanyaan di formulir supaya mudah dicocokkan, dan seluruh nilai
 * ditulis sebagai TEKS — NIK 16 digit dan nomor HP berawalan nol akan rusak
 * begitu Excel memperlakukannya sebagai angka.
 */
class PengumpulanDataExportService extends AbstractExportService
{
    public function __construct(
        private SkemaPengumpulanData $skema,
        private CakupanKampanye $cakupan,
    ) {}

    protected function moduleKey(): string
    {
        return 'pengumpulan-data-export';
    }

    protected function exportIdPrefix(): string
    {
        return 'pd-exp';
    }

    protected function storageDir(): string
    {
        return 'pengumpulan-data-exports';
    }

    public function dispatchJob(string $exportId): void
    {
        GeneratePengumpulanDataExportJob::dispatch($exportId);
    }

    protected function listLabel(object $job): string
    {
        $payload = $this->payload($job);

        $bagian = array_values(array_filter([
            (string) ($payload['status'] ?? ''),
            (string) ($payload['lokasi'] ?? ''),
        ], fn ($n) => $n !== ''));

        $lingkup = $bagian === [] ? '' : ' ('.implode(' · ', $bagian).')';

        return 'Pembaruan Data Karyawan'.$lingkup;
    }

    /** Dipakai controller untuk menyajikan berkasnya. */
    public function jobUntukUnduh(string $exportId): ?object
    {
        return $this->findJob($exportId);
    }

    public function hitungTarget(array $filter): int
    {
        return $this->queryDasar($filter)->count();
    }

    private function queryDasar(array $filter)
    {
        $q = DB::table('Karyawan as k')
            ->leftJoin('N_HRIS_Pengumpulan_Data_Status as s', 's.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->leftJoin('N_HRIS_Pengumpulan_Data_Submission as sub', function ($j) {
                $j->on('sub.Kode_Karyawan', '=', 'k.Kode_Karyawan')->where('sub.Flag_Aktif', '=', 'Y');
            })
            ->leftJoin('View_Divisi_Sub_Divisi as vds', 'vds.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign');

        // Berkas yang dibawa HR ke Coretax harus sepopulasi dengan angka di
        // layar. Ekspor yang memuat lokasi yang kampanyenya belum dibuka akan
        // terbaca sebagai ratusan orang yang "belum mengisi".
        $this->cakupan->terapkan($q);

        if (! empty($filter['lokasi'])) {
            $q->whereRaw('UPPER(LTRIM(RTRIM(k.Cabang_Untuk_Gaji))) = ?', [
                CakupanKampanye::normalkan((string) $filter['lokasi']),
            ]);
        }

        $status = $filter['status'] ?? '';

        if ($status === 'SELESAI') {
            $q->where('s.Status', 'SELESAI');
        } elseif ($status === 'BELUM') {
            $q->where(function ($w) {
                $w->whereNull('s.Status')->orWhere('s.Status', '<>', 'SELESAI');
            });
        }

        if (! empty($filter['departemen'])) {
            $q->where('vds.nama_divisi', $filter['departemen']);
        }

        return $q;
    }

    protected function buildDataset(object $job): array
    {
        $filter = $this->payload($job);

        $baris = $this->queryDasar($filter)
            ->orderBy('k.Nama')
            ->orderBy('k.Kode_Karyawan')
            ->get([
                'k.Kode_Karyawan',
                'k.No_Induk_Karyawan',
                'k.Nama as nama_sistem',
                'vds.nama_divisi as divisi_sistem',
                's.Status',
                's.Jumlah_Tunda',
                's.Is_Dikecualikan',
                's.Selesai_At',
                'sub.Data_Json',
                'sub.Versi_Skema',
            ]);

        // Seluruh katalog, termasuk pertanyaan yang dimatikan lewat env:
        // jawaban yang sudah terkumpul sebelum dimatikan tetap ikut terekspor.
        $fields = array_keys($this->skema->semuaField());

        $rows = $baris->map(function ($r) use ($fields) {
            $jawaban = json_decode($r->Data_Json ?? '{}', true) ?: [];

            $baris = [
                'kode_karyawan' => (string) $r->Kode_Karyawan,
                'nik_karyawan' => (string) ($r->No_Induk_Karyawan ?? ''),
                'nama_sistem' => (string) ($r->nama_sistem ?? ''),
                'divisi_sistem' => (string) ($r->divisi_sistem ?? ''),
                'status' => ($r->Is_Dikecualikan ?? 'T') === 'Y' ? 'DIKECUALIKAN' : (string) ($r->Status ?? 'BELUM'),
                'jumlah_tunda' => (string) ((int) ($r->Jumlah_Tunda ?? 0)),
                'dikirim_pada' => (string) ($r->Selesai_At ?? ''),
                'versi_skema' => (string) ((int) ($r->Versi_Skema ?? 0)),
            ];

            foreach ($fields as $kunci) {
                // Kolom tetap ada walau kosong: HR perlu melihat SIAPA yang
                // belum mengisi apa, bukan hanya yang sudah.
                $baris[$kunci] = (string) ($jawaban[$kunci] ?? '');
            }

            return $baris;
        })->all();

        return ['rows' => $rows, 'kolom' => $this->kolom($fields)];
    }

    private function kolom(array $fields): array
    {
        $kolom = [
            ['kunci' => 'kode_karyawan', 'label' => 'Kode Karyawan'],
            ['kunci' => 'nik_karyawan', 'label' => 'No Induk Karyawan'],
            ['kunci' => 'nama_sistem', 'label' => 'Nama (sistem)'],
            ['kunci' => 'divisi_sistem', 'label' => 'Divisi (sistem)'],
            ['kunci' => 'status', 'label' => 'Status Pengisian'],
            ['kunci' => 'jumlah_tunda', 'label' => 'Jumlah Penundaan'],
            ['kunci' => 'dikirim_pada', 'label' => 'Dikirim Pada'],
            ['kunci' => 'versi_skema', 'label' => 'Versi Skema'],
        ];

        $definisi = $this->skema->semuaField();

        foreach ($fields as $kunci) {
            $kolom[] = ['kunci' => $kunci, 'label' => $definisi[$kunci]['label'] ?? $kunci];
        }

        return $kolom;
    }

    protected function datasetCount(array $dataset): int
    {
        return count($dataset['rows'] ?? []);
    }

    protected function renderFile(object $job, array $dataset): array
    {
        return $job->Format === 'csv'
            ? $this->renderCsv($job, $dataset)
            : $this->renderExcel($job, $dataset);
    }

    private function renderExcel(object $job, array $dataset): array
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pembaruan Data');

        $kolom = $dataset['kolom'];

        foreach ($kolom as $i => $k) {
            $sel = $this->huruf($i + 1).'1';
            $sheet->setCellValue($sel, $k['label']);
        }

        $gaya = $sheet->getStyle('A1:'.$this->huruf(count($kolom)).'1');
        $gaya->getFont()->setBold(true);
        $gaya->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
        $gaya->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        foreach ($dataset['rows'] as $n => $baris) {
            foreach ($kolom as $i => $k) {
                // setCellValueExplicit + TYPE_STRING: tanpa ini NIK 16 digit
                // berubah jadi notasi ilmiah dan nomor HP kehilangan angka nol
                // di depannya begitu berkas dibuka.
                $sheet->setCellValueExplicit(
                    $this->huruf($i + 1).($n + 2),
                    (string) ($baris[$k['kunci']] ?? ''),
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING,
                );
            }
        }

        foreach (range(1, count($kolom)) as $i) {
            $sheet->getColumnDimension($this->huruf($i))->setAutoSize(true);
        }

        $sheet->freezePane('A2');

        $tmp = tempnam(sys_get_temp_dir(), 'pdexp');
        (new Xlsx($spreadsheet))->save($tmp);
        $spreadsheet->disconnectWorksheets();

        $path = $this->filePathFor($job, 'xlsx');
        $size = $this->putFileFromTemp($path, $tmp);

        return [$path, $this->namaBerkas($job, 'xlsx'), $size];
    }

    private function renderCsv(object $job, array $dataset): array
    {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, array_map(fn ($k) => $k['label'], $dataset['kolom']));

        foreach ($dataset['rows'] as $baris) {
            fputcsv($fh, array_map(fn ($k) => (string) ($baris[$k['kunci']] ?? ''), $dataset['kolom']));
        }

        rewind($fh);
        $isi = stream_get_contents($fh);
        fclose($fh);

        $path = $this->filePathFor($job, 'csv');
        $size = $this->putFile($path, $isi);

        return [$path, $this->namaBerkas($job, 'csv'), $size];
    }

    private function namaBerkas(object $job, string $ext): string
    {
        return 'pembaruan-data-karyawan-'.now()->format('Ymd-His').'.'.$ext;
    }

    private function huruf(int $indeks): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($indeks);
    }
}
