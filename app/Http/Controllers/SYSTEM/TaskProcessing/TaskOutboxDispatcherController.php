<?php

namespace App\Http\Controllers\SYSTEM\TaskProcessing;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\SYSTEM\TaskProcessing\DispatchTaskOutboxRequest;
use App\Services\SYSTEM\TaskProcessing\TaskOutboxDispatcher;
use Illuminate\Support\Str;
use Throwable;

class TaskOutboxDispatcherController extends Controller
{
    public function __construct(private readonly TaskOutboxDispatcher $dispatcher) {}

    public function dispatch(DispatchTaskOutboxRequest $request)
    {
        $validated = $request->validated();
        $worker = 'http-dispatcher:'.Str::uuid()->toString();

        try {
            $result = $this->dispatcher->dispatchBatch(
                $worker,
                (int) ($validated['limit'] ?? 20),
                (int) ($validated['lease_minutes'] ?? 5),
            );

            return ResponseHelper::success($result, 'Outbox dispatcher selesai dijalankan.');
        } catch (Throwable $exception) {
            report($exception);

            return ResponseHelper::error('Outbox dispatcher gagal dijalankan.', 500);
        }
    }
}
