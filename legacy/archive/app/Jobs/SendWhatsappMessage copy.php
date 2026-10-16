<?php

namespace App\Jobs;

use App\Helpers\Whatsapp;
use App\Jobs\SYSTEM\Notification\WhatsApp\SendWhatsappMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsappMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $item;

    protected $pesan;

    /**
     * Create a new job instance.
     */
    public function __construct($item, $pesan)
    {
        $this->item = $item;
        $this->pesan = $pesan;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Log waktu eksekusi untuk debugging
        \Log::info('WhatsApp job executed at: '.now());
        \Log::info('Job was supposed to be delayed until: '.$this->delay ?? 'no delay set');

        // Logic pengiriman pesan WhatsApp
        $response = Whatsapp::send_message($this->pesan);
        // dd($response['messages'][0]['message_status']);
        // Mengatasi response atau error jika pengiriman gagal
        if (! $response['messages'][0]['message_status']) {
            \Log::error('Failed to send WhatsApp message to: '.$this->item);
        } else {
            \Log::info('WhatsApp message sent successfully to: '.$this->item);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        \Log::error('SendWhatsappMessage job failed: '.$exception->getMessage());
    }
}
