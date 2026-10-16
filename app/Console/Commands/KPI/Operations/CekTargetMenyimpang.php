<?php

namespace App\Console\Commands\KPI\Operations;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cari nilai target yang menyimpang jauh dari saudara sebarisnya.
 *
 * Latar: pada data dev ditemukan satu parameter dengan target bulanan 100 di
 * sebelas bulan dan 100100 di satu bulan — hampir pasti salah ketik. Nilai
 * seperti itu ikut masuk penyebut YTD (target dijumlahkan dari baris yang ada),
 * jadi satu digit kelebihan bisa menjatuhkan achievement satu tahun penuh
 * tanpa gejala apa pun di layar.
 *
 * MEMBACA SAJA. Tidak ada perbaikan otomatis: nilai yang benar hanya diketahui
 * pemilik prosesnya, jadi memperbaikinya lewat perintah justru berbahaya.
 *
 * Contoh:
 *   php artisan kpi:cek-target-menyimpang
 *   php artisan kpi:cek-target-menyimpang --faktor=5 --tahun=2026
 */
class CekTargetMenyimpang extends Command
{
    protected $signature = 'kpi:cek-target-menyimpang
        {--faktor=10 : Berapa kali median sebelum dianggap menyimpang}
        {--min-baris=3 : Jumlah minimum baris agar sebuah parameter dinilai}
        {--tahun= : Batasi ke satu tahun periode}
        {--kode= : Batasi ke satu Kode_Karyawan}';

    protected $description = 'Laporkan nilai target KPI yang menyimpang jauh dari median parameternya';

    public function handle(): int
    {
        $faktor = max(2.0, (float) $this->option('faktor'));
        $minBaris = max(2, (int) $this->option('min-baris'));

        $this->info("Ambang: menyimpang bila > {$faktor}x median atau < median/{$faktor}");
        $this->info("Parameter dinilai bila punya minimal {$minBaris} baris target");
        $this->line('');

        $baris = $this->ambilBaris();

        if ($baris->isEmpty()) {
            $this->warn('Tidak ada baris target yang cocok dengan filter.');

            return self::SUCCESS;
        }

        $temuan = [];

        foreach ($baris->groupBy('Id_Pengajuan_Detail') as $rows) {
            if ($rows->count() < $minBaris) {
                continue;
            }

            $nilai = $rows->pluck('Nilai_Target')->map(fn ($v) => (float) $v);
            $median = $this->median($nilai->all());

            if ($median <= 0) {
                continue; // median nol → rasio tidak bermakna
            }

            foreach ($rows as $r) {
                $v = (float) $r->Nilai_Target;

                if ($v <= 0) {
                    continue;
                }

                $rasio = $v / $median;

                if ($rasio > $faktor || $rasio < 1 / $faktor) {
                    $temuan[] = [
                        'nama' => $r->Nama,
                        'kode' => $r->Kode_Karyawan,
                        'parameter' => $r->Nama_Parameter,
                        'periode' => $r->Periode,
                        'nilai' => $v,
                        'median' => $median,
                        'rasio' => $rasio,
                        'no_transaksi' => $r->No_Transaksi,
                    ];
                }
            }
        }

        if (empty($temuan)) {
            $this->info('Bersih: tidak ada nilai target yang menyimpang di atas ambang.');

            return self::SUCCESS;
        }

        usort($temuan, fn ($a, $b) => $b['rasio'] <=> $a['rasio']);

        foreach ($temuan as $t) {
            $this->line(sprintf(
                '<fg=yellow>%s</> (%s) %s | %s | nilai %s vs median %s = <fg=red>%sx</> | %s',
                $t['nama'],
                $t['kode'],
                $t['periode'],
                mb_strimwidth($t['parameter'], 0, 28, '…'),
                $t['nilai'],
                $t['median'],
                round($t['rasio'], 1),
                $t['no_transaksi'],
            ));
        }

        $this->line('');
        $this->info('Total nilai menyimpang: '.count($temuan));
        $this->warn('Tidak ada yang diubah — nilai yang benar hanya diketahui pemilik proses.');

        return self::SUCCESS;
    }

    private function ambilBaris()
    {
        $query = DB::table('KPI_Pengajuan_Det as d')
            ->join('KPI_Pengajuan_Detail as dt', 'dt.Id_Pengajuan_Detail', '=', 'd.Id_Pengajuan_Detail')
            ->join('KPI_Pengajuan as kp', 'kp.Id_Pengajuan', '=', 'dt.Id_Pengajuan')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'kp.Id_User')
            ->where('kp.Flag_Aktif', 'Y')
            ->whereNull('kp.Status')
            ->select(
                'd.Nilai_Target',
                'd.Periode',
                'dt.Id_Pengajuan_Detail',
                'dt.Nama_Parameter',
                'kp.No_Transaksi',
                'k.Nama',
                'k.Kode_Karyawan',
            );

        if ($tahun = $this->option('tahun')) {
            $query->where('d.Periode', 'like', $tahun.'-%');
        }

        if ($kode = $this->option('kode')) {
            $query->where('k.Kode_Karyawan', $kode);
        }

        return $query->get();
    }

    private function median(array $nilai): float
    {
        sort($nilai);
        $n = count($nilai);

        if ($n === 0) {
            return 0.0;
        }

        $tengah = intdiv($n, 2);

        return $n % 2 === 1
            ? (float) $nilai[$tengah]
            : ((float) $nilai[$tengah - 1] + (float) $nilai[$tengah]) / 2;
    }
}
