<?php

namespace App\Jobs\KPI\Monitoring\Enforcement;

use App\Services\KPI\KpiCapParameterBackfillService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job induk backfill cap per parameter: cacah sasaran (atau header yang akan
 * dipulihkan, untuk rollback), lalu satu BackfillCapParameterChunkJob per
 * potongan 300 header. Endpoint hanya mendispatch job ini sehingga membalas
 * seketika, berapa pun jumlah header produksi.
 */
class BackfillCapParameterBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public $tries = 3;

    public function __construct(protected string $batchId)
    {
        $this->onQueue((string) config('queue.names.kpi_enforcement'));
    }

    public function handle(KpiCapParameterBackfillService $svc): void
    {
        $ids = $svc->idUntukBatch($this->batchId);
        $svc->setelTotal($this->batchId, count($ids));

        foreach (array_chunk($ids, KpiCapParameterBackfillService::POTONGAN) as $indeks => $potongan) {
            BackfillCapParameterChunkJob::dispatch($this->batchId, $indeks, $potongan);
        }
    }

    public function failed(\Throwable $e): void
    {
        app(KpiCapParameterBackfillService::class)->tandaiGagal($this->batchId, 'Job induk gagal: '.$e->getMessage());
    }
}
