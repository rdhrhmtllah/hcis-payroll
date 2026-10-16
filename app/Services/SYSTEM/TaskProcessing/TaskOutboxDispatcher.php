<?php

namespace App\Services\SYSTEM\TaskProcessing;

use Throwable;

class TaskOutboxDispatcher
{
    public function __construct(private readonly TaskOutboxService $outbox, private readonly CloudTaskGateway $gateway) {}

    public function dispatchBatch(string $worker, int $limit = 20, int $leaseMinutes = 5): array
    {
        return $this->kirim($this->outbox->claimBatch($worker, $limit, $leaseMinutes), $worker);
    }

    /**
     * Mengirim baris tertentu — dipakai pemanggil yang baru saja menulis barisnya sendiri dan
     * ingin tahu apakah BARIS ITU terkirim. Hasilnya membawa `galat` per id supaya pemanggil
     * bisa menyebut sebab sebenarnya ke pengguna, bukan "sedang diproses" yang tidak benar.
     *
     * @param  list<int>  $ids
     * @return array{claimed:int, dispatched:int, retry_pending:int, failed:int, galat:array<int,string>}
     */
    public function dispatchIds(array $ids, string $worker, int $leaseMinutes = 5): array
    {
        return $this->kirim($this->outbox->claimIds($ids, $worker, $leaseMinutes), $worker, true);
    }

    private function kirim(iterable $claimed, string $worker, bool $denganGalat = false): array
    {
        $result = ['claimed' => count($claimed), 'dispatched' => 0, 'retry_pending' => 0, 'failed' => 0];
        $galat = [];

        foreach ($claimed as $row) {
            try {
                $payload = [
                    '_outbox_id' => $row->Id_Task_Outbox,
                    '_idempotency_key' => $row->Idempotency_Key,
                    '_task_type' => $row->Task_Type,
                    'data' => is_array($row->Payload) ? $row->Payload : [],
                ];
                $taskName = $this->gateway->dispatch($row->Queue_Name, $row->Task_Url ?? '', $payload, $row->Available_At);
                if ($this->outbox->markDispatched($row->Id_Task_Outbox, $worker, $taskName)) {
                    $result['dispatched']++;
                }
            } catch (Throwable $exception) {
                $attempt = ((int) $row->Attempt_Count) + 1;
                $message = substr($exception->getMessage(), 0, 4000);
                $galat[(int) $row->Id_Task_Outbox] = $message;
                if ($attempt >= (int) $row->Max_Attempts) {
                    if ($this->outbox->markDispatchFailed($row->Id_Task_Outbox, $worker, $message)) {
                        $result['failed']++;
                    }
                } elseif ($this->outbox->markDispatchRetry($row->Id_Task_Outbox, $worker, $message)) {
                    $result['retry_pending']++;
                }
            }
        }

        return $denganGalat ? $result + ['galat' => $galat] : $result;
    }
}
