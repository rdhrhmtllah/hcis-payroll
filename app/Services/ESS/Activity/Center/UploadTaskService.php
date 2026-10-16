<?php

namespace App\Services\ESS\Activity\Center;

use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * UploadTaskService
 *
 * Lifecycle tugas async "upload media LMS" di tabel sentral N_HRIS_Async_Tasks
 * (Task_Type='upload', Module='lms-content'). Berbeda dengan export, transfer
 * byte file terjadi di BROWSER (TUS→Bunny / PUT→GCS), jadi service ini TIDAK
 * memproses file — ia hanya MENCATAT & MEMPERBARUI status agar tugas muncul di
 * Pusat Aktivitas dan tetap ada sampai di-silang (pola "download browser").
 *
 * SATU TASK UNTUK SELURUH RANGKAIAN. Admin melihat satu baris yang berjalan dari
 * "Mengunggah" sampai "Selesai", bukan satu baris per tahap:
 *
 *   startUpload()        -> processing  0-70%  "Mengunggah"        (browser)
 *   updateProgress()     -> heartbeat byte dari browser
 *   markUploaded()       -> processing    72%  "Memeriksa berkas"  (finalize)
 *   tandaiTahap()        -> processing    78%  "Memindai isi"      (worker verifikasi)
 *   selesaiVerifikasi()  -> processing    90%  "Menyiapkan halaman" (PDF)
 *                           ATAU ready    100% (non-PDF langsung selesai)
 *   linkRenderResult()   -> ready|failed 100%  saat render PDF selesai
 *
 * Nama tahap disimpan di Payload_Json.tahap — tak perlu kolom baru, dan Pusat
 * Aktivitas sudah membaca Payload_Json.
 *
 * Reuse konstanta & helper static dari AbstractExportService (TABLE, status,
 * mapRow, formatSize) tanpa meng-extend-nya — upload bukan job yang punya
 * buildDataset()/renderFile().
 */
class UploadTaskService
{
    private const TASK_TYPE = 'upload';

    private const MODULE = 'lms-content';

    /** Buat Export_Id unik untuk task upload. */
    public function makeUploadId(): string
    {
        return 'upl-'.now()->format('Ymd-His').'-'.strtoupper(Str::random(6));
    }

    /**
     * Catat task upload baru (status processing). $payload: judul, kategori,
     * mime, format (video|document), ukuran byte. Kembalikan Export_Id.
     */
    public function startUpload(array $payload, ?string $requestedBy): string
    {
        $exportId = $this->makeUploadId();
        $now = now();

        DB::table(AbstractExportService::TABLE)->insert([
            'Export_Id' => $exportId,
            'Task_Type' => self::TASK_TYPE,
            'Module' => self::MODULE,
            'Format' => $payload['format'] ?? 'document',
            'Payload_Json' => json_encode($payload),
            'Status' => AbstractExportService::STATUS_PROCESSING,
            'Total_Records' => 1,
            'Processed_Records' => 0,
            'Percent' => 0,
            'Requested_By' => $requestedBy,
            'File_Size' => $payload['size'] ?? null,
            'Started_At' => $now,
            'Created_At' => $now,
            'Updated_At' => $now,
        ]);

        return $exportId;
    }

