<?php

namespace App\Http\Controllers\ESS\Attendance\Shift\Schedule;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\ESS\Attendance\Shift\Schedule\JejakJadwalHariService;
use App\Services\SYSTEM\Governance\Authorization\Shared\AccessContext;
use App\Services\SYSTEM\Governance\Authorization\Shared\FunctionalRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Riwayat jadwal per tanggal — satu panel, beberapa pintu dengan batas akses
 * masing-masing:
 *  - pribadi: hanya diri sendiri (Self Shift);
 *  - tim: anggota tim leader, atau cakupan admin (Leader/Admin Shift);
 *  - admin: siapa pun (Pengajuan Shift Admin).
 */
class JejakJadwalHariController extends Controller
{
    public function __construct(private JejakJadwalHariService $jejak) {}

    public function pribadi(Request $request): JsonResponse
    {
        $data = $request->validate(['tanggal' => 'required|date_format:Y-m-d']);
        $kode = (string) ($request->user()?->karyawan?->Kode_Karyawan ?? '');
        if ($kode === '') {
            return ResponseHelper::errorWithCode('Akun ini tidak tertaut data karyawan.', 'ESS_JEJAK_JADWAL_NO_EMPLOYEE', 403);
        }

        return $this->jawab($kode, $data['tanggal']);
    }

    public function tim(Request $request, FunctionalRole $role): JsonResponse
    {
        $data = $request->validate([
            'kode' => 'required|string|max:20',
            'tanggal' => 'required|date_format:Y-m-d',
        ]);
        $leader = (string) ($request->user()?->karyawan?->Kode_Karyawan ?? '');

        if ($leader === '' || ! $this->jejak->bolehDilihatLeader($leader, $data['kode'], $role, app(AccessContext::class))) {
            return ResponseHelper::errorWithCode('Karyawan ini bukan anggota tim Anda.', 'ESS_JEJAK_JADWAL_FORBIDDEN', 403);
        }

        return $this->jawab($data['kode'], $data['tanggal']);
    }

    public function admin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kode' => 'required|string|max:20',
            'tanggal' => 'required|date_format:Y-m-d',
        ]);

        return $this->jawab($data['kode'], $data['tanggal']);
    }

    private function jawab(string $kode, string $tanggal): JsonResponse
    {
        try {
            $hasil = $this->jejak->susun($kode, $tanggal);
            if (! $hasil) {
                return ResponseHelper::errorWithCode('Karyawan tidak ditemukan.', 'ESS_JEJAK_JADWAL_NOT_FOUND', 404);
            }

            return ResponseHelper::success($hasil);
        } catch (Throwable $e) {
            Log::error('Riwayat jadwal hari gagal disusun', ['kode' => $kode, 'tanggal' => $tanggal, 'error' => $e->getMessage()]);

            return ResponseHelper::errorWithCode('Riwayat jadwal gagal dimuat.', 'ESS_JEJAK_JADWAL_FAILED', 500);
        }
    }
}
