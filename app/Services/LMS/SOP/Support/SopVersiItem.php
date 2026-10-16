<?php

namespace App\Services\LMS\SOP\Support;

/**
 * Taut antara item paket dan versi dokumennya — SATU definisi.
 *
 * KENAPA TAUTNYA BERCABANG DUA. Versi SOP dulu dimiliki item paket
 * (`N_SOP_Item_Versi.Id_SOP_Paket_Item`), lalu berpindah menjadi milik DOKUMEN
 * (`Id_SOP_Dokumen`). Kolom lamanya tidak dihapus, tetapi sejak perpindahan itu
 * **tidak pernah lagi diisi** — nol dari seluruh baris di basis data
 * pengembangan pada 2026-09-01.
 *
 * Akibatnya kueri yang menaut lewat kolom lama saja tidak pernah cocok. Dan
 * kegagalan itu tidak berbunyi: ia mengembalikan "tidak ketemu", yang oleh
 * pemanggil dibaca sebagai keadaan sah, bukan sebagai kerusakan.
 *
 * Itu bukan kekhawatiran teoretis. Persis begitulah setelan watermark per item
 * berhenti bekerja: `SopSecurePdfAccessService::watermarkAktif()` menaut lewat
 * kolom lama, tak pernah menemukan itemnya, lalu jatuh ke gerbang default-aman
 * dan menyalakan watermark untuk SETIAP dokumen — apa pun yang dipilih admin.
 * Jalur unduh sudah lebih dulu diperbaiki untuk sebab yang sama persis, tetapi
 * perbaikannya tertulis inline di controller, sehingga pintu kedua tak ikut
 * terbawa. Kelas ini yang menutup celah itu.
 *
 * Cabang lama tetap dipertahankan, bukan dibuang: bila suatu saat ada baris
 * yang masih memakainya, membuangnya akan menghilangkan barisnya diam-diam.
 * Perbandingan dengan NULL tak pernah benar, jadi cabang yang tak terpakai
 * tidak menambah baris apa pun.
 */
final class SopVersiItem
{
    /**
     * Pasang syarat taut pada sebuah klausa JOIN.
     *
     * Dipakai sebagai:
     *   ->join('N_SOP_Item_Versi as v', fn ($j) => SopVersiItem::tautkan($j, 'i', 'v'))
     *
     * @param  \Illuminate\Database\Query\JoinClause  $j
     * @param  string  $item  alias N_SOP_Paket_Item
     * @param  string  $versi  alias N_SOP_Item_Versi
     */
    public static function tautkan($j, string $item = 'i', string $versi = 'v'): void
    {
        $j->on(function ($q) use ($item, $versi) {
            $q->on("{$item}.Id_SOP_Dokumen", '=', "{$versi}.Id_SOP_Dokumen")
                ->orOn("{$item}.Id_SOP_Paket_Item", '=', "{$versi}.Id_SOP_Paket_Item");
        });
    }
}
