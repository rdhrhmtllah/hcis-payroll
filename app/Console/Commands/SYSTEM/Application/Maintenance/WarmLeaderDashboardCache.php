<?php

namespace App\Console\Commands\SYSTEM\Application\Maintenance;

use App\Services\ESS\Dashboard\Homepage\HomepageLeaderDashboardService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pra-hitung (WARM) cache dashboard leader yang EAGER agar cache HIT saat rush.
 *
 * Yang di-warm per leader (sesuai yang dimuat saat kunjungan):
 *  - getGlobalSummary  -> cache count antrian approval (union-9-view).
 *  - getHcisSummary    -> cache attendance context tim (SP absen; bagian TERBERAT).
 * KPI/LMS/Antrian-Approval/Training TIDAK di-warm (kini on-click).
 *
 * KENAPA: saat banyak leader buka bersamaan (rush), tiap TIM berbeda = satu komputasi
 * SP absen "dingin" (~5.5 dtk) di jalur web + menahan koneksi. Command ini memindahkan
 * komputasi itu ke worker CLI (di luar tier web) & MENYEBARnya (sekuensial + throttle),
 * sehingga saat leader membuka dashboard, hasilnya sudah ada di cache (HIT, cepat).
 *
 * PENJADWALAN (oleh user): jalankan menjelang + selama jam rush pagi, dengan interval
 * <= TTL cache attendance (kini 180s). Mis. tiap 2 menit pada jam 06:00-09:59
 * (cron minute-field "slash-2", hour-field "6-9") menjalankan:
 *   php artisan leader-dashboard:warm-cache
 * Atau daftarkan di N_HRIS_Scheduler_Configs (Flag_Aktif='Y').
 *
 * CATATAN: command ini menjalankan SP absen untuk tiap tim → beban DB latar. Setelah
 * Fase 1 (lock keluar transaksi) di-deploy, beban baca ini tidak lagi ke-blok. Gunakan
 * --sleep untuk mengatur seberapa "halus" penyebaran bebannya.
 */
class WarmLeaderDashboardCache extends Command
{
    protected $signature = 'leader-dashboard:warm-cache
        {--sleep=150 : Jeda milidetik antar leader (penyebaran beban). 0 = tanpa jeda.}
        {--limit=0 : Batasi jumlah leader diproses (0 = semua). Untuk uji.}';

    protected $description = 'Warm cache eager dashboard leader (global summary + HCIS attendance) agar cache HIT saat rush.';

    public function handle(HomepageLeaderDashboardService $service): int
    {
        $startedAt = microtime(true);
        $sleepMs = max(0, (int) $this->option('sleep'));
        $limit = max(0, (int) $this->option('limit'));

        // Leader = Kode_Karyawan_Team pada HRIS_Karyawan_Team (yang aktif).
        $leaders = DB::table('HRIS_Karyawan_Team as team')
            ->join('Karyawan as k', 'team.Kode_Karyawan_Team', '=', 'k.Kode_Karyawan')
            ->where('k.Aktif', 'Y')
            ->whereNotNull('team.Kode_Karyawan_Team')
            ->where('team.Kode_Karyawan_Team', '<>', '')
            ->distinct()
            ->pluck('team.Kode_Karyawan_Team')
            ->map(fn ($k) => (string) $k)
            ->filter()
            ->unique()
            ->values();

        if ($limit > 0) {
            $leaders = $leaders->take($limit);
        }

        $total = $leaders->count();
        $this->info("Warm cache dashboard untuk {$total} leader (sleep {$sleepMs}ms)...");

        $ok = 0;
        $fail = 0;
        $index = 0;
        foreach ($leaders as $kode) {
            try {
                // Urutan: global (approval count) dulu, lalu HCIS (SP absen berat).
                $service->getGlobalSummary($kode);
                $service->getHcisSummary($kode);
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
                Log::channel('homepageLog')->warning('WarmLeaderDashboardCache gagal', [
                    'leader_kode' => $kode,
                    'error' => $e->getMessage(),
                ]);
            }

            $index++;
            if ($sleepMs > 0 && $index < $total) {
                usleep($sleepMs * 1000);
            }
        }

        $durationS = round(microtime(true) - $startedAt, 1);
        $this->info("Selesai: {$ok} ok, {$fail} gagal, {$durationS} dtk.");
        Log::channel('homepageLog')->info('WarmLeaderDashboardCache selesai', [
            'total' => $total,
            'ok' => $ok,
            'fail' => $fail,
            'duration_s' => $durationS,
        ]);

        return self::SUCCESS;
    }
}
