<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTaskOutboxDispatcher
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.project_config.OUTBOX_DISPATCHER_BEARER_TOKEN', '');
        $provided = (string) $request->bearerToken();

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'success' => false,
                'status' => 401,
                'message' => 'Unauthorized.',
            ], 401);
        }

        return $next($request);
    }
}
