<?php

namespace App\Console\Commands\SYSTEM\Application\Maintenance;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pembersih nonce anti-replay yang sudah kedaluwarsa.
 *
 * Nonce hanya berguna selama jendela anti-replay-nya (Expired_At ~5 menit
 * setelah dibuat, lihat HrisApiGuard & LmsApiGuard) — setelah itu ia murni
 * sampah yang memperlambat cek "duplicate request" di setiap callback.
 * Kedua guard hanya MENULIS nonce; sampai command ini ada, tak pernah ada
 * yang menghapusnya (N_LMS_Api_Nonces sudah menumpuk sejak awal integrasi).
 *
 * Penghapusan per-batch TOP(n): DELETE tanpa batas pada tabel yang membesar
 * menahan lock panjang — persis jenis beban yang pernah menyebabkan
 * "Login timeout expired" massal di DB ini.
 *
 * Daftarkan di N_HRIS_Scheduler_Configs: Command='hris:api-nonce-prune', tiap jam.
 */
class ApiNoncePrune extends Command
{
    protected $signature = 'hris:api-nonce-prune {--batch=5000 : Baris per batch delete}';

    protected $description = 'Hapus nonce API (HRIS & LMS) yang sudah lewat Expired_At';

    public function handle(): int
    {
        $batch = max(100, (int) $this->option('batch'));

        foreach (['N_HRIS_Api_Nonce', 'N_LMS_Api_Nonces'] as $tabel) {
            $total = 0;
            do {
                try {
                    $n = DB::affectingStatement(
                        "DELETE TOP ({$batch}) FROM [{$tabel}] WHERE Expired_At < ?",
                        [now()],
                    );
                } catch (\Throwable $e) {
                    // tabel bisa saja tak ada di lingkungan tertentu — lewati, jangan gagal
                    $this->warn("{$tabel}: ".$e->getMessage());
                    $n = 0;
                }
                $total += $n;
            } while ($n === $batch);

            $this->info("{$tabel}: {$total} nonce kedaluwarsa dihapus.");
        }

        return self::SUCCESS;
    }
}
