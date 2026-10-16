<?php

namespace App\Console\Commands\SYSTEM\Application\Maintenance;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pembersih baris cache yang sudah kedaluwarsa.
 *
 * MENGAPA INI ADA. Aplikasi ini memakai cache store `database` — bukan pilihan
 * keliru melainkan satu-satunya store lintas-instance yang tersedia: tak ada
 * Redis, dan cache `file` di Cloud Run bersifat per-instance lalu menguap.
 * Konsekuensinya setiap kunci cache adalah BARIS di tabel bersama pada basis
 * data yang sama yang justru sedang kita coba ringankan.
 *
 * `Illuminate\Cache\DatabaseStore` hanya menghapus sebuah kunci ketika kunci itu
 * ditulis ulang atau di-forget. Baris yang kedaluwarsa dan tak pernah diminta
 * lagi TINGGAL SELAMANYA. Tabelnya karena itu tumbuh mengikuti kardinalitas
 * kunci, bukan jumlah kunci yang hidup. Terukur di Web_HRIS pada 2026-09-01:
 * 121 baris, 96 di antaranya (79%) sudah mati.
 *
 * Penghapusan per-batch TOP(n): DELETE tanpa batas pada tabel BERSAMA menahan
 * lock panjang, dan basis data ini berjalan tanpa RCSI sehingga pembaca ikut
 * antre — persis jenis beban yang pernah menyebabkan "Login timeout expired".
 * Predikatnya memakai idx_cache_expiration yang sudah ada, jadi ini seek.
 *
 * Daftarkan di N_HRIS_Scheduler_Configs: Command='hris:cache-prune', tiap jam.
 */
class CachePrune extends Command
{
    protected $signature = 'hris:cache-prune
                            {--batch=5000 : Baris per batch delete}
                            {--dry-run : Hitung saja, jangan hapus}';

    protected $description = 'Hapus baris tabel cache yang sudah lewat masa berlakunya';

    public function handle(): int
    {
        $tabel = (string) config('cache.stores.database.table', 'cache');
        $batch = max(100, (int) $this->option('batch'));

        /* Satuan kolom `expiration` pada DatabaseStore adalah Unix timestamp
           detik, BUKAN datetime. Membandingkannya dengan now() apa adanya akan
           menghapus seluruh isi tabel. */
        $sekarang = now()->getTimestamp();

        try {
            $mati = (int) DB::table($tabel)->where('expiration', '<', $sekarang)->count();
            $total = (int) DB::table($tabel)->count();
        } catch (\Throwable $e) {
            $this->error("Tabel cache '{$tabel}' tak dapat dibaca: ".$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Tabel {$tabel}: {$total} baris, {$mati} kedaluwarsa.");

        if ($this->option('dry-run')) {
            $this->comment('--dry-run: tidak ada yang dihapus.');

            return self::SUCCESS;
        }

        $dihapus = 0;
        do {
            $n = DB::affectingStatement(
                "DELETE TOP ({$batch}) FROM [{$tabel}] WHERE expiration < ?",
                [$sekarang],
            );
            $dihapus += $n;
        } while ($n === $batch);

        $this->info("Cache dipangkas: {$dihapus} baris kedaluwarsa dihapus.");

        return self::SUCCESS;
    }
}
