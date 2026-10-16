<?php

namespace App\Jobs\SYSTEM\Notification\WhatsApp;

use App\Helpers\WhatsappLogHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Kirim 1 pesan WhatsApp secara ASYNC (Cloud Tasks) + log ke Whatsapp_Log.
 *
 * Dipakai oleh WhatsappLogHelper::sendAndLog untuk jalur TANPA callback: alih-alih
 * melakukan HTTP blocking (~10-20 dtk) di dalam request (yang bila terjadi di dalam
 * transaksi DB menahan lock → "Login timeout expired"), pengiriman dipindah ke
 * worker. Koneksi cloudtasks memakai after_commit=true sehingga dispatch yang
 * terjadi di dalam transaksi otomatis menunggu commit (tidak ada WA "phantom"
 * untuk transaksi yang rollback).
 *
 * Jalur yang MASIH memakai callback (mis. update angka_wa) tidak lewat job ini;
 * callback closure tak bisa di-serialize, jadi tetap dijalankan setelah commit di
 * proses request via DB::afterCommit (lihat WhatsappLogHelper::sendAndLog).
 */
class SendWhatsappJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Penundaan-sampai-commit MELEKAT PADA JOB, bukan pada konfigurasi koneksi.
     *
     * Keamanan jalur ini sebelumnya bersandar penuh pada `after_commit => true`
     * milik koneksi `cloudtasks`. Itu benar hari ini, tetapi baku di
     * config/queue.php adalah `env('QUEUE_CONNECTION', 'sync')` — dan pada
     * lingkungan yang env-nya hilang, `sync` menjalankan job INLINE, di dalam
     * transaksi yang sedang terbuka. HTTP WhatsApp memakan 10-20 detik, dan
     * dengan RCSI mati satu transaksi yang menahan sekian detik membuat
     * pembacanya menunggu selama itu juga (terukur: penahan 7 dtk -> korban
     * menunggu 5.103 ms). Persis "Login timeout expired" yang dulu diperbaiki,
     * hidup lagi tanpa satu baris kode pun berubah.
     *
     * Properti ini membuat penundaan berlaku pada koneksi APA PUN — sync,
     * database, maupun cloudtasks — sehingga jaminannya tak lagi bergantung
     * pada satu baris .env yang bisa hilang tanpa ada yang menyadarinya.
     */
    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public string $kodeKaryawan,
        public ?string $nama,
        public ?string $hp,
        public ?string $noTransaksi,
        public array $pesan,
    ) {
        $this->afterCommit();
    }

    public function handle(): void
    {
        // performSend menangani HTTP kirim, deteksi status, mirror in-app, dan
        // insert ke Whatsapp_Log (SUCCESS/FAILED). Tanpa callback closure.
        WhatsappLogHelper::performSend(
            $this->kodeKaryawan,
            $this->nama,
            $this->hp,
            $this->noTransaksi,
            $this->pesan,
        );
    }
}
