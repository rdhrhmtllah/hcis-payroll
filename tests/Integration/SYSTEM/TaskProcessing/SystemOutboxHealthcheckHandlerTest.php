<?php

namespace Tests\Integration\SYSTEM\TaskProcessing;

use App\Models\SYSTEM\Application\Outbox\Models\SystemTaskOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SystemOutboxHealthcheckHandlerTest extends TestCase
{
    public function test_registered_healthcheck_handler_completes_a_real_outbox_row(): void
    {
        config(['services.project_config.OUTBOX_HANDLER_BEARER_TOKEN' => 'handler-secret']);
        $service = app(\App\Services\SYSTEM\TaskProcessing\TaskOutboxService::class);
        $row = $service->create([
            'Task_Type' => 'SYSTEM_OUTBOX_HEALTHCHECK',
            'Idempotency_Key' => 'TEST:HEALTHCHECK:'.Str::uuid(),
            'Queue_Name' => 'system',
            'Task_Url' => 'https://example.test/api/v1/internal/task-outbox/execute',
            'Payload' => ['source' => 'integration-test'],
        ]);
        DB::table('N_SYSTEM_Task_Outbox')->where('Id_Task_Outbox', $row->Id_Task_Outbox)->update([
            'Status' => SystemTaskOutbox::STATUS_DISPATCHED,
            'Cloud_Task_Name' => 'projects/test/tasks/healthcheck',
        ]);

        try {
            $response = $this->withHeader('Authorization', 'Bearer handler-secret')->postJson('/api/v1/internal/task-outbox/execute', [
                '_outbox_id' => $row->Id_Task_Outbox,
                '_idempotency_key' => $row->Idempotency_Key,
                '_task_type' => $row->Task_Type,
                'data' => ['source' => 'integration-test'],
            ]);

            $response->assertOk()
                ->assertJsonPath('result.status', 'COMPLETED')
                ->assertJsonPath('result.result.payload_received', true);
            $this->assertSame(SystemTaskOutbox::STATUS_COMPLETED, $row->fresh()->Status);
        } finally {
            DB::table('N_SYSTEM_Task_Outbox')->where('Id_Task_Outbox', $row->Id_Task_Outbox)->delete();
        }
    }
}
