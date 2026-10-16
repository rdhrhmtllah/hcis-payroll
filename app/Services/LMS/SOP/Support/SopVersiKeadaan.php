<?php

namespace App\Services\LMS\SOP\Support;

/**
 * Keadaan sebuah versi SOP, dijawab di SATU tempat.
 *
 * KENAPA KELAS INI ADA. Keadaan versi dipikul tiga kolom sekaligus — `Status`,
 * `Tahap_Id`, dan `Flag_Aktif` — dan hubungan di antara ketiganya tak dijaga
 * basis data. Selama tiap pemanggil menyimpulkan sendiri, simpulannya
 * menyimpang, dan yang menyimpang menjadi cacat berikutnya. Audit 30 Agu 2026
 * menemukan `Status === 'DRAFT'` dipakai sebagai penilaian mandiri di 15 tempat
 * pada berkas Vue saja, di luar tiga pintu mutasi di service.
 *
 * PERANGKAP YANG MELAHIRKANNYA. Tahap CANCEL punya `Status_Teknis = 'DRAFT'`:
 * versi yang dibatalkan TETAP berstatus DRAFT — yang berpindah adalah tahapnya.
 * Setiap penjaga yang bertanya pada `Status` karena itu meloloskan arsip, dan
 * arsip yang seharusnya beku masih menerima suntingan, penghapusan, dan catatan
 * review baru.
 *
 * MURNI, TANPA KUERI. Yang dibutuhkan hanya baris versi dan baris tahapnya —
 * keduanya sudah dibaca pemanggil. Dengan begitu kelas ini bisa dipakai di
 * dalam perulangan halaman tanpa melahirkan satu kueri per baris, dan bisa
 * diuji tanpa basis data.
 */
final class SopVersiKeadaan
{
    /**
     * Apakah versi ini dibatalkan?
     *
     * Ditanyakan pada SIFAT tahapnya, bukan namanya. Menuliskan 'CANCEL'
     * sebagai kode berarti setiap perubahan kebijakan alur menunggu rilis,
     * padahal seluruh rancangan tahap ini justru ada supaya alurnya bisa
     * berubah sebagai data.
     *
     * @param  object|null  $tahap  baris N_SOP_Master_Tahap_Versi milik versi
     */
    public static function dibatalkan(?object $tahap): bool
    {
        return ($tahap->Nonaktifkan_Versi ?? null) === SopStatus::AKTIF;
    }

    /**
     * Boleh dikerjakan lagi?
     *
     * Draft yang masih berjalan — bukan yang sudah terbit (itu catatan
     * kepatuhan), bukan yang tergantikan, dan bukan yang dibatalkan (itu
     * arsip). Inilah jawaban yang selama ini ditebak tiap layar dengan
     * `Status === 'DRAFT'`, dan tebakan itu meloloskan arsip.
     */
    public static function bolehDisunting(?string $status, ?object $tahap): bool
    {
        return $status === SopStatus::PAKET_DRAFT && ! self::dibatalkan($tahap);
    }

    /**
     * Field yang boleh diubah saat versi masih DRAFT dan berjalan.
     *
     * Sama persis dengan yang selama ini ditulis ubahVersi(); didaftar di sini
     * supaya layar tak perlu menghafalnya sendiri.
     */
    public const FIELD_DRAFT = [
        'Versi_Ke', 'Id_Master_Konten', 'Id_Master_Ujian', 'Nama_Ujian',
        'Ringkasan_Perubahan',
        'Tanggal_Berlaku', 'Tanggal_Permintaan', 'Tanggal_Respon',
        'Tanggal_Akhir_Approval', 'Tanggal_Kadaluarsa', 'Tanggal_Review_Berikutnya',
        'Pengesah', 'Tanggal_Pengesahan', 'Nomor_Pengesahan',
        'Lampiran',
    ];

