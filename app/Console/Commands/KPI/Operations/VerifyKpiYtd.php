<?php

namespace App\Console\Commands\KPI\Operations;

use App\Helpers\KpiFormulaHelper;
use App\Helpers\KpiScheduleHelper;
use App\Services\KPI\KpiAkumulasiYtdService;
use App\Services\KPI\KpiScoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Verifikasi perhitungan YTD (Year To Date) untuk dashboard Staff & CEO/Tim.
 *
 * Mencetak, untuk satu karyawan (atau seluruh tim seorang leader):
 *   1. Rincian per-parameter: Σactual, Σtarget (Jan..bulan batas), ratio, Bobot, Bobot×ratio.
 *   2. Skor YTD AKUMULASI -> harus = angka dashboard saat filter "YTD".
 *   3. Pembanding: rata-rata Total_Score bulanan untuk melihat selisih metode.
 *
 * Angka resminya diambil dari KpiAkumulasiYtdService — sumber yang sama dengan
 * Monitoring, dashboard CEO/staff, homepage, dan export. Perintah ini dulu
 * mereplikasi kuerinya sendiri; replika yang dipakai untuk MEMBANDINGKAN angka
 * justru bisa menyimpang tanpa ketahuan. Yang dihitung di sini hanya angka
 * "mentah" (sebelum jepitan per parameter), sebagai diagnosis.
 *
 * Batas bulan bawaan mengikuti jadwal update performance
 * (KpiScheduleHelper::resolveYtdCapMonth), sama dengan dashboard.
 *
 * Contoh:
 *   php artisan kpi:verify-ytd EMP001 --year=2026
 *   php artisan kpi:verify-ytd --leader=LEAD001 --year=2026
 */
class VerifyKpiYtd extends Command
{
    protected $signature = 'kpi:verify-ytd
        {kode? : Kode_Karyawan yang diverifikasi}
        {--year= : Tahun (default tahun berjalan)}
        {--leader= : Kode_Karyawan leader untuk verifikasi agregat tim}
        {--ytd-month= : Override ytdEndMonth untuk simulasi (1-12)}';

    protected $description = 'Verifikasi perhitungan YTD KPI (akumulasi) vs rata-rata Total_Score bulanan (pembanding)';

    public function handle(): int
    {
        $year = (int) ($this->option('year') ?: date('Y'));
        // Batas dari sumber kanonik. Baku lokal sebelumnya 150/0 — menyimpang
        // dari yang sebenarnya berlaku, dan perintah ini justru yang dipakai
        // untuk MEMBANDINGKAN angka, sehingga batas yang salah membuat seluruh
        // perbandingannya ikut salah.
        [$maxCap, $minCap] = app(KpiScoringService::class)->capBounds();

        // Mode tim memakai jadwal global (seperti dashboard CEO); mode orang
        // memakai jadwal orang itu (seperti dashboard staff).
        $ytdEndMonth = $this->option('ytd-month')
            ? max(1, min(12, (int) $this->option('ytd-month')))
            : KpiScheduleHelper::resolveYtdCapMonth($year, $this->option('leader') ? null : $this->argument('kode'));
        $overrideNote = $this->option('ytd-month') ? ' [OVERRIDE]' : '';
        $this->info("Tahun            : {$year}");
        $this->info("ytdEndMonth      : {$ytdEndMonth} (periode Jan..".str_pad((string) $ytdEndMonth, 2, '0', STR_PAD_LEFT)."){$overrideNote}");
        $this->info("Cap skor         : min {$minCap} / max {$maxCap}");
        $this->line('');

        if ($ytdEndMonth <= 0) {
            $this->warn('Tidak ada periode YTD untuk tahun ini (belum ada bulan terjadwal).');

            return self::SUCCESS;
        }

        $leader = $this->option('leader');
        if ($leader) {
            return $this->verifyTeam($leader, $year, $ytdEndMonth, $minCap, $maxCap);
        }

        $kode = $this->argument('kode');
        if (! $kode) {
            $this->error('Berikan {kode} karyawan, atau gunakan --leader=KODE untuk agregat tim.');

            return self::FAILURE;
        }

        $userId = $this->resolveUserId($kode);
        if (! $userId) {
            $this->error("Karyawan dengan Kode_Karyawan '{$kode}' tidak ditemukan / tidak punya UserID_Web.");

            return self::FAILURE;
        }

        $this->verifyUser($kode, $userId, $year, $ytdEndMonth, $minCap, $maxCap, true);

        return self::SUCCESS;
    }

