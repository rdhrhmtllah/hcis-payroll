<?php

namespace App\Jobs\ESS\Attendance\Leave\Notification;

use App\Helpers\Whatsapp;
use App\Jobs\KPI\Notification\SendKpiNotifWhatsappJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Kirim 1 pesan WhatsApp notifikasi izin expired ke SATU karyawan.
 *
 * Dipakai command `izin:expiredIzin` (ExpiredIzin) yang sebelumnya memanggil
 * Whatsapp::send_message() SECARA SINKRON di dalam loop transaksi expired.
 * Command itu berjalan di dalam request schedule:run yang di-cap ~300s;
 * dengan timeout WA 10s/pesan, run bisa terbunuh di tengah saat pesan banyak.
 * Pola granularitas sama dengan SendKpiNotifWhatsappJob: SENGAJA 1 job per
 * pesan supaya tiap eksekusi /handle-task hanya 1 panggilan WA.
 */
class SendExpiredIzinWhatsappJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public string $kodeKaryawan,
        public string $nama,
        public string $hp,
        public string $noTransaksi,
        public array $pesan,
    ) {}

    public function handle(): void
    {
        $response = Whatsapp::send_message($this->pesan, [
            'kode_karyawan' => $this->kodeKaryawan,
            'nama' => $this->nama,
            'hp' => $this->hp,
            'no_transaksi' => $this->noTransaksi,
            'module' => 'HCIS',
            'source_context' => 'EXPIRED_IZIN_COMMAND',
        ]);

        if (isset($response['error'])) {
            Log::channel('expiredIzin')->warning('Gagal mengirim pesan WhatsApp - Error API.', [
                'nama_approver' => $this->nama,
                'nomor_hp_approver' => $this->hp,
                'No_Transaksi' => $this->noTransaksi,
                'Kode_Karyawan' => $this->kodeKaryawan,
                'payload_whatsapp_dikirim' => $this->pesan,
                'respons_api_whatsapp' => $response,
            ]);

            return;
        }

        Log::channel('expiredIzin')->info('Pesan WhatsApp berhasil dikirim.', [
            'nama_approver' => $this->nama,
            'nomor_hp_approver' => $this->hp,
            'No_Transaksi' => $this->noTransaksi,
            'Kode_Karyawan' => $this->kodeKaryawan,
            'respons_api_whatsapp' => $response,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('expiredIzin')->error('Terjadi pengecualian saat mengirim pesan WhatsApp.', [
            'nama_approver' => $this->nama,
            'nomor_hp_approver' => $this->hp,
            'No_Transaksi' => $this->noTransaksi,
            'Kode_Karyawan' => $this->kodeKaryawan,
            'pesan_pengecualian' => $e->getMessage(),
        ]);
    }
}
