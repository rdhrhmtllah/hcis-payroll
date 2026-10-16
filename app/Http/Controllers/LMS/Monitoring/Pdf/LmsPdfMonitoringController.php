<?php

namespace App\Http\Controllers\LMS\Monitoring\Pdf;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\SYSTEM\TaskProcessing\CloudTaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Monitoring & migrasi render PDF (secure viewer) untuk Administrator LMS.
 * - Ringkasan global per status render
 * - Daftar dokumen PDF + status
 * - Detail per dokumen (cek halaman ter-render di storage, on-demand)
 * - Aksi: mulai migrasi massal, retry per dokumen
 */
class LmsPdfMonitoringController extends Controller
{
    private const RENDER_VERSION = 'v4';

    public function index()
    {
        return inertia('LMS/Training/Monitoring/Pdf/pdfMonitoring');
    }

    /**
     * Ringkasan global: jumlah dokumen PDF per Status_Render.
     */
    public function getSummary()
    {
        $rows = $this->basePdfQuery()
            ->select('a.Status_Render', DB::raw('COUNT(*) as total'))
            ->groupBy('a.Status_Render')
            ->pluck('total', 'Status_Render');

        $done = (int) ($rows['DONE'] ?? 0);
        $processing = (int) ($rows['PROCESSING'] ?? 0);
        $pending = (int) ($rows['PENDING'] ?? 0);
        $failed = (int) ($rows['FAILED'] ?? 0);
        $total = $done + $processing + $pending + $failed;

        return ResponseHelper::success([
            'total' => $total,
            'done' => $done,
            'processing' => $processing,
            'pending' => $pending,
            'failed' => $failed,
            'percent' => $total > 0 ? round(($done / $total) * 100, 1) : 0,
            // Sedang benar-benar diproses worker.
            'is_processing' => $processing > 0,
            // Masih ada antrian/belum selesai (untuk memutuskan auto-refresh).
            'in_progress' => ($processing + $pending) > 0,
        ], 'Ringkasan status render PDF');
    }

    /**
     * Daftar dokumen PDF dengan status. Filter opsional via ?status= & ?search=.
     */
    public function getList(Request $request)
    {
        $status = strtoupper((string) $request->query('status', ''));
        $search = trim((string) $request->query('search', ''));
        $limit = min(100, max(10, (int) $request->query('limit', 25)));

        $query = $this->basePdfQuery()
            ->select(
                'a.Id_Master_Konten_Berkas',
                'a.Id_Master_Konten',
                'a.Type_File',
                'a.Status_Render',
                'a.Total_Halaman',
                'a.Error_Message',
                'a.Updated_At',
                'b.Judul_Konten',
            );

        if (in_array($status, ['DONE', 'PROCESSING', 'PENDING', 'FAILED'], true)) {
            $query->where('a.Status_Render', $status);
        }

        if ($search !== '') {
            $query->where('b.Judul_Konten', 'like', '%'.$search.'%');
        }

        $paginator = $query->orderByDesc('a.Updated_At')->paginate($limit);

        $items = collect($paginator->items())->map(function ($row) {
            return [
                'id' => Hashids::connection('custom')->encode($row->Id_Master_Konten_Berkas),
                'berkas_id' => (int) $row->Id_Master_Konten_Berkas,
                'judul' => $row->Judul_Konten ?? ('Konten #'.$row->Id_Master_Konten),
                'status' => $row->Status_Render ?? 'UNKNOWN',
                'total_halaman' => (int) ($row->Total_Halaman ?? 0),
                'error_message' => $row->Error_Message,
                'updated_at' => $row->Updated_At,
            ];
        });

        return ResponseHelper::successWithPaginationV2(
            $items,
            $paginator->currentPage(),
            $paginator->perPage(),
            $paginator->total(),
            'Daftar dokumen PDF',
        );
    }

