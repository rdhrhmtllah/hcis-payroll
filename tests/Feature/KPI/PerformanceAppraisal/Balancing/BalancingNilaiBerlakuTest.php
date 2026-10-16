<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Balancing;

use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingException;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingNilaiBerlaku;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingPenjaga;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanBalancing;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

class BalancingNilaiBerlakuTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanBalancing;
    use MenyiapkanLembarPenilaian;

    public function test_tanpa_sesi_final_tidak_ada_nilai_berlaku(): void
    {
        $p = $this->pesertaDihitung();
        $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$p['kompetensi'][0] => 90.0]], 'DRAFT');

        $this->assertSame([], app(PaBalancingNilaiBerlaku::class)->kompetensiBerlaku($p['participant_id']));
    }

    public function test_sesi_final_terbaru_yang_menang(): void
    {
        $p = $this->pesertaDihitung();
        [$k1] = $p['kompetensi'];
        $lama = $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$k1 => 80.0]]);
        DB::table('N_PA_Balancing_Sesi')->where('Id_PA_Balancing_Sesi', $lama)->update(['Final_At' => now()->subDay()]);
        $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$k1 => 75.0]]);
        $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$k1 => 99.0]], 'DIBATALKAN');

        $this->assertSame([$k1 => 75.0], app(PaBalancingNilaiBerlaku::class)->kompetensiBerlaku($p['participant_id']));
    }

    public function test_muat_siklus_sama_dengan_baca_satu_per_satu(): void
    {
        $a = $this->pesertaDihitung();
        $b = $this->pesertaDihitung(['cycle_id' => $a['cycle_id']]);
        $this->sesiLangsung($a['cycle_id'], [$a['participant_id'] => [$a['kompetensi'][0] => 88.0]]);

        $pembaca = app(PaBalancingNilaiBerlaku::class);
        $pembaca->muatSiklus($a['cycle_id']);

        $this->assertSame([$a['kompetensi'][0] => 88.0], $pembaca->kompetensiBerlaku($a['participant_id'], $a['cycle_id']));
        $this->assertSame([], $pembaca->kompetensiBerlaku($b['participant_id'], $a['cycle_id']));
    }

    public function test_penjaga_menolak_publish_selama_sesi_terbuka_atau_belum_dihitung(): void
    {
        $p = $this->pesertaDihitung();
        $penjaga = app(PaBalancingPenjaga::class);
        $penjaga->pastikanTuntas($p['cycle_id']);

        $sesi = $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$p['kompetensi'][0] => 90.0]], 'DRAFT');

        try {
            $penjaga->pastikanTuntas($p['cycle_id']);
            $this->fail('Sesi terbuka harus menahan publish.');
        } catch (PaBalancingException $e) {
            $this->assertSame(PaBalancingException::SESI_MASIH_TERBUKA, $e->errorCode);
        }

        DB::table('N_PA_Balancing_Sesi')->where('Id_PA_Balancing_Sesi', $sesi)->update(['Status_Sesi' => 'FINAL', 'Final_At' => now()]);

        try {
            $penjaga->pastikanTuntas($p['cycle_id']);
            $this->fail('Sesi FINAL yang belum dihitung ulang harus menahan publish.');
        } catch (PaBalancingException $e) {
            $this->assertSame(PaBalancingException::BELUM_DIHITUNG_ULANG, $e->errorCode);
        }

        DB::table('N_PA_Balancing_Sesi')->where('Id_PA_Balancing_Sesi', $sesi)->update(['Hitung_Ulang_At' => now(), 'Nomor_Hitung_Ulang' => 1]);
        $penjaga->pastikanTuntas($p['cycle_id']);
        $this->assertNull($penjaga->sesiTerbukaPeserta($p['participant_id']));
    }
}
