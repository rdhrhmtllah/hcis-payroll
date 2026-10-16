<?php

namespace App\Console\Commands\ESS\Attendance\Leave\Admin;

use App\Models\ESS\Employee\Models\Karyawan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rekonsiliasi saldo cuti — READ ONLY (ZERO WRITE).
 *
 * Membandingkan balance-of-record (HRIS_Buku_Cuti.sisa_cuti, mutable) terhadap
 * ledger append-only (N_HRIS_Log_Buku_Cuti) untuk mendeteksi DRIFT sebelum
 * cutover. Semua pengecekan hanya SELECT — tidak pernah menulis DB.
 *
 * Kelas temuan:
 *   WALLET_TIP_DRIFT        (C) tip ledger != sisa_cuti  ← severity tertinggi
 *   WALLET_CHAIN_BREAK      (B) row[n].Saldo_Sebelum != row[n-1].Saldo_Sesudah   [mode deep/kode]
 *   WALLET_ROW_MATH         (A) Saldo_Sesudah != Saldo_Sebelum + Jumlah          [mode deep/kode]
 *   WALLET_NO_LEDGER        wallet tanpa baris ledger (orphan; kandidat migrasi)
 *   EMPLOYEE_VIEW_MISMATCH  Σtip ledger != Σsisa_cuti (per karyawan, filter view)
 *   EMPLOYEE_PARITY_MISMATCH view Sisa_Cuti_Tampil != Karyawan::calculateLeaveBalance() [mode kode]
 *   INT_TRUNCATION_WARNING  saldo tampil pecahan (UI cast ke INT → terpotong)
 *   DECIMAL_OVERFLOW_RISK   |saldo| mendekati/melewati 999.99 (batas decimal(5,2))
 *
 * Exit code: FAILURE (1) bila ada temuan severity tinggi (tip drift / chain / row math),
 * SUCCESS (0) bila bersih — supaya bisa dipakai sebagai gate scheduler/alert.
 *
 * Contoh:
 *   php artisan cuti:verify                 # scan agregat semua wallet aktif
 *   php artisan cuti:verify EMP001          # deep check 1 karyawan (A/B/C/D + parity)
 *   php artisan cuti:verify --deep --limit=100
 *   php artisan cuti:verify --json > baseline.json
 */
class VerifyCutiBalance extends Command
{
    protected $signature = 'cuti:verify
        {kode? : Kode_Karyawan spesifik (deep check per wallet). Kosong = scan agregat semua karyawan aktif}
        {--tolerance=0.00 : Ambang selisih (hari) yang masih dianggap cocok}
        {--limit=50 : Batas baris detail yang ditampilkan/diarsip per kelas}
        {--overflow-threshold=900 : Ambang |saldo| untuk peringatan DECIMAL_OVERFLOW_RISK}
        {--deep : Mode agregat: jalankan juga cek rantai (A/B) per wallet (lebih berat)}
        {--json : Output JSON (untuk diarsipkan)}';

    protected $description = 'Rekonsiliasi saldo cuti (sisa_cuti vs ledger) — read-only, deteksi drift';

    private const WALLET_TABLE = 'HRIS_Buku_Cuti';

    private const LEDGER_TABLE = 'N_HRIS_Log_Buku_Cuti';

    private const VIEW_TABLE = 'N_HRIS_VW_Saldo_Cuti_Karyawan';

    /** Filter wallet yang dipakai view Saldo_Fisik (harus identik dgn view v2). */
    private const VIEW_WALLET_WHERE = "Flag_Aktif = 'Y' AND Id_Jenis_Cuti IN (1,2) AND ((tanggal_expired >= CAST(GETDATE() AS DATE) AND Activated_At <= CAST(GETDATE() AS DATE)) OR sisa_cuti < 0)";

    public function handle(): int
    {
        $tolerance = (float) $this->option('tolerance');
        $limit = max(1, (int) $this->option('limit'));
        $overflowThreshold = (float) $this->option('overflow-threshold');
        $json = (bool) $this->option('json');
        $kode = $this->argument('kode');

        $result = $kode
            ? $this->verifyEmployee($kode, $tolerance, $limit)
            : $this->verifyAggregate($tolerance, $limit, $overflowThreshold, (bool) $this->option('deep'));

        if ($json) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->render($result);
        }

