<?php

namespace App\Http\Controllers\KPI\PerformanceAppraisal\Leader;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\KPI\PerformanceAppraisal\PaLeaderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Sisi LEADER — memantau tim sendiri.
 *
 * `{participantId}` MEMANG ADA di sini, berbeda dari sisi peserta. Itu perbedaan yang
 * disengaja dan karenanya justru perlu dijaga lebih ketat: satu-satunya hal yang
 * memisahkan "melihat anggota saya" dari "melihat siapa pun dengan mengganti angka di
 * alamat" adalah pemeriksaan keanggotaan di `PaLeaderService::detailAnggota()`.
 *
 * Pemeriksaan itu ada di SERVICE, bukan di sini. Menaruhnya di controller berarti jalur
 * lain yang memanggil service yang sama — perintah artisan, ekspor, pekerjaan antrean —
 * melewatinya tanpa ada yang menyadari.
 *
 * Identitas leader tidak pernah diterima dari permintaan. Ia selalu `Auth::id()`.
 */
class PaLeaderController extends Controller
{
    public function __construct(
        private readonly PaLeaderService $leader,
    ) {}

    public function index()
    {
        return inertia('KPI/PerformanceAppraisal/Leader/tim');
    }

    public function tim(Request $request)
    {
        $data = $request->validate([
            'cycle_id' => ['nullable', 'integer'],
        ]);

        return $this->jawab(fn () => $this->leader->tim(
            (int) Auth::id(),
            isset($data['cycle_id']) ? (int) $data['cycle_id'] : null,
        ));
    }

    public function detailAnggota(int $participantId)
    {
        return $this->jawab(fn () => $this->leader->detailAnggota((int) Auth::id(), $participantId));
    }

    /**
     * Pesan `RuntimeException` diteruskan apa adanya sebagai 422, sama seperti jalur
     * peserta dan admin. Termasuk penolakan "bukan anggota tim Anda": menyamarkannya
     * sebagai 404 tidak menambah keamanan apa pun — penyerang yang menebak id tetap
     * membedakan keduanya lewat waktu tanggap — sementara leader yang sah kehilangan
     * satu-satunya keterangan yang menjelaskan kenapa layarnya kosong.
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
