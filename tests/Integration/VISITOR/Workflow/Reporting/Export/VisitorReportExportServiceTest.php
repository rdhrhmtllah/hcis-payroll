<?php

namespace Tests\Integration\VISITOR\Workflow\Reporting\Export;

use App\Jobs\VISITOR\Workflow\Reporting\Export\GenerateVisitorReportExportJob;
use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use App\Services\VISITOR\Workflow\Reporting\Export\VisitorReportExportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VisitorReportExportServiceTest extends TestCase
{
    public function test_dispatch_uses_the_visitor_export_job(): void
    {
        Queue::fake();

        app(VisitorReportExportService::class)->dispatchJob('visitor-export-test');

        Queue::assertPushed(GenerateVisitorReportExportJob::class, 1);
    }

    /** @dataProvider formatLimitProvider */
    public function test_visitor_export_has_memory_safe_limit_per_format(string $format, int $expected): void
    {
        config(['exports.visitor_report_max_rows' => [
            'csv' => 50000,
            'xlsx' => 20000,
            'pdf' => 200,
        ]]);

        $service = app(VisitorReportExportService::class);
        $method = new \ReflectionMethod($service, 'maxRowsFor');
        $method->setAccessible(true);

        $this->assertSame($expected, $method->invoke($service, (object) ['Format' => $format]));
    }

    public function test_job_retries_transient_export_failure(): void
    {
        $job = new GenerateVisitorReportExportJob('visitor-export-test');

        // Sama seperti PublishVisitorNotificationsJob: di bawah phpunit
        // queue.default = 'sync' dan job mengikutinya supaya benar-benar jalan;
        // yang dijaga adalah sisi sebaliknya.
        $this->assertSame('sync', $job->connection, 'Di lingkungan uji job harus ikut sync agar benar-benar jalan.');

        config(['queue.default' => 'redis']);
        $jobProduksi = new GenerateVisitorReportExportJob('visitor-export-test');
        $this->assertSame('cloudtasks', $jobProduksi->connection, 'Di luar sync, job wajib ke cloudtasks.');

        $this->assertSame('async-export', $job->queue);
        $this->assertSame(3, $job->tries);
        $this->assertSame([30, 120, 300], $job->backoff());
    }

    public function test_claim_does_not_take_over_an_active_processing_export(): void
    {
        config(['exports.processing_lease_minutes' => 10]);

        $service = app(VisitorReportExportService::class);
        $method = new \ReflectionMethod($service, 'claimForProcessing');
        $method->setAccessible(true);

        $queries = DB::connection()->pretend(
            fn () => $method->invoke($service, 'visitor-export-lease-test')
        );

        $this->assertCount(1, $queries);
        $sql = strtolower($queries[0]['query']);
        $bindings = array_map(
            fn ($binding) => is_string($binding) ? strtolower($binding) : $binding,
            $queries[0]['bindings'],
        );

        $this->assertStringContainsString('file_path', $sql);
        $this->assertStringContainsString('updated_at', $sql);
        $this->assertStringContainsString('<=', $sql);
        $this->assertContains(AbstractExportService::STATUS_QUEUED, $bindings);
        $this->assertContains(AbstractExportService::STATUS_FAILED, $bindings);
        $this->assertContains(AbstractExportService::STATUS_PROCESSING, $bindings);
    }

    /** @dataProvider failedJobRetryProvider */
    public function test_failed_export_is_retried_unless_user_aborted(bool $aborted, bool $expected): void
    {
        $service = app(VisitorReportExportService::class);
        $method = new \ReflectionMethod($service, 'shouldRetryAfterProcess');
        $method->setAccessible(true);

        $job = (object) [
            'Status' => AbstractExportService::STATUS_FAILED,
            'Payload_Json' => json_encode(['aborted' => $aborted]),
        ];

        $this->assertSame($expected, $method->invoke($service, $job));
    }

    public static function formatLimitProvider(): array
    {
        return [
            ['csv', 50000],
            ['xlsx', 20000],
            ['pdf', 200],
        ];
    }

    public static function failedJobRetryProvider(): array
    {
        return [
            'technical failure' => [false, true],
            'user cancellation' => [true, false],
        ];
    }
}
