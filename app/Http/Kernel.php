<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        // Jaring pengaman pencatatan 5xx. Paling luar supaya ia melihat SETIAP
        // tanggapan, termasuk yang dibuat middleware lain di bawahnya.
        \App\Http\Middleware\CatatGalatServer::class,
        // \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\RequestDiagnostics::class,
            \App\Http\Middleware\SetDatabaseLockTimeout::class,
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\HandleInertiaRequests::class, // Tambahkan ini
            \App\Http\Middleware\NoCache::class,
            \App\Http\Middleware\CheckMaintenanceMode::class,
            \App\Http\Middleware\TrackUserActivity::class,
            // Selama terkunci, aplikasinya adalah formulir. Dipasang PALING AKHIR
            // supaya sesi, autentikasi, dan rute sudah terpasang saat ia menilai.
            \App\Http\Middleware\ArahkanKeFormulirPengumpulanData::class,
            // \App\Http\Middleware\ValidateRequestOrigin::class,
        ],

        'api' => [
            // \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \App\Http\Middleware\RequestDiagnostics::class,
            \App\Http\Middleware\SetDatabaseLockTimeout::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\TrackUserActivity::class,
        ],
    ];

    /**
     * The application's middleware aliases.
     *
     * Aliases may be used instead of class names to conveniently assign middleware to routes and groups.
     *
     * @var array<string, class-string|string>
     */
    protected $middlewareAliases = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'precognitive' => \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
        'signed' => \App\Http\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'check.jabatan' => \App\Http\Middleware\CheckJabatan::class,
        'visitor.tipe-tamu' => \App\Http\Middleware\VISITOR\Guest\Visit\TetapkanTipeTamu::class,
        'system.admin' => \App\Http\Middleware\EnsureSystemAdmin::class,
        'akses.peran' => \App\Http\Middleware\EnsureAdminAkses::class,
        'akses' => \App\Http\Middleware\EnsureAksesHalaman::class,
        /* Gerbang ATAU: System Admin, atau pemegang akses halaman yang disebut.
           Menumpuk dua middleware menghasilkan DAN, bukan ATAU. */
        'system.admin.atau' => \App\Http\Middleware\EnsureSystemAdminOrPage::class,
        'outbox.dispatcher.auth' => \App\Http\Middleware\AuthenticateTaskOutboxDispatcher::class,
        'outbox.handler.auth' => \App\Http\Middleware\AuthenticateTaskOutboxHandler::class,
        'status-hari.backfill.auth' => \App\Http\Middleware\AuthenticateStatusHariBackfill::class,
        'sop.impor-register.auth' => \App\Http\Middleware\AuthenticateSopImporRegister::class,
        'audit.admin.transaksi' => \App\Http\Middleware\AuditAdminTransaction::class,
        'authorize.resource' => \App\Http\Middleware\AuthorizeResourceAccess::class,
        'access.context' => \App\Http\Middleware\SetAccessContext::class,
        'lms.api.guard' => \App\Http\Middleware\LmsApiGuard::class,
        'pa.api.guard' => \App\Http\Middleware\PaApiGuard::class,
        'hris.api.guard' => \App\Http\Middleware\HrisApiGuard::class,
        'track.activity' => \App\Http\Middleware\TrackUserActivity::class,
    ];
}
