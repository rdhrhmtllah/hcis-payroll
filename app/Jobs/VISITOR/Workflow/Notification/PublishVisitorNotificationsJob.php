<?php

namespace App\Jobs\VISITOR\Workflow\Notification;

use App\Services\VISITOR\Workflow\Notification\VisitorNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishVisitorNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 3;

    /** @param array<int, array<string, mixed>> $events */
    public function __construct(public array $events)
    {
        /*
         * Koneksi dipatok ke cloudtasks supaya notifikasi tidak ikut antrean
         * lain — KECUALI bila lingkungan memang memilih 'sync'.
         *
         * 'sync' tidak pernah terpilih secara kebetulan: ia dipilih sadar oleh
         * uji dan oleh lingkungan tanpa worker. Memaksanya ke cloudtasks di
         * sana bukan sekadar mubazir, melainkan membuat job TAK PERNAH JALAN:
         * cloudtasks satu-satunya koneksi ber-`after_commit => true`, sementara
         * uji membungkus dirinya dalam transaksi yang selalu di-rollback. Job
         * menunggu commit yang tak akan datang, notifikasinya tak pernah
         * ditulis, dan ujinya gagal dengan "0 notifikasi" tanpa satu pun galat
         * yang bisa dilacak — persis yang terjadi pada delapan uji Integration
         * VISITOR sampai 9 September 2026.
         */
        $koneksi = (string) config('queue.default');
        $this->onConnection($koneksi === 'sync' ? 'sync' : 'cloudtasks');
        $this->onQueue((string) config('queue.connections.cloudtasks.queue_kpi_notif', 'general-notif'));
    }

    public function backoff(): array
    {
        return [10, 30, 120];
    }

    public function handle(VisitorNotificationService $notifications): void
    {
        foreach ($this->events as $event) {
            $kind = (string) ($event['kind'] ?? '');

            if ($kind === 'submission') {
                $notifications->notifikasiPengajuanBaru(
                    (string) $event['domain'],
                    (string) $event['no_transaksi'],
                    (array) ($event['context'] ?? []),
                );

                continue;
            }

            if ($kind === 'decision') {
                $notifications->notifikasiKeputusan(
                    (string) $event['domain'],
                    (string) $event['no_transaksi'],
                    (string) $event['decision'],
                    (array) ($event['context'] ?? []),
                );

                continue;
            }

            throw new \InvalidArgumentException('Jenis event notifikasi Visitor tidak dikenal.');
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('VISITOR_NOTIFICATION_JOB_FAILED', [
            'event_count' => count($this->events),
            'error' => $exception->getMessage(),
        ]);
    }
}
