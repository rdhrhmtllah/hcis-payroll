<?php

namespace App\Jobs\SYSTEM\Notification\WhatsApp;

use App\Helpers\Whatsapp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log; // Import RateLimiter
use Illuminate\Support\Facades\RateLimiter; // Untuk logging, opsional

class SendWhatsappMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $recipient;

    protected $messageContent;

    protected array $context;

    /**
     * Berapa kali job boleh dicoba kembali jika gagal.
     * Dalam kasus rate limit, ini penting agar job tidak terbuang.
     * Anda bisa sesuaikan ini.
     */
    /**
     * Sama alasannya dengan SendWhatsappJob: penundaan-sampai-commit melekat
     * pada JOB, bukan pada koneksi. Baku config/queue.php adalah
     * env('QUEUE_CONNECTION', 'sync'), dan `sync` menjalankan job INLINE di
     * dalam transaksi yang sedang terbuka — HTTP WhatsApp 10-20 detik menahan
     * kunci selama itu juga.
     */
    public $tries = 5;

    /**
     * Waktu (dalam detik) sebelum job dicoba lagi.
     * Ini akan digunakan jika job dilepaskan kembali (release).
     */
    public $backoff = 60; // Coba lagi setelah 60 detik jika dilepaskan

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($recipient, $messageContent, array $context = [])
    {
        $this->recipient = $recipient;
        $this->messageContent = $messageContent;
        $this->context = $context;
        $this->afterCommit();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Kunci unik untuk rate limiter, misalnya 'whatsapp-send-limit'
        $key = 'whatsapp-send-limit';
        // Batas 2 percobaan (pesan) dalam 60 detik (1 menit)
        $limit = 2;
        $decaySeconds = 60;

        // Cek apakah sudah melebihi batas
        if (RateLimiter::tooManyAttempts($key, $limit, $decaySeconds)) {
            // Jika sudah melebihi batas, log dan lepaskan job kembali ke queue
            Log::info('Rate limit exceeded for WhatsApp message. Releasing job.', [
                'recipient' => $this->recipient,
                'attempts' => $this->attempts(), // Berapa kali job ini sudah dicoba
            ]);

            // Lepaskan job kembali ke queue untuk dicoba lagi nanti.
            // Gunakan $this->release() agar job tidak diulang segera.
            // $this->backoff akan menentukan kapan dicoba lagi (default di __construct atau properti $backoff).
            $this->release($this->backoff);

            return; // Penting: hentikan eksekusi method handle
        }

        // Jika belum melebihi batas, tandai satu percobaan berhasil dan kirim pesan
        RateLimiter::hit($key);

        try {
            // -- LOGIC PENGIRIMAN PESAN WHATSAPP ANDA DI SINI --
            // Contoh: Panggil layanan WhatsApp API Anda
            // $whatsappApiService->sendMessage($this->recipient, $this->messageContent);
            Whatsapp::send_message(
                $this->messageContent,
                array_merge(
                    [
                        'hp' => $this->recipient,
                        'module' => 'ESS',
                        'source_context' => 'SEND_WHATSAPP_MESSAGE_JOB',
                    ],
                    $this->context,
                ),
            );
            Log::info('Sending WhatsApp message to: '.$this->recipient);

            // Simulasi pengiriman pesan
            // sleep(rand(1, 3)); // Simulasi delay API call
            // if (rand(0, 10) < 2) { // Contoh: 20% kemungkinan gagal
            //     throw new \Exception('Simulated API failure');
            // }

            Log::info('Successfully sent WhatsApp message to: '.$this->recipient);
        } catch (\Exception $e) {
            // Jika ada error saat mengirim pesan (misalnya API error),
            // laporkan error dan lepaskan job untuk dicoba lagi jika ingin retry.
            Log::error('Failed to send WhatsApp message to '.$this->recipient.': '.$e->getMessage());
            // Anda bisa memilih untuk $this->release() lagi atau menandai sebagai gagal permanen
            // jika errornya bukan transient.
            $this->fail($e); // menandai job gagal permanen setelah percobaan habis
        }
    }
}
