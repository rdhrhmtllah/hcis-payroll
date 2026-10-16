<?php

namespace Tests\Integration\SYSTEM\TaskProcessing;

use App\Services\SYSTEM\TaskProcessing\CloudTaskGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskOutboxDispatcherEndpointTest extends TestCase
{
    public function test_it_rejects_requests_without_dispatcher_token(): void
    {
        config(['services.project_config.OUTBOX_DISPATCHER_BEARER_TOKEN' => 'endpoint-secret']);

        $response = $this->postJson('/api/v1/internal/task-outbox/dispatch');

        $response->assertStatus(401)->assertJson(['success' => false, 'status' => 401]);
    }

    public function test_it_dispatches_with_valid_token_and_validated_limits(): void
    {
        config(['services.project_config.OUTBOX_DISPATCHER_BEARER_TOKEN' => 'endpoint-secret']);
        $service = app(\App\Services\SYSTEM\TaskProcessing\TaskOutboxService::class);
        $row = $service->create([
            'Task_Type' => 'TEST_ENDPOINT_DISPATCH',
            'Idempotency_Key' => 'TEST:ENDPOINT:'.Str::uuid(),
            'Queue_Name' => 'test',
            'Task_Url' => 'https://example.test/task',
            'Payload' => ['endpoint' => true],
        ]);
        $this->app->instance(CloudTaskGateway::class, new class implements CloudTaskGateway
        {
            public function dispatch(string $queue, string $url, array $payload, ?\DateTimeInterface $availableAt = null): string
            {
                return 'projects/test/tasks/endpoint-fake';
            }
        });

        try {
            $response = $this->withHeader('Authorization', 'Bearer endpoint-secret')
                ->postJson('/api/v1/internal/task-outbox/dispatch', ['limit' => 1, 'lease_minutes' => 5]);

            $response->assertOk()->assertJsonPath('result.claimed', 1)->assertJsonPath('result.dispatched', 1);
            $this->assertSame('DISPATCHED', $row->fresh()->Status);
        } finally {
            DB::table('N_SYSTEM_Task_Outbox')->where('Id_Task_Outbox', $row->Id_Task_Outbox)->delete();
        }
    }

    public function test_it_rejects_invalid_batch_limits(): void
    {
        config(['services.project_config.OUTBOX_DISPATCHER_BEARER_TOKEN' => 'endpoint-secret']);

        $response = $this->withHeader('Authorization', 'Bearer endpoint-secret')
            ->postJson('/api/v1/internal/task-outbox/dispatch', ['limit' => 101]);

        $response->assertStatus(422)->assertJsonValidationErrors(['limit']);
    }
}
