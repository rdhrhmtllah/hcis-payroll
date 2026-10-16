<?php

namespace Tests\Feature\KPI\Backfill;

use App\Jobs\KPI\Monitoring\Enforcement\BackfillCapParameterBatchJob;
use App\Services\KPI\KpiCapParameterBackfillService as Svc;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Endpoint Postman backfill cap per parameter.
 *
 * APP_URL repo ini tanpa skema, sehingga url() memperlakukannya sebagai segmen
 * path dan setiap permintaan uji meleset jadi 404 — rootnya dipaksa di setUp.
 */
class KpiBackfillCapEndpointTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/v1/kpi-enforcement/backfill-cap-parameter';

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('http://localhost');
        config(['services.kpi.backfill_token' => 'token-uji']);
    }

    private function hdr(string $t = 'token-uji'): array
    {
        return ['X-Backfill-Token' => $t, 'Accept' => 'application/json'];
    }

    public function test_tanpa_atau_salah_token_ditolak(): void
    {
        $this->postJson(self::URL, [], ['Accept' => 'application/json'])->assertStatus(401);
        $this->postJson(self::URL, [], $this->hdr('salah'))->assertStatus(401);
        $this->getJson(self::URL.'/kpi-bf-apa-saja', $this->hdr('salah'))->assertStatus(401);
    }

    public function test_token_kosong_di_config_menolak_segalanya(): void
    {
        config(['services.kpi.backfill_token' => null]);
        $this->postJson(self::URL, [], $this->hdr(''))->assertStatus(401);
        $this->postJson(self::URL, [], $this->hdr('apa-saja'))->assertStatus(401);

        // Tak boleh ada nilai cadangan di config: tanpa env, token = null (gagal-tertutup).
        $isi = file_get_contents(base_path('config/services.php'));
        $this->assertDoesNotMatchRegularExpression("/env\(\s*'KPI_BACKFILL_TOKEN'\s*,/", $isi,
            'KPI_BACKFILL_TOKEN punya nilai cadangan — endpoint tak pernah gagal-tertutup.');
    }

    public function test_pratinjau_adalah_mode_baku(): void
    {
        $res = $this->postJson(self::URL, [], $this->hdr())->assertOk();
        $this->assertArrayHasKey('header_sasaran', $res->json('result'));
    }

    /**
     * Eksekusi DIKUNCI di kode sampai aturan final ditetapkan dari pratinjau
     * produksi (12 Okt 2026): aturan saat ini memotong record yang kolomnya
     * tertukar. Penguncian lewat env bisa dibuka tanpa sengaja; di kode tidak.
     */
    public function test_jalankan_dan_rollback_dikunci(): void
    {
        Bus::fake([BackfillCapParameterBatchJob::class]);

        $this->postJson(self::URL, ['mode' => 'jalankan', 'requested_by' => 'H45'], $this->hdr())->assertStatus(423);
        $this->postJson(self::URL.'/kpi-bf-apa-saja/rollback', [], $this->hdr())->assertStatus(423);
        Bus::assertNotDispatched(BackfillCapParameterBatchJob::class);
        $this->assertNull(app(Svc::class)->batchAktif(), 'Batch tercatat padahal eksekusi dikunci.');
    }

    public function test_endpoint_lama_ditarik(): void
    {
        $this->postJson('/api/v1/kpi-enforcement/backfill-cap', [], $this->hdr())->assertStatus(410);
    }
}
