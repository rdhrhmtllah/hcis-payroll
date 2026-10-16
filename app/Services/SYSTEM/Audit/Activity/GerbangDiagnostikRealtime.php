<?php

namespace App\Services\SYSTEM\Audit\Activity;

use Illuminate\Support\Facades\Config;

/**
 * Gerbang tiga kunci untuk pemantauan lock realtime.
 *
 * Pemantauan realtime membaca DMV berulang-ulang. Di dev itu murah. Di
 * PRODUKSI ia merusak: tiap sampel memakai satu koneksi dari pool yang sama
 * yang sedang habis, jadi ia menambah beban tepat pada saat sistemnya paling
 * rapuh — persis keadaan yang sedang ia coba jelaskan.
 *
 * Log produksi 1 Sep 2026 memperlihatkan bentuk kerapuhan itu: 212 "Login
 * timeout expired" dalam 11 menit, dengan permintaan menggantung sampai batas
 * 301 detik Cloud Run, dan NOL "Lock request time out" — jadi yang habis
 * memang koneksinya, bukan lock-nya. Menambah satu penyedot koneksi ke dalam
 * keadaan itu bukan pemantauan, itu bensin.
 *
 * KETIGA kunci harus setuju:
 *
 *   1. app.env ada di daftar lingkungan yang diizinkan
 *   2. sakelar 'realtime.enabled' menyala — DEFAULT MATI
 *   3. host DB tidak cocok satu pun pola terlarang
 *
 * Kunci ketiga ada karena dua yang pertama sama-sama env var: satu salah set
 * saat deploy meruntuhkan keduanya sekaligus. Host DB datang dari sumber lain,
 * jadi ia penahan yang benar-benar terpisah — bukan salinan kunci yang sama.
 */
class GerbangDiagnostikRealtime
{
    /** Boleh membaca DMV secara realtime? */
    public function boleh(): bool
    {
        return $this->alasanTertutup() === null;
    }

    /**
     * Alasan gerbangnya tertutup, atau null bila terbuka.
     *
     * Dipisah dari boleh() supaya layar bisa menerangkan KENAPA ia mati, bukan
     * sekadar kosong. Pemantau yang diam tanpa sebab menghabiskan waktu orang
     * yang mengira ia sedang bekerja.
     */
    public function alasanTertutup(): ?string
    {
        $lingkungan = (string) Config::get('app.env');
        $diizinkan = (array) Config::get('request-diagnostics.realtime.lingkungan', []);

        if (! in_array($lingkungan, $diizinkan, true)) {
            return "Lingkungan '{$lingkungan}' bukan lingkungan pengembangan.";
        }

        if (! (bool) Config::get('request-diagnostics.realtime.enabled', false)) {
            return 'Sakelar DB_REALTIME_WATCH_ENABLED masih mati.';
        }

        $host = $this->hostDb();
        foreach ((array) Config::get('request-diagnostics.realtime.host_terlarang', []) as $pola) {
            if ($pola !== '' && stripos($host, (string) $pola) !== false) {
                return "Host basis data mengandung '{$pola}' — terbaca sebagai produksi.";
            }
        }

        return null;
    }

    /**
     * Host basis data pada koneksi yang sedang dipakai.
     *
     * Dibaca dari config koneksi, bukan dari env langsung, supaya koneksi yang
     * ditimpa saat runtime (mis. saat uji) ikut terbaca apa adanya.
     */
    protected function hostDb(): string
    {
        $koneksi = (string) Config::get('database.default');

        return (string) Config::get("database.connections.{$koneksi}.host", '');
    }
}
