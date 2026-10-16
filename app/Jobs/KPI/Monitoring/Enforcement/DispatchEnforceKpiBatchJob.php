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
 * DispatchEnforceKpiBatchJob
 *
 * Job induk (parent dispatcher) untuk eksekusi auto-cap KPI massal.
 *
 * Menerima daftar id_users dari batch enforcement, lalu memecah dan
 * mendispatch EnforceKpiCapJob per karyawan di background. Dengan adanya job
 * induk ini, web controller hanya mendispatch 1 job dan segera merespons 202
 * ke browser tanpa risiko HTTP timeout (Cloud Run / gateway) berapa pun
 * jumlah karyawan yang dipilih.
 */
class DispatchEnforceKpiBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public $tries = 3;

    /**
     * @param  int[]  $idUsers
     */
    public function __construct(
        protected string $batchId,
        protected array $idUsers,
        protected string $periode,
        protected ?int $idPenjadwalan = null,
    ) {
        $this->onQueue((string) config('queue.names.kpi_enforcement'));
    }

    public function handle(KpiEnforcementService $service): void
    {
        if ($service->batchDibatalkan($this->batchId)) {
            return;
        }

        $service->markProcessing($this->batchId);

        foreach ($this->idUsers as $idUser) {
            EnforceKpiCapJob::dispatch(
                $this->batchId,
                (int) $idUser,
                $this->periode,
                $this->idPenjadwalan,
            );
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('DispatchEnforceKpiBatchJob gagal permanen: '.$e->getMessage(), [
            'batch_id' => $this->batchId,
            'total_users' => count($this->idUsers),
            'periode' => $this->periode,
        ]);

        app(KpiEnforcementService::class)->markFailed($this->batchId);
    }
}