    private function resolveUserId(string $kode): ?string
    {
        $row = DB::table('Karyawan')->where('Kode_Karyawan', $kode)->first();

        return $row->UserID_Web ?? null;
    }

    /**
     * Skor YTD akumulasi untuk satu user, DARI KpiAkumulasiYtdService.
     *
     * 'score' adalah angka resmi (jepitan per parameter) dan harus sama persis
     * dengan dashboard. Angka "mentah" dihitung ulang dari Σtarget/Σactual milik
     * layanan yang sama, tanpa jepitan per parameter, hanya untuk diagnosis.
     *
     * Tanpa realisasi terverifikasi sama sekali, 'score' = null — bukan 50 hasil
     * menjepit nol. Terjadi pada E14: perintah ini dulu melaporkan YTD 50 /
     * Under Target untuk orang yang belum punya data apa pun.
     */
    private function computeCumulative(string $userId, int $year, int $maxMonth, float $minCap, float $maxCap): array
    {
        $ytdSvc = app(KpiAkumulasiYtdService::class);
        $rincian = $ytdSvc->rincianPerUser([$userId], $year, $ytdSvc->periodeSampai($year, $maxMonth))[$userId] ?? null;

        if (! $rincian) {
            return [
                'score' => null,
                'scoreMentah' => null,
                'rows' => [],
                'sumBobot' => 0.0,
                'rawPct' => 0.0,
                'cappedPct' => 0.0,
                'cappedCount' => 0,
            ];
        }

        $rows = [];
        $sumOfScores = 0.0;
        $cappedCount = 0;

        foreach ($rincian['parameter'] as $param) {
            $mentah = KpiFormulaHelper::evaluate(
                (float) $param['sum_actual'],
                (float) $param['sum_target'],
                (float) $param['bobot'],
                $param['rumus'],
            );
            $dijepit = abs((float) $mentah['score'] - (float) $param['score']) > 0.0001;

            $sumOfScores += (float) $mentah['score'];
            if ($dijepit) {
                $cappedCount++;
            }

            $rows[] = [
                'parameter' => $param['nama_parameter'],
                'actual' => round((float) $param['sum_actual'], 2),
                'target' => round((float) $param['sum_target'], 2),
                'ratio' => round((float) $mentah['achievement'] / 100, 4),
                'bobot' => (float) $param['bobot'],
                'skor' => round((float) $mentah['score'], 2), // Bobot × ratio mentah
                'skor_jepit' => round((float) $param['score'], 2),
                'jepit' => $dijepit ? 'ya' : '',
            ];
        }

        $sumOfBobot = (float) $rincian['total_bobot'];
        $rawPct = $sumOfBobot > 0 ? ($sumOfScores / $sumOfBobot) * 100 : 0;

        return [
            'score' => (float) $rincian['skor'],
            'scoreMentah' => round(max($minCap, min($maxCap, $rawPct)), 2),
            'rows' => $rows,
            'sumBobot' => $sumOfBobot,
            'rawPct' => round($rawPct, 2),
            'cappedPct' => (float) $rincian['raw_pct'],
            'cappedCount' => $cappedCount,
        ];
    }

    /**
     * Rata-rata Total_Score bulanan (pembanding metode, BUKAN angka dashboard mana pun) untuk satu user.
     */
    private function computeMonthlyAverage(string $userId, int $year, int $maxMonth): array
    {
        $maxMonthPad = str_pad((string) $maxMonth, 2, '0', STR_PAD_LEFT);

        $monthly = DB::table('KPI_Update_Performance')
            ->where('Id_User', $userId)
            ->where('Flag_Aktif', 'Y') // revisi yang sudah digantikan tidak dihitung
            ->where('Flag_Approval', 'Y')
            ->where('Flag_Verifikasi', 'Y')
            ->whereNull('Status')
            ->whereRaw('LEFT(Periode, 4) = ?', [(string) $year])
            ->whereRaw('RIGHT(Periode, 2) <= ?', [$maxMonthPad])
            ->selectRaw('RIGHT(Periode, 2) as bulan, MAX(Total_Score) as skor')
            ->groupByRaw('RIGHT(Periode, 2)')
            ->orderByRaw('RIGHT(Periode, 2)')
            ->get();

        $vals = $monthly->pluck('skor')->map(fn ($v) => (float) $v);
        $avg = $vals->isNotEmpty() ? round($vals->avg(), 2) : 0;

        return ['avg' => $avg, 'byMonth' => $monthly];
    }

