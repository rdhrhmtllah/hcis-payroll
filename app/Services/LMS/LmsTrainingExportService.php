<?php

namespace App\Services\LMS;

use App\Jobs\LMS\Training\Export\GenerateLmsTrainingExportJob;
use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * LmsTrainingExportService
 *
 * Membangun dataset export data training LMS (identitas karyawan + rincian
 * training berlapis) lalu merendernya ke Excel / CSV / PDF. Dijalankan headless
 * dari job queue (Cloud Tasks). Gaya hasil (merge identitas + baris training
 * berlapis, header indigo) mengikuti pola KPI export.
 *
 * Lifecycle job (progress, list, cleanup) ditangani AbstractExportService;
 * status disimpan di tabel sentral N_HRIS_Export_Jobs (Module='lms-training').
 */
class LmsTrainingExportService extends AbstractExportService
{
    public function __construct(
        private TrainingProgressReadService $progressService,
        private TrainingScheduleQueryService $scheduleService,
    ) {}

    protected function moduleKey(): string
    {
        return 'lms-training';
    }

    protected function exportIdPrefix(): string
    {
        return 'lms-exp';
    }

    protected function storageDir(): string
    {
        return 'lms-exports';
    }

    public function dispatchJob(string $exportId): void
    {
        GenerateLmsTrainingExportJob::dispatch($exportId);
    }

    protected function listLabel(object $job): string
    {
        return 'Export Training · '.strtoupper((string) $job->Format).' · '.((int) $job->Total_Records).' training';
    }

    protected function renderFile(object $job, array $dataset): array
    {
        return match ($job->Format) {
            'pdf' => $this->renderPdf($job, $dataset),
            'csv' => $this->renderCsv($job, $dataset),
            default => $this->renderExcel($job, $dataset),
        };
    }

    // =====================================================================
    // Dataset builder
    // =====================================================================

    /**
     * Bangun dataset: 1 row per karyawan, masing-masing punya daftar trainings
     * (rincian berlapis) sesuai Id_Penjadwalan yang dipilih admin.
     */
    protected function buildDataset(object $job): array
    {
        $payload = $this->payload($job);
        $selection = array_values(array_filter(array_map('intval', $payload['selection'] ?? [])));
        $exportId = $job->Export_Id;

        if (empty($selection)) {
            return ['rows' => [], 'generated_at' => now()->isoFormat('D MMMM YYYY HH:mm')];
        }

        // Ambil penjadwalan + identitas karyawan.
        // Dichunk untuk menghindari SQL Server max 2100 parameter per query.
        $penjadwalans = collect();
        foreach (array_chunk($selection, 2000) as $chunk) {
            $batch = DB::table('N_LMS_Penjadwalan as p')
                ->join('Karyawan as k', 'p.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->leftJoin('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as lvl',
                    'k.ID_Level_Jabatan',
                    '=',
                    'lvl.ID_Level_Jabatan',
                )
                ->whereIn('p.Id_Penjadwalan', $chunk)
                ->select([
                    'p.Id_Penjadwalan',
                    'p.Id_Master_Paket',
                    'p.Id_Master_Rumus',
                    'p.Flag_Selesai',
                    'p.Waktu_Enroll',
                    'k.Kode_Karyawan',
                    'k.Nama as nama_karyawan',
                    'k.Cabang_Untuk_Gaji as lokasi_kerja',
                    'div.nama_divisi as divisi',
                    'div.nama_sub_divisi as departemen',
                    'lvl.nama_level as level',
                    'lvl.nama_jabatan as jabatan',
                ])
                ->orderBy('k.Nama')
                ->orderByDesc('p.Waktu_Enroll')
                ->get();
            $penjadwalans = $penjadwalans->merge($batch);
        }

        // Sort ulang karena merge dari beberapa query bisa tidak terurut.
        $penjadwalans = $penjadwalans->sortBy([
            ['nama_karyawan', 'asc'],
            ['Waktu_Enroll', 'desc'],
        ])->values();

        // Pre-fetch semua data dependent dalam batch query (hindari N+1 per training).
        $lookups = $this->prefetchTrainingLookups($penjadwalans);

        $total = $penjadwalans->count();
        $processed = 0;
        $rows = [];

        foreach ($penjadwalans as $p) {
            $identity = [
                'kode_karyawan' => trim((string) $p->Kode_Karyawan),
                'nama' => $p->nama_karyawan ?: '-',
                'lokasi_kerja' => $p->lokasi_kerja ?: '-',
                'divisi' => $p->divisi ?: '-',
                'departemen' => $p->departemen ?: '-',
                'level' => $p->level ?: '-',
                'jabatan' => $p->jabatan ?: '-',
            ];

            $training = $this->buildTrainingDetail($p, $lookups);

            // Flat: satu baris = identity + training (tanpa merge cell).
            $rows[] = array_merge($identity, $training);

            $processed++;
            $this->bumpProgress($exportId, $processed, $total);
        }

        return [
            'rows' => $rows,
            'generated_at' => now()->isoFormat('D MMMM YYYY HH:mm'),
        ];
    }

