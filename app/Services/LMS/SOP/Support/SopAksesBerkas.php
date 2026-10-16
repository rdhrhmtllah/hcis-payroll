<?php

namespace App\Services\LMS\SOP\Support;

/**
 * Aturan akses berkas item SOP: boleh diunduh? boleh dipratinjau di tempat?
 *
 * Dikumpulkan di satu kelas karena dua aturan ini dibaca dari tempat berbeda —
 * controller yang menandatangani URL dan pembaca yang menyusun payload item —
 * dan pernah terbukti bergeser sendiri-sendiri.
 */
class SopAksesBerkas
{
    /** Ekstensi yang peramban tampilkan sendiri tanpa perkakas tambahan. */
    private const EKSTENSI_PDF = 'pdf';

    /** Mode_Akses yang mengizinkan berkas mentahnya diunduh. */
    public const MODE_UNDUH = 'VIEW_UNDUH';

    /**
     * Boleh mengunduh berkas versi ini?
     *
     * Mode_Akses berarti sesuatu bila berkasnya PDF: "hanya lihat" melayaninya
     * lewat pembaca aman ber-tanda-air, jadi menahan URL mentah memang menahan
     * sesuatu. Itu berlaku untuk DOKUMEN, dan sejak 10 Okt 2026 juga untuk
     * contoh pengisian FORM — admin memilih izinnya per paket.
     *
     * FORM non-PDF tetap selalu boleh. Itu berkas warisan dari masa sebelum
     * contoh pengisian wajib PDF (.xlsx/.docx tanpa penampil web), dan pembaca
     * aman tak bisa menampilkannya: menahan unduhannya berarti berkasnya tak
     * bisa dipakai sama sekali. Form kosong (lampiran) tidak lewat sini — selalu
     * diunduh.
     *
     * @param  string|null  $mime  jenis berkas utama versi; hanya dibaca untuk FORM
     * @param  string|null  $path  cadangan bila mime kosong (berkas lama)
     */
    public static function bolehUnduh(?string $modeAkses, ?string $tipeItem, ?string $mime = null, ?string $path = null): bool
    {
        if ($tipeItem === SopTipeItem::FORM && ! self::adalahPdf($mime, $path)) {
            return true;
        }

        return $modeAkses === self::MODE_UNDUH;
    }

    /**
     * Bolehkah watermark menyala pada item dengan mode akses ini?
     *
     * Tidak, bila berkasnya boleh diunduh. Watermark disematkan oleh pembaca
     * aman, halaman demi halaman; begitu berkas mentahnya boleh diunduh,
     * pembaca itu bisa dilewati sepenuhnya. Watermark yang menyala di
     * sebelahnya karena itu bukan perlindungan melainkan janji yang tak
     * ditepati siapa pun — dan admin yang melihat keduanya menyala akan
     * mengira dokumennya bertanda air padahal berkas yang diunduh bersih.
     *
     * Diperiksa dari Mode_Akses saja, sengaja tidak lewat bolehUnduh(): tipe
     * FORM selalu boleh diunduh, tetapi watermarknya tak pernah disunting
     * maupun dibaca, dan menormalkannya hanya akan melahirkan selisih palsu di
     * riwayat perubahan versi.
     */
    public static function watermarkBolehMenyala(?string $modeAkses): bool
    {
        return $modeAkses !== self::MODE_UNDUH;
    }

    /**
     * Nilai Flag_Watermark yang benar-benar disimpan, setelah aturan di atas.
     *
     * DIPAKAI DI SETIAP PENULIS, bukan hanya di layar. Builder memang
     * menonaktifkan sakelarnya begitu izin unduh dipilih, tetapi layar bukan
     * tempat aturan bisnis dijaga: permintaan yang dirakit sendiri tetap
     * sampai ke service, dan keadaan mustahil yang lolos sekali akan bertahan
     * di basis data selamanya.
     *
     * @param  mixed  $diminta  nilai dari permintaan; null berarti "tidak
     *                          disebut", yang berarti menyala seperti baku kolomnya
     */
    public static function watermarkTersimpan($diminta, ?string $modeAkses): string
    {
        if (! self::watermarkBolehMenyala($modeAkses)) {
            return SopStatus::INAKTIF;
        }

        return ($diminta ?? true) ? SopStatus::AKTIF : SopStatus::INAKTIF;
    }

    /**
     * Boleh dibuka di layar lewat jalur FORM, tanpa pelacakan halaman?
     *
     * Hanya FORM, dan hanya bila berkasnya PDF. FORM tak dilacak per halaman,
     * jadi tak ada yang hilang dengan menampilkannya tanpa gerbang baca. Yang
     * boleh diunduh tampil di iframe peramban; yang hanya boleh dilihat tampil
     * di pembaca aman tanpa pelacakan (lihat bolehUnduh). DOKUMEN justru
     * sebaliknya — seluruh gunanya ada pada pelacakan halaman, dan jalur ini
     * akan melewatinya.
     *
     * @param  string|null  $path  Cadangan bila Type_File kosong (berkas lama).
     */
    public static function bisaPratinjau(?string $tipeItem, ?string $mime, ?string $path = null): bool
    {
        if ($tipeItem !== SopTipeItem::FORM) {
            return false;
        }

        return self::adalahPdf($mime, $path);
    }

    /** PDF menurut mime; kalau mime kosong, menurut ekstensi berkasnya. */
    public static function adalahPdf(?string $mime, ?string $path = null): bool
    {
        if ($mime !== null && $mime !== '') {
            return str_contains(strtolower($mime), self::EKSTENSI_PDF);
        }

        return $path
            ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) === self::EKSTENSI_PDF
            : false;
    }

    /**
     * Ekstensi untuk lencana di kartu item, selalu huruf besar ("PDF", "XLSX").
     *
     * Kolom Ext disimpan huruf kecil dan kadang kosong; keduanya diseragamkan
     * di sini supaya lencananya tak berganti bentuk antar item.
     */
    public static function ekstensi(?string $ext, ?string $path = null): ?string
    {
        $nilai = trim((string) $ext);
        if ($nilai === '' && $path) {
            $nilai = pathinfo($path, PATHINFO_EXTENSION);
        }

        return $nilai !== '' ? strtoupper(ltrim($nilai, '.')) : null;
    }
}
