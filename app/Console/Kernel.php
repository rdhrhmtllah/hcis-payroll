<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // FIX: Menghindari Schema::hasTable() yang menghasilkan query berat ke sys.tables
        // dan sering menyebabkan Login Timeout di Cloud Run (cold start).
        // Langsung query tabel, jika belum ada akan ditangkap oleh catch.
        try {
            $schedules = DB::table('N_HRIS_Scheduler_Configs')
                ->where('Flag_Aktif', 'Y')
                ->get();

            foreach ($schedules as $config) {
                $schedule->command($config->Command)
                    ->cron($config->Cron_Expression)
                    ->timezone($config->Timezone ?? 'Asia/Jakarta')
                    // Cegah job menumpuk: bila run sebelumnya masih jalan (atau ke-kill
                    // di ~300s lalu trigger di-retry pada menit cron yg sama), invokasi
                    // baru di-skip. Expiry 15 mnt = auto-recover bila proses mati tanpa
                    // melepas lock; aman karena run riil ke-cap ~300s dan job harian/
                    // hourly punya jeda jauh > 15 mnt ke run berikutnya (tak ada run sah
                    // yang hilang).
                    ->withoutOverlapping(15)
                    // Cegah eksekusi dobel saat >1 instance Cloud Run menerima trigger
                    // schedule:run bersamaan. Lock cache store = database (shared lintas
                    // instance) sehingga guard ini valid.
                    ->onOneServer()
                    ->appendOutputTo(storage_path('logs/scheduler.log'));
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Scheduler: Gagal load config dari DB.', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
