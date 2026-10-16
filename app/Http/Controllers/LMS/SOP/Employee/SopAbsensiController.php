<?php

namespace App\Http\Controllers\LMS\SOP\Employee;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\LMS\SOP\SopAbsensiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

/**
 * SOP — Presensi kehadiran (karyawan).
 *
 * Gerbang sebelum paket ber-`Flag_Absensi` boleh dikerjakan. Penegakannya ada di
 * service (SopCompletionService & SopKuisDispatchService memanggil
 * SopAbsensiService::boleh()); controller ini hanya pintu masuk UI.
 */
class SopAbsensiController extends Controller
{
    public function __construct(private SopAbsensiService $svc) {}

    private function kodeKaryawan(): ?string
    {
        return optional(Auth::user()->karyawan)->Kode_Karyawan;
    }

    private function decode(string $id): ?int
    {
        $d = Hashids::connection('custom')->decode($id);

        return $d[0] ?? null;
    }

    /** GET /api/v1/sop/{id}/absensi — wajib atau tidak, sudah atau belum. */
    public function status(string $id)
    {
        $kode = $this->kodeKaryawan();
        if (! $kode) {
            return ResponseHelper::error('Akun tidak tertaut data karyawan', 403);
        }

        $idPaket = $this->decode($id);
        if (! $idPaket) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        try {
            $s = $this->svc->status($kode, $idPaket);
            // Foto hanya dikirim sebagai URL bertanda tangan; path GCS mentah
            // tak pernah keluar ke klien.
            if ($s['absensi']) {
                $s['absensi']->Foto_Url = $this->svc->fotoUrl($s['absensi']->File_Path);
                unset($s['absensi']->File_Path);
            }

            return ResponseHelper::success($s, 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP absensi status gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** POST /api/v1/sop/{id}/absensi/checkin */
    public function checkin(Request $request, string $id)
    {
        $kode = $this->kodeKaryawan();
        if (! $kode) {
            return ResponseHelper::error('Akun tidak tertaut data karyawan', 403);
        }

        $idPaket = $this->decode($id);
        if (! $idPaket) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        $v = $request->validate([
            // ~2 MB base64 sudah lebih dari cukup untuk foto 640×480 hasil kompresi
            'foto' => 'required|string|max:2800000',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'akurasi' => 'nullable|numeric|min:0',
        ]);

        try {
            $r = $this->svc->checkin(
                $kode,
                $idPaket,
                $v['foto'],
                isset($v['lat']) ? (float) $v['lat'] : null,
                isset($v['lng']) ? (float) $v['lng'] : null,
                isset($v['akurasi']) ? (float) $v['akurasi'] : null,
            );

            if (! $r['ok']) {
                return ResponseHelper::error($r['message'], 422);
            }

            if ($r['absensi']) {
                $r['absensi']->Foto_Url = $this->svc->fotoUrl($r['absensi']->File_Path);
                unset($r['absensi']->File_Path);
            }

            // Sesi ikut dikembalikan supaya kartu langsung berubah jadi
            // "sesi berjalan" tanpa perlu satu permintaan status tambahan.
            return ResponseHelper::success([
                'absensi' => $r['absensi'],
                'sesi' => $this->svc->status($kode, $idPaket)['sesi'],
            ], $r['message']);
        } catch (\Exception $e) {
            Log::error('SOP absensi checkin gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }
}
