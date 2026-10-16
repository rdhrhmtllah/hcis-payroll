<?php

namespace App\Services\SYSTEM\Application\Database;

use Illuminate\Support\Facades\Schema;

/**
 * "Apakah tabel/kolom ini ada?" — ditanyakan ke basis data SEKALI per proses.
 *
 * ── Kenapa ini perlu ada sama sekali ─────────────────────────────────────────
 *
 * Di SQL Server, `Schema::hasTable()` TIDAK dikompilasi menjadi lookup ringan.
 * Laravel menerjemahkannya menjadi kueri yang mengagregasi UKURAN SELURUH TABEL
 * di basis data:
 *
 *     select t.name, SCHEMA_NAME(t.schema_id), sum(u.total_pages) * 8 * 1024
 *       from sys.tables t
 *       join sys.partitions p on ...
 *       join sys.allocation_units u on ...
 *      group by t.name, t.schema_id
 *
 * Diukur di basis data ini lewat dm_exec_query_stats: **46.285 halaman dibaca
 * dan 99 ms CPU untuk SATU panggilan**, dan kueri itu tercatat **4.457 kali
 * dengan total 442 detik CPU** — pemakan CPU terbesar dari sisi aplikasi ini.
 *
 * Yang membuatnya menyakitkan: jawabannya SELALU SAMA. Tabel tidak muncul atau
 * hilang di tengah permintaan, dan worker antrean pun tidak mengubah skema.
 *
 * ── Kenapa STATIC, bukan properti instance ───────────────────────────────────
 *
 * Service pemakai umumnya tidak terdaftar sebagai singleton, jadi tiap
 * `app(...)` membuat objek baru dan cache tingkat-instance tidak akan pernah
 * kena. Cache proses adalah satu-satunya tingkat yang benar-benar bekerja.
 *
 * ── Kenapa dipusatkan ────────────────────────────────────────────────────────
 *
 * Pola memo yang sama sudah disalin ke tiga tempat berbeda
 * (ApproverResolverService, ApprovalMatrixService, HandleInertiaRequests),
 * sementara belasan pemanggil lain tidak memakainya sama sekali. Itu bentuk
 * "aturan hanya ada di sebagian jalur" — yang di repo ini sudah berkali-kali
 * jadi sumber bug. Satu rumah, satu perilaku.
 */
class SkemaTersedia
{
    /** @var array<string, bool> */
    private static array $tabel = [];

    /** @var array<string, bool> */
    private static array $kolom = [];

    /**
     * `Schema::hasTable()` yang hanya menyentuh basis data sekali per nama tabel.
     */
    public static function tabel(?string $nama): bool
    {
        if (! $nama) {
            return false;
        }

        if (! array_key_exists($nama, self::$tabel)) {
            /* Kegagalan (koneksi putus, izin katalog) TIDAK di-cache: menyimpan
               "false" karena gangguan sesaat akan mematikan fitur selama sisa
               proses, dan pada worker antrean "sisa proses" bisa berjam-jam. */
            self::$tabel[$nama] = Schema::hasTable($nama);
        }

        return self::$tabel[$nama];
    }

    /**
     * Semua tabel ada? Berhenti pada yang pertama tidak ada.
     */
    public static function semuaTabel(string ...$nama): bool
    {
        foreach ($nama as $t) {
            if (! self::tabel($t)) {
                return false;
            }
        }

        return true;
    }

    /**
     * `Schema::hasColumn()` yang hanya menyentuh basis data sekali per pasangan.
     *
     * Lebih murah daripada hasTable (ia hanya menyentuh sys.columns), tetapi
     * tetap satu perjalanan penuh ke basis data — dan di lingkungan ini satu
     * perjalanan kosong saja sudah puluhan milidetik.
     */
    public static function kolom(string $tabel, string $kolom): bool
    {
        $kunci = $tabel.'.'.$kolom;

        if (! array_key_exists($kunci, self::$kolom)) {
            self::$kolom[$kunci] = Schema::hasColumn($tabel, $kolom);
        }

        return self::$kolom[$kunci];
    }

    /**
     * Lupakan yang sudah diingat.
     *
     * Ada untuk UJI yang membuat atau menghapus tabel di tengah proses. Di
     * jalur aplikasi ini tidak pernah diperlukan — dan kalau suatu saat terasa
     * diperlukan di sana, yang salah bukan cache ini melainkan kode yang
     * mengubah skema saat permintaan sedang berjalan.
     */
    public static function lupakan(): void
    {
        self::$tabel = [];
        self::$kolom = [];
    }
}
