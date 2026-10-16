<?php

namespace App\Services\KPI;

use App\Helpers\KpiFormulaHelper;
use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use Illuminate\Support\Facades\DB;

/**
 * Sumber kebenaran TUNGGAL perhitungan skor KPI.
 *
 * Diekstrak dari UpdatePerformanceController (acuan kanonik). Semua surface
 * (update performance personal/team/bypass, approval, dashboard staff/ceo,
 * homepage, leader dashboard) HARUS memakai service ini agar penyesuaian rumus
 * cukup diubah di satu tempat.
 *
 * Konvensi nilai per-parameter (dari KpiFormulaHelper::evaluate):
 *   ['achievement' => ratio * 100]   // persentase pencapaian (%)
 *   ['score'       => bobot * ratio]  // skor berbobot (weighted)
 *
 * CATATAN konvensi kolom DB (TIDAK diubah oleh service ini): pada
 * KPI_Update_Performance_Detail, kolom `Score` menyimpan achievement% dan kolom
 * `Achievement` menyimpan skor berbobot (tertukar dari namanya). Pemetaan
 * nilai service → kolom dilakukan di pemanggil, bukan di sini.
 */
class KpiScoringService
{
    /**
     * Evaluasi satu parameter langsung dari string rumus, lalu jepit rasionya
     * ke batas per-parameter dari config.
     *
     * Yang dijepit adalah RASIO, bukan achievement sendirian: achievement dan
     * skor sama-sama diturunkan dari rasio yang sudah dijepit. Menjepit
     * achievement saja lalu menghitung skor dari rasio mentah membuat keduanya
     * tak sinkron, dan total berhenti sama dengan rata-rata baris yang tampil.
     *
     * Bila rumus kosong/null → rasio nol (identik perilaku lama), lalu tetap
     * melewati penjepitan.
     *
     * @return array{achievement: float, score: float, achievement_raw: float, score_raw: float, is_capped: bool}
     */
    public function scoreParameter(float $actual, float $target, float $weight, ?string $formula): array
    {
        $mentah = (! is_string($formula) || trim($formula) === '')
            ? ['achievement' => 0.0, 'score' => 0.0]
            : KpiFormulaHelper::evaluate($actual, $target, $weight, $formula);

        // Rasio diturunkan dari achievement yang SUDAH dibulatkan dua desimal oleh
        // KpiFormulaHelper, bukan dari hasil eval mentah: supaya perbandingan
        // "terjepit" tidak terpicu galat pembulatan float, dan supaya angka yang
        // dijepit persis angka yang ditampilkan.
        $rasioMentah = ((float) $mentah['achievement']) / 100;

        return $this->scoreFromRatio($rasioMentah, $weight, (float) $mentah['score']);
    }

    /**
     * Bentuk nilai parameter dari rasio yang sudah diketahui, melewati
     * penjepitan yang sama dengan scoreParameter().
     *
     * Dipakai jalur yang menetapkan rasio secara langsung alih-alih
     * mengevaluasi rumus — terutama enforcement, yang memberi rasio nol karena
     * karyawannya tidak menyerahkan apa pun. Mengevaluasi rumus dengan aktual
     * nol tidak setara: rumus "semakin kecil semakin baik" justru mengembalikan
     * rasio penuh untuk aktual nol.
     *
     * @param  float|null  $skorMentah  skor berbobot mentah bila sudah dihitung
     *                                  pemanggil (menjaga pembulatan aslinya)
     * @return array{achievement: float, score: float, achievement_raw: float, score_raw: float, is_capped: bool}
     */
    public function scoreFromRatio(float $rasioMentah, float $weight, ?float $skorMentah = null): array
    {
        $rasio = $this->jepitRasio($rasioMentah);
        $terjepit = $rasio !== $rasioMentah;

        $achievementMentah = round($rasioMentah * 100, 2);
        $skorMentah = $skorMentah ?? round($weight * $rasioMentah, 2);

        return [
            'achievement' => $terjepit ? round($rasio * 100, 2) : $achievementMentah,
            'score' => $terjepit ? round($weight * $rasio, 2) : $skorMentah,
            'achievement_raw' => $achievementMentah,
            'score_raw' => $skorMentah,
            'is_capped' => $terjepit,
        ];
    }

