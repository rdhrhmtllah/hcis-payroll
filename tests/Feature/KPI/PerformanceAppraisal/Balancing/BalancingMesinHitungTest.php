<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Balancing;

use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingKalkulator;
use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanBalancing;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

class BalancingMesinHitungTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanBalancing;
    use MenyiapkanLembarPenilaian;

    private function mesin(): PaResultCalculationService
    {
        return app(PaResultCalculationService::class);
    }

    public function test_tanpa_balancing_nilai_sama_dengan_mesin(): void
    {
        $p = $this->pesertaDihitung();
        $this->palsukanAturan($p['kategori_id']);

        $r = $this->mesin()->rincianKompetensi($p['participant_id']);

        // (70 + 85) / 2 = 77,5; porsi 0,5 dan 0,5.
        $this->assertSame(77.5, $r['nilai']);
        $this->assertSame(77.5, $r['nilai_mesin']);
        $this->assertEqualsWithDelta(0.5, $r['bobot_kompetensi'][$p['kompetensi'][0]], 1e-9);
        $this->assertEqualsWithDelta(0.5, $r['bobot_kompetensi'][$p['kompetensi'][1]], 1e-9);
    }

    public function test_bobot_tepat_sama_dengan_menggeser_jawaban_penilai(): void
    {
        // Sifat §6.1: menaikkan semua jawaban kompetensi k sebesar Δ menaikkan Nilai Kompetensi
        // tepat sebesar bobotₖ × Δ. Dibuktikan dengan mengubah skor mentah lalu menghitung ulang.
        $p = $this->pesertaDihitung(['skor' => [60.0, 80.0, 90.0]]);
        $this->palsukanAturan($p['kategori_id']);
        $k1 = $p['kompetensi'][0];

        $awal = $this->mesin()->rincianKompetensi($p['participant_id']);
        DB::table('N_PA_Assignment_Score')->where('PA_Competency_Id', $k1)->where('Kode_Soal_Sumber', 'UJI1')
            ->whereIn('PA_Assignment_Payload_Id', DB::table('N_PA_Assignment_Payload as ap')
                ->join('N_PA_Assignment_Integration as ai', 'ai.Id_PA_Assignment_Integration', '=', 'ap.PA_Assignment_Integration_Id')
                ->where('ai.PA_Assignment_Id', $p['assignment_id'])->pluck('ap.Id_PA_Assignment_Payload'))
            ->update(['Skor' => 72.0]);
        app()->forgetInstance(PaResultCalculationService::class);
        $sesudah = $this->mesin()->rincianKompetensi($p['participant_id']);

        $this->assertEqualsWithDelta($awal['nilai'] + $awal['bobot_kompetensi'][$k1] * 12.0, $sesudah['nilai'], 0.01);
    }

    public function test_sesi_final_menggeser_nilai_dan_baris_radar(): void
    {
        $p = $this->pesertaDihitung();
        $this->palsukanAturan($p['kategori_id']);
        [$k1, $k2] = $p['kompetensi'];
        $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$k1 => 80.0, $k2 => 85.0]]);

        $r = $this->mesin()->rincianKompetensi($p['participant_id']);

        // 77,5 + 0,5 × (80 − 70) = 82,5
        $this->assertSame(82.5, $r['nilai']);
        $this->assertSame(77.5, $r['nilai_mesin']);
        $agregat = collect($r['kompetensi'])->whereNull('kategori_id')->pluck('nilai', 'competency_id');
        $this->assertSame(80.0, (float) $agregat[$k1]);
    }

    public function test_dua_sesi_berurutan_tidak_menjumlah_dua_kali(): void
    {
        $p = $this->pesertaDihitung();
        $this->palsukanAturan($p['kategori_id']);
        [$k1, $k2] = $p['kompetensi'];
        $pertama = $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$k1 => 80.0, $k2 => 85.0]]);
        DB::table('N_PA_Balancing_Sesi')->where('Id_PA_Balancing_Sesi', $pertama)
            ->update(['Final_At' => now()->subHour(), 'Hitung_Ulang_At' => now()->subMinutes(30), 'Nomor_Hitung_Ulang' => 1]);
        // Sesi kedua: Before = 80 (After sesi pertama), After = 90.
        $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$k1 => 90.0, $k2 => 85.0]]);

        // 77,5 + 0,5 × (90 − 70) = 87,5 — bukan 77,5 + 0,5×10 + 0,5×10 dari dua selisih bertumpuk.
        $this->assertSame(87.5, $this->mesin()->rincianKompetensi($p['participant_id'])['nilai']);
    }

    public function test_simulasi_memakai_isian_layar_tanpa_menulis(): void
    {
        $p = $this->pesertaDihitung(['kpi' => 90.0]);
        $this->palsukanAturan($p['kategori_id']);
        [$k1, $k2] = $p['kompetensi'];
        $sebelum = DB::table('N_PA_Result')->where('Id_PA_Result', $p['result_id'])->first();

        $s = $this->mesin()->simulasiHasil($p['participant_id'], [$k1 => 90.0, $k2 => 85.0]);

        // Komp 77,5 + 0,5 × 20 = 87,5; Final = 90 × 50% + 87,5 × 50% = 88,75.
        $this->assertSame(87.5, $s['nilai_kompetensi']);
        $this->assertSame(PaBalancingKalkulator::nilaiAkhir(90.0, 87.5, 50.0, 50.0), $s['final']);
        $this->assertEquals($sebelum, DB::table('N_PA_Result')->where('Id_PA_Result', $p['result_id'])->first(), 'Simulasi tidak boleh menulis.');
    }

    public function test_hitung_ditolak_selama_peserta_ada_di_sesi_terbuka(): void
    {
        $p = $this->pesertaDihitung();
        $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$p['kompetensi'][0] => 80.0]], 'DRAFT');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/sedang dibahas di sesi Balancing/');
        $this->mesin()->hitung($p['participant_id']);
    }
    /** Review final, Penting 4: memo nilai berlaku hitungSiklus tidak boleh bertahan sesudahnya. */
    public function test_memo_nilai_berlaku_dibuang_sesudah_hitung_siklus(): void
    {
        $cycleId = $this->siklusLembar('CLOSED');
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->update(['Periode_Dinilai_Dari' => date('Y').'-01-01', 'Periode_Dinilai_Sampai' => date('Y').'-12-31']);
        $mesin = $this->mesin();

        $mesin->hitungSiklus($cycleId);

        $balancing = (new \ReflectionProperty($mesin, 'balancing'))->getValue($mesin);
        $this->assertNull((new \ReflectionProperty($balancing, 'siklusTermuat'))->getValue($balancing));
    }
}
