<?php

namespace App\Console\Commands\SYSTEM\Application\Maintenance;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pemangkas tabel diagnostik yang selama ini hanya bertambah.
 *
 * ── Kenapa ini perlu ada ─────────────────────────────────────────────────────
 *
 * RequestDiagnostics menulis satu baris tiap permintaan >= slow_ms atau 5xx, dan
 * db:health-watch menulis satu baris tiap sampel. Keduanya BENAR; yang tidak ada
 * adalah yang menghapusnya. Per 9 September 2026 N_HRIS_Request_Diagnostics di
 * production sudah 71.000 baris tanpa satu pun baris pernah dibuang sejak
 * middleware-nya dipasang.
 *
 * Akibatnya bukan sekadar tabel besar. Halaman /admin/activity meng-agregat
 * SELURUH jendela waktunya (diagnosticsSummary) dan menghitung total baris untuk
 * paginasi, dan indeks yang ada tidak menutupi kolom yang diagregat — sehingga
 * tiap kunjungan memindai tabel penuh. Terbukti di DMV development: indeks
 * clustered tabel ini tercatat 0 seek melawan 65 scan. Tabel yang tumbuh
 * selamanya membuat pemantau kesehatan sistem ikut jadi beban yang dipantau.
 *
 * ── Kenapa per-batch ─────────────────────────────────────────────────────────
 *
 * DELETE tanpa batas pada tabel yang membesar menahan lock panjang, dan RCSI
 * mati di Web_HRIS sehingga pembaca antre di belakangnya. DELETE TOP(n) berulang
 * menahan lock sebentar-sebentar dan memberi jalan pada lalu lintas normal —
 * pola yang sama dengan hris:api-nonce-prune.
 *
 * Daftarkan di N_HRIS_Scheduler_Configs: Command='hris:diagnostik-prune', harian.
 */
class DiagnostikPrune extends Command
{
    protected $signature = 'hris:diagnostik-prune
                            {--hari= : Umur maksimum baris (default dari config request-diagnostics.retention_days)}
                            {--batch=2000 : Baris per batch delete}
                            {--kering : Hitung saja, jangan hapus}';

    protected $description = 'Pangkas N_HRIS_Request_Diagnostics & N_HRIS_DB_Health_Snapshot yang sudah lewat masa simpan';

    /** Tabel diagnostik dan kolom waktunya masing-masing. */
    private const TABEL = [
        'N_HRIS_Request_Diagnostics' => 'Created_At',
        'N_HRIS_DB_Health_Snapshot' => 'Captured_At',
    ];

    public function handle(): int
    {
        $hari = (int) ($this->option('hari') ?? config('request-diagnostics.retention_days', 90));

        if ($hari < 1) {
            $this->error('--hari minimal 1. Memangkas sampai nol akan menghapus insiden yang sedang dilihat orang.');

            return self::FAILURE;
        }

        $batch = max(100, (int) $this->option('batch'));
        $ambang = now()->subDays($hari);
        $kering = (bool) $this->option('kering');

        $this->info(sprintf(
            '%sBuang baris sebelum %s (masa simpan %d hari).',
            $kering ? '[KERING] ' : '',
            $ambang->format('Y-m-d H:i'),
            $hari,
        ));

        foreach (self::TABEL as $tabel => $kolom) {
            // COUNT hanya untuk mode kering. Di jalur nyata, DELETE sudah
            // memulangkan jumlah barisnya sendiri — menghitung lebih dulu berarti
            // memindai tabel yang sama dua kali untuk angka yang sudah didapat.
            try {
                if ($kering) {
                    $sisa = (int) DB::table($tabel)->where($kolom, '<', $ambang)->count();
                    $this->line("  {$tabel}: {$sisa} baris akan dihapus.");

                    continue;
                }

                // Menyentuh tabelnya sekali supaya ketiadaannya ketahuan di sini,
                // bukan di tengah perulangan DELETE.
                DB::table($tabel)->limit(1)->exists();
            } catch (\Throwable $e) {
                // Tabel diagnostik boleh saja belum ada di sebuah lingkungan —
                // itu bukan kegagalan pemangkasan.
                $this->warn("{$tabel}: dilewati ({$e->getMessage()})");

                continue;
            }

            $total = 0;
            do {
                $n = DB::affectingStatement(
                    "DELETE TOP ({$batch}) FROM [{$tabel}] WHERE [{$kolom}] < ?",
                    [$ambang],
                );
                $total += $n;
            } while ($n === $batch);

            $this->line("  {$tabel}: {$total} baris dihapus.");
        }

        return self::SUCCESS;
    }
}
