<?php

namespace App\Jobs\KPI\Notification;

use App\Helpers\WhatsappLogHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Kirim 1 pesan WhatsApp reminder Update Performance KPI ke SATU karyawan.
 *
 * Dipakai oleh command `kpi:notif-update` (KpiNotifUpdatePerformance) yang dulu
 * memanggil WhatsappLogHelper::sendAndLog() SECARA SINKRON di dalam loop ratusan
 * karyawan — satu request bisa berjalan belasan menit dan dibunuh di ~300s
 * (max_execution_time / timeout Cloud Run). Dengan memecahnya jadi 1 job/karyawan,
 * tiap eksekusi (request /handle-task Cloud Tasks) hanya melakukan 1 panggilan WA
 * (~detik) sehingga tidak pernah mendekati batas 300s.
 *
 * CATATAN granularitas: SENGAJA 1 job per karyawan. Satu job yang memproses semua
 * karyawan akan kembali menabrak tembok 300s di request handler-nya sendiri.
 */
class SendKpiNotifWhatsappJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Sama alasannya dengan SendWhatsappJob: penundaan-sampai-commit melekat
     * pada JOB, bukan pada koneksi. Baku config/queue.php adalah
     * env('QUEUE_CONNECTION', 'sync'), dan `sync` menjalankan job INLINE di
     * dalam transaksi yang sedang terbuka — HTTP WhatsApp 10-20 detik menahan
     * kunci selama itu juga.
     */
    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public string $kodeKaryawan,
        public string $nama,
        public string $hp,
        public ?string $noTransaksi,
        public array $pesan,
    ) {
        $this->afterCommit();
    }

    public function handle(): void
    {
        // sendAndLog sudah menangani validasi nomor, kirim via Whatsapp::send_message,
        // deteksi status, dan insert ke Whatsapp_Log (sukses/gagal). Callback di sini
        // cukup mencatat ke channel log command agar jejaknya tetap ada.
        WhatsappLogHelper::sendAndLog(
            $this->kodeKaryawan,
            $this->nama,
            $this->hp,
            $this->noTransaksi,
            $this->pesan,
            function () {
                Log::channel('kpiNotifUpdate')->info(
                    "WA reminder Update Performance terkirim ke {$this->kodeKaryawan}"
                );
            },
            function ($reason) {
                Log::channel('kpiNotifUpdate')->warning(
                    "Gagal kirim WA reminder Update Performance ke {$this->kodeKaryawan}",
                    ['reason' => $reason]
                );
            },
        );
    }
}
