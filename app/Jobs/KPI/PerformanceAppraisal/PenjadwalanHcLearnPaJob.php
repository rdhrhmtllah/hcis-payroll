<?php

namespace App\Jobs\KPI\PerformanceAppraisal;

use App\Jobs\KPI\PerformanceAppraisal\Concerns\BertindakSebagaiPemulai;
use App\Services\KPI\PerformanceAppraisal\PaHcLearnScheduleService;
use App\Services\KPI\PerformanceAppraisal\PaPenjadwalanTugasService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Menjadwalkan rater satu siklus ke HC Learn, DI LATAR — satu batch per job, berantai.
 *
 * ------------------------------------------------------------------------------------------
 * DUA FASE, SATU TUGAS
 * ------------------------------------------------------------------------------------------
 *
 *   kirim   tiap job menitipkan paling banyak BATAS_PER_JOB penugasan yang belum berpintu,
 *           lalu mengantrekan job berikutnya selama masih ada yang belum terkirim.
 *   tunggu  HC Learn menjawab 202 dan menggarap antreannya belakangan. Tiap job menarik
 *           hasilnya, lalu mengantrekan dirinya lagi JEDA_TUNGGU detik kemudian — sampai tak
 *           ada yang antre, atau BATAS_TUNGGU_MENIT habis.
 *
 * Berantai, bukan satu job panjang: 10.000 penugasan dalam satu permintaan melewati batas waktu
 * Cloud Tasks, dan job yang terputus di tengah tidak meninggalkan jejak di mana ia berhenti.
 * Per batch, tiap job pendek dan keadaannya tercatat di baris tugas sebelum job berikutnya
 * diantrekan.
 *
 * ------------------------------------------------------------------------------------------
 * KEGAGALAN KIRIM MENGHENTIKAN KIRIMAN, BUKAN PENANTIANNYA
 * ------------------------------------------------------------------------------------------
 *
 * Penugasan yang gagal dikirim tidak meninggalkan baris aktif, jadi kueri "belum berpintu"
 * memilihnya lagi PALING DEPAN di batch berikutnya. Mengulang kiriman berarti menabrak tembok
 * yang sama tiap batch. Jadi kiriman berhenti, yang sudah dititipkan tetap ditunggu, dan
 * tugasnya berakhir "gagal" dengan sebabnya — admin membereskannya lalu menekan Coba Lagi.
 */
class PenjadwalanHcLearnPaJob implements ShouldQueue
{
    use BertindakSebagaiPemulai, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** 3 kiriman x 150 rater. Tiap kiriman paling lama 120 detik — jauh di bawah batas job. */
    public const BATAS_PER_JOB = 450;

    public const JEDA_TUNGGU = 30;

    public const BATAS_TUNGGU_MENIT = 120;

    public $timeout = 900;

    /* Sekali jalan. Mengulang otomatis atas galat yang sama hanya menggandakan kiriman yang
       gagal; pengulangan adalah keputusan admin lewat "Coba Lagi". */
    public $tries = 1;

    public function __construct(public string $taskId) {}

