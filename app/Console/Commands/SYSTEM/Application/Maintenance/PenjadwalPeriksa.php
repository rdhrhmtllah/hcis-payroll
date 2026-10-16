<?php

namespace App\Console\Commands\SYSTEM\Application\Maintenance;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

/**
 * Periksa penjadwal berbasis basis data: entri yang menunjuk perintah yang tidak ada.
 *
 * ── Kenapa perintah ini ada ──────────────────────────────────────────────────
 *
 * Jadwal HCIS tidak ditulis di kode melainkan di tabel N_HRIS_Scheduler_Configs,
 * dan Console\Kernel mendaftarkannya apa adanya:
 *
 *     $schedule->command($config->Command)->cron(...)
 *
 * Laravel tidak memeriksa keberadaan perintahnya saat MENDAFTAR — ia baru gagal
 * saat cron-nya menyala, dengan "Command ... is not defined" yang mendarat di
 * storage/logs/scheduler.log dan tidak ke mana-mana lagi. Akibatnya sebuah baris
 * bisa berdiri bertahun-tahun dengan Flag_Aktif = 'Y', terbaca oleh siapa pun
 * yang membuka tabel itu sebagai "pekerjaan ini berjalan tiap malam", padahal
 * tidak pernah berjalan sama sekali.
 *
 * Diukur pada Web_HRIS (2026-09-08): dari 21 entri aktif, TIGA menunjuk perintah
 * yang tidak ada di branch mana pun — sop:recert-scan (perintahnya dihapus
 * bersama pencabutan masa berlaku SOP), talent-pool:kedaluwarsa (tidak pernah
 * ada di riwayat), dan hcis:kalender (didaftarkan 3 hari sebelum kodenya ada).
 *
 * ── Satu hal yang SUDAH aman, dan disebut supaya tidak dicemaskan ────────────
 *
 * Seluruh perulangan pendaftaran dibungkus satu try/catch. Kalau pendaftaran
 * sebuah entri melempar, seluruh jadwal SESUDAHNYA ikut hilang diam-diam. Itu
 * sudah diperiksa: `schedule:list` menampilkan kedua puluh satu entri, jadi
 * nama yang tidak dikenal TIDAK melempar saat mendaftar. Kegagalannya terisolasi
 * pada entri itu sendiri.
 *
 * ── Kenapa perintah, bukan uji ───────────────────────────────────────────────
 *
 * Jawabannya ada di BASIS DATA, dan berbeda antar lingkungan: entri yang yatim
 * di sini bisa sah di produksi, dan sebaliknya. Sebuah uji yang membaca basis
 * data pengembangan akan menjawab pertanyaan yang salah — dan ikut mengunci
 * suite pada koneksi bersama. Perintah ini bisa dijalankan di kedua tempat.
 *
 * HANYA MEMBACA. Tidak mengubah satu baris pun.
 */
class PenjadwalPeriksa extends Command
{
    protected $signature = 'hris:penjadwal-periksa
        {--semua : Tampilkan juga entri yang sehat}
        {--abaikan= : Prefix perintah yang dimaklumi, dipisah koma (mis. pa:)}';

    protected $description = 'Periksa N_HRIS_Scheduler_Configs: entri yang menunjuk perintah tidak ada (hanya baca)';

    public function handle(Kernel $kernel): int
    {
        $terdaftar = array_keys($kernel->all());

        $abaikan = array_values(array_filter(array_map(
            'trim', explode(',', (string) $this->option('abaikan'))
        )));

        $baris = DB::table('N_HRIS_Scheduler_Configs')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Command')
            ->get();

        $yatim = [];
        $dimaklumi = [];
        $sehat = [];

        foreach ($baris as $r) {
            /*
             * Kolomnya menyimpan perintah LENGKAP beserta opsinya
             * ("hris:absen-plan-rolling --kode_perusahaan=001 ..."), jadi yang
             * dibandingkan hanya kata pertamanya.
             */
            $nama = self::namaPerintah((string) $r->Command);
            if ($nama === '') {
                continue;
            }

            if (in_array($nama, $terdaftar, true)) {
                $sehat[] = $r;

                continue;
            }

            if (self::dimaklumi($nama, $abaikan)) {
                $dimaklumi[] = $r;

                continue;
            }

            $yatim[] = $r;
        }

        $this->line(sprintf('%d entri aktif; %d yatim, %d dimaklumi, %d sehat',
            $baris->count(), count($yatim), count($dimaklumi), count($sehat)));
        $this->newLine();

        if ($yatim !== []) {
            $this->line('=== MENUNJUK PERINTAH YANG TIDAK ADA ===');
            $this->line('Entri ini menyala sesuai cron-nya lalu gagal dengan');
            $this->line('"Command is not defined"; jejaknya hanya di scheduler.log.');
            $this->newLine();
            foreach ($yatim as $r) {
                $this->line(sprintf('  %-30s cron=%-14s %s',
                    self::namaPerintah((string) $r->Command),
                    (string) $r->Cron_Expression,
                    substr((string) ($r->Keterangan ?? ''), 0, 60)));
            }
            $this->newLine();
        }

        if ($dimaklumi !== []) {
            $this->line('=== dimaklumi lewat --abaikan (mis. fitur yang ditahan) ===');
            foreach ($dimaklumi as $r) {
                $this->line('  '.self::namaPerintah((string) $r->Command));
            }
            $this->newLine();
        }

        if ($this->option('semua')) {
            $this->line('=== sehat ===');
            foreach ($sehat as $r) {
                $this->line('  '.self::namaPerintah((string) $r->Command));
            }
            $this->newLine();
        }

        $this->line('Jawabannya milik lingkungan ini saja: entri yang yatim di sini bisa');
        $this->line('sah di lingkungan lain. Jalankan di keduanya sebelum menonaktifkan.');

        /*
         * Sengaja SUKSES walau ada yatim: perintah ini alat periksa, dan
         * membuatnya gagal akan menjadikannya penghalang di pipeline yang
         * kebetulan memanggilnya. Yang menilai manusia.
         */
        return self::SUCCESS;
    }

    /** Kata pertama dari kolom Command — sisanya opsi. */
    public static function namaPerintah(string $mentah): string
    {
        $mentah = trim($mentah);
        if ($mentah === '') {
            return '';
        }

        return explode(' ', $mentah)[0];
    }

    /** @param  list<string>  $prefix */
    public static function dimaklumi(string $nama, array $prefix): bool
    {
        foreach ($prefix as $p) {
            if ($p !== '' && str_starts_with($nama, $p)) {
                return true;
            }
        }

        return false;
    }
}
