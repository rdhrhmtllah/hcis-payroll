<?php

namespace App\Services\ESS\Attendance\Processing;

use App\Jobs\ESS\Attendance\Processing\MarkAttendanceDirtyJob;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceDirtyResolverService
{
    public function __construct(private readonly AttendanceDirtyMarkService $dirtyMarkService) {}

    public function markByKodeKaryawan(
        string $kodeKaryawan,
        string $tglAwal,
        ?string $tglAkhir = null,
        string $reason = 'LARAVEL_SOURCE_CHANGED',
        string $source = 'LARAVEL',
        int $priority = 1,
        string $kodePerusahaan = '001',
    ): array {
        return $this->markMany(
            [
                [
                    'kode_perusahaan' => $kodePerusahaan,
                    'kode_karyawan' => $kodeKaryawan,
                    'tgl_awal' => $tglAwal,
                    'tgl_akhir' => $tglAkhir ?: $tglAwal,
                ],
            ],
            $reason,
            $source,
            $priority,
        );
    }

    public function markPermanentEffectByKodeKaryawan(
        string $kodeKaryawan,
        string $startDate,
        string $reason = 'PERMANENT_EFFECT_CHANGED',
        string $source = 'LARAVEL_PERMANENT_EFFECT',
        int $priority = 1,
        string $kodePerusahaan = '001',
    ): array {
        return $this->markByKodeKaryawan(
            $kodeKaryawan,
            $startDate,
            $this->permanentEffectEndDate($startDate),
            $reason,
            $source,
            $priority,
            $kodePerusahaan,
        );
    }

    public function permanentEffectEndDate(string $startDate): string
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $now = Carbon::now()->startOfDay();

        $futureMaxDays = max(
            0,
            (int) config('services.hcis.shift_request.future_max_days', 33),
            (int) config('services.hcis.leader_shift_request.future_max_days', 33),
            (int) config('services.hcis.swap_shift_request.future_max_days', 33),
        );
        $rollingHorizonDays = max(0, (int) config('services.hcis.attendance_dirty.rolling_horizon_days', 7));
        $permanentHorizonDays = max(1, (int) config('services.hcis.attendance_dirty.permanent_horizon_days', 90));

        $end = collect([
            $this->currentCutoffEndDate($now),
            $start->copy()->addDays($futureMaxDays),
            $now->copy()->addDays($rollingHorizonDays),
            $start,
        ])->reduce(fn (Carbon $carry, Carbon $date) => $date->gt($carry) ? $date : $carry, $start->copy());

        $cap = $start->copy()->addDays($permanentHorizonDays);

        if ($end->gt($cap)) {
            $end = $cap;
        }

        if ($end->lt($start)) {
            $end = $start;
        }

        return $end->toDateString();
    }

    public function markMany(
        array|Collection $items,
        string $reason = 'LARAVEL_SOURCE_CHANGED',
        string $source = 'LARAVEL',
        int $priority = 1,
        ?string $requestedBy = null,
    ): array {
        try {
            $targets = $this->normalizeTargets($items);

            if ($targets->isEmpty()) {
                return ['success' => true, 'marked' => 0, 'results' => []];
            }

            $karyawanMap = DB::table('Karyawan')
                ->whereIn('Kode_Karyawan', $targets->pluck('kode_karyawan')->unique()->values()->all())
                ->whereNotNull('UserID_Absen')
                ->pluck('UserID_Absen', 'Kode_Karyawan');

            $results = [];

            foreach ($targets as $target) {
                $userIdAbsen = $karyawanMap[$target['kode_karyawan']] ?? null;

                if (! $userIdAbsen) {
                    Log::channel('attendance_queue_worker')->warning('Skip attendance dirty mark: UserID_Absen not found', [
                        'kode_karyawan' => $target['kode_karyawan'],
                        'tgl_awal' => $target['tgl_awal'],
                        'tgl_akhir' => $target['tgl_akhir'],
                        'reason' => $reason,
                        'source' => $source,
                    ]);

                    continue;
                }

                $results[] = $this->dirtyMarkService->markDirty(
                    kodePerusahaan: $target['kode_perusahaan'],
                    userId: (int) $userIdAbsen,
                    tglAwal: $target['tgl_awal'],
                    tglAkhir: $target['tgl_akhir'],
                    dirtyReason: $reason,
                    requestSource: $source,
                    priority: $priority,
                    // Saat dijalankan dari worker (MarkAttendanceDirtyJob) tidak ada sesi
                    // Auth → requestedBy dikirim via payload. Fallback ke Auth utk pemanggil
                    // sinkron (request context).
                    requestedBy: $requestedBy ?? $this->requestedBy(),
                    createQueue: true,
                );
            }

            return [
                'success' => ! collect($results)->contains(fn ($result) => ($result['success'] ?? false) === false),
                'marked' => count($results),
                'results' => $results,
            ];
        } catch (\Throwable $e) {
            Log::channel('attendance_queue_worker')->error('Failed resolving attendance dirty targets', [
                'error' => $e->getMessage(),
                'reason' => $reason,
                'source' => $source,
            ]);

            return ['success' => false, 'marked' => 0, 'error' => $e->getMessage()];
        }
    }

    public function markFromTransaction(
        string $noTransaksi,
        string $reason = 'TRANSACTION_CHANGED',
        string $source = 'LARAVEL_TRANSACTION',
        int $priority = 1,
        ?string $requestedBy = null,
    ): array {
        try {
            return $this->markMany($this->targetsFromTransaction($noTransaksi), $reason, $source, $priority, $requestedBy);
        } catch (\Throwable $e) {
            Log::channel('attendance_queue_worker')->error('Failed resolving attendance dirty transaction targets', [
                'no_transaksi' => $noTransaksi,
                'error' => $e->getMessage(),
                'reason' => $reason,
                'source' => $source,
            ]);

            return ['success' => false, 'marked' => 0, 'error' => $e->getMessage()];
        }
    }

    public function targetsFromTransaction(string $noTransaksi): Collection
    {
        $prefix = $this->resolvePrefix($noTransaksi);

        return match ($prefix) {
            'CT' => DB::table('Transaksi_Cuti_Det')
                ->where('No_Transaksi', $noTransaksi)
                ->get()
                ->map(fn ($row) => [
                    'kode_perusahaan' => $row->Kode_Perusahaan ?? '001',
                    'kode_karyawan' => $row->Kode_Karyawan,
                    'tanggal' => $row->Tanggal_Cuti,
                ]),
            'IS' => DB::table('Transaksi_Sakit_Izin_Det')
                ->where('No_Transaksi', $noTransaksi)
                ->get()
                ->map(fn ($row) => [
                    'kode_perusahaan' => $row->Kode_Perusahaan ?? '001',
                    'kode_karyawan' => $row->Kode_Karyawan,
                    'tanggal' => $row->Tanggal_Sakit_Izin,
                ]),
            'TP' => DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail')
                ->where('No_Transaksi', $noTransaksi)
                ->get()
                ->map(fn ($row) => [
                    'kode_perusahaan' => $row->Kode_Perusahaan ?? '001',
                    'kode_karyawan' => $row->Kode_Karyawan,
                    'tanggal' => $row->Tanggal_Masuk_Pulang,
                ]),
            'LB', 'LM' => DB::table('Transaksi_Lembur_Det')
                ->where('No_Transaksi', $noTransaksi)
                ->get()
                ->map(fn ($row) => [
                    'kode_perusahaan' => $row->Kode_Perusahaan ?? '001',
                    'kode_karyawan' => $row->Kode_Karyawan,
                    'tanggal' => $row->Tanggal_Lembur,
                ]),
            'KL' => DB::table('N_HRIS_Transaksi_Konversi_Lembur_Det as det')
                ->join('N_HRIS_Transaksi_Konversi_Lembur_Detail as d', 'det.Id_Detail', '=', 'd.Id')
                ->where('det.No_Transaksi', $noTransaksi)
                ->get()
                ->flatMap(function ($row) {
                    $targets = [
                        [
                            'kode_perusahaan' => $row->Kode_Perusahaan ?? '001',
                            'kode_karyawan' => $row->Kode_Karyawan,
                            'tanggal' => $row->Tanggal_Lembur,
                        ],
                    ];

                    if (! empty($row->Target_Off)) {
                        $targets[] = [
                            'kode_perusahaan' => $row->Kode_Perusahaan ?? '001',
                            'kode_karyawan' => $row->Kode_Karyawan,
                            'tanggal' => $row->Target_Off,
                        ];
                    }

                    return $targets;
                }),
            default => collect(),
        };
    }

    private function normalizeTargets(array|Collection $items): Collection
    {
        return collect($items)
            ->map(function ($item) {
                $item = (array) $item;
                $kodeKaryawan = $item['kode_karyawan'] ?? $item['Kode_Karyawan'] ?? null;
                $tglAwal = $item['tgl_awal'] ?? $item['tanggal'] ?? $item['Tanggal'] ?? null;
                $tglAkhir = $item['tgl_akhir'] ?? $item['tanggal_akhir'] ?? $tglAwal;

                if (! $kodeKaryawan || ! $tglAwal) {
                    return null;
                }

                try {
                    return [
                        'kode_perusahaan' => (string) ($item['kode_perusahaan'] ?? $item['Kode_Perusahaan'] ?? '001'),
                        'kode_karyawan' => (string) $kodeKaryawan,
                        'tgl_awal' => Carbon::parse($tglAwal)->toDateString(),
                        'tgl_akhir' => Carbon::parse($tglAkhir)->toDateString(),
                    ];
                } catch (\Throwable) {
                    return null;
                }
            })
            ->filter()
            ->unique(fn ($item) => implode('|', $item))
            ->values();
    }

    private function resolvePrefix(string $noTransaksi): string
    {
        if (str_starts_with($noTransaksi, 'KL')) {
            return 'KL';
        }

        return substr($noTransaksi, 0, 2);
    }

    private function currentCutoffEndDate(Carbon $referenceDate): Carbon
    {
        $startDay = max(1, (int) config('services.hcis.cutoff_payroll_date.start_day', 16));
        $endDay = max(1, (int) config('services.hcis.cutoff_payroll_date.end_day', 15));
        $periodEndMonth = $referenceDate->copy();

        if ($startDay > $endDay && $referenceDate->day >= $startDay) {
            $periodEndMonth->addMonthNoOverflow();
        }

        $day = min($endDay, $periodEndMonth->daysInMonth);

        return $periodEndMonth->copy()->day($day)->startOfDay();
    }

    /**
     * Resolusi identitas pemicu dirty-mark dari sesi Auth (request context).
     * PUBLIK agar bisa DITANGKAP di titik dispatch (MarkAttendanceDirtyJob) sebelum
     * pekerjaan pindah ke worker yang tak punya sesi Auth.
     */
    public function requestedBy(): ?string
    {
        return Auth::user()?->karyawan?->Kode_Karyawan
            ?? Auth::user()?->Username
            ?? Auth::user()?->Id_Users
            ?? 'system';
    }
}
