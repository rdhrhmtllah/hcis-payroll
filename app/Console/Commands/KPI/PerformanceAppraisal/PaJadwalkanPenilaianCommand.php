<?php

namespace App\Console\Commands\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaHcLearnScheduleService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Membuatkan pintu HC Learn bagi tiap penilai satu siklus.
 *
 * Bukan perintah simulasi. Ia jalur SUNGGUHAN yang kelak memanggil HC Learn; sekarang
 * jawabannya datang dari simulasi karena kontrak penjadwalan PA belum diserahkan. Yang
 * berubah nanti hanya isi `PaHcLearnScheduleService::jadwalDariHcLearn()`.
 *
 * KENAPA ADA PERINTAHNYA, BUKAN HANYA TOMBOL
 *
 * Penjadwalan 3.580 penugasan bisa gagal sebagian, dan perbaikannya adalah menjalankannya
 * ulang. Menjadikannya hanya tombol berarti satu-satunya jalan memperbaiki sisa yang bolong
 * adalah membuka layar, mencari siklusnya, dan menekan tombol yang sama — sementara yang
 * dibutuhkan sering kali sekadar mengulang dari terminal atau penjadwal.
 *
 * ── Identitas penanda tangan ──────────────────────────────────────────────────
 *
 * Di sini tidak pernah ada yang sedang masuk, jadi permintaannya ditandatangani AKUN LAYANAN
 * (`service_user_key` + `service_kode_users`). Sampai 10 September 2026 perintah ini berhenti
 * pada "Sambungan HC Learn belum tersedia" sebelum satu permintaan pun terkirim — sebab
 * `kirim()` hanya membaca `Auth::user()` — sementara saudaranya `pa:tarik-jadwal` sudah
 * mengurusnya sendiri. Satu aturan yang hidup di satu dari dua pintu setara.
 */
class PaJadwalkanPenilaianCommand extends Command
{
    protected $signature = 'pa:jadwalkan-penilaian
        {cycle : Id siklus PA}
        {--periksa : Hanya melaporkan keadaan, tanpa menulis apa pun}';

    protected $description = 'Membuatkan token HC Learn untuk penugasan penilai satu siklus PA';

    public function handle(PaHcLearnScheduleService $jadwal): int
    {
        $cycleId = (int) $this->argument('cycle');
        $awal = $jadwal->ringkasan($cycleId);

        $this->line("Siklus {$cycleId}");
        $this->line(sprintf(
            '  penugasan %d | berpintu %d | antre %d | gagal %d | belum %d',
            $awal['total'],
            $awal['terjadwal'],
            $awal['antre'],
            $awal['gagal'],
            $awal['belum'],
        ));

        if ($this->option('periksa')) {
            $this->info('Mode periksa: tidak ada yang ditulis.');

            return self::SUCCESS;
        }

        if ($awal['belum'] === 0) {
            $this->info('Seluruh penugasan sudah punya pintu. Tidak ada yang perlu dikerjakan.');

            return self::SUCCESS;
        }

        /* Identitas penanda tangannya diurus `PaHcLearnJadwalContract::identitas()`: sesi bila
           ada, akun layanan bila tidak — dan di sini memang tidak pernah ada sesi. Yang perlu
           dilakukan perintah ini hanya MENYAMPAIKAN sebabnya dengan bersih; melempar jejak
           tumpukan ke terminal membuat sebab yang sebenarnya sederhana terlihat seperti
           kerusakan. */
        try {
            $hasil = $jadwal->jadwalkanSiklus($cycleId, null);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line(sprintf(
            '  diminta %d | dititipkan %d | gagal %d | proses %d',
            $hasil['diminta'],
            $hasil['dititipkan'],
            $hasil['gagal'],
            count($hasil['proses']),
        ));

        /* DITITIPKAN, BUKAN SELESAI. `store` menjawab 202 dan menggarap antreannya
           belakangan; mengatakan "selesai" di sini akan membuat admin menutup terminal lalu
           heran kenapa penilainya belum punya tautan. */
        if ($hasil['dititipkan'] > 0) {
            $this->info('  Dititipkan ke antrean HC Learn. Tarik hasilnya dengan: php artisan pa:tarik-jadwal '.$cycleId);
        }

        foreach ($hasil['contoh_galat'] as $g) {
            $this->error('  '.$g);
        }

        if ($hasil['gagal'] > 0) {
            /* Kegagalan sebagian BUKAN sukses. Perintah yang memulangkan 0 atas 168 penugasan
               yang gagal membuat penjadwal otomatis menganggapnya beres, dan yang bolong baru
               ketahuan saat penilainya mengeluh. */
            $this->error("{$hasil['gagal']} penugasan gagal dijadwalkan. Jalankan ulang untuk mencoba sisanya.");

            return self::FAILURE;
        }

        $this->info('Selesai.');

        return self::SUCCESS;
    }
}
