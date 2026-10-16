<?php

namespace Tests\Integration\SYSTEM\TaskProcessing;

use App\Models\SYSTEM\Application\Outbox\Models\SystemTaskOutbox;
use App\Services\SYSTEM\TaskProcessing\PermanentTaskException;
use App\Services\SYSTEM\TaskProcessing\RetryableTaskException;
use App\Services\SYSTEM\TaskProcessing\TaskHandler;
use App\Services\SYSTEM\TaskProcessing\TaskHandlerRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskOutboxHandlerEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.project_config.OUTBOX_HANDLER_BEARER_TOKEN' => 'handler-secret']);
    }

    public function test_it_requires_handler_token(): void
    {
        $response = $this->postJson('/api/v1/internal/task-outbox/execute');
        $response->assertStatus(401);
    }

    public function test_it_marks_successful_task_completed(): void
    {
        $row = $this->seedDispatched('TEST_HANDLER_SUCCESS');
        $registry = new TaskHandlerRegistry;
        $registry->register('TEST_HANDLER_SUCCESS', new class implements TaskHandler
        {
            public function handle(SystemTaskOutbox $outbox, array $payload): ?array
            {
                return ['accepted' => true];
            }
        });
        $this->app->instance(TaskHandlerRegistry::class, $registry);

        try {
            $response = $this->execute($row);
            $response->assertOk()->assertJsonPath('result.status', 'COMPLETED');
            $this->assertSame('COMPLETED', $row->fresh()->Status);
        } finally {
            $this->cleanup($row);
        }
    }

    public function test_it_marks_retryable_failure_and_returns_503(): void
    {
        $row = $this->seedDispatched('TEST_HANDLER_RETRY');
        $registry = new TaskHandlerRegistry;
        $registry->register('TEST_HANDLER_RETRY', new class implements TaskHandler
        {
            public function handle(SystemTaskOutbox $outbox, array $payload): ?array
            {
                throw new RetryableTaskException('provider timeout');
            }
        });
        $this->app->instance(TaskHandlerRegistry::class, $registry);

        try {
            $response = $this->execute($row);
            $response->assertStatus(503);
            $this->assertSame('RETRY_PENDING', $row->fresh()->Status);
            $this->assertSame(1, $row->fresh()->Attempt_Count);
        } finally {
            $this->cleanup($row);
        }
    }

    public function test_it_marks_permanent_failure_without_cloud_task_retry(): void
    {
        $row = $this->seedDispatched('TEST_HANDLER_PERMANENT');
        $registry = new TaskHandlerRegistry;
        $registry->register('TEST_HANDLER_PERMANENT', new class implements TaskHandler
        {
            public function handle(SystemTaskOutbox $outbox, array $payload): ?array
            {
                throw new PermanentTaskException('invalid destination');
            }
        });
        $this->app->instance(TaskHandlerRegistry::class, $registry);

        try {
            $response = $this->execute($row);
            $response->assertOk()->assertJsonPath('result.status', 'FAILED');
            $this->assertSame('FAILED', $row->fresh()->Status);
        } finally {
            $this->cleanup($row);
        }
    }

    public function test_completed_task_is_idempotent(): void
    {
        $row = $this->seedDispatched('TEST_HANDLER_IDEMPOTENT');
        DB::table('N_SYSTEM_Task_Outbox')->where('Id_Task_Outbox', $row->Id_Task_Outbox)->update(['Status' => 'COMPLETED']);
        $registry = new TaskHandlerRegistry;
        $registry->register('TEST_HANDLER_IDEMPOTENT', new class implements TaskHandler
        {
            public function handle(SystemTaskOutbox $outbox, array $payload): ?array
            {
                throw new \LogicException('must not execute');
            }
        });
        $this->app->instance(TaskHandlerRegistry::class, $registry);

        try {
            $response = $this->execute($row);
            $response->assertOk()->assertJsonPath('result.status', 'COMPLETED');
        } finally {
            $this->cleanup($row);
        }
    }

    private function seedDispatched(string $type): SystemTaskOutbox
    {
        $service = app(\App\Services\SYSTEM\TaskProcessing\TaskOutboxService::class);
        $row = $service->create(['Task_Type' => $type, 'Idempotency_Key' => 'TEST:HANDLER:'.Str::uuid(), 'Queue_Name' => 'test', 'Task_Url' => 'https://example.test/task', 'Payload' => ['x' => 1]]);
        DB::table('N_SYSTEM_Task_Outbox')->where('Id_Task_Outbox', $row->Id_Task_Outbox)->update(['Status' => 'DISPATCHED']);

        return $row->fresh();
    }

    private function execute(SystemTaskOutbox $row)
    {
        return $this->withHeader('Authorization', 'Bearer handler-secret')->postJson('/api/v1/internal/task-outbox/execute', [
            '_outbox_id' => $row->Id_Task_Outbox, '_idempotency_key' => $row->Idempotency_Key, '_task_type' => $row->Task_Type, 'data' => ['hello' => 'world'],
        ]);
    }

    private function cleanup(SystemTaskOutbox $row): void
    {
        DB::table('N_SYSTEM_Task_Outbox')->where('Id_Task_Outbox', $row->Id_Task_Outbox)->delete();
    }
}
