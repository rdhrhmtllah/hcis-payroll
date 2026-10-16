<?php

namespace App\Services\KPI;

use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Hitung ulang satu header KPI + detailnya dengan cap PER PARAMETER.
 *
 * Murni: tidak membaca atau menulis basis data selain muatPita(). Rumusnya
 * milik KpiScoringService, tidak ditulis ulang di sini — hasilnya wajib sama
 * dengan finalisasi normal.
 *
 * Nilai asli diambil dari kolom _Raw bila sudah ada, sehingga record yang sudah
 * dijepit menghasilkan nilai yang sama (idempoten).
 */
class KpiCapParameterKalkulator
{
    public function __construct(private KpiScoringService $scoring) {}

    public function muatPita(): Collection
    {
        return DB::table('KPI_Master_Performance')->get(['Id_Master_Performance', 'Range_Awal']);
    }

    /**
     * @param  iterable<object|array>  $detail  Id_Update_Performance_Detail, Score, Achievement, Score_Raw, Achievement_Raw, Bobot
     * @param  iterable<object>  $pita  baris KPI_Master_Performance (lihat muatPita)
     */
    public function hitung(iterable $detail, iterable $pita): array
    {
        $baru = [];
        $berbobot = [];
        foreach ($detail as $d) {
            $d = (object) $d;
            $asliScore = (float) ($d->Score_Raw ?? $d->Score);
            $asliAch = (float) ($d->Achievement_Raw ?? $d->Achievement);
            $h = $this->scoring->scoreFromRatio($asliScore / 100, (float) $d->Bobot, $asliAch);

            $baris = [
                'Id_Update_Performance_Detail' => (int) $d->Id_Update_Performance_Detail,
                'Score' => (float) $h['achievement'],
                'Achievement' => (float) $h['score'],
                'Score_Raw' => (float) $h['achievement_raw'],
                'Achievement_Raw' => (float) $h['score_raw'],
                'Flag_Capped' => $h['is_capped'] ? 'Y' : 'T',
            ];
            $baru[] = $baris;
            $berbobot[] = $baris + ['Bobot' => (float) $d->Bobot];
        }

        $scores = $this->scoring->applyCap($this->scoring->aggregate($berbobot, 'Achievement', 'Bobot', 'Achievement_Raw'));
        $level = $this->scoring->pilihLevel($pita, floor($scores['final_score_percentage']));

        return [
            'detail' => $baru,
            'header' => [
                'Total_Score' => (float) $scores['final_score_percentage'],
                'Total_Score_Achievement' => (float) $scores['sum_of_scores'],
                'Total_Score_Raw' => (float) $scores['raw_final_score_percentage'],
                'Total_Score_Achievement_Raw' => (float) $scores['raw_sum_of_scores'],
                'Flag_Capped' => ! empty($scores['is_capped']) ? 'Y' : 'T',
                // Sama seperti finalisasi: dijepit per parameter. Penanda ini yang
                // membuat backfill tak menyentuh record ini lagi.
                'Metode_Cap' => UpdatePerformanceController::METODE_CAP_PARAMETER,
                'Cap_Min_Applied' => isset($scores['cap_min_applied']) ? (float) $scores['cap_min_applied'] : null,
                'Cap_Max_Applied' => isset($scores['cap_max_applied']) ? (float) $scores['cap_max_applied'] : null,
                'Id_Performance' => (int) $level->Id_Master_Performance,
            ],
        ];
    }
}
