<?php

namespace App\Jobs\KPI\Monitoring\Enforcement;

use App\Services\KPI\KpiEnforcementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * EnforceKpiCapJob
 *
 * Eksekusi auto-cap KPI untuk SATU karyawan pada periode tertentu, dijalankan di
 * background via Cloud Tasks. Achievement diset 0, status langsung Approved
 * (bypass approval), skor akhir melewati cap min/max. Progress batch dilacak di
 * N_HRIS_KPI_Execution_Logs (sumber kebenaran polling frontend).
 *
 * Satu job = satu karyawan agar retry granular & progress halus. Counter batch
 * di-increment idempoten-secukupnya oleh service (skip bila performance aktif
 * sudah ada).
 */
class EnforceKpiCapJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    // Retry beberapa kali agar error sementara (DB timeout) bisa pulih. Cloud Tasks
    // menjadwalkan ulang selama job melempar exception. enforceForEmployee() menelan
    // error per-karyawan & mencatat FAILED, jadi job hanya throw untuk error fatal.
    public $tries = 3;

    public function __construct(
        protected string $batchId,
        protected int $idUser,
        protected string $periode,
        protected ?int $idPenjadwalan = null,
    ) {
        $this->onQueue((string) config('queue.names.kpi_enforcement'));
    }

    public function handle(KpiEnforcementService $service): void
    {
        // Batch dibatalkan: task Cloud Tasks yang masih antre tetap dikirim
        // (tak bisa dihapus dari aplikasi), jadi di sinilah ia harus berhenti.
        // Selesai dengan sukses supaya Cloud Tasks tidak mencobanya lagi.
        if ($service->batchDibatalkan($this->batchId)) {
            return;
        }

        // Tandai batch processing (no-op bila sudah processing).
        $service->markProcessing($this->batchId);

        // Service mencatat hasil (SUCCESS/FAILED) + increment counter batch sendiri,
        // sehingga satu karyawan gagal tidak menggagalkan seluruh batch.
        $service->enforceForEmployee($this->batchId, $this->idUser, $this->periode, $this->idPenjadwalan);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('EnforceKpiCapJob gagal permanen (semua retry habis): '.$e->getMessage(), [
            'batch_id' => $this->batchId,
            'id_user' => $this->idUser,
            'periode' => $this->periode,
            'exception' => $e,
        ]);
    }
}
