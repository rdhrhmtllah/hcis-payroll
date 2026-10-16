<?php

namespace App\Services\LMS\SOP;

use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lifecycle tugas async "rekonsiliasi SOP" di tabel sentral N_HRIS_Async_Tasks
 * (Task_Type='reconcile-dokumen', Module='sop-dokumen').
 *
 * Mengikuti pola SopBulkAssignTaskService: reuse konstanta/helper static
 * AbstractExportService TANPA meng-extend-nya — rekonsiliasi tak punya
 * buildDataset()/renderFile(), jadi subclassing hanya mewarisi kewajiban
 * yang tak relevan (mapRow menganggap ready-tanpa-File_Path = expired, dst).
 *
 * Nol DDL: tabelnya sudah generik lewat Task_Type + Module.
 *
 * Payload_Json = {id_dokumen|id_paket, judul|nama_paket, paket?, hasil?}.
 * Seluruh payload hidup di baris task ini — satu baris, satu sumber kebenaran.
 */
class SopReconcileTaskService
{
    public const TASK_TYPE = 'reconcile-dokumen';

    public const MODULE = 'sop-dokumen';

    public function makeId(): string
    {
        return 'sop-rec-'.now()->format('Ymd-His').'-'.strtoupper(Str::random(6));
    }

    /**
     * Catat task baru (queued). Kembalikan Export_Id.
     *
     * $id boleh ditentukan pemanggil supaya baris ini dapat dibuat SESUDAH
     * intent outbox berhasil, tanpa kehilangan id yang sudah terlanjur
     * dititipkan ke payload intent itu. Tanpa itu, pemanggil terpaksa membuat
     * baris progres lebih dulu — dan ketika dedup outbox menang, baris itu
     * menjadi yatim: tak ada handler yang akan menutupnya, dan panel Aktivitas
     * menggantung di "queued" selamanya.
     */
    public function mulai(array $payload, int $total, ?string $requestedBy, ?string $id = null): string
    {
        $id = $id ?: $this->makeId();
        $now = now();

        DB::table(AbstractExportService::TABLE)->insert([
            'Export_Id' => $id,
            'Task_Type' => self::TASK_TYPE,
            'Module' => self::MODULE,
            'Format' => 'reconcile',
            'Payload_Json' => json_encode($payload),
            'Status' => AbstractExportService::STATUS_QUEUED,
            'Total_Records' => max(1, $total),
            'Processed_Records' => 0,
            'Percent' => 0,
            'Requested_By' => $requestedBy,
            'Created_At' => $now,
            'Updated_At' => $now,
        ]);

        return $id;
    }

    public function baris(string $id): ?object
    {
        return DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $id)
            ->where('Task_Type', self::TASK_TYPE)
            ->whereNull('Deleted_At')
            ->first();
    }

    public function bumpProgress(string $id, int $processed, int $total): void
    {
        DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $id)->where('Task_Type', self::TASK_TYPE)
            ->update([
                'Processed_Records' => $processed,
                'Percent' => max(0, min(99, (int) floor($processed / max(1, $total) * 100))),
                'Updated_At' => now(),
            ]);
    }

    /** Selesai — hasil {paket, diberitahu, gagal[]} disimpan ke payload (terlihat di panel). */
    public function selesai(string $id, array $hasil): void
    {
        $row = $this->baris($id);
        $payload = $row ? (json_decode($row->Payload_Json ?: '{}', true) ?: []) : [];
        $payload['hasil'] = $hasil;

        DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $id)->where('Task_Type', self::TASK_TYPE)
            ->update([
                'Status' => AbstractExportService::STATUS_READY,
                'Percent' => 100,
                'Processed_Records' => DB::raw('Total_Records'),
                'Payload_Json' => json_encode($payload),
                'Error_Message' => ! empty($hasil['gagal'])
                    ? (is_array($hasil['gagal']) ? count($hasil['gagal']) : (int) $hasil['gagal']).' potongan gagal — lihat rincian.'
                    : null,
                'Completed_At' => now(),
                'Updated_At' => now(),
            ]);
    }

    public function gagal(string $id, string $pesan): void
    {
        DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $id)->where('Task_Type', self::TASK_TYPE)
            ->update([
                'Status' => AbstractExportService::STATUS_FAILED,
                'Error_Message' => mb_substr($pesan, 0, 500),
                'Updated_At' => now(),
            ]);
    }

    /** Sembunyikan dari panel (soft delete). */
    public function dismiss(string $id): void
    {
        DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $id)->where('Task_Type', self::TASK_TYPE)
            ->update(['Deleted_At' => now(), 'Updated_At' => now()]);
    }
}
