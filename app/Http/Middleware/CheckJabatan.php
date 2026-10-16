<?php

namespace App\Http\Middleware;

use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckJabatan
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$jabatans): Response
    {
        if (! Auth::check()) {
            return redirect('login');
        }

        // RBAC Fase 5: request non-GET = aksi tulis (di mode LAMA sama dengan VIEW),
        // kecuali rute yang ditandai ->defaults('rbac_aksi', 'VIEW') karena POST-nya
        // hanya membaca (pratinjau, ekspor, daftar).
        $tulis = ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)
            && strtoupper((string) ($request->route()?->defaults['rbac_aksi'] ?? '')) !== 'VIEW';
        $keputusan = app(KeputusanAkses::class);

        foreach ($jabatans as $jabatan) {
            $jabatan = trim($jabatan);

            if ($jabatan === '') {
                continue;
            }

            // RBAC Fase 1: satu titik keputusan, sama dengan sidebar.
            $boleh = $tulis
                ? $keputusan->bolehTulis($request->user(), $jabatan)
                : $keputusan->boleh($request->user(), $jabatan);
            if ($boleh) {
                return $next($request);
            }
        }

        Log::warning('CheckJabatan denied request', [
            'user_id' => $request->user()->Id_Users ?? null,
            'username' => $request->user()->Username ?? null,
            'required_pages' => $jabatans,
            'path' => $request->path(),
            'route' => optional($request->route())->getName(),
        ]);

        // return redirect()->back();
        abort(403, 'Akses Ditolak.');
    }
}
