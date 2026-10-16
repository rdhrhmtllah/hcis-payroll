<?php

namespace App\Console\Commands\ESS\Attendance\Calendar;

use App\Services\ESS\Attendance\Calendar\Operational\KalenderOperasionalService;
use Illuminate\Console\Command;

/**
 * Membuang cache kalender operasional setelah master hari libur atau cuti
 * bersama diubah, tanpa menunggu TTL habis.
 *
 * Cache tidak dibersihkan otomatis dari mutasi admin cuti bersama: itu
 * menyentuh alur admin yang sedang hidup dan berada di luar cakupan fitur ini.
 */
class FlushKalenderOperasionalCommand extends Command
{
    protected $signature = 'kalender:flush';

    protected $description = 'Membuang cache kalender operasional (hari libur & cuti bersama)';

    public function handle(): int
    {
        $versi = KalenderOperasionalService::bersihkanCache();

        $this->info("Cache kalender operasional dibuang. Versi cache sekarang: {$versi}.");

        return self::SUCCESS;
    }
}
