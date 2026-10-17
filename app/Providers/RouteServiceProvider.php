<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // API cek kartu publik: per APLIKASI terdaftar (atribut dari hris.api.guard).
        RateLimiter::for('kartu-permit-publik', function (Request $request) {
            $aplikasi = $request->attributes->get('hris_api_app_id');

            return Limit::perMinute(max((int) config('services.visitor.kartu_publik_per_menit', 600), 1))
                ->by($aplikasi ? 'kartu-publik-app:'.$aplikasi : 'kartu-publik-ip:'.$request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
