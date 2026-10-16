<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanBalancing;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

/**
 * Hitung ulang antrean bercicil (koreksi 7 Okt 2026): tiap cicilan melanjutkan dari penanda
 * posisi. Dulu cicilan berikutnya mengambil orang yang sama, sehingga yang tetap tertahan
 * menghalangi sisanya selamanya.
 */
class SapuBercicilTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanBalancing;
    use MenyiapkanLembarPenilaian;

    private function sapu(int $cycleId, int $batas, ?int $mulai, string $jenis = 'TERTAHAN'): array
    {
        return app(PaResultCalculationService::class)->sapuTertahan($cycleId, null, $batas, $mulai, $jenis);
    }

    public function test_cicilan_tertahan_maju_dengan_penanda_sampai_habis(): void
    {
        $a = $this->pesertaDihitung(['status_hasil' => 'TERTAHAN']);
        $b = $this->pesertaDihitung(['cycle_id' => $a['cycle_id'], 'status_hasil' => 'TERTAHAN']);
        $c = $this->pesertaDihitung(['cycle_id' => $a['cycle_id'], 'status_hasil' => 'TERTAHAN']);
        $urut = [$a['participant_id'], $b['participant_id'], $c['participant_id']];
        sort($urut);

        $satu = $this->sapu($a['cycle_id'], 2, null);
        $this->assertSame(2, $satu['diperiksa']);
        $this->assertSame($urut[1], $satu['terakhir_id']);
        $this->assertFalse($satu['selesai']);

        $dua = $this->sapu($a['cycle_id'], 2, $satu['terakhir_id']);
        $this->assertSame(1, $dua['diperiksa']);
        $this->assertSame($urut[2], $dua['terakhir_id']);
        $this->assertTrue($dua['selesai']);
    }

    public function test_jenis_siap_hanya_belum_dihitung_yang_penilainya_tuntas(): void
    {
        $siap = $this->pesertaDihitung(['status_hasil' => 'BELUM_DIHITUNG']);
        $belumTuntas = $this->pesertaDihitung(['cycle_id' => $siap['cycle_id'], 'status_hasil' => 'BELUM_DIHITUNG']);
        DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $belumTuntas['assignment_id'])->update(['Status_Penugasan' => 'BELUM_MULAI']);
        $this->pesertaDihitung(['cycle_id' => $siap['cycle_id'], 'status_hasil' => 'TERTAHAN']);

        $h = $this->sapu($siap['cycle_id'], 50, null, 'SIAP');

        $this->assertSame('SIAP', $h['jenis']);
        $this->assertSame(1, $h['diperiksa']);
        $this->assertSame($siap['participant_id'], $h['terakhir_id']);
        $this->assertTrue($h['selesai']);
    }
}