    /**
     * Detail per dokumen: cek halaman mana yang sudah ter-render di storage.
     * Progress per halaman dihitung on-demand dari storage (bukan dari DB).
     */
    public function getDetail(string $berkasId)
    {
        $realId = Hashids::connection('custom')->decode($berkasId)[0] ?? (int) $berkasId;

        $berkas = DB::table('N_LMS_Master_Konten_Berkas')
            ->where('Id_Master_Konten_Berkas', $realId)
            ->first();

        if (! $berkas) {
            return ResponseHelper::error('Dokumen tidak ditemukan', 404);
        }

        $totalPages = (int) ($berkas->Total_Halaman ?? 0);
        $disk = Storage::disk($this->storageDiskName());

        $pages = [];
        $renderedCnt = 0;

        for ($p = 1; $p <= $totalPages; $p++) {
            $path = $this->pageStoragePath($realId, $p);
            $rendered = $disk->exists($path);
            if ($rendered) {
                $renderedCnt++;
            }
            $pages[] = ['page' => $p, 'rendered' => $rendered];
        }

        return ResponseHelper::success([
            'berkas_id' => $realId,
            'status' => $berkas->Status_Render ?? 'UNKNOWN',
            'total_halaman' => $totalPages,
            'halaman_selesai' => $renderedCnt,
            'percent' => $totalPages > 0 ? round(($renderedCnt / $totalPages) * 100, 1) : 0,
            'error_message' => $berkas->Error_Message,
            'render_version' => self::RENDER_VERSION,
            'pages' => $pages,
        ], 'Detail render dokumen');
    }

    /**
     * Mulai migrasi massal: set semua dokumen PDF lama (DONE/FAILED, atau yang
     * dipilih) ke PENDING lalu dispatch ke Cloud Tasks.
     * Body: { "scope": "pending" | "failed" | "all_done" | "all_unrendered" | "berkas_ids", "berkas_ids": [..] }
     */
    public function startMigration(Request $request)
    {
        $scope = (string) $request->input('scope', 'pending');
        $berkasIds = (array) $request->input('berkas_ids', []);

        $query = $this->basePdfQuery()->select('a.Id_Master_Konten_Berkas', 'a.Id_Master_Konten');

        if ($scope === 'berkas_ids' && ! empty($berkasIds)) {
            $ids = array_map(static fn ($id) => Hashids::connection('custom')->decode($id)[0] ?? (int) $id, $berkasIds);
            $query->whereIn('a.Id_Master_Konten_Berkas', $ids);
        } elseif ($scope === 'all_done') {
            $query->where('a.Status_Render', 'DONE');
        } elseif ($scope === 'pending') {
            // Dokumen menunggu dirender (mis. baru di-set PENDING massal).
            $query->where('a.Status_Render', 'PENDING');
        } elseif ($scope === 'all_unrendered') {
            // Semua yang belum tentu beres: PENDING + FAILED + PROCESSING (stuck).
            $query->whereIn('a.Status_Render', ['PENDING', 'FAILED', 'PROCESSING']);
        } else { // failed
            $query->where('a.Status_Render', 'FAILED');
        }

        $targets = $query->get();
        if ($targets->isEmpty()) {
            return ResponseHelper::error('Tidak ada dokumen yang cocok untuk dimigrasi.', 422);
        }

        $cloudTask = app(CloudTaskService::class);
        $queue = config('services.project_config.gcp.queue_pdf', 'default');
        $webhookUrl = $this->cloudTaskWebhookUrl();
        $dispatched = 0;

        foreach ($targets as $row) {
            $bid = (int) $row->Id_Master_Konten_Berkas;
            try {
                DB::table('N_LMS_Master_Konten_Berkas')
                    ->where('Id_Master_Konten_Berkas', $bid)
                    ->update(['Status_Render' => 'PENDING', 'Error_Message' => null, 'Updated_At' => now()]);

                $cloudTask->dispatch(
                    url: $webhookUrl,
                    queue: $queue,
                    payload: ['berkas_id' => $bid],
                    taskType: 'LMS_PDF_RENDER_MIGRATION',
                    referenceId: $bid,
                    referenceNo: 'MIGRATE-KONTEN-'.$row->Id_Master_Konten,
                    dispatchedBy: 'ADMIN_MONITORING',
                );
                $dispatched++;
            } catch (\Throwable $e) {
                // Lanjut dokumen lain; jangan gagalkan seluruh batch.
                continue;
            }
        }

        return ResponseHelper::success([
            'dispatched' => $dispatched,
            'total' => $targets->count(),
        ], "Berhasil mengantrikan {$dispatched} dokumen untuk dirender.");
    }

