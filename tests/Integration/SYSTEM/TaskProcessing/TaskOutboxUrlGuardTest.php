<?php

namespace Tests\Integration\SYSTEM\TaskProcessing;

use App\Services\SYSTEM\TaskProcessing\CloudTaskService;
use App\Services\SYSTEM\TaskProcessing\TaskOutboxService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Task_Url tanpa skema adalah kegagalan SENYAP.
 *
 * Cloud Tasks menolak URL semacam itu, tasknya tak pernah terbuat, dan
 * pekerjaannya menggantung tanpa galat di layar — persis yang sudah pernah
 * terjadi dan dicatat di KontenVerifikasiWebhookController. Karena diamnya
 * itulah penjaganya harus berada di batas TULIS, bukan diserahkan pada
 * kedisiplinan tiap pemanggil.
 */
class TaskOutboxUrlGuardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_task_url_tanpa_skema_ditolak_saat_ditulis(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/skema/i');

        app(TaskOutboxService::class)->create($this->data('n8n.ajt.my.id/api/v1/internal/task-outbox/execute'));
    }

    public function test_task_url_berskema_diterima(): void
    {
        $baris = app(TaskOutboxService::class)->create(
            $this->data('https://n8n.ajt.my.id/api/v1/internal/task-outbox/execute'),
        );

        $this->assertSame(
            'https://n8n.ajt.my.id/api/v1/internal/task-outbox/execute',
            $baris->Task_Url,
        );
    }

    public function test_task_url_kosong_tetap_boleh(): void
    {
        // Sebagian antrean tidak memakai callback URL; penjaga ini hanya
        // mengurus nilai yang terisi tapi cacat.
        $baris = app(TaskOutboxService::class)->create($this->data(null));

        $this->assertNull($baris->Task_Url);
    }

    public function test_absolute_url_menambal_app_url_yang_tanpa_skema(): void
    {
        // Inilah jalan keluar yang wajib dipakai pemanggil: APP_URL di
        // lingkungan ini memang ditulis tanpa skema.
        config(['app.url' => 'n8n.ajt.my.id']);

        $url = CloudTaskService::absoluteUrl('/api/v1/internal/task-outbox/execute');

        $this->assertMatchesRegularExpression('~^https?://~', $url);
        $this->assertStringEndsWith('/api/v1/internal/task-outbox/execute', $url);
    }

    /**
     * @return array<string,mixed>
     */
    private function data(?string $taskUrl): array
    {
        return [
            'Task_Type' => 'UJI_PENJAGA_URL',
            'Idempotency_Key' => 'UJI-'.Str::upper(Str::random(20)),
            'Queue_Name' => 'lms',
            'Task_Url' => $taskUrl,
            'Payload' => ['uji' => true],
            'Source_Module' => 'SYSTEM',
            'Source_Feature' => 'TaskOutboxUrlGuard',
        ];
    }
}
