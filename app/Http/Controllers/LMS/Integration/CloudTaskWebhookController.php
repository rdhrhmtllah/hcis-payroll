<?php

namespace App\Http\Controllers\LMS\Integration;

use App\Http\Controllers\Controller;
use App\Services\ESS\Activity\Center\UploadTaskService;
use App\Services\LMS\PdfProcessorService;
use App\Services\SYSTEM\TaskProcessing\CloudTaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CloudTaskWebhookController extends Controller
{
    public function __construct(private PdfProcessorService $pdfProcessorService) {}

    /**
     * ENDPOINT TEST MANUAL (Postman) — pre-render semua halaman tanpa Cloud Task log.
     * Dijaga bearer token yang sama. Hapus / batasi di produksi bila tidak perlu.
     *
     * POST /api/v1/tasks/lms/test-render-pdf
     * Header: Authorization: Bearer <SCHEDULER_BEARER_TOKEN>
     * Body  : { "berkas_id": 28 }
     */
    public function testRenderPdf(Request $request)
    {
        $expectedToken = config('services.project_config.SCHEDULER_BEARER_TOKEN');
        if ($request->bearerToken() !== $expectedToken) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $berkasId = (int) $request->input('berkas_id');
        if ($berkasId < 1) {
            return response()->json(['message' => 'berkas_id wajib diisi'], 400);
        }

        $berkas = DB::table('N_LMS_Master_Konten_Berkas')
            ->where('Id_Master_Konten_Berkas', $berkasId)
            ->first();

        if (! $berkas) {
            return response()->json(['message' => "Berkas ID {$berkasId} tidak ditemukan."], 404);
        }

        set_time_limit(0);
        $start = microtime(true);

        try {
            $totalPages = $this->pdfProcessorService->processPdfToImages($berkas);

            DB::table('N_LMS_Master_Konten_Berkas')
                ->where('Id_Master_Konten_Berkas', $berkasId)
                ->update([
                    'Status_Render' => 'DONE',
                    'Total_Halaman' => $totalPages,
                    'Error_Message' => null,
                    'Updated_At' => now(),
                ]);

            return response()->json([
                'success' => true,
                'berkas_id' => $berkasId,
                'pages' => $totalPages,
                'elapsed_secs' => round(microtime(true) - $start, 2),
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'elapsed_secs' => round(microtime(true) - $start, 2),
            ], 500);
        }
    }

    public function processPdfToImage(Request $request)
    {
        // 1. Validasi Keamanan (Token dari CloudTaskService kamu)
        $expectedToken = config('services.project_config.SCHEDULER_BEARER_TOKEN');
        if ($request->bearerToken() !== $expectedToken) {
            Log::warning('Unauthorized access attempt to Cloud Tasks Webhook');

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Jangan batasi waktu eksekusi, biarkan worker bekerja sampai selesai (bisa makan waktu 1-5 menit untuk PDF tebal)
        set_time_limit(0);

        $berkasId = $request->input('berkas_id');
        $logId = $request->input('_cloud_task_log_id');

        if (! $berkasId || ! $logId) {
            return response()->json(['message' => 'Missing payloads'], 400);
        }

        try {
            // Tandai task sedang jalan di N_HRIS_Cloud_Task_Log
            CloudTaskService::markProcessing($logId);

            $berkas = DB::table('N_LMS_Master_Konten_Berkas')->where('Id_Master_Konten_Berkas', $berkasId)->first();

            if (! $berkas) {
                throw new \Exception("Berkas ID {$berkasId} tidak ditemukan.");
            }

            // Tandai status di tabel Berkas menjadi PROCESSING
            DB::table('N_LMS_Master_Konten_Berkas')
                ->where('Id_Master_Konten_Berkas', $berkasId)
                ->update(['Status_Render' => 'PROCESSING']);

            // EKSEKUSI PEMROSESAN PDF
            $totalPages = $this->pdfProcessorService->processPdfToImages($berkas);

            // Jika Berhasil: Update tabel Berkas
            DB::table('N_LMS_Master_Konten_Berkas')
                ->where('Id_Master_Konten_Berkas', $berkasId)
                ->update([
                    'Status_Render' => 'DONE',
                    'Total_Halaman' => $totalPages,
                    'Error_Message' => null,
                    'Updated_At' => now(),
                ]);

            // Tutup log task menjadi COMPLETED
            CloudTaskService::markCompleted($logId, $totalPages, $totalPages, 0, ['pages_rendered' => $totalPages]);

            // Tutup task upload terkait di Pusat Aktivitas (PDF: processing → ready).
            app(UploadTaskService::class)->linkRenderResult((int) $berkasId, 'DONE');

            return response()->json(['success' => true, 'pages' => $totalPages], 200);
        } catch (\Throwable $e) {
            // Jika Gagal: Update tabel berkas & Log task
            DB::table('N_LMS_Master_Konten_Berkas')
                ->where('Id_Master_Konten_Berkas', $berkasId)
                ->update([
                    'Status_Render' => 'FAILED',
                    'Error_Message' => substr($e->getMessage(), 0, 1000),
                    'Updated_At' => now(),
                ]);

            CloudTaskService::markFailed($logId, $e->getMessage());

            // Tandai task upload terkait gagal di Pusat Aktivitas.
            app(UploadTaskService::class)->linkRenderResult((int) $berkasId, 'FAILED');

            // Kirim status 500 agar Cloud Task tahu ini gagal dan akan melakukan retry otomatis (sesuai config GCP)
            return response()->json(['message' => 'Processing failed', 'error' => $e->getMessage()], 500);
        }
    }
}
