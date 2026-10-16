<?php

namespace App\Http\Middleware;

use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `akses:halaman,aksi` — gerbang aksi spesifik (RBAC Fase 5, spec §5), mis.
 * `akses:menuSopDokumenPage,DELETE`. Di mode LAMA aksi tak dibedakan (sama
 * dengan check.jabatan); di BARU dibaca dari matriks hak peran.
 */
class EnsureAksesHalaman
{
    public function handle(Request $request, Closure $next, string $halaman, string $aksi = 'VIEW'): Response
    {
        $user = $request->user();
        abort_unless($user && app(KeputusanAkses::class)->boleh($user, trim($halaman), strtoupper(trim($aksi))), 403, 'Akses Ditolak.');

        return $next($request);
    }
}
