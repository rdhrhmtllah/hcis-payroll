<?php

namespace App\Http\Controllers\ESS\Attendance\Overtime\Conversion;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\ESS\Attendance\Overtime\Conversion\KonversiLemburAdminService;
use App\Services\ESS\Attendance\Overtime\Conversion\KonversiLemburService;
use App\Services\SYSTEM\Application\Transactions\TransactionService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use InvalidArgumentException;
use Throwable;

/**
 * Layar admin HC untuk memantau dan membatalkan pengajuan Konversi Lembur.
 */
class KonversiLemburAdminController extends Controller
{
    public function __construct(
        private KonversiLemburAdminService $admin,
        private KonversiLemburService $konversi,
        private TransactionService $transaksi,
    ) {}

    public function index()
    {
        try {
            $opsi = $this->admin->opsiSaringan();
        } catch (Throwable $e) {
            // Saringan organisasi hilang lebih baik daripada seluruh halaman gagal dibuka.
            Log::error('Opsi saringan admin konversi lembur gagal dimuat', ['error' => $e->getMessage()]);
            $opsi = ['divisi' => [], 'departemen' => [], 'level' => [], 'jabatan' => [], 'lokasi' => []];
        }

        return Inertia::render('ESS/Attendance/Overtime/Conversion/Admin/KonversiLemburAdmin', [
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
            Log::error('Daftar admin konversi lembur gagal dimuat', ['error' => $e->getMessage()]);

            return ResponseHelper::errorWithCode('Daftar konversi lembur gagal dimuat.', 'ESS_KONVERSI_ADMIN_LIST_FAILED', 500);
        }
    }

    public function detail(string $noTransaksi)
    {
        try {
            $detail = $this->admin->detail($noTransaksi, $this->konversi, $this->transaksi);
            if (! $detail) {
                return ResponseHelper::errorWithCode('Pengajuan tidak ditemukan.', 'ESS_KONVERSI_ADMIN_NOT_FOUND', 404);
            }

            return ResponseHelper::success($detail);
        } catch (Throwable $e) {
            Log::error('Detail admin konversi lembur gagal dimuat', ['no_transaksi' => $noTransaksi, 'error' => $e->getMessage()]);

            return ResponseHelper::errorWithCode('Detail pengajuan gagal dimuat.', 'ESS_KONVERSI_ADMIN_DETAIL_FAILED', 500);
        }
    }

    public function batal(Request $request, string $noTransaksi)
    {
        $request->validate(['alasan' => 'required|string|max:500']);

        $adminKode = (string) ($request->user()?->karyawan?->Kode_Karyawan ?? $request->user()?->Username ?? '');
        if ($adminKode === '') {
            return ResponseHelper::errorWithCode('Akun admin tidak tertaut data karyawan.', 'ESS_KONVERSI_ADMIN_NO_ACTOR', 403);
        }

        try {
            $hasil = $this->konversi->adminCancelTransaction($noTransaksi, $adminKode, (string) $request->input('alasan'));

            return ResponseHelper::success($hasil, $hasil['message']);
        } catch (InvalidArgumentException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'ESS_KONVERSI_ADMIN_VALIDATION', 422);
        } catch (DomainException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'ESS_KONVERSI_ADMIN_CONFLICT', 409);
        } catch (Throwable $e) {
            Log::error('Pembatalan admin konversi lembur gagal', ['no_transaksi' => $noTransaksi, 'error' => $e->getMessage()]);

            return ResponseHelper::errorWithCode('Pembatalan gagal diproses.', 'ESS_KONVERSI_ADMIN_CANCEL_FAILED', 500);
        }
    }
}
