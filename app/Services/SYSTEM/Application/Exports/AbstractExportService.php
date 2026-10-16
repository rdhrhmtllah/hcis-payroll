<?php

namespace App\Services\SYSTEM\Application\Exports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * AbstractExportService
 *
 * Basis terpusat untuk semua fitur export berbasis job (Cloud Tasks). Menampung
 * SELURUH logika umum lintas modul: lifecycle job, progress polling, daftar
 * export, cleanup file, dan orchestration process(). Tiap modul export cukup
 * meng-extend kelas ini dan mengimplementasi:
 *   - moduleKey()        : identitas modul (kolom Module), mis. 'lms-training'.
 *   - exportIdPrefix()   : prefix Export_Id, mis. 'lms-exp'.
 *   - buildDataset($job) : bangun data dari payload (panggil bumpProgress).
 *   - renderFile($job,$dataset) : render & simpan file -> [path, name, size].
 *   - listLabel($job)    : label ringkas untuk panel UI.
 *
 * Sumber kebenaran semua tugas async disatukan di tabel N_HRIS_Async_Tasks
 * (kolom Task_Type membedakan jenis tugas, Module membedakan asal). File hasil
 * disimpan di disk GCS, dibersihkan command export:cleanup ~1 jam setelah diunduh
 * / 24 jam jika tak pernah diunduh.
 */
abstract class AbstractExportService
{
    public const TABLE = 'N_HRIS_Async_Tasks';

    public const DISK = 'gcs';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    /** Interval cek pembatalan saat memproses (per N baris). */
    protected const ABORT_CHECK_EVERY = 25;

    /** Channel log dipakai bersama agar mudah dipantau. */
    protected const LOG_CHANNEL = 'UpdatePerformanceController';

    // =====================================================================
    // Kontrak modul
    // =====================================================================

    /** Identitas modul (kolom Module di N_HRIS_Async_Tasks). */
    abstract protected function moduleKey(): string;

    /** Jenis tugas async (kolom Task_Type). Export service default 'export'. */
    protected function taskType(): string
    {
        return 'export';
    }

    /** Prefix Export_Id (mis. 'lms-exp', 'kpi-exp'). */
    abstract protected function exportIdPrefix(): string;

    /** Subdirektori penyimpanan file di disk (mis. 'lms-exports'). */
    abstract protected function storageDir(): string;

    /**
     * Bangun dataset dari record job (payload ada di $job->Payload_Json).
     * Wajib memanggil bumpProgress() saat memproses baris.
     */
    abstract protected function buildDataset(object $job): array;

    /**
     * Render dataset ke file & simpan ke disk. Kembalikan [path, name, size].
     */
    abstract protected function renderFile(object $job, array $dataset): array;

    /** Label ringkas untuk panel UI (judul card export). */
    abstract protected function listLabel(object $job): string;

    /**
     * Re-dispatch job pembuatan file untuk export ini. Tiap service tahu kelas
     * Job-nya sendiri, sehingga Activity Center bisa retry secara polimorfik
     * tanpa hardcode per modul.
     */
    abstract public function dispatchJob(string $exportId): void;

    /** Jumlah baris hasil (untuk Processed_Records final). Default: count rows. */
    protected function datasetCount(array $dataset): int
    {
        return count($dataset['rows'] ?? []);
    }

    // =====================================================================
    // Lifecycle
    // =====================================================================

    public function makeExportId(): string
    {
        return $this->exportIdPrefix().'-'.now()->format('Ymd-His').'-'.strtoupper(Str::random(6));
    }

    /**
     * Buat record job export (status queued). $payload berisi semua data
     * spesifik modul (jenis, scope, filter, selection, dll).
     */
    public function startJob(string $exportId, string $format, array $payload, int $total, ?string $requestedBy): int
    {
        $now = now();

        return (int) DB::table(self::TABLE)->insertGetId([
            'Export_Id' => $exportId,
            'Task_Type' => $this->taskType(),
            'Module' => $this->moduleKey(),
            'Format' => $format,
            'Payload_Json' => json_encode($payload),
            'Status' => self::STATUS_QUEUED,
            'Total_Records' => $total,
            'Processed_Records' => 0,
            'Percent' => 0,
            'Requested_By' => $requestedBy,
            'Started_At' => $now,
            'Created_At' => $now,
            'Updated_At' => $now,
        ]);
    }

