<?php

namespace App\Console\Commands\LMS\SOP\Operations;

use App\Services\LMS\SOP\SopAssignmentRuleService;
use App\Services\LMS\SOP\SopAutomationRunService;
use Illuminate\Console\Command;

class SopRuleScan extends Command
{
    protected $signature = 'sop:rule-scan';

    protected $description = 'Evaluasi ulang seluruh aturan penugasan SOP aktif';

    public function handle(SopAssignmentRuleService $service, SopAutomationRunService $runs): int
    {
        // Sapu run yang menggantung sebelum memeriksa hasActive(). Tanpa ini satu
        // worker yang mati di tengah jalan membuat scan harian dilewati selamanya.
        if ($disapu = $runs->reapStale()) {
            $this->warn("{$disapu} run otomasi menggantung ditandai FAILED.");
        }

        if ($runs->hasActive('RULE_SCAN')) {
            $this->warn('Rule scan dilewati karena run lain masih aktif.');

            return self::SUCCESS;
        }

        $runId = $runs->queue('RULE_SCAN', 'SCHEDULED');
        if (! $runs->claim($runId)) {
            return self::SUCCESS;
        }

        try {
            $result = $service->scanActive();
            $runs->success($runId, $result);
            $this->info("Rule scan: {$result['queued']} aturan masuk antrean.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $runs->fail($runId, $e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
