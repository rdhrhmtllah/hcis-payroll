<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Balancing;

use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingException;
use App\Services\KPI\PerformanceAppraisal\PaResultPublishService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanBalancing;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

class BalancingPublishTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanBalancing;
    use MenyiapkanLembarPenilaian;

    public function test_semua_pintu_publish_menunggu_balancing_tuntas(): void
    {
        $p = $this->pesertaDihitung();
        $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$p['kompetensi'][0] => 90.0]], 'DRAFT');
        $layanan = app(PaResultPublishService::class);

        foreach ([
            'terbitkan' => fn () => $layanan->terbitkan($p['cycle_id'], 1),
            'terbitkanPeserta' => fn () => $layanan->terbitkanPeserta($p['participant_id'], 1),
            'rilisKeLeader' => fn () => $layanan->rilisKeLeader($p['cycle_id'], 1),
            'rilisKeLeaderPeserta' => fn () => $layanan->rilisKeLeaderPeserta($p['participant_id'], 1),
            'tutupPenerbitan' => fn () => $layanan->tutupPenerbitan($p['cycle_id'], 1),
        ] as $pintu => $f) {
            try {
                $f();
                $this->fail("{$pintu} harus menunggu sesi Balancing selesai.");
            } catch (PaBalancingException $e) {
                $this->assertSame(PaBalancingException::SESI_MASIH_TERBUKA, $e->errorCode, $pintu);
            }
        }
    }

    public function test_kalibrasi_forum_ditutup(): void
    {
        $p = $this->pesertaDihitung();
        $this->actingAs($this->penggunaHasil());

        $r = $this->postJson("/api/v1/performance-appraisal/results/{$p['result_id']}/kalibrasi", ['kode_grade' => 'A', 'alasan' => 'uji']);

        $r->assertStatus(422);
        $this->assertSame('PA_KALIBRASI_FORUM_DITUTUP', $r->json('error_code'));
    }
}
