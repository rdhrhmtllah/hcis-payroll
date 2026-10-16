<?php

namespace App\Listeners;

use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use Illuminate\Support\Facades\Log;
use Stackkit\LaravelGoogleCloudTasksQueue\Events\TaskCreated;

/**
 * CaptureExportCloudTaskName
 *
 * Saat job export di-dispatch ke Cloud Tasks, vendor stackkit memancarkan event
 * TaskCreated berisi objek Task lengkap. Listener ini menangkap NAMA task GCP
 * (Task::getName()) dan menyimpannya ke kolom Cloud_Task_Name pada baris job
 * (N_HRIS_Async_Tasks) yang sesuai — supaya fitur "Batalkan" bisa menghapus
 * task itu di Cloud Tasks sebelum sempat dieksekusi.
 *
 * Hubungan task -> job dicari lewat Export_Id yang ada di payload command job.
 * Best-effort: kegagalan capture TIDAK boleh menggagalkan dispatch.
 */
class CaptureExportCloudTaskName
{
    public function handle(TaskCreated $event): void
    {
        try {
            $task = $event->task;

            $taskName = trim((string) $task->getName());
            if ($taskName === '') {
                return;
            }

            // Body HTTP berisi payload Laravel job (JSON). Ambil dari HttpRequest.
            $httpRequest = method_exists($task, 'getHttpRequest') ? $task->getHttpRequest() : null;
            if (! $httpRequest) {
                return;
            }
            $body = (string) $httpRequest->getBody();
            if ($body === '') {
                return;
            }

            $exportId = $this->extractExportId($body);
            if ($exportId === null) {
                return;
            }

            AbstractExportService::setCloudTaskName($exportId, $taskName);
        } catch (\Throwable $e) {
            Log::channel('UpdatePerformanceController')->warning(
                'CaptureExportCloudTaskName gagal: '.$e->getMessage(),
            );
        }
    }

    /**
     * Ekstrak Export_Id dari body task. Body = payload queue Laravel
     * ({ data: { command: "<serialized job>" }, ... }). Job export menyimpan
     * properti protected $exportId. Kita baca via unserialize aman; bila gagal,
     * fallback regex ke pola export id (kpi-exp-… / lms-exp-…).
     */
    private function extractExportId(string $body): ?string
    {
        $payload = json_decode($body, true);
        $command = $payload['data']['command'] ?? null;
        if (! is_string($command) || $command === '') {
            return null;
        }

        // Hanya tangani job export (hindari kerja sia-sia utk job lain).
        if (! str_contains($command, 'ExportJob')) {
            return null;
        }

        // 1) Coba unserialize aman -> __PHP_Incomplete_Class, properti protected
        //    ter-mangle jadi "\0*\0exportId".
        if (str_starts_with($command, 'O:')) {
            try {
                $obj = unserialize($command, ['allowed_classes' => false]);
                $arr = (array) $obj;
                foreach ($arr as $key => $val) {
                    if (is_string($val) && str_ends_with($key, 'exportId')) {
                        $val = trim($val);
                        if ($val !== '') {
                            return $val;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // lanjut ke fallback regex
            }
        }

        // 2) Fallback regex: cari pola export id pada string command.
        if (preg_match('/(?:kpi|lms|sop)-exp-\d{8}-\d{6}-[A-Z0-9]+/', $command, $m)) {
            return $m[0];
        }

        return null;
    }
}
