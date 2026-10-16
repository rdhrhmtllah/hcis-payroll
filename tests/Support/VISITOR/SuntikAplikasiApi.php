<?php

namespace Tests\Support\VISITOR;

use Closure;
use Illuminate\Http\Request;

/**
 * Middleware uji yang meniru satu-satunya hal yang dilakukan HrisApiGuard bagi
 * controller: menyuntikkan `hris_api_app_id` ke atribut request.
 *
 * Ada dua alasan kelas ini perlu ada, bukan sekadar closure:
 *
 *  1. `pushMiddlewareToGroup()` menuntut NAMA KELAS. Closure yang didorong ke
 *     grup membuat MiddlewareNameResolver melempar "Illegal offset type in
 *     isset or empty" — galat yang tidak menyebut closure sama sekali.
 *  2. `$this->app['request']` bukan objek request yang sama dengan yang dipakai
 *     `postJson()`, jadi menyetel atribut di sana tidak pernah sampai ke
 *     controller.
 *
 * Jalur ini HANYA hidup di tes. Jalan pintas serupa di kode produksi — misalnya
 * membaca id aplikasi dari header saat environment testing — adalah pintu
 * belakang yang ikut terbawa rilis.
 */
class SuntikAplikasiApi
{
    /** Aplikasi mana yang seolah-olah menandatangani permintaan berikutnya. */
    public static ?int $idAplikasi = null;

    public function handle(Request $request, Closure $next)
    {
        if (self::$idAplikasi !== null) {
            $request->attributes->set('hris_api_app_id', self::$idAplikasi);
        }

        return $next($request);
    }

    /** Bersihkan antar tes supaya kebocoran identitas tidak menular. */
    public static function reset(): void
    {
        self::$idAplikasi = null;
    }
}
