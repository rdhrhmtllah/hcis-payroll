<?php

namespace App\Console\Commands\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Menghitung hasil satu siklus penuh.
 *
 * KENAPA PERINTAH, BUKAN ENDPOINT
 *
 * Terukur pada database pengembangan: 8 kueri dan ±0,5 detik per peserta. Satu siklus
 * berisi seluruh karyawan aktif — 859 orang — sehingga sekali hitung memakan tujuh
 * menit lebih. Tidak ada batas waktu permintaan HTTP yang wajar bisa menampungnya, dan
 * menaikkan batas waktunya hanya memindahkan kegagalan ke tempat yang lebih sulit
 * dilihat.
 *
 * Endpoint `POST /cycles/{id}/hitung` tetap ada dan tetap benar — ia berguna untuk
 * siklus kecil dan untuk menghitung ulang sebagian. Untuk siklus penuh, inilah jalurnya.
 *
 * KENAPA DIPOTONG
 *
 * Diproses per potongan lalu dilanjutkan dari peserta terakhir. Kalau prosesnya putus di
 * tengah — koneksi jatuh, mesin dimatikan — yang sudah dihitung tetap tersimpan dan
 * `--mulai-dari` melanjutkannya tanpa mengulang dari awal.
 */
class PaHitungSiklusCommand extends Command
{
    protected $signature = 'pa:hitung-siklus
        {cycle : Id atau Kode_Cycle siklus}
        {--potongan=50 : Berapa peserta diproses per potongan}
        {--mulai-dari= : Lanjutkan dari Id_PA_Participant sesudah nomor ini}
        {--kering : Hanya menampilkan berapa yang akan dihitung, tanpa menulis apa pun}';

    protected $description = 'Menghitung hasil Performance Appraisal satu siklus penuh, bertahap';

    public function handle(PaResultCalculationService $perhitungan): int
    {
        $siklus = $this->siklus((string) $this->argument('cycle'));

        if ($siklus === null) {
            $this->error('Siklus tidak ditemukan.');

            return self::FAILURE;
        }

        $antre = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $siklus->Id_PA_Cycle)
            ->where('Status_Peserta', 'DIIKUTKAN')
            ->where('Flag_Aktif', 'Y')
            ->count();

        $this->info("Siklus #{$siklus->Id_PA_Cycle} {$siklus->Kode_Cycle} ({$siklus->Status_Cycle})");
        $this->info("Peserta DIIKUTKAN: {$antre}");

        if ($this->option('kering')) {
            // Perkiraan memakai angka terukur, bukan tebakan: ±0,5 detik per peserta.
            $this->line(sprintf('Perkiraan waktu: ~%.1f menit.', $antre * 0.51 / 60));
            $this->comment('Mode kering — tidak ada yang ditulis.');

            return self::SUCCESS;
        }

        if ($antre === 0) {
            $this->warn('Tidak ada peserta DIIKUTKAN; tidak ada yang dihitung.');

            return self::SUCCESS;
        }

        $potongan = max(1, (int) $this->option('potongan'));
        $mulaiDari = $this->option('mulai-dari') === null ? null : (int) $this->option('mulai-dari');

        $dihitung = 0;
        $tertahan = 0;
        $gagal = [];
        $diproses = 0;
        $mulaiWaktu = microtime(true);

        $bar = $this->output->createProgressBar($antre);
        $bar->start();

        try {
            do {
                $h = $perhitungan->hitungSiklus(
                    (int) $siklus->Id_PA_Cycle, null, $potongan, $mulaiDari
                );

                $dihitung += $h['dihitung'];
                $tertahan += $h['tertahan'];
                $gagal = array_merge($gagal, $h['gagal']);
                $diproses += $h['diproses'];
                $mulaiDari = $h['terakhir_id'] ?? $mulaiDari;

                $bar->advance($h['diproses']);
            } while (! $h['selesai'] && $h['diproses'] > 0);
        } catch (RuntimeException $e) {
            $bar->finish();
            $this->newLine(2);
            $this->error($e->getMessage());
            $this->warn(
                'Yang sudah dihitung tetap tersimpan. Lanjutkan dengan '
                . "--mulai-dari={$mulaiDari}"
            );

            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine(2);

        $detik = microtime(true) - $mulaiWaktu;

        $this->info(sprintf(
            'Selesai: %d dihitung, %d tertahan, %d gagal, dari %d peserta dalam %.1f menit (%.2f dtk/orang).',
            $dihitung, $tertahan, count($gagal), $diproses, $detik / 60,
            $diproses > 0 ? $detik / $diproses : 0
        ));

        if ($gagal !== []) {
            // Yang gagal disebutkan, bukan dijumlahkan saja: satu peserta gagal berarti
            // satu orang tidak punya hasil, dan itu harus bisa dicari.
            $this->newLine();
            $this->warn('Peserta yang gagal:');

            foreach (array_slice($gagal, 0, 20) as $g) {
                $this->line("  #{$g['participant_id']}: {$g['pesan']}");
            }

            if (count($gagal) > 20) {
                $this->line('  … dan '.(count($gagal) - 20).' lainnya.');
            }
        }

        return $gagal === [] ? self::SUCCESS : self::FAILURE;
    }

    private function siklus(string $acuan): ?object
    {
        $q = DB::table('N_PA_Cycle');

        return ctype_digit($acuan)
            ? $q->where('Id_PA_Cycle', (int) $acuan)->first(['Id_PA_Cycle', 'Kode_Cycle', 'Status_Cycle'])
            : $q->where('Kode_Cycle', $acuan)->first(['Id_PA_Cycle', 'Kode_Cycle', 'Status_Cycle']);
    }
}
