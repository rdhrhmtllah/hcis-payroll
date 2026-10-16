<?php

namespace App\Console\Commands\LMS\SOP\Operations;

use App\Services\LMS\SOP\SopAutomationRunService;
use App\Services\LMS\SOP\SopReminderService;
use Illuminate\Console\Command;

class SopReminderScan extends Command
{
    protected $signature = 'sop:reminder-scan {--hari= : Override interval reminder bawaan}';

    protected $description = 'Kirim digest in-app periodik untuk seluruh SOP yang belum selesai';

    public function handle(SopReminderService $service, SopAutomationRunService $runs): int
    {
        // Sapu run menggantung lebih dulu; lihat catatan di SopRuleScan.
        if ($disapu = $runs->reapStale()) {
            $this->warn("{$disapu} run otomasi menggantung ditandai FAILED.");
        }

        if ($runs->hasActive('REMINDER')) {
            $this->warn('Reminder scan dilewati karena run lain masih aktif.');

            return self::SUCCESS;
        }

        $runId = $runs->queue('REMINDER', 'SCHEDULED');
        if (! $runs->claim($runId)) {
            return self::SUCCESS;
        }

        try {
            $days = $this->option('hari') !== null ? max(1, (int) $this->option('hari')) : null;
            $result = $service->scan($days);
            $runs->success($runId, $result);
            $this->info("Digest SOP: {$result['sent']} notifikasi untuk ".($result['employees'] ?? 0).' karyawan.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $runs->fail($runId, $e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
