<?php

namespace App\Services\SYSTEM\Application\Database;

use Illuminate\Database\QueryException;
use Throwable;

/**
 * Pengenalan deadlock SQL Server, satu tempat.
 *
 * ── Kenapa dipusatkan ────────────────────────────────────────────────────────
 *
 * isDeadlockException() ada DUA salinan identik — di LmsModulAbsensiController
 * dan TrainingExecutionService. Keduanya memutuskan hal yang sama: apakah sebuah
 * kegagalan pantas DICOBA ULANG atau harus dilempar ke pemanggil.
 *
 * Salinan pada keputusan coba-ulang punya dua arah gagal yang sama buruknya.
 * Kalau satu sisi diperlonggar, ia mengulang operasi yang seharusnya gagal —
 * dan operasi di kedua tempat itu MENULIS. Kalau satu sisi diperketat, deadlock
 * yang sebenarnya wajar diulang malah sampai ke pengguna sebagai galat.
 *
 * ── Kenapa dua lapis pemeriksaan ─────────────────────────────────────────────
 *
 * Lapis pertama membaca errorInfo: SQLSTATE 40001 dan kode driver 1205 adalah
 * penanda resmi deadlock SQL Server. Lapis kedua mencocokkan teks pesan, untuk
 * kegagalan yang sampai bukan sebagai QueryException — mis. terbungkus ulang
 * oleh lapisan lain. Keduanya dipertahankan apa adanya dari kode asal.
 */
class GalatDeadlock
{
    /** SQLSTATE untuk serialization failure — yang dipakai SQL Server untuk deadlock. */
    public const SQLSTATE = '40001';

    /** Kode galat asli SQL Server: "Transaction was deadlocked ... chosen as the deadlock victim". */
    public const KODE_DRIVER = '1205';

    public static function adalah(Throwable $galat): bool
    {
        if ($galat instanceof QueryException) {
            $sqlState = $galat->errorInfo[0] ?? null;
            $kodeDriver = (string) ($galat->errorInfo[1] ?? '');
            if ($sqlState === self::SQLSTATE || $kodeDriver === self::KODE_DRIVER) {
                return true;
            }
        }

        $pesan = strtolower($galat->getMessage());

        return str_contains($pesan, 'deadlock')
            || str_contains($pesan, 'sqlstate['.self::SQLSTATE.']')
            || str_contains($pesan, self::KODE_DRIVER);
    }
}
