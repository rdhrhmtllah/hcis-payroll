<?php

namespace App\Http\Controllers\LMS\Integration;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\LMS\TrainingExecutionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LmsThirdPartyCallbackController extends Controller
{
    private TrainingExecutionService $executionService;

    public function __construct(TrainingExecutionService $executionService)
    {
        $this->executionService = $executionService;
    }

    public function completeExam(Request $request)
    {
        try {
            /*
             * Performance Appraisal punya pintunya sendiri sekarang —
             * PaThirdPartyCallbackController lewat /api/v1/pa/third-party. Di sini dulu
             * ada cabang yang harus diperiksa SEBELUM validasi di bawah, karena kiriman
             * PA tidak memuat `Score` dan akan ditolak oleh aturan yang tak pernah
             * ditujukan kepadanya. Cabang itu bekerja, tetapi ia menaruh aturan satu
             * modul di dalam alur modul lain: siapa pun yang kelak merapikan validasi di
             * bawah akan mematahkan PA tanpa pernah membuka satu berkas PA pun.
             */
            $validated = $request->validate([
                'Kode_Karyawan' => 'required|string',
                'Id_Penjadwalan_Integrasi' => 'required',
                'Id_Ujian_Token' => 'required|string',
                'Score' => 'required|numeric|min:0|max:100',
            ]);

            // Callback kuis SOP (endpoint HC Learn yang SAMA; token milik N_SOP) → delegasikan.
            $sop = $request->attributes->get('sop_integrasi');
            if ($sop) {
                return $this->completeSopKuis($validated, $sop);
            }

            // ─── 1. Ambil integrasi (sudah di-inject middleware LmsApiGuard) ───
            $integrasi = $request->attributes->get('lms_integrasi');

            if (! $integrasi) {
                return ResponseHelper::error('Data integrasi tidak ditemukan dalam request. Pastikan middleware LmsApiGuard aktif.', 500);
            }

            if (empty($integrasi->Id_Penjadwalan_Det)) {
                return ResponseHelper::error(
                    "Token ujian ditemukan tetapi belum terhubung ke penjadwalan detail (Id_Penjadwalan_Det kosong pada integrasi #{$integrasi->Id_Penjadwalan_Integrasi}).",
                    422
                );
            }

            // ─── 2. Trace hierarchy: Integrasi → Penjadwalan_Det → Detail → Penjadwalan ───
            $penjadwalanDet = DB::table('N_LMS_Penjadwalan_Det')
                ->where('Id_Penjadwalan_Det', $integrasi->Id_Penjadwalan_Det)
                ->first();

            if (! $penjadwalanDet) {
                return ResponseHelper::error(
                    "Data penjadwalan item (N_LMS_Penjadwalan_Det) tidak ditemukan untuk Id_Penjadwalan_Det = {$integrasi->Id_Penjadwalan_Det}. "
                    .'Kemungkinan data penjadwalan belum di-generate atau sudah dihapus.',
                    404
                );
            }

            $penjadwalanDetail = DB::table('N_LMS_Penjadwalan_Detail')
                ->where('Id_Penjadwalan_Detail', $penjadwalanDet->Id_Penjadwalan_Detail)
                ->first();

            if (! $penjadwalanDetail) {
                return ResponseHelper::error(
                    "Data penjadwalan section (N_LMS_Penjadwalan_Detail) tidak ditemukan untuk Id_Penjadwalan_Detail = {$penjadwalanDet->Id_Penjadwalan_Detail}. "
                    .'Struktur penjadwalan mungkin belum lengkap.',
                    404
                );
            }

            $penjadwalan = DB::table('N_LMS_Penjadwalan')
                ->where('Id_Penjadwalan', $penjadwalanDetail->Id_Penjadwalan)
                ->first();

            if (! $penjadwalan) {
                return ResponseHelper::error(
                    "Data penjadwalan header (N_LMS_Penjadwalan) tidak ditemukan untuk Id_Penjadwalan = {$penjadwalanDetail->Id_Penjadwalan}.",
                    404
                );
            }

            // ─── 3. Resolve user dari penjadwalan → Karyawan → Auth context ───
            if (empty($penjadwalan->Kode_Karyawan)) {
                return ResponseHelper::error(
                    "Penjadwalan #{$penjadwalan->Id_Penjadwalan} tidak memiliki Kode_Karyawan. Data penjadwalan tidak valid.",
                    422
                );
            }

            $karyawan = DB::table('Karyawan')
                ->where('Kode_Karyawan', $penjadwalan->Kode_Karyawan)
                ->first();

            if (! $karyawan) {
                return ResponseHelper::error(
                    "Data karyawan tidak ditemukan untuk Kode_Karyawan = '{$penjadwalan->Kode_Karyawan}'.",
                    404
                );
            }

            if (empty($karyawan->UserID_Web)) {
                return ResponseHelper::error(
                    "Karyawan '{$penjadwalan->Kode_Karyawan}' belum memiliki akun user (UserID_Web kosong). Hubungi admin HRIS.",
                    422
                );
            }

            // Middleware sudah memvalidasi kepemilikan token dan mengikat user ke request.
            // Fallback login ini dipertahankan agar service tetap aman bila guard aktif tanpa session.
            if (! Auth::check()) {
                Auth::loginUsingId($karyawan->UserID_Web);
            }

            // ─── 4. Trace materi detail ───
            if (empty($penjadwalanDet->Id_Materi_Det)) {
                return ResponseHelper::error(
                    "Penjadwalan item #{$penjadwalanDet->Id_Penjadwalan_Det} belum terhubung ke materi (Id_Materi_Det kosong).",
                    422
                );
            }

            $materiDet = DB::table('N_LMS_Materi_Det')
                ->where('Id_Materi_Det', $penjadwalanDet->Id_Materi_Det)
                ->first();

            if (! $materiDet) {
                return ResponseHelper::error(
                    "Data materi item (N_LMS_Materi_Det) tidak ditemukan untuk Id_Materi_Det = {$penjadwalanDet->Id_Materi_Det}. "
                    .'Materi mungkin sudah dihapus atau dinonaktifkan.',
                    404
                );
            }

            // ─── 5. Eksekusi: service otomatis ensure semua tabel transaksi ───
            // TrainingExecutionService::completeActivity() sudah memiliki pola ensure*
            // yang akan auto-create transaksi Header, Detail, Det, dan Progress
            // jika belum ada, sehingga aman meskipun user belum pernah membuka training.
            $result = $this->executionService->completeActivity([
                'Id_Penjadwalan' => $penjadwalan->Id_Penjadwalan,
                'Id_Penjadwalan_Detail' => $penjadwalanDetail->Id_Penjadwalan_Detail,
                'Id_Penjadwalan_Det' => $penjadwalanDet->Id_Penjadwalan_Det,
                'Id_Materi' => $materiDet->Id_Materi,
                'Id_Materi_Det' => $penjadwalanDet->Id_Materi_Det,
                'Flag_Third_Party' => 'Y',
                'Score' => $validated['Score'],
                'Status_Progress' => 'SELESAI',
                'MarkStarted' => true,
                'MarkCompleted' => true,
            ]);

            Log::info('LMS_THIRD_PARTY_EXAM_COMPLETED', [
                'correlation_id' => $request->attributes->get('api_correlation_id'),
                'token_hash' => hash('sha256', (string) $validated['Id_Ujian_Token']),
                'Id_Penjadwalan_Integrasi' => $validated['Id_Penjadwalan_Integrasi'],
                'Score' => $validated['Score'],
                'Kode_Karyawan' => $penjadwalan->Kode_Karyawan,
                'Id_Penjadwalan' => $penjadwalan->Id_Penjadwalan,
                'result' => $result,
            ]);

            return ResponseHelper::success($result, 'Exam completion recorded successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            // ResponseHelper::error() menerima string. Mengirim $e->errors()
            // (array) melempar TypeError, lalu LmsApiGuard mengubah validasi 422
            // menjadi 500 — persis yang terlihat saat callback bertanda tangan
            // lolos gerbang tetapi Score tidak dikirim.
            return ResponseHelper::error($this->ringkasGalatValidasi($e), 422);
        } catch (\Throwable $e) {
            $correlationId = $request->attributes->get('api_correlation_id');
            Log::error('LMS_THIRD_PARTY_CALLBACK_ERROR', [
                'correlation_id' => $correlationId,
                'exception' => $e::class,
                'code' => (string) $e->getCode(),
            ]);

            // Jangan kembalikan detail exception/SQL ke pihak luar. Evidence log
            // menyimpan status + ringkasan respons; correlation ID cukup untuk
            // menemukan rincian server-side tanpa membocorkan struktur internal.
            return ResponseHelper::error(
                'Gagal menyimpan hasil ujian pihak ke-3. Referensi: '.($correlationId ?: '-'),
                500,
            );
        }
    }

    private function ringkasGalatValidasi(\Illuminate\Validation\ValidationException $e): string
    {
        $pesan = collect($e->errors())->flatten()->filter()->map(fn ($x) => (string) $x)->values();

        return $pesan->isEmpty() ? 'Payload callback tidak valid.' : $pesan->implode(' ');
    }

    /**
     * Callback hasil ujian untuk item KUIS milik modul SOP.
     * HC Learn memanggil endpoint yang SAMA dengan LMS; token ($sop) sudah
     * di-resolve & di-inject oleh LmsApiGuard dari N_SOP_Penjadwalan_Integrasi.
     */
    /**
     * Callback kuis SOP yang masuk lewat URL callback LMS.
     * Resolusi & penulisannya didelegasikan ke SopKuisCallbackService — otak yang
     * SAMA dengan endpoint SOP khusus, supaya satu hasil ujian tak diperlakukan
     * berbeda tergantung pintu masuknya. $sop dari LmsApiGuard dipakai sebagai
     * penanda "token ini milik SOP"; pencariannya sendiri diulang di service
     * agar aturannya satu tempat.
     */
    private function completeSopKuis(array $validated, $sop)
    {
        $r = app(\App\Services\LMS\SOP\SopKuisCallbackService::class)->terima(
            (string) $validated['Kode_Karyawan'],
            (string) $validated['Id_Ujian_Token'],
            $validated['Id_Penjadwalan_Integrasi'] ?? null,
            (float) $validated['Score'],
        );

        return $r['ok']
            ? ResponseHelper::success(['result' => $r['result']], $r['message'])
            : ResponseHelper::error($r['message'], $r['code']);
    }

}
