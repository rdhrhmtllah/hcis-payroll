<?php

namespace App\Services\LMS\SOP\Support;

/**
 * Sakelar hidup/mati per TIPE item SOP.
 *
 * SopTipeItem tetap murni konstanta dan bebas database — ia dibaca dari mana
 * saja, termasuk dari tempat yang tak boleh menyentuh koneksi. Kelas inilah
 * yang menyambungkannya ke setelan global (N_SOP_Setting lewat SopSetting,
 * cache 10 menit dengan fallback).
 *
 * ARTINYA SEMPIT DAN DISENGAJA: mematikan sebuah tipe hanya menutup PEMBUATAN
 * BARU — palet Builder, pilihan tipe di Master Dokumen, dan pemilih dokumen.
 * Item serta dokumen lama bertipe itu tetap hidup, tetap disajikan ke karyawan,
 * dan tetap dihitung monitoring maupun export. Sakelar yang ikut menyembunyikan
 * data lama akan mengubah angka kepatuhan historis, dan kewajiban baca yang
 * lenyap tanpa jejak lebih buruk daripada tipe yang terlanjur dipakai.
 *
 * Bawaannya menyalin perilaku sebelum kelas ini ada, termasuk ABSENSI yang
 * memang sudah mati — dulu lewat satu baris filter di SopBuilder.vue yang tak
 * menyebut alasan dan tak bisa diubah siapa pun.
 */
class SopTipeItemAktif
{
    /** Kode_Setting = "tipe_item_" + tipe huruf kecil. Bawaan per tipe. */
    private const BAWAAN = [
        SopTipeItem::DOKUMEN => true,
        SopTipeItem::FORM => true,
        SopTipeItem::VIDEO => true,
        SopTipeItem::KUIS => true,
        SopTipeItem::TAUTAN => true,
        SopTipeItem::PARAGRAF => true,
        /* ABSENSI sudah lama tak bisa dipilih: storeItem menolaknya terang-terangan
           ("Absensi diatur lewat toggle paket, bukan item") dan paletnya menyaringnya.
           Bawaan mati di sini bukan perubahan perilaku — hanya memindahkan keputusan
           yang sama ke tempat yang bisa dibaca. */
        SopTipeItem::ABSENSI => false,
    ];

    /**
     * Peta tipe => bawaan hidup/mati.
     *
     * Dibuka supaya halaman setelan merakit kontraknya dari sini, bukan dari
     * daftar kedua yang diketik ulang — daftar kedua akan menyimpang, dan yang
     * menyimpang berarti layar mengatakan "aktif" untuk tipe yang penjaganya
     * menolak.
     *
     * @return array<string,bool>
     */
    public static function bawaan(): array
    {
        return self::BAWAAN;
    }

    public static function kunci(string $tipe): string
    {
        return 'tipe_item_'.strtolower($tipe);
    }

    public static function nyala(string $tipe): bool
    {
        $tipe = strtoupper(trim($tipe));
        if (! array_key_exists($tipe, self::BAWAAN)) {
            return false;
        }

        return SopSetting::bool(self::kunci($tipe), self::BAWAAN[$tipe]);
    }

    /**
     * Tipe yang menyala, URUT MENGIKUTI SopTipeItem::SEMUA.
     *
     * Urutan ikut konstanta, bukan urutan baris setelan: palet tipe di Builder
     * dirender dari daftar ini, dan susunan yang berubah-ubah mengikuti kapan
     * admin terakhir menyentuh setelan membuat tombol berpindah tempat tanpa
     * sebab yang bisa dilihat.
     *
     * @return list<string>
     */
    public static function aktif(): array
    {
        return array_values(array_filter(SopTipeItem::SEMUA, fn ($t) => self::nyala($t)));
    }

    /**
     * Irisan dengan tipe ber-versi — dipakai pintu Master Dokumen, yang memang
     * hanya menerima tipe ber-versi.
     *
     * @return list<string>
     */
    public static function aktifVersioned(): array
    {
        return array_values(array_filter(SopTipeItem::VERSIONED, fn ($t) => self::nyala($t)));
    }

    /**
     * Kalimat penolakan, satu sumber untuk semua pintu.
     *
     * Menyebut TEMPAT MENYALAKANNYA, bukan cuma "tidak aktif": penolakan yang
     * tak memberi tahu apa yang harus dikerjakan berikutnya sama saja dengan
     * tombol yang gagal tanpa keterangan.
     */
    public static function pesanMati(string $tipe): string
    {
        return "Tipe item {$tipe} sedang dinonaktifkan, jadi tak bisa dipakai untuk item atau dokumen baru. "
            .'Item dan dokumen lama bertipe ini tetap berjalan. '
            .'Sakelarnya ada di menu Kebijakan Global Dokumen.';
    }
}
