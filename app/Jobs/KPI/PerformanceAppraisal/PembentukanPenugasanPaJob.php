<?php

namespace App\Jobs\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaAssignmentBuilderService;
use App\Services\KPI\PerformanceAppraisal\PaPembentukanTugasService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Membentuk penugasan rater satu siklus DI LATAR — satu potongan peserta per job, berantai.
 *
 * Urutannya sama dengan yang dulu dijalankan layar (`PaCycleAdminController::bentukPenugasanMassal`):
 *
 *   cepat     `bentukMassalCepat()` atas peserta yang belum punya satu penugasan pun, berkursor
 *             supaya peserta yang selalu gagal tidak diambil lagi tiap potongan.
 *   lengkapi  sesudah kursor habis, `bentukMassal()` mencoba sisanya lewat `tugaskan()`.
 *
 * Berhenti ketika tak ada lagi peserta yang diambil — atau, di jalur lengkapi, ketika satu
 * potongan tidak menghasilkan satu penugasan pun: tanpa kursor, peserta yang calonnya memang
 * tidak cukup akan diambil lagi setiap potongan, dan rantainya berputar tanpa maju.
 */
class PembentukanPenugasanPaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Sama dengan potongan layar dulu: sekitar 10 detik per 100 peserta. */
    public const BATAS_PER_JOB = 100;

    /** Pengaman rantai: 1.000 x 100 peserta jauh di atas siklus terbesar. */
    public const MAKS_BATCH = 1000;

    public $timeout = 900;

    /* Sekali jalan. Pengulangan adalah keputusan admin lewat "Coba Lagi". */
    public $tries = 1;

    public function __construct(public string $taskId) {}

    public function handle(PaPembentukanTugasService $tugas, PaAssignmentBuilderService $pembentuk): void
    {
        $baris = $tugas->baris($this->taskId);

        if (! $baris || $tugas->dihentikan($this->taskId) || ! $tugas->klaim($this->taskId)) {
            return;
        }

        $p = $tugas->payload($baris);
        $cycleId = (int) ($p['cycle_id'] ?? 0);
        $userId = isset($p['user_id']) ? (int) $p['user_id'] : null;
        $batch = (int) ($p['batch'] ?? 0) + 1;
        $jalur = $p['jalur'] ?? PaPembentukanTugasService::JALUR_CEPAT;
        $kursor = isset($p['mulai_dari']) ? (int) $p['mulai_dari'] : null;

        try {
            if ($jalur === PaPembentukanTugasService::JALUR_CEPAT) {
                $h = $pembentuk->bentukMassalCepat($cycleId, false, self::BATAS_PER_JOB, $userId, $kursor);

                if (($h['peserta_diproses'] ?? 0) > 0) {
                    $kursor = (int) ($h['terakhir_id'] ?? $kursor);
                } else {
                    $jalur = PaPembentukanTugasService::JALUR_LENGKAPI;
                    $h = $pembentuk->bentukMassal($cycleId, false, self::BATAS_PER_JOB, $userId);
                }
            } else {
                $h = $pembentuk->bentukMassal($cycleId, false, self::BATAS_PER_JOB, $userId);
            }

            $galatBaru = (array) ($h['gagal'] ?? []);
            $ringkasan = $pembentuk->progresPembentukan($cycleId);

            $tugas->catat($this->taskId, [
                'batch' => $batch,
                'jalur' => $jalur,
                'mulai_dari' => $kursor,
                'dibuat' => (int) ($p['dibuat'] ?? 0) + (int) ($h['dibuat'] ?? 0),
                'ringkasan' => $ringkasan,
                'contoh_galat' => array_slice(array_merge((array) ($p['contoh_galat'] ?? []), $galatBaru), 0, 5),
                'jumlah_galat' => (int) ($p['jumlah_galat'] ?? 0) + count($galatBaru),
            ]);

            $diambil = (int) ($h['peserta_diproses'] ?? 0) > 0;
            $tanpaKemajuan = $jalur === PaPembentukanTugasService::JALUR_LENGKAPI && (int) ($h['dibuat'] ?? 0) === 0;

            if ($diambil && ! $tanpaKemajuan && $batch < self::MAKS_BATCH && ! $tugas->dihentikan($this->taskId)) {
                self::dispatch($this->taskId);

                return;
            }

            /* Selesai walau masih ada peserta tanpa penugasan: mereka memang tidak punya calon
               yang cukup, dan itu urusan relasi organisasi, bukan pembentukan. Angkanya disebut
               di panel dan di bagian 3 (Kesehatan) Langkah 5. */
            if (! $tugas->dihentikan($this->taskId)) {
                $tugas->selesai($this->taskId);
            }
        } catch (Throwable $e) {
            Log::error('PA: pembentukan latar gagal', ['task' => $this->taskId, 'cycle_id' => $cycleId, 'galat' => $e->getMessage()]);
            $tugas->gagal($this->taskId, $e->getMessage());
        }
    }

    public function failed(Throwable $e): void
    {
        app(PaPembentukanTugasService::class)->gagal($this->taskId, $e->getMessage());
    }
}
