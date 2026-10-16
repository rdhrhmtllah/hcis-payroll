<?php

namespace App\Jobs\KPI\PerformanceAppraisal;

use App\Jobs\KPI\PerformanceAppraisal\Concerns\BertindakSebagaiPemulai;
use App\Services\KPI\PerformanceAppraisal\PaHcLearnScheduleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Menghapus jadwal HC Learn milik penugasan yang baru saja dibatalkan.
 *
 * DI ANTREAN, bukan di dalam pembatalannya: pembatalan adalah keputusan kita dan harus sah
 * seketika — tidak menunggu HC Learn yang lambat, dan tidak ikut gagal ketika HC Learn
 * menolak. Yang gagal dihapus tetap tercatat di baris integrasinya dan muncul di Langkah 5
 * untuk dicoba lagi.
 */
class HapusJadwalHcLearnPaJob implements ShouldQueue
{
    use BertindakSebagaiPemulai, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public $tries = 1;

    /** @param  list<int>|null  $assignmentIds  null = seluruh siklus */
    public function __construct(
        public ?array $assignmentIds = null,
        public ?int $cycleId = null,
        public ?int $olehUserId = null,
    ) {}

    public function handle(PaHcLearnScheduleService $jadwal): void
    {
        $this->bertindakSebagai($this->olehUserId);

        try {
            $jadwal->hapusJadwalDibatalkan($this->assignmentIds, $this->cycleId, $this->olehUserId);
        } catch (Throwable $e) {
            Log::error('PA: hapus jadwal HC Learn gagal', [
                'penugasan' => $this->assignmentIds,
                'cycle_id' => $this->cycleId,
                'galat' => $e->getMessage(),
            ]);
        }
    }
}
