<?php

namespace App\Services\LMS\SOP\Support;

/**
 * Tipe item SOP (N_SOP_Paket_Item.Tipe_Item).
 * Mirror di frontend: resources/js/Pages/LMS/SOP/Shared/support/constants.js
 */
class SopTipeItem
{
    const DOKUMEN = 'DOKUMEN';   // PDF wajib-baca, tracking per-halaman

    const FORM = 'FORM';      // berkas unduhan

    const VIDEO = 'VIDEO';     // Bunny stream

    const KUIS = 'KUIS';      // HC Learn

    const TAUTAN = 'TAUTAN';    // link eksternal

    const PARAGRAF = 'PARAGRAF';  // blok teks (informational)

    const ABSENSI = 'ABSENSI';   // check-in kehadiran

    const SEMUA = [
        self::DOKUMEN, self::FORM, self::VIDEO, self::KUIS,
        self::TAUTAN, self::PARAGRAF, self::ABSENSI,
    ];

    /** Tipe berbasis berkas (mereferensi konten library + punya versi). */
    const FILE = [self::DOKUMEN, self::FORM, self::VIDEO];

    /** Tipe ber-versi (spine N_SOP_Item_Versi): file + KUIS (versi = referensi ujian HC Learn). */
    const VERSIONED = [self::DOKUMEN, self::FORM, self::VIDEO, self::KUIS];

    /* ── Profil tipe ───────────────────────────────────────────────────────
     *
     * Blok mana yang berlaku bagi sebuah tipe, dan mana yang wajib. Ini
     * SATU-SATUNYA tempat pertanyaan itu dijawab.
     *
     * Sebelum peta ini ada, layarlah yang memutuskan, dan tidak ada pihak kedua
     * yang menyetujuinya. Akibatnya terukur pada 20 Agustus 2026: nol dari 17
     * item non-dokumen pernah punya Nomor_SOP_Resmi, sementara 47% versi FORM
     * punya Nomor_Pengesahan. Bedanya bukan kebutuhan — form mengisi apa yang
     * disodorkan, dan yang tidak disodorkan tidak pernah terisi.
     *
     * Cermin frontend: resources/js/Pages/LMS/SOP/Shared/support/constants.js
     * (PROFIL_TIPE). Nama blok wajib identik.
     */

    const BLOK_NOMOR_RESMI = 'nomor_resmi';

    const BLOK_PENGESAHAN = 'pengesahan';

    const BLOK_BERKAS = 'berkas';

    const BLOK_UJIAN = 'ujian';

    /** Form kosong untuk diunduh karyawan (5 Okt 2026); berkas utama FORM adalah contoh pengisiannya. */
    const BLOK_LAMPIRAN = 'lampiran';

    /** Dirender, dan ditolak server bila kosong. */
    const WAJIB = 'wajib';

    /** Dirender, boleh kosong. */
    const BERLAKU = 'berlaku';

    /**
     * Blok yang TIDAK terdaftar berarti tidak berlaku: tidak dirender, dan
     * ditolak server bila tetap dikirim terisi. Itu yang membedakannya dari
     * sekadar menyembunyikan lewat v-if.
     */
    private const PROFIL = [
        self::DOKUMEN => [
            self::BLOK_NOMOR_RESMI => self::WAJIB,
            self::BLOK_PENGESAHAN => self::WAJIB,
            self::BLOK_BERKAS => self::WAJIB,
        ],
        /* Pengesahan formulir OPSIONAL (keputusan user 5 Okt 2026): blok
           tampil dan boleh diisi, tanpa menahan langkah, terbit, atau lencana.
           Penjaga yang MENUNTUT pengesah bertanya pada blokWajib(), bukan
           blokBerlaku(). */
        self::FORM => [
            self::BLOK_PENGESAHAN => self::BERLAKU,
            self::BLOK_BERKAS => self::WAJIB,
            self::BLOK_LAMPIRAN => self::BERLAKU,
        ],
        self::VIDEO => [
            self::BLOK_BERKAS => self::WAJIB,
        ],
        self::KUIS => [
            self::BLOK_UJIAN => self::WAJIB,
        ],
    ];

    /** Blok apa saja yang berlaku bagi tipe ini, beserta tingkatnya. */
    public static function profil(string $tipe): array
    {
        return self::PROFIL[$tipe] ?? [];
    }

    public static function blokBerlaku(string $tipe, string $blok): bool
    {
        return isset(self::PROFIL[$tipe][$blok]);
    }

    public static function blokWajib(string $tipe, string $blok): bool
    {
        return (self::PROFIL[$tipe][$blok] ?? null) === self::WAJIB;
    }

    public static function isFile(string $tipe): bool
    {
        return in_array($tipe, self::FILE, true);
    }

    /** True bila tipe memakai spine versi item (file atau KUIS). */
    public static function isVersioned(string $tipe): bool
    {
        return in_array($tipe, self::VERSIONED, true);
    }

    /**
     * Jenis konten Library yang sah untuk sebuah tipe item.
     *
     * FORM boleh memakai berkas jenis apa pun (document, image, video) karena
     * formulir adalah berkas unduhan bebas — tak pernah ditampilkan di penampil.
     */
    private const KONTEN_SAH = [
        self::DOKUMEN => ['document'],
        self::FORM => ['document', 'image', 'video'],
        self::VIDEO => ['video'],
    ];

    /**
     * Apakah konten itu boleh dipakai tipe item ini?
     *
     * Yang dijaga bukan sekadar "ada isinya", melainkan isinya JENIS YANG
     * BENAR. Item VIDEO yang dilampiri PDF tetap lolos pemeriksaan "punya
     * berkas", lalu karyawan membuka pemutar video yang isinya bukan video —
     * kegagalan yang baru ketahuan di sisi mereka.
     *
     * Tipe yang tak ada di peta (KUIS, TAUTAN, PARAGRAF, ABSENSI) tak memakai
     * konten Library sama sekali, jadi tak ada yang perlu dicocokkan.
     */
    public static function kontenCocok(string $tipe, ?string $tipeKonten): bool
    {
        if (! isset(self::KONTEN_SAH[$tipe])) {
            return true;
        }

        /* Konten tanpa jenis tercatat dibiarkan lolos: menolaknya akan
           memblokir penerbitan karena kolom yang kosong di sisi Library,
           bukan karena admin memilih yang salah. */
        if ($tipeKonten === null || $tipeKonten === '') {
            return true;
        }

        return in_array($tipeKonten, self::KONTEN_SAH[$tipe], true);
    }
}
