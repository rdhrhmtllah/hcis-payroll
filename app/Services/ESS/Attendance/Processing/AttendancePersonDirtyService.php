<?php

namespace App\Services\ESS\Attendance\Processing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendancePersonDirtyService
{
    public function getAttendance(
        string $kodePerusahaan,
        string $tglAwal,
        string $tglAkhir,
        string $Kode_Karyawan_List,
        string $executionMode = 'AUTO',
        int $maxSyncUserDate = 4500,
        string $requestSource = 'WEB',
        ?string $requestedBy = null,
        ?int $priority = null,
    ): array {
        try {
            $Kode_Karyawan_List = str_replace(' ', '', $Kode_Karyawan_List);

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
                'data' => $result,
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
}
