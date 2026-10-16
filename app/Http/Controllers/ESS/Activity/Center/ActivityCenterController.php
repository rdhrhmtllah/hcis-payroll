<?php

namespace App\Http\Controllers\ESS\Activity\Center;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\LMS\Monitoring\Pdf\LmsPdfMonitoringController;
use App\Jobs\SYSTEM\Application\Exports\DeleteExportFileJob;
use App\Services\ESS\Activity\Center\ActivityCenterService;
use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * ActivityCenterController
 *
 * Endpoint global "Activity Center" (dropdown topbar). Mengagregasi semua tugas
 * async lintas modul & mendelegasikan aksi (download/retry/dismiss) ke sumber
 * yang tepat berdasarkan {type} + Module.
 */
class ActivityCenterController extends Controller
{
    public function __construct(private ActivityCenterService $activity) {}

    private function requestedBy(): ?string
    {
        return optional(optional(Auth::user())->karyawan)->Kode_Karyawan;
    }

    public function index()
    {
        return ResponseHelper::success(
            $this->activity->forUser($this->requestedBy())->list(15),
            'Daftar aktivitas berhasil diambil',
        );
    }

    public function summary()
    {
        return ResponseHelper::success(
            $this->activity->forUser($this->requestedBy())->summary(),
            'Ringkasan aktivitas berhasil diambil',
        );
    }

    // =====================================================================
    // Aksi (delegasi per type)
    // =====================================================================

    public function download(string $type, string $id)
    {
        if ($type !== 'export') {
            return ResponseHelper::error('Aktivitas ini tidak punya file unduhan.', 404);
        }

        $service = $this->resolveExportService($id);
        if (! $service) {
            return ResponseHelper::error('Export tidak ditemukan.', 404);
        }

        $job = $service->markDownloaded($id);
        if (! $job || $job->Status !== AbstractExportService::STATUS_READY || ! $job->File_Path) {
            return ResponseHelper::error('File export belum siap atau sudah dihapus.', 404);
        }
        if (! Storage::disk(AbstractExportService::DISK)->exists($job->File_Path)) {
            return ResponseHelper::error('File export sudah tidak tersedia.', 410);
        }

        // Pembersihan WAJIB berjeda. Dulu dikirim tanpa jeda dengan anggapan
        // file sudah selesai di-stream lebih dulu -- padahal Cloud Tasks
        // mengeksekusinya dalam ~1 detik, sering sebelum stream dimulai, dan
        // unduhan pertama pun berakhir 500 "No such object". Jeda satu jam juga
        // memberi ruang mengunduh ulang. afterResponse(): koneksi `sync`
        // mengabaikan jeda, jadi pengirimannya pun ditahan sampai stream selesai.
        DeleteExportFileJob::dispatch($id)->delay(now()->addHour())->afterResponse();

        try {
            return Storage::disk(AbstractExportService::DISK)->download($job->File_Path, $job->File_Name);
        } catch (\League\Flysystem\FilesystemException $e) {
            // Berkas hilang di antara exists() dan stream: katakan apa adanya.
            return ResponseHelper::error('File export sudah tidak tersedia.', 410);
        }
    }

