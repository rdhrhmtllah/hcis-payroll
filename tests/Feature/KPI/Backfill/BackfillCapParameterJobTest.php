<?php

namespace Tests\Feature\KPI\Backfill;

use App\Jobs\KPI\Monitoring\Enforcement\BackfillCapParameterBatchJob;
use App\Jobs\KPI\Monitoring\Enforcement\BackfillCapParameterChunkJob;
use App\Services\KPI\KpiCapParameterBackfillService as Svc;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackfillCapParameterJobTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        if (! app(Svc::class)->tabelCadanganAda()) {
            $this->markTestSkipped('DDL-2026-167 belum dijalankan.');
        }
    }

    public function test_induk_memecah_sasaran_per_300_dan_menyetel_total(): void
    {
        $svc = app(Svc::class);
        Bus::fake([BackfillCapParameterChunkJob::class]);
        $batch = $svc->mulai('jalankan', 'UJI-BF');
        $n = count($svc->idSasaran());
        if ($n === 0) {
            $this->markTestSkipped('Tak ada header sasaran di DB dev.');
        }

        (new BackfillCapParameterBatchJob($batch))->handle($svc);

        Bus::assertDispatchedTimes(BackfillCapParameterChunkJob::class, (int) ceil($n / Svc::POTONGAN));
        $this->assertSame($n, (int) DB::table('N_HRIS_Async_Tasks')->where('Export_Id', $batch)->value('Total_Records'));
    }

    public function test_tanpa_sasaran_batch_langsung_selesai(): void
    {
        $svc = $this->partialMock(Svc::class, fn ($m) => $m->shouldReceive('idUntukBatch')->andReturn([]));
        Bus::fake([BackfillCapParameterChunkJob::class]);
        $batch = $svc->mulai('jalankan', 'UJI-BF');

        (new BackfillCapParameterBatchJob($batch))->handle($svc);

        Bus::assertNotDispatched(BackfillCapParameterChunkJob::class);
        $this->assertSame('ready', DB::table('N_HRIS_Async_Tasks')->where('Export_Id', $batch)->value('Status'));
    }

    public function test_job_potongan_memilih_jalur_rollback_dari_payload(): void
    {
        // Job yang sama melayani dua mode; salah membaca payload berarti rollback
        // menghitung ulang (atau sebaliknya) tanpa galat apa pun.
        $svc = $this->partialMock(Svc::class, function ($m) {
            $m->shouldReceive('pulihkanPotongan')->once()->with(\Mockery::type('string'), 'kpi-bf-asal', 3, [7, 8])->andReturn(2);
            $m->shouldNotReceive('prosesPotongan');
        });
        $rb = $svc->mulai('rollback', 'UJI-BF', 'kpi-bf-asal');

        (new BackfillCapParameterChunkJob($rb, 3, [7, 8]))->handle($svc);
    }

    public function test_kegagalan_job_potongan_menandai_batch_gagal(): void
    {
        $svc = app(Svc::class);
        $batch = $svc->mulai('jalankan', 'UJI-BF');

        (new BackfillCapParameterChunkJob($batch, 0, [1]))->failed(new \RuntimeException('boom'));

        $t = DB::table('N_HRIS_Async_Tasks')->where('Export_Id', $batch)->first(['Status', 'Error_Message']);
        $this->assertSame('failed', $t->Status);
        $this->assertStringContainsString('boom', (string) $t->Error_Message);
    }
}
