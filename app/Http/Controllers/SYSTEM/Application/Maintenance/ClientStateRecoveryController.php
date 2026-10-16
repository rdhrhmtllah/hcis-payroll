<?php

namespace App\Http\Controllers\SYSTEM\Application\Maintenance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Halaman pemulihan state klien — jaring pengaman terakhir.
 *
 * Sengaja TANPA auth, TANPA Inertia, dan TANPA aset hasil build: dipakai justru
 * ketika aplikasi utamanya yang tidak bisa dipakai (orang tersangkut di balik
 * lapisan logout, bundle gagal termuat, localStorage berisi state basi). Kalau
 * halaman ini ikut bergantung pada hal-hal tersebut, ia tidak menolong siapa pun.
 *
 * Yang dibersihkan murni milik browser orang itu sendiri (localStorage,
 * sessionStorage, sisa lapisan logout) — tidak ada data server yang disentuh,
 * jadi tidak ada yang bisa disalahgunakan lewat URL ini.
 */
class ClientStateRecoveryController extends Controller
{
    public function index(Request $request)
    {
        return response()
            ->view('SYSTEM.Application.Maintenance.pulihkan', [
                'loginUrl' => route('login'),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
