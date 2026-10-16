<?php

namespace App\Http\Controllers\SYSTEM\TaskProcessing;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\SYSTEM\Application\Outbox\Models\SystemTaskOutbox;
use App\Services\SYSTEM\TaskProcessing\PermanentTaskException;
use App\Services\SYSTEM\TaskProcessing\RetryableTaskException;
use App\Services\SYSTEM\TaskProcessing\TaskHandlerRegistry;
use App\Services\SYSTEM\TaskProcessing\TaskOutboxService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class TaskOutboxHandlerController extends Controller
{
    public function __construct(
        private readonly TaskOutboxService $outbox,
        private readonly TaskHandlerRegistry $registry,
    ) {}

    public function execute(Request $request)
    {
        $outboxId = (int) $request->input('_outbox_id');
        $idempotencyKey = (string) $request->input('_idempotency_key');
        $taskType = (string) $request->input('_task_type');
        $payload = $request->input('data', []);
        $worker = 'http-handler:'.Str::uuid()->toString();

        if ($outboxId < 1 || $idempotencyKey === '' || $taskType === '' || ! is_array($payload)) {
            return ResponseHelper::error('Payload Outbox tidak lengkap.', 400);
        }

        $row = SystemTaskOutbox::query()->find($outboxId);
        if (! $row || ! hash_equals((string) $row->Idempotency_Key, $idempotencyKey) || $row->Task_Type !== $taskType) {
            return ResponseHelper::error('Outbox task tidak ditemukan atau metadata tidak cocok.', 400);
        }

        if ($row->Status === SystemTaskOutbox::STATUS_COMPLETED || $row->Status === SystemTaskOutbox::STATUS_FAILED || $row->Status === SystemTaskOutbox::STATUS_CANCELLED) {
            return ResponseHelper::success(['status' => $row->Status], 'Task sudah berada pada status final.');
        }

        if (! $this->outbox->markProcessing($outboxId, $worker)) {
            $row->refresh();
            if ($row->Status === SystemTaskOutbox::STATUS_COMPLETED || $row->Status === SystemTaskOutbox::STATUS_FAILED || $row->Status === SystemTaskOutbox::STATUS_CANCELLED) {
                return ResponseHelper::success(['status' => $row->Status], 'Task sudah diproses sebelumnya.');
            }

            return ResponseHelper::error('Task sedang diproses oleh worker lain.', 409);
        }

        try {
            $result = $this->registry->resolve($taskType)->handle($row->fresh(), $payload);
            $provider = is_array($result) && isset($result['name']) ? $result : [];
            $this->outbox->markCompleted($outboxId, $worker, $provider);

            return ResponseHelper::success(['status' => SystemTaskOutbox::STATUS_COMPLETED, 'result' => $result], 'Task selesai.');
        } catch (RetryableTaskException $exception) {
            $this->outbox->markRetryPending($outboxId, $worker, $exception->getMessage());

            return ResponseHelper::error('Task akan dicoba kembali.', 503);
        } catch (PermanentTaskException $exception) {
            $this->outbox->markFailed($outboxId, $worker, $exception->getMessage());

            return ResponseHelper::success(['status' => SystemTaskOutbox::STATUS_FAILED], 'Task gagal permanen.');
        } catch (Throwable $exception) {
            report($exception);
            $this->outbox->markRetryPending($outboxId, $worker, $exception->getMessage());

            return ResponseHelper::error('Task gagal sementara.', 503);
        }
    }
}