    public function retry(string $type, string $id)
    {
        if ($type === 'pdf-render') {
            // Delegasi ke retry render PDF yang sudah ada.
            return app(LmsPdfMonitoringController::class)->retryDocument($id);
        }

        if ($type === 'bulk-assign') {
            // Re-dispatch job yang SAMA — aman karena idempoten: badan chunk-nya
            // men-diff kode terhadap penugasan aktif, yang sudah commit ter-skip.
            $task = app(\App\Services\LMS\SOP\SopBulkAssignTaskService::class);
            $row = $task->baris($id);
            if (! $row || $row->Status === \App\Services\SYSTEM\Application\Exports\AbstractExportService::STATUS_READY) {
                return ResponseHelper::error('Aktivitas tidak ditemukan atau sudah selesai.', 422);
            }
            \App\Jobs\LMS\SOP\Automation\ProsesBulkAssignSop::dispatch($id);

            return ResponseHelper::success(['id' => $id], 'Penjadwalan dijadwalkan ulang', 202);
        }

        if ($tugasPa = $this->tugasPa($type, $id)) {
            // Tugas yang SAMA dijalankan lagi — aman karena kedua pekerjaannya melewati yang
            // sudah tersimpan (penugasan yang sudah terbentuk, pintu yang sudah terkirim).
            if (! app($tugasPa['layanan'])->siapkanUlang($id)) {
                return ResponseHelper::error('Aktivitas tidak ditemukan atau tidak sedang gagal.', 422);
            }
            $tugasPa['job']::dispatch($id);

            return ResponseHelper::success(['id' => $id], 'Tugas dijalankan lagi', 202);
        }

        $service = $this->resolveExportService($id);
        if (! $service) {
            return ResponseHelper::error('Aktivitas tidak ditemukan atau sudah selesai.', 422);
        }

        // "Coba lagi": hapus job gagal lalu buat & dispatch job BARU (export_id
        // baru) dgn payload yang sama. Tidak memakai retry otomatis Cloud Tasks.
        $newId = $service->retryAsNew($id);
        if (! $newId) {
            return ResponseHelper::error('Aktivitas tidak ditemukan atau sudah selesai.', 422);
        }

        $service->dispatchJob($newId);

        return ResponseHelper::success(['id' => $newId], 'Aktivitas dijadwalkan ulang', 202);
    }

    public function abort(string $type, string $id)
    {
        if ($tugasPa = $this->tugasPa($type, $id)) {
            if (! app($tugasPa['layanan'])->hentikan($id)) {
                return ResponseHelper::error('Aktivitas tidak ditemukan atau sudah selesai.', 422);
            }

            return ResponseHelper::success(['id' => $id], 'Tugas dihentikan', 200);
        }

        if ($type === 'kpi-auto-cap') {
            if (! app(\App\Services\KPI\KpiEnforcementService::class)->batalkanBatch($id, $this->requestedBy())) {
                return ResponseHelper::error('Eksekusi auto-cap tidak ditemukan atau sudah selesai.', 422);
            }

            return ResponseHelper::success(['id' => $id], 'Eksekusi auto-cap dibatalkan', 200);
        }

        if ($type !== 'export') {
            return ResponseHelper::error('Aktivitas ini tidak bisa dibatalkan.', 422);
        }

        $service = $this->resolveExportService($id);
        if (! $service || ! $service->abort($id)) {
            return ResponseHelper::error('Aktivitas tidak ditemukan atau sudah selesai.', 422);
        }

        return ResponseHelper::success(['id' => $id], 'Aktivitas dibatalkan', 200);
    }

    /**
     * Sembunyikan SATU aktivitas — satu-satunya tempat pemetaan type → service.
     *
     * DIPAKAI DUA PINTU: dismiss() per item, dan dismissAll() untuk tombol
     * "Bersihkan". Sebelum diangkat ke sini, pemetaannya hanya hidup di
     * dismiss(), sementara dismissAll() menulis kueri sendiri ke SATU tabel —
     * sehingga tombol Bersihkan diam-diam melewatkan upload, bulk-assign, dan
     * rekonsiliasi. Aturan yang disalin akan menyimpang pada salinan yang
     * terlewat; satu-satunya pencegahan yang bekerja adalah tidak menyalinnya.
     *
     * @return bool false bila jenisnya memang tak mengenal "sembunyikan"
     *              (mis. pdf-render, yang merupakan status sumber, bukan task)
     */
    private function sembunyikanSatu(string $type, string $id): bool
    {
        // Task upload media: soft-delete via UploadTaskService.
        if ($type === 'upload') {
            app(\App\Services\ESS\Activity\Center\UploadTaskService::class)->dismiss($id);

            return true;
        }

        if ($type === 'bulk-assign') {
            app(\App\Services\LMS\SOP\SopBulkAssignTaskService::class)->dismiss($id);

            return true;
        }

        if (isset(self::TUGAS_PA[$type])) {
            if (! $tugasPa = $this->tugasPa($type, $id)) {
                return false;
            }
            app($tugasPa['layanan'])->dismiss($id);

            return true;
        }

        // Rekonsiliasi SOP: segmen URL 'reconcile', tapi Task_Type-nya
        // 'reconcile-dokumen' — terima keduanya agar payload panel yang sudah
        // ter-cache di browser tidak berakhir 422 secara diam-diam.
        if ($type === 'reconcile' || $type === \App\Services\LMS\SOP\SopReconcileTaskService::TASK_TYPE) {
            app(\App\Services\LMS\SOP\SopReconcileTaskService::class)->dismiss($id);

            return true;
        }

        if ($type === 'kpi-auto-cap' || $type === \App\Services\KPI\KpiEnforcementService::ASYNC_TASK_TYPE) {
            DB::table(AbstractExportService::TABLE)
                ->where('Export_Id', $id)
                ->where('Task_Type', \App\Services\KPI\KpiEnforcementService::ASYNC_TASK_TYPE)
                ->update(['Deleted_At' => now()]);

            return true;
        }

        if ($type === 'kpi-backfill-cap') {
            DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)
                ->where('Task_Type', \App\Services\KPI\KpiCapParameterBackfillService::TASK_TYPE)
                ->update(['Deleted_At' => now()]);

            return true;
        }

