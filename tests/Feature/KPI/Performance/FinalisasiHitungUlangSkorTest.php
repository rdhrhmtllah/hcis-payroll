<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use App\Services\KPI\KpiScoringService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Finalisasi menghitung ulang skor dari aktual + target terkini, bukan
 * menyalin skor draft.
 *
 * Kasus nyata (produksi, AKPI-08-2026-0284): draft disimpan 31 Agt 2026
 * sebelum penjepitan per parameter ter-deploy, aktual 44 jam lawan target 3.
 * Skor 1466,67% disalin apa adanya ke transaksi final dan dilihat approver.
 */
class FinalisasiHitungUlangSkorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_skor_draft_basi_dihitung_ulang_dan_terjepit(): void
    {
        $baris = DB::table('KPI_Update_Performance_Detail_Sementara as s')
            ->join('KPI_Pengajuan_Det as det', 'det.Id_Pengajuan_Det', '=', 's.Id_Pengajuan_Det')
            ->join('KPI_Pengajuan_Detail as pd', 'pd.Id_Pengajuan_Detail', '=', 'det.Id_Pengajuan_Detail')
            ->join('KPI_Master_Formula as mf', 'mf.Id_Master_Formula', '=', 'pd.Id_Master_Formula')
            ->where('s.Flag_Aktif', 'Y')
            ->where('det.Nilai_Target', '>', 0)
            ->whereNotNull('s.Nilai_Actual')
            ->orderByDesc('s.Id_Update_Performance_Detail_Sementara')
            ->first(['s.Id_Update_Performance_Detail_Sementara', 's.Id_Update_Performance_Sementara', 'det.Nilai_Target', 'pd.Bobot', 'pd.Id_Master_Formula', 'mf.Rumus']);

        if (! $baris) {
            $this->markTestSkipped('Tidak ada detail draft berumus di data dev.');
        }

        // Aktual jauh di atas target, lalu skor draft dibuat BASI: mentah dan
        // tanpa jejak penjepitan, persis baris yang disimpan kode lama.
        $aktual = (float) $baris->Nilai_Target * 15;
        DB::table('KPI_Update_Performance_Detail_Sementara')
            ->where('Id_Update_Performance_Detail_Sementara', $baris->Id_Update_Performance_Detail_Sementara)
            ->update(['Nilai_Actual' => $aktual, 'Score' => 1466.67, 'Achievement' => 220, 'Score_Raw' => null, 'Achievement_Raw' => null, 'Flag_Capped' => 'T']);

        $controller = app(UpdatePerformanceController::class);
        $ref = new \ReflectionMethod($controller, 'getPerformanceDetails');
        $ref->setAccessible(true);
        $hasil = collect($ref->invoke($controller, (int) $baris->Id_Update_Performance_Sementara))
            ->firstWhere('Id_Update_Performance_Detail_Sementara', $baris->Id_Update_Performance_Detail_Sementara);

        $harapan = app(KpiScoringService::class)->scoreParameter($aktual, (float) $baris->Nilai_Target, (float) $baris->Bobot, $baris->Rumus);

        $this->assertEqualsWithDelta($harapan['achievement'], (float) $hasil->Score, 0.001, 'Skor final harus dihitung ulang, bukan disalin dari draft.');
        $this->assertEqualsWithDelta($harapan['score'], (float) $hasil->Achievement, 0.001);
        $this->assertEqualsWithDelta($harapan['achievement_raw'], (float) $hasil->Score_Raw, 0.001, 'Jejak mentah harus ikut terisi.');
        $this->assertSame($harapan['is_capped'] ? 'Y' : 'T', $hasil->Flag_Capped);

        [$max, $min] = app(KpiScoringService::class)->paramCapBounds();
        if ($max !== null) {
            $this->assertLessThanOrEqual($max, (float) $hasil->Score);
        }
        if ($min !== null) {
            $this->assertGreaterThanOrEqual($min, (float) $hasil->Score);
        }
    }
}