    /**
     * Pre-fetch semua data dependent yang dibutuhkan buildTrainingDetail()
     * dalam batch query, agar tidak terjadi N+1 query per training.
     *
     * @return array Lookup maps siap pakai di buildTrainingDetail().
     */
    private function prefetchTrainingLookups(\Illuminate\Support\Collection $penjadwalans): array
    {
        $penjadwalanIds = $penjadwalans->pluck('Id_Penjadwalan')
            ->map(fn ($v) => (int) $v)->unique()->values()->toArray();
        $paketIds = $penjadwalans->pluck('Id_Master_Paket')
            ->map(fn ($v) => (int) $v)->unique()->filter()->values()->toArray();

        // --- 1. Materi pertama aktif per paket ---
        $materiByPaket = [];
        if (! empty($paketIds)) {
            $materiByPaket = DB::table('N_LMS_Master_Paket_Detail as mpd')
                ->join('N_LMS_Materi as m', 'mpd.Id_Master_Training', '=', 'm.Id_Materi')
                ->leftJoin('N_LMS_Training_Kategori as k', 'm.Id_Training_Kategori', '=', 'k.Id_Training_Kategori')
                ->whereIn('mpd.Id_Master_Paket', $paketIds)
                ->where('m.Flag_Aktif', 'Y')
                ->select([
                    'mpd.Id_Master_Paket', 'm.Id_Materi', 'm.Judul_Training',
                    'm.Standar_Kelulusan', 'k.Nama_Kategori', 'mpd.Urutan_Paket_Detail',
                ])
                ->orderBy('mpd.Urutan_Paket_Detail')
                ->get()
                ->groupBy('Id_Master_Paket')
                ->map(fn ($rows) => $rows->first())
                ->all();
        }

        // --- 2. Progress snapshot (total & completed items dari penjadwalan_det) ---
        $progressByPenjadwalan = [];
        if (! empty($penjadwalanIds)) {
            foreach (array_chunk($penjadwalanIds, 2000) as $chunk) {
                $batch = $this->scheduleService
                    ->getProgressSnapshotsByPenjadwalanIds($chunk)
                    ->toArray();
                foreach ($batch as $key => $val) {
                    $progressByPenjadwalan[$key] = $val;
                }
            }
        }

        // --- 3. Transaksi training aktif (untuk progress, hasil, completion date) ---
        $transaksiByPenjadwalan = [];
        if (! empty($penjadwalanIds)) {
            foreach (array_chunk($penjadwalanIds, 2000) as $chunk) {
                $batch = DB::table('N_LMS_Transaksi_Training as trx')
                    ->leftJoin(
                        'N_LMS_Master_Keterangan_Kelulusan as kel',
                        'trx.Id_Keterangan_Kelulusan', '=', 'kel.Id_Keterangan_Kelulusan',
                    )
                    ->whereIn('trx.Id_Penjadwalan', $chunk)
                    ->where('trx.Flag_Aktif', 'Y')
                    ->select([
                        'trx.Id_Penjadwalan', 'trx.Id_Transaksi_Training',
                        'trx.Total_Score', 'trx.Score_Learning_Gain',
                        'trx.Updated_At',
                        'kel.Kode_Keterangan', 'kel.Nama_Keterangan',
                    ])
                    ->get()
                    ->each(function ($row) use (&$transaksiByPenjadwalan) {
                        $transaksiByPenjadwalan[$row->Id_Penjadwalan] = $row;
                    });
            }
        }

        // --- 4. Count progress "SELESAI" dari tabel training progress ---
        $progressSelesaiCounts = [];
        $transaksiIds = collect($transaksiByPenjadwalan)
            ->pluck('Id_Transaksi_Training')->filter()->values()->toArray();
        if (! empty($transaksiIds)) {
            foreach (array_chunk($transaksiIds, 2000) as $chunk) {
                $batch = DB::table('N_LMS_Training_Progress')
                    ->whereIn('Id_Transaksi_Training', $chunk)
                    ->where('Status_Progress', 'SELESAI')
                    ->select('Id_Transaksi_Training', DB::raw('COUNT(*) as cnt'))
                    ->groupBy('Id_Transaksi_Training')
                    ->pluck('cnt', 'Id_Transaksi_Training')
                    ->all();
                foreach ($batch as $key => $val) {
                    $progressSelesaiCounts[$key] = $val;
                }
            }
        }

        // --- 5. Has-started flags (tiga sumber: penjadwalan_det, checkinout, training_progress) ---
        $hasStartedSet = [];
        if (! empty($penjadwalanIds)) {
            foreach (array_chunk($penjadwalanIds, 2000) as $chunk) {
                // 5a. Penjadwalan_Det yang sudah dimulai
                DB::table('N_LMS_Penjadwalan_Detail as pd')
                    ->join('N_LMS_Penjadwalan_Det as pdet', 'pd.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
                    ->whereIn('pd.Id_Penjadwalan', $chunk)
                    ->where(function ($q) {
                        $q->where('pdet.Flag_Checkin', 'Y')
                            ->orWhere('pdet.Flag_Selesai', 'Y')
                            ->orWhereNotNull('pdet.Waktu_Mulai_Akses');
                    })
                    ->select('pd.Id_Penjadwalan')
                    ->distinct()
                    ->pluck('Id_Penjadwalan')
                    ->each(fn ($id) => $hasStartedSet[(int) $id] = true);

                // 5b. CHECKINOUT
                DB::table('N_LMS_CHECKINOUT')
                    ->whereIn('Id_Penjadwalan', $chunk)
                    ->select('Id_Penjadwalan')
                    ->distinct()
                    ->pluck('Id_Penjadwalan')
                    ->each(fn ($id) => $hasStartedSet[(int) $id] = true);
            }

            // 5c. Lewat transaksi → training_progress (PROGRESS/SELESAI)
            if (! empty($transaksiIds)) {
                $startedTrxSet = [];
                foreach (array_chunk($transaksiIds, 2000) as $chunkTrx) {
                    DB::table('N_LMS_Training_Progress')
                        ->whereIn('Id_Transaksi_Training', $chunkTrx)
                        ->whereIn('Status_Progress', ['PROGRESS', 'SELESAI'])
                        ->select('Id_Transaksi_Training')
                        ->distinct()
                        ->pluck('Id_Transaksi_Training')
                        ->each(fn ($id) => $startedTrxSet[(int) $id] = true);
                }

                foreach ($transaksiByPenjadwalan as $pid => $trx) {
                    if (isset($startedTrxSet[(int) $trx->Id_Transaksi_Training])) {
                        $hasStartedSet[(int) $pid] = true;
                    }
                }
            }
        }

        // --- 6. Pre-test / Post-test scores ---
        $scoresByPenjadwalan = [];
        if (! empty($penjadwalanIds)) {
            foreach (array_chunk($penjadwalanIds, 2000) as $chunk) {
                $rows = DB::table('N_LMS_Penjadwalan_Detail as pd')
                    ->join('N_LMS_Penjadwalan_Det as pdet', 'pd.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
                    ->join('N_LMS_Materi_Det as det', 'pdet.Id_Materi_Det', '=', 'det.Id_Materi_Det')
                    ->leftJoin('N_LMS_Master_Aktivitas as akt', 'det.Id_Master_Aktivitas', '=', 'akt.Id_Master_Aktivitas')
                    ->leftJoin('N_LMS_Transaksi_Training as trx', function ($join) {
                        $join->on('trx.Id_Penjadwalan', '=', 'pd.Id_Penjadwalan')
                            ->where('trx.Flag_Aktif', '=', 'Y');
                    })
                    ->leftJoin('N_LMS_Transaksi_Training_Detail as trx_detail', function ($join) {
                        $join->on('trx_detail.Id_Transaksi_Training', '=', 'trx.Id_Transaksi_Training')
                            ->on('trx_detail.Id_Penjadwalan_Detail', '=', 'pd.Id_Penjadwalan_Detail')
                            ->on('trx_detail.Id_Materi', '=', 'det.Id_Materi')
                            ->on('trx_detail.Id_Materi_Det', '=', 'det.Id_Materi_Det');
                    })
                    ->leftJoin('N_LMS_Transaksi_Training_Det as trx_det', 'trx_det.Id_Transaksi_Training_Detail', '=', 'trx_detail.Id_Transaksi_Training_Detail')
                    ->whereIn('pd.Id_Penjadwalan', $chunk)
                    ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(akt.Kode_Aktivitas, '')))) IN ('PRT','PTT')")
                    ->select(['pd.Id_Penjadwalan', 'det.Id_Materi', 'akt.Kode_Aktivitas', 'trx_det.Skor_Akhir'])
                    ->get()
                    ->each(function ($row) use (&$scoresByPenjadwalan) {
                        $key = (int) $row->Id_Penjadwalan.'_'.(int) $row->Id_Materi;
                        if (! isset($scoresByPenjadwalan[$key])) {
                            $scoresByPenjadwalan[$key] = [];
                        }
                        $scoresByPenjadwalan[$key][] = $row;
                    });
            }
        }

