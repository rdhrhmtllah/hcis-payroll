<?php

namespace App\Services\LMS\SOP\Uat;

/**
 * Daftar 48 skenario dokumen UAT Modul SOP, beserta klasifikasi lapisnya.
 *
 * SUMBER KEBENARAN UNTUK LAPORAN, bukan untuk dokumennya. Teks skenarionya
 * hidup di scripts/uat_sop_data.py, yang sengaja di luar git bersama seluruh
 * script builder UAT. Yang dibutuhkan laporan hanyalah nomor, modul, dan
 * lapisnya — dan ketiganya harus tersedia tanpa bergantung pada berkas yang
 * mungkin tak ada di mesin yang menjalankan laporan.
 *
 * Kalau dokumen UAT bertambah atau berkurang skenario, daftar ini ikut diubah.
 * Ketidaksesuaian akan terlihat sebagai skenario BELUM yang tak pernah bisa
 * hijau, atau sebagai skenario yang hilang dari laporan sama sekali.
 */
class UatSkenario
{
    /** Modul per rentang UAT-ID, urut sesuai bab Ruang Lingkup dokumen. */
    private const MODUL = [
        [1, 2, 'Hak Akses & Menu DMS'],
        [3, 4, 'Master Kategori Dokumen'],
        [5, 6, 'Master Konten (Berkas)'],
        [7, 14, 'Master Dokumen Terkendali'],
        [15, 19, 'Document Builder'],
        [20, 23, 'Penjadwalan & Penugasan'],
        [24, 25, 'Kebijakan & Otomasi'],
        [26, 27, 'Public Document'],
        [28, 29, 'Functional Document'],
        [30, 33, 'Membaca & Konfirmasi Dokumen'],
        [34, 35, 'Kuis / Checkpoint'],
        [36, 37, 'Absensi Check-in'],
        [38, 39, 'Kepatuhan Tim (Leader)'],
        [40, 43, 'Monitoring & Laporan'],
        [44, 46, 'Siklus Hidup Dokumen'],
        [47, 48, 'Notifikasi & Rekertifikasi'],
    ];

    public const TOTAL = 48;

    /**
     * Skenario yang membuktikan sistem MENOLAK sesuatu.
     *
     * Dibedakan pada laporan karena Acceptance Criteria memperlakukannya
     * berbeda: skenario kontrol tak boleh diturunkan menjadi issue Minor.
     */
    public const KONTROL = [2, 6, 9, 13, 22, 27, 32, 33, 35, 37, 39, 43];

    /**
     * Skenario yang FITURNYA DICABUT — bukan gagal, bukan tertunda.
     *
     * 003 (mengelola kategori) dan 004 (kategori terpakai tak bisa dihapus)
     * menguji MASTER KATEGORI, yang dicabut dari modul 28 Agu 2026. Halaman
     * masternya dibongkar dan kategori tak lagi melekat di paket, jadi tak ada
     * lagi keadaan yang bisa mereka uji. Barisnya di MODUL sengaja dibiarkan
     * supaya nomor 3 dan 4 tetap punya nama saat dirujuk.
     *
     * 046 (dokumen lewat masa berlaku tertandai kadaluarsa) dan 048
     * (rekertifikasi berkala membuka ulang kewajiban) sama-sama menguji konsep
     * MASA BERLAKU dokumen, yang dicabut sebagai keputusan produk 15 Agu 2026.
     * Sebuah SOP kini berlaku sampai ada revisi versi baru; tak ada lagi keadaan
     * yang bisa mereka uji.
     *
     * Nomornya SENGAJA TIDAK dipakai ulang dan TOTAL tidak diturunkan: dokumen
     * UAT yang sudah ditandatangani memakai penomoran ini, dan menggeser nomor
     * membuat setiap rujukan ke sana menunjuk skenario yang berbeda.
     *
     * TINDAKAN MANUSIA YANG MASIH TERSISA: dokumen UAT (scripts/uat_sop_data.py,
     * di luar git) belum ikut diubah. Selama belum, dokumennya menjanjikan empat
     * skenario yang tak lagi ada di sistem.
     */
    public const DICABUT = [3, 4, 46, 48];

    /** Butuh peramban sungguhan — janji tentang layar, bukan tentang aturan. */
    public const PERAMBAN = [8, 9, 16, 19, 31, 32];

    /**
     * Punya bagian yang TAK BISA diotomatiskan sama sekali.
     *
     * 034 — mengerjakan kuisnya berlangsung di sistem ujian HC Learn, di luar
     * modul ini; yang bisa dilihat SOP hanya hasil yang dikembalikannya.
     *
     * 036 — check-in menulis foto wajah ke Google Cloud Storage. Bukan
     * kameranya yang menghalangi (SopAbsensiService menerima data URI, dan
     * penolakan foto galeri lewat EXIF justru bisa diuji), melainkan berkas
     * yang tertinggal di penyimpanan awan: ia di luar kontrak pembersih, yang
     * hanya menghapus baris pada daftar-putih tabel.
     *
     * 047 DIKELUARKAN dari daftar ini. Alasannya dulu "pesan benar-benar sampai
     * ke ponsel", dan itu keliru — notifikasi SOP in-app saja, ditulis ke
     * N_HRIS_Notification tanpa WhatsApp maupun surel. Kini terbukti otomatis
     * di Alur047NotifikasiTest.
     *
     * Ditandai MANUAL pada laporan, bukan dihitung gagal: laporan yang
     * berpura-pura bisa menguji sistem ujian pihak ketiga akan berbohong, dan
     * angka 48/48 yang mustahil membuat seluruh laporan kehilangan artinya.
     */
    public const MANUAL = [34, 36];

    public static function id(int $nomor): string
    {
        return sprintf('UAT-SOP-%03d', $nomor);
    }

    public static function modul(int $nomor): string
    {
        foreach (self::MODUL as [$dari, $sampai, $nama]) {
            if ($nomor >= $dari && $nomor <= $sampai) {
                return $nama;
            }
        }

        return '(di luar daftar)';
    }

    /** @return list<int> */
    public static function semua(): array
    {
        return array_values(array_diff(range(1, self::TOTAL), self::DICABUT));
    }

    public static function dicabut(int $nomor): bool
    {
        return in_array($nomor, self::DICABUT, true);
    }

    public static function adalahKontrol(int $nomor): bool
    {
        return in_array($nomor, self::KONTROL, true);
    }

    public static function butuhPeramban(int $nomor): bool
    {
        return in_array($nomor, self::PERAMBAN, true);
    }

    public static function adaBagianManual(int $nomor): bool
    {
        return in_array($nomor, self::MANUAL, true);
    }
}
