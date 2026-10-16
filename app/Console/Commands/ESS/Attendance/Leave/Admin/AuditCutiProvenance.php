<?php

namespace App\Console\Commands\ESS\Attendance\Leave\Admin;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Audit provenance saldo cuti — READ ONLY (ZERO WRITE).
 *
 * Membongkar "acak-acakan"-nya saldo: per karyawan, memecah SEMUA mutasi
 * ledger (N_HRIS_Log_Buku_Cuti) ke dalam bucket sumber berdasarkan
 * Kategori_Transaksi yang SUDAH ADA hari ini (tidak butuh kolom baru).
 *
 * Tujuan (lihat plan §3.1): mengklasifikasi kekurangan jatah & menyoroti
 * karyawan yang saldonya banyak ditambal MANUAL oleh admin (red flag bahwa
 * accrual bocor untuk orang itu) → arahkan ke alat yang benar:
 *   - accrual terlewat  → Recovery page (idempoten, tagged accrual)
 *   - migrasi/hangus     → manual + alasan (tak terhindarkan, tapi ditandai)
 *
 * Bucket kredit (Jenis_Mutasi=CREDIT): accrual_system, manual, migration, debt_repay, other_credit
 * Bucket debit  (Jenis_Mutasi=DEBIT) : usage, expired, debt_create, other_debit
 * Wallet aktif tanpa ledger (orphan) → migration_unlogged (opening tak terjelaskan).
 *
 * Catatan: "manual_share" dihitung dari total KREDIT sepanjang riwayat
 * (accrual + manual + migrasi) — sinyal seberapa besar saldo dibentuk oleh
 * tambalan manual, bukan klaim komposisi saldo berjalan yang presisi
 * (pemakaian FIFO memotong lintas-sumber). Presisi penuh menyusul setelah
 * kolom Source_Category diisi.
 *
 * Contoh:
 *   php artisan cuti:audit-provenance                 # semua karyawan, tampilkan yang ter-flag
 *   php artisan cuti:audit-provenance EMP001          # rincian 1 karyawan
 *   php artisan cuti:audit-provenance --json > provenance.json
 */
class AuditCutiProvenance extends Command
{
    protected $signature = 'cuti:audit-provenance
        {kode? : Kode_Karyawan spesifik. Kosong = semua karyawan}
        {--manual-share-threshold=0.20 : Ambang porsi tambalan manual (0..1) untuk red-flag}
        {--min-manual=1 : Minimal total hari kredit manual agar masuk daftar flag}
        {--limit=50 : Batas baris yang ditampilkan/diarsip}
        {--json : Output JSON}';

    protected $description = 'Audit provenance saldo cuti (pecahan sumber per karyawan) — read-only';

    private const WALLET_TABLE = 'HRIS_Buku_Cuti';

    private const LEDGER_TABLE = 'N_HRIS_Log_Buku_Cuti';

    public function handle(): int
    {
        $kode = $this->argument('kode');
        $threshold = (float) $this->option('manual-share-threshold');
        $minManual = (float) $this->option('min-manual');
        $limit = max(1, (int) $this->option('limit'));
        $json = (bool) $this->option('json');

        $perEmployee = $this->aggregateByEmployee($kode);
        $orphan = $this->orphanOpeningByEmployee($kode);

        // Gabungkan orphan (opening tak ter-log) ke bucket migration_unlogged
        foreach ($orphan as $k => $amount) {
            $perEmployee[$k] ??= $this->emptyBuckets($k);
            $perEmployee[$k]['migration_unlogged'] += $amount;
        }

        $rows = [];
        foreach ($perEmployee as $k => $b) {
            $totalCredit = $b['accrual_system'] + $b['manual'] + $b['migration'] + $b['migration_unlogged'] + $b['debt_repay'] + $b['other_credit'];
            $manualShare = $totalCredit > 0 ? round($b['manual'] / $totalCredit, 4) : 0.0;
            $flagged = $b['manual'] >= $minManual && $manualShare >= $threshold;

            $rows[] = array_merge(['kode' => $k, 'total_credit' => round($totalCredit, 2), 'manual_share' => $manualShare, 'flagged' => $flagged],
                array_map(fn ($v) => round($v, 2), $b));
        }

        // Urutkan: yang ter-flag & manual terbesar dulu
        usort($rows, fn ($a, $b) => ($b['flagged'] <=> $a['flagged']) ?: ($b['manual'] <=> $a['manual']));

        $flaggedRows = array_values(array_filter($rows, fn ($r) => $r['flagged']));

        $result = [
            'mode' => $kode ? 'employee' : 'all',
            'kode' => $kode,
            'generated_at' => now()->toDateTimeString(),
            'manual_share_threshold' => $threshold,
            'min_manual' => $minManual,
            'total_karyawan' => count($rows),
            'flagged_count' => count($flaggedRows),
            'rows' => $kode ? $rows : array_slice($flaggedRows, 0, $limit),
            'note' => 'manual_share = total kredit manual / total seluruh kredit (lifetime). Red-flag = accrual kemungkinan bocor → cek Recovery page dulu sebelum nambal manual.',
        ];

        if ($json) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->render($result, $kode !== null);
        }

