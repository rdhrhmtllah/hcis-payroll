<?php

namespace Tests\Integration\SYSTEM\TaskProcessing;

use App\Models\SYSTEM\Application\Outbox\Models\SystemTaskOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsappOutboxHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.project_config.OUTBOX_HANDLER_BEARER_TOKEN' => 'handler-secret',
            'services.project_config.whatsapp.api_token' => 'fake-api-token',
            'services.project_config.whatsapp.phone_id' => 'https://graph.example.test/messages',
            'services.project_config.whatsapp.endpoint' => 'https://graph.example.test/messages',
        ]);
    }

    public function test_it_sends_whatsapp_and_persists_provider_result_and_metadata(): void
    {
        Http::fake(['https://graph.example.test/messages' => Http::response(['messages' => [['id' => 'wamid.TEST123', 'message_status' => 'accepted']], 'messaging_product' => 'whatsapp'], 200)]);
        $row = $this->seedRow('6281234567890');

        try {
            $response = $this->execute($row);
            $response->assertOk()->assertJsonPath('result.status', 'COMPLETED');
            $fresh = $row->fresh();
            $this->assertSame('COMPLETED', $fresh->Status);
            $this->assertSame('WHATSAPP_META', $fresh->Provider_Name);
            $this->assertSame('accepted', $fresh->Provider_Status);
            $this->assertSame('wamid.TEST123', $fresh->Provider_Message_Id);
            $this->assertSame('AJT-001', $fresh->Sender_Employee_Code);
            $this->assertSame('HRIS', $fresh->Source_Module);
            $this->assertSame('Approval', $fresh->Source_Feature);
            $this->assertSame('APPROVAL_ACCEPTED', $fresh->Message_Template);
            Http::assertSent(fn ($request) => $request->url() === 'https://graph.example.test/messages' && $request['to'] === '6281234567890');
        } finally {
            $this->cleanup($row);
        }
    }

    public function test_rate_limit_is_retryable_and_returns_503(): void
    {
        Http::fake(['https://graph.example.test/messages' => Http::response(['error' => ['message' => 'rate limit']], 429)]);
        $row = $this->seedRow('6281234567890');

        try {
            $this->execute($row)->assertStatus(503);
            $fresh = $row->fresh();
            $this->assertSame('RETRY_PENDING', $fresh->Status);
            $this->assertSame(1, $fresh->Attempt_Count);
            $this->assertStringContainsString('429', $fresh->Last_Error);
        } finally {
            $this->cleanup($row);
        }
    }

    public function test_invalid_destination_is_permanent_without_provider_call(): void
    {
        Http::fake();
        $row = $this->seedRow('081234');

        try {
            $this->execute($row)->assertOk()->assertJsonPath('result.status', 'FAILED');
            $this->assertSame('FAILED', $row->fresh()->Status);
            Http::assertNothingSent();
        } finally {
            $this->cleanup($row);
        }
    }

    private function seedRow(string $to): SystemTaskOutbox
    {
        $service = app(\App\Services\SYSTEM\TaskProcessing\TaskOutboxService::class);
        $row = $service->create([
            'Task_Type' => 'WHATSAPP_SEND',
            'Idempotency_Key' => 'TEST:WA:'.Str::uuid(),
            'Queue_Name' => 'whatsapp',
            'Task_Url' => 'https://example.test/api/v1/internal/task-outbox/execute',
            'Payload' => ['to' => $to, 'type' => 'template', 'template' => ['name' => 'APPROVAL_ACCEPTED']],
            'Sender_Employee_Code' => 'AJT-001',
            'Source_Module' => 'HRIS',
            'Source_Feature' => 'Approval',
            'Message_Template' => 'APPROVAL_ACCEPTED',
        ]);
        DB::table('N_SYSTEM_Task_Outbox')->where('Id_Task_Outbox', $row->Id_Task_Outbox)->update(['Status' => SystemTaskOutbox::STATUS_DISPATCHED]);

        return $row->fresh();
    }

    private function execute(SystemTaskOutbox $row)
    {
        return $this->withHeader('Authorization', 'Bearer handler-secret')->postJson('/api/v1/internal/task-outbox/execute', [
            '_outbox_id' => $row->Id_Task_Outbox,
            '_idempotency_key' => $row->Idempotency_Key,
            '_task_type' => $row->Task_Type,
            'data' => $row->Payload,
        ]);
    }

    private function cleanup(SystemTaskOutbox $row): void
    {
        DB::table('N_SYSTEM_Task_Outbox')->where('Id_Task_Outbox', $row->Id_Task_Outbox)->delete();
    }
}
