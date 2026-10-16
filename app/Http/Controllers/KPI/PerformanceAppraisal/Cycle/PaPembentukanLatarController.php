<?php

namespace App\Http\Controllers\KPI\PerformanceAppraisal\Cycle;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Jobs\KPI\PerformanceAppraisal\PembentukanPenugasanPaJob;
use App\Services\KPI\PerformanceAppraisal\PaAssignmentBuilderService;
use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use App\Services\KPI\PerformanceAppraisal\PaPembentukanTugasService;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Pembentukan penugasan rater sebagai TUGAS LATAR (bagian 2 Langkah 5 penyiapan).
 *
 * Layar hanya memulai dan membaca; `PembentukanPenugasanPaJob` mengerjakan potongan demi
 * potongan di antrean, dan barisnya tampil di panel Tugas & Aktivitas. Admin boleh menutup
 * halaman kapan saja.
 *
 * Endpoint lama `POST /cycles/{id}/penugasan-massal` (satu potongan, sinkron) tetap ada untuk
 * pemanggil lain; Langkah 5 tidak lagi memakainya.
 */
class PaPembentukanLatarController extends Controller
{
    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaAssignmentBuilderService $pembentuk,
        private readonly PaPembentukanTugasService $tugas,
    ) {}

    /** Memulai tugas pembentukan; siklus yang tugasnya masih berjalan mendapat tugas itu kembali. */
    public function mulai(int $cycleId)
    {
        return $this->jalankan(fn () => $this->mulaiTugas($cycleId));
    }

    private function mulaiTugas(int $cycleId): array
    {
        /* Ditolak DI MUKA, saat admin masih di layar — bukan belakangan di dalam job. */
        $this->gate->pastikanBoleh(PaCycleGate::BUAT_PENUGASAN, $cycleId);

        $mulai = $this->tugas->mulai(
            $cycleId,
            $this->pembentuk->progresPembentukan($cycleId),
            $this->userId(),
            optional(optional(Auth::user())->karyawan)->Kode_Karyawan,
        );

        if ($mulai['baru']) {
            PembentukanPenugasanPaJob::dispatch($mulai['id']);
        }

        return [
            'baru' => $mulai['baru'],
            'tugas' => $this->tugas->bentukLayar($this->tugas->baris($mulai['id'])),
        ];
    }

    /** Keadaan tugas terakhir siklus ini — dibaca Langkah 5 tiap beberapa detik. */
    public function status(int $cycleId)
    {
        return $this->jalankan(fn () => [
            'tugas' => $this->tugas->bentukLayar($this->tugas->terakhirUntukSiklus($cycleId)),
        ]);
    }

    /**
     * Menghentikan tugas yang berjalan. Potongan yang sedang dikerjakan diselesaikan; yang sudah
     * terbentuk tetap berlaku.
     */
    public function hentikan(int $cycleId)
    {
        return $this->jalankan(function () use ($cycleId) {
            $baris = $this->tugas->berjalanUntukSiklus($cycleId);

            if (! $baris || ! $this->tugas->hentikan($baris->Export_Id)) {
                throw new RuntimeException('Tidak ada pembentukan yang sedang berjalan.');
            }

            return ['tugas' => $this->tugas->bentukLayar($this->tugas->baris($baris->Export_Id))];
        });
    }

    /** Menjalankan lagi tugas yang gagal atau dihentikan; tanpa tugas gagal, memulai yang baru. */
    public function ulangi(int $cycleId)
    {
        return $this->jalankan(function () use ($cycleId) {
            $this->gate->pastikanBoleh(PaCycleGate::BUAT_PENUGASAN, $cycleId);

            $baris = $this->tugas->terakhirUntukSiklus($cycleId);

            if ($baris && $this->tugas->siapkanUlang($baris->Export_Id)) {
                PembentukanPenugasanPaJob::dispatch($baris->Export_Id);

                return ['tugas' => $this->tugas->bentukLayar($this->tugas->baris($baris->Export_Id))];
            }

            return $this->mulaiTugas($cycleId);
        });
    }

    private function jalankan(callable $operasi)
    {
        try {
            return ResponseHelper::success($operasi(), 'Berhasil');
        } catch (RuntimeException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }
    }

    private function userId(): ?int
    {
        $id = Auth::id();

        return $id === null ? null : (int) $id;
    }
}
