<?php

namespace App\Jobs\LMS\SOP\Automation;

use App\Services\LMS\SOP\SopAssignmentRuleService;
use App\Services\LMS\SOP\SopAutomationRunService;
use App\Services\LMS\SOP\SopReminderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunSopAutomation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public int $runId, public string $type, public ?int $userId = null)
    {
        $this->onQueue(config('queue.names.penjadwalan_training'));
    }

    public function handle(
        SopAutomationRunService $runs,
        SopAssignmentRuleService $rules,
        SopReminderService $reminders,
    ): void {
        if (! $runs->claim($this->runId)) {
            return;
        }
        try {
            $result = match ($this->type) {
                'RULE_SCAN' => $rules->scanActive('MANUAL', $this->userId),
                'REMINDER' => $reminders->scan(),
                default => throw new \InvalidArgumentException('Tipe otomasi tidak didukung.'),
            };
            $runs->success($this->runId, $result);
        } catch (\Throwable $e) {
            $runs->fail($this->runId, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Dipanggil queue ketika job menyerah (timeout, kehabisan tries, worker mati
     * saat shutdown). handle() tak selalu sempat berjalan sampai catch, jadi
     * tanpa hook ini baris run bisa tertinggal RUNNING dan membekukan otomasi.
     */
    public function failed(?\Throwable $e): void
    {
        app(SopAutomationRunService::class)->fail(
            $this->runId,
            $e ? $e->getMessage() : 'Job otomasi dihentikan tanpa pesan galat.',
        );
    }
}