    /**
     * Retry render satu dokumen (mis. setelah FAILED).
     */
    public function retryDocument(string $berkasId)
    {
        $realId = Hashids::connection('custom')->decode($berkasId)[0] ?? (int) $berkasId;

        $berkas = DB::table('N_LMS_Master_Konten_Berkas')
            ->where('Id_Master_Konten_Berkas', $realId)
            ->first();

        if (! $berkas) {
            return ResponseHelper::error('Dokumen tidak ditemukan', 404);
        }

        /*
         * Kunci anti double-dispatch, ATOMIK.
         *
         * PINTU KEDUA. Awalan kunci ini sama persis dengan yang dipakai
         * ContentManagementController::renderUlangBerkas -- keduanya menjaga
         * sumber daya yang sama dari dua endpoint berbeda. Pintu itu sudah
         * dibuat atomik lebih dulu; selama pintu ini masih memakai has()+put(),
         * perlindungannya cuma separuh: dua permintaan yang masuk lewat pintu
         * berbeda tetap bisa sama-sama men-dispatch.
         *
         * add() = pasang-kalau-belum-ada, dan itu atomik: di SQL Server Laravel
         * menjalankan INSERT ke tabel cache lalu menangkap pelanggaran primary
         * key, sehingga yang kalah balapan mendapat false. Bentuk has()+put()
         * bukan kunci melainkan cek-lalu-tindak, dan kuncinya bahkan baru
         * terpasang SESUDAH dispatch selesai -- jendela balapannya selebar satu
         * panggilan jaringan ke Cloud Tasks.
         */
        $lockKey = 'lms_pdf_converting_'.$realId;
        if (! Cache::store('database')->add($lockKey, true, 300)) {
            return ResponseHelper::error('Dokumen ini sedang dalam antrian/proses render.', 429);
        }

        try {
            $cloudTask = app(CloudTaskService::class);
            $queue = config('services.project_config.gcp.queue_pdf', 'default');
            $webhookUrl = $this->cloudTaskWebhookUrl();

            $cloudTask->dispatch(
                url: $webhookUrl,
                queue: $queue,
                payload: ['berkas_id' => $realId],
                taskType: 'LMS_PDF_RENDER_RETRY',
                referenceId: $realId,
                referenceNo: 'RETRY-'.$realId,
                dispatchedBy: 'ADMIN_MONITORING',
            );

            DB::table('N_LMS_Master_Konten_Berkas')
                ->where('Id_Master_Konten_Berkas', $realId)
                ->update(['Status_Render' => 'PENDING', 'Error_Message' => null, 'Updated_At' => now()]);

            return ResponseHelper::success(['berkas_id' => $realId], 'Dokumen diantrikan ulang untuk render.');
        } catch (\Throwable $e) {
            /* Dilepas supaya dispatch yang gagal tidak mengunci dokumennya
               300 detik penuh -- perilaku lama, karena dulu kunci memang tak
               pernah terpasang kalau dispatch melempar. */
            Cache::store('database')->forget($lockKey);

            return ResponseHelper::error('Gagal mengantrikan render: '.$e->getMessage(), 500);
        }
    }

    /**
     * Query dasar: hanya berkas PDF dari konten dokumen yang aktif.
     */
    private function basePdfQuery()
    {
        return DB::table('N_LMS_Master_Konten_Berkas as a')
            ->join('N_LMS_Master_Konten as b', 'a.Id_Master_Konten', '=', 'b.Id_Master_Konten')
            ->where('a.Type_File', 'like', '%pdf%')
            ->where('b.Flag_Aktif', 'Y')
            ->where('b.Tipe_Konten', 'document');
    }

    private function storageDiskName(): string
    {
        return config('services.lms.gcs.disk') ?: (config('filesystems.disks.gcs') ? 'gcs' : 'local');
    }

    /**
     * URL webhook yang dipanggil Cloud Tasks. Pakai config webhook_base_url
     * (fallback APP_URL). HARUS URL publik Cloud Run — bukan domain lain.
     */
    private function cloudTaskWebhookUrl(): string
    {
        $base = trim((string) config('services.project_config.gcp.webhook_base_url', ''));
        if ($base === '') {
            throw new \RuntimeException('CLOUD_TASK_WEBHOOK_BASE_URL / APP_URL belum dikonfigurasi.');
        }
        if (! preg_match('~^https?://~i', $base)) {
            $base = 'https://'.ltrim($base, '/');
        }

        return rtrim($base, '/').'/api/v1/tasks/lms/process-pdf';
    }

    private function pageStoragePath(int $berkasId, int $pageNumber): string
    {
        return 'lms/secure-pages/'.$berkasId.'/'.self::RENDER_VERSION.'/page-'.$pageNumber.'.jpg';
    }
}
