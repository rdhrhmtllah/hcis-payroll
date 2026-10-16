<?php

namespace App\Services\SYSTEM\TaskProcessing;

use App\Models\SYSTEM\Application\Outbox\Models\SystemTaskOutbox;

class SystemOutboxHealthcheckHandler implements TaskHandler
{
    public function handle(SystemTaskOutbox $outbox, array $payload): ?array
    {
        return [
            'task_type' => $outbox->Task_Type,
            'idempotency_key' => $outbox->Idempotency_Key,
            'payload_received' => true,
            'payload_keys' => array_keys($payload),
        ];
    }
}
