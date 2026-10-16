<?php

namespace App\Services\ESS\Attendance\Processing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** CARA PAKAI:
 *    $dirtyService->markDirty(
 *        kodePerusahaan: '001',
 *        userId: (int) $request->user_id,
 *        tglAwal: $request->tanggal,
 *        tglAkhir: $request->tanggal,
 *        dirtyReason: 'IZIN_TERLAMBAT_APPROVED',
 *        requestSource: 'LARAVEL_IZIN_TERLAMBAT',
 *        priority: 1,
 *        requestedBy: auth()->user()->username ?? 'system',
 *        createQueue: true
 *    );
 */
class AttendanceDirtyMarkService
{
    public function markDirty(
        string $kodePerusahaan,
        int $userId,
        string $tglAwal,
        string $tglAkhir,
        string $dirtyReason,
        string $requestSource = 'LARAVEL_DIRTY_MARK',
        int $priority = 1,
        ?string $requestedBy = null,
        bool $createQueue = true,
    ): array {
        if ($priority < 1) {
            $priority = 1;
        }

        if ($priority > 9) {
            $priority = 9;
        }

        try {
            Log::channel('attendance_queue_worker')->info('Starting mark attendance dirty', [
                'kode_perusahaan' => $kodePerusahaan,
                'user_id' => $userId,
                'tgl_awal' => $tglAwal,
                'tgl_akhir' => $tglAkhir,
                'dirty_reason' => $dirtyReason,
                'request_source' => $requestSource,
                'priority' => $priority,
                'requested_by' => $requestedBy,
                'create_queue' => $createQueue,
            ]);

            $result = DB::select(
                'EXEC dbo.N_HRIS_SP_MARK_ABSEN_DIRTY
                    @KodePerusahaan = ?,
                    @UserID = ?,
                    @TglAwal = ?,
                    @TglAkhir = ?,
                    @DirtyReason = ?,
                    @RequestSource = ?,
                    @Priority = ?,
                    @RequestedBy = ?,
                    @CreateQueue = ?',
                [
                    $kodePerusahaan,
                    $userId,
                    $tglAwal,
                    $tglAkhir,
                    $dirtyReason,
                    $requestSource,
                    $priority,
                    $requestedBy,
                    $createQueue ? 1 : 0,
                ],
            );

            Log::channel('attendance_queue_worker')->info('Finished mark attendance dirty', [
                'result' => $result,
            ]);

            return [
                'success' => true,
                'result' => $result,
            ];
        } catch (\Throwable $e) {
            Log::channel('attendance_queue_worker')->error('Failed mark attendance dirty', [
                'error' => $e->getMessage(),
                'kode_perusahaan' => $kodePerusahaan,
                'user_id' => $userId,
                'tgl_awal' => $tglAwal,
                'tgl_akhir' => $tglAkhir,
                'dirty_reason' => $dirtyReason,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
