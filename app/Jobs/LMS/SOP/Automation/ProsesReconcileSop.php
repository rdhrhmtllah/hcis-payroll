<?php

namespace App\Jobs\LMS\SOP\Automation;

use App\Services\LMS\SOP\SopReconciliationService;
use App\Services\LMS\SOP\Support\SopLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Reconcile paket SOP di latar belakang — jalur untuk paket berpenugasan banyak.
 *
 * reconcilePaket() menyisir setiap penugasan aktif x setiap item wajib. Pada
 * paket kecil biayanya tak terasa dan tetap dijalankan sinkron; pada paket besar
 * ia menahan permintaan publish selama belasan menit, jadi di atas ambang
 * pekerjaannya dipindahkan ke sini.
 *
 * Aman diulang: reconcilePaket idempoten (penjagaan exists mencegah seed dan
 * rollup ganda), dan sejak dipecah per potongan, potongan yang sudah commit
 * tidak dikerjakan lagi. Karena itu $tries > 1 tidak menggandakan apa pun.
 *
 * Statusnya TIDAK ditampilkan di Pusat Aktivitas: ini efek samping publikasi,
 * bukan pekerjaan yang diminta admin secara terpisah, dan hasilnya sudah tercatat
 * di jejak SOP lewat SopLog di dalam reconcilePaket(). Kegagalan totalnya dicatat
 * sebagai jejak tersendiri supaya tidak hilang tanpa jejak.
 */
class ProsesReconcileSop implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 3;

    public function __construct(
        public int $idPaket,
        public string $alasan = 'MANUAL',
        public ?int $userId = null,
    ) {
        // Queue yang sama dengan penjadwalan — nol infrastruktur baru.
        $this->onQueue(config('queue.names.penjadwalan_training'));
    }

    public function handle(SopReconciliationService $svc): void
    {
        $svc->reconcilePaket($this->idPaket, $this->alasan, $this->userId);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SOP reconcile paket gagal total', ['id_paket' => $this->idPaket, 'exception' => $e]);

        SopLog::catat(
            'RECONCILE',
            'Reconcile latar belakang GAGAL — kewajiban karyawan mungkin belum tersemai. '
                .'Jalankan reconcile manual pada paket ini.',
            $this->idPaket, null, null, null,
            ['alasan' => $this->alasan, 'oleh_id_users' => $this->userId, 'pesan' => mb_substr($e->getMessage(), 0, 300)],
        );
    }
}