    /**
     * Jepit rasio ke batas per-parameter. Sisi yang batasnya null dilewati.
     */
    private function jepitRasio(float $rasio): float
    {
        [$max, $min] = $this->paramCapBounds();

        if ($max !== null) {
            $rasio = min($rasio, $max / 100);
        }

        if ($min !== null) {
            $rasio = max($rasio, $min / 100);
        }

        return $rasio;
    }

    /**
     * Batas penjepitan per-parameter [max, min] dalam satuan achievement (%).
     * null pada satu sisi berarti sisi itu tidak dijepit.
     *
     * WAJIB lewat config(), bukan env() langsung: setelah `config:cache`
     * dijalankan, .env tidak lagi dimuat dan env() mengembalikan nilai baku —
     * tombol pengembaliannya mati tanpa memberi tanda apa pun.
     *
     * @return array{0: ?float, 1: ?float}
     */
    public function paramCapBounds(): array
    {
        return [
            $this->batasConfig('param_max_achievement_score', 125.0, bolehMati: true),
            $this->batasConfig('param_min_achievement_score', 50.0, bolehMati: true),
        ];
    }

    /**
     * Baca satu batas dari config dengan dua aturan yang harus tetap terpisah.
     *
     * 1. null = MATI (hanya bila $bolehMati). Itu satu-satunya cara mematikan
     *    penjepitan, dan ia harus berupa pernyataan yang disengaja.
     * 2. Apa pun yang tidak terbaca sebagai angka = kembali ke $baku.
     *
     * Aturan kedua ada karena `KEY=` di .env tidak menghasilkan null dan tidak
     * menghasilkan nilai baku: env() memulangkan STRING KOSONG. `(float) ''`
     * adalah 0,0, dan plafon nol menjepit achievement SETIAP parameter menjadi
     * nol tanpa memberi tanda apa pun. Config sudah menyaringnya di hulu; ini
     * lapis kedua agar `config:cache` yang basi atau penyetelan langsung dari
     * kode tidak bisa menghidupkan kembali kegagalan senyap yang sama.
     *
     * Sampah tidak pernah berarti "matikan aturannya": mematikan penjepitan
     * diam-diam sama merusaknya dengan menolkannya, hanya ke arah sebaliknya.
     */
    private function batasConfig(string $kunci, float $baku, bool $bolehMati): ?float
    {
        $nilai = config("services.kpi.{$kunci}");

        if ($nilai === null) {
            return $bolehMati ? null : $baku;
        }

        return is_numeric($nilai) ? (float) $nilai : $baku;
    }

    /**
     * Pengganti langsung `calculateFormulaStream` yang terduplikasi di banyak
     * controller: resolusi rumus dari map by-id, lalu evaluasi.
     *
     * @param  array<int|string, string|null>  $formulaMap  [Id_Master_Formula => Rumus]
     * @return array{achievement: float, score: float}
     */
    public function scoreParameterById($actual, $target, $weight, $idMasterFormula, array $formulaMap): array
    {
        $formula = $formulaMap[$idMasterFormula] ?? null;

        return $this->scoreParameter((float) ($actual ?? 0), (float) $target, (float) ($weight ?? 0), $formula);
    }

    /**
     * Agregasi total dari kumpulan detail (pengganti `calculateScores`).
     * final_score_percentage = (Σ skor_berbobot / Σ bobot) × 100.
     *
     * $details = iterable of objek/array yang mengekspos skor berbobot ($weightedKey)
     * dan bobot ($bobotKey). Default keys mengikuti kolom lama: 'Achievement' (weighted)
     * dan 'Bobot'.
     *
     * $rawWeightedKey menunjuk kolom skor SEBELUM penjepitan per parameter. Bila
     * key itu tidak ada atau bernilai null (baris beraturan lama), nilai tersimpan
     * dipakai sebagai nilai mentah — baris lama memang tidak pernah dijepit di
     * tingkat parameter.
     *
     * @return array{sum_of_scores: float, sum_of_active_bobot: float, final_score_percentage: float, raw_sum_of_scores: float, raw_final_score_percentage: float, capped_details_count: int}
     */
    public function aggregate(
        $details,
        string $weightedKey = 'Achievement',
        string $bobotKey = 'Bobot',
        ?string $rawWeightedKey = 'Achievement_Raw',
    ): array {
        $sumOfScores = 0.0;
        $sumOfRawScores = 0.0;
        $sumOfActiveBobot = 0.0;
        $cappedCount = 0;

        foreach ($details as $item) {
            $weighted = (float) $this->pick($item, $weightedKey);
            $raw = $rawWeightedKey === null ? null : $this->pick($item, $rawWeightedKey);
            $raw = $raw === null ? $weighted : (float) $raw;

            $sumOfScores += $weighted;
            $sumOfRawScores += $raw;
            $sumOfActiveBobot += (float) $this->pick($item, $bobotKey);

            if (abs($raw - $weighted) > 0.0001) {
                $cappedCount++;
            }
        }

        $finalScorePercentage = $sumOfActiveBobot > 0 ? ($sumOfScores / $sumOfActiveBobot) * 100 : 0;
        $rawScorePercentage = $sumOfActiveBobot > 0 ? ($sumOfRawScores / $sumOfActiveBobot) * 100 : 0;

        return [
            'sum_of_scores' => $sumOfScores,
            'sum_of_active_bobot' => $sumOfActiveBobot,
            'final_score_percentage' => round($finalScorePercentage, 2),
            'raw_sum_of_scores' => round($sumOfRawScores, 2),
            'raw_final_score_percentage' => round($rawScorePercentage, 2),
            'capped_details_count' => $cappedCount,
        ];
    }

