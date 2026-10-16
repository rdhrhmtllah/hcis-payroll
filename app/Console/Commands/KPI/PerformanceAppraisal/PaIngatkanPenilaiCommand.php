<?php

namespace App\Console\Commands\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaNotificationService;
use App\Services\KPI\PerformanceAppraisal\PaPesertaService;
use Illuminate\Console\Command;

/**
 * Pengingat harian bagi penilai yang tenggatnya mendekat atau sudah lewat.
 *
 * KENAPA PERINTAH, BUKAN DIPICU KEJADIAN
 *
 * Penugasan dibentuk sekali dan diberitahukan sekali; sesudah itu tidak ada kejadian apa
 * pun yang bisa memicu pengingat. Yang berubah hanyalah TANGGAL, dan hanya sesuatu yang
 * berjalan menurut waktu yang bisa memperhatikannya.
 *
 * AMAN DIJALANKAN BERULANG
 *
 * Kuncinya memuat tanggal, sehingga menjalankannya dua kali dalam sehari tidak
 * menghasilkan dua pemberitahuan. Itu disengaja: perintah yang hanya aman dijalankan
 * sekali akan menakuti orang untuk menjalankannya ulang setelah gagal separuh jalan.
 */
class PaIngatkanPenilaiCommand extends Command
{
    protected $signature = 'pa:ingatkan-penilai
        {--ambang= : Berapa hari sebelum tenggat mulai diingatkan (baku: ambang layar peserta)}
        {--hanya-terlambat : Hanya kirim pengingat keterlambatan}
        {--hanya-mendekat : Hanya kirim pengingat tenggat mendekat}';

    protected $description = 'Mengirim pengingat in-app kepada penilai PA yang tenggatnya mendekat atau terlewat.';

    public function handle(PaNotificationService $pemberitahuan): int
    {
        $ambang = (int) ($this->option('ambang') ?: PaPesertaService::AMBANG_DEKAT_HARI);

        if ($ambang < 0) {
            $this->error('Ambang hari tidak boleh negatif.');

            return self::FAILURE;
        }

        $hanyaTerlambat = (bool) $this->option('hanya-terlambat');
        $hanyaMendekat = (bool) $this->option('hanya-mendekat');

        if ($hanyaTerlambat && $hanyaMendekat) {
            $this->error('Pilih salah satu: --hanya-terlambat atau --hanya-mendekat, bukan keduanya.');

            return self::FAILURE;
        }

        $mendekat = 0;
        $terlambat = 0;

        if (! $hanyaTerlambat) {
            $mendekat = $pemberitahuan->tenggatMendekat($ambang);
            $this->info(sprintf('Tenggat mendekat (<= %d hari): %d pemberitahuan.', $ambang, $mendekat));
        }

        if (! $hanyaMendekat) {
            $terlambat = $pemberitahuan->sudahTerlambat();
            $this->info(sprintf('Sudah terlambat: %d pemberitahuan.', $terlambat));
        }

        /* Nol bukan kegagalan — siklus yang belum dibuka atau yang semua penilaiannya
           sudah selesai memang tidak punya siapa pun untuk diingatkan. Tetapi disebut
           terang-terangan, karena perintah yang diam saat tidak melakukan apa-apa membuat
           orang mengira ia rusak. */
        if ($mendekat + $terlambat === 0) {
            $this->line('Tidak ada penugasan yang perlu diingatkan hari ini.');
        }

        return self::SUCCESS;
    }
}
