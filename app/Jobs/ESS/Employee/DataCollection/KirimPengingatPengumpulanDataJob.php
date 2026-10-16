<?php

namespace App\Jobs\ESS\Employee\DataCollection;

use App\Services\SYSTEM\Notification\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Menaburkan notifikasi pengingat pengisian data, satu potongan penerima.
 *
 * Dulu seluruhnya dikerjakan di dalam permintaan admin: satu klik memanggil
 * upsertByKey() sekali per orang — 2 kueri masing-masing. Diukur pada populasi
 * dev: 952 sasaran => 1.904 kueri dalam SATU permintaan sinkron, sekitar 9,5
 * detik pada latensi produksi, sambil menahan satu slot PHP-FPM.
 *
 * Penaburannya kini di latar dan DIPOTONG, jadi tidak ada satu pekerjaan pun
 * yang memegang koneksi terlalu lama.
 *
 * Ditulis lewat NotificationService, BUKAN langsung ke tabelnya. Menyisipkan
 * borongan sendiri memang lebih sedikit kueri, tetapi melewati penyeragaman
 * di layanan itu — termasuk 'source' yang disaring topbar. Notifikasi yang
 * tersimpan tetapi tak pernah muncul adalah kegagalan yang lebih buruk
 * daripada lambat, dan uji tabel tetap hijau saat itu terjadi.
 */
class KirimPengingatPengumpulanDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    public $tries = 3;

    /**
     * @param  list<string>  $kodeKaryawan  satu potongan penerima
     * @param  string  $tanggal  dipakai di notification_key supaya pengulangan
     *                           job pada hari yang sama tidak menggandakan
     *                           notifikasi — upsertByKey memperbarui yang ada.
     */
    public function __construct(
        protected array $kodeKaryawan,
        protected string $tanggal,
    ) {
        $this->onQueue((string) config('queue.names.default', 'default'));
    }

    public function handle(NotificationService $notifikasi): void
    {
        foreach ($this->kodeKaryawan as $kode) {
            try {
                $notifikasi->upsertByKey([
                    'kode_karyawan' => $kode,
                    'type' => 'PENGUMPULAN_DATA_REMINDER',
                    'title' => 'Data karyawanmu belum diperbarui',
                    'message' => 'Lengkapi data karyawanmu — hanya butuh dua menit, dan cukup sekali.',
                    'module' => 'HCIS',
                    'severity' => 'warning',
                    'action_url' => '/pengumpulan-data',
                    'notification_key' => "PENGUMPULAN_DATA_REMINDER|{$kode}|{$this->tanggal}",
                ]);
            } catch (\Throwable $e) {
                // Satu penerima yang gagal tidak boleh menjatuhkan sisanya:
                // percobaan ulang seluruh potongan hanya akan mengulang yang
                // sudah berhasil, dan yang bermasalah tetap bermasalah.
                Log::warning('Pengingat pengumpulan data gagal untuk satu penerima', [
                    'kode_karyawan' => $kode,
                    'pesan' => $e->getMessage(),
                ]);
            }
        }
    }
}