    /**
     * Proses penuh satu job: klaim queued -> processing -> buildDataset ->
     * renderFile -> status ready.
     *
     * Exception sengaja dilempar kembali. Cloud Tasks/Laravel Job hanya dapat
     * menjalankan retry dan failed() bila handler benar-benar gagal; mengubah
     * exception menjadi return normal membuat delivery yang gagal terlihat 2xx.
     */
    public function process(string $exportId): void
    {
        $job = $this->findJob($exportId);
        if (! $job) {
            return;
        }

        // Dibatalkan user: bila task GCP terlanjur fire (deleteTask gagal/telat),
        // payload['aborted'] menahan eksekusi -> no-op. Guard ini yang membuat
        // abort aman walau status DB dipakai 'failed' (yang sebenarnya bisa diklaim).
        $payload = $this->payload($job);
        if (! empty($payload['aborted'])) {
            return;
        }

        // Idempoten terhadap retry Cloud Tasks: bila job sudah selesai (file siap),
        // JANGAN proses ulang. Tanpa guard ini, retry (mis. handler kena timeout
        // proxy padahal job sukses) akan menimpa job 'ready' -> Status balik
        // 'processing' (progress 0 lagi) & file lama hilang -> download 404.
        if ($job->Status === self::STATUS_READY && $job->File_Path) {
            return;
        }

        // Cegah dua eksekusi paralel atas job yang sama. Status processing hanya
        // boleh direbut kembali setelah lease stale; bumpProgress memperbarui
        // Updated_At sehingga worker aktif tidak dianggap mati.
        $claimed = $this->claimForProcessing($exportId);

        // Update gagal (0 baris) -> job sudah ready, dibatalkan, atau sedang
        // diproses worker lain dengan lease aktif. Aman: berhenti, jangan timpa.
        if ($claimed === 0) {
            return;
        }

        try {
            $dataset = $this->buildDataset($job);

            // Pembatalan bisa terjadi setelah dataset selesai tapi sebelum render
            // (render bisa lama). Cek sekali lagi agar tidak menulis file sia-sia.
            if ($this->isAborted($exportId)) {
                throw new ExportAbortedException($exportId);
            }

            [$path, $name, $size] = $this->renderFile($job, $dataset);

            // Jangan tandai ready bila job sudah dibatalkan saat render berlangsung.
            if ($this->isAborted($exportId)) {
                throw new ExportAbortedException($exportId);
            }

            $count = $this->datasetCount($dataset);

            DB::table(self::TABLE)->where('Export_Id', $exportId)->update([
                'Status' => self::STATUS_READY,
                'Total_Records' => $count,
                'Processed_Records' => $count,
                'Percent' => 100,
                'File_Path' => $path,
                'File_Name' => $name,
                'File_Size' => $size,
                'Completed_At' => now(),
                'Updated_At' => now(),
            ]);
        } catch (ExportAbortedException $e) {
            // Pembatalan bersih: status & pesan sudah di-set oleh abort(). Jangan
            // timpa jadi 'failed' generik. Cukup pastikan progress tidak menyesatkan.
            DB::table(self::TABLE)->where('Export_Id', $exportId)->update([
                'Percent' => 0,
                'Updated_At' => now(),
            ]);
            Log::channel(self::LOG_CHANNEL)->info(strtoupper($this->moduleKey()).'_EXPORT_ABORTED', [
                'export_id' => $exportId,
            ]);
        } catch (\Throwable $e) {
            // Lepaskan klaim supaya retry milik Job dapat mengambil pekerjaan
            // yang sama. failed() akan mengubahnya menjadi failed ketika semua
            // percobaan sudah habis. Guard status mencegah abort/ready yang
            // terjadi bersamaan ditimpa kembali menjadi queued.
            DB::table(self::TABLE)
                ->where('Export_Id', $exportId)
                ->where('Status', self::STATUS_PROCESSING)
                ->update([
                    'Status' => self::STATUS_QUEUED,
                    'Error_Message' => 'Percobaan export gagal dan akan dicoba kembali.',
                    'Updated_At' => now(),
                ]);
            Log::channel(self::LOG_CHANNEL)->error(strtoupper($this->moduleKey()).'_EXPORT_FAILED: '.$e->getMessage(), [
                'export_id' => $exportId,
                'format' => $job->Format,
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    protected function claimForProcessing(string $exportId): int
    {
        $staleBefore = now()->subMinutes(max(1, (int) config('exports.processing_lease_minutes', 10)));

        return DB::table(self::TABLE)
            ->where('Export_Id', $exportId)
            ->whereNull('File_Path')
            ->where(function ($query) use ($staleBefore) {
                $query->whereIn('Status', [self::STATUS_QUEUED, self::STATUS_FAILED])
                    ->orWhere(function ($stale) use ($staleBefore) {
                        $stale->where('Status', self::STATUS_PROCESSING)
                            ->where('Updated_At', '<=', $staleBefore);
                    });
            })
            ->update([
                'Status' => self::STATUS_PROCESSING,
                'Processed_Records' => 0,
                'Percent' => 0,
                'Error_Message' => null,
                'Updated_At' => now(),
            ]);
    }

    /** Update progress (dipanggil subclass saat membangun dataset). */
    protected function bumpProgress(string $exportId, int $processed, int $total): void
    {
        // Cek pembatalan secara berkala (bukan tiap baris -> hemat query). Bila job
        // di-abort di tengah proses, hentikan segera dgn exception khusus supaya
        // worker tidak lanjut menyelesaikan job yang sudah dibatalkan.
        if ($processed % self::ABORT_CHECK_EVERY === 0 && $this->isAborted($exportId)) {
            throw new ExportAbortedException($exportId);
        }

        $percent = min($total > 0 ? (int) round(($processed / $total) * 100) : 0, 99);

        /*
         * DITULIS HANYA SAAT PERSENNYA BERGERAK.
         *
         * Subclass memanggil bumpProgress() sekali PER BARIS — itu memang
         * bentuk yang wajar dari sisi pemanggil. Yang tidak wajar: tiap
         * panggilan dulu menerbitkan satu UPDATE. Ekspor 900 karyawan berarti
         * 900 UPDATE ke tabel job hanya untuk menggerakkan progress bar yang
         * cuma punya 100 langkah — 800 di antaranya menulis angka yang sama
         * dengan yang sudah tertulis.
         *
         * Baris terakhir SELALU ditulis walau persennya tak berubah: di situlah
         * Processed_Records mencapai angka akhirnya, dan panel membaca kolom itu
         * apa adanya.
         *
         * Yang berubah hanya kekerapan tulisnya. Nilai yang dilihat pengguna
         * sama persis; ia hanya tidak lagi ditulis ulang berkali-kali dengan
         * isi yang identik.
         */
        $terakhir = $this->persenTerakhir[$exportId] ?? null;

        if ($terakhir === $percent && $processed < $total) {
            return;
        }

        $this->persenTerakhir[$exportId] = $percent;

        DB::table(self::TABLE)->where('Export_Id', $exportId)->update([
            'Processed_Records' => $processed,
            'Percent' => $percent, // 100 hanya saat file benar-benar siap
            'Updated_At' => now(),
        ]);
    }

    /**
     * Persen terakhir yang BENAR-BENAR ditulis, per Export_Id.
     *
     * Terikat instance, bukan statik: satu proses ekspor memakai satu instance,
     * dan cache statik akan bertahan lintas job di worker antrean lalu melewati
     * penulisan yang seharusnya terjadi.
     *
     * @var array<string,int>
     */
    private array $persenTerakhir = [];

    /** Apakah job sudah ditandai dibatalkan (payload['aborted']) — cek ringan. */
    protected function isAborted(string $exportId): bool
    {
        $payloadJson = DB::table(self::TABLE)->where('Export_Id', $exportId)->value('Payload_Json');
        $payload = json_decode($payloadJson ?: '{}', true) ?: [];

        return ! empty($payload['aborted']);
    }

    /** Decode payload job menjadi array. */
    protected function payload(object $job): array
    {
        return json_decode($job->Payload_Json ?: '{}', true) ?: [];
    }

    /**
     * Bersihkan nama custom (dari user) menjadi potongan nama file yang aman:
     * buang karakter tak valid, rapikan spasi -> underscore, batasi panjang.
     * Return '' bila kosong/tidak valid (pemanggil fallback ke nama default).
     */
    protected function sanitizeFileName(?string $nama): string
    {
        $nama = trim((string) $nama);
        if ($nama === '') {
            return '';
        }
        // Hilangkan karakter ilegal untuk nama file lintas-OS.
        $nama = preg_replace('/[\\/:*?"<>|\x00-\x1F]+/', ' ', $nama);
        // Rapikan whitespace -> satu underscore.
        $nama = preg_replace('/\s+/', '_', trim($nama));
        // Sisakan hanya karakter aman (alfanumerik, underscore, strip, titik).
        $nama = preg_replace('/[^\w\-.]+/u', '', $nama);
        $nama = trim($nama, '_-.');

        return mb_substr($nama, 0, 80);
    }

    protected function findJob(string $exportId): ?object
    {
        return DB::table(self::TABLE)
            ->where('Export_Id', $exportId)
            ->where('Task_Type', $this->taskType())
            ->where('Module', $this->moduleKey())
            ->first();
    }

    /**
     * Simpan nama task Cloud Tasks ke baris job (dipanggil listener TaskCreated).
     * Static & lintas modul: cukup match Export_Id (unik). Tidak menimpa bila
     * sudah terisi (dispatch ulang seharusnya tak terjadi, tapi aman).
     */
    public static function setCloudTaskName(string $exportId, string $taskName): void
    {
        DB::table(self::TABLE)
            ->where('Export_Id', $exportId)
            ->whereNull('Cloud_Task_Name')
            ->update([
                'Cloud_Task_Name' => $taskName,
                'Updated_At' => now(),
            ]);
    }

    /**
     * Tandai job gagal permanen (dipanggil dari Job::failed() saat retry habis /
     * worker mati keras spt OOM, di mana process() tak sempat menulis status).
     * Idempotent: tidak menimpa job yang sudah ready.
     */
    public function markFailed(string $exportId, ?string $message = null): void
    {
        $job = $this->findJob($exportId);
        if (! $job || $job->Status === self::STATUS_READY) {
            return;
        }

        DB::table(self::TABLE)->where('Export_Id', $exportId)->update([
            'Status' => self::STATUS_FAILED,
            'Error_Message' => $message ?: 'Gagal membuat file export. Silakan coba lagi.',
            'Updated_At' => now(),
        ]);
    }

    // =====================================================================
    // Penyimpanan file
    // =====================================================================

    /** Simpan konten string ke disk, kembalikan ukuran byte. */
    protected function putFile(string $relativePath, string $content): int
    {
        Storage::disk(self::DISK)->put($relativePath, $content);

        return strlen($content);
    }

    /** Simpan file dari path temp ke disk, kembalikan ukuran byte. */
    protected function putFileFromTemp(string $relativePath, string $tmpPath): int
    {
        $size = filesize($tmpPath);
        $stream = fopen($tmpPath, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('File sementara export tidak dapat dibuka.');
        }

        try {
            // Flysystem menerima resource dan mengalirkannya ke object storage;
            // file besar tidak perlu disalin utuh sekali lagi ke string PHP.
            Storage::disk(self::DISK)->put($relativePath, $stream);
        } finally {
            // Flysystem MENUTUP resource yang diberikan kepadanya setelah
            // mengalirkannya. Menutupnya lagi di sini melempar TypeError
            // "supplied resource is not a valid stream resource" — dan karena
            // lemparan itu terjadi SESUDAH berkasnya benar-benar terunggah,
            // job export ditandai "gagal permanen" untuk pekerjaan yang
            // sebenarnya sudah selesai. Terjadi empat kali pada log
            // 10 September 2026 (LMS-TRAINING_EXPORT_FAILED).
            if (is_resource($stream)) {
                fclose($stream);
            }
            @unlink($tmpPath);
        }

        return (int) $size;
    }

    protected function filePathFor(object $job, string $ext): string
    {
        return $this->storageDir().'/'.$job->Export_Id.'.'.$ext;
    }

    // =====================================================================
    // Polling / list (untuk panel UI)
    // =====================================================================

    public function getProgress(string $exportId): ?array
    {
        $job = $this->findJob($exportId);

        return $job ? $this->mapJob($job) : null;
    }

    /** Export terakhir (paling baru, belum di-dismiss) milik admin untuk modul ini. */
    public function getLatest(?string $requestedBy): ?array
    {
        $job = DB::table(self::TABLE)
            ->where('Task_Type', $this->taskType())
            ->where('Module', $this->moduleKey())
            ->when($requestedBy, fn ($q) => $q->where('Requested_By', $requestedBy))
            ->whereNull('Deleted_At')
            ->orderByDesc('Id_Export')
            ->first();

        return $job ? $this->mapJob($job) : null;
    }

    /** Daftar export terakhir milik admin untuk modul ini. */
    public function getList(?string $requestedBy, int $limit = 10): array
    {
        return DB::table(self::TABLE)
            ->where('Task_Type', $this->taskType())
            ->where('Module', $this->moduleKey())
            ->when($requestedBy, fn ($q) => $q->where('Requested_By', $requestedBy))
            ->whereNull('Deleted_At')
            ->orderByDesc('Id_Export')
            ->limit($limit)
            ->get()
            ->map(fn ($job) => $this->mapJob($job))
            ->all();
    }

    /** Petakan record job ke payload UI. Subclass boleh override untuk field ekstra. */
    protected function mapJob(object $job): array
    {
        return self::mapRow($job, $this->listLabel($job));
    }

    /**
     * Petakan record tabel async-task ke payload UI seragam. Static agar bisa
     * dipakai agregator Activity Center tanpa tahu modul asal. $label bisa
     * di-supply pemanggil; jika null pakai Module + format sebagai fallback.
     */
    public static function mapRow(object $job, ?string $label = null): array
    {
        $fileGone = $job->Status === self::STATUS_READY && (! $job->File_Path || $job->Deleted_At);
        $status = $fileGone ? 'expired' : $job->Status;

        $waktu = $job->Created_At ? Carbon::parse($job->Created_At)->isoFormat('D MMM YYYY HH:mm') : null;

        return [
            'export_id' => $job->Export_Id,
            'type' => $job->Task_Type ?? 'export',
            'module' => $job->Module,
            'format' => $job->Format,
            'format_label' => strtoupper((string) $job->Format),
            'status' => $status,
            'percent' => (int) $job->Percent,
            'total_records' => (int) $job->Total_Records,
            'processed_records' => (int) $job->Processed_Records,
            'file_name' => $job->File_Name,
            'file_size' => $job->File_Size,
            'file_size_label' => self::formatSize($job->File_Size),
            'error_message' => $job->Error_Message,
            'requested_at' => $waktu,
            'label' => $label ?? (ucfirst((string) ($job->Module ?? 'Task')).' · '.strtoupper((string) $job->Format)),
            'is_ready' => $status === self::STATUS_READY,
        ];
    }

    // =====================================================================
    // Aksi panel
    // =====================================================================

    /**
     * "Coba lagi" gaya hapus-lalu-buat-baru: buat job BARU (export_id baru) dari
     * payload job gagal, lalu hapus job lama (file + baris). Mengembalikan
     * export_id baru, atau null bila job tak ada / sudah selesai (ready).
     *
     * Tidak men-dispatch — pemanggil (controller) yang dispatchJob(export_id baru),
     * agar konsisten dgn alur lain & mudah dites.
     */
    public function retryAsNew(string $exportId): ?string
    {
        $job = $this->findJob($exportId);
        if (! $job || $job->Status === self::STATUS_READY) {
            return null;
        }

        $payload = $this->payload($job);
        // Job baru harus berjalan normal: jangan bawa penanda 'aborted' lama
        // (kalau terbawa, process() akan langsung no-op).
        unset($payload['aborted']);
        $newId = $this->makeExportId();

        $this->startJob(
            $newId,
            (string) $job->Format,
            $payload,
            (int) $job->Total_Records,
            $job->Requested_By,
        );

        // Hapus job gagal yang lama (file fisik bila ada + baris DB) supaya tak
        // menumpuk di panel. Aman: file job gagal umumnya belum ada.
        if ($job->File_Path) {
            try {
                if (Storage::disk(self::DISK)->exists($job->File_Path)) {
                    Storage::disk(self::DISK)->delete($job->File_Path);
                }
            } catch (\Throwable $e) {
                Log::channel(self::LOG_CHANNEL)->warning(
                    'retryAsNew(): gagal hapus file export lama: '.$e->getMessage(),
                    ['export_id' => $exportId],
                );
            }
        }
        DB::table(self::TABLE)->where('Export_Id', $exportId)->delete();

        return $newId;
    }

    /**
     * Batalkan job yang masih berjalan/antri. Menghapus task di Cloud Tasks (bila
     * nama task tersimpan) lalu menandai job 'failed' + penanda payload 'aborted'
     * (guard di process() mencegah eksekusi bila task terlanjur fire). Item tetap
     * tampil di panel dgn tombol "Coba Lagi". Return false bila job tak ada / sudah
     * ready (tak bisa dibatalkan).
     */
    public function abort(string $exportId): bool
    {
        $job = $this->findJob($exportId);
        if (! $job || $job->Status === self::STATUS_READY) {
            return false;
        }

        // Hapus task di Cloud Tasks bila namanya tertangkap saat dispatch.
        $taskName = trim((string) ($job->Cloud_Task_Name ?? ''));
        if ($taskName !== '') {
            try {
                app(\App\Services\SYSTEM\TaskProcessing\CloudTaskService::class)->deleteTaskByName($taskName);
            } catch (\Throwable $e) {
                // Task mungkin sudah jalan/hilang — bukan kegagalan fatal untuk abort.
                Log::channel(self::LOG_CHANNEL)->warning(
                    'abort(): gagal hapus Cloud Task: '.$e->getMessage(),
                    ['export_id' => $exportId],
                );
            }
        }

        // Tandai aborted di payload (guard process) + status failed (UI: gagal +
        // tombol coba lagi). Tidak set Deleted_At supaya item tetap terlihat.
        $payload = $this->payload($job);
        $payload['aborted'] = true;

        DB::table(self::TABLE)->where('Export_Id', $exportId)->update([
            'Status' => self::STATUS_FAILED,
            'Payload_Json' => json_encode($payload),
            'Error_Message' => 'Dibatalkan oleh pengguna.',
            'Updated_At' => now(),
        ]);

        return true;
    }

    /** Tandai waktu unduh pertama. Return record job (untuk controller download). */
    public function markDownloaded(string $exportId): ?object
    {
        $job = $this->findJob($exportId);
        if (! $job) {
            return null;
        }

        if (! $job->Downloaded_At) {
            DB::table(self::TABLE)->where('Export_Id', $exportId)->update([
                'Downloaded_At' => now(),
                'Updated_At' => now(),
            ]);
        }

        return $job;
    }

    /**
     * Sembunyikan dari panel (Deleted_At) SEKALIGUS hapus file fisik di disk.
     * Dipanggil saat user men-silang task → bersihkan temp seketika.
     */
    public function dismiss(string $exportId): void
    {
        $job = DB::table(self::TABLE)->where('Export_Id', $exportId)->first();

        if ($job && $job->File_Path) {
            try {
                if (Storage::disk(self::DISK)->exists($job->File_Path)) {
                    Storage::disk(self::DISK)->delete($job->File_Path);
                }
            } catch (\Throwable $e) {
                Log::channel(self::LOG_CHANNEL)->warning('dismiss(): gagal hapus file export: '.$e->getMessage(), [
                    'export_id' => $exportId,
                ]);
            }
        }

        DB::table(self::TABLE)
            ->where('Export_Id', $exportId)
            ->update(['File_Path' => null, 'Deleted_At' => now(), 'Updated_At' => now()]);
    }

    // =====================================================================
    // Util
    // =====================================================================

    protected function humanSize(?int $bytes): ?string
    {
        return self::formatSize($bytes);
    }

    public static function formatSize(?int $bytes): ?string
    {
        if (! $bytes) {
            return null;
        }
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 2).' MB';
    }
}
