<?php

namespace Tests\Integration\SYSTEM\TaskProcessing;

use App\Models\SYSTEM\Application\Outbox\Models\SystemTaskOutbox;
use App\Services\SYSTEM\TaskProcessing\TaskOutboxService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SystemTaskOutboxServiceTest extends TestCase
{
    public function test_it_is_idempotent_and_supports_conditional_lifecycle_transitions(): void
    {
        $this->assertSame('Web_HRIS', DB::selectOne('SELECT DB_NAME() db')->db);

        $key = 'TEST:OUTBOX:'.Str::uuid();
        $service = app(TaskOutboxService::class);
        $row = null;

        try {
            $row = $service->create([
                'Task_Type' => 'TEST_OUTBOX',
                'Idempotency_Key' => $key,
                'Queue_Name' => 'test',
                'Payload' => ['test' => true],
                'Created_By' => 'phpunit',
            ]);
            $duplicate = $service->create([
                'Task_Type' => 'TEST_OUTBOX',
                'Idempotency_Key' => $key,
                'Queue_Name' => 'test',
            ]);

            $this->assertSame($row->Id_Task_Outbox, $duplicate->Id_Task_Outbox);
            $claimed = $service->claimBatch('phpunit-worker', 1);
            $this->assertCount(1, $claimed);
            $this->assertTrue($service->markDispatched($row->Id_Task_Outbox, 'phpunit-worker', 'test-cloud-task'));
            $this->assertTrue($service->markProcessing($row->Id_Task_Outbox, 'handler'));
            $this->assertTrue($service->markRetryPending($row->Id_Task_Outbox, 'handler', 'temporary failure', 1));
            $this->assertFalse($service->markCompleted($row->Id_Task_Outbox, 'handler'));
            $this->assertSame(SystemTaskOutbox::STATUS_RETRY_PENDING, $row->fresh()->Status);
        } finally {
            if ($row) {
                DB::table('N_SYSTEM_Task_Outbox')->where('Id_Task_Outbox', $row->Id_Task_Outbox)->delete();
            }
        }
    }
}
