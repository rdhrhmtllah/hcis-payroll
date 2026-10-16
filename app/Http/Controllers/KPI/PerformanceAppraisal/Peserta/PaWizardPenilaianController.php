<?php

namespace App\Http\Controllers\KPI\PerformanceAppraisal\Peserta;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\KPI\PerformanceAppraisal\PaKalibrasiPenilaiException;
use App\Services\KPI\PerformanceAppraisal\PaKalibrasiPenilaiService;
use App\Services\KPI\PerformanceAppraisal\PaPesertaService;
use App\Services\KPI\PerformanceAppraisal\PaWizardPenilaianService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Wizard tugas penilaian dan Kalibrasi Penilai — sisi penilai.
 *
 * Aturan yang sama dengan PaPesertaController: TIDAK ADA identitas peserta yang diterima.
 * `{kode}` hanya memilih kategori di antara tugas milik yang login, dan `assignment_id` pada
 * kalibrasi diperiksa kepemilikannya di service; milik orang lain dijawab 404.
 */
class PaWizardPenilaianController extends Controller
{
    public function __construct(
        private readonly PaWizardPenilaianService $wizard,
        private readonly PaKalibrasiPenilaiService $kalibrasi,
        private readonly PaPesertaService $peserta,
    ) {}

    public function wizard(Request $request)
    {
        return $this->jawab(fn () => $this->wizard->wizard((int) Auth::id(), $this->cycleId($request)));
    }

    public function lembar(Request $request, string $kode)
    {
        return $this->jawab(fn () => $this->wizard->lembar((int) Auth::id(), $kode, $this->cycleId($request)));
    }

    public function pratinjau(Request $request, string $kode)
    {
        return $this->jawab(fn () => $this->wizard->pratinjau((int) Auth::id(), $kode, $this->cycleId($request)));
    }

    /**
     * Menyimpan Kalibrasi Penilai atas satu orang, lalu mengembalikan pratinjau kategorinya
     * UTUH — bukan hanya baris orang itu — karena rata-rata per kompetensi ikut berubah.
     */
    public function kalibrasi(Request $request)
    {
        $data = $request->validate([
            'assignment_id' => ['required', 'integer', 'min:1'],
            // Skor, esai, atau keduanya — satu tindakan simpan, satu alasan.
            'perubahan' => ['array', 'max:100', 'required_without:perubahan_jawaban'],
            'perubahan.*.competency_id' => ['required', 'integer', 'min:1', 'distinct'],
            'perubahan.*.skor' => ['required', 'numeric'],
            'perubahan_jawaban' => ['array', 'max:100', 'required_without:perubahan'],
            /* Kode soal boleh berulang: pada format per soal satu bagian memuat beberapa soal,
               dibedakan `id_master_soal`. Kembaran (kode, id) ditolak service. */
            'perubahan_jawaban.*.kode_soal' => ['required', 'string', 'max:100'],
            'perubahan_jawaban.*.id_master_soal' => ['nullable', 'integer', 'min:1'],
            // Kosong diperiksa service, supaya penolakannya membawa PA_JAWABAN_KOSONG.
            'perubahan_jawaban.*.jawaban' => ['present', 'nullable', 'string'],
            // Diperiksa service, bukan di sini, supaya penolakannya membawa PA_ALASAN_WAJIB.
            'alasan' => ['nullable', 'string'],
        ]);

        return $this->jawab(function () use ($data) {
            $userId = (int) Auth::id();
            $hasil = $this->kalibrasi->simpan(
                $this->peserta->karyawan($userId),
                (int) $data['assignment_id'],
                $data['perubahan'] ?? [],
                $data['alasan'] ?? null,
                $userId,
                $data['perubahan_jawaban'] ?? [],
            );

            return $hasil + ['pratinjau' => $this->wizard->pratinjau($userId, $hasil['kategori'], $hasil['cycle_id'])];
        });
    }

    private function cycleId(Request $request): ?int
    {
        $v = $request->query('cycle_id');

        return ($v === null || $v === '') ? null : (int) $v;
    }

    /**
     * Penolakan berkode dijawab dengan kodenya; penolakan aturan lain tetap 422 dengan
     * pesannya apa adanya — pola yang sama dengan PaPesertaController.
     */
    private function jawab(callable $kerja)
    {
        try {
            return ResponseHelper::success($kerja());
        } catch (PaKalibrasiPenilaiException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), $e->errorCode, $e->httpStatus);
        } catch (RuntimeException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }
    }
}
