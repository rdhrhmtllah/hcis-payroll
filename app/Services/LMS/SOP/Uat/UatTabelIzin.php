<?php

namespace App\Services\LMS\SOP\Uat;

use RuntimeException;

/**
 * Daftar-putih tabel yang boleh dihapus oleh pembersih otomasi UAT.
 *
 * KENAPA DAFTAR TERTANAM, BUKAN HASIL PEMINDAIAN. Menyusun daftar tabel dengan
 * memindai nama `N_SOP_*` di dalam kode terasa cerdas dan selalu mutakhir, tapi
 * hasil pemindaian itu memuat nama indeks (`N_SOP_Item_Versi_Urut`), nama
 * tetapan, dan potongan SQL — bukan hanya tabel. Sebuah pembersih yang menerima
 * nama tabel dari pemindaian adalah pembersih yang suatu hari akan menghapus
 * sesuatu yang tak pernah dimaksudkan siapa pun. Daftar ini ditulis tangan,
 * dan penambahannya harus disengaja.
 *
 * Suite berjalan di database yang dipakai orang bekerja. Itu bukan alasan untuk
 * gugup, tapi alasan untuk membuat batasnya eksplisit dan bisa diuji.
 */
class UatTabelIzin
{
    /**
     * Urutan penghapusan: anak lebih dulu, induk belakangan.
     *
     * Urutan yang salah membuat foreign key menolak di tengah jalan, dan
     * pembersihan berhenti separuh — meninggalkan justru keadaan yang paling
     * sulit dibereskan tangan, karena sebagian sudah hilang dan sebagian belum.
     */
    public const URUTAN_HAPUS = [
        /* Pemberitahuan in-app yang DIBUAT jalan-uji ini.
           Tabelnya milik seluruh HCIS, bukan milik SOP — dan itulah alasan ia
           dulu sengaja ditolak. Yang mengubah keputusannya: menugaskan paket
           uji MENULIS ke sini, di luar transaksi, sehingga tiap jalan peramban
           meninggalkan pemberitahuan pada lonceng karyawan sungguhan yang
           menunjuk paket yang sudah dihapus. Sampah yang tak bisa dibereskan
           otomasi yang membuatnya bukan keadaan yang boleh dibiarkan tumbuh.

           Batasnya tetap sama ketatnya dengan tabel lain: pembersih hanya
           menghapus id yang TERCATAT di jurnal, tak pernah menyapu berdasarkan
           nama, tanggal, atau pemilik. Baris milik modul lain tak terjangkau. */
        'N_HRIS_Notification',

        // Jejak & rekaman: tak punya tanggungan, aman paling depan.
        'N_SOP_Log',
        'N_SOP_Automation_Run',

        // Bukti kepatuhan karyawan.
        'N_SOP_Progress',
        'N_SOP_Coverage_Rollup',
        'N_SOP_Kepatuhan_Riwayat',
        'N_SOP_Absensi',
        'N_SOP_Favorit',

        // Penugasan.
        'N_SOP_Penjadwalan_Detail',
        'N_SOP_Penjadwalan_Integrasi',
        'N_SOP_Penjadwalan',
        'N_SOP_Assignment_Rule',

        // Rilis paket yang dibekukan.
        'N_SOP_Paket_Versi_Item',
        'N_SOP_Paket_Versi',

        // Overlay draft Builder.
        'N_SOP_Paket_Draft_Item_Versi',
        'N_SOP_Paket_Draft_Item',
        'N_SOP_Paket_Draft_Section',
        'N_SOP_Paket_Draft',

        // Isi paket, lalu paketnya.
        'N_SOP_Paket_Item',
        'N_SOP_Paket_Section',
        'N_SOP_Master_Paket',

        // Versi dokumen, lalu dokumennya.
        'N_SOP_Item_Versi_Pengesah',
        'N_SOP_Item_Versi_Berkas',
        'N_SOP_Item_Versi',
        'N_SOP_Dokumen',
    ];

    /** Sama isinya dengan URUTAN_HAPUS; dijaga kesamaannya oleh uji. */
    public const DIIZINKAN = self::URUTAN_HAPUS;

    /**
     * Tabel yang SENGAJA di luar daftar, beserta pemiliknya.
     *
     * Disimpan eksplisit supaya penolakannya bisa menyebut alasan. Pesan
     * "tabel tidak diizinkan" mengundang orang menambahkannya ke daftar; pesan
     * "tabel ini milik modul LMS" menjelaskan kenapa menambahkannya salah.
     */
    private const DILARANG = [
        'N_LMS_Master_Konten' => 'modul LMS — berkas dokumen menumpang di sini, dan tabel yang sama menyimpan materi pelatihan LMS',
        'N_LMS_Master_Konten_Berkas' => 'modul LMS',
        'N_LMS_Master_Konten_Media' => 'modul LMS',
        'N_SOP_Setting' => 'setelan modul DMS. Skenario 025 MENGUBAH nilainya, bukan membuat barisnya; yang benar mengembalikan nilai lama, bukan menghapus. Menghapusnya meninggalkan modul tanpa konfigurasi',
    ];

    public static function diizinkan(string $tabel): bool
    {
        return in_array($tabel, self::DIIZINKAN, true);
    }

    public static function pastikanDiizinkan(string $tabel): void
    {
        if (self::diizinkan($tabel)) {
            return;
        }

        $alasan = self::DILARANG[$tabel] ?? null;

        throw new RuntimeException($alasan
            ? "Tabel '{$tabel}' sengaja di luar daftar-putih otomasi UAT: {$alasan}."
            : "Tabel '{$tabel}' tidak ada pada daftar-putih otomasi UAT. Penambahan "
              .'harus disengaja dan disertai alasan di UatTabelIzin.');
    }
}
