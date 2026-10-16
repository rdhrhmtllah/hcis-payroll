<?php

namespace App\Http\Controllers\KPI\PerformanceAppraisal\Peserta;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\KPI\PerformanceAppraisal\PaSimulasiPengisianService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Lembar penilaian simulasi — pengganti sementara HC Learn.
 *
 * TIDAK ADA SATU METHOD DI SINI YANG MENERIMA IDENTITAS PENILAI. Lingkupnya selalu
 * `Auth::id()`; tokennya hanya menunjuk penugasan mana, bukan siapa yang mengisinya.
 * Kepemilikan diperiksa di service terhadap `Kode_Karyawan_Penilai`, dan itu bukan
 * kehati-hatian berlebihan — token yang dipercaya begitu saja berarti siapa pun yang
 * melihat satu token bisa menilai atas nama orang lain.
 *
 * Seluruh aturannya di service, sesuai pola modul ini. Menyalin sepotong pun ke sini
 * menciptakan pintu kedua yang harus dijaga sama persis.
 */
class PaSimulasiPengisianController extends Controller
{
    public function __construct(
        private readonly PaSimulasiPengisianService $pengisian,
    ) {}

    public function index(string $token)
    {
        return inertia('KPI/PerformanceAppraisal/Peserta/nilai', ['token' => $token]);
    }

    public function lembar(Request $request, string $token)
    {
        return $this->jawab(fn () => $this->pengisian->lembar($token, (int) Auth::id()));
    }

    public function kirim(Request $request, string $token)
    {
        /* Jawabannya dikirim sebagai peta `kode soal => isi`. Isinya boleh angka maupun teks;
           yang menentukan bentuknya adalah paket soalnya, bukan yang dikirim layar — sehingga
           validasi tipe di sini justru akan menolak kiriman yang sah. Yang ditegakkan di sini
           hanya bentuk luarnya. */
        $data = $request->validate([
            'jawaban' => ['required', 'array', 'min:1'],
        ]);

        return $this->jawab(fn () => $this->pengisian->kirim(
            $token,
            (int) Auth::id(),
            $data['jawaban'],
        ));
    }

    /**
     * Pesan RuntimeException diteruskan apa adanya sebagai 422 — pola yang sama dengan
     * controller peserta dan admin. Pesannya ditulis untuk dibaca manusia.
     */
    private function jawab(callable $kerja)
    {
        try {
            return ResponseHelper::success($kerja());
        } catch (RuntimeException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }
    }
}
