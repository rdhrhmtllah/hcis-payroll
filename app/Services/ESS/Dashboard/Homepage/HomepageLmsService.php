<?php

namespace App\Services\ESS\Dashboard\Homepage;

use App\Services\LMS\TrainingExecutionService;
use App\Services\LMS\TrainingScheduleQueryService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class HomepageLmsService
{
    public function __construct(
        protected TrainingScheduleQueryService $trainingScheduleQueryService,
        protected TrainingExecutionService $trainingExecutionService,
    ) {}

    public function getSummary(string $kodeKaryawan, ?int $year = null): array
    {
        $summary = $this->getTeamSummaries([$kodeKaryawan], true, $year);

        return $summary[$this->normalizeKode($kodeKaryawan)] ?? $this->defaultSummary();
    }

    public function getTeamSummaries(array $kodeKaryawans, bool $includeDetail = false, ?int $year = null): array
    {
        $selectedYear = $this->normalizeYear($year);
        $codes = collect($kodeKaryawans)->map(fn ($kode) => $this->normalizeKode($kode))->filter()->unique()->values();

        if ($codes->isEmpty()) {
            return [];
        }

        try {
            // Hanya assignment AKTIF yang dihitung. Tidak ada fallback ke
            // assignment non-aktif/dicabut aksesnya (aturan bisnis PD-053/LD-023:
            // assignment nonaktif tidak dihitung sebagai training aktif).
            $penjadwalans = $this->trainingScheduleQueryService->getSchedulesByKodeKaryawans($codes->all(), true);

            if ($penjadwalans->isEmpty()) {
                return $codes->mapWithKeys(fn ($kode) => [$kode => $this->defaultSummary()])->all();
            }

            $penjadwalanIds = $penjadwalans->pluck('Id_Penjadwalan')->filter()->unique()->values()->all();
            $progressByPenjadwalan = $this->trainingScheduleQueryService->getProgressSnapshotsByPenjadwalanIds($penjadwalanIds);
            $executionByPenjadwalan = $this->trainingExecutionService->getExecutionSummariesByPenjadwalanIds($penjadwalanIds);
            $schedulesByKode = $penjadwalans
                ->groupBy(fn ($item) => $this->normalizeKode($item->Kode_Karyawan))
                ->map(fn (Collection $items) => $items->values());

            return $codes
                ->mapWithKeys(function ($kode) use (
                    $schedulesByKode,
                    $progressByPenjadwalan,
                    $executionByPenjadwalan,
                    $includeDetail,
                    $selectedYear,
                ) {
                    $memberSchedules = $schedulesByKode->get($kode, collect())->values();

                    if ($memberSchedules->isEmpty()) {
                        return [$kode => $this->defaultSummary()];
                    }

                    $trainingRows = $memberSchedules
                        ->map(function ($schedule) use ($progressByPenjadwalan, $executionByPenjadwalan) {
                            $progressRow = $progressByPenjadwalan->get($schedule->Id_Penjadwalan);
                            $executionRow = $executionByPenjadwalan->get($schedule->Id_Penjadwalan);

                            $completedItems = (int) ($progressRow->completed_items ?? 0);
                            $totalItems = (int) ($progressRow->total_items ?? 0);
                            $progressPercent =
                                $totalItems > 0
                                    ? (int) round(($completedItems / max(1, $totalItems)) * 100)
                                    : (strtoupper((string) ($schedule->Flag_Selesai ?? '')) === 'Y'
                                        ? 100
                                        : 0);

                            $status = 'pending';
                            $statusLabel = 'Belum';
                            if (
                                strtoupper((string) ($schedule->Flag_Selesai ?? '')) === 'Y' ||
                                $progressPercent >= 100
                            ) {
                                $status = 'complete';
                                $statusLabel = 'Selesai';
                            } elseif ($progressPercent > 0) {
                                $status = 'progress';
                                $statusLabel = $progressPercent.'%';
                            }

                            $enrollAt = $schedule->Waktu_Enroll ? Carbon::parse($schedule->Waktu_Enroll) : null;
                            $completionAt = null;
                            if ($executionRow && ! empty($executionRow->Updated_At) && $progressPercent > 0) {
                                $completionAt = Carbon::parse($executionRow->Updated_At);
                            } elseif ($status === 'complete' && $enrollAt) {
                                $completionAt = $enrollAt->copy();
                            }

                            $totalDurasiMenit = (float) ($schedule->total_durasi_menit ?? 0);
                            $clampedProgress = max(0, min(100, $progressPercent));
                            $completedMinutes = ($clampedProgress / 100) * max(0, $totalDurasiMenit);

                            // Hanya training yang DIRANCANG punya learning gain (Flag_Learning_Gain = 'Y')
                            // yang boleh masuk perhitungan rata-rata learning gain. Training tanpa
                            // rumus learning gain tidak pernah mengisi Score_Learning_Gain (tetap 0
                            // placeholder), sehingga tidak boleh menjadi pembagi.
                            $needsLearningGain = strtoupper((string) ($schedule->Flag_Learning_Gain ?? 'T')) === 'Y';

                            return [
                                'id_penjadwalan' => (int) $schedule->Id_Penjadwalan,
                                'title' => $schedule->Nama_Paket ?? 'Training',
                                'progress' => $progressPercent,
                                'status' => $status,
                                'statusLabel' => $statusLabel,
                                'deadline' => $enrollAt ? $enrollAt->translatedFormat('d M Y') : null,
                                'enroll_at' => $enrollAt?->toDateString(),
                                'completion_at' => $completionAt?->toDateString(),
                                'completion_period' => $completionAt?->format('Y-m'),
                                // Skor (Total_Score) hanya valid jika post-test sudah dievaluasi
                                // (Id_Keterangan_Kelulusan terisi). Row placeholder saat mulai training
                                // punya Total_Score = 0 dan tidak boleh ikut menurunkan rata-rata skor.
                                'score' => $executionRow
                                        && $executionRow->Id_Keterangan_Kelulusan !== null
                                        && $executionRow->Total_Score !== null
                                        ? (float) $executionRow->Total_Score
                                        : null,
                                // Learning gain hanya dihitung bila SEMUA syarat terpenuhi:
                                // 1. Training memang dirancang punya learning gain (Flag_Learning_Gain = 'Y').
                                // 2. Post-test sudah selesai dievaluasi (Id_Keterangan_Kelulusan terisi —
                                //    kolom ini di-set hanya setelah Total_Score post-test diketahui).
                                // 3. Score_Learning_Gain bukan null.
                                // Row transaksi yang baru dibuat saat mulai training berisi
                                // Score_Learning_Gain = 0 (placeholder) & Id_Keterangan_Kelulusan = null,
                                // dan training tanpa Flag_Learning_Gain tidak pernah mengisi skor tsb —
                                // keduanya harus dikecualikan dari pembagi rata-rata.
                                'learning_gain' => $needsLearningGain
                                        && $executionRow
                                        && $executionRow->Id_Keterangan_Kelulusan !== null
                                        && $executionRow->Score_Learning_Gain !== null
                                        ? (float) $executionRow->Score_Learning_Gain
                                        : null,
                                'is_completed' => $status === 'complete',
                                'total_duration_minutes' => $totalDurasiMenit,
                                'completed_minutes' => $completedMinutes,
                            ];
                        })
                        ->values();

                    $yearRows = $trainingRows
                        ->filter(function ($row) use ($selectedYear) {
                            if (empty($row['enroll_at'])) {
                                return false;
                            }

                            return (int) Carbon::parse($row['enroll_at'])->format('Y') === $selectedYear;
                        })
                        ->values();

                    $completed = $yearRows->where('is_completed', true)->count();
                    $total = $yearRows->count();
                    $progress =
                        $total > 0
                            ? (int) round($yearRows->avg(fn ($item) => (float) ($item['progress'] ?? 0)) ?? 0)
                            : 0;

                    $scoreValues = $yearRows->pluck('score')->filter(fn ($score) => $score !== null)->values();
                    $learningGainValues = $yearRows
                        ->pluck('learning_gain')
                        ->filter(fn ($score) => $score !== null)
                        ->values();

                    $nextTraining = $yearRows->first(fn ($row) => ! $row['is_completed']);
                    $completedThisMonth = $yearRows
                        ->filter(function ($row) {
                            return $row['is_completed'] &&
                                ! empty($row['completion_at']) &&
                                Carbon::parse($row['completion_at'])->isSameMonth(Carbon::now('Asia/Jakarta'));
                        })
                        ->count();

                    $completedMonthly = $this->buildCompletedMonthlySeries($trainingRows, $selectedYear);
                    $durationMonthly = $this->buildDurationMonthlySeries($trainingRows, $selectedYear);
                    $completedMinutesYear = (float) collect($durationMonthly)->sum(
                        fn ($item) => (float) ($item['completed_minutes'] ?? 0),
                    );
                    $totalDurationMinutesYear = (float) $yearRows->sum(
                        fn ($item) => (float) ($item['total_duration_minutes'] ?? 0),
                    );

                    // Avg durasi = rata-rata durasi belajar PER training yang sudah dilalui
                    // (training dengan menit belajar > 0), bukan rata-rata per bulan.
                    $attemptedRows = $yearRows->filter(
                        fn ($item) => (float) ($item['completed_minutes'] ?? 0) > 0,
                    );
                    $attemptedCount = $attemptedRows->count();
                    $attemptedMinutes = (float) $attemptedRows->sum(
                        fn ($item) => (float) ($item['completed_minutes'] ?? 0),
                    );
                    $avgDurationMinutes = $attemptedCount > 0 ? $attemptedMinutes / $attemptedCount : 0;

                    $summary = [
                        'has_data' => true,
                        'year' => $selectedYear,
                        'selected_year' => $selectedYear,
                        'enrolled_courses' => $total,
                        'completed_courses' => $completed,
                        'completed_this_month' => $completedThisMonth,
                        'mandatory_pending' => max(0, $total - $completed),
                        'learning_hours' => round($completedMinutesYear / 60, 1),
                        'completed_minutes_year' => round($completedMinutesYear, 2),
                        'total_duration_minutes_year' => round($totalDurationMinutesYear, 2),
                        'attempted_courses' => $attemptedCount,
                        'avg_duration_minutes' => round($avgDurationMinutes, 2),
                        'avg_duration_hours' => round($avgDurationMinutes / 60, 2),
                        'progress' => $progress,
                        'average_score' => $scoreValues->isNotEmpty() ? round($scoreValues->avg(), 1) : null,
                        'learning_gain' => $learningGainValues->isNotEmpty()
                            ? round($learningGainValues->avg(), 1)
                            : null,
                        'certificates' => $completed,
                        'overdue' => $yearRows->where('status', 'pending')->count(),
                        'next_session' => $nextTraining['title'] ?? 'Tidak ada training aktif',
                        'next_session_date' => $nextTraining['deadline'] ?? '-',
                        'learning_progress' => $yearRows
                            ->take($includeDetail ? 6 : 4)
                            ->map(function ($row) {
                                return [
                                    'title' => $row['title'],
                                    'progress' => $row['progress'],
                                    'status' => $row['status'],
                                    'statusLabel' => $row['statusLabel'],
                                    'deadline' => $row['deadline'],
                                ];
                            })
                            ->values()
                            ->all(),
                        'trainings' => $yearRows
                            ->take($includeDetail ? 6 : 3)
                            ->map(function ($row) {
                                return [
                                    'title' => $row['title'],
                                    'deadline' => $row['deadline'],
                                    'status' => $row['status'],
                                    'statusLabel' => $row['statusLabel'],
                                ];
                            })
                            ->values()
                            ->all(),
                        'completed_monthly' => $completedMonthly,
                        'completedMonthly' => $completedMonthly,
                        'duration_monthly' => $durationMonthly,
                    ];

                    return [$kode => $summary];
                })
                ->all();
        } catch (\Throwable $e) {
            Log::error('HomepageLmsService error: '.$e->getMessage());

            return $codes->mapWithKeys(fn ($kode) => [$kode => $this->defaultSummary()])->all();
        }
    }

    protected function buildCompletedMonthlySeries(Collection $trainingRows, int $year): array
    {
        $months = collect(range(1, 12))->map(fn ($month) => Carbon::create($year, $month, 1, 0, 0, 0, 'Asia/Jakarta'));

        return $months
            ->map(function (Carbon $month) use ($trainingRows) {
                $count = $trainingRows
                    ->filter(function ($row) use ($month) {
                        return $row['is_completed'] &&
                            ! empty($row['completion_at']) &&
                            Carbon::parse($row['completion_at'])->format('Y-m') === $month->format('Y-m');
                    })
                    ->count();

                return [
                    'month' => $month->translatedFormat('M'),
                    'period' => $month->format('Y-m'),
                    'total' => $count,
                    'value' => $count,
                ];
            })
            ->all();
    }

    protected function buildDurationMonthlySeries(Collection $trainingRows, int $year): array
    {
        $months = collect(range(1, 12))->map(fn ($month) => Carbon::create($year, $month, 1, 0, 0, 0, 'Asia/Jakarta'));

        return $months
            ->map(function (Carbon $month) use ($trainingRows) {
                $monthRows = $trainingRows
                    ->filter(function ($row) use ($month) {
                        return ! empty($row['completion_at']) &&
                            Carbon::parse($row['completion_at'])->format('Y-m') === $month->format('Y-m') &&
                            (float) ($row['completed_minutes'] ?? 0) > 0;
                    })
                    ->values();

                // Jumlah training yang dilalui (punya menit belajar) di bulan ini.
                $trainingCount = $monthRows->count();

                $totalMinutes = (float) $monthRows->sum(fn ($row) => (float) ($row['completed_minutes'] ?? 0));
                // avg_hours = rata-rata durasi belajar PER training yang dilalui di bulan ini.
                $avgHours = $trainingCount > 0 ? ($totalMinutes / $trainingCount) / 60 : 0;

                return [
                    'month' => $month->translatedFormat('M'),
                    'period' => $month->format('Y-m'),
                    'completed_minutes' => round($totalMinutes, 2),
                    'active_trainings' => $trainingCount,
                    'avg_hours' => round($avgHours, 2),
                ];
            })
            ->all();
    }

    protected function normalizeYear(?int $year): int
    {
        $value = (int) ($year ?? Carbon::now('Asia/Jakarta')->year);
        if ($value < 2000 || $value > 2100) {
            return (int) Carbon::now('Asia/Jakarta')->year;
        }

        return $value;
    }

    protected function normalizeKode(?string $kode): string
    {
        return strtoupper(trim((string) $kode));
    }

    protected function defaultSummary(): array
    {
        return [
            'has_data' => false,
            'year' => (int) Carbon::now('Asia/Jakarta')->year,
            'selected_year' => (int) Carbon::now('Asia/Jakarta')->year,
            'enrolled_courses' => 0,
            'completed_courses' => 0,
            'completed_this_month' => 0,
            'mandatory_pending' => 0,
            'learning_hours' => 0,
            'completed_minutes_year' => 0,
            'total_duration_minutes_year' => 0,
            'attempted_courses' => 0,
            'avg_duration_minutes' => 0,
            'avg_duration_hours' => 0,
            'progress' => 0,
            'average_score' => null,
            'learning_gain' => null,
            'certificates' => 0,
            'overdue' => 0,
            'next_session' => 'Tidak ada training aktif',
            'next_session_date' => '-',
            'learning_progress' => [],
            'trainings' => [],
            'completed_monthly' => [],
            'completedMonthly' => [],
            'duration_monthly' => [],
        ];
    }
}