    /**
     * Field yang masih boleh diubah SESUDAH versi terbit.
     *
     * Keputusan produk 30 Agu 2026. Keempatnya punya satu sifat bersama: tak
     * satu pun mengubah APA yang dibaca karyawan. Memperpanjang masa berlaku,
     * menggeser jadwal review, membetulkan ringkasan, atau memperbaiki salah
     * ketik pada blok pengesahan tak melahirkan dokumen yang berbeda — memaksa
     * semuanya lewat revisi baru hanya melahirkan versi yang isinya sama persis.
     *
     * Isi versi (berkas/ujian) dan nomor versinya sengaja TIDAK di sini: itulah
     * yang membuat sebuah revisi menjadi revisi.
     *
     * 21 Sep 2026: seluruh tanggal proses ikut dibuka atas permintaan produk,
     * TERMASUK Tanggal_Berlaku yang sampai 30 Agu 2026 sengaja dikunci karena
     * ia gerbang keberlakuan yang dibaca karyawan. Sebabnya migrasi dokumen
     * konvensional: tanggal berlaku dokumen lama kerap salah ketik dan baru
     * ketahuan setelah terbit. Konsekuensi yang disadari: menggeser tanggal
     * ini menggeser pula kapan versi itu berlaku bagi pembacanya.
     */
    public const FIELD_TERBIT = [
        'Ringkasan_Perubahan',
        'Tanggal_Berlaku', 'Tanggal_Permintaan', 'Tanggal_Respon',
        'Tanggal_Akhir_Approval', 'Tanggal_Kadaluarsa', 'Tanggal_Review_Berikutnya',
        'Pengesah', 'Tanggal_Pengesahan', 'Nomor_Pengesahan',
    ];

    /**
     * Isi versi terbit yang masih boleh diganti SELAMA belum dijadwalkan.
     *
     * Keputusan produk 5 Okt 2026. Salah lampir kerap baru ketahuan sesudah
     * terbit, dan selama dokumennya belum dijadwalkan ke karyawan lewat paket,
     * berkas maupun ujiannya belum pernah dibaca siapa pun — revisi baru demi
     * itu hanya melahirkan versi yang riwayatnya tak berarti. Begitu ada
     * karyawan yang dijadwalkan, isi kembali terkunci: dari situ penggantian
     * memang harus lewat revisi.
     *
     * Nomor versi sengaja tidak di sini; ia identitas revisi, bukan isinya.
     * `Lampiran` (form kosong dokumen FORM) ikut sejak 5 Okt 2026: ia bagian
     * dari apa yang diterima karyawan, sama seperti berkasnya.
     */
    public const FIELD_ISI = ['Id_Master_Konten', 'Id_Master_Ujian', 'Nama_Ujian', 'Lampiran'];

    /**
     * Versi arsip (dibatalkan / digantikan) hanya menerima TANGGAL.
     *
     * Keputusan produk 21 Sep 2026, diminta untuk migrasi dokumen konvensional
     * ke digital: tanggal pada dokumen lama sering baru ketahuan salah setelah
     * versinya berjalan, dan memaksa pembetulan lewat versi baru berarti
     * melahirkan versi yang isinya sama persis hanya demi satu angka tanggal.
     *
     * Yang TIDAK ikut terbuka: isi versi, nomor versi, dan blok pengesahan —
     * arsip tetap menjawab "apa yang terjadi". Yang berubah cuma KAPAN-nya.
     */
    public const FIELD_ARSIP = self::FIELD_TANGGAL;

    /** Field tanggal yang tunduk pada aturan "yang sudah lewat tak bisa digeser". */
    public const FIELD_TANGGAL = [
        'Tanggal_Berlaku', 'Tanggal_Permintaan', 'Tanggal_Respon',
        'Tanggal_Akhir_Approval', 'Tanggal_Kadaluarsa', 'Tanggal_Review_Berikutnya',
        'Tanggal_Pengesahan',
    ];

