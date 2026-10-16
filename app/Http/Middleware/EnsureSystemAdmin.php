<?php

namespace App\Http\Middleware;

use App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationScopeResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi akses hanya untuk System Admin (KPI_Users.Is_System_Admin = 'Y').
 * Berbeda dari check.jabatan (akses menu per-page); ini gate keras khusus admin
 * sistem. Pola pembacaan flag sama dengan AuthorizationScopeResolver::isSystemAdmin.
 */
class EnsureSystemAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        $flag = $user->Is_System_Admin ?? ($user->is_system_admin ?? null);

        if ($flag !== 'Y') {
            abort(403, 'Halaman ini khusus System Admin.');
        }

        return $next($request);
    }
}
