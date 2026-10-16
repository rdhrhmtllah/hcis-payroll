<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateRequestOrigin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $validOrigins = array_map('strtolower', config('services.hcis.valid_origins', []));
        $origin = strtolower($request->headers->get('origin') ?? '');
        $appEnv = strtolower(config('services.project_config.app_env', 'production'));
        // dd($appEnv, $origin, $validOrigins);
        if ($appEnv === 'production' && $origin && ! in_array($origin, $validOrigins)) {
            abort(403, 'Forbidden: Invalid request origin.');
        }

        return $next($request);
    }
}
