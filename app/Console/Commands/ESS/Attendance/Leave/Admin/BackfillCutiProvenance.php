<?php

namespace App\Console\Commands\ESS\Attendance\Leave\Admin;

use App\Helpers\CutiSourceCategoryHelper;
use App\Services\ESS\Attendance\Leave\Processing\CutiProcessBatchService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill provenance saldo cuti — Fase 1 (deteksi + tandai).
 *
 * SATU command, dua mode:
 *   --dry-run (DEFAULT bila --apply tidak diberikan) : hanya PREVIEW, tidak menulis.
 *   --apply                                          : benar-benar menulis.
 *
 * Yang dilakukan (semua ADITIF, tidak pernah menyentuh sisa_cuti/Flag_Aktif):
 *   1. Tandai wallet migrasi (Is_Migrated='Y') — SCOPE hanya Flag_Aktif='Y'.
 *      Sinyal utama (auto-tag)  : wallet TANPA baris ledger penjelas (orphan).
 *      Sinyal review (NO auto)  : wallet yang baris ledger paling awalnya tidak
 *                                 mulai dari 0 (opening tak terjelaskan) → hanya
 *                                 di-flag Migration_Basis='manual_review'.
 *   2. Backfill Source_Category pada baris ledger yang masih NULL.
 *   3. Sisipkan synthetic opening (OPENING_BALANCE_MIGRATED) untuk wallet auto-tag.
 *
 * PRASYARAT --apply: DDL database/sql/cuti/ sudah dijalankan (kolom baru ada).
 * Command MENOLAK --apply bila kolom belum ada, dengan pesan jelas.
 *
 * Contoh:
 *   php artisan cuti:backfill-provenance            # dry-run (aman)
 *   php artisan cuti:backfill-provenance --apply    # tulis (setelah review dry-run)
 */
class BackfillCutiProvenance extends Command
{
    protected $signature = 'cuti:backfill-provenance
        {--apply : Benar-benar menulis. Tanpa flag ini = dry-run (aman).}
        {--kode= : Batasi ke 1 Kode_Karyawan (mis. setelah review manual, atau untuk uji terisolasi)}
        {--cutoff= : Override tanggal cutoff (default CutiProcessBatchService::accrualCutoffDate())}
        {--limit=30 : Batas sample yang ditampilkan}
        {--json : Output JSON}';

    protected $description = 'Deteksi & tandai wallet migrasi + backfill Source_Category (Fase 1). Default dry-run.';

    private const WALLET_TABLE = 'HRIS_Buku_Cuti';

    private const LEDGER_TABLE = 'N_HRIS_Log_Buku_Cuti';

    private const EPS = 0.001;

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $kode = $this->option('kode') ?: null;
        $limit = max(1, (int) $this->option('limit'));
        $cutoff = $this->option('cutoff') ?: CutiProcessBatchService::accrualCutoffDate();
        $cutoffYear = (int) Carbon::parse($cutoff)->format('Y');
        $now = now();
        $batchRef = 'BACKFILL-'.$now->format('Ymd-His');

        // Guard: --apply butuh kolom baru sudah ada.
        if ($apply && ! $this->columnsReady()) {
            $this->error('Kolom provenance belum ada. Jalankan DDL di database/sql/cuti/ dulu '.
                '(01_..., 02_...). Batalkan --apply.');

            return self::FAILURE;
        }

        $autoTag = $this->detectOrphanWallets($cutoffYear, $kode);      // no_opening_ledger → auto Is_Migrated='Y'
        $review = $this->detectUnexplainedOpening($cutoffYear, $kode);  // manual_review (no auto)
        $sourceMap = $this->sourceCategoryPlan($kode);                  // per (kategori,jenis) → enum + jumlah baris

        $result = [
            'mode' => $apply ? 'APPLY' : 'DRY-RUN',
            'generated_at' => $now->toDateTimeString(),
            'cutoff' => (string) $cutoff,
            'cutoff_year' => $cutoffYear,
            'scope' => "Flag_Aktif='Y'".($kode ? " AND Kode_Karyawan='{$kode}'" : ''),
            'counts' => [
                'auto_tag_migrated' => count($autoTag),
                'flag_manual_review' => count($review),
                'ledger_rows_to_set_source' => array_sum(array_column($sourceMap, 'rows')),
                'synthetic_opening_to_insert' => count($autoTag),
            ],
            'sample' => [
                'auto_tag_migrated' => array_slice($autoTag, 0, $limit),
                'flag_manual_review' => array_slice($review, 0, $limit),
                'source_category_map' => $sourceMap,
            ],
        ];

