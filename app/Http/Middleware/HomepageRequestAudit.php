<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AUDIT SEMENTARA: catat durasi + jumlah query DB per request homepage/leader.
 * Aktif hanya saat config('homepage_audit.enabled'). Dipakai untuk mengukur
 * frekuensi × beban tiap endpoint (mana yang paling sering × paling berat).
 * HAPUS dari Kernel + hapus file ini setelah analisis selesai.
 */
class HomepageRequestAudit
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('homepage_audit.enabled', false)) {
            return $next($request);
        }

        $path = $request->path();
        // Hanya endpoint homepage/leader (kurangi noise).
        if (! str_contains($path, 'homepage') && ! str_contains($path, 'leader-dashboard')) {
            return $next($request);
        }

        $queryCount = 0;
        $queryMs = 0.0;
        DB::listen(function ($q) use (&$queryCount, &$queryMs) {
            $queryCount++;
            $queryMs += (float) $q->time;
        });

        $t0 = microtime(true);
        $response = $next($request);
        $totalMs = (int) round((microtime(true) - $t0) * 1000);

        Log::channel('homepageLog')->info('AUDIT REQ', [
            'path' => $path,
            'method' => $request->method(),
            'total_ms' => $totalMs,
            'db_query_count' => $queryCount,
            'db_query_ms' => (int) round($queryMs),
        ]);

        return $response;
    }
}
