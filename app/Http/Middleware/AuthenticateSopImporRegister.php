<?php

namespace App\Http\Middleware;

use App\Helpers\ResponseHelper;
use App\Services\SYSTEM\Application\Security\GerbangToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang Bearer untuk impor register dokumen SOP lewat API.
 *
 * Tanpa sesi login, jadi tanpa CSRF. Gagal-tertutup lewat GerbangToken: token
 * yang belum dikonfigurasi menolak semua permintaan, bukan meloloskannya.
 */
class AuthenticateSopImporRegister
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! GerbangToken::sah($request->bearerToken(), 'services.hcis.sop_impor_register.token')) {
            $requestId = (string) ($request->header('X-Request-Id') ?: Str::uuid());
            Log::warning('SOP_IMPOR_REGISTER_UNAUTHORIZED', [
                'request_id' => $requestId,
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return ResponseHelper::errorWithCode(
                'Unauthorized.',
                'SOP_IMPOR_REGISTER_UNAUTHORIZED',
                401,
                requestId: $requestId,
            );
        }

        return $next($request);
    }
}
