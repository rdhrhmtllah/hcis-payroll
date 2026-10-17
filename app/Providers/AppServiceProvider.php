<?php

namespace App\Providers;

use App\Services\SYSTEM\Audit\Activity\Tracker\ActivityTracker;
use App\Services\SYSTEM\Audit\Activity\Tracker\IdentityResolver;
use App\Services\SYSTEM\Audit\Activity\Tracker\IpMasker;
use App\Services\SYSTEM\Audit\Activity\Tracker\PayloadSanitizer;
use App\Services\SYSTEM\Audit\Activity\Tracker\RouteMapResolver;
use App\Services\SYSTEM\Audit\Activity\Tracker\UserAgentParser;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalRequestService;
use App\Services\SYSTEM\TaskProcessing\CloudTaskGateway;
use App\Services\SYSTEM\TaskProcessing\GoogleCloudTaskGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CloudTaskGateway::class, GoogleCloudTaskGateway::class);

        /*
         * Parameter string ASCII dikirim sebagai varchar, bukan nvarchar.
         *
         * pdo_sqlsrv membungkus setiap string jadi `nvarchar(4000)`, dan basis
         * data ini punya 4.241 kolom varchar/char. Ketidakcocokan itu memaksa
         * SQL Server meng-CONVERT KOLOMNYA, dan kolom yang dibungkus fungsi tak
         * bisa di-seek: 6.655 dari 8.995 rencana ter-cache memuat
         * CONVERT_IMPLICIT.
         *
         * Diselesaikan pada satu titik — Connection::bindValues() — bukan pada
         * ribuan kueri. Rinciannya di SqlServerKoneksiAnsi.
         *
         * DEFAULT MATI. Ini mengubah cara SETIAP parameter sampai ke SQL
         * Server; ia menyala hanya di lingkungan yang sudah membuktikannya
         * dengan suite penuh.
         */
        if (env('DB_SQLSRV_ANSI_BIND', false)) {
            \Illuminate\Database\Connection::resolverFor(
                'sqlsrv',
                fn ($pdo, $database, $prefix, $config) => new \App\Database\SqlServerKoneksiAnsi($pdo, $database, $prefix, $config),
            );
        }

        /*
         * scoped, BUKAN singleton.
         *
         * SopPaketVersionService memoisasi "paket ini sudah punya rilis resmi?"
         * dan snapshot rilisnya, karena SopSequenceGate menanyakannya sekali per
         * item: terukur 2.830 kali dalam SATU uji, 155 detik. Memo itu hanya
         * berguna kalau pemanggilnya berbagi instance.
         *
         * Tapi berbagi selamanya berbahaya. singleton bertahan lintas job di
         * worker antrean, dan memonya akan menjawab dengan keadaan yang sudah
         * berubah beberapa job lalu -- jebakan yang sudah pernah menggigit di
         * proyek ini. `scoped` dibuang di antara permintaan dan di antara job,
         * jadi ia berbagi persis selama satu satuan kerja dan tidak lebih.
         */
        $this->app->singleton(\App\Services\SYSTEM\TaskProcessing\TaskHandlerRegistry::class, function ($app) {
            $registry = new \App\Services\SYSTEM\TaskProcessing\TaskHandlerRegistry;
            $registry->register('SYSTEM_OUTBOX_HEALTHCHECK', new \App\Services\SYSTEM\TaskProcessing\SystemOutboxHealthcheckHandler);
            $registry->register('WHATSAPP_SEND', $app->make(\App\Services\SYSTEM\TaskProcessing\WhatsappOutboxHandler::class));

            return $registry;
        });

        // Register ApprovalRequestService as singleton
        $this->app->singleton(ApprovalRequestService::class, function ($app) {
            return new ApprovalRequestService;
        });

        // ===== Activity Tracker bindings =====
        $this->app->singleton(IdentityResolver::class);
        $this->app->singleton(RouteMapResolver::class);
        $this->app->singleton(UserAgentParser::class);
        $this->app->singleton(IpMasker::class);

        $this->app->singleton(PayloadSanitizer::class, function ($app) {
            return new PayloadSanitizer((array) config('activity-tracker.pii_redact', []));
        });

        $this->app->singleton(ActivityTracker::class, function ($app) {
            return new ActivityTracker(
                $app->make(IdentityResolver::class),
                $app->make(RouteMapResolver::class),
                $app->make(UserAgentParser::class),
                $app->make(PayloadSanitizer::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Guard anti-regresi (NON-produksi): peringatkan bila ada panggilan HTTP
        // keluar via Http:: saat transaksi DB masih terbuka. HTTP di dalam transaksi
        // menahan lock selama round-trip jaringan → koneksi menumpuk → saturasi pool
        // → "Login timeout expired". WA sudah dipindah ke job; guard ini jaring untuk
        // API lain / regresi baru (mis. developer menaruh Http::post di dalam DB::transaction).
        if (! $this->app->isProduction()) {
            Http::globalRequestMiddleware(function ($request) {
                try {
                    if (DB::transactionLevel() > 0) {
                        Log::warning('GUARD: panggilan HTTP di dalam transaksi DB (menahan lock).', [
                            'uri' => (string) $request->getUri(),
                            'method' => $request->getMethod(),
                            'tx_level' => DB::transactionLevel(),
                        ]);
                    }
                } catch (\Throwable $e) {
                    // Jangan pernah gagalkan request karena guard.
                }

                return $request;
            });
        }
    }
}