    /**
     * Terapkan cap TOTAL pada final_score_percentage (pengganti `applyAchievementCap`).
     *
     * Sejak penjepitan pindah ke tingkat parameter, lapisan ini praktis tidak
     * pernah menggigit — rata-rata tertimbang dari nilai yang semuanya di dalam
     * rentang pasti juga di dalam rentang. Ia sengaja dipertahankan sebagai
     * jaring pengaman terakhir bila batas per-parameter dilonggarkan atau
     * dimatikan lewat config.
     *
     * Jejak audit (raw_* dan is_capped) TIDAK lagi lahir dari penjepitan total,
     * melainkan dari penjumlahan kolom mentah per detail yang dihitung
     * aggregate(). Untuk record beraturan lama yang tidak punya kolom mentah,
     * hasilnya identik dengan perilaku sebelumnya.
     *
     * HANYA final_score_percentage yang di-cap; sum_of_scores TETAP data faktual.
     */
    public function applyCap(array $scores): array
    {
        [$maxCap, $minCap] = $this->capBounds();

        $rawScore = (float) ($scores['raw_final_score_percentage'] ?? $scores['final_score_percentage']);
        $nilai = (float) $scores['final_score_percentage'];
        $cappedScore = max($minCap, min($maxCap, $nilai));
        $totalTerjepit = $cappedScore !== $nilai;

        $scores['raw_final_score_percentage'] = $rawScore;
        $scores['raw_sum_of_scores'] = (float) ($scores['raw_sum_of_scores'] ?? $scores['sum_of_scores']);
        $scores['capped_details_count'] = (int) ($scores['capped_details_count'] ?? 0);
        $scores['is_capped'] = $totalTerjepit || $scores['capped_details_count'] > 0;

        [$paramMax, $paramMin] = $this->paramCapBounds();
        $scores['cap_min_applied'] = $paramMin ?? $minCap;
        $scores['cap_max_applied'] = $paramMax ?? $maxCap;

        if ($totalTerjepit) {
            $scores['final_score_percentage'] = round($cappedScore, 2);
        }

        return $scores;
    }

    /**
     * Cap nilai persentase mentah ke [min, max] config. Untuk jalur yang hanya
     * butuh angka (dashboard/homepage recompute), bukan struktur audit lengkap.
     */
    public function capValue(float $pct): float
    {
        [$maxCap, $minCap] = $this->capBounds();

        return max($minCap, min($maxCap, $pct));
    }

    /**
     * Resolusi level/band performa dari KPI_Master_Performance (pengganti
     * `getPerformanceLevel`). Pemanggil tetap mengirim floor(score) bila memang
     * memakai floor (konvensi lama dipertahankan di pemanggil).
     */
    public function resolveLevel(float $score): object
    {
        return $this->pilihLevel(DB::table('KPI_Master_Performance')->get(), $score);
    }

