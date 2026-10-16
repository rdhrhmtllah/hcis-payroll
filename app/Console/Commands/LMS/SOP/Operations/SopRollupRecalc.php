<?php

namespace App\Console\Commands\LMS\SOP\Operations;

use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Hitung ulang N_SOP_Coverage_Rollup dari sumber kebenarannya: N_SOP_Progress
 * yang dimiliki penugasan AKTIF dan belum dicabut.
 *
 * Rollup dipelihara secara inkremental (bump saat assign/buka/konfirmasi, unbump
 * saat cabut). Selama pencabutan belum menurunkan Total_Target — diperbaiki pada
 * commit 040a1bdd — setiap akses yang dicabut meninggalkan target berlebih yang
 * tak pernah hilang sendiri. Command ini menyetel ulang angka lama agar sesuai
 * kenyataan; perilaku ke depan sudah ditangani kode.
 *
 * SELALU jalankan --dry-run lebih dulu untuk melihat dampaknya.
 */
class SopRollupRecalc extends Command
{
    protected $signature = 'sop:rollup-recalc
        {--dry-run : Tampilkan perubahan tanpa menulis apa pun}
        {--paket= : Batasi ke satu Id_SOP_Paket}';

    protected $description = 'Hitung ulang coverage rollup SOP dari data progres yang sebenarnya';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $paket = $this->option('paket') ? (int) $this->option('paket') : null;

        $rows = DB::table('N_SOP_Coverage_Rollup')
            ->when($paket, fn ($q) => $q->where('Id_SOP_Paket', $paket))
            ->orderBy('Id_SOP_Paket')->orderBy('Id_SOP_Item_Versi')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('Tidak ada baris rollup yang cocok.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '')."Memeriksa {$rows->count()} baris rollup...");

        $perubahan = [];
        foreach ($rows as $r) {
            $benar = $this->hitungSebenarnya((int) $r->Id_SOP_Paket, (int) $r->Id_SOP_Item_Versi);

            $persen = $benar['target'] > 0
                ? round($benar['konfirmasi'] / $benar['target'] * 100, 2)
                : 0.0;

            $sama = (int) $r->Total_Target === $benar['target']
                && (int) $r->Jumlah_Dibuka === $benar['dibuka']
                && (int) $r->Jumlah_Dikonfirmasi === $benar['konfirmasi']
                && abs((float) $r->Persen_Dikonfirmasi - $persen) < 0.01;

            if ($sama) {
                continue;
            }

            $perubahan[] = [
                'id' => (int) $r->Id_SOP_Coverage_Rollup,
                'paket' => (int) $r->Id_SOP_Paket,
                'versi' => (int) $r->Id_SOP_Item_Versi,
                'dari' => [(int) $r->Total_Target, (int) $r->Jumlah_Dibuka, (int) $r->Jumlah_Dikonfirmasi, (float) $r->Persen_Dikonfirmasi],
                'ke' => [$benar['target'], $benar['dibuka'], $benar['konfirmasi'], $persen],
            ];
        }

        if (empty($perubahan)) {
            $this->info('Semua baris sudah akurat. Tidak ada yang perlu diubah.');

            return self::SUCCESS;
        }

        $this->table(
            ['Paket', 'Versi', 'Target', 'Dibuka', 'Konfirmasi', 'Persen'],
            array_map(fn ($c) => [
                $c['paket'],
                $c['versi'],
                $c['dari'][0].' → '.$c['ke'][0],
                $c['dari'][1].' → '.$c['ke'][1],
                $c['dari'][2].' → '.$c['ke'][2],
                $c['dari'][3].' → '.$c['ke'][3],
            ], $perubahan),
        );

        $this->warn(count($perubahan).' baris menyimpang dari data progres yang sebenarnya.');

        if ($dryRun) {
            $this->info('[DRY-RUN] Tidak ada yang ditulis. Jalankan tanpa --dry-run untuk menerapkan.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Terapkan perubahan di atas ke database?', false)) {
            $this->info('Dibatalkan. Tidak ada yang ditulis.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($perubahan) {
            foreach ($perubahan as $c) {
                DB::table('N_SOP_Coverage_Rollup')
                    ->where('Id_SOP_Coverage_Rollup', $c['id'])
                    ->update([
                        'Total_Target' => $c['ke'][0],
                        'Jumlah_Dibuka' => $c['ke'][1],
                        'Jumlah_Dikonfirmasi' => $c['ke'][2],
                        'Persen_Dikonfirmasi' => $c['ke'][3],
                        'Updated_At' => now(),
                    ]);
            }
        });

        $this->info(count($perubahan).' baris rollup diperbarui.');

        return self::SUCCESS;
    }

    /**
     * Angka sebenarnya untuk satu (paket × versi).
     *
     * Definisi mengikuti pemelihara rollup di jalur normal: satu baris progres per
     * penugasan aktif yang belum dicabut. Status DIGANTIKAN sengaja tidak dihitung
     * sebagai dibuka maupun dikonfirmasi — baris itu peninggalan versi lama.
     *
     * @return array{target:int, dibuka:int, konfirmasi:int}
     */
    private function hitungSebenarnya(int $idPaket, int $idVersi): array
    {
        $r = DB::table('N_SOP_Progress as pr')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan_Detail', '=', 'pr.Id_SOP_Penjadwalan_Detail')
            ->join('N_SOP_Penjadwalan as h', 'h.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->where('pr.Id_SOP_Item_Versi', $idVersi)
            ->where('h.Id_SOP_Paket', $idPaket)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', SopStatus::INAKTIF)
            ->selectRaw(
                'COUNT(*) as target,
                 SUM(CASE WHEN pr.Status IN (?, ?, ?) THEN 1 ELSE 0 END) as dibuka,
                 SUM(CASE WHEN pr.Status IN (?, ?) THEN 1 ELSE 0 END) as konfirmasi',
                [
                    SopStatus::ITEM_DIBUKA, SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI,
                    SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI,
                ],
            )
            ->first();

        return [
            'target' => (int) ($r->target ?? 0),
            'dibuka' => (int) ($r->dibuka ?? 0),
            'konfirmasi' => (int) ($r->konfirmasi ?? 0),
        ];
    }
}
