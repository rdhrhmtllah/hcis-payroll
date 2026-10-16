<?php

namespace App\Console\Commands\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaAssignmentBuilderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Membentuk penugasan penilai untuk seluruh peserta satu siklus, dari usulan yang sudah ada.
 *
 * KENAPA PERINTAH INI ADA
 *
 * Relasi organisasi yang lengkap TIDAK dengan sendirinya membentuk penugasan. Ia menyediakan
 * CALON; penugasannya masih harus dibuat. Itu pembedaan yang paling mudah disalahpahami di
 * modul ini — layar Langkah 5 melaporkan "859 peserta belum memenuhi kuota" tepat ketika
 * relasinya baru saja dilengkapi, karena yang diukurnya penugasan, bukan relasi.
 *
 * Halaman Penugasan Penilai bekerja per peserta. Untuk 859 peserta itu bukan pekerjaan yang
 * bisa diselesaikan seseorang, jadi jalur massalnya perlu ada.
 *
 * KENAPA MELALUI `tugaskan()`, BUKAN MENULIS LANGSUNG
 *
 * Semua penjaga tinggal di sana: kategori yang bukan milik levelnya ditolak, penilai yang
 * melewati `maks_rater` ditolak, penilai kembar ditolak, dan bobot kategori dihitung ulang
 * sesudahnya. Menulis langsung ke tabel akan melewati semuanya dan menghasilkan penugasan yang
 * tampak benar lalu hilang dari hasil tanpa jejak.
 *
 * Konsekuensinya perintah ini TIDAK cepat: satu peserta beberapa kueri, dan pada DB dev satu
 * kueri ~165 ms. Itu harga dari penjaga yang tetap berlaku, dan dilaporkan sebagai perkiraan
 * durasi sebelum mulai.
 *
 * YANG DIPILIHNYA
 *
 * Sebanyak `Min_Rater` per kategori — cukup untuk memenuhi kuota, tidak lebih. `--penuh`
 * mengisi sampai `Maks_Rater`. Yang sudah cukup dilewati, jadi perintah ini bisa dijalankan
 * berulang tanpa menggandakan apa pun.
 */
class PaBuatPenugasanMassalCommand extends Command
{
    protected $signature = 'pa:buat-penugasan
        {cycle : Id siklus}
        {--dry-run : Hitung dan laporkan saja, tidak menulis apa pun}
        {--penuh : Isi sampai Maks_Rater, bukan sekadar Min_Rater}
        {--batas=0 : Batasi jumlah peserta yang diproses (0 = semua). Untuk mencoba dulu}';

    protected $description = 'Membentuk penugasan penilai massal untuk satu siklus PA.';

    public function handle(PaAssignmentBuilderService $pembangun): int
    {
        $cycleId = (int) $this->argument('cycle');
        $kering = (bool) $this->option('dry-run');
        $penuh = (bool) $this->option('penuh');
        $batas = max(0, (int) $this->option('batas'));

        $siklus = DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)
            ->first(['Kode_Cycle', 'Status_Cycle']);

        if ($siklus === null) {
            $this->error("Siklus {$cycleId} tidak ada.");

            return self::FAILURE;
        }

        $jumlah = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)->where('Status_Peserta', 'DIIKUTKAN')->where('Flag_Aktif', 'Y')
            ->when($batas > 0, fn ($q) => $q->limit($batas))
            ->count();

        $this->info("Siklus {$siklus->Kode_Cycle} ({$siklus->Status_Cycle}) — {$jumlah} peserta");
        $this->line($penuh ? 'Mengisi sampai Maks_Rater.' : 'Mengisi sampai Min_Rater saja.');

        /* Perkiraan durasi disebut SEBELUM mulai. Perintah yang berjalan belasan menit tanpa
           memberi tahu akan dihentikan orang di tengah jalan, dan penugasan separuh jadi lebih
           sulit ditelusuri daripada nol penugasan. */
        $this->line(sprintf('Perkiraan: ~%d menit (setiap peserta beberapa kueri).', (int) ceil($jumlah * 2 / 60)));
        $this->newLine();

        /* Alurnya HIDUP DI SERVICE, bukan di sini. Layar memanggil yang sama; menyalinnya akan
           melahirkan dua cara membentuk penugasan dengan hasil yang berbeda. */
        $h = $pembangun->bentukMassal($cycleId, $penuh, $batas, null, $kering);

        $this->info(sprintf(
            '%s%d penugasan untuk %d peserta.',
            $kering ? '[dry-run] akan dibuat ' : 'Dibuat ',
            $h['dibuat'],
            $h['peserta_tersentuh'],
        ));
        $this->line("Sudah cukup, dilewati: {$h['sudah_cukup']} peserta");
        $this->line("Masih tanpa penugasan sama sekali: {$h['belum_berpenugasan']} peserta");

        if ($h['gagal'] !== []) {
            $this->newLine();
            $this->warn(count($h['gagal']).' peserta gagal:');

            foreach (array_slice($h['gagal'], 0, 10) as $g) {
                $this->line('  '.$g);
            }

            if (count($h['gagal']) > 10) {
                $this->line('  ... dan '.(count($h['gagal']) - 10).' lagi');
            }
        }

        return self::SUCCESS;
    }
}
