<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedKey = config('services.project_config.log-viewer.admin_key');
        $providedKey = $request->query('key') ?? $request->header('X-Log-Key');
        $cookieKey = $request->cookie('log_admin_key');
        $hasValidRequestKey = is_string($providedKey) && hash_equals($expectedKey, $providedKey);
        $hasValidCookieKey = is_string($cookieKey) && hash_equals($expectedKey, $cookieKey);

        if ($hasValidRequestKey || $hasValidCookieKey) {
            $response = $next($request);

            if ($hasValidRequestKey) {
                // Simpan key sementara agar request API Log Viewer berikutnya tetap authorized.
                $response->headers->setCookie(
                    Cookie::create(
                        'log_admin_key',
                        $expectedKey,
                        now()->addHours(1),
                        '/',
                        null,
                        $request->isSecure(),
                        true,
                        false,
                        'lax',
                    ),
                );
            }

            return $response;
        }

        abort(403, 'Akses Ditolak.');
    }
}