        return self::SUCCESS;
    }

    /**
     * Agregasi SUM(Jumlah) per (Kode_Karyawan, Kategori_Transaksi, Jenis_Mutasi),
     * lalu di-bucket di PHP. Jumlah signed: CREDIT positif, DEBIT negatif.
     *
     * @return array<string, array<string, float>>
     */
    private function aggregateByEmployee(?string $kode): array
    {
        $q = DB::table(self::LEDGER_TABLE.' as l')
            ->join(self::WALLET_TABLE.' as b', 'b.Id_Buku_Cuti', '=', 'l.Id_Buku_Cuti')
            ->groupBy('b.Kode_Karyawan', 'l.Kategori_Transaksi', 'l.Jenis_Mutasi')
            ->select('b.Kode_Karyawan as kode', 'l.Kategori_Transaksi as kategori', 'l.Jenis_Mutasi as jenis', DB::raw('SUM(l.Jumlah) as total'));
        if ($kode !== null) {
            $q->where('b.Kode_Karyawan', $kode);
        }

        $out = [];
        foreach ($q->get() as $r) {
            $out[$r->kode] ??= $this->emptyBuckets($r->kode);
            $bucket = $this->classify((string) $r->kategori, (string) $r->jenis);
            // Bucket kredit dijumlah sebagai nilai positif; bucket debit sebagai magnitudo positif.
            $out[$r->kode][$bucket] += abs((float) $r->total);
        }

        return $out;
    }

    /** Wallet AKTIF tanpa baris ledger sama sekali → opening tak terjelaskan (kandidat migrasi). */
    private function orphanOpeningByEmployee(?string $kode): array
    {
        $q = DB::table(self::WALLET_TABLE.' as b')
            ->where('b.Flag_Aktif', 'Y')
            ->whereRaw('NOT EXISTS (SELECT 1 FROM '.self::LEDGER_TABLE.' l WHERE l.Id_Buku_Cuti = b.Id_Buku_Cuti)')
            ->groupBy('b.Kode_Karyawan')
            ->select('b.Kode_Karyawan as kode', DB::raw('SUM(b.sisa_cuti) as total'));
        if ($kode !== null) {
            $q->where('b.Kode_Karyawan', $kode);
        }

        $out = [];
        foreach ($q->get() as $r) {
            $out[$r->kode] = (float) $r->total;
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function emptyBuckets(string $kode): array
    {
        return [
            'accrual_system' => 0.0,
            'manual' => 0.0,
            'migration' => 0.0,
            'migration_unlogged' => 0.0,
            'debt_repay' => 0.0,
            'other_credit' => 0.0,
            'usage' => 0.0,
            'usage_reversal' => 0.0,
            'expired' => 0.0,
            'debt_create' => 0.0,
            'other_debit' => 0.0,
        ];
    }

    /**
     * Klasifikasi kategori → bucket (resilient: keyword uppercase + fallback other_*).
     * Kategori tak dikenal tidak disembunyikan — masuk other_credit / other_debit.
     */
    private function classify(string $kategori, string $jenis): string
    {
        $k = strtoupper(trim($kategori));
        $isCredit = strtoupper(trim($jenis)) === 'CREDIT';

        // Short-codes tutup buku (BatasCutiAkhirTahun): CO/MLY/CLB/BMLY = carry-over accrual
        if (in_array($k, ['CO', 'MLY', 'CLB', 'BMLY'], true)) {
            return 'accrual_system';
        }

        $has = fn (string $needle) => str_contains($k, $needle);

        // Cuti bersama: pemakaian (DEBIT) vs pengembalian/rollback (CREDIT) —
        // mekanisme sistem, BUKAN tambalan manual. Dipisah agar tak mengaburkan.
        if ($has('BERSAMA')) {
            return $isCredit ? 'usage_reversal' : 'usage';
        }

        // Debit-side
        if ($has('EXPIRED') || $has('HANGUS')) {
            return 'expired';
        }
        if (! $isCredit && ($has('PENGAJUAN') || $has('CONSUME') || $has('PEMAKAIAN'))) {
            return 'usage';
        }
        if (! $isCredit && $has('HUTANG')) {
            return 'debt_create';
        }

        // Credit-side
        if ($has('MIGRAS') || $has('OPENING')) {
            return 'migration';
        }
        if ($has('ADJUSTMENT') || $has('MANUAL')) {
            return 'manual';
        }
        if ($has('PAY_DEBT') || $has('PELUNASAN') || ($isCredit && $has('HUTANG'))) {
            return 'debt_repay';
        }
        if ($has('AKRUAL') || $has('MONTHLY') || $has('LOYALTY') || $has('REWARD')
            || $has('AWAL') || $has('PROMOTION') || $has('MATURITY') || $has('SALDO_CUTI')
            || $has('KONVERSI')) {
            return 'accrual_system';
        }

        return $isCredit ? 'other_credit' : 'other_debit';
    }

    private function render(array $result, bool $single): void
    {
        $this->line('');
        $this->info('cuti:audit-provenance — '.($result['mode']).($single ? " ({$result['kode']})" : ''));
        $this->line("Waktu                : {$result['generated_at']}");
        $this->line("Ambang manual_share  : {$result['manual_share_threshold']}  (min manual {$result['min_manual']} hari)");
        $this->line("Total karyawan       : {$result['total_karyawan']}");
        $this->line("<fg=yellow;options=bold>Ter-flag (manual↑)   : {$result['flagged_count']}</>");
        $this->line('');

        if (empty($result['rows'])) {
            $this->info('Tidak ada karyawan yang memenuhi kriteria flag.');

            return;
        }

        $cols = ['kode', 'accrual_system', 'manual', 'migration', 'migration_unlogged', 'usage', 'expired', 'manual_share', 'flag'];
        $tableRows = array_map(fn ($r) => [
            $r['kode'],
            $r['accrual_system'],
            $r['manual'],
            $r['migration'],
            $r['migration_unlogged'],
            $r['usage'],
            $r['expired'],
            $r['manual_share'],
            $r['flagged'] ? '⚠' : '',
        ], $result['rows']);

        $this->table($cols, $tableRows);
        $this->line('');
        $this->line("<fg=cyan>{$result['note']}</>");
    }
}
