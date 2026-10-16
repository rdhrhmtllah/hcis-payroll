<?php

namespace App\Console\Commands\SYSTEM\Audit\Activity;

use App\Jobs\SYSTEM\Audit\Activity\ExpireIdleSessionsJob;
use Illuminate\Console\Command;

class ExpireIdleSessions extends Command
{
    protected $signature = 'activity:expire-idle';

    protected $description = 'Tutup sesi N_HRIS_Activity_Session yang idle melebihi konfigurasi.';

    public function handle(): int
    {
        // Jalankan langsung (bukan via queue) — operasi cepat & idempotent
        (new ExpireIdleSessionsJob)->handle();

        return self::SUCCESS;
    }
}
