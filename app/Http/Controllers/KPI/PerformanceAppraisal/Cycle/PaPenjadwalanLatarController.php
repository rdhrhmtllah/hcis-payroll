<?php

namespace App\Http\Controllers\KPI\PerformanceAppraisal\Cycle;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Jobs\KPI\PerformanceAppraisal\HapusJadwalHcLearnPaJob;
use App\Jobs\KPI\PerformanceAppraisal\PenjadwalanHcLearnPaJob;
use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use App\Services\KPI\PerformanceAppraisal\PaCyclePreparationService;
use App\Services\KPI\PerformanceAppraisal\PaHcLearnScheduleService;
use App\Services\KPI\PerformanceAppraisal\PaPenjadwalanTugasService;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Kunci & jadwalkan rater ke HC Learn sebagai TUGAS LATAR (langkah 5 penyiapan siklus).
 *
 * Layar hanya memulai dan membaca. Pekerjaannya — ribuan kiriman ke HC Learn dan penantian
 * hasilnya — dikerjakan `PenjadwalanHcLearnPaJob` di antrean, dan barisnya tampil di panel
 * Tugas & Aktivitas. Admin boleh menutup halaman kapan saja.
 *
 * Endpoint lama `POST /cycles/{id}/penjadwalan` (satu batch, sinkron) tetap ada untuk
 * pemanggil lain; langkah 5 tidak lagi memakainya.
 */
class PaPenjadwalanLatarController extends Controller
{
    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaCyclePreparationService $penyiapan,
        private readonly PaHcLearnScheduleService $jadwal,
        private readonly PaPenjadwalanTugasService $tugas,
    ) {}

    /**
     * Mengunci (bila belum) lalu memulai tugas penjadwalan. Siklus yang sudah punya tugas
     * berjalan mendapat tugas itu kembali, bukan tugas kedua.
     */
    public function mulai(int $cycleId)
    {
        return $this->jalankan(fn () => $this->mulaiTugas($cycleId));
    }

    private function mulaiTugas(int $cycleId): array
    {
        /* Ditolak DI MUKA, saat admin masih di layar — galat di dalam job hanya terbaca di
           panel aktivitas. Penguncian pun dicegah: mengunci siklus yang jendelanya belum
           sah meninggalkan siklus beku yang tak bisa dijadwalkan. */
        $this->jadwal->jendelaSah($cycleId);

        $status = $this->gate->status($cycleId);

        if (in_array($status, ['DRAFT', 'PREPARED'], true)) {
            $this->penyiapan->kunci($cycleId, $this->userId());
        } elseif (! in_array($status, ['LOCKED', 'OPEN'], true)) {
            throw new RuntimeException("Siklus berstatus {$status} tidak bisa dijadwalkan ke HC Learn.");
        }

        $mulai = $this->tugas->mulai(
            $cycleId,
            $this->jadwal->ringkasan($cycleId),
            $this->userId(),
            optional(optional(Auth::user())->karyawan)->Kode_Karyawan,
        );

        if ($mulai['baru']) {
            PenjadwalanHcLearnPaJob::dispatch($mulai['id']);
        }

        return [
            'baru' => $mulai['baru'],
            'tugas' => $this->tugas->bentukLayar($this->tugas->baris($mulai['id'])),
            'ringkasan' => $this->jadwal->ringkasan($cycleId),
        ];
    }

    /** Keadaan tugas terakhir siklus ini — dibaca dialog progres tiap beberapa detik. */
    public function status(int $cycleId)
    {
        return $this->jalankan(fn () => [
            'tugas' => $this->tugas->bentukLayar($this->tugas->terakhirUntukSiklus($cycleId)),
            'ringkasan' => $this->jadwal->ringkasan($cycleId),
            // Jadwal milik penugasan yang dibatalkan yang masih hidup di HC Learn.
            'jadwal_dibatalkan' => $this->jadwal->ringkasanJadwalDibatalkan($cycleId),
        ]);
    }

    /**
     * Menghapus (lagi) jadwal HC Learn milik penugasan yang dibatalkan — di antrean.
     * Dipakai tombol "Hapus sekarang" Langkah 5 untuk sisa yang gagal atau tertinggal.
     */
    public function hapusDibatalkan(int $cycleId)
    {
        return $this->jalankan(function () use ($cycleId) {
            $this->gate->pastikanBoleh(PaCycleGate::BATALKAN_PENUGASAN, $cycleId);

            HapusJadwalHcLearnPaJob::dispatch(null, $cycleId, $this->userId());

            return ['jadwal_dibatalkan' => $this->jadwal->ringkasanJadwalDibatalkan($cycleId)];
        });
    }

    /** Menjalankan lagi tugas yang gagal atau dihentikan. */
    public function ulangi(int $cycleId)
    {
        return $this->jalankan(function () use ($cycleId) {
            $baris = $this->tugas->terakhirUntukSiklus($cycleId);

            if ($baris && $this->tugas->siapkanUlang($baris->Export_Id)) {
                PenjadwalanHcLearnPaJob::dispatch($baris->Export_Id);

                return ['tugas' => $this->tugas->bentukLayar($this->tugas->baris($baris->Export_Id))];
            }

            /* Tidak ada tugas gagal untuk diulang — mulai yang baru (sudah idempoten). */
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
