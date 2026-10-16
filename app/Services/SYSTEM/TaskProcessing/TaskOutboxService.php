<?php

namespace App\Services\SYSTEM\TaskProcessing;

use App\Helpers\FormatTanggalHelper;
use App\Models\SYSTEM\Application\Outbox\Models\SystemTaskOutbox;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TaskOutboxService
{
    public function create(array $data): SystemTaskOutbox
    {
        foreach (['Task_Type', 'Idempotency_Key', 'Queue_Name'] as $required) {
            if (empty($data[$required])) {
                throw new RuntimeException($required.' wajib diisi.');
            }
        }

        // Task_Url tanpa skema gagal SENYAP: Cloud Tasks menolaknya, tasknya tak
        // pernah terbuat, dan pekerjaannya menggantung tanpa galat di layar.
        // APP_URL di lingkungan ini memang ditulis tanpa skema, jadi merangkai
        // config('app.url') secara langsung selalu menghasilkan URL cacat —
        // pemanggil wajib lewat CloudTaskService::absoluteUrl().
        $taskUrl = $data['Task_Url'] ?? null;
        if ($taskUrl !== null && trim((string) $taskUrl) !== '' && ! preg_match('~^https?://~i', (string) $taskUrl)) {
            throw new RuntimeException(
                'Task_Url wajib memuat skema http/https; pakai CloudTaskService::absoluteUrl(). Diterima: '.$taskUrl,
            );
        }

        $payload = $data['Payload'] ?? null;
        if (is_resource($payload)) {
            throw new RuntimeException('Payload resource tidak didukung.');
        }

        $attributes = [
            'Task_Type' => $data['Task_Type'],
            'Aggregate_Type' => $data['Aggregate_Type'] ?? null,
            'Aggregate_Id' => $data['Aggregate_Id'] ?? null,
            'Idempotency_Key' => $data['Idempotency_Key'],
            'Queue_Name' => $data['Queue_Name'],
            'Task_Url' => $data['Task_Url'] ?? null,
            'Payload' => $payload,
            'Status' => $data['Status'] ?? SystemTaskOutbox::STATUS_QUEUED,
            'Attempt_Count' => $data['Attempt_Count'] ?? 0,
            'Max_Attempts' => $data['Max_Attempts'] ?? 3,
            'Available_At' => $data['Available_At'] ?? FormatTanggalHelper::getCurrentTime('All')->utc(),
            'Created_By' => $data['Created_By'] ?? null,
            'Sender_Employee_Code' => $data['Sender_Employee_Code'] ?? null,
            'Source_Module' => $data['Source_Module'] ?? null,
            'Source_Feature' => $data['Source_Feature'] ?? null,
            'Message_Template' => $data['Message_Template'] ?? null,
            'Provider_Name' => $data['Provider_Name'] ?? null,
        ];

        if (! in_array($attributes['Status'], [SystemTaskOutbox::STATUS_QUEUED, SystemTaskOutbox::STATUS_RETRY_PENDING], true)) {
            throw new RuntimeException('Status awal Outbox tidak valid.');
        }

        $existing = SystemTaskOutbox::query()->where('Idempotency_Key', $attributes['Idempotency_Key'])->first();
        if ($existing) {
            return $existing;
        }

        try {
            return SystemTaskOutbox::query()->create($attributes);
        } catch (QueryException $exception) {
            // Unique-key race: another request may have created the same intent.
            $existing = SystemTaskOutbox::query()->where('Idempotency_Key', $attributes['Idempotency_Key'])->first();
            if ($existing) {
                return $existing;
            }

            throw $exception;
        }
    }

    public function claimBatch(string $worker, int $limit = 20, int $leaseMinutes = 5): Collection
    {
        if ($limit < 1 || $limit > 100) {
            throw new RuntimeException('Limit claim harus berada di antara 1 dan 100.');
        }
        if ($leaseMinutes < 1 || $leaseMinutes > 60) {
            throw new RuntimeException('Lease harus berada di antara 1 dan 60 menit.');
        }

        return $this->klaim($worker, $leaseMinutes, fn ($q) => $q->limit($limit));
    }

    /**
     * Mengklaim baris TERTENTU — milik permintaan yang baru saja menulisnya.
     *
     * `claimBatch()` selalu memungut baris TERTUA. Pemanggil yang memicu pengiriman sesudah
     * menulis barisnya sendiri dulu memakai `claimBatch(1)`; begitu antrean punya tunggakan,
     * yang terkirim adalah baris lama orang lain dan barisnya sendiri tidak pernah tersentuh,
     * sementara layar sudah berkata "sedang diproses" (prod, Okt 2026: ajukan ulang ujian LMS).
     *
     * @param  list<int>  $ids
     */
    public function claimIds(array $ids, string $worker, int $leaseMinutes = 5): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return new Collection;
        }
        if (count($ids) > 100) {
            throw new RuntimeException('Klaim per id paling banyak 100 baris.');
        }
        if ($leaseMinutes < 1 || $leaseMinutes > 60) {
            throw new RuntimeException('Lease harus berada di antara 1 dan 60 menit.');
        }

        return $this->klaim($worker, $leaseMinutes, fn ($q) => $q->whereIn('Id_Task_Outbox', $ids));
    }

    private function klaim(string $worker, int $leaseMinutes, callable $saring): Collection
    {
        $now = FormatTanggalHelper::getCurrentTime('All')->utc();
        $expired = $now->copy()->subMinutes($leaseMinutes);

        return DB::transaction(function () use ($worker, $now, $expired, $saring) {
            $query = SystemTaskOutbox::query()
                ->where(function ($query) use ($now, $expired) {
                    $query->where(function ($eligible) use ($now) {
                        $eligible->whereIn('Status', [SystemTaskOutbox::STATUS_QUEUED, SystemTaskOutbox::STATUS_RETRY_PENDING])
                            ->where('Available_At', '<=', $now);
                    })->orWhere(function ($stale) use ($expired) {
                        $stale->where('Status', SystemTaskOutbox::STATUS_DISPATCHING)
                            ->where('Locked_At', '<', $expired);
                    });
                })
                ->orderBy('Id_Task_Outbox');
            $rows = $saring($query)
                ->lock('with (updlock, readpast, rowlock)')
                ->get();

            foreach ($rows as $row) {
                $row->forceFill([
                    'Status' => SystemTaskOutbox::STATUS_DISPATCHING,
                    'Locked_At' => $now,
                    'Locked_By' => $worker,
                    'Updated_At' => $now,
                    'Updated_By' => $worker,
                ])->save();
            }

            return $rows->fresh();
        });
    }

    public function markDispatched(int $id, string $worker, string $cloudTaskName): bool
    {
        return $this->transition($id, SystemTaskOutbox::STATUS_DISPATCHING, [
            'Status' => SystemTaskOutbox::STATUS_DISPATCHED,
            'Dispatched_At' => FormatTanggalHelper::getCurrentTime('All')->utc(),
            'Cloud_Task_Name' => $cloudTaskName,
            'Updated_At' => FormatTanggalHelper::getCurrentTime('All')->utc(),
            'Updated_By' => $worker,
        ], $worker);
    }

    public function markDispatchRetry(int $id, string $worker, string $error, int $delaySeconds = 60): bool
    {
        $now = FormatTanggalHelper::getCurrentTime('All')->utc();

        return $this->transition($id, SystemTaskOutbox::STATUS_DISPATCHING, [
            'Status' => SystemTaskOutbox::STATUS_RETRY_PENDING,
            'Attempt_Count' => DB::raw('Attempt_Count + 1'),
            'Available_At' => $now->copy()->addSeconds(max(1, $delaySeconds)),
            'Last_Error' => $error,
            'Locked_At' => null,
            'Locked_By' => null,
            'Updated_At' => $now,
            'Updated_By' => $worker,
        ], $worker);
    }

    public function markDispatchFailed(int $id, string $worker, string $error): bool
    {
        return $this->transition($id, SystemTaskOutbox::STATUS_DISPATCHING, [
            'Status' => SystemTaskOutbox::STATUS_FAILED,
            'Attempt_Count' => DB::raw('Attempt_Count + 1'),
            'Last_Error' => $error,
            'Locked_At' => null,
            'Locked_By' => null,
            'Updated_At' => FormatTanggalHelper::getCurrentTime('All')->utc(),
            'Updated_By' => $worker,
        ], $worker);
    }

    public function markProcessing(int $id, string $worker): bool
    {
        return $this->transition($id, SystemTaskOutbox::STATUS_DISPATCHED, [
            'Status' => SystemTaskOutbox::STATUS_PROCESSING,
            'Updated_At' => FormatTanggalHelper::getCurrentTime('All')->utc(),
            'Updated_By' => $worker,
        ]);
    }

    public function markCompleted(int $id, string $worker, array $provider = []): bool
    {
        $now = FormatTanggalHelper::getCurrentTime('All')->utc();

        return $this->transition($id, SystemTaskOutbox::STATUS_PROCESSING, [
            'Status' => SystemTaskOutbox::STATUS_COMPLETED,
            'Last_Error' => null,
            'Provider_Name' => $provider['name'] ?? null,
            'Provider_Status' => $provider['status'] ?? null,
            'Provider_Message_Id' => $provider['message_id'] ?? null,
            'Provider_Response' => isset($provider['response']) ? json_encode($provider['response'], JSON_UNESCAPED_UNICODE) : null,
            'Processed_At' => $now,
            'Locked_At' => null,
            'Locked_By' => null,
            'Updated_At' => $now,
            'Updated_By' => $worker,
        ]);
    }

    public function markRetryPending(int $id, string $worker, string $error, int $delaySeconds = 60): bool
    {
        $now = FormatTanggalHelper::getCurrentTime('All')->utc();

        return $this->transition($id, SystemTaskOutbox::STATUS_PROCESSING, [
            'Status' => SystemTaskOutbox::STATUS_RETRY_PENDING,
            'Attempt_Count' => DB::raw('Attempt_Count + 1'),
            'Available_At' => $now->copy()->addSeconds(max(1, $delaySeconds)),
            'Last_Error' => $error,
            'Locked_At' => null,
            'Locked_By' => null,
            'Updated_At' => $now,
            'Updated_By' => $worker,
        ]);
    }

    public function recordProviderResult(int $id, string $worker, array $provider): bool
    {
        return SystemTaskOutbox::query()
            ->where('Id_Task_Outbox', $id)
            ->where('Locked_By', $worker)
            ->update([
                'Provider_Name' => $provider['name'] ?? null,
                'Provider_Status' => $provider['status'] ?? null,
                'Provider_Message_Id' => $provider['message_id'] ?? null,
                'Provider_Response' => isset($provider['response']) ? json_encode($provider['response'], JSON_UNESCAPED_UNICODE) : null,
                'Updated_At' => FormatTanggalHelper::getCurrentTime('All')->utc(),
                'Updated_By' => $worker,
            ]) === 1;
    }

    public function markFailed(int $id, string $worker, string $error): bool
    {
        return $this->transition($id, SystemTaskOutbox::STATUS_PROCESSING, [
            'Status' => SystemTaskOutbox::STATUS_FAILED,
            'Attempt_Count' => DB::raw('Attempt_Count + 1'),
            'Last_Error' => $error,
            'Locked_At' => null,
            'Locked_By' => null,
            'Updated_At' => FormatTanggalHelper::getCurrentTime('All')->utc(),
            'Updated_By' => $worker,
        ]);
    }

    private function transition(int $id, string $fromStatus, array $values, ?string $worker = null): bool
    {
        $query = SystemTaskOutbox::query()->where('Id_Task_Outbox', $id)->where('Status', $fromStatus);
        if ($worker !== null) {
            $query->where('Locked_By', $worker);
        }

        return $query->update($values) === 1;
    }
}
