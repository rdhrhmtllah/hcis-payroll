<?php

namespace App\Jobs\LMS\Training\Scheduling;

use App\Helpers\WhatsappLogHelper;
use App\Jobs\SYSTEM\Notification\WhatsApp\SendWhatsappJob;
use App\Services\SYSTEM\Notification\NotificationService;
use App\Services\SYSTEM\Notification\WhatsappMessageBuilder;
use App\Services\SYSTEM\Notification\WhatsappNotificationMirrorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Kirim notifikasi penugasan training LMS untuk SATU chunk peserta (maks 20 orang):
 * (a) in-app top-bar (N_HRIS_Notification, batch + idempotent), dan
 * (b) WhatsApp per orang lewat WhatsappLogHelper::sendAndLog.
 *
 * Di-dispatch dari ProcessBulkPenjadwalan SETELAH DB::commit() tiap chunk (di luar
 * transaksi), sehingga: worker bulk tetap ringan (1 dispatch per chunk, bukan 20),
 * pengiriman hanya untuk data yang PASTI ter-commit, dan HTTP WA (~10-20 dtk) tidak
 * pernah menahan lock DB (dijalankan lagi di worker SendWhatsappJob).
 *
 * Granularitas per-chunk (≤20) sengaja: satu job memproses ≤20 orang → jauh di bawah
 * batas 300s handler queue.
 */
class SendPenjadwalanNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * @param  array<int,array{kode:string,nama:string,hp:string,id_penjadwalan:int|string}>  $recipients
     */
    public function __construct(
        public array $recipients,
        public string $namaPaket,
    ) {}

    public function handle(): void
    {
        if (empty($this->recipients)) {
            return;
        }

        // (a) In-app: batch (1 SELECT + 1 bulk INSERT), idempotent via notification_key.
        // Key & type DISAMAKAN dengan WA mirror (WhatsappNotificationMirrorService) agar
        // tidak dobel: notif ini (dibuat di sini, andal untuk peserta tanpa HP valid) dan
        // notif mirror status WA berbagi SATU baris. Wording dibuat konsisten dengan case
        // HCIS_PENUGASAN_LMS di mirror sehingga tetap rapi meski mirror meng-update baris.
        $mirror = app(WhatsappNotificationMirrorService::class);
        $payloads = [];
        foreach ($this->recipients as $r) {
            $idPenjadwalan = $r['id_penjadwalan'] ?? null;
            if (empty($r['kode']) || empty($idPenjadwalan)) {
                continue;
            }

            $nama = $r['nama'] ?? '-';
            $noTransaksi = 'LMS-'.$idPenjadwalan;

            $payloads[] = [
                'kode_karyawan' => $r['kode'],
                'type' => $mirror->notificationTypeFor('hcis_penugasan_lms'),
                'title' => 'Penugasan Training Baru',
                'message' => 'Halo '.$nama.', Anda mendapat penugasan training "'.$this->namaPaket.'". '.
                    'Selesaikan seluruh pembelajaran hingga 100% termasuk pre-test & post-test. Selamat belajar!',
                // Seragam 'HCIS' mengikuti konvensi notif WA-mirror (performSend memakai
                // 'HCIS'); mencegah module berbeda antara peserta ber-HP vs tanpa HP.
                'module' => 'HCIS',
                'severity' => 'info',
                'action_url' => '/lms/training/catalog',
                'reference_id' => $noTransaksi,
                'notification_key' => $mirror->notificationKeyFor($r['kode'], 'hcis_penugasan_lms', $noTransaksi),
            ];
        }

        if (! empty($payloads)) {
            try {
                app(NotificationService::class)->upsertManyByKey($payloads);
            } catch (\Throwable $e) {
                // Notifikasi tak boleh menggagalkan seluruh chunk; catat & lanjut ke WA.
                Log::error('Gagal simpan notifikasi in-app penugasan LMS: '.$e->getMessage());
            }
        }

        // (b) WhatsApp: per orang. sendAndLog menormalkan nomor, memvalidasi, mencatat
        // nomor invalid ke Whatsapp_Log, lalu men-dispatch SendWhatsappJob (retryable).
        foreach ($this->recipients as $r) {
            if (empty($r['kode'])) {
                continue;
            }

            $nama = $r['nama'] ?? '-';
            $hp = $r['hp'] ?? '';
            $idPenjadwalan = $r['id_penjadwalan'] ?? '';

            try {
                $pesan = WhatsappMessageBuilder::penugasanLms($hp, $nama, $this->namaPaket);
                WhatsappLogHelper::sendAndLog($r['kode'], $nama, $hp, 'LMS-'.$idPenjadwalan, $pesan);
            } catch (\Throwable $e) {
                Log::error('Gagal kirim WA penugasan LMS ke '.$r['kode'].': '.$e->getMessage());
            }
        }
    }
}