    /** Heartbeat progres upload byte (dipanggil dari browser). Cap 99%. */
    public function updateProgress(string $exportId, int $percent): void
    {
        DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $exportId)
            ->where('Task_Type', self::TASK_TYPE)
            ->whereNull('Deleted_At')
            ->update([
                'Percent' => max(0, min($percent, 99)),
                'Updated_At' => now(),
            ]);
    }

    /**
     * Byte selesai diunggah dan berkasnya lolos pemeriksaan cepat.
     *
     * Task TIDAK ditutup di sini — bahkan untuk non-PDF. Berkasnya masih di
     * karantina menunggu verifikasi struktur, dan menutup task sekarang akan
     * memberi tahu admin "selesai" untuk konten yang belum tentu boleh dipakai.
     *
     * Video Bunny pengecualiannya: ia tak melewati penyimpanan kita sama sekali,
     * jadi tak ada yang bisa diverifikasi dan tasknya langsung selesai.
     */
    public function markUploaded(string $exportId, bool $isPdf, ?int $berkasId = null, ?int $kategoriId = null, ?string $scope = null): void
    {
        $row = $this->find($exportId);
        if (! $row) {
            return;
        }

        $payload = json_decode($row->Payload_Json ?: '{}', true) ?: [];
        if ($berkasId !== null) {
            $payload['berkas_id'] = $berkasId;
        }
        if ($kategoriId !== null) {
            $payload['kategori_id'] = $kategoriId;
        }
        if ($scope !== null) {
            $payload['scope'] = $scope;
        }
        $payload['is_pdf'] = $isPdf;

        // Tanpa berkas = video Bunny: tak ada objek di penyimpanan kita untuk
        // diverifikasi, jadi rangkaiannya memang berhenti di sini.
        if ($berkasId === null) {
            $payload['tahap'] = 'Selesai';
            DB::table(AbstractExportService::TABLE)
                ->where('Export_Id', $exportId)
                ->update([
                    'Status' => AbstractExportService::STATUS_READY,
                    'Percent' => 100,
                    'Processed_Records' => 1,
                    'Payload_Json' => json_encode($payload),
                    'Completed_At' => now(),
                    'Updated_At' => now(),
                ]);

            return;
        }

        $payload['tahap'] = 'Memeriksa berkas';
        DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $exportId)
            ->update([
                'Status' => AbstractExportService::STATUS_PROCESSING,
                'Percent' => 72,
                'Processed_Records' => 1,
                // Reference_Id menggantikan pencocokan Payload_Json bergaya LIKE
                // saat webhook mencari task ini kembali (lihat cariByBerkas).
                'Reference_Id' => $berkasId,
                'Payload_Json' => json_encode($payload),
                'Updated_At' => now(),
            ]);
    }

    /** Perbarui label & persentase tahap sebuah task yang sedang berjalan. */
    public function tandaiTahap(int $berkasId, string $tahap, int $percent): void
    {
        $row = $this->cariByBerkas($berkasId);
        if (! $row) {
            return;
        }

        $payload = json_decode($row->Payload_Json ?: '{}', true) ?: [];
        $payload['tahap'] = $tahap;

        DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $row->Export_Id)
            ->update([
                'Percent' => max(0, min($percent, 99)),
                'Payload_Json' => json_encode($payload),
                'Updated_At' => now(),
            ]);
    }

    /**
     * Verifikasi struktur selesai.
     *
     * $lanjutRender true berarti masih ada tahap render halaman PDF; tasknya
     * tetap terbuka dan akan ditutup linkRenderResult(). false berarti seluruh
     * rangkaian sudah beres.
     */
    public function selesaiVerifikasi(int $berkasId, bool $lanjutRender): void
    {
        if ($lanjutRender) {
            $this->tandaiTahap($berkasId, 'Menyiapkan halaman', 90);

            return;
        }

        $row = $this->cariByBerkas($berkasId);
        if (! $row) {
            return;
        }

        $payload = json_decode($row->Payload_Json ?: '{}', true) ?: [];
        $payload['tahap'] = 'Selesai';

        DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $row->Export_Id)
            ->update([
                'Status' => AbstractExportService::STATUS_READY,
                'Percent' => 100,
                'Payload_Json' => json_encode($payload),
                'Completed_At' => now(),
                'Updated_At' => now(),
            ]);
    }

    /** Verifikasi menolak berkasnya — tutup task sebagai gagal beserta alasannya. */
    public function gagalVerifikasi(int $berkasId, string $alasan): void
    {
        $row = $this->cariByBerkas($berkasId);
        if (! $row) {
            return;
        }

        $payload = json_decode($row->Payload_Json ?: '{}', true) ?: [];
        $payload['tahap'] = 'Ditolak';

        DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $row->Export_Id)
            ->update([
                'Status' => AbstractExportService::STATUS_FAILED,
                'Error_Message' => Str::limit($alasan, 250, ''),
                'Payload_Json' => json_encode($payload),
                'Updated_At' => now(),
            ]);
    }

    /**
     * Task upload yang masih berjalan untuk sebuah berkas.
     *
     * Memakai kolom Reference_Id, BUKAN `Payload_Json LIKE '%"berkas_id":123%'`
     * seperti sebelumnya: pola itu juga cocok dengan `"berkas_id":1234`, sehingga
     * task berkas 1234 bisa ditutup oleh hasil render berkas 123.
     */
    private function cariByBerkas(int $berkasId)
    {
        return DB::table(AbstractExportService::TABLE)
            ->where('Task_Type', self::TASK_TYPE)
            ->where('Reference_Id', $berkasId)
            ->whereNull('Deleted_At')
            ->where('Status', AbstractExportService::STATUS_PROCESSING)
            ->orderByDesc('Id_Export')
            ->first();
    }

    /** Tandai upload gagal (mis. finalize ke DB gagal). */
    public function markFailed(string $exportId, string $message): void
    {
        DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $exportId)
            ->where('Task_Type', self::TASK_TYPE)
            ->update([
                'Status' => AbstractExportService::STATUS_FAILED,
                'Error_Message' => Str::limit($message, 250, ''),
                'Updated_At' => now(),
            ]);
    }

    /**
     * Tutup row upload PDF berdasarkan hasil render. Dipanggil dari webhook
     * render PDF (CloudTaskWebhookController) setelah Status_Render diset.
     * $renderStatus: 'DONE' | 'FAILED'.
     */
    public function linkRenderResult(int $berkasId, string $renderStatus): void
    {
        // Cari row upload PDF yang menunggu (processing) untuk berkas ini.
        $row = $this->cariByBerkas($berkasId);

        if (! $row) {
            return;
        }

        if (strtoupper($renderStatus) === 'DONE') {
            $payload = json_decode($row->Payload_Json ?: '{}', true) ?: [];
            $payload['tahap'] = 'Selesai';

            DB::table(AbstractExportService::TABLE)
                ->where('Export_Id', $row->Export_Id)
                ->update([
                    'Status' => AbstractExportService::STATUS_READY,
                    'Percent' => 100,
                    'Payload_Json' => json_encode($payload),
                    'Completed_At' => now(),
                    'Updated_At' => now(),
                ]);
        } else {
            DB::table(AbstractExportService::TABLE)
                ->where('Export_Id', $row->Export_Id)
                ->update([
                    'Status' => AbstractExportService::STATUS_FAILED,
                    'Error_Message' => 'Render PDF gagal.',
                    'Updated_At' => now(),
                ]);
        }
    }

    /** Soft-delete (silang dari panel). */
    public function dismiss(string $exportId): void
    {
        DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $exportId)
            ->where('Task_Type', self::TASK_TYPE)
            ->update(['Deleted_At' => now(), 'Updated_At' => now()]);
    }

    private function find(string $exportId): ?object
    {
        return DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $exportId)
            ->where('Task_Type', self::TASK_TYPE)
            ->first();
    }
}
