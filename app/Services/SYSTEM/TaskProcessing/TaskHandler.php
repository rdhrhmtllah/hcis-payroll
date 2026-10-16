<?php

namespace App\Services\SYSTEM\TaskProcessing;

use App\Models\SYSTEM\Application\Outbox\Models\SystemTaskOutbox;

interface TaskHandler
{
    /** @return array<string, mixed>|null provider/business result */
    public function handle(SystemTaskOutbox $outbox, array $payload): ?array;
}
