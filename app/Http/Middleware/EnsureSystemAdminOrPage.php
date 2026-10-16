<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lolos bila System Admin ATAU punya akses ke salah satu halaman yang disebut.
 *
 * KENAPA MIDDLEWARE SENDIRI, BUKAN MENUMPUK DUA YANG SUDAH ADA
 *
 * Middleware Laravel bersifat DAN, bukan ATAU. Menumpuk `system.admin` dan `check.jabatan`
 * menghasilkan "harus System Admin DAN punya halamannya" — kebalikan dari yang dimaksud, dan
 * kebalikan yang tidak menampakkan diri: satu-satunya System Admin yang ada memang biasanya
 * punya semua halaman, jadi layarnya tetap terbuka untuknya dan salahnya baru terlihat pada
 * orang kedua.
 *
 * KENAPA BUKAN SEKADAR MENGGANTI `system.admin` DENGAN `check.jabatan`
 *
 * `CheckJabatan` membaca gabungan role, akses eksplisit, dan menu global. Ia TIDAK
 * memberi kelonggaran apa pun kepada System Admin — bendera `Is_System_Admin` tidak
 * disinggung sama sekali di jalur itu. Jadi mengganti begitu saja akan MENCABUT akses
 * System Admin atas halaman yang selama ini memang miliknya, kecuali ia kebetulan juga
 * diberi halamannya satu per satu.
 *
 * Gerbang ini menyatukan keduanya secara eksplisit: bendera keras tetap berlaku, dan
 * pemberian akses per halaman berjalan lewat mekanisme biasa (Page Access) tanpa perlu
 * menyentuh kode lagi untuk setiap orang baru.
 */
class EnsureSystemAdminOrPage
{
    public function handle(Request $request, Closure $next, string ...$halaman): Response
    {
        if (! Auth::check()) {
            return redirect('login');
        }

        $user = Auth::user();

        /* Pola pembacaan benderanya disamakan dengan EnsureSystemAdmin: kolomnya muncul
           dengan dua ejaan tergantung dari mana modelnya dimuat. */
        $flag = $user->Is_System_Admin ?? ($user->is_system_admin ?? null);

        if ($flag === 'Y') {
            return $next($request);
        }

        foreach ($halaman as $kode) {
            $kode = trim($kode);

            if ($kode !== '' && $user->memilikiJabatan($kode)) {
                return $next($request);
            }
        }

        /* Dicatat dengan halaman yang DIBUTUHKAN, bukan hanya "ditolak". Tanpa itu, laporan
           "saya tidak bisa membuka" tidak bisa ditelusuri ke baris akses yang kurang. */
        Log::warning('EnsureSystemAdminOrPage menolak permintaan', [
            'user_id' => $user->Id_Users ?? null,
            'username' => $user->Username ?? null,
            'halaman_dibutuhkan' => $halaman,
            'path' => $request->path(),
            'route' => optional($request->route())->getName(),
        ]);

        abort(403, 'Akses Ditolak. Halaman ini untuk System Admin atau pemegang akses yang ditunjuk.');
    }
}