        if ($apply) {
            $applied = $this->apply($autoTag, $review, $sourceMap, $cutoff, $batchRef, $now, $kode);
            $result['applied'] = $applied;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->render($result, $apply);
        }

        return self::SUCCESS;
    }

    // =================================================================
    // Deteksi (read-only)
    // =================================================================

    /**
     * Wallet AKTIF tanpa baris ledger sama sekali & sisa_cuti != 0.
     * Sinyal terkuat migrasi (opening di-INSERT langsung tanpa lewat sistem).
     *
     * @return array<int, array<string, mixed>>
     */
    private function detectOrphanWallets(int $cutoffYear, ?string $kode = null): array
    {
        $kodeSql = $kode ? ' AND b.Kode_Karyawan = ?' : '';
        $rows = DB::select('
            SELECT b.Id_Buku_Cuti AS id_buku, b.Kode_Karyawan AS kode, b.Periode AS periode,
                   b.sisa_cuti AS sisa_cuti,
                   COALESCE(b.created_at, b.tanggal_diberikan) AS migrated_at_guess
            FROM '.self::WALLET_TABLE.' b
            WHERE b.Flag_Aktif = \'Y\'
              AND ABS(b.sisa_cuti) > '.self::EPS.'
              AND NOT EXISTS (SELECT 1 FROM '.self::LEDGER_TABLE.' l WHERE l.Id_Buku_Cuti = b.Id_Buku_Cuti)'.$kodeSql.'
            ORDER BY b.Kode_Karyawan, b.Id_Buku_Cuti', $kode ? [$kode] : []);

        return array_map(fn ($r) => [
            'id_buku' => (int) $r->id_buku,
            'kode' => $r->kode,
            'periode' => $r->periode !== null ? (int) $r->periode : null,
            'sisa_cuti' => (float) $r->sisa_cuti,
            'migrated_at_guess' => $r->migrated_at_guess,
            'basis' => 'no_opening_ledger',
        ], $rows);
    }

    /**
     * Wallet AKTIF yang PUNYA ledger tapi baris paling awalnya tidak mulai dari 0
     * (opening tak terjelaskan). Hanya di-flag manual_review — TIDAK auto-tag.
     *
     * @return array<int, array<string, mixed>>
     */
    private function detectUnexplainedOpening(int $cutoffYear, ?string $kode = null): array
    {
        $kodeSql = $kode ? ' AND b.Kode_Karyawan = ?' : '';
        $rows = DB::select('
            ;WITH first_row AS (
                SELECT Id_Buku_Cuti, Saldo_Sebelum,
                       ROW_NUMBER() OVER (PARTITION BY Id_Buku_Cuti
                                          ORDER BY Created_At ASC, Id_Log ASC) rn
                FROM '.self::LEDGER_TABLE.'
            )
            SELECT b.Id_Buku_Cuti AS id_buku, b.Kode_Karyawan AS kode, b.Periode AS periode,
                   b.sisa_cuti AS sisa_cuti, f.Saldo_Sebelum AS first_saldo_sebelum
            FROM '.self::WALLET_TABLE.' b
            INNER JOIN first_row f ON f.Id_Buku_Cuti = b.Id_Buku_Cuti AND f.rn = 1
            WHERE b.Flag_Aktif = \'Y\'
              AND f.Saldo_Sebelum IS NOT NULL
              AND ABS(f.Saldo_Sebelum) > '.self::EPS.$kodeSql.'
            ORDER BY ABS(f.Saldo_Sebelum) DESC', $kode ? [$kode] : []);

        return array_map(fn ($r) => [
            'id_buku' => (int) $r->id_buku,
            'kode' => $r->kode,
            'periode' => $r->periode !== null ? (int) $r->periode : null,
            'sisa_cuti' => (float) $r->sisa_cuti,
            'first_saldo_sebelum' => (float) $r->first_saldo_sebelum,
            'basis' => 'manual_review',
        ], $rows);
    }

    /**
     * Rencana Source_Category per (Kategori_Transaksi, Jenis_Mutasi) untuk baris
     * ledger yang Source_Category-nya masih NULL.
     *
     * @return array<int, array{kategori:string, jenis:string, source_category:string, rows:int}>
     */
    private function sourceCategoryPlan(?string $kode = null): array
    {
        $hasCol = $this->hasColumn(self::LEDGER_TABLE, 'Source_Category');

        $q = DB::table(self::LEDGER_TABLE.' as l')
            ->select('l.Kategori_Transaksi', 'l.Jenis_Mutasi', DB::raw('COUNT(*) as c'))
            ->groupBy('l.Kategori_Transaksi', 'l.Jenis_Mutasi');
        if ($hasCol) {
            $q->whereNull('l.Source_Category'); // hanya yang belum terisi (idempoten)
        }
        if ($kode) {
            $q->join(self::WALLET_TABLE.' as b', 'b.Id_Buku_Cuti', '=', 'l.Id_Buku_Cuti')
                ->where('b.Kode_Karyawan', $kode);
        }

        return $q->get()->map(fn ($r) => [
            'kategori' => (string) $r->Kategori_Transaksi,
            'jenis' => (string) $r->Jenis_Mutasi,
            'source_category' => $this->mapSourceCategory((string) $r->Kategori_Transaksi, (string) $r->Jenis_Mutasi),
            'rows' => (int) $r->c,
        ])->all();
    }

    // =================================================================
    // Apply (menulis — hanya saat --apply)
    // =================================================================
    private function apply(array $autoTag, array $review, array $sourceMap, string $cutoff, string $batchRef, Carbon $now, ?string $kode = null): array
    {
        $applied = ['migrated_tagged' => 0, 'review_flagged' => 0, 'source_set_rows' => 0, 'opening_inserted' => 0, 'opening_skipped_exist' => 0];

        // Bila di-scope per karyawan, batasi backfill Source_Category ke dompet miliknya.
        $scopedWalletIds = $kode
            ? DB::table(self::WALLET_TABLE)->where('Kode_Karyawan', $kode)->pluck('Id_Buku_Cuti')->all()
            : null;

        DB::transaction(function () use ($autoTag, $review, $sourceMap, $cutoff, $batchRef, $now, $scopedWalletIds, &$applied) {
            // 1. Tag wallet migrasi (auto) — hanya yang belum ditandai.
            foreach ($autoTag as $w) {
                $affected = DB::table(self::WALLET_TABLE)
                    ->where('Id_Buku_Cuti', $w['id_buku'])
                    ->whereNull('Is_Migrated')
                    ->update([
                        'Is_Migrated' => 'Y',
                        'Migration_Basis' => $w['basis'],
                        'Migrated_At' => $w['migrated_at_guess']
                            ? Carbon::parse($w['migrated_at_guess'])->format('Y-m-d H:i:s')
                            : null,
                        'Tagged_At' => $now->format('Y-m-d H:i:s'),
                        'Migration_Cutoff_Snapshot' => $cutoff,
                        'Migration_Batch_Ref' => $batchRef,
                    ]);
                $applied['migrated_tagged'] += $affected;
            }

            // 2. Flag review (tanpa set Is_Migrated) — hanya Migration_Basis kosong.
            foreach ($review as $w) {
                $affected = DB::table(self::WALLET_TABLE)
                    ->where('Id_Buku_Cuti', $w['id_buku'])
                    ->whereNull('Is_Migrated')
                    ->whereNull('Migration_Basis')
                    ->update([
                        'Migration_Basis' => 'manual_review',
                        'Tagged_At' => $now,
                        'Migration_Batch_Ref' => $batchRef,
                    ]);
                $applied['review_flagged'] += $affected;
            }

            // 3. Backfill Source_Category (per pasangan kategori+jenis, hanya NULL).
            foreach ($sourceMap as $m) {
                $upd = DB::table(self::LEDGER_TABLE)
                    ->whereNull('Source_Category')
                    ->where('Kategori_Transaksi', $m['kategori'])
                    ->where('Jenis_Mutasi', $m['jenis']);
                if ($scopedWalletIds !== null) {
                    $upd->whereIn('Id_Buku_Cuti', $scopedWalletIds);
                }
                $applied['source_set_rows'] += $upd->update(['Source_Category' => $m['source_category']]);
            }

            // 4. Synthetic opening untuk wallet auto-tag (no-ledger → opening = sisa_cuti).
            foreach ($autoTag as $w) {
                $cutiKey = 'OPENING::'.$w['id_buku'];
                $exists = DB::table(self::LEDGER_TABLE)->where('Cuti_Key', $cutiKey)->exists();
                if ($exists) {
                    $applied['opening_skipped_exist']++;

                    continue;
                }
                $amount = $w['sisa_cuti'];
                DB::table(self::LEDGER_TABLE)->insert([
                    'Id_Buku_Cuti' => $w['id_buku'],
                    'No_Transaksi_Ref' => $batchRef,
                    'Jenis_Mutasi' => $amount >= 0 ? 'CREDIT' : 'DEBIT',
                    'Kategori_Transaksi' => 'OPENING_BALANCE_MIGRATED',
                    'Source_Category' => 'migrated_from_manual_book',
                    'Jumlah' => $amount,
                    'Saldo_Sebelum' => 0,
                    'Saldo_Sesudah' => $amount,
                    'Keterangan' => 'Rekonstruksi opening migrasi per '.$now->toDateString().
                        '; migrasi asli diperkirakan '.($w['migrated_at_guess'] ?? 'tidak diketahui').
                        '; tanpa histori transaksi asli.',
                    'Cuti_Key' => $cutiKey,
                    'Created_At' => $now,
                    'Created_By' => 'SYSTEM_BACKFILL',
                ]);
                $applied['opening_inserted']++;
            }
        });

        return $applied;
    }

    // =================================================================
    // Helper
    // =================================================================
    private function columnsReady(): bool
    {
        return $this->hasColumn(self::WALLET_TABLE, 'Is_Migrated')
            && $this->hasColumn(self::WALLET_TABLE, 'Migration_Basis')
            && $this->hasColumn(self::WALLET_TABLE, 'Tagged_At')
            && $this->hasColumn(self::LEDGER_TABLE, 'Source_Category');
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Peta Kategori_Transaksi (+Jenis_Mutasi) → nilai enum Source_Category (helper terpusat). */
    private function mapSourceCategory(string $kategori, string $jenis): string
    {
        return CutiSourceCategoryHelper::map($kategori, $jenis);
    }

    private function render(array $r, bool $apply): void
    {
        $this->line('');
        $mode = $apply ? '<fg=red;options=bold>APPLY (menulis)</>' : '<fg=green;options=bold>DRY-RUN (aman, tidak menulis)</>';
        $this->info('cuti:backfill-provenance — '.$mode);
        $this->line("Waktu        : {$r['generated_at']}");
        $this->line("Cutoff       : {$r['cutoff']} (tahun {$r['cutoff_year']})");
        $this->line("Scope        : {$r['scope']}");
        $this->line('');
        $this->line('<options=bold>Rencana:</>');
        foreach ($r['counts'] as $k => $v) {
            $this->line(sprintf('  %-30s: %s', $k, $v));
        }

        if (! empty($r['sample']['auto_tag_migrated'])) {
            $this->line('');
            $this->line('<options=bold>Auto-tag Is_Migrated=Y (no_opening_ledger)</>');
            $this->table(['id_buku', 'kode', 'periode', 'sisa_cuti'],
                array_map(fn ($w) => [$w['id_buku'], $w['kode'], $w['periode'], $w['sisa_cuti']], $r['sample']['auto_tag_migrated']));
        }
        if (! empty($r['sample']['flag_manual_review'])) {
            $this->line('<options=bold>Flag manual_review (opening tak terjelaskan, TIDAK auto-tag)</>');
            $this->table(['id_buku', 'kode', 'periode', 'first_saldo_sebelum', 'sisa_cuti'],
                array_map(fn ($w) => [$w['id_buku'], $w['kode'], $w['periode'], $w['first_saldo_sebelum'], $w['sisa_cuti']], $r['sample']['flag_manual_review']));
        }

        $this->line('');
        $this->line('<options=bold>Peta Source_Category (baris ledger NULL):</>');
        $this->table(['kategori', 'jenis', 'source_category', 'rows'],
            array_map(fn ($m) => [$m['kategori'], $m['jenis'], $m['source_category'], $m['rows']], $r['sample']['source_category_map']));

        if ($apply && isset($r['applied'])) {
            $this->line('');
            $this->info('HASIL APPLY:');
            foreach ($r['applied'] as $k => $v) {
                $this->line(sprintf('  %-24s: %s', $k, $v));
            }
        } else {
            $this->line('');
            $this->warn('Ini DRY-RUN. Untuk menulis, jalankan ulang dengan --apply (setelah DDL database/sql/cuti/ diterapkan).');
        }
    }
}
