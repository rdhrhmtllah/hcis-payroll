<?php

namespace App\Services\SYSTEM\Application\Diagnostics;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Menyalakan lampu ketika panggilan jaringan terjadi DI DALAM transaksi.
 *
 * MENGAPA RUNTIME, BUKAN PEMINDAI.
 * Cacat ini sudah dua kali terbukti nyata di sistem ini — temuan #2 (LMS,
 * `Http::timeout(8)` di dalam transaksi) dan #18 (`createTask()` ke Google Cloud
 * Tasks sambil memegang `lockForUpdate`). Keduanya ditemukan dengan MEMBACA
 * rantai panggilannya, bukan oleh pemindai.
 *
 * Pemindai statis memang tak bisa menemukannya. Panggilan jaringannya bersarang
 * dua lapis ke dalam:
 *
 *     DB::transaction( ... lockForUpdate() ...
 *         -> dispatchExecutionTaskById()
 *             -> cloudTaskService->dispatch()
 *                 -> client()->createTask()      <- jaringan
 *     )
 *
 * Penyisiran atas 70 titik kunci membuktikannya: dengan batas metode yang benar
 * pemindai hanya menyisakan 3 kandidat, dan KETIGANYA positif palsu — sementara
 * satu-satunya cacat yang nyata tak muncul sama sekali.
 *
 * Runtime tahu apa yang pemindai tak bisa tahu: pada saat panggilan itu benar
 * terjadi, `DB::transactionLevel()` bicara apa adanya, sedalam apa pun
 * bersarangnya.
 *
 * MENGAPA HANYA MEMPERINGATKAN.
 * Melempar akan mengubah perilaku produksi untuk cacat yang perbaikannya masih
 * menunggu keputusan produk (apa yang harus terjadi bila panggilan jaringan
 * gagal SESUDAH commit). Sampai itu dijawab, yang dibutuhkan adalah membuat
 * kejadiannya TERLIHAT — bukan menjatuhkannya.
 *
 * MENGAPA BUKAN CUMA DI DEV.
 * Justru produksi yang perlu tahu. Di dev, transaksi selesai dalam milidetik
 * dan tak ada yang mengantre di belakangnya; di produksi, dengan RCSI mati,
 * setiap pembaca baris yang terkunci menunggu selama panggilan jaringan itu
 * berlangsung. Peringatan ini murah — satu pemeriksaan integer.
 */
class PeringatanJaringanDalamTransaksi
{
    /**
     * @param  string  $apa  Nama panggilan jaringannya, untuk log. Mis. "Cloud Tasks createTask".
     * @param  array<string,mixed>  $konteks  Keterangan tambahan (id, antrean, url).
     * @return bool true bila memang sedang di dalam transaksi (dipakai uji).
     */
    public static function periksa(string $apa, array $konteks = []): bool
    {
        $tingkat = DB::transactionLevel();

        if ($tingkat < 1) {
            return false;
        }

        Log::warning('Panggilan jaringan dijalankan DI DALAM transaksi basis data.', array_merge($konteks, [
            'panggilan' => $apa,
            'tingkat_transaksi' => $tingkat,
            'akibat' => 'Baris yang terkunci menahan pembaca lain selama panggilan ini berlangsung '
                .'(RCSI mati di basis data ini).',
            'pemanggil' => self::pemanggil(),
        ]));

        return true;
    }

    /**
     * Titik pemanggil pertama di luar kelas ini.
     *
     * Tanpa ini, log hanya menunjuk ke dalam service jaringannya sendiri dan
     * tak menolong siapa pun menemukan transaksi mana yang bersalah.
     */
    private static function pemanggil(): string
    {
        /* Dibandingkan dengan __FILE__ secara TEPAT, bukan lewat potongan nama.
           Percobaan pertama memakai str_contains('PeringatanJaringanDalamTransaksi')
           dan ikut melewati berkas pemanggil yang namanya kebetulan memuat
           potongan itu — ujinya sendiri, yang bernama ...TransaksiTest.php.
           Akibatnya log menunjuk ke dalam PHPUnit, bukan ke kode yang bersalah. */
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 12) as $bingkai) {
            $berkas = $bingkai['file'] ?? '';
            if ($berkas === '' || $berkas === __FILE__) {
                continue;
            }

            return str_replace(base_path().DIRECTORY_SEPARATOR, '', $berkas).':'.($bingkai['line'] ?? '?');
        }

        return '(tak diketahui)';
    }
}
