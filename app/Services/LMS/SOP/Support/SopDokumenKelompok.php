<?php

namespace App\Services\LMS\SOP\Support;

/**
 * Aturan kelompok dokumen SOP — WAJIB / TERBUKA / BELUM BEREDAR.
 *
 * Modul: LMS · Submodul: SOP · Fitur: Export · Lapisan: Support.
 *
 * KENAPA KELAS INI ADA. Aturannya dipakai dua pintu yang bentuknya berbeda:
 * SopDokumenKesehatanService merakitnya sebagai query builder lalu
 * menggabungkan di PHP, sedangkan dataset Export Studio membutuhkannya sebagai
 * KOLOM yang bisa di-GROUP BY. Dua penulisan untuk satu aturan adalah pola bug
 * yang sudah berulang di modul ini: aturan dipasang di satu pintu, pintu kedua
 * ketinggalan, dan selisihnya baru terlihat berbulan-bulan kemudian sebagai
 * "angka dashboard beda dengan angka laporan". Karena itu klausa FROM dan
 * WHERE-nya ditulis SEKALI di sini, lalu dirender ke dua bentuk.
 *
 * DUA BENTUK, SATU SUMBER:
 *
 *   sqlWajib()/sqlBeredar()       → EXISTS, untuk dipakai di WHERE.
 *   derivedWajib()/derivedBeredar() → daftar id, untuk di-LEFT JOIN.
 *
 * Bentuk kedua bukan kemewahan. SQL Server MELARANG sub-kueri di dalam GROUP
 * BY, dan mesin laporan menaruh ekspresi dimensi apa adanya ke sana. Sebuah
 * dimensi 'CASE WHEN EXISTS (...)' karena itu berjalan mulus di mode rinci dan
 * gagal total begitu seseorang mengelompokkannya — padahal mengelompokkan per
 * kelompok justru satu-satunya alasan kolom ini ada. Di-LEFT JOIN sebagai
 * daftar id, ekspresinya menyusut jadi pemeriksaan NULL biasa yang sah di
 * GROUP BY. DISTINCT di dalamnya yang menjaga satu dokumen tetap satu baris.
 *
 * TIDAK ADA BINDING DI SELURUH KELAS INI. Setiap nilai adalah konstanta
 * SopStatus, bukan masukan pengguna. Itu yang membuat teks ini aman ditanam
 * sebagai SQL mentah: tak ada satu pun tanda tanya yang bisa tergeser oleh
 * binding berikutnya — kerapuhan yang dihindari SopDokumenKesehatanService
 * ketika ia memilih menggabungkan di PHP.
 *
 * PERBEDAAN YANG DISENGAJA antara dua pintu, supaya tidak dikira cacat:
 * di dashboard, WAJIB dihitung SETELAH saringan organisasi (dokumen bisa
 * berpindah kelompok saat saringan digeser); di Export Studio, kelompok adalah
 * sifat TETAP dokumen — dihitung atas seluruh organisasi, dan saringan hanya
 * membuang baris, tak pernah mengubah labelnya. Keputusan itu diambil sadar:
 * kolom yang berubah arti ketika saringan digeser tak bisa dipakai
 * membandingkan dua berkas ekspor.
 */
class SopDokumenKelompok
{
    /* KOSAKATA TUNGGAL SEJAK 2026-09-01. Kelas ini adalah satu-satunya tempat
       nilai kelompok dokumen didefinisikan; SopDokumenKesehatanService
       (dashboard) membacanya dari sini, tidak lagi punya salinan sendiri.

       Sebelumnya kedua layar memakai kosakata berbeda untuk hal yang sama:

           kelompok       Export Studio   dashboard      sekarang
           -----------    -------------   -----------    ------------
           ditugaskan     WAJIB           FUNGSIONAL     DITUGASKAN
           beredar        TERBUKA         PUBLIK         BEREDAR
           belum beredar  BELUM_BEREDAR   BELUM_BEREDAR  BELUM_BEREDAR

       Dua dari tiga berbeda, sehingga dokumen yang sama disebut "Wajib
       (Ditugaskan)" di berkas ekspor dan "Fungsional" di dashboard.

       KENAPA KATA KETIGA, BUKAN SALAH SATU YANG SUDAH ADA. Keduanya sama-sama
       bertabrakan dengan konsep lain di modul ini:

         PUBLIK  juga nilai Mode_Distribusi — satu kata untuk dua hal berbeda
                 (mode distribusi paket vs klasifikasi peredaran dokumen).
         WAJIB   satu kata dengan kunci dimensi 'wajib' (pi.Flag_Wajib) di
                 dataset kepatuhan. Collation SQL Server tak peduli besar-kecil
                 huruf, sehingga pencarian pada definisi template menyamakan
                 keduanya — sudah terbukti menipu sekali saat audit.

       DITUGASKAN dan BEREDAR tidak bertabrakan dengan apa pun, dan keduanya
       sudah menjadi kata yang dibaca pengguna di dalam kurung pada LABEL di
       bawah, jadi istilahnya bukan hal baru bagi siapa pun.

       YANG SENGAJA TIDAK IKUT BERUBAH, mengikuti preseden penggantian istilah
       Monitoring: nama kelas CSS (.is-fungsional, .is-publik) dan bentuk kunci
       respons API (Fungsional/Publik pada pecahKelompok). Keduanya tak terbaca
       pengguna, dan menggantinya melipatgandakan permukaan risiko tanpa
       menambah kejelasan bagi siapa pun. */
    public const DITUGASKAN = 'DITUGASKAN';