    /**
     * Level untuk skor dari pita yang SUDAH dimuat — supaya pemanggil yang
     * menghitung ratusan header (backfill cap) tak membayar satu kueri per header.
     *
     * Range_Awal tertinggi yang <= skor. Skor di bawah seluruh pita (mis. kinerja
     * sangat buruk / achievement negatif) = pita TERENDAH, bukan galat konfigurasi;
     * galat hanya bila master performance memang kosong.
     */
    public function pilihLevel(iterable $pita, float $skor): object
    {
        $pita = collect($pita);
        if ($pita->isEmpty()) {
            throw new \Exception('Konfigurasi Master Performance tidak ditemukan atau tidak lengkap.');
        }

        return $pita->filter(fn ($p) => (float) $p->Range_Awal <= $skor)->sortByDesc(fn ($p) => (float) $p->Range_Awal)->first()
            ?? $pita->sortBy(fn ($p) => (float) $p->Range_Awal)->first();
    }

    /**
     * Skor AKUMULASI Full-Year / YTD: agregasi Σtarget & Σactual per-parameter
     * sepanjang rentang periode, lalu evaluasi rumus per-param → normalisasi
     * (Σweighted/Σbobot × 100) → cap. Ini metode kanonik untuk dashboard, dan
     * (per keputusan) juga untuk YTD homepage/leader.
     *
     * @param  iterable  $paramDefs  item dgn key: id, bobot, dan formula_id ATAU formula
     * @param  array<string, float>  $sumTargetByParam  [paramId => Σ Nilai_Target]
     * @param  array<string, float>  $sumActualByParam  [paramId => Σ Nilai_Actual]
     * @param  array<int|string, string|null>  $formulaMap  [Id_Master_Formula => Rumus]
     * @param  array{requireActual?: bool, cap?: bool, round?: int}  $opts
     * @return array{items: array, sum_weighted: float, total_bobot: float, raw_pct: float, final_pct: float}
     */
    public function accumulatedScore(
        iterable $paramDefs,
        array $sumTargetByParam,
        array $sumActualByParam,
        array $formulaMap,
        array $opts = [],
    ): array {
        $requireActual = $opts['requireActual'] ?? false;
        $cap = $opts['cap'] ?? true;
        $round = $opts['round'] ?? 2;

        $sumWeighted = 0.0;
        $totalBobot = 0.0;
        $items = [];

        foreach ($paramDefs as $param) {
            $id = (string) ($this->pick($param, 'id') ?? '');
            if ($id === '') {
                continue;
            }

            $hasActual = array_key_exists($id, $sumActualByParam);
            if ($requireActual && ! $hasActual) {
                continue;
            }

            $weight = (float) ($this->pick($param, 'bobot') ?? 0);
            $target = (float) ($sumTargetByParam[$id] ?? 0);
            $actual = (float) ($sumActualByParam[$id] ?? 0);

            $formula = $this->pick($param, 'formula');
            if ($formula === null) {
                $formula = $formulaMap[$this->pick($param, 'formula_id')] ?? null;
            }

            $calc = $this->scoreParameter($actual, $target, $weight, $formula);

            $sumWeighted += (float) $calc['score'];
            $totalBobot += $weight;
            $items[$id] = [
                'achievement' => (float) $calc['achievement'],
                'score' => (float) $calc['score'],
                'weight' => $weight,
                'target' => $target,
                'actual' => $actual,
                'has_actual' => $hasActual,
            ];
        }

        $rawPct = $totalBobot > 0 ? ($sumWeighted / $totalBobot) * 100 : 0;
        $finalPct = $cap ? $this->capValue($rawPct) : $rawPct;

        return [
            'items' => $items,
            'sum_weighted' => round($sumWeighted, $round),
            'total_bobot' => round($totalBobot, $round),
            'raw_pct' => round($rawPct, $round),
            'final_pct' => round($finalPct, $round),
        ];
    }

    /**
     * Batas cap TOTAL [max, min] dari config services.kpi (baku 125/50).
     *
     * Publik dan sejajar dengan paramCapBounds() supaya pemanggil yang perlu
     * MENAMPILKAN batasnya tidak menyalin ulang config()-nya sendiri. Salinan
     * seperti itu yang melahirkan baku menyimpang 150/0 di beberapa dashboard.
     *
     * @return array{0: float, 1: float} [max, min]
     */
    public function capBounds(): array
    {
        return [
            $this->batasConfig('max_achievement_score', 125.0, bolehMati: false),
            $this->batasConfig('min_achievement_score', 50.0, bolehMati: false),
        ];
    }

    /**
     * Ambil nilai dari objek atau array secara seragam.
     */
    private function pick($item, string $key)
    {
        if (is_array($item)) {
            return $item[$key] ?? null;
        }

        return $item->{$key} ?? null;
    }
}
