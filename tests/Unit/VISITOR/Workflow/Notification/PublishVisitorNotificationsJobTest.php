<?php

namespace Tests\Unit\VISITOR\Workflow\Notification;

use App\Jobs\VISITOR\Workflow\Notification\PublishVisitorNotificationsJob;
use App\Services\VISITOR\Workflow\Notification\VisitorNotificationService;
use Mockery;
use Tests\TestCase;

class PublishVisitorNotificationsJobTest extends TestCase
{
    public function test_job_publishes_submission_and_decision_events_idempotently(): void
    {
        $notifications = Mockery::mock(VisitorNotificationService::class);
        $notifications->shouldReceive('notifikasiPengajuanBaru')
            ->once()->with('tamu', 'VSR-1', ['nama_tamu' => 'A']);
        $notifications->shouldReceive('notifikasiKeputusan')
            ->once()->with('permit', 'PMT-1', 'DISETUJUI', ['plat_nomor' => 'B 1 X']);

        $job = new PublishVisitorNotificationsJob([
            [
                'kind' => 'submission',
                'domain' => 'tamu',
                'no_transaksi' => 'VSR-1',
                'context' => ['nama_tamu' => 'A'],
            ],
            [
                'kind' => 'decision',
                'domain' => 'permit',
                'no_transaksi' => 'PMT-1',
                'decision' => 'DISETUJUI',
                'context' => ['plat_nomor' => 'B 1 X'],
            ],
        ]);

        $job->handle($notifications);

        /*
         * Di bawah phpunit, queue.default = 'sync' dan job ini SENGAJA
         * mengikutinya: cloudtasks satu-satunya koneksi ber-`after_commit`,
         * dan uji bertransaksi tak pernah commit -- job yang dipaksa ke sana
         * tak akan pernah jalan. Yang harus dijaga adalah sisi sebaliknya:
         * begitu lingkungan memilih koneksi nyata, job WAJIB ke cloudtasks
         * dan tidak ikut antrean lain.
         */
        $this->assertSame('sync', $job->connection, 'Di lingkungan uji job harus ikut sync agar benar-benar jalan.');

        config(['queue.default' => 'redis']);
        $jobProduksi = new PublishVisitorNotificationsJob([]);
        $this->assertSame('cloudtasks', $jobProduksi->connection, 'Di luar sync, job wajib ke cloudtasks.');

        $this->assertSame('general-notif', $job->queue);
        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 30, 120], $job->backoff());
    }
}