    public const BEREDAR = 'BEREDAR';

    public const BELUM_BEREDAR = 'BELUM_BEREDAR';

    /** Label yang dibaca manusia di berkas ekspor. */
    public const LABEL = [
        self::DITUGASKAN => 'Wajib (Ditugaskan)',
        self::BEREDAR => 'Terbuka (Beredar)',
        self::BELUM_BEREDAR => 'Belum Beredar',
    ];

    /** Alias yang dipakai baseQuery() untuk kedua tabel turunan. */
    public const ALIAS_WAJIB = 'kel_wajib';

    public const ALIAS_BEREDAR = 'kel_beredar';

    /*
    |--------------------------------------------------------------------------
    | Satu sumber: klausa FROM dan WHERE
    |--------------------------------------------------------------------------
    */

    private static function fromWajib(): string
    {
        return 'FROM N_SOP_Penjadwalan_Detail AS kel_d
                JOIN N_SOP_Penjadwalan AS kel_pj ON kel_pj.Id_SOP_Penjadwalan = kel_d.Id_SOP_Penjadwalan
                JOIN N_SOP_Master_Paket AS kel_p ON kel_p.Id_SOP_Paket = kel_pj.Id_SOP_Paket
                JOIN N_SOP_Paket_Item AS kel_i ON kel_i.Id_SOP_Paket = kel_p.Id_SOP_Paket';
    }

    /**
     * Syarat sebuah penugasan dihitung sebagai KEWAJIBAN nyata.
     *
     * SENGAJA SAMA PERSIS dengan SopDokumenKesehatanService::idWajib() dan,
     * lewat jalur itu, dengan SopMonitoringPeopleService::terapkanSaringanPaket()
     * yang dipakai tujuh tempat di lapisan monitoring:
     *
     *   - penugasan harus aktif dan aksesnya tidak dicabut;
     *   - itemnya harus aktif dan menunjuk sebuah dokumen;
     *   - GERBANG PUBLIK, kini dirender dari SopGerbangPaket dan bukan lagi
     *     disalin ke sini. Salinan lamanya sempat menyimpang tanpa terlihat
     *     ketika gerbangnya diperbaiki: ia ditulis sebagai SQL mentah, sehingga
     *     pencarian yang menyisir pola query builder melewatinya begitu saja —
     *     padahal komentar tepat di atasnya menjanjikan ia "sengaja sama
     *     persis". Janji dalam komentar tidak menahan apa pun; berbagi kode
     *     menahannya.
     *
     * Flag_Aktif dan Status paket TIDAK diperiksa. Itu bukan kelalaian
     * melainkan cerminan idWajib(): yang menentukan adalah ada tidaknya
     * penugasan hidup, bukan keadaan paketnya. Menambahkan pemeriksaan di sini
     * akan membuat pintu ini lebih ketat daripada dashboard — persis
     * penyimpangan yang kelas ini dibuat untuk mencegahnya.
     */
    private static function whereWajib(): string
    {
        $aktif = SopStatus::AKTIF;
        $inaktif = SopStatus::INAKTIF;

        return "kel_d.Flag_Aktif = '{$aktif}'
            AND kel_d.Flag_Cabut_Akses = '{$inaktif}'
            AND kel_i.Flag_Aktif = '{$aktif}'
            AND kel_i.Id_SOP_Dokumen IS NOT NULL
            AND ".SopGerbangPaket::sql('kel_p', 'kel_pj', 'kel_d');
    }

    private static function fromBeredar(): string
    {
        return 'FROM N_SOP_Paket_Item AS bed_i
                JOIN N_SOP_Master_Paket AS bed_p ON bed_p.Id_SOP_Paket = bed_i.Id_SOP_Paket';
    }

