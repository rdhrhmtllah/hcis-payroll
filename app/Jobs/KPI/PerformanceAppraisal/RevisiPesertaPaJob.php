<?php

namespace App\Jobs\KPI\PerformanceAppraisal;

use App\Jobs\KPI\PerformanceAppraisal\Concerns\BertindakSebagaiPemulai;
use App\Services\KPI\PerformanceAppraisal\PaHcLearnScheduleService;
use App\Services\KPI\PerformanceAppraisal\PaPenjadwalanTugasService;
use App\Services\KPI\PerformanceAppraisal\PaRevisiPesertaService;
use App\Services\KPI\PerformanceAppraisal\PaRevisiTugasService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Revisi peserta banyak orang, BERANTAI — PER_JOB orang per job.
 *
 * Tiap orang lewat `PaRevisiPesertaService::jalankan()` sendiri-sendiri (transaksinya sendiri,
 * penjaganya sendiri); yang gagal dicatat beserta sebabnya, yang lain tetap jalan. Penjadwalan
 * ke HC Learn TIDAK per orang — sekali di akhir, untuk semua penugasan yang baru terbentuk.
 */
class RevisiPesertaPaJob implements ShouldQueue
{
    use BertindakSebagaiPemulai, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const PER_JOB = 10;

    public $timeout = 900;

    public $tries = 1;

    public function __construct(public string $taskId) {}

    public function handle(
        PaRevisiTugasService $tugas,
        PaRevisiPesertaService $revisi,
        PaHcLearnScheduleService $jadwal,
        PaPenjadwalanTugasService $tugasJadwal,
    ): void {
        $baris = $tugas->baris($this->taskId);

        if (! $baris || $tugas->dihentikan($this->taskId) || ! $tugas->klaim($this->taskId)) {
            return;
        }

        $p = $tugas->payload($baris);
        $cycleId = (int) ($p['cycle_id'] ?? 0);
        $userId = isset($p['user_id']) ? (int) $p['user_id'] : null;
        $orang = (array) ($p['orang'] ?? []);
        $indeks = (int) ($p['indeks'] ?? 0);
        $hasil = (array) ($p['hasil'] ?? []);
        $r = (array) ($p['ringkasan'] ?? []) + ['total' => count($orang), 'sudah' => 0, 'berhasil' => 0, 'gagal' => 0, 'dibentuk' => 0, 'dibatalkan' => 0];

        $this->bertindakSebagai($userId);

        try {
            foreach (array_slice($orang, $indeks, self::PER_JOB) as $o) {
                try {
                    $h = $revisi->jalankan((string) $p['tindakan'], $cycleId, (string) $o['kode_perusahaan'], (string) $o['kode_karyawan'], (string) $p['alasan'], $userId, false, (array) ($p['opsi'] ?? []));
                    $hasil[] = ['kode_karyawan' => $o['kode_karyawan'], 'nama' => $o['nama'] ?? $o['kode_karyawan'], 'ok' => true,
                        'pesan' => ($h['tanpa_pengganti'] ?? []) !== [] || ($h['dibatalkan'] === 0 && $h['dibentuk'] === 0) ? mb_substr($h['catatan'], 0, 300) : null,
                        'dibatalkan' => $h['dibatalkan'], 'dibentuk' => $h['dibentuk']];
                    $r['berhasil']++;
                    $r['dibentuk'] += (int) $h['dibentuk'];
                    $r['dibatalkan'] += (int) $h['dibatalkan'];
                } catch (RuntimeException $e) {
                    $hasil[] = ['kode_karyawan' => $o['kode_karyawan'], 'nama' => $o['nama'] ?? $o['kode_karyawan'], 'ok' => false,
                        'pesan' => mb_substr($e->getMessage(), 0, 300), 'dibatalkan' => 0, 'dibentuk' => 0];
                    $r['gagal']++;
                }
                $indeks++;
                $r['sudah'] = $indeks;
            }

            $tugas->catat($this->taskId, ['indeks' => $indeks, 'batch' => (int) ($p['batch'] ?? 0) + 1, 'hasil' => $hasil, 'ringkasan' => $r]);

            if ($indeks < count($orang) && ! $tugas->dihentikan($this->taskId)) {
                self::dispatch($this->taskId);

                return;
            }

            /* SEKALI di akhir: semua penugasan yang baru terbentuk dikirim ke HC Learn. */
            $idJadwal = null;
            if ($r['dibentuk'] > 0) {
                /* Penilai baru dipastikan dikenal HC Learn SEBELUM jadwalnya dikirim; yang
                   ditolak disebut di tugas ini, bukan muncul belakangan sebagai jadwal GAGAL. */
                $tugas->catat($this->taskId, ['hclearn' => $revisi->pastikanPenilaiBaruTerdaftar($cycleId)]);
                $mulai = $tugasJadwal->mulai($cycleId, $jadwal->ringkasan($cycleId), $userId,
                    optional(optional(Auth::user())->karyawan)->Kode_Karyawan);
                if ($mulai['baru']) {
                    PenjadwalanHcLearnPaJob::dispatch($mulai['id']);
                }
                $idJadwal = $mulai['id'];
                $tugas->catat($this->taskId, ['penjadwalan' => $idJadwal]);
            }

            if (! $tugas->dihentikan($this->taskId)) {
                $tugas->selesai($this->taskId);
            }
        } catch (Throwable $e) {
            Log::error('PA: revisi peserta latar gagal', ['task' => $this->taskId, 'galat' => $e->getMessage()]);
            $tugas->catat($this->taskId, ['indeks' => $indeks, 'hasil' => $hasil, 'ringkasan' => $r]);
            $tugas->gagal($this->taskId, $e->getMessage());
        }
    }

    public function failed(Throwable $e): void
    {
        app(PaRevisiTugasService::class)->gagal($this->taskId, $e->getMessage());
    }
}
