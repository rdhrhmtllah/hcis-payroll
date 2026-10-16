<?php

namespace App\Services\SYSTEM\TaskProcessing;

use App\Services\SYSTEM\Application\Diagnostics\PeringatanJaringanDalamTransaksi;
use Google\Cloud\Tasks\V2\Client\CloudTasksClient;
use Google\Cloud\Tasks\V2\CreateTaskRequest;
use Google\Cloud\Tasks\V2\DeleteTaskRequest;
use Google\Cloud\Tasks\V2\HttpRequest;
use Google\Cloud\Tasks\V2\Task;
use Google\Protobuf\Timestamp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CloudTaskService
{
    protected ?CloudTasksClient $client = null;

    protected $defaultProjectId;

    protected $defaultLocation;

    // Table name for Cloud Task audit log
    const LOG_TABLE = 'N_HRIS_Cloud_Task_Log';

    public function __construct()
    {
        $config = [];
        $keyPath = config('services.project_config.gcp.key_path');

        // Jika file JSON ada (Lokal), gunakan itu.
        // Jika tidak ada (Cloud Run), biarkan kosong agar pakai ADC.
        if (file_exists($keyPath)) {
            $config['credentials'] = $keyPath;
        }

        // Cloud Tasks client dibuat saat benar-benar dipakai. Route discovery,
        // request web biasa, dan command yang tidak mengirim task tidak boleh
        // gagal hanya karena ADC belum tersedia di environment tersebut.
        $this->clientConfig = $config;

        // Ambil dari config/env sebagai default
        $this->defaultProjectId = config('services.project_config.gcp.project_id', 'project-6a30e7a0-bed0-4614-a28');
        $this->defaultLocation = config('services.project_config.gcp.location', 'asia-southeast1');
    }

    protected array $clientConfig = [];

    protected function client(): CloudTasksClient
    {
        return $this->client ??= new CloudTasksClient($this->clientConfig);
    }

    /**
     * URL absolut untuk dijadikan sasaran sebuah Cloud Task.
     *
     * Cloud Tasks menolak URL tanpa skema, dan APP_URL di sejumlah lingkungan
     * ditulis tanpa "https://" (mis. "n8n.ajt.my.id"). Merangkai sendiri
     * config('app.url').$path karena itu menghasilkan task yang gagal dibuat —
     * dan gagalnya baru terlihat sebagai pekerjaan yang menggantung, bukan
     * sebagai galat di layar. Helper ini tinggal di sini, satu tempat, supaya
     * setiap pemanggil Cloud Task memakai aturan yang sama.
     */
    public static function absoluteUrl(string $path): string
    {
        $base = trim((string) config('app.url', ''));

        if ($base === '' && request()) {
            $base = request()->getSchemeAndHttpHost();
        }

        if ($base === '') {
            throw new \RuntimeException('APP_URL belum dikonfigurasi untuk Cloud Task callback.');
        }

        if (! preg_match('~^https?://~i', $base)) {
            $base = (request() ? request()->getScheme() : 'https').'://'.ltrim($base, '/');
        }

        $url = rtrim($base, '/').'/'.ltrim($path, '/');

        if (! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('~^https?://~i', $url)) {
            throw new \RuntimeException(
                'URL callback Cloud Task tidak valid. Pastikan APP_URL memakai http:// atau https://.',
            );
        }

        return $url;
    }

    /**
     * Dispatch task dengan auto-logging ke N_HRIS_Cloud_Task_Log.
     *
     * @param  string  $url  URL endpoint yang akan dipanggil oleh Cloud Task
     * @param  string  $queue  Nama queue GCP Cloud Tasks
     * @param  array  $payload  Data JSON yang dikirim ke endpoint
     * @param  string  $taskType  Identitas tipe task (e.g. 'CUTI_BERSAMA_EXECUTE')
     * @param  int|null  $referenceId  ID referensi (e.g. Id_Cuti_Bersama)
     * @param  string|null  $referenceNo  Label referensi (e.g. nama cuti bersama)
     * @param  string|null  $dispatchedBy  Kode admin yang men-dispatch
     * @param  \DateTimeInterface|null  $scheduledAt  Waktu eksekusi spesifik (prioritas utama)
     * @param  int  $delayInSeconds  Delay sederhana dalam detik (0 = langsung, dipakai jika scheduledAt null)
     * @param  string|null  $customLocation  Custom GCP location
     * @param  string|null  $customProjectId  Custom GCP project ID
     * @return int $logId — ID log di N_HRIS_Cloud_Task_Log
     */
    public function dispatch(
        string $url,
        string $queue,
        array $payload = [],
        string $taskType = 'GENERAL',
        ?int $referenceId = null,
        ?string $referenceNo = null,
        ?string $dispatchedBy = null,
        ?\DateTimeInterface $scheduledAt = null,
        int $delayInSeconds = 0,
        ?string $customLocation = null,
        ?string $customProjectId = null,
    ): int {
        $logId = $this->catat(
            url: $url,
            queue: $queue,
            payload: $payload,
            taskType: $taskType,
            referenceId: $referenceId,
            referenceNo: $referenceNo,
            dispatchedBy: $dispatchedBy,
            scheduledAt: $scheduledAt,
            delayInSeconds: $delayInSeconds,
        );

        $this->kirim($logId, $customLocation, $customProjectId);

        return $logId;
    }

    /**
     * Catat niat mengirim task — TANPA menyentuh jaringan.
     *
     * Barisnya di N_HRIS_Cloud_Task_Log adalah KLAIM: `hasActiveExecutionTask()`
     * dan kerabatnya membacanya untuk menolak dispatch kedua. Karena itu ia
     * aman — bahkan perlu — dipanggil DI DALAM transaksi yang memegang
     * `lockForUpdate`: justru kuncinya yang membuat klaim ini tak bisa
     * kembar.
     *
     * Yang TIDAK boleh ikut masuk transaksi adalah `kirim()`.
     *
     * @return int $logId — ID log di N_HRIS_Cloud_Task_Log
     */
    public function catat(
        string $url,
        string $queue,
        array $payload = [],
        string $taskType = 'GENERAL',
        ?int $referenceId = null,
        ?string $referenceNo = null,
        ?string $dispatchedBy = null,
        ?\DateTimeInterface $scheduledAt = null,
        int $delayInSeconds = 0,
    ): int {
        // ──────────────────────────────────────────
        // 1. Resolve waktu eksekusi: scheduledAt > delayInSeconds > langsung
        //    Log selalu record jam spesifik, bukan delay
        // ──────────────────────────────────────────
        $resolvedScheduledAt = null;
        if ($scheduledAt) {
            // Prioritas 1: Jam spesifik (e.g. "2026-03-20 08:00:00")
            $resolvedScheduledAt = $scheduledAt;
        } elseif ($delayInSeconds > 0) {
            // Prioritas 2: Delay → convert ke jam spesifik
            $resolvedScheduledAt = now()->addSeconds($delayInSeconds);
        }
        // else: null = langsung / sekarang
        // ──────────────────────────────────────────
        // 2. INSERT audit log → DISPATCHED
        // ──────────────────────────────────────────
        $logId = DB::table(self::LOG_TABLE)->insertGetId([
            'Task_Type' => $taskType,
            'Reference_Id' => $referenceId,
            'Reference_No' => $referenceNo,
            'Queue_Name' => $queue,
            'Task_Url' => $url,
            'Payload' => json_encode($payload),
            'Scheduled_At' => $resolvedScheduledAt,
            'Status' => 'DISPATCHED',
            'Dispatched_By' => $dispatchedBy,
            'Created_At' => now(),
        ]);

        return $logId;
    }

    /**
     * Kirim task yang sudah dicatat ke Google Cloud Tasks.
     *
     * INI panggilan jaringannya, dan karena itu ia TIDAK BOLEH dijalankan di
     * dalam transaksi: setiap baris yang terkunci akan menahan pembaca lain
     * selama Google menjawab, dan RCSI mati di basis data ini. Panggil sesudah
     * commit. `PeringatanJaringanDalamTransaksi` di bawah mencatat pelanggaran
     * berikutnya tanpa menunggu seseorang membaca rantai panggilannya lagi.
     */
    public function kirim(int $logId, ?string $customLocation = null, ?string $customProjectId = null): void
    {
        $log = DB::table(self::LOG_TABLE)->where('Id_Log', $logId)->first();

        if (! $log) {
            throw new \RuntimeException("Log Cloud Task {$logId} tidak ditemukan; tak ada yang bisa dikirim.");
        }

        $url = (string) $log->Task_Url;
        $queue = (string) $log->Queue_Name;
        $taskType = (string) $log->Task_Type;
        $referenceId = $log->Reference_Id;
        $payload = json_decode((string) $log->Payload, true) ?: [];
        $resolvedScheduledAt = $log->Scheduled_At ? \Carbon\Carbon::parse($log->Scheduled_At) : null;

        // ──────────────────────────────────────────
        // 2. Inject log ID ke payload agar handler bisa update record yang sama
        // ──────────────────────────────────────────
        $payload['_cloud_task_log_id'] = $logId;

        // ──────────────────────────────────────────
        // 3. Build & dispatch Cloud Task ke GCP
        // ──────────────────────────────────────────
        $projectId = $customProjectId ?? $this->defaultProjectId;
        $location = $customLocation ?? $this->defaultLocation;
        $parent = CloudTasksClient::queueName($projectId, $location, $queue);

        $httpRequest = new HttpRequest;
        $httpRequest->setUrl($url);
        $httpRequest->setHttpMethod(1); // POST
        $httpRequest->setHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.config('services.project_config.SCHEDULER_BEARER_TOKEN'),
        ]);
        $httpRequest->setBody(json_encode($payload));

        $task = new Task;
        $task->setHttpRequest($httpRequest);

        // Schedule berdasarkan jam spesifik yang sudah di-resolve
        if ($resolvedScheduledAt) {
            $timestamp = new Timestamp;
            $timestamp->setSeconds($resolvedScheduledAt->getTimestamp());
            $task->setScheduleTime($timestamp);
        }

        $request = new CreateTaskRequest;
        $request->setParent($parent);
        $request->setTask($task);

        /* createTask() adalah panggilan JARINGAN ke Google Cloud Tasks. Bila ia
           berjalan di dalam transaksi, setiap baris yang terkunci menahan
           pembaca lain selama panggilan itu berlangsung — dan RCSI mati di basis
           data ini. Sudah terbukti terjadi di CutiBersamaAdvancedController
           (temuan #18); peringatan ini membuat kejadian berikutnya terlihat
           tanpa menunggu seseorang membaca rantai panggilannya lagi. */
        PeringatanJaringanDalamTransaksi::periksa('Cloud Tasks createTask', [
            'antrean' => $queue,
            'url' => $url,
            'id_log' => $logId,
        ]);

        try {
            $createdTask = $this->client()->createTask($request);

            // Persist generated Cloud Task name to enable cancellation later.
            $createdTaskName = trim((string) $createdTask->getName());
            if ($createdTaskName !== '') {
                $payload['_gcp_task_name'] = $createdTaskName;

                DB::table(self::LOG_TABLE)
                    ->where('Id_Log', $logId)
                    ->update([
                        'Payload' => json_encode($payload),
                    ]);
            }
        } catch (\Exception $e) {
            // Update log: dispatch gagal
            DB::table(self::LOG_TABLE)
                ->where('Id_Log', $logId)
                ->update([
                    'Status' => 'FAILED',
                    'Error_Message' => 'Dispatch failed: '.substr($e->getMessage(), 0, 2000),
                    'Completed_At' => now(),
                ]);

            Log::error('CloudTaskService dispatch failed: '.$e->getMessage());
            throw $e;
        }

        Log::info("CloudTask dispatched: type={$taskType}, ref={$referenceId}, logId={$logId}");
    }

    /**
     * Delete a Cloud Task by its full task name.
     *
     * @return bool True when deletion request succeeds, false when task is already missing.
     */
    public function deleteTaskByName(string $taskName): bool
    {
        $taskName = trim($taskName);

        if ($taskName === '') {
            throw new \InvalidArgumentException('Cloud Task name tidak boleh kosong.');
        }

        try {
            $request = new DeleteTaskRequest;
            $request->setName($taskName);

            $this->client()->deleteTask($request);

            return true;
        } catch (\Throwable $e) {
            $message = strtolower((string) $e->getMessage());
            if (str_contains($message, 'not found') || str_contains($message, '404')) {
                return false;
            }

            throw $e;
        }
    }

    // =========================================================================
    // STATIC HELPER METHODS — untuk dipakai oleh handler endpoints
    // =========================================================================

    /**
     * Mark task sebagai PROCESSING. Panggil di awal handler.
     */
    public static function markProcessing(int $logId): void
    {
        DB::table(self::LOG_TABLE)
            ->where('Id_Log', $logId)
            ->update([
                'Status' => 'PROCESSING',
                'Started_At' => now(),
            ]);
    }

    /**
     * Mark task sebagai COMPLETED. Panggil setelah handler selesai sukses.
     */
    public static function markCompleted(
        int $logId,
        int $totalProcessed = 0,
        int $totalSuccess = 0,
        int $totalFailed = 0,
        ?array $resultSummary = null,
    ): void {
        $startedAt = DB::table(self::LOG_TABLE)->where('Id_Log', $logId)->value('Started_At');

        $duration = $startedAt ? now()->diffInSeconds(\Carbon\Carbon::parse($startedAt)) : null;

        DB::table(self::LOG_TABLE)
            ->where('Id_Log', $logId)
            ->update([
                'Status' => 'COMPLETED',
                'Completed_At' => now(),
                'Duration_Seconds' => $duration,
                'Total_Processed' => $totalProcessed,
                'Total_Success' => $totalSuccess,
                'Total_Failed' => $totalFailed,
                'Result_Summary' => $resultSummary ? json_encode($resultSummary) : null,
            ]);
    }

    /**
     * Mark task sebagai FAILED. Panggil saat handler error.
     */
    public static function markFailed(int $logId, string $errorMessage): void
    {
        $startedAt = DB::table(self::LOG_TABLE)->where('Id_Log', $logId)->value('Started_At');

        $duration = $startedAt ? now()->diffInSeconds(\Carbon\Carbon::parse($startedAt)) : null;

        DB::table(self::LOG_TABLE)
            ->where('Id_Log', $logId)
            ->update([
                'Status' => 'FAILED',
                'Completed_At' => now(),
                'Duration_Seconds' => $duration,
                'Error_Message' => substr($errorMessage, 0, 2000),
            ]);
    }
}
