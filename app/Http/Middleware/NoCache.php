<?php

namespace App\Http\Middleware;

use Closure;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NoCache
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // Skip setting headers for file downloads
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return $response;
        }

        return $response->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
