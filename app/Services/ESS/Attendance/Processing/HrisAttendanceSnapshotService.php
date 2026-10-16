<?php

namespace App\Services\ESS\Attendance\Processing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HrisAttendanceSnapshotService
{
    public function getAttendance(
        string $kodePerusahaan,
        string $tglAwal,
        string $tglAkhir,
        string $userIdList,
        string $executionMode = 'AUTO',
        int $maxSyncUserDate = 4500,
        string $requestSource = 'WEB',
        ?string $requestedBy = null,
        ?int $priority = null,
    ): array {
        try {
            $result = DB::select(
                'EXEC dbo.N_HRIS_SP_GET_ABSEN_WEB_WRAPPER
                    @KodePerusahaan = ?,
                    @TglAwal = ?,
                    @TglAkhir = ?,
                    @UserIDList = ?,
                    @ExecutionMode = ?,
                    @MaxSyncUserDate = ?,
                    @Request_Source = ?,
                    @Requested_By = ?,
                    @Priority = ?',
                [
                    $kodePerusahaan,
                    $tglAwal,
                    $tglAkhir,
                    $userIdList,
                    strtoupper($executionMode),
                    $maxSyncUserDate,
                    strtoupper($requestSource),
                    $requestedBy,
                    $priority,
                ],
            );

            return [
                'success' => true,
                'data' => $this->normalizeAttendanceRows($result),
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to get HRIS attendance snapshot', [
                'error' => $e->getMessage(),
                'kode_perusahaan' => $kodePerusahaan,
                'tgl_awal' => $tglAwal,
                'tgl_akhir' => $tglAkhir,
                'user_id_list' => $userIdList,
                'execution_mode' => $executionMode,
                'request_source' => $requestSource,
            ]);

            return [
                'success' => false,
                'message' => 'Gagal mengambil data absensi.',
                'error' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Compatibility guard for snapshots generated with the old positional
     * mapping after Overtime_Gross and Overtime_Calculated were added to the SP.
     */
    private function normalizeAttendanceRows(array $rows): array
    {
        foreach ($rows as $row) {
            if (! is_object($row)) {
                continue;
            }

            $koefisienOt = $this->numericValue($row->Koefisien_OT ?? null);
            $overtimeReal = $this->numericValue($row->Overtime_Real ?? null);
            $overtimeGross = $this->numericValue($row->Overtime_Gross ?? null);
            $overtimeCalculated = $this->numericValue($row->Overtime_Calculated ?? null);

            $isShiftedSnapshot =
                $koefisienOt > 24 &&
                abs($koefisienOt - $overtimeReal) < 0.001 &&
                $overtimeCalculated >= 0 &&
                $overtimeCalculated <= 24 &&
                $overtimeGross > $overtimeCalculated;

            if (! $isShiftedSnapshot) {
                if ($koefisienOt <= 24 || abs($koefisienOt - $overtimeReal) >= 0.001) {
                    continue;
                }

                $snapshotOvertime = $this->loadSnapshotOvertimeColumns($row);
                if ($snapshotOvertime === null) {
                    continue;
                }

                $overtimeGross = $snapshotOvertime['overtime_gross'];
                $overtimeCalculated = $snapshotOvertime['overtime_calculated'];

                if ($overtimeCalculated < 0 || $overtimeCalculated > 24 || $overtimeGross <= $overtimeCalculated) {
                    continue;
                }
            }

            $row->Overtime_Gross = $koefisienOt;
            $row->Overtime_Calculated = $overtimeGross;
            $row->Koefisien_OT = $overtimeCalculated;
        }

        return $rows;
    }

    private function numericValue(mixed $value): float
    {
        return (float) str_replace(',', '.', (string) ($value ?? 0));
    }

    private function loadSnapshotOvertimeColumns(object $row): ?array
    {
        if (empty($row->UserId ?? $row->UserID ?? null) || empty($row->Tanggal ?? null)) {
            return null;
        }

        $snapshot = DB::table('N_HRIS_Absen_Snapshot')
            ->where('Kode_Perusahaan', (string) ($row->Kode_Perusahaan ?? '001'))
            ->where('UserID', (string) ($row->UserId ?? $row->UserID))
            ->whereDate('Tanggal', $row->Tanggal)
            ->first(['Overtime_Gross', 'Overtime_Calculated']);

        if (! $snapshot) {
            return null;
        }

        return [
            'overtime_gross' => $this->numericValue($snapshot->Overtime_Gross ?? null),
            'overtime_calculated' => $this->numericValue($snapshot->Overtime_Calculated ?? null),
        ];
    }
}
