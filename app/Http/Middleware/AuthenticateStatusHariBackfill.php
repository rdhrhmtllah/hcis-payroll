<?php

namespace App\Http\Middleware;

use App\Helpers\ResponseHelper;
use App\Services\SYSTEM\Application\Security\GerbangToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateStatusHariBackfill
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! GerbangToken::sah($request->bearerToken(), 'services.hcis.status_hari_backfill.token')) {
            $requestId = (string) ($request->header('X-Request-Id') ?: Str::uuid());
            Log::warning('STATUS_HARI_BACKFILL_UNAUTHORIZED', [
                'request_id' => $requestId,
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return ResponseHelper::errorWithCode(
                'Unauthorized.',
                'STATUS_HARI_BACKFILL_UNAUTHORIZED',
                401,
                requestId: $requestId,
            );
        }

        return $next($request);
    }
}
