<?php

namespace App\Http\Controllers\ESS\Attendance\Shift\Repair;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ESS\Attendance\Shift\Repair\StatusHariTanggalSelesaiBackfillRequest;
use App\Services\ESS\Attendance\Shift\Repair\StatusHariBackfillException;
use App\Services\ESS\Attendance\Shift\Repair\StatusHariTanggalSelesaiBackfillService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class StatusHariTanggalSelesaiBackfillController extends Controller
{
    public function __construct(private readonly StatusHariTanggalSelesaiBackfillService $service) {}

    public function run(StatusHariTanggalSelesaiBackfillRequest $request)
    {
        $requestId = (string) ($request->header('X-Request-Id') ?: Str::uuid());

        try {
            $result = $this->service->run($request->validated(), $requestId);
            $message = $result['mode'] === 'DRY_RUN'
                ? 'Dry-run selesai; tidak ada data bisnis yang diubah.'
                : 'Batch backfill selesai diterapkan.';

            return ResponseHelper::success($result, $message);
        } catch (StatusHariBackfillException $exception) {
            Log::warning('STATUS_HARI_BACKFILL_REJECTED', [
                'request_id' => $requestId,
                'error_code' => $exception->errorCode,
                'status' => $exception->httpStatus,
            ]);

            return ResponseHelper::errorWithCode(
                $exception->getMessage(),
                $exception->errorCode,
                $exception->httpStatus,
                requestId: $requestId,
            );
        } catch (Throwable $exception) {
            Log::error('STATUS_HARI_BACKFILL_FAILED', [
                'request_id' => $requestId,
                'exception' => $exception,
            ]);

            return ResponseHelper::errorWithCode(
                'Backfill status hari gagal dijalankan.',
                'STATUS_HARI_BACKFILL_FAILED',
                500,
                requestId: $requestId,
            );
        }
    }
}