        // --- 7. Completion date (MAX Updated_At dari transaksi) ---
        $completionByPenjadwalan = [];
        if (! empty($penjadwalanIds)) {
            foreach (array_chunk($penjadwalanIds, 2000) as $chunk) {
                $batch = DB::table('N_LMS_Transaksi_Training')
                    ->whereIn('Id_Penjadwalan', $chunk)
                    ->where('Flag_Aktif', 'Y')
                    ->select('Id_Penjadwalan', DB::raw('MAX(Updated_At) as max_updated'))
                    ->groupBy('Id_Penjadwalan')
                    ->pluck('max_updated', 'Id_Penjadwalan');
                foreach ($batch as $pid => $val) {
                    $completionByPenjadwalan[(int) $pid] = $val;
                }
            }
        }

        // --- 8. Standar kelulusan per materi ---
        $standarByMateri = [];
        $allMateriIds = collect($materiByPaket)
            ->map(fn ($m) => (int) ($m->Id_Materi ?? 0))
            ->filter(fn ($id) => $id > 0)
            ->unique()->values()->toArray();
        if (! empty($allMateriIds)) {
            $standarByMateri = DB::table('N_LMS_Materi')
                ->whereIn('Id_Materi', $allMateriIds)
                ->pluck('Standar_Kelulusan', 'Id_Materi')
                ->map(fn ($v) => $v !== null ? (float) $v : null)
                ->all();
        }

