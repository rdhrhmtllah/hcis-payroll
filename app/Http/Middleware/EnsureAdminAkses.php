<?php

namespace App\Http\Middleware;

use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang layar Akses & Peran (RBAC Fase 2b): System Admin atau pemegang role
 * berjenis ADMIN. Halaman SYSTEM lain memakai `system.admin`; yang ini dibuka
 * juga untuk admin modul supaya mereka melihat akses di modulnya sendiri.
 */
class EnsureAdminAkses
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $keputusan = app(KeputusanAkses::class);
        // Konteks modul (halaman /admin/{modul}/akses-peran dan API-nya lewat ?konteks=;
        // bukan ?modul= — nama itu sudah dipakai endpoint selisih Katalog untuk id modul):
        // hanya admin modul itu (atau System Admin); data & perubahan dikunci ke modul itu.
        $kode = $request->route('modul') ?? $request->query('konteks');
        if ($kode !== null && $kode !== '') {
            $modul = $keputusan->idModul((string) $kode);
            abort_unless($user && $modul !== null && $keputusan->bolehKelolaModul($user, $modul), 403, 'Halaman ini khusus admin modul tersebut.');
            $request->attributes->set('akses_modul', $modul);
            $request->attributes->set('akses_modul_kode', strtolower((string) $kode));

            return $next($request);
        }
        abort_unless($user && $keputusan->bolehKelolaAkses($user), 403, 'Halaman ini khusus admin akses.');

        return $next($request);
    }
}
