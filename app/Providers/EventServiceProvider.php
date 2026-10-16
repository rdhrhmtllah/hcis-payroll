<?php

namespace App\Providers;

use App\Listeners\Activity\HandleFailedLogin as ActivityHandleFailedLogin;
use App\Listeners\Activity\HandleLogin as ActivityHandleLogin;
use App\Listeners\Activity\HandleLogout as ActivityHandleLogout;
use App\Listeners\CaptureExportCloudTaskName;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Stackkit\LaravelGoogleCloudTasksQueue\Events\TaskCreated;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        Login::class => [
            ActivityHandleLogin::class,
        ],
        Logout::class => [
            ActivityHandleLogout::class,
        ],
        Failed::class => [
            ActivityHandleFailedLogin::class,
        ],
        // Tangkap nama task Cloud Tasks saat job export di-dispatch (utk fitur batalkan).
        TaskCreated::class => [
            CaptureExportCloudTaskName::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
