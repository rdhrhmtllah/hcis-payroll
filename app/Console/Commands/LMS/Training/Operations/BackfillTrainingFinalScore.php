<?php

namespace App\Console\Commands\LMS\Training\Operations;

use App\Services\LMS\TrainingExecutionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class BackfillTrainingFinalScore extends Command
{
    /**
     * --dry-run    : hanya menampilkan apa yang AKAN diubah, tanpa menyentuh DB (default disarankan).
     * --penjadwalan: batasi ke satu/beberapa Id_Penjadwalan (boleh diulang), mis. --penjadwalan=5929.
     */
    protected $signature = 'lms:backfill-training-score
        {--dry-run : Tampilkan rencana perubahan tanpa menulis ke database}
        {--penjadwalan=* : Batasi hanya pada Id_Penjadwalan tertentu (boleh lebih dari satu)}';

    protected $description = 'Hitung ulang Total_Score & Id_Keterangan_Kelulusan header N_LMS_Transaksi_Training '
        .'untuk penjadwalan lama yang post-test-nya sudah selesai tapi headernya masih kosong.';

    public function handle(TrainingExecutionService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlyPenjadwalan = collect($this->option('penjadwalan'))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $this->info($dryRun
            ? '=== MODE DRY-RUN: tidak ada perubahan yang ditulis ke database ==='
            : '=== MODE EKSEKUSI: perubahan AKAN ditulis ke database ===');

        // Kandidat: header aktif yang skornya belum lengkap (Total_Score 0/null atau keterangan kelulusan null).
        $query = DB::table('N_LMS_Transaksi_Training as t')
            ->where('t.Flag_Aktif', 'Y')
            ->where(function ($q) {
                $q->whereNull('t.Id_Keterangan_Kelulusan')
                    ->orWhere('t.Total_Score', 0)
                    ->orWhereNull('t.Total_Score');
            });

        if ($onlyPenjadwalan->isNotEmpty()) {
            $query->whereIn('t.Id_Penjadwalan', $onlyPenjadwalan->all());
        }

        $candidates = $query
            ->select('t.Id_Transaksi_Training', 't.Id_Penjadwalan', 't.Total_Score', 't.Id_Keterangan_Kelulusan')
            ->orderBy('t.Id_Penjadwalan')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('Tidak ada kandidat yang perlu di-backfill.');

            return self::SUCCESS;
        }

        $this->info("Ditemukan {$candidates->count()} header kandidat. Memproses...");

        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $rows = [];

        foreach ($candidates as $cand) {
            // Satu header bisa mencakup >1 materi (jarang), proses tiap materi.
            $materiIds = DB::table('N_LMS_Transaksi_Training_Detail')
                ->where('Id_Transaksi_Training', $cand->Id_Transaksi_Training)
                ->distinct()
                ->pluck('Id_Materi')
                ->filter(fn ($id) => (int) $id > 0)
                ->map(fn ($id) => (int) $id)
                ->unique();

            if ($materiIds->isEmpty()) {
                $skipped++;

                continue;
            }

            $appliedForHeader = false;

            foreach ($materiIds as $materiId) {
                try {
                    $updates = $service->applyFinalScoreBackfill(
                        (int) $cand->Id_Penjadwalan,
                        $materiId,
                        $dryRun,
                    );
                } catch (Throwable $e) {
                    $failed++;
                    $this->error("Penj={$cand->Id_Penjadwalan} Materi={$materiId} GAGAL: {$e->getMessage()}");

                    continue;
                }

                if ($updates === null) {
                    // Belum siap (post-test belum selesai / hanya pre-test / komponen kurang).
                    continue;
                }

                $appliedForHeader = true;
                $rows[] = [
                    $cand->Id_Penjadwalan,
                    $materiId,
                    $this->fmt($cand->Total_Score).' -> '.$this->fmt($updates['Total_Score'] ?? '(tetap)'),
                    $this->fmt($cand->Id_Keterangan_Kelulusan).' -> '
                        .$this->fmt($updates['Id_Keterangan_Kelulusan'] ?? '(tetap)'),
                    array_key_exists('Score_Learning_Gain', $updates)
                        ? $this->fmt($updates['Score_Learning_Gain'])
                        : '-',
                ];
            }

            if ($appliedForHeader) {
                $updated++;
            } else {
                $skipped++;
            }
        }

        if ($rows !== []) {
            $this->table(
                ['Id_Penjadwalan', 'Id_Materi', 'Total_Score', 'Id_Keterangan_Kelulusan', 'Learning_Gain'],
                $rows,
            );
        }

        $this->newLine();
        $this->info("Ringkasan: header diproses={$candidates->count()}, "
            .($dryRun ? 'akan diperbarui' : 'diperbarui')."={$updated}, "
            ."dilewati (belum siap)={$skipped}, gagal={$failed}");

        if ($dryRun) {
            $this->warn('Ini DRY-RUN. Jalankan ulang tanpa --dry-run untuk menerapkan perubahan.');
        }

        return self::SUCCESS;
    }

    private function fmt($value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return (string) $value;
    }
}
