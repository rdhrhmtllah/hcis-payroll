<?php

namespace App\Console\Commands\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Menghitung ulang hasil PA yang TERTAHAN, untuk siklus yang masih berjalan.
 *
 * ------------------------------------------------------------------------------------------
 * KENAPA PERLU DISAPU BERKALA
 * ------------------------------------------------------------------------------------------
 *
 * `TERTAHAN` hampir selalu bersebab di LUAR modul ini: KPI belum disetujui, karyawan belum
 * punya akun KPI, bobot parameter KPI menyimpang dari 100. Ketiganya diperbaiki orang lain di
 * modul lain, dan modul KPI tidak mengenal PA sama sekali — nol referensi, dan itu memang
 * sebaiknya begitu.
 *
 * Akibatnya ada lubang yang tidak bisa ditutup pemicu biasa. Perhitungan otomatis menempel
 * pada kedatangan kiriman PENILAI; kalau yang menyusul belakangan adalah persetujuan KPI,
 * tidak ada kiriman PA baru, sehingga tidak ada yang men-dispatch apa pun. Orangnya diam di
 * TERTAHAN sampai ada manusia yang membukanya.
 *
 * Perintah ini menutup lubang itu dengan cara paling murah yang ada: bertanya ulang.
 *
 * ------------------------------------------------------------------------------------------
 * AMAN DIJALANKAN BERULANG
 * ------------------------------------------------------------------------------------------
 *
 * Peserta yang sebabnya belum hilang akan TERTAHAN lagi — tanpa efek samping selain nomor
 * versi yang naik. Yang sudah TERBIT tidak ikut tersentuh sama sekali.
 */
class PaSapuTertahanCommand extends Command
{
    protected $signature = 'pa:sapu-tertahan
        {cycle? : Id siklus. Kosongkan untuk menyapu seluruh siklus yang berjalan}
        {--batas=50 : Berapa peserta per siklus sekali jalan}';

    protected $description = 'Menghitung ulang hasil PA yang TERTAHAN — dipakai saat KPI menyusul disetujui';

    public function handle(PaResultCalculationService $perhitungan): int
    {
        $cycleId = $this->argument('cycle');
        $batas = max(1, (int) $this->option('batas'));

        /* Hanya siklus yang MASIH BERJALAN. `PUBLISHED` tidak mengizinkan operasi apa pun,
           dan DRAFT/PREPARED/LOCKED belum punya hasil untuk disapu. */
        $siklus = DB::table('N_PA_Cycle')
            ->where('Flag_Aktif', 'Y')
            ->whereIn('Status_Cycle', ['OPEN', 'CLOSED'])
            ->when($cycleId !== null, fn ($q) => $q->where('Id_PA_Cycle', (int) $cycleId))
            ->orderBy('Id_PA_Cycle')
            ->get(['Id_PA_Cycle', 'Kode_Cycle', 'Status_Cycle']);

        if ($siklus->isEmpty()) {
            $this->info('Tidak ada siklus berjalan yang perlu disapu.');

            return self::SUCCESS;
        }

        $adaGagal = false;

        foreach ($siklus as $s) {
            $this->line(sprintf('Siklus %s (%s, %s)', $s->Id_PA_Cycle, $s->Kode_Cycle, $s->Status_Cycle));

            try {
                $h = $perhitungan->sapuTertahan((int) $s->Id_PA_Cycle, null, $batas);
            } catch (\Throwable $e) {
                $adaGagal = true;
                $this->error('  '.$e->getMessage());

                continue;
            }

            $this->line(sprintf(
                '  diperiksa %d | jadi dihitung %d | tetap tertahan %d | gagal %d',
                $h['diperiksa'], $h['jadi_dihitung'], $h['tetap_tertahan'], $h['gagal'],
            ));

            foreach ($h['contoh_galat'] as $g) {
                $this->warn('  '.$g);
            }

            if ($h['gagal'] > 0) {
                $adaGagal = true;
            }

            /* Konteks per siklus dilupakan supaya siklus berikutnya tidak memakai pita nilai,
               kuadran, dan bobot milik siklus sebelumnya. */
            $perhitungan->lupakanKonteks();
        }

        return $adaGagal ? self::FAILURE : self::SUCCESS;
    }
}
