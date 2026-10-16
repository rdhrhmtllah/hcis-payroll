<?php

namespace App\Console\Commands\KPI\Operations;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Periksa keutuhan band penilaian di KPI_Master_Performance.
 *
 * Band disimpan sebagai pasangan Range_Awal..Range_Akhir. Kalau ada celah di
 * antaranya (mis. 0-74 lalu 75-89 → 74,5 tidak masuk band mana pun), setiap
 * konsumen menanganinya dengan caranya sendiri:
 *
 *   - KpiScoringService:130, KpiEnforcementService:663, KpiCapParameterKalkulator
 *     mengabaikan Range_Akhir: ">= Range_Awal" lalu ambil yang tertinggi.
 *   - HomepageKpiService:2372 mencocokkan tepat dulu, baru jatuh ke aturan di atas.
 *   - updatePerformance.vue:968 mengikuti aturan ">= Range_Awal".
 *
 * Jadi celahnya selama ini tertutup oleh jaring pengaman, bukan oleh datanya.
 * Perintah ini memperlihatkan celahnya supaya bisa dirapikan di data master —
 * satu perbaikan di sana membuat semua konsumen sepakat tanpa menyentuh kode.
 *
 * MEMBACA SAJA.
 */
class CekBandPerformance extends Command
{
    protected $signature = 'kpi:cek-band-performance';

    protected $description = 'Periksa celah dan tumpang tindih band KPI_Master_Performance';

    public function handle(): int
    {
        $band = DB::table('KPI_Master_Performance')->orderBy('Range_Awal')->get();

        if ($band->isEmpty()) {
            $this->error('KPI_Master_Performance kosong — tidak ada band penilaian sama sekali.');

            return self::FAILURE;
        }

        $this->info('Band terdaftar:');
        foreach ($band as $b) {
            $this->line(sprintf(
                '   %-4s %8s .. %-8s %s',
                $b->Kode ?? '-',
                $b->Range_Awal,
                $b->Range_Akhir,
                $b->Keterangan,
            ));
        }
        $this->line('');

        $celah = $this->cariCelah($band);
        $tumpang = $this->cariTumpangTindih($band);

        if (empty($celah)) {
            $this->info('Tidak ada celah antar band.');
        } else {
            $this->warn('Celah antar band ('.count($celah).'):');
            foreach ($celah as $c) {
                $this->line("   > {$c['dari']} .. < {$c['sampai']}  (setelah {$c['kode_sebelum']}, sebelum {$c['kode_sesudah']})");
            }
        }

        if (! empty($tumpang)) {
            $this->line('');
            $this->warn('Band tumpang tindih ('.count($tumpang).'):');
            foreach ($tumpang as $t) {
                $this->line("   {$t['a']} bertumpuk dengan {$t['b']}");
            }
        }

        $this->line('');
        $this->periksaNilaiNyata($band, 'Total_Score pada KPI_Update_Performance', $this->ambilSkor());
        $this->line('');
        $this->periksaNilaiNyata($band, 'Achievement pada KPI_Update_Performance_Detail', $this->ambilAchievement());

        if (! empty($celah)) {
            $this->line('');
            $this->warn('Saran: rapatkan band di data master (mis. 0-74,99 / 75-89,99 / 90-105,99 / 106+).');
            $this->warn('Satu perbaikan di data membuat seluruh konsumen sepakat tanpa mengubah kode.');
        }

        return self::SUCCESS;
    }

    private function cariCelah($band): array
    {
        $celah = [];
        $sebelumnya = null;

        foreach ($band as $b) {
            if ($sebelumnya !== null) {
                $akhir = (float) $sebelumnya->Range_Akhir;
                $awal = (float) $b->Range_Awal;

                // Bersinggungan dianggap rapat bila selisihnya <= 0,01.
                if ($awal - $akhir > 0.01) {
                    $celah[] = [
                        'dari' => $akhir,
                        'sampai' => $awal,
                        'kode_sebelum' => $sebelumnya->Kode ?? '-',
                        'kode_sesudah' => $b->Kode ?? '-',
                    ];
                }
            }

            $sebelumnya = $b;
        }

        return $celah;
    }

    private function cariTumpangTindih($band): array
    {
        $hasil = [];
        $daftar = $band->values();

        for ($i = 0; $i < $daftar->count(); $i++) {
            for ($j = $i + 1; $j < $daftar->count(); $j++) {
                $a = $daftar[$i];
                $b = $daftar[$j];

                if ((float) $a->Range_Awal <= (float) $b->Range_Akhir
                    && (float) $b->Range_Awal <= (float) $a->Range_Akhir) {
                    $hasil[] = [
                        'a' => ($a->Kode ?? '-')." ({$a->Range_Awal}..{$a->Range_Akhir})",
                        'b' => ($b->Kode ?? '-')." ({$b->Range_Awal}..{$b->Range_Akhir})",
                    ];
                }
            }
        }

        return $hasil;
    }

    private function ambilSkor()
    {
        return DB::table('KPI_Update_Performance as kup')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'kup.Id_User')
            ->where('kup.Flag_Aktif', 'Y')
            ->whereNotNull('kup.Total_Score')
            ->select('k.Nama', 'kup.Periode', 'kup.Total_Score as nilai')
            ->get();
    }

    private function ambilAchievement()
    {
        return DB::table('KPI_Update_Performance_Detail as d')
            ->join('KPI_Update_Performance as kup', 'kup.Id_Update_Performance', '=', 'd.Id_Update_Performance')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'kup.Id_User')
            ->where('kup.Flag_Aktif', 'Y')
            ->whereNotNull('d.Achievement')
            ->select('k.Nama', 'kup.Periode', 'd.Achievement as nilai')
            ->get();
    }

    private function periksaNilaiNyata($band, string $judul, $nilai): void
    {
        $luar = $nilai->filter(function ($n) use ($band) {
            $v = (float) $n->nilai;

            foreach ($band as $b) {
                if ($v >= (float) $b->Range_Awal && $v <= (float) $b->Range_Akhir) {
                    return false;
                }
            }

            return true;
        });

        $this->info("{$judul}: {$nilai->count()} nilai, ".$luar->count().' di luar band');

        foreach ($luar->take(20) as $n) {
            $this->line("   {$n->Nama} {$n->Periode} = {$n->nilai}");
        }

        if ($luar->count() > 20) {
            $this->line('   ... dan '.($luar->count() - 20).' lainnya');
        }
    }
}
