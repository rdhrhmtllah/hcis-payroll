<?php

namespace App\Services\LMS\SOP\Support;

/**
 * Peran pada blok penanda tangan versi SOP.
 *
 * KENAPA DAFTAR TETAP, padahal semula sengaja teks bebas. Alasan lama —
 * "blok tanda tangan berbeda antar jenis dokumen" — kalah oleh apa yang
 * terjadi pada data nyata. Dari sebelas baris pertama saja sudah muncul:
 *
 *   Disetujui / Disetujui oleh      -> dua kelompok untuk satu maksud
 *   Diperiksa / Diperiksa oleh      -> dua lagi
 *   Disahkan oleh                   -> varian ketiga
 *   Dianukan oleh                   -> salah ketik yang ikut tersimpan
 *
 * Teks bebas membuat pengelompokan mustahil: laporan "berapa SOP yang sudah
 * diperiksa" harus menebak ejaan mana yang dimaksud, dan salah ketik menjadi
 * kategori tersendiri yang tak pernah ada yang meminta.
 *
 * NILAI LAMA TIDAK DIUBAH. Seluruh varian di atas ada pada versi PUBLISHED
 * dan OBSOLETE — catatan yang sudah diteken dan riwayat yang sudah lewat.
 * Menormalkannya berarti menulis ulang catatan pengesahan yang sudah sah,
 * dan itu justru yang dokumen terkendali larang. Ia tetap tampil apa adanya
 * di riwayat; yang dijaga daftar ini adalah baris yang ditulis SEJAK KINI.
 *
 * Cerminnya di sisi layar ada di resources/js/Pages/LMS/SOP/Shared/support/constants.js
 * (PERAN_PENGESAH). Keduanya harus diubah bersama.
 */
class SopPeranPengesah
{
    /** Urut sesuai lazimnya blok tanda tangan dibaca, dari bawah ke atas rantai. */
    public const SEMUA = [
        'Disiapkan oleh',
        'Diperiksa oleh',
        'Disetujui oleh',
        'Disahkan oleh',
        'Mengetahui',
    ];
}
