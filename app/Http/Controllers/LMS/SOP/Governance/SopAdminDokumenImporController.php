<?php

namespace App\Http\Controllers\LMS\SOP\Governance;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\LMS\SOP\Import\SopImporRegisterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Impor register dokumen (Excel) ke Master Dokumen.
 *
 * Satu proses, dua pintu:
 *   - WEB  POST /api/v1/sop/admin/dokumen/impor-register — modal "Impor Excel",
 *     sesi login + CSRF, pelaku = user yang login (grup menuSopDokumenPage).
 *   - API  POST /api/v1/sop/dokumen/impor-register — Authorization: Bearer,
 *     tanpa login, pelaku = HCIS_SOP_IMPOR_REGISTER_USER_ID.
 * Keduanya memanggil proses() yang sama, jadi aturannya tak bisa menyimpang.
 *
 * Dua mode. `dry_run` hanya menyusun rencana; `jalankan` menyusun ulang rencana
 * yang SAMA dari berkas yang dikirim ulang lalu menulis baris yang siap. Server
 * tak menyimpan hasil dry run.
 */
class SopAdminDokumenImporController extends Controller
{
    public function __construct(private SopImporRegisterService $impor) {}

    /** Pintu web (sesi). */
    public function imporRegister(Request $request)
    {
        return $this->proses($request, (int) Auth::user()->Id_Users);
    }

    /** Pintu API (Bearer, lewat middleware sop.impor-register.auth). */
    public function imporRegisterApi(Request $request)
    {
        $userId = (int) config('services.hcis.sop_impor_register.user_id');

        /* Gagal-tertutup juga untuk pelaku: impor tanpa pelaku yang nyata
           menghasilkan dokumen dan jejak yang tak bisa ditanyakan asalnya. */
        if ($userId <= 0 || ! DB::table('KPI_Users')->where('Id_Users', $userId)->exists()) {
            return ResponseHelper::error(
                'HCIS_SOP_IMPOR_REGISTER_USER_ID belum diisi atau tidak menunjuk user yang ada.', 503);
        }

        return $this->proses($request, $userId);
    }

    private function proses(Request $request, int $userId)
    {
        $data = $request->validate([
            'berkas' => 'required|file|mimes:xlsx|max:10240',
            'mode' => 'required|string|in:dry_run,jalankan',
            // Jendela baris untuk mode jalankan; lihat SopImporRegisterService::jalankan().
            'mulai' => 'nullable|integer|min:0',
            'jumlah' => 'nullable|integer|min:1|max:50',
        ], [
            'berkas.mimes' => 'Berkas harus Excel .xlsx.',
        ]);

        $file = $data['berkas'];

        try {
            if ($data['mode'] === 'dry_run') {
                return ResponseHelper::success(
                    $this->impor->dryRun($file->getRealPath()),
                    'Dry run selesai — belum ada yang ditulis.',
                );
            }

            /* Pemanggil mengirim jendela kecil (lihat jalankan()), jadi satu
               permintaan hanya beberapa dokumen. Batas dibuka tetap, untuk
               pemanggil yang memilih jendela besar; ini permintaan web yang
               prosesnya mati bersama permintaan, bukan worker berumur panjang. */
            set_time_limit(0);

            return ResponseHelper::success(
                $this->impor->jalankan(
                    $file->getRealPath(), $file->getClientOriginalName(), $userId,
                    (int) ($data['mulai'] ?? 0),
                    isset($data['jumlah']) ? (int) $data['jumlah'] : null,
                ),
                'Batch impor selesai.',
            );
        } catch (\DomainException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('SOP impor register gagal', ['exception' => $e, 'mode' => $data['mode']]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }
}