        return [
            'materiByPaket' => $materiByPaket,
            'progressByPenjadwalan' => $progressByPenjadwalan,
            'transaksiByPenjadwalan' => $transaksiByPenjadwalan,
            'progressSelesaiCounts' => $progressSelesaiCounts,
            'hasStartedSet' => $hasStartedSet,
            'scoresByPenjadwalan' => $scoresByPenjadwalan,
            'completionByPenjadwalan' => $completionByPenjadwalan,
            'standarByMateri' => $standarByMateri,
        ];
    }

    /**
     * Rincian satu training (1 baris penjadwalan) untuk dataset export.
     * Gunakan data dari $lookups (hasil prefetchTrainingLookups) agar tidak
     * N+1 query per training.
     */
    private function buildTrainingDetail(object $p, array $lookups): array
    {
        $penjadwalanId = (int) $p->Id_Penjadwalan;
        $paketId = (int) $p->Id_Master_Paket;

        // --- Materi (dari lookup) ---
        $materi = $lookups['materiByPaket'][$paketId] ?? null;
        $materiId = (int) ($materi->Id_Materi ?? 0);

        // --- Progress percent ---
        $snapshot = $lookups['progressByPenjadwalan'][$penjadwalanId] ?? null;
        $totalItems = (int) ($snapshot->total_items ?? 0);

        if ($totalItems <= 0) {
            $progressVal = 0;
        } else {
            $completedFromDet = (int) ($snapshot->completed_items ?? 0);
            $trx = $lookups['transaksiByPenjadwalan'][$penjadwalanId] ?? null;
            $completedFromProgress = 0;
            if ($trx && isset($trx->Id_Transaksi_Training)) {
                $completedFromProgress = (int) ($lookups['progressSelesaiCounts'][$trx->Id_Transaksi_Training] ?? 0);
            }
            $completedItems = min($totalItems, max($completedFromDet, $completedFromProgress));
            $progressVal = (int) round(($completedItems / $totalItems) * 100);
        }

        // --- Has started ---
        $hasStarted = ! empty($lookups['hasStartedSet'][$penjadwalanId]);

        // --- Status ---
        $status = $p->Flag_Selesai === 'Y' ? 'completed' : 'in-progress';
        if (
            $status === 'in-progress' &&
            ! $hasStarted &&
            $p->Waktu_Enroll &&
            Carbon::parse($p->Waktu_Enroll)->isFuture()
        ) {
            $status = 'not-started';
            $progressVal = 0;
        }
        $isCompleted = $status === 'completed';

        // --- Hasil training (final score, kelulusan, learning gain) ---
        $result = ['final_display' => '-', 'kelulusan' => '-', 'learning_gain' => '-'];
        if ($isCompleted) {
            $trx = $lookups['transaksiByPenjadwalan'][$penjadwalanId] ?? null;
            if ($trx) {
                $totalScore = $trx->Total_Score !== null ? (float) $trx->Total_Score : null;
                $learningGain = $trx->Score_Learning_Gain !== null ? (float) $trx->Score_Learning_Gain : null;

                $standar = null;
                if ($materiId > 0) {
                    $standar = $lookups['standarByMateri'][$materiId] ?? null;
                }

                $kelulusan = '-';
                $nama = strtolower((string) ($trx->Nama_Keterangan ?? ''));
                if ((int) ($trx->Kode_Keterangan ?? 0) === 1 || $nama === 'lulus') {
                    $kelulusan = 'Lulus';
                } elseif ((int) ($trx->Kode_Keterangan ?? 0) === 2 || $nama === 'tidak lulus') {
                    $kelulusan = 'Tidak Lulus';
                } elseif ($standar !== null && $totalScore !== null) {
                    $kelulusan = $totalScore >= $standar ? 'Lulus' : 'Tidak Lulus';
                }

                $result = [
                    'final_display' => $totalScore !== null ? number_format($totalScore, 2) : '-',
                    'learning_gain' => $learningGain !== null ? number_format($learningGain, 2) : '-',
                    'kelulusan' => $kelulusan,
                ];
            }
        }

        // --- Pre-test / Post-test scores ---
        $scoreKey = $penjadwalanId.'_'.$materiId;
        $scoreRows = $lookups['scoresByPenjadwalan'][$scoreKey] ?? [];
        $scores = $this->computeAvgScores($scoreRows);

        // --- Completion date ---
        $completion = ['date' => '-', 'status' => 'Belum Selesai'];
        if ($p->Flag_Selesai === 'Y') {
            $waktu = $lookups['completionByPenjadwalan'][$penjadwalanId] ?? null;
            $completion = [
                'date' => $waktu ? $this->fmtDate($waktu) : '-',
                'status' => 'Selesai',
            ];
        }

        return [
            'judul' => $materi->Judul_Training ?? '-',
            'kategori' => $materi->Nama_Kategori ?? 'General',
            'tanggal_training' => $this->fmtDate($p->Waktu_Enroll),
            'status_label' => $this->statusLabel($status),
            'progress' => $progressVal,
            'completion_date' => $completion['date'],
            'completion_status' => $completion['status'],
            'pre_test' => $scores['pre'],
            'post_test' => $scores['post'],
            'final_score' => $result['final_display'],
            'learning_gain' => $result['learning_gain'],
            'kelulusan' => $result['kelulusan'],
        ];
    }

    /**
     * Hitung rata-rata skor Pre-Test (PRT) & Post-Test (PTT) dari baris
     * yang sudah di-prefetch.
     */
    private function computeAvgScores(array $rows): array
    {
        $avg = function (string $kode) use ($rows) {
            $scores = collect($rows)
                ->filter(fn ($r) => strtoupper((string) $r->Kode_Aktivitas) === $kode && $r->Skor_Akhir !== null)
                ->map(fn ($r) => (float) $r->Skor_Akhir);

            return $scores->isEmpty() ? '-' : number_format($scores->avg(), 2);
        };

        return ['pre' => $avg('PRT'), 'post' => $avg('PTT')];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'completed' => 'Selesai',
            'not-started' => 'Belum Dimulai',
            default => 'Berlangsung',
        };
    }

    private function fmtDate($value): string
    {
        if (! $value) {
            return '-';
        }
        try {
            return Carbon::parse($value)->isoFormat('D MMM YYYY');
        } catch (\Throwable $e) {
            return '-';
        }
    }

    // =====================================================================
    // Definisi kolom
    // =====================================================================

    /**
     * Kolom export. merge=identitas digabung per karyawan, center=rata tengah.
     */
    private function columns(): array
    {
        return [
            ['key' => 'no', 'label' => 'No', 'width' => 5, 'center' => true],
            ['key' => 'kode_karyawan', 'label' => 'Kode Karyawan', 'width' => 16, 'center' => true],
            ['key' => 'nama', 'label' => 'Nama Karyawan', 'width' => 24],
            ['key' => 'lokasi_kerja', 'label' => 'Lokasi Kerja', 'width' => 18],
            ['key' => 'divisi', 'label' => 'Divisi', 'width' => 22],
            ['key' => 'departemen', 'label' => 'Departemen', 'width' => 24],
            ['key' => 'level', 'label' => 'Level', 'width' => 12, 'center' => true],
            ['key' => 'jabatan', 'label' => 'Jabatan', 'width' => 24],
            ['key' => 'judul', 'label' => 'Nama Training', 'width' => 36],
            ['key' => 'tanggal_training', 'label' => 'Tanggal Training', 'width' => 16, 'center' => true],
            ['key' => 'status_label', 'label' => 'Status Training', 'width' => 14, 'center' => true],
            ['key' => 'progress', 'label' => 'Progress (%)', 'width' => 11, 'center' => true],
            ['key' => 'completion_date', 'label' => 'Completion Date', 'width' => 16, 'center' => true],
            ['key' => 'completion_status', 'label' => 'Completion Status', 'width' => 16, 'center' => true],
            ['key' => 'pre_test', 'label' => 'Pre-test Score', 'width' => 12, 'center' => true],
            ['key' => 'post_test', 'label' => 'Post-test Score', 'width' => 12, 'center' => true],
            ['key' => 'final_score', 'label' => 'Final Score', 'width' => 12, 'center' => true],
            ['key' => 'learning_gain', 'label' => 'Learning Gain', 'width' => 13, 'center' => true],
            ['key' => 'kelulusan', 'label' => 'Status Kelulusan', 'width' => 14, 'center' => true],
            ['key' => 'kategori', 'label' => 'Kategori Training', 'width' => 18, 'center' => true],
        ];
    }

    /**
     * Nilai sel — setiap row sekarang flat (identity + training), lookup langsung.
     */
    private function cellValue(string $key, array $row, int $no): string
    {
        if ($key === 'no') {
            return (string) $no;
        }

        return (string) ($row[$key] ?? '-');
    }

    // =====================================================================
    // Renderers
    // =====================================================================

    private function renderExcel($job, array $dataset): array
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Export Training LMS');

        $columns = $this->columns();
        $colCount = count($columns);
        $lastCol = Coordinate::stringFromColumnIndex($colCount);

        // ---- Judul (A1) + info (A2), merge full width, fill indigo ----
        $sheet->setCellValue('A1', 'EXPORT DATA TRAINING LMS');
        $sheet->setCellValue('A2', 'Dibuat: '.$dataset['generated_at']);
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A2')->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // ---- Header kolom (baris 3) ----
        $headerRow = 3;
        foreach ($columns as $i => $col) {
            $sheet->setCellValue([$i + 1, $headerRow], $col['label']);
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth($col['width']);
        }
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        // ---- Data: flat per row (satu baris = satu training) ----
        $r = $headerRow + 1;
        $no = 0;
        foreach ($dataset['rows'] as $row) {
            $no++;
            foreach ($columns as $i => $col) {
                $sheet->setCellValueExplicit(
                    [$i + 1, $r],
                    $this->cellValue($col['key'], $row, $no),
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING,
                );
            }
            $r++;
        }

        $lastDataRow = $r - 1;

        if ($lastDataRow >= $headerRow + 1) {
            $dataRange = 'A'.($headerRow + 1).":{$lastCol}{$lastDataRow}";
            $sheet->getStyle($dataRange)->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBE5F2']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ]);
            $sheet->getStyle("A1:{$lastCol}{$lastDataRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B7C3D6']]],
            ]);
            foreach ($columns as $i => $col) {
                if (! empty($col['center'])) {
                    $letter = Coordinate::stringFromColumnIndex($i + 1);
                    $sheet->getStyle("{$letter}".($headerRow + 1).":{$letter}{$lastDataRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            }
        }

        $sheet->freezePane('A'.($headerRow + 1));

        $tmp = tempnam(sys_get_temp_dir(), 'lmsexp');
        (new Xlsx($spreadsheet))->save($tmp);
        $path = $this->filePathFor($job, 'xlsx');
        $size = $this->putFileFromTemp($path, $tmp);

        return [$path, $this->fileName($job, 'xlsx'), $size];
    }

    private function renderCsv($job, array $dataset): array
    {
        $columns = $this->columns();
        $tmp = tempnam(sys_get_temp_dir(), 'lmsexp');
        $fh = fopen($tmp, 'w');

        // BOM agar Excel membuka UTF-8 dengan benar.
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, array_map(fn ($c) => $c['label'], $columns));

        $no = 0;
        foreach ($dataset['rows'] as $row) {
            $no++;
            $line = [];
            foreach ($columns as $col) {
                $line[] = $this->cellValue($col['key'], $row, $no);
            }
            fputcsv($fh, $line);
        }

        fclose($fh);

        $path = $this->filePathFor($job, 'csv');
        $size = $this->putFileFromTemp($path, $tmp);

        return [$path, $this->fileName($job, 'csv'), $size];
    }

    private function renderPdf($job, array $dataset): array
    {
        $pdf = Pdf::loadView('exports.lms-training-pdf', [
            'dataset' => $dataset,
            'columns' => $this->columns(),
        ])->setPaper('a4', 'landscape');

        $content = $pdf->output();
        $path = $this->filePathFor($job, 'pdf');
        $size = $this->putFile($path, $content);

        return [$path, $this->fileName($job, 'pdf'), $size];
    }

    private function fileName(object $job, string $ext): string
    {
        $custom = $this->sanitizeFileName($this->payload($job)['nama'] ?? null);
        if ($custom !== '') {
            return $custom.'_'.now()->format('Ymd_His').'.'.$ext;
        }

        return 'Export_Training_LMS_'.now()->format('Ymd_His').'.'.$ext;
    }
}
