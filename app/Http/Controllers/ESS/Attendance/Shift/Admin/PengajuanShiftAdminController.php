<?php

namespace App\Http\Controllers\ESS\Attendance\Shift\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\ESS\Attendance\Shift\Admin\PengajuanShiftAdminService;
use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use InvalidArgumentException;
use Throwable;

/**
 * Layar admin HC untuk memantau dan membatalkan seluruh pengajuan perubahan
 * jadwal: Ganti Shift (karyawan/leader/admin), Tukar Hari, dan Status Hari.
 */
class PengajuanShiftAdminController extends Controller
{
    public function __construct(
        private PengajuanShiftAdminService $admin,
        private ShiftRequestService $shift,
    ) {}

    public function index()
    {
        try {
            $opsi = $this->admin->opsiSaringan();
        } catch (Throwable $e) {
            // Saringan organisasi hilang lebih baik daripada seluruh halaman gagal dibuka.
            Log::error('Opsi saringan admin pengajuan shift gagal dimuat', ['error' => $e->getMessage()]);
            $opsi = ['divisi' => [], 'departemen' => [], 'level' => [], 'jabatan' => [], 'lokasi' => []];
        }

        return Inertia::render('ESS/Attendance/Shift/Admin/PengajuanShiftAdmin', [
            'opsiSaringan' => $opsi,
        ]);
    }

    public function data(Request $request)
    {
        try {
            return ResponseHelper::success([
                'list' => $this->admin->list($request->all()),
                'stats' => $this->admin->stats($request->all()),
            ]);
        } catch (Throwable $e) {
            Log::error('Daftar admin pengajuan shift gagal dimuat', ['error' => $e->getMessage()]);

            return ResponseHelper::errorWithCode('Daftar pengajuan shift gagal dimuat.', 'ESS_PENGAJUAN_SHIFT_ADMIN_LIST_FAILED', 500);
        }
    }

    public function detail(string $noTransaksi, int $idDetail)
    {
        try {
            $detail = $this->admin->detail($noTransaksi, $idDetail, $this->shift);
            if (! $detail) {
                return ResponseHelper::errorWithCode('Pengajuan tidak ditemukan.', 'ESS_PENGAJUAN_SHIFT_ADMIN_NOT_FOUND', 404);
            }

            return ResponseHelper::success($detail);
        } catch (Throwable $e) {
            Log::error('Detail admin pengajuan shift gagal dimuat', ['no_transaksi' => $noTransaksi, 'error' => $e->getMessage()]);

            return ResponseHelper::errorWithCode('Detail pengajuan gagal dimuat.', 'ESS_PENGAJUAN_SHIFT_ADMIN_DETAIL_FAILED', 500);
        }
    }

    public function batal(Request $request, string $noTransaksi, int $idDetail)
    {
        $request->validate(['alasan' => 'required|string|max:500']);

        try {
            // Updated_By di kedua header menyimpan Id_Users, sama dengan alur yang ada.
            $hasil = $this->admin->batal($noTransaksi, $idDetail, (string) $request->user()->getAuthIdentifier(), (string) $request->input('alasan'));

            return ResponseHelper::success($hasil, $hasil['message']);
        } catch (InvalidArgumentException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'ESS_PENGAJUAN_SHIFT_ADMIN_VALIDATION', 422);
        } catch (DomainException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'ESS_PENGAJUAN_SHIFT_ADMIN_CONFLICT', 409);
        } catch (Throwable $e) {
            Log::error('Pembatalan admin pengajuan shift gagal', ['no_transaksi' => $noTransaksi, 'error' => $e->getMessage()]);

            return ResponseHelper::errorWithCode('Pembatalan gagal diproses.', 'ESS_PENGAJUAN_SHIFT_ADMIN_CANCEL_FAILED', 500);
        }
    }
}
