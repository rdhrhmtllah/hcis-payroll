<?php

namespace App\Services\VISITOR\Workflow\Errors;

use App\Helpers\ResponseHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class VisitorApiFailure
{
    /** @param array<string, mixed> $context */
    public static function respond(
        Throwable $exception,
        string $operation,
        string $errorCode,
        string $safeMessage,
        array $context = [],
    ) {
        $requestId = (string) Str::uuid();

        Log::error('VISITOR_API_OPERATION_FAILED', array_merge($context, [
            'request_id' => $requestId,
            'module' => 'VISITOR',
            'operation' => $operation,
            'error_code' => $errorCode,
            'exception' => $exception,
        ]));

        return ResponseHelper::errorWithCode($safeMessage, $errorCode, 500, [], $requestId);
    }
}
