<?php

namespace App\Http\Middleware\VISITOR\Guest\Visit;

use App\Services\VISITOR\Guest\Visit\TipeTamu;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menetapkan tipe tamu dari RUTE: `visitor.tipe-tamu:B2B`.
 *
 * Sengaja atribut permintaan, bukan route default: Laravel meneruskan route
 * default ke method controller sebagai argumen POSISI, sehingga parameter
 * tambahan diam-diam menggeser argumen method yang punya parameter opsional.
 */
class TetapkanTipeTamu
{
    public function handle(Request $request, Closure $next, string $tipe): Response
    {
        $request->attributes->set(TipeTamu::ATRIBUT, TipeTamu::sah($tipe));

        return $next($request);
    }
}
