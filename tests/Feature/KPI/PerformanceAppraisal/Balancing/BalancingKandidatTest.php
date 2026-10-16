<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Balancing;

use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingKandidatService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanBalancing;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

class BalancingKandidatTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanBalancing;
    use MenyiapkanLembarPenilaian;

    public function test_alasan_tidak_layak_disebut_per_orang(): void
    {
        $siap = $this->pesertaDihitung();
        $c = $siap['cycle_id'];
        $tertahan = $this->pesertaDihitung(['cycle_id' => $c, 'status_hasil' => 'TERTAHAN']);
        $terbit = $this->pesertaDihitung(['cycle_id' => $c, 'status_hasil' => 'TERBIT']);
        $leader = $this->pesertaDihitung(['cycle_id' => $c]);
        DB::table('N_PA_Result')->where('Id_PA_Result', $leader['result_id'])->update(['Published_Leader_At' => now()]);
        $dibahas = $this->pesertaDihitung(['cycle_id' => $c]);
        $this->sesiLangsung($c, [$dibahas['participant_id'] => [$dibahas['kompetensi'][0] => 80.0]], 'DRAFT');

        $daftar = collect(app(PaBalancingKandidatService::class)->daftar($c))->keyBy('participant_id');

        $this->assertTrue($daftar[$siap['participant_id']]['layak']);
        $this->assertStringContainsString('belum DIHITUNG', $daftar[$tertahan['participant_id']]['alasan']);
        $this->assertStringContainsString('sudah terbit', $daftar[$terbit['participant_id']]['alasan']);
        $this->assertStringContainsString('dirilis ke leader', $daftar[$leader['participant_id']]['alasan']);
        $this->assertStringContainsString('Sedang dibahas di sesi', $daftar[$dibahas['participant_id']]['alasan']);

        $tolak = app(PaBalancingKandidatService::class)->alasanTidakLayak($c, [$siap['participant_id'], $tertahan['participant_id'], 999999999]);
        $this->assertSame([$tertahan['participant_id'], 999999999], array_column($tolak, 'participant_id'));
    }

    public function test_saringan_jumlah_sesi_dan_grade(): void
    {
        $a = $this->pesertaDihitung(['grade' => 'A']);
        $b = $this->pesertaDihitung(['cycle_id' => $a['cycle_id'], 'grade' => 'B']);
        $this->sesiLangsung($a['cycle_id'], [$a['participant_id'] => [$a['kompetensi'][0] => 80.0]]);

        $layanan = app(PaBalancingKandidatService::class);

        $this->assertSame([$a['participant_id']], array_column($layanan->daftar($a['cycle_id'], ['jumlah_sesi' => 1]), 'participant_id'));
        $this->assertSame([$b['participant_id']], array_column($layanan->daftar($a['cycle_id'], ['grade' => ['B']]), 'participant_id'));
    }
}
