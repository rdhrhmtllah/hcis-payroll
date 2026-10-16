<?php

namespace App\Jobs\LMS\SOP\Automation;

use App\Jobs\LMS\Training\Scheduling\ProcessBulkPenjadwalan;
use App\Services\LMS\SOP\SopBulkAssignTaskService;
use App\Services\LMS\SOP\SopNotificationService;
use App\Services\LMS\SOP\SopPenjadwalanService;
use App\Services\LMS\SOP\Support\SopLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Bulk assign SOP — jalur async di atas ambang penjadwalan_async_ambang.
 *
 * Diperbaiki dari cacat ProcessBulkPenjadwalan (LMS):
 *  1. IDEMPOTEN: badan chunk = SopPenjadwalanService::assignKeHeader(), yang
 *     men-diff kode terhadap penugasan AKTIF paket. Retry ($tries) menjalankan
 *     ulang semua chunk, tapi yang sudah commit ter-skip — bukan di-insert ulang.
 *  2. KEGAGALAN TERLIHAT: chunk yang melempar exception dicatat ke hasil
 *     (gagal[]) dan tampil di Activity Center — LMS menelan lalu tetap 100%.
 *  3. Progress via N_HRIS_Async_Tasks (Activity Center bersama) — bukan
 *     Cache::put + localStorage bespoke.
 *
 * Header N_SOP_Penjadwalan SUDAH dibuat sinkron oleh controller sebelum
 * dispatch, jadi batch langsung tampil di daftar dengan pill "MEMPROSES".
 */
class ProsesBulkAssignSop implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 3;

    private const CHUNK = 100;

    public function __construct(public string $taskId)
    {
        // Queue Cloud Tasks yang sama dengan penjadwalan LMS — nol infra baru.
        $this->onQueue(config('queue.names.penjadwalan_training'));
    }

    public function handle(
        SopBulkAssignTaskService $task,
        SopPenjadwalanService $svc,
    ): void {
        $row = $task->baris($this->taskId);
        if (! $row) {
            return;
        } // baris dihapus — tak ada yang bisa dikerjakan

        if (! $task->klaim($this->taskId)) {
            return;
        } // sudah ready (retry Cloud Tasks basi)

        $p = json_decode($row->Payload_Json ?: '{}', true) ?: [];
        $penjadwalanId = (int) ($p['id_penjadwalan'] ?? 0);
        $idPaket = (int) ($p['id_paket'] ?? 0);
        $kodes = (array) ($p['kodes'] ?? []);
        $userId = $p['user_id'] ?? null;

        if (! $penjadwalanId || ! $idPaket || empty($kodes)) {
            $task->gagal($this->taskId, 'Payload task tidak lengkap.');

            return;
        }

        $namaPaket = DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idPaket)->value('Nama_Paket') ?? 'SOP';

        $assigned = 0;
        $skipped = 0;
        $gagal = [];
        $diproses = 0;
        $total = count($kodes);

        foreach (array_chunk($kodes, self::CHUNK) as $i => $chunk) {
            if ($task->isAborted($this->taskId)) {
                // Chunk yang sudah commit dibiarkan berdiri — membatalkan bukan
                // membatalkan penugasan yang sudah sah, hanya berhenti menambah.
                $task->gagal($this->taskId, "Dibatalkan setelah {$diproses}/{$total} diproses; yang sudah ter-assign tetap berlaku.");

                return;
            }

            try {
                $hasil = DB::transaction(fn () => $svc->assignKeHeader($penjadwalanId, $idPaket, $chunk, $userId));
                $assigned += $hasil['assigned'];
                $skipped += $hasil['skipped'];

                // Notifikasi SETELAH commit chunk — HTTP/notif tak boleh menahan lock DB.
                if (! empty($hasil['targets'])) {
                    (new SopNotificationService)->notifPenugasan($hasil['targets'], $idPaket, $namaPaket);
                }
            } catch (\Throwable $e) {
                Log::error('SOP bulk assign chunk gagal', ['chunk' => $i, 'exception' => $e]);
                $gagal[] = ['chunk' => $i + 1, 'jumlah' => count($chunk), 'pesan' => mb_substr($e->getMessage(), 0, 200)];
            }

            $diproses += count($chunk);
            $task->bumpProgress($this->taskId, $diproses, $total);
        }

        $task->selesai($this->taskId, ['assigned' => $assigned, 'skipped' => $skipped, 'gagal' => $gagal]);

        if ($assigned > 0) {
            SopLog::catat(
                'ASSIGN',
                "Bulk assign selesai: {$assigned} karyawan di-assign"
                    .($skipped > 0 ? ", {$skipped} dilewati" : '')
                    .(count($gagal) > 0 ? ', '.count($gagal).' chunk gagal' : '').'.',
                $idPaket, null, null, null,
                ['id_penjadwalan' => $penjadwalanId, 'task_id' => $this->taskId, 'oleh_id_users' => $userId, 'gagal' => $gagal],
            );
        }
    }

    public function failed(\Throwable $e): void
    {
        app(SopBulkAssignTaskService::class)->gagal($this->taskId, $e->getMessage());
    }
}
