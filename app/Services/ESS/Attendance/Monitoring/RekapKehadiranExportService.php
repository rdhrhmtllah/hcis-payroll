<?php

namespace App\Services\ESS\Attendance\Monitoring;

use App\Jobs\ESS\Attendance\Monitoring\GenerateRekapKehadiranExportJob;
use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use App\Services\SYSTEM\Governance\Authorization\Shared\FunctionalRole;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Ekspor Rekap Periode — menumpang mesin ekspor terpusat (N_HRIS_Export_Jobs).
 *
 * Worker antrean tak punya sesi login, jadi cakupan admin diambil dari
 * `user_id` pemohon yang disimpan di payload, bukan dari Auth. Tanpa itu
 * berkasnya akan memuat karyawan di luar cakupan pemohon.
 */
class RekapKehadiranExportService extends AbstractExportService
{
    public const KOLOM = [
        'kode' => 'Kode Karyawan',
        'nama' => 'Nama',
        'divisi' => 'Divisi',
        'departemen' => 'Departemen',
        'jabatan' => 'Jabatan',
        'lokasi' => 'Lokasi',
        'terjadwal' => 'Hari Terjadwal',
        'hadir' => 'Hadir',
        'terlambat_kali' => 'Terlambat (kali)',
        'terlambat_menit' => 'Terlambat (menit)',
        'terlambat_berizin' => 'Terlambat Berizin (kali)',
        'cepat_kali' => 'Pulang Cepat (kali)',
        'cepat_menit' => 'Pulang Cepat (menit)',
        'cepat_berizin' => 'Pulang Cepat Berizin (kali)',
        'sakit' => 'Sakit (hari)',
        'izin' => 'Izin (hari)',
        'cuti' => 'Cuti (hari)',
        'alpha' => 'Alpha (hari)',
        'lupa_checkout' => 'Tanpa Check-out (hari)',
        'lembur_jam' => 'Lembur (jam dibayar)',
        'lembur_hari' => 'Lembur (hari)',
        'konversi' => 'Konversi Lembur (hari)',
    ];

    private const TEKS = ['kode', 'nama', 'divisi', 'departemen', 'jabatan', 'lokasi'];

    public function __construct(private RekapPeriodeService $rekap) {}

    protected function moduleKey(): string
    {
        return 'monitoring-kehadiran-rekap';
    }

    protected function exportIdPrefix(): string
    {
        return 'mk-exp';
    }

    protected function storageDir(): string
    {
        return 'monitoring-kehadiran-exports';
    }

    public function dispatchJob(string $exportId): void
    {
        GenerateRekapKehadiranExportJob::dispatch($exportId);
    }

    protected function listLabel(object $job): string
    {
        $p = $this->payload($job);

        return 'Rekap Kehadiran '.($p['dari'] ?? '?').' s.d. '.($p['sampai'] ?? '?');
    }

    /** Job milik pemohon saja; job orang lain diperlakukan tak ada. */
    public function jobMilik(string $exportId, string $pemohon): ?object
    {
        $job = $this->findJob($exportId);

        return $job && (string) $job->Requested_By === $pemohon ? $job : null;
    }

    public function progresMilik(string $exportId, string $pemohon): ?array
    {
        $job = $this->jobMilik($exportId, $pemohon);

        return $job ? $this->mapJob($job) : null;
    }

    protected function buildDataset(object $job): array
    {
        $p = $this->payload($job);

        // Worker hidup lama: cache cakupan statis bisa milik permintaan sebelumnya.
        FunctionalRole::clearCache();
        $role = new FunctionalRole((int) ($p['user_id'] ?? 0));

        $hasil = $this->rekap->rekapKaryawan((string) $p['dari'], (string) $p['sampai'], (array) ($p['filter'] ?? []), $role, semua: true);

        return ['rows' => $hasil['items'], 'diperbarui' => $hasil['diperbarui'], 'payload' => $p];
    }

    protected function datasetCount(array $dataset): int
    {
        return count($dataset['rows'] ?? []);
    }

    protected function renderFile(object $job, array $dataset): array
    {
        $buku = new Spreadsheet;
        $sheet = $buku->getActiveSheet();
        $sheet->setTitle('Rekap');

        $kunci = array_keys(self::KOLOM);
        foreach ($kunci as $i => $k) {
            $sheet->setCellValue($this->huruf($i + 1).'1', self::KOLOM[$k]);
        }
        $kepala = $sheet->getStyle('A1:'.$this->huruf(count($kunci)).'1');
        $kepala->getFont()->setBold(true);
        $kepala->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');

        foreach ($dataset['rows'] as $n => $baris) {
            foreach ($kunci as $i => $k) {
                $sel = $this->huruf($i + 1).($n + 2);
                in_array($k, self::TEKS, true)
                    // Kode karyawan berawalan nol rusak bila dibaca Excel sebagai angka.
                    ? $sheet->setCellValueExplicit($sel, (string) ($baris[$k] ?? ''), DataType::TYPE_STRING)
                    : $sheet->setCellValue($sel, (int) ($baris[$k] ?? 0));
            }
        }

        foreach (range(1, count($kunci)) as $i) {
            $sheet->getColumnDimension($this->huruf($i))->setAutoSize(true);
        }
        $sheet->freezePane('C2');

        $info = $buku->createSheet();
        $info->setTitle('Keterangan');
        $p = $dataset['payload'];
        $saringan = collect((array) ($p['filter'] ?? []))
            ->filter(fn ($v) => $v !== [] && $v !== '' && $v !== null)
            ->map(fn ($v, $k) => $k.': '.(is_array($v) ? implode(', ', $v) : $v))
            ->implode('; ');
        $baris = [
            ['Periode', ($p['dari'] ?? '').' s.d. '.($p['sampai'] ?? '')],
            ['Saringan', $saringan !== '' ? $saringan : 'Semua karyawan dalam cakupan'],
            ['Jumlah karyawan', (string) count($dataset['rows'])],
            ['Dibuat', now()->format('Y-m-d H:i')],
            ['Sumber', 'N_HRIS_Absen_Snapshot (data absensi terhitung)'],
        ];
        if (($dataset['diperbarui'] ?? 0) > 0) {
            $baris[] = ['Catatan', $dataset['diperbarui'].' baris masih menunggu hitung ulang saat berkas dibuat; angkanya bisa berubah.'];
        }
        foreach ($baris as $i => [$label, $nilai]) {
            $info->setCellValue('A'.($i + 1), $label);
            $info->setCellValueExplicit('B'.($i + 1), $nilai, DataType::TYPE_STRING);
        }
        $info->getStyle('A1:A'.count($baris))->getFont()->setBold(true);
        $info->getColumnDimension('A')->setAutoSize(true);
        $info->getColumnDimension('B')->setAutoSize(true);
        $buku->setActiveSheetIndex(0);

        $tmp = tempnam(sys_get_temp_dir(), 'mkexp');
        (new Xlsx($buku))->save($tmp);
        $buku->disconnectWorksheets();

        $path = $this->filePathFor($job, 'xlsx');
        $size = $this->putFileFromTemp($path, $tmp);

        return [$path, 'rekap-kehadiran-'.($p['dari'] ?? '').'-'.($p['sampai'] ?? '').'.xlsx', $size];
    }

    private function huruf(int $indeks): string
    {
        return Coordinate::stringFromColumnIndex($indeks);
    }
}