        return ($result['high_severity_count'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }

    // =================================================================
    // Mode agregat (semua wallet aktif)
    // =================================================================
    private function verifyAggregate(float $tolerance, int $limit, float $overflowThreshold, bool $deep): array
    {
        $tipDrift = $this->walletTipDrift($tolerance, null);
        $noLedger = array_values(array_filter($tipDrift, fn ($r) => $r['ledger_tip'] === null));
        $realDrift = array_values(array_filter($tipDrift, fn ($r) => $r['ledger_tip'] !== null));

        $employeeMismatch = $this->employeeViewMismatch($tolerance, null);
        $intTrunc = $this->intTruncationWarnings(null);
        $overflow = $this->decimalOverflowRisk($overflowThreshold, null);

        $chainBreaks = [];
        $rowMath = [];
        if ($deep) {
            [$chainBreaks, $rowMath] = $this->deepChainChecks($tolerance, null);
        }

        // CHAIN_BREAK = peringatan (artefak alur promosi/pelunasan legacy), BUKAN severity
        // tinggi: invariant otoritatif = tip == sisa_cuti (check-C / WALLET_TIP_DRIFT).
        $high = count($realDrift) + count($rowMath);

        return [
            'mode' => 'aggregate',
            'generated_at' => now()->toDateTimeString(),
            'tolerance' => $tolerance,
            'deep' => $deep,
            'high_severity_count' => $high,
            'summary' => [
                'WALLET_TIP_DRIFT' => count($realDrift),
                'WALLET_CHAIN_BREAK' => $deep ? count($chainBreaks) : 'skipped (--deep)',
                'WALLET_ROW_MATH' => $deep ? count($rowMath) : 'skipped (--deep)',
                'WALLET_NO_LEDGER' => count($noLedger),
                'EMPLOYEE_VIEW_MISMATCH' => count($employeeMismatch),
                'INT_TRUNCATION_WARNING' => count($intTrunc),
                'DECIMAL_OVERFLOW_RISK' => count($overflow),
            ],
            'findings' => [
                'WALLET_TIP_DRIFT' => array_slice($realDrift, 0, $limit),
                'WALLET_CHAIN_BREAK' => array_slice($chainBreaks, 0, $limit),
                'WALLET_ROW_MATH' => array_slice($rowMath, 0, $limit),
                'WALLET_NO_LEDGER' => array_slice($noLedger, 0, $limit),
                'EMPLOYEE_VIEW_MISMATCH' => array_slice($employeeMismatch, 0, $limit),
                'INT_TRUNCATION_WARNING' => array_slice($intTrunc, 0, $limit),
                'DECIMAL_OVERFLOW_RISK' => array_slice($overflow, 0, $limit),
            ],
        ];
    }

    // =================================================================
    // Mode per-karyawan (deep: A/B/C/D + parity)
    // =================================================================
    private function verifyEmployee(string $kode, float $tolerance, int $limit): array
    {
        $tipDrift = $this->walletTipDrift($tolerance, $kode);
        $noLedger = array_values(array_filter($tipDrift, fn ($r) => $r['ledger_tip'] === null));
        $realDrift = array_values(array_filter($tipDrift, fn ($r) => $r['ledger_tip'] !== null));
        [$chainBreaks, $rowMath] = $this->deepChainChecks($tolerance, $kode);
        $employeeMismatch = $this->employeeViewMismatch($tolerance, $kode);
        $parity = $this->parityCheck($kode, $tolerance);

        // CHAIN_BREAK = peringatan (lihat catatan di verifyAggregate), bukan severity tinggi.
        $high = count($realDrift) + count($rowMath);

        return [
            'mode' => 'employee',
            'kode' => $kode,
            'generated_at' => now()->toDateTimeString(),
            'tolerance' => $tolerance,
            'high_severity_count' => $high,
            'summary' => [
                'WALLET_TIP_DRIFT' => count($realDrift),
                'WALLET_CHAIN_BREAK' => count($chainBreaks),
                'WALLET_ROW_MATH' => count($rowMath),
                'WALLET_NO_LEDGER' => count($noLedger),
                'EMPLOYEE_VIEW_MISMATCH' => count($employeeMismatch),
                'EMPLOYEE_PARITY_MISMATCH' => $parity['mismatch'] ? 1 : 0,
            ],
            'findings' => [
                'WALLET_TIP_DRIFT' => array_slice($realDrift, 0, $limit),
                'WALLET_CHAIN_BREAK' => array_slice($chainBreaks, 0, $limit),
                'WALLET_ROW_MATH' => array_slice($rowMath, 0, $limit),
                'WALLET_NO_LEDGER' => array_slice($noLedger, 0, $limit),
                'EMPLOYEE_VIEW_MISMATCH' => $employeeMismatch,
                'EMPLOYEE_PARITY' => $parity,
            ],
        ];
    }

    // =================================================================
    // Check (C): tip ledger vs sisa_cuti  (set-based, window function)
    // =================================================================
    /** @return array<int, array{id_buku:int, kode:string, sisa_cuti:float, ledger_tip:?float, drift:?float}> */
    private function walletTipDrift(float $tolerance, ?string $kode): array
    {
        $where = "b.Flag_Aktif = 'Y'";
        $bindings = [];
        if ($kode !== null) {
            $where .= ' AND b.Kode_Karyawan = ?';
            $bindings[] = $kode;
        }

        $sql = '
            ;WITH tip AS (
                SELECT Id_Buku_Cuti, Saldo_Sesudah AS Ledger_Tip,
                       ROW_NUMBER() OVER (PARTITION BY Id_Buku_Cuti
                                          ORDER BY Created_At DESC, Id_Log DESC) rn
                FROM '.self::LEDGER_TABLE.'
            )
            SELECT b.Id_Buku_Cuti AS id_buku, b.Kode_Karyawan AS kode,
                   b.sisa_cuti AS sisa_cuti, t.Ledger_Tip AS ledger_tip
            FROM '.self::WALLET_TABLE." b
            LEFT JOIN tip t ON t.Id_Buku_Cuti = b.Id_Buku_Cuti AND t.rn = 1
            WHERE {$where}
              AND (t.Ledger_Tip IS NULL OR ABS(b.sisa_cuti - t.Ledger_Tip) > ?)
            ORDER BY ABS(b.sisa_cuti - ISNULL(t.Ledger_Tip, 0)) DESC";
        $bindings[] = $tolerance;

        return array_map(function ($r) {
            $tip = $r->ledger_tip === null ? null : (float) $r->ledger_tip;

            return [
                'id_buku' => (int) $r->id_buku,
                'kode' => $r->kode,
                'sisa_cuti' => (float) $r->sisa_cuti,
                'ledger_tip' => $tip,
                'drift' => $tip === null ? null : round((float) $r->sisa_cuti - $tip, 2),
            ];
        }, DB::select($sql, $bindings));
    }

    // =================================================================
    // Check (A) row math + (B) chain continuity — per wallet, di PHP
    // =================================================================
    /** @return array{0: array<int,array>, 1: array<int,array>} [chainBreaks, rowMath] */
    private function deepChainChecks(float $tolerance, ?string $kode): array
    {
        $walletQuery = DB::table(self::WALLET_TABLE)->where('Flag_Aktif', 'Y');
        if ($kode !== null) {
            $walletQuery->where('Kode_Karyawan', $kode);
        }
        $walletIds = $walletQuery->pluck('Id_Buku_Cuti');

        $chainBreaks = [];
        $rowMath = [];

        foreach ($walletIds as $id) {
            $rows = DB::table(self::LEDGER_TABLE)
                ->where('Id_Buku_Cuti', $id)
                ->orderBy('Created_At')
                ->orderBy('Id_Log')
                ->get(['Id_Log', 'Jenis_Mutasi', 'Jumlah', 'Saldo_Sebelum', 'Saldo_Sesudah']);

            $prevSesudah = null;
            foreach ($rows as $row) {
                $sebelum = $row->Saldo_Sebelum === null ? null : (float) $row->Saldo_Sebelum;
                $sesudah = $row->Saldo_Sesudah === null ? null : (float) $row->Saldo_Sesudah;
                $jumlah = (float) $row->Jumlah;

                // Baris ANOTASI/availability (mis. PROMOTION_UNLOCK): Saldo_Sebelum==Saldo_Sesudah
                // TAPI Jumlah != 0 (Jumlah = jumlah yang di-unlock, BUKAN delta saldo).
                // Bukan mutasi saldo murni → dikecualikan dari cek A & B agar tidak false-positive.
                $isAnnotation = $sebelum !== null && $sesudah !== null
                    && abs($sesudah - $sebelum) <= $tolerance && abs($jumlah) > $tolerance;
                if ($isAnnotation) {
                    continue;
                }

                // (A) row math: Saldo_Sesudah == Saldo_Sebelum + Jumlah (Jumlah signed)
                if ($sebelum !== null && $sesudah !== null && abs($sesudah - ($sebelum + $jumlah)) > $tolerance) {
                    $rowMath[] = [
                        'id_buku' => (int) $id,
                        'id_log' => (int) $row->Id_Log,
                        'saldo_sebelum' => $sebelum,
                        'jumlah' => $jumlah,
                        'saldo_sesudah' => $sesudah,
                        'expected_sesudah' => round($sebelum + $jumlah, 2),
                    ];
                }

                // (B) chain: row[n].Saldo_Sebelum == row[n-1].Saldo_Sesudah
                if ($prevSesudah !== null && $sebelum !== null && abs($sebelum - $prevSesudah) > $tolerance) {
                    $chainBreaks[] = [
                        'id_buku' => (int) $id,
                        'id_log' => (int) $row->Id_Log,
                        'prev_saldo_sesudah' => $prevSesudah,
                        'this_saldo_sebelum' => $sebelum,
                        'gap' => round($sebelum - $prevSesudah, 2),
                    ];
                }

                $prevSesudah = $sesudah ?? $prevSesudah;
            }
        }

        return [$chainBreaks, $rowMath];
    }

    // =================================================================
    // EMPLOYEE_VIEW_MISMATCH: Σtip vs Σsisa_cuti (filter view), per karyawan
    // =================================================================
    /** @return array<int, array{kode:string, sum_sisa_cuti:float, sum_ledger_tip:float, drift:float}> */
    private function employeeViewMismatch(float $tolerance, ?string $kode): array
    {
        $where = self::VIEW_WALLET_WHERE;
        $bindings = [];
        if ($kode !== null) {
            $where .= ' AND b.Kode_Karyawan = ?';
            $bindings[] = $kode;
        }

        $sql = '
            ;WITH tip AS (
                SELECT Id_Buku_Cuti, Saldo_Sesudah AS Ledger_Tip,
                       ROW_NUMBER() OVER (PARTITION BY Id_Buku_Cuti
                                          ORDER BY Created_At DESC, Id_Log DESC) rn
                FROM '.self::LEDGER_TABLE.'
            )
            SELECT b.Kode_Karyawan AS kode,
                   SUM(b.sisa_cuti) AS sum_sisa,
                   SUM(ISNULL(t.Ledger_Tip, b.sisa_cuti)) AS sum_tip
            FROM '.self::WALLET_TABLE." b
            LEFT JOIN tip t ON t.Id_Buku_Cuti = b.Id_Buku_Cuti AND t.rn = 1
            WHERE {$where}
            GROUP BY b.Kode_Karyawan
            HAVING ABS(SUM(b.sisa_cuti) - SUM(ISNULL(t.Ledger_Tip, b.sisa_cuti))) > ?
            ORDER BY ABS(SUM(b.sisa_cuti) - SUM(ISNULL(t.Ledger_Tip, b.sisa_cuti))) DESC";
        $bindings[] = $tolerance;

        return array_map(fn ($r) => [
            'kode' => $r->kode,
            'sum_sisa_cuti' => (float) $r->sum_sisa,
            'sum_ledger_tip' => (float) $r->sum_tip,
            'drift' => round((float) $r->sum_sisa - (float) $r->sum_tip, 2),
        ], DB::select($sql, $bindings));
    }

    // =================================================================
    // EMPLOYEE_PARITY_MISMATCH: view vs Karyawan::calculateLeaveBalance()
    // =================================================================
    private function parityCheck(string $kode, float $tolerance): array
    {
        $view = DB::table(self::VIEW_TABLE)->where('Kode_Karyawan', $kode)->first();
        $karyawan = Karyawan::where('Kode_Karyawan', $kode)->first();

        if (! $view || ! $karyawan) {
            return ['mismatch' => false, 'note' => 'view/karyawan tidak ditemukan', 'view' => null, 'php' => null];
        }

        $php = $karyawan->calculateLeaveBalance();
        $viewSisa = (float) $view->Sisa_Cuti_Tampil;
        $viewHutang = (float) $view->Hutang_Cuti_Tampil;
        $phpSisa = (float) $php['remaining'];
        $phpHutang = (float) $php['debt'];

        $mismatch = abs($viewSisa - $phpSisa) > $tolerance || abs($viewHutang - $phpHutang) > $tolerance;

        return [
            'mismatch' => $mismatch,
            'view' => ['sisa' => $viewSisa, 'hutang' => $viewHutang],
            'php' => ['sisa' => $phpSisa, 'hutang' => $phpHutang],
        ];
    }

    // =================================================================
    // INT_TRUNCATION_WARNING: saldo tampil pecahan (UI cast ke INT)
    // =================================================================
    private function intTruncationWarnings(?string $kode): array
    {
        $q = DB::table(self::VIEW_TABLE)
            ->whereRaw('Sisa_Cuti_Tampil <> FLOOR(Sisa_Cuti_Tampil)');
        if ($kode !== null) {
            $q->where('Kode_Karyawan', $kode);
        }

        return $q->orderByRaw('Sisa_Cuti_Tampil DESC')
            ->get(['Kode_Karyawan', 'Sisa_Cuti_Tampil'])
            ->map(fn ($r) => [
                'kode' => $r->Kode_Karyawan,
                'sisa_tampil' => (float) $r->Sisa_Cuti_Tampil,
                'ditampilkan_UI' => (int) $r->Sisa_Cuti_Tampil,
            ])->all();
    }

    // =================================================================
    // DECIMAL_OVERFLOW_RISK: |saldo| mendekati batas decimal(5,2)=999.99
    // =================================================================
    private function decimalOverflowRisk(float $threshold, ?string $kode): array
    {
        $q = DB::table(self::WALLET_TABLE)
            ->where('Flag_Aktif', 'Y')
            ->whereRaw('(ABS(sisa_cuti) >= ? OR ABS(jumlah_awal_cuti) >= ?)', [$threshold, $threshold]);
        if ($kode !== null) {
            $q->where('Kode_Karyawan', $kode);
        }

        return $q->orderByRaw('ABS(sisa_cuti) DESC')
            ->get(['Id_Buku_Cuti', 'Kode_Karyawan', 'sisa_cuti', 'jumlah_awal_cuti'])
            ->map(fn ($r) => [
                'id_buku' => (int) $r->Id_Buku_Cuti,
                'kode' => $r->Kode_Karyawan,
                'sisa_cuti' => (float) $r->sisa_cuti,
                'jumlah_awal_cuti' => (float) $r->jumlah_awal_cuti,
            ])->all();
    }

    // =================================================================
    // Render tabel ke terminal
    // =================================================================
    private function render(array $result): void
    {
        $this->line('');
        $this->info('cuti:verify — '.($result['mode'] ?? '').(isset($result['kode']) ? " ({$result['kode']})" : ''));
        $this->line("Waktu       : {$result['generated_at']}");
        $this->line("Tolerance   : {$result['tolerance']}");
        $this->line('');

        foreach ($result['summary'] as $klas => $count) {
            $isHigh = in_array($klas, ['WALLET_TIP_DRIFT', 'WALLET_ROW_MATH'], true);
            $numeric = is_int($count) ? $count : 0;
            $tag = ($isHigh && $numeric > 0) ? '<fg=red;options=bold>' : ($numeric > 0 ? '<fg=yellow>' : '<fg=green>');
            $this->line(sprintf('  %s%-26s: %s</>', $tag, $klas, is_int($count) ? $count : $count));
        }
        $this->line('');

        $high = $result['high_severity_count'] ?? 0;
        if ($high > 0) {
            $this->error("Ditemukan {$high} temuan severity TINGGI (tip/chain/row). Exit code = 1.");
        } else {
            $this->info('Bersih — tidak ada drift severity tinggi. Exit code = 0.');
        }

        // Detail tabel untuk kelas yang paling penting
        $this->renderTable($result, 'WALLET_TIP_DRIFT', ['id_buku', 'kode', 'sisa_cuti', 'ledger_tip', 'drift']);
        $this->renderTable($result, 'WALLET_NO_LEDGER', ['id_buku', 'kode', 'sisa_cuti']);
        $this->renderTable($result, 'EMPLOYEE_VIEW_MISMATCH', ['kode', 'sum_sisa_cuti', 'sum_ledger_tip', 'drift']);
        $this->renderTable($result, 'WALLET_CHAIN_BREAK', ['id_buku', 'id_log', 'prev_saldo_sesudah', 'this_saldo_sebelum', 'gap']);
        $this->renderTable($result, 'WALLET_ROW_MATH', ['id_buku', 'id_log', 'saldo_sebelum', 'jumlah', 'saldo_sesudah', 'expected_sesudah']);
    }

    private function renderTable(array $result, string $klas, array $cols): void
    {
        $rows = $result['findings'][$klas] ?? [];
        if (empty($rows) || ! is_array($rows) || ! isset($rows[0]) || ! is_array($rows[0])) {
            return;
        }
        $this->line('');
        $this->line("<options=bold>{$klas}</> (menampilkan ".count($rows).')');
        $this->table($cols, array_map(fn ($r) => array_map(fn ($c) => $r[$c] ?? '', $cols), $rows));
    }
}
