<?php

namespace App\Services\LMS\SOP\Uat;

use RuntimeException;

/**
 * Gerbang sengaja untuk otomasi UAT.
 *
 * Suite ini menulis ke database yang dipakai orang bekerja. Ia tidak boleh bisa
 * ikut terpicu oleh `php artisan test` biasa, oleh IDE yang menjalankan seluruh
 * folder tests, atau oleh CI yang tidak tahu apa-apa tentang UAT.
 *
 * Penandanya harus bernilai persis "1". Menerima "true"/"yes"/"on" terasa ramah,
 * tapi keramahan itu memperbesar peluang gerbang terbuka karena kebetulan —
 * dan gerbang yang terbuka karena kebetulan bukan gerbang.
 */
class UatGerbang
{
    public const PENANDA = 'UAT_AUTO_ALLOW';

    public static function terbuka(): bool
    {
        return self::nilai() === '1';
    }

    public static function pastikanTerbuka(): void
    {
        if (self::terbuka()) {
            return;
        }

        throw new RuntimeException(
            'Otomasi UAT tertutup. Suite ini menulis ke database dev yang dipakai '
            .'orang bekerja, jadi ia harus dinyalakan dengan sengaja: setel '
            .self::PENANDA.'=1 pada lingkungan yang menjalankannya.'
        );
    }

    /**
     * getenv() dibaca lebih dulu supaya penanda yang disetel per-perintah di
     * shell tetap terbaca; env() Laravel tidak selalu melihatnya setelah
     * konfigurasi di-cache.
     */
    private static function nilai(): ?string
    {
        $v = getenv(self::PENANDA);
        if ($v !== false) {
            return (string) $v;
        }

        foreach ([$_ENV, $_SERVER] as $sumber) {
            if (array_key_exists(self::PENANDA, $sumber)) {
                return (string) $sumber[self::PENANDA];
            }
        }

        return null;
    }
}
