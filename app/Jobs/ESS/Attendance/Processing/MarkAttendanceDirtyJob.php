<?php

namespace App\Jobs\ESS\Attendance\Processing;

use App\Services\ESS\Attendance\Processing\AttendanceDirtyResolverService;
use App\Services\SYSTEM\Application\Transactions\TransactionService;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Tandai snapshot absen "kotor" (EXEC N_HRIS_SP_MARK_ABSEN_DIRTY) secara ASYNC
 * (Cloud Tasks), untuk sebuah No_Transaksi yang baru di-approve/reject/ubah.
 *
 * KENAPA JOB: sebelumnya `AttendanceDirtyResolverService::markFromTransaction`
 * dipanggil SINKRON DI DALAM transaksi approval (ApprovalService::approve/reject →
 * TransactionService::handleFinalApprove/handleReject). Ia menjalankan SP dirty-mark
 * (N+1 per target) sehingga menahan lock exclusive selama durasi approve → pembaca
 * SP absen ter-blok (LCK_M_S) → antrean koneksi menumpuk → "Login timeout expired".
 * Data diagnostik produksi (N_HRIS_DB_Health_Snapshot) menunjukkan sesi yang
 * menjalankan SP ini menjadi pemblokir terbanyak.
 *
 * Dirty-mark memang ASYNC by-design (mengisi antrean re-kalkulasi absen) & idempoten,
 * jadi aman dipindah ke worker. Koneksi cloudtasks memakai after_commit=true → dispatch
 * yang terjadi di dalam transaksi otomatis menunggu commit (tak ada dirty-mark
 * "phantom" untuk transaksi yang rollback).
 *
 * requestedBy DIKIRIM via payload karena worker tak punya sesi Auth.
 */
class MarkAttendanceDirtyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public string $noTransaksi,
        public string $reason = 'TRANSACTION_CHANGED',
        public string $source = 'LARAVEL_TRANSACTION',
        public int $priority = 1,
        public ?string $requestedBy = null,
    ) {}

    public function handle(AttendanceDirtyResolverService $resolver): void
    {
        try {
            $resolver->markFromTransaction(
                $this->noTransaksi,
                $this->reason,
                $this->source,
                $this->priority,
                $this->requestedBy,
            );
        } catch (\Throwable $e) {
            Log::channel('attendance_queue_worker')->error('MarkAttendanceDirtyJob gagal', [
                'no_transaksi' => $this->noTransaksi,
                'reason' => $this->reason,
                'error' => $e->getMessage(),
            ]);

            throw $e; // lempar ulang agar di-retry (tries=3)
        }
    }
}
