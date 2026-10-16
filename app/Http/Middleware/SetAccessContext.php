<?php

namespace App\Http\Middleware;

use App\Services\SYSTEM\Governance\Authorization\Shared\AccessContext;
use Closure;
use Illuminate\Http\Request;

class SetAccessContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $mode = 'user')
    {

        // dd("Setting access context to: " . $mode);
        app(AccessContext::class)->set($mode);

        return $next($request);
    }
}
