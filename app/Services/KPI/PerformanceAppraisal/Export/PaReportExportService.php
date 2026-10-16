<?php

namespace App\Services\KPI\PerformanceAppraisal\Export;

use App\Jobs\KPI\PerformanceAppraisal\Export\GeneratePaReportExportJob;
use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use App\Services\SYSTEM\Application\Reports\ReportExportService;
use RuntimeException;

class PaReportExportService extends ReportExportService
{
    protected function moduleKey(): string
    {
        return 'pa-studio';
    }

    public function dispatchJob(string $exportId): void
    {
        GeneratePaReportExportJob::dispatch($exportId);
    }

    public function processOrFail(string $exportId): void
    {
        $this->process($exportId);

        $job = $this->findJob($exportId);
        if (! $job) {
            throw new RuntimeException('PA report export job was not found.');
        }

        if ($this->shouldRetryAfterProcess($job)) {
            throw new RuntimeException('PA report export failed and is eligible for retry.');
        }
    }

    protected function shouldRetryAfterProcess(object $job): bool
    {
        $payload = $this->payload($job);

        return $job->Status === AbstractExportService::STATUS_FAILED && empty($payload['aborted']);
    }

    protected function maxRowsFor(object $job): int
    {
        $format = strtolower((string) ($job->Format ?? 'xlsx'));
        $limits = (array) config('exports.pa_report_max_rows', []);

        return max(1, (int) ($limits[$format] ?? $limits['xlsx'] ?? 20000));
    }
}
