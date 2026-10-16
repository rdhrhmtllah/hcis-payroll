<?php

namespace App\Services\LMS\SOP\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pembaca kebijakan global SOP (N_SOP_Setting).
 *
 * Tabel + seed-nya sudah ada sejak awal modul (03_N_SOP_seed.sql blok A), tapi
 * TIDAK PERNAH ada satu baris kode pun yang membacanya — semua ambang ditulis
 * keras di tempat pemakaiannya (dwell 3 dtk di SopPdfReader, reminder H-3 di
 * SopReminderScan). Kelas ini yang akhirnya menyambungkannya.
 *
 * Perilaku:
 *  - Seluruh tabel dibaca SEKALI lalu di-cache 10 menit (store database, sama
 *    dengan cache monitoring). Setting adalah data yang berubah setahun sekali;
 *    satu query per 10 menit sudah lebih dari cukup.
 *  - Di atas cache itu ada memo per-proses. Cache produksi memakai basis data
 *    (tak ada Redis), jadi TIAP pembacaan cache adalah satu kueri — dan
 *    int()/bool() dipanggil berkali-kali untuk nilai yang sama dalam satu
 *    permintaan. Memo memangkasnya menjadi satu.
 *  - Fallback WAJIB disebut pemanggil. Bila tabel kosong (skrip seed belum
 *    dijalankan di lingkungan lain) sistem tetap berperilaku seperti sebelum
 *    kelas ini ada — tidak ada fitur yang mendadak mati karena setting hilang.
 */
class SopSetting
{
    private const CACHE_KEY = 'sop_setting_all';

    private const TTL = 600;

    /**
     * Memo per-proses. Umumnya hidup selama satu permintaan HTTP saja, TETAPI
     * proses queue worker hidup berjam-jam: tanpa batas usia, seorang admin yang
     * mengubah setting tak pernah terlihat oleh worker sampai ia di-restart —
     * regresi terhadap cache basis data yang paling lama basi 10 menit. Karena
     * itu memo memakai batas usia yang sama dengan TTL cache.
     *
     * @var array<string,string>|null
     */
    private static ?array $memo = null;

    /** Titik waktu (microtime) saat memo di atas berhenti berlaku. */
    private static float $memoSampai = 0.0;

    /** @return array<string,string> peta Kode_Setting => Nilai */
    public static function semua(): array
    {
        if (self::$memo !== null && microtime(true) < self::$memoSampai) {
            return self::$memo;
        }

        try {
            $nilai = Cache::store('database')->remember(self::CACHE_KEY, self::TTL, function () {
                return DB::table('N_SOP_Setting')->pluck('Nilai', 'Kode_Setting')->all();
            });
        } catch (\Throwable $e) {
            Log::warning('SopSetting: gagal membaca — memakai fallback. '.$e->getMessage());

            /* Fallback SENGAJA tidak dimemo: satu kegagalan sesaat tidak boleh
               mengunci seluruh proses pada "tak ada setting sama sekali". */
            return [];
        }

        self::$memo = $nilai;
        self::$memoSampai = microtime(true) + self::TTL;

        return $nilai;
    }

    public static function get(string $kode, ?string $default = null): ?string
    {
        return self::semua()[$kode] ?? $default;
    }

    public static function int(string $kode, int $default): int
    {
        $v = self::get($kode);

        return is_numeric($v) ? (int) $v : $default;
    }

    public static function bool(string $kode, bool $default): bool
    {
        $v = self::get($kode);
        if ($v === null) {
            return $default;
        }

        return in_array(strtoupper(trim($v)), ['Y', '1', 'TRUE'], true);
    }

    /** Dipanggil bila admin kelak mengubah setting lewat UI. */
    public static function flush(): void
    {
        /* WAJIB dan urutannya di depan: tanpa baris ini permintaan yang MENULIS
           setting lalu membacanya kembali (updateSettings() → settingValues()
           pada respons yang sama) menerima nilai lama, dan hanya di jalur itu —
           cache basis data sudah bersih sementara memo proses belum. */
        self::$memo = null;
        self::$memoSampai = 0.0;

        Cache::store('database')->forget(self::CACHE_KEY);
    }
}
