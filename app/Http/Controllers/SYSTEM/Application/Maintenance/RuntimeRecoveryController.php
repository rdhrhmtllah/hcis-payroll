<?php

namespace App\Http\Controllers\SYSTEM\Application\Maintenance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RuntimeRecoveryController extends Controller
{
    public function dbHealth(Request $request)
    {
        if (! $this->authorizeOpsRequest($request)) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'Unauthorized',
            ], 403);
        }

        $startedAt = microtime(true);
        $connectionName = DB::getDefaultConnection();

        try {
            DB::connection($connectionName)->getPdo();

            $latencyMs = $this->roundLatencyMs($startedAt);
            $this->logOpsEvent($request, 'db-health', 'ok', $latencyMs, ['connection' => $connectionName]);

            return response()->json([
                'status' => 'ok',
                'message' => 'Database connection is healthy.',
                'connection' => $connectionName,
                'latency_ms' => $latencyMs,
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            $latencyMs = $this->roundLatencyMs($startedAt);
            $this->logOpsEvent($request, 'db-health', 'error', $latencyMs, [
                'connection' => $connectionName,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Database connection check failed.',
                'connection' => $connectionName,
                'latency_ms' => $latencyMs,
                'error' => $e->getMessage(),
                'timestamp' => now()->toIso8601String(),
            ], 503);
        }
    }

    public function dbReconnect(Request $request)
    {
        if (! $this->authorizeOpsRequest($request)) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'Unauthorized',
            ], 403);
        }

        $startedAt = microtime(true);
        $connectionName = DB::getDefaultConnection();

        try {
            DB::purge($connectionName);
            $connection = DB::reconnect($connectionName);
            $connection->getPdo();

            $latencyMs = $this->roundLatencyMs($startedAt);
            $this->logOpsEvent($request, 'db-reconnect', 'ok', $latencyMs, ['connection' => $connectionName]);

            return response()->json([
                'status' => 'ok',
                'message' => 'Database connection has been reconnected.',
                'connection' => $connectionName,
                'latency_ms' => $latencyMs,
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            $latencyMs = $this->roundLatencyMs($startedAt);
            $this->logOpsEvent($request, 'db-reconnect', 'error', $latencyMs, [
                'connection' => $connectionName,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Database reconnect failed.',
                'connection' => $connectionName,
                'latency_ms' => $latencyMs,
                'error' => $e->getMessage(),
                'timestamp' => now()->toIso8601String(),
            ], 500);
        }
    }

    public function laravelRefresh(Request $request)
    {
        if (! $this->authorizeOpsRequest($request)) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'Unauthorized',
            ], 403);
        }

        $startedAt = microtime(true);

        try {
            Artisan::call('optimize:clear');
            $commandOutput = trim(Artisan::output());
            $latencyMs = $this->roundLatencyMs($startedAt);

            $this->logOpsEvent($request, 'laravel-refresh', 'ok', $latencyMs, [
                'command' => 'optimize:clear',
            ]);

            return response()->json([
                'status' => 'ok',
                'message' => 'Laravel runtime refresh completed.',
                'command' => 'optimize:clear',
                'output' => $commandOutput,
                'latency_ms' => $latencyMs,
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            $latencyMs = $this->roundLatencyMs($startedAt);
            $this->logOpsEvent($request, 'laravel-refresh', 'error', $latencyMs, [
                'command' => 'optimize:clear',
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Laravel runtime refresh failed.',
                'command' => 'optimize:clear',
                'latency_ms' => $latencyMs,
                'error' => $e->getMessage(),
                'timestamp' => now()->toIso8601String(),
            ], 500);
        }
    }

    private function authorizeOpsRequest(Request $request): bool
    {
        $expectedToken = (string) config('services.project_config.OPS_API_BEARER_TOKEN', '');
        $providedToken = (string) $request->bearerToken();

        $isAuthorized = $expectedToken !== '' && $providedToken !== '' && hash_equals($expectedToken, $providedToken);

        if (! $isAuthorized) {
            $this->logUnauthorizedAttempt($request);
        }

        return $isAuthorized;
    }

    private function logUnauthorizedAttempt(Request $request): void
    {
        Log::warning('Unauthorized ops runtime API attempt.', [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
        ]);
    }

    private function logOpsEvent(
        Request $request,
        string $operation,
        string $result,
        float $durationMs,
        array $context = []
    ): void {
        Log::info('Ops runtime API executed.', array_merge($context, [
            'operation' => $operation,
            'result' => $result,
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'duration_ms' => $durationMs,
        ]));
    }

    private function roundLatencyMs(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 2);
    }
}