    public function handle(PaPenjadwalanTugasService $tugas, PaHcLearnScheduleService $jadwal): void
    {
        $baris = $tugas->baris($this->taskId);

        /* Dihentikan admin, disembunyikan, atau sudah selesai: tak ada yang dikerjakan. */
        if (! $baris || $tugas->dihentikan($this->taskId) || ! $tugas->klaim($this->taskId)) {
            return;
        }

        $p = $tugas->payload($baris);
        $cycleId = (int) ($p['cycle_id'] ?? 0);
        $userId = isset($p['user_id']) ? (int) $p['user_id'] : null;
        $batch = (int) ($p['batch'] ?? 0) + 1;
        $galat = (array) ($p['contoh_galat'] ?? []);

        $this->bertindakSebagai($userId);

        try {
            if (($p['fase'] ?? PaPenjadwalanTugasService::FASE_KIRIM) === PaPenjadwalanTugasService::FASE_KIRIM) {
                $hasil = $jadwal->jadwalkanSiklus($cycleId, $userId, self::BATAS_PER_JOB);
                $r = $jadwal->ringkasan($cycleId);
                $galat = array_slice(array_merge($galat, $hasil['contoh_galat'] ?? []), 0, 5);
                $kirimGagal = ($hasil['gagal'] ?? 0) > 0;

                if (! $kirimGagal && ($hasil['diminta'] ?? 0) > 0 && $r['belum'] > 0) {
                    $tugas->catat($this->taskId, ['batch' => $batch, 'ringkasan' => $r, 'contoh_galat' => $galat]);
                    $this->lanjutkan();

                    return;
                }

                if ($r['antre'] > 0) {
                    $tugas->catat($this->taskId, [
                        'fase' => PaPenjadwalanTugasService::FASE_TUNGGU,
                        'mulai_tunggu' => now()->toDateTimeString(),
                        'batch' => $batch,
                        'ringkasan' => $r,
                        'contoh_galat' => $galat,
                    ]);

                    if (! $this->tungguLagi()) {
                        $this->akhiri($tugas, $r, $batch, $galat);
                    }

                    return;
                }

                $this->akhiri($tugas, $r, $batch, $galat);

                return;
            }

            $tarik = $jadwal->tarikStatus($cycleId, $userId);
            $r = $jadwal->ringkasan($cycleId);

            /* KEMBALI KE FASE KIRIM bila ada penugasan baru yang belum berpintu — revisi atau
               ganti penilai selagi tugas ini menunggu HC Learn. Tugas yang berjalan DIPAKAI
               ULANG oleh pemanggil baru, jadi tanpa ini penugasan barunya tak pernah dikirim. */
            if ($r['belum'] > 0) {
                $tugas->catat($this->taskId, ['fase' => PaPenjadwalanTugasService::FASE_KIRIM, 'batch' => $batch, 'ringkasan' => $r]);
                $this->lanjutkan();

                return;
            }
            $mulai = isset($p['mulai_tunggu']) ? Carbon::parse($p['mulai_tunggu']) : now();
            $masihBoleh = $mulai->diffInMinutes(now()) < self::BATAS_TUNGGU_MENIT;

            if ($r['antre'] > 0 && $masihBoleh) {
                /* Galat tarik di sini SESAAT — HC Learn yang lambat menjawab bukan kegagalan.
                   Dicatat supaya terlihat, tidak menghentikan penantian. */
                $tugas->catat($this->taskId, [
                    'batch' => $batch,
                    'ringkasan' => $r,
                    'galat_tarik' => $tarik['contoh_galat'][0] ?? null,
                ]);

                if ($this->tungguLagi()) {
                    return;
                }
            }

            $this->akhiri($tugas, $r, $batch, $galat);
        } catch (Throwable $e) {
            Log::error('PA: penjadwalan latar gagal', ['task' => $this->taskId, 'cycle_id' => $cycleId, 'galat' => $e->getMessage()]);
            $tugas->gagal($this->taskId, $e->getMessage());
        }
    }

    private function lanjutkan(): void
    {
        self::dispatch($this->taskId);
    }

    /**
     * Mengantrekan penarikan berikutnya.
     *
     * Pada antrean `sync` (test, atau mesin lokal tanpa Cloud Tasks) jeda tidak berlaku — job
     * berikutnya jalan SAAT ITU JUGA, berulang tanpa jeda selama dua jam. Di sana tugasnya
     * langsung diakhiri dan sisa antrean ditarik lewat tombol "Cek Sekarang" di langkah 5.
     *
     * @return bool false bila tidak diantrekan — pemanggil yang mengakhiri tugasnya.
     */
    private function tungguLagi(): bool
    {
        if (config('queue.default') === 'sync') {
            return false;
        }

        self::dispatch($this->taskId)->delay(now()->addSeconds(self::JEDA_TUNGGU));

        return true;
    }

    /** Akhir tugas: selesai bila semua bertautan, selain itu gagal dengan sebab yang terbaca. */
    private function akhiri(PaPenjadwalanTugasService $tugas, array $r, int $batch, array $galat): void
    {
        $tugas->catat($this->taskId, ['batch' => $batch, 'ringkasan' => $r, 'contoh_galat' => $galat]);

        if ($r['belum'] === 0 && $r['antre'] === 0 && $r['gagal'] === 0) {
            $tugas->selesai($this->taskId);

            return;
        }

        $sebab = [];
        if ($r['belum'] > 0) {
            $sebab[] = "{$r['belum']} belum terkirim".($galat ? ' ('.$galat[0].')' : '');
        }
        if ($r['antre'] > 0) {
            $sebab[] = "{$r['antre']} masih antre di HC Learn";
        }
        if ($r['gagal'] > 0) {
            $sebab[] = "{$r['gagal']} ditolak HC Learn";
        }

        $tugas->gagal($this->taskId, implode('; ', $sebab).'.');
    }

    public function failed(Throwable $e): void
    {
        app(PaPenjadwalanTugasService::class)->gagal($this->taskId, $e->getMessage());
    }
}
