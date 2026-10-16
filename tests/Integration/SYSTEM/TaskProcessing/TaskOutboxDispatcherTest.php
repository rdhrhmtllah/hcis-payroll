<?php

namespace Tests\Integration\SYSTEM\TaskProcessing;

use App\Models\SYSTEM\Application\Outbox\Models\SystemTaskOutbox;
use App\Services\SYSTEM\TaskProcessing\CloudTaskGateway;
use App\Services\SYSTEM\TaskProcessing\TaskOutboxDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskOutboxDispatcherTest extends TestCase
{
    public function test_it_marks_successful_dispatch(): void
    {
        $this->assertSame('Web_HRIS', DB::selectOne('SELECT DB_NAME() db')->db);
        $service = app(\App\Services\SYSTEM\TaskProcessing\TaskOutboxService::class);
        $key = 'TEST:DISPATCHER:'.Str::uuid();
        $row = $service->create([
            'Task_Type' => 'TEST_DISPATCHER',
            'Idempotency_Key' => $key,
            'Queue_Name' => 'test',
            'Task_Url' => 'https://example.test/task',
            'Payload' => ['probe' => true],
            'Max_Attempts' => 3,
        ]);
        $this->app->instance(CloudTaskGateway::class, new class implements CloudTaskGateway
        {
            public function dispatch(string $queue, string $url, array $payload, ?\DateTimeInterface $availableAt = null): string
            {
                return 'projects/test/locations/test/queues/test/tasks/fake-1';
            }
        });

        try {
            $result = app(TaskOutboxDispatcher::class)->dispatchBatch('dispatcher-test', 5);
            $this->assertSame(['claimed' => 1, 'dispatched' => 1, 'retry_pending' => 0, 'failed' => 0], $result);
            $this->assertSame(SystemTaskOutbox::STATUS_DISPATCHED, $row->fresh()->Status);
            $this->assertSame('projects/test/locations/test/queues/test/tasks/fake-1', $row->fresh()->Cloud_Task_Name);
        } finally {
            DB::table('N_SYSTEM_Task_Outbox')->where('Id_Task_Outbox', $row->Id_Task_Outbox)->delete();
        }
    }

    public function test_it_marks_dispatch_failure_as_retry_pending(): void
    {
        $service = app(\App\Services\SYSTEM\TaskProcessing\TaskOutboxService::class);
        $row = $service->create([
            'Task_Type' => 'TEST_DISPATCH_FAILURE',
            'Idempotency_Key' => 'TEST:DISPATCH_FAILURE:'.Str::uuid(),
            'Queue_Name' => 'test',
            'Task_Url' => 'https://example.test/task',
            'Payload' => ['probe' => 'failure'],
            'Max_Attempts' => 3,
        ]);
        $this->app->instance(CloudTaskGateway::class, new class implements CloudTaskGateway
        {
            public function dispatch(string $queue, string $url, array $payload, ?\DateTimeInterface $availableAt = null): string
            {
                throw new \RuntimeException('temporary gateway failure');
            }
        });

        try {
            $result = app(TaskOutboxDispatcher::class)->dispatchBatch('dispatcher-test', 5);
            $this->assertSame(['claimed' => 1, 'dispatched' => 0, 'retry_pending' => 1, 'failed' => 0], $result);
            $fresh = $row->fresh();
            $this->assertSame(SystemTaskOutbox::STATUS_RETRY_PENDING, $fresh->Status);
            $this->assertSame(1, $fresh->Attempt_Count);
            $this->assertSame('temporary gateway failure', $fresh->Last_Error);
        } finally {
            DB::table('N_SYSTEM_Task_Outbox')->where('Id_Task_Outbox', $row->Id_Task_Outbox)->delete();
        }
    }

    /**
     * Prod, Okt 2026: ajukan ulang ujian LMS memanggil dispatchBatch(1) sesudah menulis
     * barisnya, sehingga yang terkirim selalu tunggakan TERTUA dan barisnya sendiri tak
     * pernah tersentuh. dispatchIds() wajib mengirim baris yang diminta, bukan yang tertua.
     */
    public function test_dispatch_ids_mengirim_baris_sendiri_walau_ada_tunggakan_lebih_lama(): void
    {
        $service = app(\App\Services\SYSTEM\TaskProcessing\TaskOutboxService::class);
        $baris = fn (string $label) => $service->create([
            'Task_Type' => 'TEST_DISPATCH_IDS',
            'Idempotency_Key' => 'TEST:DISPATCH_IDS:'.$label.':'.Str::uuid(),
            'Queue_Name' => 'test',
            'Task_Url' => 'https://example.test/task',
            'Payload' => ['probe' => $label],
            'Max_Attempts' => 3,
        ]);
        $tunggakan = $baris('lama');
        $milikSendiri = $baris('baru');

        $this->app->instance(CloudTaskGateway::class, new class implements CloudTaskGateway
        {
            public function dispatch(string $queue, string $url, array $payload, ?\DateTimeInterface $availableAt = null): string
            {
                return 'projects/test/locations/test/queues/test/tasks/'.$payload['_outbox_id'];
            }
        });

        try {
            $hasil = app(TaskOutboxDispatcher::class)->dispatchIds([(int) $milikSendiri->Id_Task_Outbox], 'dispatcher-test');

            $this->assertSame(1, $hasil['dispatched']);
            $this->assertSame([], $hasil['galat']);
            $this->assertSame(SystemTaskOutbox::STATUS_DISPATCHED, $milikSendiri->fresh()->Status);
            $this->assertSame(SystemTaskOutbox::STATUS_QUEUED, $tunggakan->fresh()->Status, 'Tunggakan orang lain tidak boleh ikut terkirim.');
        } finally {
            DB::table('N_SYSTEM_Task_Outbox')->whereIn('Id_Task_Outbox', [$tunggakan->Id_Task_Outbox, $milikSendiri->Id_Task_Outbox])->delete();
        }
    }

    public function test_dispatch_ids_melaporkan_sebab_gagal_per_baris(): void
    {
        $service = app(\App\Services\SYSTEM\TaskProcessing\TaskOutboxService::class);
        $row = $service->create([
            'Task_Type' => 'TEST_DISPATCH_IDS_GAGAL',
            'Idempotency_Key' => 'TEST:DISPATCH_IDS_GAGAL:'.Str::uuid(),
            'Queue_Name' => 'test',
            'Task_Url' => 'https://example.test/task',
            'Payload' => ['probe' => 'gagal'],
            'Max_Attempts' => 3,
        ]);
        $this->app->instance(CloudTaskGateway::class, new class implements CloudTaskGateway
        {
            public function dispatch(string $queue, string $url, array $payload, ?\DateTimeInterface $availableAt = null): string
            {
                throw new \RuntimeException('Konfigurasi project/location/token Outbox Google Cloud belum tersedia.');
            }
        });

        try {
            $hasil = app(TaskOutboxDispatcher::class)->dispatchIds([(int) $row->Id_Task_Outbox], 'dispatcher-test');

            $this->assertSame(0, $hasil['dispatched']);
            $this->assertStringContainsString('belum tersedia', $hasil['galat'][(int) $row->Id_Task_Outbox]);
            $this->assertSame(SystemTaskOutbox::STATUS_RETRY_PENDING, $row->fresh()->Status);
        } finally {
            DB::table('N_SYSTEM_Task_Outbox')->where('Id_Task_Outbox', $row->Id_Task_Outbox)->delete();
        }
    }
}