        if ($type !== 'export') {
            return false;
        }

        $service = $this->resolveExportService($id);
        if (! $service) {
            return false;
        }

        $service->dismiss($id);

        return true;
    }

    public function dismiss(string $type, string $id)
    {
        /* Export yang tak dikenali dibedakan dari jenis yang memang tak bisa
           disembunyikan: yang pertama 404 (barisnya hilang), yang kedua 422
           (jenisnya memang begitu). Menyatukannya membuat admin menerima sebab
           yang salah tentang apa yang perlu ia perbaiki. */
        if ($type === 'export' && ! $this->resolveExportService($id)) {
            return ResponseHelper::error('Export tidak ditemukan.', 404);
        }

        if (! $this->sembunyikanSatu($type, $id)) {
            return ResponseHelper::error('Aktivitas ini tidak bisa disembunyikan.', 422);
        }

        return ResponseHelper::success(null, 'Aktivitas disembunyikan dari panel');
    }

    /**
     * Sembunyikan semua aktivitas yang sudah selesai atau gagal dari panel.
     */
    /**
     * Status yang berarti "masih berjalan" — cermin isActive() di layar.
     *
     * DITULIS SEBAGAI LARANGAN, bukan daftar-putih "sudah selesai", dan itu
     * inti perbaikannya. Versi sebelumnya menyaring kolom `Status` MENTAH
     * dengan daftar-putih (ready/failed/expired/cancelled/completed),
     * sedangkan panel menampilkan status TURUNAN dari adapter. Terukur
     * 10 Sep 2026 pada satu pengguna: panel menawarkan 13 aktivitas yang bisa
     * dibersihkan, tetapi UPDATE lama hanya menyentuh 11 — dua baris berstatus
     * mentah `processing` sudah dilaporkan `failed` oleh adapternya. Kedua
     * baris itu terlihat, tampak bisa dibersihkan, dan tak pernah hilang.
     *
     * Dengan bentuk larangan, apa pun status turunan baru yang kelak muncul
     * ikut terbersihkan tanpa daftar ini perlu disusul.
     */
    private const STATUS_BERJALAN = ['queued', 'processing'];

    public function dismissAll()
    {
        /*
         * BERANGKAT DARI DAFTAR YANG DILIHAT PENGGUNA, bukan dari satu tabel.
         *
         * Panel menggabungkan LIMA sumber (export, upload, pdf-render,
         * bulk-assign, reconcile). Versi sebelumnya menulis satu UPDATE ke
         * N_HRIS_Export_Jobs saja, lalu membalas 200 — sehingga tombol
         * "Bersihkan" tampak bekerja tetapi item upload, bulk-assign, dan
         * rekonsiliasi muncul kembali pada penyegaran berikutnya. Balasan 200
         * itu pula yang membuat jalur cadangan di layar tak pernah jalan:
         * cadangannya hanya dipicu bila endpoint ini MELEMPAR galat.
         *
         * KEPEMILIKAN IKUT DENGAN SENDIRINYA. Id-nya berasal dari list() milik
         * pengguna ini, jadi tak ada id orang lain yang bisa masuk — tanpa
         * perlu setiap service mengulang penyaringan Requested_By-nya sendiri.
         *
         * Batas 200 sengaja jauh di atas 15 yang dirender panel: yang
         * dibersihkan adalah semua yang MEMANG miliknya, bukan sebatas yang
         * kebetulan terlihat di layar.
         */
        $items = $this->activity->forUser($this->requestedBy())->list(200);

        $jumlah = 0;
        foreach ($items as $item) {
            $status = (string) ($item['status'] ?? '');
            $type = (string) ($item['type'] ?? '');
            $id = (string) ($item['export_id'] ?? $item['id'] ?? '');

            /* dismiss_url kosong = jenis ini memang tak mengenal "sembunyikan"
               (pdf-render adalah status sumber, bukan task). Melewatinya di
               sini membuat layar dan server sepakat tentang apa yang bisa
               dibersihkan. */
            if ($id === '' || empty($item['dismiss_url'])) {
                continue;
            }
            if (in_array($status, self::STATUS_BERJALAN, true)) {
                continue;
            }

            /* Satu kegagalan tak boleh menggagalkan sisanya: tombol ini
               membersihkan sebanyak yang bisa, lalu melaporkan angkanya. */
            try {
                if ($this->sembunyikanSatu($type, $id)) {
                    $jumlah++;
                }
            } catch (\Throwable $e) {
                Log::warning('ActivityCenter dismissAll melewati satu aktivitas', [
                    'type' => $type, 'id' => $id, 'exception' => $e->getMessage(),
                ]);
            }
        }

        return ResponseHelper::success(
            ['dismissed' => $jumlah],
            $jumlah > 0
                ? "{$jumlah} aktivitas selesai atau gagal telah dibersihkan"
                : 'Tidak ada aktivitas yang bisa dibersihkan',
        );
    }

    // =====================================================================
    // Resolver
    // =====================================================================

    /** Tugas latar PA: jenis → layanan siklus hidupnya dan job yang mengerjakannya. */
    private const TUGAS_PA = [
        \App\Services\KPI\PerformanceAppraisal\PaPenjadwalanTugasService::TASK_TYPE => [
            'layanan' => \App\Services\KPI\PerformanceAppraisal\PaPenjadwalanTugasService::class,
            'job' => \App\Jobs\KPI\PerformanceAppraisal\PenjadwalanHcLearnPaJob::class,
        ],
        \App\Services\KPI\PerformanceAppraisal\PaPembentukanTugasService::TASK_TYPE => [
            'layanan' => \App\Services\KPI\PerformanceAppraisal\PaPembentukanTugasService::class,
            'job' => \App\Jobs\KPI\PerformanceAppraisal\PembentukanPenugasanPaJob::class,
        ],
        \App\Services\KPI\PerformanceAppraisal\PaRevisiTugasService::TASK_TYPE => [
            'layanan' => \App\Services\KPI\PerformanceAppraisal\PaRevisiTugasService::class,
            'job' => \App\Jobs\KPI\PerformanceAppraisal\RevisiPesertaPaJob::class,
        ],
        \App\Services\KPI\PerformanceAppraisal\PaHitungTugasService::TASK_TYPE => [
            'layanan' => \App\Services\KPI\PerformanceAppraisal\PaHitungTugasService::class,
            'job' => \App\Jobs\KPI\PerformanceAppraisal\HitungSiklusPaJob::class,
        ],
    ];

    /**
     * Tugas latar PA milik PENGGUNA INI — null bila jenisnya bukan tugas PA, barisnya tak ada,
     * atau pemiliknya orang lain.
     *
     * Berbeda dari export, aksinya MENGUBAH keadaan siklus (membentuk penugasan, mengirim ke
     * HC Learn), jadi id yang ditebak tidak boleh cukup.
     *
     * @return array{layanan:class-string, job:class-string}|null
     */
    private function tugasPa(string $type, string $id): ?array
    {
        $peta = self::TUGAS_PA[$type] ?? null;
        if ($peta === null) {
            return null;
        }

        $baris = app($peta['layanan'])->baris($id);

        return $baris !== null && (string) $baris->Requested_By === (string) $this->requestedBy() ? $peta : null;
    }

    private function moduleOf(string $exportId): ?string
    {
        return DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $exportId)
            ->value('Module');
    }

    /** Pilih service export sesuai kolom Module via registry config/exports.php. */
    private function resolveExportService(string $exportId): ?AbstractExportService
    {
        $module = $this->moduleOf($exportId);
        $serviceClass = config("exports.modules.{$module}");

        if (! $serviceClass || ! is_subclass_of($serviceClass, AbstractExportService::class)) {
            return null;
        }

        return app($serviceClass);
    }
}