    /**
     * Field mana yang boleh diubah pada versi berkeadaan ini?
     *
     * Larik KOSONG berarti terkunci penuh. Tak ada nilai sentinel "semua" —
     * daftar eksplisit selalu, supaya layar tak perlu menerjemahkan apa pun dan
     * penambahan field baru terjadi di sini, bukan di tiap pemakainya.
     *
     * OBSOLETE dan DIBATALKAN sama-sama kosong, dengan alasan yang sama:
     * keduanya historis. Yang satu revisi yang sudah digantikan, yang lain
     * keputusan membatalkan — dan keduanya adalah jawaban atas pertanyaan
     * "apa yang terjadi", bukan pekerjaan yang masih berjalan.
     *
     * `$sudahDijadwalkan` hanya berarti bagi versi terbit (lihat FIELD_ISI).
     * Bawaannya TRUE — terkunci — supaya pemanggil yang tak menanyakan
     * penjadwalan tak pernah membuka isi versi tanpa sengaja. Kelas ini tetap
     * murni: faktanya dijawab pemanggil, bukan dikueri di sini.
     */
    public static function fieldBolehUbah(?string $status, ?object $tahap, bool $sudahDijadwalkan = true): array
    {
        if (self::dibatalkan($tahap)) {
            return self::FIELD_ARSIP;
        }

        return match ($status) {
            SopStatus::PAKET_DRAFT => self::FIELD_DRAFT,
            SopStatus::VERSI_PUBLISHED => $sudahDijadwalkan
                ? self::FIELD_TERBIT
                : array_values(array_merge(self::FIELD_ISI, self::FIELD_TERBIT)),
            SopStatus::VERSI_OBSOLETE => self::FIELD_ARSIP,
            default => [],   // keadaan yang tak dikenali tetap beku
        };
    }

    /**
     * Kalimat penolakan yang menyebut SEBABNYA, bukan sekadar "tidak boleh".
     *
     * Admin yang ditolak tanpa tahu kenapa akan mencoba lagi lewat jalan lain.
     */
    public static function alasanTerkunci(?string $status, ?object $tahap): string
    {
        if (self::dibatalkan($tahap)) {
            return 'Versi ini sudah dibatalkan dan tersimpan sebagai arsip. '
                .'Buat versi baru untuk melanjutkan.';
        }

        if ($status === SopStatus::VERSI_OBSOLETE) {
            return 'Versi ini sudah digantikan revisi yang lebih baru dan menjadi riwayat. '
                .'Riwayat dibaca, tidak diubah.';
        }

        return 'Versi ini tidak dalam keadaan yang menerima perubahan.';
    }

    /**
     * Fakta-fakta yang dikirim ke layar, dalam satu bentuk.
     *
     * Dikembalikan sebagai larik supaya penambahan fakta baru terjadi di SINI,
     * bukan di tiap muatan. Muatan yang merakit sendiri daftarnya adalah cara
     * bagaimana satu layar tertinggal saat fakta bertambah.
     *
     * @return array{Dibatalkan: bool, Boleh_Disunting: bool, Field_Boleh_Ubah: array}
     */
    public static function fakta(?string $status, ?object $tahap, bool $sudahDijadwalkan = true): array
    {
        return [
            'Dibatalkan' => self::dibatalkan($tahap),
            'Boleh_Disunting' => self::bolehDisunting($status, $tahap),
            'Beku_Isi' => self::bekuIsi($status, $tahap),
            'Field_Boleh_Ubah' => self::fieldBolehUbah($status, $tahap, $sudahDijadwalkan),
        ];
    }

    /**
     * Isi versi ini sudah beku: arsip (dibatalkan) atau riwayat (digantikan).
     *
     * Pertanyaan ini DULU dijawab dengan "daftar izinnya kosong". Sejak arsip
     * menerima pembetulan tanggal, daftar itu tak lagi kosong — dan pintu lain
     * yang ikut membacanya (catatan review) akan diam-diam terbuka bersamanya.
     * Karena itu keadaan "beku isi" dinyatakan sendiri, bukan disimpulkan dari
     * panjang daftar.
     */
    public static function bekuIsi(?string $status, ?object $tahap): bool
    {
        return self::dibatalkan($tahap) || $status === SopStatus::VERSI_OBSOLETE;
    }
}
