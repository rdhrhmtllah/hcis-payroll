<?php

namespace Tests\Feature\KPI\Backfill;

use App\Services\KPI\KpiCapParameterKalkulator;
use App\Services\KPI\KpiScoringService;
use Tests\TestCase;

/**
 * Kalkulator backfill cap per parameter (12 Okt 2026).
 *
 * Angka harapan ditulis tangan, bukan dihitung ulang lewat KpiScoringService —
 * membandingkan kalkulator dengan pustaka yang ia panggil tak membuktikan apa pun.
 */
class KpiCapParameterKalkulatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.kpi.param_max_achievement_score' => 125,
            'services.kpi.param_min_achievement_score' => 50,
            'services.kpi.max_achievement_score' => 125,
            'services.kpi.min_achievement_score' => 50,
        ]);
    }

    private function pita(): array
    {
        return [
            (object) ['Id_Master_Performance' => 12, 'Range_Awal' => 0.0],
            (object) ['Id_Master_Performance' => 13, 'Range_Awal' => 75.0],
            (object) ['Id_Master_Performance' => 14, 'Range_Awal' => 90.0],
            (object) ['Id_Master_Performance' => 15, 'Range_Awal' => 110.0],
        ];
    }

    private function d(int $id, ?float $score, ?float $ach, float $bobot, ?float $scoreRaw = null, ?float $achRaw = null): object
    {
        return (object) ['Id_Update_Performance_Detail' => $id, 'Score' => $score, 'Achievement' => $ach,
            'Score_Raw' => $scoreRaw, 'Achievement_Raw' => $achRaw, 'Bobot' => $bobot];
    }

    public function test_menjepit_tiap_parameter_lalu_menghitung_total_dan_level(): void
    {
        $hasil = app(KpiCapParameterKalkulator::class)->hitung([
            $this->d(1, 140.0, 42.0, 30.0),  // di atas plafon
            $this->d(2, 80.0, 56.0, 70.0),   // di dalam rentang
        ], $this->pita());

        $this->assertSame(['Id_Update_Performance_Detail' => 1, 'Score' => 125.0, 'Achievement' => 37.5,
            'Score_Raw' => 140.0, 'Achievement_Raw' => 42.0, 'Flag_Capped' => 'Y'], $hasil['detail'][0]);
        $this->assertSame(['Id_Update_Performance_Detail' => 2, 'Score' => 80.0, 'Achievement' => 56.0,
            'Score_Raw' => 80.0, 'Achievement_Raw' => 56.0, 'Flag_Capped' => 'T'], $hasil['detail'][1]);

        $h = $hasil['header'];
        $this->assertSame(93.5, $h['Total_Score']);           // (37,5 + 56) / 100 × 100
        $this->assertSame(98.0, $h['Total_Score_Raw']);       // (42 + 56) / 100 × 100
        $this->assertEqualsWithDelta(93.5, $h['Total_Score_Achievement'], 0.0001);
        $this->assertSame(98.0, $h['Total_Score_Achievement_Raw']);
        $this->assertSame('Y', $h['Flag_Capped']);
        $this->assertSame('PARAMETER', $h['Metode_Cap']);
        $this->assertSame(14, $h['Id_Performance']);          // floor(93,5)=93 → pita 90
        $this->assertSame(50.0, $h['Cap_Min_Applied']);
        $this->assertSame(125.0, $h['Cap_Max_Applied']);
    }

    public function test_menjepit_ke_lantai(): void
    {
        $hasil = app(KpiCapParameterKalkulator::class)->hitung([$this->d(1, 20.0, 10.0, 50.0)], $this->pita());

        $this->assertSame(50.0, $hasil['detail'][0]['Score']);
        $this->assertSame(25.0, $hasil['detail'][0]['Achievement']);
        $this->assertSame(50.0, $hasil['header']['Total_Score']);
        $this->assertSame(12, $hasil['header']['Id_Performance']);
    }

    public function test_nilai_asli_dibaca_dari_kolom_raw_bila_ada(): void
    {
        // Detail yang SUDAH dijepit (Score 125, Score_Raw 140): hitung dari 140, bukan 125.
        $hasil = app(KpiCapParameterKalkulator::class)->hitung([$this->d(1, 125.0, 37.5, 30.0, 140.0, 42.0)], $this->pita());

        $this->assertSame(140.0, $hasil['detail'][0]['Score_Raw']);
        $this->assertSame(42.0, $hasil['detail'][0]['Achievement_Raw']);
        $this->assertSame(125.0, $hasil['detail'][0]['Score']);
    }

    public function test_idempoten_bila_dijalankan_atas_hasilnya_sendiri(): void
    {
        $k = app(KpiCapParameterKalkulator::class);
        $pertama = $k->hitung([$this->d(1, 140.0, 42.0, 30.0), $this->d(2, 20.0, 14.0, 70.0)], $this->pita());

        $masukan = array_map(fn ($r) => (object) ($r + ['Bobot' => $r['Id_Update_Performance_Detail'] === 1 ? 30.0 : 70.0]), $pertama['detail']);
        $kedua = $k->hitung($masukan, $this->pita());

        $this->assertSame($pertama, $kedua, 'Menghitung ulang record yang sudah dijepit mengubah nilainya.');
    }

    public function test_pilih_level_sama_dengan_resolve_level_untuk_pita_nyata(): void
    {
        $s = app(KpiScoringService::class);
        $pita = app(KpiCapParameterKalkulator::class)->muatPita();
        foreach ([-5, 0, 49.99, 50, 74, 75, 89.5, 100, 124.9, 125, 300] as $skor) {
            $this->assertSame(
                (int) $s->resolveLevel($skor)->Id_Master_Performance,
                (int) $s->pilihLevel($pita, $skor)->Id_Master_Performance,
                "Level berbeda untuk skor {$skor}.",
            );
        }
    }

    public function test_pita_kosong_tetap_dianggap_galat_konfigurasi(): void
    {
        $this->expectException(\Exception::class);

        app(KpiScoringService::class)->pilihLevel([], 80);
    }
}
