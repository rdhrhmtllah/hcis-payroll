<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Balancing;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanBalancing;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

class BalancingHalamanTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanBalancing;
    use MenyiapkanLembarPenilaian;

    public function test_halaman_daftar_dan_board(): void
    {
        $this->actingAs($this->penggunaHasil());

        $this->get('/performance-appraisal/balancing')->assertOk()
            ->assertInertia(fn ($p) => $p->component('KPI/PerformanceAppraisal/Balancing/BalancingPage'));
        $this->get('/performance-appraisal/balancing/sesi/12')->assertOk()
            ->assertInertia(fn ($p) => $p->component('KPI/PerformanceAppraisal/Balancing/BalancingSesiPage')->where('sesiId', 12));
    }

    public function test_alamat_kalibrasi_lama_dialihkan_ke_balancing(): void
    {
        $this->actingAs($this->penggunaHasil());

        $this->get('/performance-appraisal/kalibrasi?cycle=8781')->assertRedirect('/performance-appraisal/balancing?cycle=8781');
    }

    public function test_kandidat_membawa_kode_perusahaan(): void
    {
        $p = $this->pesertaDihitung();
        $k = app(\App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingKandidatService::class)->daftar($p['cycle_id']);

        $this->assertNotEmpty($k[0]['kode_perusahaan']);
    }
}
