<?php

namespace App\Jobs\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Menghitung hasil SATU peserta yang penilaiannya baru saja lengkap.
 *
 * ------------------------------------------------------------------------------------------
 * KENAPA LEWAT ANTREAN, BUKAN DI DALAM CALLBACK
 * ------------------------------------------------------------------------------------------
 *
 * Pemicunya adalah kiriman terakhir dari HC Learn. Menghitung di dalam permintaan callback itu
 * berarti HC Learn menunggu perhitungan kita — sekitar 1,2 detik pada latensi sekarang, dan
 * lebih lama bila KPI-nya banyak. Pengirim callback yang menunggu terlalu lama akan mengulang
 * kirimannya, dan pengulangan itu memicu perhitungan lagi atas kiriman yang sama.
 *
 * Callback harus cepat dan hanya menjawab "kiriman diterima". Sisanya urusan kita.
 *
 * ------------------------------------------------------------------------------------------
 * KEGAGALAN DI SINI TIDAK BOLEH MENGHILANGKAN KIRIMAN
 * ------------------------------------------------------------------------------------------
 *
 * Payload penilai sudah tersimpan dan terpetakan SEBELUM job ini berjalan. Kalau perhitungannya
 * gagal, yang hilang hanya angkanya — datanya utuh, dan admin bisa menghitung ulang lewat
 * tombolnya. Karena itu kegagalan dicatat dan didiamkan, bukan dilempar sampai membuat
 * pengirimnya mengulang.
 *
 * `RuntimeException` khususnya BUKAN kegagalan teknis melainkan aturan yang menolak: peserta
 * yang ternyata belum lengkap (ada kiriman yang dibatalkan di sela), hasil yang sudah terbit,
 * atau siklus yang keburu ditutup. Ketiganya jawaban yang benar, bukan sesuatu untuk diulang.
 */
class HitungHasilPesertaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    /* Sekali jalan. Perhitungan bersifat idempoten — menjalankannya dua kali menghasilkan
       versi baru yang isinya sama — tetapi versi yang bertambah tanpa sebab membuat riwayat
       perhitungan seseorang terbaca seperti ada yang berubah padahal tidak. */
    public $tries = 1;

    public function __construct(
        protected int $participantId,
        protected ?int $olehUserId = null,
    ) {}

    public function handle(PaResultCalculationService $perhitungan): void
    {
        try {
            $hasil = $perhitungan->hitung($this->participantId, $this->olehUserId);

            Log::info('PA: hasil peserta dihitung otomatis', [
                'participant_id' => $this->participantId,
                'status' => $hasil['status'] ?? null,
                'versi' => $hasil['versi'] ?? null,
            ]);
        } catch (RuntimeException $e) {
            /* Aturan yang menolak, bukan kerusakan. Dicatat pada tingkat info supaya tidak
               membanjiri log galat dengan keadaan yang memang wajar. */
            Log::info('PA: perhitungan otomatis dilewati', [
                'participant_id' => $this->participantId,
                'sebab' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            Log::error('PA: perhitungan otomatis gagal', [
                'participant_id' => $this->participantId,
                'galat' => $e->getMessage(),
            ]);
        }
    }
}
