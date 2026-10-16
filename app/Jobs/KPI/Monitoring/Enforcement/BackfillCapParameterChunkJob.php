<?php

namespace App\Jobs\KPI\Monitoring\Enforcement;

use App\Services\KPI\KpiCapParameterBackfillService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Proses (atau pulihkan) satu potongan header backfill cap per parameter.
 * Kiriman ulang Cloud Tasks aman: service mengklaim potongan sekali per batch.
 */
class BackfillCapParameterChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    public $tries = 3;

    public function __construct(protected string $batchId, protected int $indeks, protected array $ids)
    {
        $this->onQueue((string) config('queue.names.kpi_enforcement'));
    }

    public function handle(KpiCapParameterBackfillService $svc): void
    {
        $payload = $svc->payload($this->batchId);

        if (($payload['mode'] ?? null) === 'rollback') {
            $svc->pulihkanPotongan($this->batchId, (string) $payload['sumber'], $this->indeks, $this->ids);

            return;
        }

        $svc->prosesPotongan($this->batchId, $this->indeks, $this->ids);
    }

    public function failed(\Throwable $e): void
    {
        app(KpiCapParameterBackfillService::class)->tandaiGagal(
            $this->batchId, "Potongan {$this->indeks} gagal: ".$e->getMessage(),
        );
    }
}
