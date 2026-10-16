<?php

namespace App\Jobs\LMS\SOP\Automation;

use App\Services\LMS\SOP\SopAssignmentRuleService;
use App\Services\LMS\SOP\SopAturanPaketService;
use App\Services\LMS\SOP\SopAutomationRunService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ApplySopAssignmentRule implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 2;

    public function __construct(
        public int $runId,
        public int $ruleId,
        public bool $activate,
        public ?int $userId = null,
    ) {
        $this->onQueue(config('queue.names.penjadwalan_training'));
    }

    public function handle(SopAssignmentRuleService $service): void
    {
        $service->applyQueued($this->runId, $this->ruleId, $this->activate, $this->userId);
    }

    /**
     * Dipanggil queue ketika job menyerah (timeout, kehabisan tries, worker mati
     * saat shutdown). Tanpa hook ini, run tertinggal RUNNING dan aturan ikut
     * tersangkut: hasActiveForRule() true selamanya sehingga aturan itu tak
     * pernah bisa di-apply atau diaktifkan lagi.
     */
    public function failed(?\Throwable $e): void
    {
        $pesan = $e ? $e->getMessage() : 'Job penerapan aturan dihentikan tanpa pesan galat.';

        app(SopAutomationRunService::class)->fail($this->runId, $pesan);

        // Paket yang belum sempat menutup dirinya ikut ditandai. Tanpa ini
        // aturan tampak NEEDS_ATTENTION tetapi tak satu paket pun menjelaskan
        // sebabnya, dan admin tak punya cara tahu mana yang berhenti.
        app(SopAturanPaketService::class)->tandaiGantung($this->ruleId, $pesan);

        DB::table('N_SOP_Assignment_Rule')
            ->where('Id_SOP_Assignment_Rule', $this->ruleId)
            ->update([
                'Last_Apply_Status' => 'FAILED',
                'Last_Apply_Error' => mb_substr($pesan, 0, 1000),
                'Updated_At' => now(),
            ]);
    }
}
