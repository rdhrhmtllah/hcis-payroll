<?php

namespace App\Http\Controllers\LMS\SOP\Integration;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\LMS\SOP\SopKuisCallbackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * SOP — Callback hasil kuis HC Learn (inbound).
 * POST /api/v1/hris/third-party/sop/exam/complete (dijaga hris.api.guard).
 * Body: { Kode_Karyawan, Id_Penjadwalan_Integrasi, Id_Ujian_Token, Score }.
 * Resolusi via token → tulis Skor/Status_Lulus/percobaan ke progress VERSI token,
 * bila lulus → SELESAI + recompute + ledger.
 */
class SopKuisCallbackController extends Controller
{
    public function __construct(private SopKuisCallbackService $callback) {}

    public function completeExam(Request $request)
    {
        try {
            $v = $request->validate([
                'Kode_Karyawan' => 'required|string',
                'Id_Penjadwalan_Integrasi' => 'required',
                'Id_Ujian_Token' => 'required|string',
                'Score' => 'required|numeric|min:0|max:100',
            ]);
            // Aturan resolusi & penulisan hidup di service — dipakai bersama pintu
            // callback LMS, supaya hasil yang sama tak diperlakukan berbeda.
            $r = $this->callback->terima(
                (string) $v['Kode_Karyawan'],
                (string) $v['Id_Ujian_Token'],
                $v['Id_Penjadwalan_Integrasi'] ?? null,
                (float) $v['Score'],
            );

            return $r['ok']
                ? ResponseHelper::success(['result' => $r['result']], $r['message'])
                : ResponseHelper::error($r['message'], $r['code']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // ResponseHelper::error() menerima string; $e->errors() adalah
            // array. Tanpa flatten, validasi yang semestinya 422 berubah menjadi
            // TypeError/500. Pintu LMS gabungan dan pintu SOP khusus harus punya
            // perilaku yang sama.
            $pesan = collect($e->errors())->flatten()->filter()->map(fn ($x) => (string) $x)->values();

            return ResponseHelper::error(
                $pesan->isEmpty() ? 'Payload callback tidak valid.' : $pesan->implode(' '),
                422,
            );
        } catch (\Throwable $e) {
            /* Token SOP_KUIS_CALLBACK_ERROR DIPERTAHANKAN apa adanya: ini
               callback integrasi pihak ketiga, dan token sebaris begini lazim
               menjadi kunci alert/saringan log di luar repo ini. Menggantinya
               dengan kalimat yang lebih enak dibaca akan mematikan alert yang
               tak terlihat dari sini. Stack trace ditambahkan lewat konteks,
               bukan dengan menyusun ulang pesannya. */
            Log::error('SOP_KUIS_CALLBACK_ERROR', ['exception' => $e]);

            return ResponseHelper::error('Gagal menyimpan hasil ujian: '.$e->getMessage(), 500);
        }
    }
}
