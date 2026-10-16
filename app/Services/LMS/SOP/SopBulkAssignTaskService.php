<?php

namespace App\Services\LMS\SOP;

use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lifecycle tugas async "bulk assign SOP" di tabel sentral N_HRIS_Async_Tasks
 * (Task_Type='bulk-assign', Module='sop-penjadwalan').
 *
 * Mengikuti pola UploadTaskService: reuse konstanta/helper static
 * AbstractExportService TANPA meng-extend-nya — bulk assign tak punya
 * buildDataset()/renderFile(), jadi subclassing hanya mewarisi kewajiban
 * yang tak relevan (mapRow menganggap ready-tanpa-File_Path = expired, dst).
 *
 * Payload_Json = {id_penjadwalan, id_paket, kodes[], opts, user_id, hasil?}.
 * Seluruh payload hidup di baris task ini — satu baris, satu sumber kebenaran.
 * (Menolak pola N_LMS_Job_Payloads yang memisahkan payload ke tabel kedua.)
 */
class SopBulkAssignTaskService
{
    public const TASK_TYPE = 'bulk-assign';

    public const MODULE = 'sop-penjadwalan';

    public function makeId(): string
    {
        return 'sop-asg-'.now()->format('Ymd-His').'-'.strtoupper(Str::random(6));
    }

    /** Catat task baru (queued). Kembalikan Export_Id. */
    public function mulai(array $payload, int $total, ?string $requestedBy): string
    {
        $id = $this->makeId();
        $now = now();

        DB::table(AbstractExportService::TABLE)->insert([
            'Export_Id' => $id,
            'Task_Type' => self::TASK_TYPE,
            'Module' => self::MODULE,
            'Format' => 'assign',
            'Payload_Json' => json_encode($payload),
            'Status' => AbstractExportService::STATUS_QUEUED,
            'Total_Records' => max(1, $total),
            'Processed_Records' => 0,
            'Percent' => 0,
            'Requested_By' => $requestedBy,
            'Created_At' => $now,
            'Updated_At' => $now,
        ]);

        return $id;
    }

    public function baris(string $id): ?object
    {
        return DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $id)
            ->where('Task_Type', self::TASK_TYPE)
            ->whereNull('Deleted_At')
            ->first();
    }

    /**
     * Claim guard ala AbstractExportService::process(): hanya queued/processing/
     * failed yang boleh diklaim — task ready tak pernah dijalankan ulang.
     */
    public function klaim(string $id): bool
    {
        return DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $id)
            ->where('Task_Type', self::TASK_TYPE)
            ->whereIn('Status', [
                AbstractExportService::STATUS_QUEUED,
                AbstractExportService::STATUS_PROCESSING,
                AbstractExportService::STATUS_FAILED,
            ])
            ->whereNull('Deleted_At')
            ->update([
                'Status' => AbstractExportService::STATUS_PROCESSING,
                'Started_At' => now(),
                'Updated_At' => now(),
            ]) > 0;
    }

    public function bumpProgress(string $id, int $processed, int $total): void
    {
        DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $id)->where('Task_Type', self::TASK_TYPE)
            ->update([
                'Processed_Records' => $processed,
                'Percent' => max(0, min(99, (int) round($processed / max(1, $total) * 100))),
                'Updated_At' => now(),
            ]);
    }

    /** Selesai — hasil {assigned, skipped, gagal[]} disimpan ke payload (terlihat di panel). */
    public function selesai(string $id, array $hasil): void
    {
        $row = $this->baris($id);
        $payload = $row ? (json_decode($row->Payload_Json ?: '{}', true) ?: []) : [];
        $payload['hasil'] = $hasil;

        DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $id)->where('Task_Type', self::TASK_TYPE)
            ->update([
                'Status' => AbstractExportService::STATUS_READY,
                'Percent' => 100,
                'Processed_Records' => DB::raw('Total_Records'),
                'Payload_Json' => json_encode($payload),
                'Error_Message' => ! empty($hasil['gagal'])
                    ? count($hasil['gagal']).' chunk gagal — lihat rincian.' : null,
                'Completed_At' => now(),
                'Updated_At' => now(),
            ]);
    }

    public function gagal(string $id, string $pesan): void
    {
        DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $id)->where('Task_Type', self::TASK_TYPE)
            ->update([
                'Status' => AbstractExportService::STATUS_FAILED,
                'Error_Message' => mb_substr($pesan, 0, 500),
                'Updated_At' => now(),
            ]);
    }

    public function isAborted(string $id): bool
    {
        $row = $this->baris($id);
        if (! $row) {
            return true;
        } // baris hilang = berhenti

        $payload = json_decode($row->Payload_Json ?: '{}', true) ?: [];

        return ! empty($payload['aborted']);
    }

    /** Sembunyikan dari panel (soft delete). */
    public function dismiss(string $id): void
    {
        DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $id)->where('Task_Type', self::TASK_TYPE)
            ->update(['Deleted_At' => now(), 'Updated_At' => now()]);
    }
}
