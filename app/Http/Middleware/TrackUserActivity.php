<?php

namespace App\Http\Middleware;

use App\Services\SYSTEM\Audit\Activity\Tracker\ActivityTracker;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    public function __construct(protected ActivityTracker $tracker) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tracker->enabled()) {
            return $next($request);
        }

        $request->attributes->set('_activity_start_us', microtime(true));

        return $next($request);
    }

    /**
     * terminate() jalan SETELAH response dikirim ke user — aman untuk
     * push event dan dispatch job tanpa mempengaruhi response time.
     */
    public function terminate(Request $request, Response $response): void
    {
        try {
            if (! $this->tracker->enabled()) {
                return;
            }
            if (! Auth::check()) {
                return;
            }
            if ($this->tracker->shouldExclude($request)) {
                return;
            }

            $startUs = (float) ($request->attributes->get('_activity_start_us') ?? microtime(true));
            $duration = (int) round((microtime(true) - $startUs) * 1000);

            $method = strtoupper($request->method());
            $isMutation = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
            $isApiPath = str_starts_with(ltrim($request->path(), '/'), 'api/');

            // ── Tentukan KELAS request ───────────────────────────────
            // 1) Navigasi halaman nyata: Inertia visit penuh, atau
            //    full page load / refresh dari browser (Sec-Fetch-Mode: navigate).
            //    Partial reload Inertia (X-Inertia-Partial-Data) = data refresh → BUKAN navigasi.
            $isInertiaVisit = $request->header('X-Inertia') === 'true'
                && ! $request->headers->has('X-Inertia-Partial-Data');

            $secFetchMode = strtolower((string) $request->header('Sec-Fetch-Mode', ''));
            $secFetchDest = strtolower((string) $request->header('Sec-Fetch-Dest', ''));

            if ($secFetchMode !== '' || $secFetchDest !== '') {
                // Browser modern → andalkan Sec-Fetch-* (paling akurat)
                $isNavigation = $secFetchMode === 'navigate' || $secFetchDest === 'document';
            } else {
                // Fallback browser lama
                $isNavigation = $method === 'GET'
                    && ! $request->ajax()
                    && $request->acceptsHtml()
                    && ! $request->expectsJson();
            }

            $isPageView = $method === 'GET' && ($isInertiaVisit || $isNavigation);

            // ── Putuskan apakah di-track + tipe event ────────────────
            $trackPageNav = (bool) config('activity-tracker.track.page_navigations', true);
            $trackMutations = (bool) config('activity-tracker.track.mutations', true);
            $trackBgGet = (bool) config('activity-tracker.track.background_get', false);

            if ($isMutation) {
                if (! $trackMutations) {
                    return;
                }
                // Aksi user (submit, approve, mark-read, hapus, dll.)
                $eventType = $isApiPath ? 'api_call' : 'action';
            } elseif ($isPageView) {
                if (! $trackPageNav) {
                    return;
                }
                $eventType = 'pageview';
            } else {
                // GET XHR / data-fetch / polling otomatis → noise.
                if (! $trackBgGet) {
                    return;
                }
                $eventType = 'api_call';
            }

            // sampling
            $rate = $eventType === 'pageview'
                ? (float) config('activity-tracker.sampling.pageview_rate', 1.0)
                : (float) config('activity-tracker.sampling.api_call_rate', 1.0);
            if ($rate < 1.0 && mt_rand(0, 999) / 1000 >= $rate) {
                return;
            }

            // hash payload (BUKAN payload mentahnya)
            $payloadHash = null;
            if ($isMutation) {
                $body = $request->all();
                if (! empty($body)) {
                    $payloadHash = $this->tracker->getSanitizer()->hash($body);
                }
            }

            $this->tracker->pushEvent($request, [
                'event_type' => $eventType,
                'http_status' => $response->getStatusCode(),
                'duration_ms' => $duration,
                'payload_hash' => $payloadHash,
            ]);

            $this->tracker->flush();
        } catch (\Throwable $e) {
            // never block — error logging silent
            \Illuminate\Support\Facades\Log::debug('TrackUserActivity terminate failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