    private function verifyUser(
        string $kode,
        string $userId,
        int $year,
        int $ytdEndMonth,
        float $minCap,
        float $maxCap,
        bool $verbose,
    ): ?float {
        $cum = $this->computeCumulative($userId, $year, $ytdEndMonth, $minCap, $maxCap);
        $mon = $this->computeMonthlyAverage($userId, $year, $ytdEndMonth);

        if ($verbose) {
            $this->line("<options=bold>Karyawan {$kode} (UserID_Web {$userId})</>");
            if (! empty($cum['rows'])) {
                $this->table(
                    ['Parameter', 'ΣActual', 'ΣTarget', 'Ratio', 'Bobot', 'Bobot×ratio', 'Setelah jepit', 'Dijepit'],
                    array_map(fn ($r) => [
                        $r['parameter'], $r['actual'], $r['target'], $r['ratio'], $r['bobot'],
                        $r['skor'], $r['skor_jepit'], $r['jepit'],
                    ], $cum['rows']),
                );
            } else {
                $this->warn('  Tidak ada parameter KPI aktif untuk user/tahun ini.');
            }

            $this->line("  Σbobot                         : {$cum['sumBobot']}");
            $this->line("  rawPct (Σskor mentah/Σbobot)   : {$cum['rawPct']}");
            $this->line("  Parameter dijepit              : {$cum['cappedCount']} dari ".count($cum['rows']));
            $tampilSkor = $cum['score'] === null ? 'tidak ada data' : $cum['score'];
            $this->line("  <fg=green;options=bold>YTD AKUMULASI (jepit/param)    : {$tampilSkor}</>  <- harus = dashboard filter YTD");

            // Dibandingkan pada presisi angka resmi (1 desimal), supaya selisih
            // pembulatan tidak terbaca sebagai efek jepitan.
            if ($cum['score'] !== null && round($cum['score'], 1) !== round($cum['scoreMentah'], 1)) {
                $this->line("  <fg=gray>YTD tanpa penjepitan parameter : {$cum['scoreMentah']}  (aturan sebelum 28 Agu 2026, BUKAN angka dashboard)</>");
            }

            $this->line('');

            $monthList = $mon['byMonth']->map(fn ($m) => "{$m->bulan}:".round((float) $m->skor, 1))->implode('  ');
            $this->line("  Total_Score bulanan            : {$monthList}");
            $this->line("  <fg=yellow>Rata-rata bulanan (pembanding) : {$mon['avg']}</>");
            $this->line('');
        }

        return $cum['score'];
    }

    private function verifyTeam(string $leader, int $year, int $ytdEndMonth, float $minCap, float $maxCap): int
    {
        $members = DB::table('KPI_Karyawan_Team')
            ->where('Kode_Karyawan_Leader', $leader)
            ->where('Flag_Aktif', 'Y')
            ->pluck('Kode_Karyawan_Team');

        if ($members->isEmpty()) {
            $this->error("Tidak ada anggota tim aktif untuk leader '{$leader}'.");

            return self::FAILURE;
        }

        $this->info("Leader {$leader} — {$members->count()} anggota tim");
        $this->line('');

        $rows = [];
        $onTarget = 0;
        $underTarget = 0;
        $scores = [];

        foreach ($members as $kode) {
            $userId = $this->resolveUserId($kode);
            if (! $userId) {
                $rows[] = [$kode, '-', 'no UserID_Web'];

                continue;
            }
            $score = $this->verifyUser($kode, $userId, $year, $ytdEndMonth, $minCap, $maxCap, false);

            // Tanpa data BUKAN "Under Target". Dashboard mengeluarkan orang
            // seperti ini dari rata-rata; perintah ini harus melakukan hal yang
            // sama, kalau tidak ia melaporkan selisih yang tidak ada.
            if ($score === null) {
                $rows[] = [$kode, '-', 'tidak ada data'];

                continue;
            }

            $scores[] = $score;
            $status = $score >= 100 ? 'On Target' : 'Under Target';
            $score >= 100 ? $onTarget++ : $underTarget++;
            $rows[] = [$kode, $score, $status];
        }

        $this->table(['Kode_Karyawan', 'YTD Akumulasi', 'Status'], $rows);
        $this->line('');
        $this->line("  <fg=green;options=bold>On Target (≥100)   : {$onTarget}</>  <- cocokkan widget CEO 'On Target' saat filter YTD");
        $this->line("  <fg=red;options=bold>Under Target (<100): {$underTarget}</>  <- cocokkan widget CEO 'Under Target' saat filter YTD");
        if (! empty($scores)) {
            $avg = round(array_sum($scores) / count($scores), 2);
            $this->line("  Rata-rata skor tim : {$avg}");
        }
        $this->line('');

        return self::SUCCESS;
    }
}
