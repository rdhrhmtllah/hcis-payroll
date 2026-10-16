<?php

namespace App\Jobs\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaHitungTugasService;
use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Menghitung hasil satu siklus DI LATAR — satu potongan peserta per job, berantai lewat kursor.
 *
 * Tiap peserta tetap dihitung dalam transaksinya sendiri oleh `hitungSiklus()` (≤ 0,5 detik
 * terukur), jadi tidak ada kunci yang dipegang sepanjang siklus. Potongannya 50: ±25–50 detik
 * per job, jauh di bawah batas waktu pekerja.
 */
class HitungSiklusPaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const BATAS_PER_JOB = 50;

    /** Pengaman rantai: 1.000 x 50 peserta jauh di atas siklus terbesar. */
    public const MAKS_BATCH = 1000;

    public $timeout = 900;

    /* Sekali jalan. Pengulangan adalah keputusan admin lewat "Coba Lagi". */
    public $tries = 1;

    public function __construct(public string $taskId) {}

    public function handle(PaHitungTugasService $tugas, PaResultCalculationService $perhitungan): void
    {
        $baris = $tugas->baris($this->taskId);

        if (! $baris || $tugas->dihentikan($this->taskId) || ! $tugas->klaim($this->taskId)) {
            return;
        }

        $p = $tugas->payload($baris);
        $cycleId = (int) ($p['cycle_id'] ?? 0);
        $userId = isset($p['user_id']) ? (int) $p['user_id'] : null;
        $batch = (int) ($p['batch'] ?? 0) + 1;
        $lingkup = (string) ($p['lingkup'] ?? PaResultCalculationService::LINGKUP_SEMUA);
        $kursor = isset($p['mulai_dari']) ? (int) $p['mulai_dari'] : null;

        try {
            $h = $perhitungan->hitungSiklus($cycleId, $userId, self::BATAS_PER_JOB, $kursor, $lingkup);

            $galatBaru = (array) ($h['gagal'] ?? []);
            $ringkasan = (array) ($p['ringkasan'] ?? []);
            $ringkasan['sudah'] = (int) ($ringkasan['sudah'] ?? 0) + (int) ($h['diproses'] ?? 0);

            $tugas->catat($this->taskId, [
                'batch' => $batch,
                'mulai_dari' => $h['terakhir_id'] ?? $kursor,
                'dihitung' => (int) ($p['dihitung'] ?? 0) + (int) ($h['dihitung'] ?? 0),
                'tertahan' => (int) ($p['tertahan'] ?? 0) + (int) ($h['tertahan'] ?? 0),
                'ringkasan' => $ringkasan,
                'contoh_galat' => array_slice(array_merge((array) ($p['contoh_galat'] ?? []), $galatBaru), 0, 5),
                'jumlah_galat' => (int) ($p['jumlah_galat'] ?? 0) + count($galatBaru),
            ]);

            $lanjut = empty($h['selesai']) && ! empty($h['terakhir_id']);

            if ($lanjut && $batch < self::MAKS_BATCH && ! $tugas->dihentikan($this->taskId)) {
                self::dispatch($this->taskId);

                return;
            }

            if (! $tugas->dihentikan($this->taskId)) {
                $tugas->selesai($this->taskId);
            }
        } catch (Throwable $e) {
            Log::error('PA: perhitungan latar gagal', ['task' => $this->taskId, 'cycle_id' => $cycleId, 'galat' => $e->getMessage()]);
            $tugas->gagal($this->taskId, $e->getMessage());
        }
    }

    public function failed(Throwable $e): void
    {
        app(PaHitungTugasService::class)->gagal($this->taskId, $e->getMessage());
    }
}