    /**
     * Syarat sebuah dokumen disebut BEREDAR: ada di setidaknya satu paket terbit.
     *
     * Tak ada satu pun saringan organisasi di sini, dan tak boleh ditambahkan.
     * "Beredar" adalah sifat dokumen terhadap paket, bukan terhadap sekelompok
     * karyawan; dokumen yang sama tak boleh berpindah kelompok hanya karena
     * saringan departemen digeser.
     */
    private static function whereBeredar(): string
    {
        $aktif = SopStatus::AKTIF;
        $terbit = SopStatus::PAKET_PUBLISHED;

        return "bed_i.Flag_Aktif = '{$aktif}'
            AND bed_p.Flag_Aktif = '{$aktif}'
            AND bed_p.Status = '{$terbit}'
            AND bed_i.Id_SOP_Dokumen IS NOT NULL";
    }

    /*
    |--------------------------------------------------------------------------
    | Bentuk 1 — EXISTS, untuk WHERE
    |--------------------------------------------------------------------------
    */

    public static function sqlWajib(string $alias = 'dok'): string
    {
        return 'EXISTS (SELECT 1 '.self::fromWajib()
            ." WHERE kel_i.Id_SOP_Dokumen = {$alias}.Id_SOP_Dokumen AND ".self::whereWajib().')';
    }

    public static function sqlBeredar(string $alias = 'dok'): string
    {
        return 'EXISTS (SELECT 1 '.self::fromBeredar()
            ." WHERE bed_i.Id_SOP_Dokumen = {$alias}.Id_SOP_Dokumen AND ".self::whereBeredar().')';
    }

    /*
    |--------------------------------------------------------------------------
    | Bentuk 2 — daftar id, untuk LEFT JOIN
    |--------------------------------------------------------------------------
    */

    /** DISTINCT bukan hiasan: tanpanya satu dokumen bercabang sebanyak penugasannya. */
    public static function derivedWajib(): string
    {
        return '(SELECT DISTINCT kel_i.Id_SOP_Dokumen '.self::fromWajib()
            .' WHERE '.self::whereWajib().')';
    }

    public static function derivedBeredar(): string
    {
        return '(SELECT DISTINCT bed_i.Id_SOP_Dokumen '.self::fromBeredar()
            .' WHERE '.self::whereBeredar().')';
    }

    /*
    |--------------------------------------------------------------------------
    | Ekspresi kelompok, dirakit dari hasil LEFT JOIN di atas
    |--------------------------------------------------------------------------
    */

    /**
     * Label kelompok. Aman dipakai sebagai dimensi karena tak memuat sub-kueri.
     *
     * WAJIB MENANG. Satu dokumen bisa dijadwalkan di paket A dan sekadar
     * terbuka di paket B; yang menentukan adalah ada tidaknya KEWAJIBAN, di
     * mana pun. Urutan cabang CASE inilah yang menegakkannya — bukan sebuah if
     * di layar, dan bukan urutan baris yang kebetulan.
     *
     * Ketiga cabang saling lepas dan menutupi seluruh kemungkinan, sehingga
     * jumlah per kelompok SELALU tepat sama dengan jumlah dokumen. Angka yang
     * tak menjumlah adalah sumber keraguan yang tak pernah selesai.
     */
    public static function sqlKelompok(): string
    {
        return self::rakit(self::LABEL);
    }

    /** Nilai mentah kelompok, supaya saringan tak patah bila labelnya diperhalus. */
    public static function sqlKelompokKode(): string
    {
        return self::rakit([
            self::DITUGASKAN => self::DITUGASKAN,
            self::BEREDAR => self::BEREDAR,
            self::BELUM_BEREDAR => self::BELUM_BEREDAR,
        ]);
    }

    /** @param array<string, string> $nilai */
    private static function rakit(array $nilai): string
    {
        $w = self::ALIAS_WAJIB;
        $b = self::ALIAS_BEREDAR;

        return "CASE WHEN {$w}.Id_SOP_Dokumen IS NOT NULL THEN '{$nilai[self::DITUGASKAN]}'
                     WHEN {$b}.Id_SOP_Dokumen IS NOT NULL THEN '{$nilai[self::BEREDAR]}'
                     ELSE '{$nilai[self::BELUM_BEREDAR]}' END";
    }

    /** 1 bila dokumen sudah sampai ke peredaran dengan cara apa pun. */
    public static function sqlSudahBeredar(): string
    {
        $w = self::ALIAS_WAJIB;
        $b = self::ALIAS_BEREDAR;

        return "CASE WHEN {$w}.Id_SOP_Dokumen IS NOT NULL OR {$b}.Id_SOP_Dokumen IS NOT NULL THEN 1 ELSE 0 END";
    }
}
