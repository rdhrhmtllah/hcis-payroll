<?php

namespace App\Services\KPI\PerformanceAppraisal\Katalog;

use Illuminate\Support\Facades\DB;

/**
 * Level jabatan yang memang tidak pernah dinilai Performance Appraisal.
 *
 * ── Kenapa kelas ini ada ──────────────────────────────────────────────────────
 *
 * Sampai 9 September 2026 jawabannya ditulis sebagai `private const LEVEL_FREELANCE = 21`
 * yang DISALIN di tiga service dan dibaca di lima titik bertugas berbeda: penanda "Non-PA"
 * di layar Matriks Kompetensi, pembebasan dari alarm "level berisi orang tanpa dimensi",
 * penandaan peserta DIKECUALIKAN saat penyiapan, penyaringan `levelYangDipakai()`, dan
 * cakupan level pada langkah penyiapan.
 *
 * Mengecualikan level kedua berarti menyunting kelimanya, dan satu yang terlewat menghasilkan
 * belahan yang tidak bisa ditangkap uji mana pun — karena kelimanya memang benar
 * sendiri-sendiri. Lupa satu dan layar berteriak selamanya tentang level yang sengaja
 * dikecualikan; lupa yang lain dan orangnya tetap menjadi peserta yang tak mungkin dinilai.
 *
 * Sekarang jawabannya SATU: tabel `N_PA_Level_Exclusion`, dan kelas ini satu-satunya yang
 * membacanya.
 *
 * ── Kenapa memonya per permintaan ─────────────────────────────────────────────
 *
 * `PaCyclePreparationService` menanyakannya SEKALI PER KARYAWAN — pada 958 orang itu 958
 * kueri untuk daftar yang panjangnya satu baris dan tidak berubah sedetik pun di antaranya.
 * Memonya statis supaya pemanggil yang tidak berbagi instance pun ikut menikmatinya.
 *
 * Memo statis di dalam proses yang panjang (worker antrean, perintah artisan) bisa menjawab
 * dengan keadaan yang sudah berubah, jadi `lupakan()` disediakan dan WAJIB dipanggil oleh
 * jalur yang menulis daftar ini. Uji yang mengubah isinya di tengah jalan juga memanggilnya.
 */
class PaLevelDikecualikan
{
    /** @var list<int>|null */
    private static ?array $memo = null;

    /** @var array<int, string>|null */
    private static ?array $memoAlasan = null;

    /**
     * Id level yang dikecualikan.
     *
     * @return list<int>
     */
    public static function daftar(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        self::muat();

        return self::$memo ?? [];
    }

    /** Apakah satu level dikecualikan dari PA. */
    public static function apakah(int|string|null $idLevel): bool
    {
        if ($idLevel === null || $idLevel === '') {
            return false;
        }

        return in_array((int) $idLevel, self::daftar(), true);
    }

    /**
     * Alasan pengecualian satu level, atau null bila level itu tidak dikecualikan.
     *
     * Alasannya dibawa sampai ke baris peserta: "DIKECUALIKAN" tanpa sebab menghasilkan
     * orang yang hilang dari penilaian tanpa siapa pun bisa menjawab kenapa.
     */
    public static function alasan(int|string|null $idLevel): ?string
    {
        if (! self::apakah($idLevel)) {
            return null;
        }

        return self::$memoAlasan[(int) $idLevel] ?? null;
    }

    /** Melupakan memo. WAJIB dipanggil sesudah daftarnya berubah. */
    public static function lupakan(): void
    {
        self::$memo = null;
        self::$memoAlasan = null;
    }

    private static function muat(): void
    {
        $baris = DB::table('N_PA_Level_Exclusion')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('ID_Level')
            ->get(['ID_Level', 'Alasan']);

        self::$memo = $baris->map(fn ($r) => (int) $r->ID_Level)->values()->all();
        self::$memoAlasan = $baris->mapWithKeys(fn ($r) => [(int) $r->ID_Level => (string) $r->Alasan])->all();
    }
}
