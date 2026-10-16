<?php

namespace App\Services\VISITOR\Workflow\Reporting\Export;

use App\Jobs\VISITOR\Workflow\Reporting\Export\GenerateVisitorReportExportJob;
use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use App\Services\SYSTEM\Application\Reports\ReportExportService;
use RuntimeException;

class VisitorReportExportService extends ReportExportService
{
    protected function moduleKey(): string
    {
        return 'visitor-studio';
    }

    public function dispatchJob(string $exportId): void
    {
        GenerateVisitorReportExportJob::dispatch($exportId);
    }

    public function processOrFail(string $exportId): void
    {
        $this->process($exportId);

        $job = $this->findJob($exportId);
        if (! $job) {
            throw new RuntimeException('Visitor report export job was not found.');
        }

        if ($this->shouldRetryAfterProcess($job)) {
            throw new RuntimeException('Visitor report export failed and is eligible for retry.');
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
        $limits = (array) config('exports.visitor_report_max_rows', []);

        return max(1, (int) ($limits[$format] ?? $limits['xlsx'] ?? 20000));
    }
}
