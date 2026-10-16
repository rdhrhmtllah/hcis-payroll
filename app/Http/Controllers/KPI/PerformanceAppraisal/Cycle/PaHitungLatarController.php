<?php

namespace App\Http\Controllers\KPI\PerformanceAppraisal\Cycle;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Jobs\KPI\PerformanceAppraisal\HitungSiklusPaJob;
use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use App\Services\KPI\PerformanceAppraisal\PaHitungTugasService;
use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Perhitungan hasil siklus sebagai TUGAS LATAR (tombol "Hitung" di Pemantauan).
 *
 * Layar hanya memulai dan membaca; `HitungSiklusPaJob` mengerjakan potongan demi potongan di
 * antrean, dan barisnya tampil di panel Tugas & Aktivitas. Admin boleh menutup halaman.
 *
 * Endpoint lama `POST /cycles/{id}/hitung` (satu potongan, sinkron) tetap ada untuk pemanggil
 * lain; Pemantauan tidak lagi memakainya.
 */
class PaHitungLatarController extends Controller
{
    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaResultCalculationService $perhitungan,
        private readonly PaHitungTugasService $tugas,
    ) {}

    /** Memulai tugas; siklus yang tugasnya masih berjalan mendapat tugas itu kembali. */
    public function mulai(Request $request, int $cycleId)
    {
        $data = $request->validate(['lingkup' => ['nullable', 'string', 'in:SEMUA,BELUM']]);

        return $this->jalankan(fn () => $this->mulaiTugas($cycleId, (string) ($data['lingkup'] ?? PaResultCalculationService::LINGKUP_SEMUA)));
    }

    private function mulaiTugas(int $cycleId, string $lingkup): array
    {
        /* Ditolak DI MUKA, saat admin masih di layar — bukan belakangan di dalam job. Aturannya
           sama dengan `hitungSiklus()`: gerbang HITUNG_HASIL dan siklus wajib CLOSED. */
        $this->gate->pastikanBoleh(PaCycleGate::HITUNG_HASIL, $cycleId);

        if ($this->gate->status($cycleId) !== 'CLOSED') {
            throw new RuntimeException('Perhitungan massal hanya untuk siklus yang sudah ditutup.');
        }

        $berjalan = $this->tugas->berjalanUntukSiklus($cycleId);
        $mulai = $berjalan
            ? ['id' => $berjalan->Export_Id, 'baru' => false]
            : $this->tugas->mulai(
                $cycleId,
                ['total' => $this->perhitungan->jumlahAkanDihitung($cycleId, $lingkup), 'sudah' => 0],
                $this->userId(),
                optional(optional(Auth::user())->karyawan)->Kode_Karyawan,
            );

        if ($mulai['baru']) {
            $this->tugas->catat($mulai['id'], ['lingkup' => $lingkup]);
            HitungSiklusPaJob::dispatch($mulai['id']);
        }

        return [
            'baru' => $mulai['baru'],
            'tugas' => $this->tugas->bentukLayar($this->tugas->baris($mulai['id'])),
        ];
    }

    /** Keadaan tugas terakhir siklus ini — dibaca Pemantauan tiap beberapa detik. */
    public function status(int $cycleId)
    {
        return $this->jalankan(fn () => [
            'tugas' => $this->tugas->bentukLayar($this->tugas->terakhirUntukSiklus($cycleId)),
        ]);
    }

    /** Menghentikan tugas yang berjalan. Potongan yang sedang dikerjakan diselesaikan; yang sudah dihitung tetap berlaku. */
    public function hentikan(int $cycleId)
    {
        return $this->jalankan(function () use ($cycleId) {
            $baris = $this->tugas->berjalanUntukSiklus($cycleId);

            if (! $baris || ! $this->tugas->hentikan($baris->Export_Id)) {
                throw new RuntimeException('Tidak ada perhitungan yang sedang berjalan.');
            }

            return ['tugas' => $this->tugas->bentukLayar($this->tugas->baris($baris->Export_Id))];
        });
    }

    /** Melanjutkan tugas yang gagal atau dihentikan dari kursornya; tanpa tugas gagal, memulai yang baru. */
    public function ulangi(Request $request, int $cycleId)
    {
        $data = $request->validate(['lingkup' => ['nullable', 'string', 'in:SEMUA,BELUM']]);

        return $this->jalankan(function () use ($cycleId, $data) {
            $this->gate->pastikanBoleh(PaCycleGate::HITUNG_HASIL, $cycleId);

            $baris = $this->tugas->terakhirUntukSiklus($cycleId);

            if ($baris && $this->tugas->siapkanUlang($baris->Export_Id)) {
                HitungSiklusPaJob::dispatch($baris->Export_Id);

                return ['tugas' => $this->tugas->bentukLayar($this->tugas->baris($baris->Export_Id))];
            }

            return $this->mulaiTugas($cycleId, (string) ($data['lingkup'] ?? PaResultCalculationService::LINGKUP_SEMUA));
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
