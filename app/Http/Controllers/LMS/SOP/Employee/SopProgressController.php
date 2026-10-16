<?php

namespace App\Http\Controllers\LMS\SOP\Employee;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\LMS\SOP\SopCompletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

/**
 * SOP — Aksi progress karyawan (buka / konfirmasi item).
 * Dipanggil dari Reader / Detail / callback item. IDOR dijaga di service
 * (resolusi lewat penugasan aktif milik karyawan itu sendiri).
 */
class SopProgressController extends Controller
{
    public function __construct(private SopCompletionService $svc) {}

    private function kodeKaryawan(): ?string
    {
        return optional(Auth::user()->karyawan)->Kode_Karyawan;
    }

    private function decodeItem(Request $request): ?int
    {
        $val = $request->input('id_item', '');
        if (empty($val) || $val === 'undefined' || $val === 'null') {
            return null;
        }
        $d = Hashids::connection('custom')->decode($val);
        if (! empty($d)) {
            return (int) $d[0];
        }
        $d2 = Hashids::decode($val);
        if (! empty($d2)) {
            return (int) $d2[0];
        }
        if (is_numeric($val) && (int) $val > 0) {
            return (int) $val;
        }

        return null;
    }

    private function terjemahkan(string $code): array
    {
        return match ($code) {
            'ITEM_NOT_FOUND' => ['Item tidak ditemukan', 404],
            'NOT_ASSIGNED' => ['dokumen ini belum ditugaskan kepada Anda', 403],
            'TERKUNCI' => ['Selesaikan dulu item wajib sebelumnya (dokumen ini berurutan).', 423],
            'BELUM_ABSEN' => ['Lakukan presensi kehadiran dulu sebelum mengerjakan dokumen ini.', 423],
            default => ['OK', 200],
        };
    }

    /** POST /api/v1/sop/progress/buka  body: { id_item } */
    public function bukaItem(Request $request)
    {
        $kode = $this->kodeKaryawan();
        if (! $kode) {
            return ResponseHelper::error('Akun tidak tertaut data karyawan', 403);
        }

        $idItem = $this->decodeItem($request);
        if (! $idItem) {
            return ResponseHelper::error('ID item tidak valid', 400);
        }

        try {
            $res = $this->svc->buka($kode, $idItem);
            if ($res !== 'OK') {
                [$msg, $st] = $this->terjemahkan($res);

                return ResponseHelper::error($msg, $st);
            }

            return ResponseHelper::success(null, 'Item dibuka');
        } catch (\Exception $e) {
            Log::error('SOP progress bukaItem gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** POST /api/v1/sop/progress/konfirmasi  body: { id_item, persen? } */
    public function konfirmasiItem(Request $request)
    {
        $kode = $this->kodeKaryawan();
        if (! $kode) {
            return ResponseHelper::error('Akun tidak tertaut data karyawan', 403);
        }

        $idItem = $this->decodeItem($request);
        if (! $idItem) {
            return ResponseHelper::error('ID item tidak valid', 400);
        }

        $persen = $request->filled('persen') ? (float) $request->input('persen') : 100;

        try {
            $res = $this->svc->konfirmasi($kode, $idItem, $persen, $request->ip(), (string) $request->userAgent());
            if ($res !== 'OK') {
                [$msg, $st] = $this->terjemahkan($res);

                return ResponseHelper::error($msg, $st);
            }

            return ResponseHelper::success(null, 'Item dikonfirmasi selesai');
        } catch (\Exception $e) {
            Log::error('SOP progress konfirmasiItem gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** POST /api/v1/sop/progress/halaman  body: { id_item, halaman } — penanda resume reader. */
    public function simpanHalaman(Request $request)
    {
        $kode = $this->kodeKaryawan();
        if (! $kode) {
            return ResponseHelper::error('Akun tidak tertaut data karyawan', 403);
        }

        $idItem = $this->decodeItem($request);
        if (! $idItem) {
            return ResponseHelper::error('ID item tidak valid', 400);
        }

        $halaman = (int) $request->input('halaman', 0);
        if ($halaman < 1) {
            return ResponseHelper::error('Nomor halaman tidak valid', 422);
        }

        try {
            $res = $this->svc->simpanHalaman($kode, $idItem, $halaman, (int) $request->input('durasi_detik', 0));
            if ($res !== 'OK') {
                [$msg, $st] = $this->terjemahkan($res);

                return ResponseHelper::error($msg, $st);
            }

            return ResponseHelper::success(null);
        } catch (\Exception $e) {
            Log::error('SOP progress simpanHalaman gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }
}
