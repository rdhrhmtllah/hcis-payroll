<?php

namespace App\Services\LMS\SOP\Support;

/**
 * Tanggal tata kelola versi sebagai KEADAAN, bukan sekadar tanggal.
 *
 * Modul: LMS · Submodul: SOP · Fitur: Export · Lapisan: Support.
 *
 * Sebuah kolom tanggal menjawab "kapan", tetapi yang ditanyakan orang saat
 * membuka laporan tata kelola adalah "mana yang bermasalah". Menjawabnya dari
 * kolom tanggal menuntut setiap pembaca berkas menghitung sendiri selisihnya
 * terhadap hari ini di Excel — dan setiap pembaca akan memilih ambang yang
 * sedikit berbeda. Karena itu keadaannya dihitung di sini, sekali.
 *
 * AMBANGNYA SATU ANGKA DI SATU TEMPAT. Dua dataset memakainya; menaruh 90 di
 * masing-masing berarti suatu hari yang satu berubah dan yang lain tidak.
 *
 * "Tanpa versi aktif" adalah cabang PERTAMA, bukan efek samping dari tanggal
 * yang kosong. Item atau dokumen tanpa versi aktif memang tidak punya tanggal,
 * tetapi artinya sama sekali berbeda dari versi yang punya tanggal kosong:
 * yang satu belum pernah sah terbit, yang lain sah tanpa batas waktu.
 * Meleburkan keduanya jadi satu sel kosong menghapus temuan terpenting di
 * laporan ini.
 */
class SopVersiTanggal
{
    /** Berapa hari ke depan yang masih dianggap "sebentar lagi". */
    public const AMBANG_HARI = 90;

    public const TANPA_VERSI = 'Tanpa versi aktif';

    /**
     * Label keadaan masa berlaku, berurut dari yang paling mendesak.
     *
     * Daftar ini BUKAN duplikat teks di dalam CASE — CASE-nya dirakit dari
     * konstanta ini. Bila keduanya ditulis terpisah, memilih sebuah opsi di
     * layar akan membandingkan teks yang tak pernah dihasilkan kueri, dan
     * hasilnya nol baris tanpa satu pun galat: laporan tampak "memang kosong".
     */
    public const KEADAAN_MASA_BERLAKU = [
        'Kedaluwarsa',
        'Akan kedaluwarsa (<= '.self::AMBANG_HARI.' hari)',
        'Berlaku',
        'Tanpa masa berlaku',
        self::TANPA_VERSI,
    ];

    public const KEADAAN_REVIEW = [
        'Jatuh tempo',
        'Segera ditinjau (<= '.self::AMBANG_HARI.' hari)',
        'Terjadwal',
        'Tanpa jadwal review',
        self::TANPA_VERSI,
    ];

    /** Pasangan value/label siap pakai untuk katalog saringan. */
    public static function opsiMasaBerlaku(): array
    {
        return array_map(fn ($v) => ['value' => $v, 'label' => $v], self::KEADAAN_MASA_BERLAKU);
    }

    public static function opsiReview(): array
    {
        return array_map(fn ($v) => ['value' => $v, 'label' => $v], self::KEADAAN_REVIEW);
    }

    /**
     * Keadaan masa berlaku versi.
     *
     * $alias menunjuk baris N_SOP_Item_Versi. Kolom Id_SOP_Item_Versi dipakai
     * sebagai penanda "ada versinya" karena join ke versi selalu LEFT.
     */
    public static function sqlStatusMasaBerlaku(string $alias = 'vr'): string
    {
        $ambang = self::AMBANG_HARI;
        [$lewat, $segera, $aman, $tanpaTanggal, $tanpaVersi] = self::KEADAAN_MASA_BERLAKU;

        return "CASE
            WHEN {$alias}.Id_SOP_Item_Versi IS NULL THEN '{$tanpaVersi}'
            WHEN {$alias}.Tanggal_Kadaluarsa IS NULL THEN '{$tanpaTanggal}'
            WHEN {$alias}.Tanggal_Kadaluarsa < CAST(GETDATE() AS date) THEN '{$lewat}'
            WHEN {$alias}.Tanggal_Kadaluarsa <= DATEADD(day, {$ambang}, CAST(GETDATE() AS date))
                THEN '{$segera}'
            ELSE '{$aman}'
        END";
    }

    /** Keadaan jadwal review berikutnya — bentuknya sengaja kembar dengan masa berlaku. */
    public static function sqlStatusReview(string $alias = 'vr'): string
    {
        $ambang = self::AMBANG_HARI;
        [$lewat, $segera, $aman, $tanpaTanggal, $tanpaVersi] = self::KEADAAN_REVIEW;

        return "CASE
            WHEN {$alias}.Id_SOP_Item_Versi IS NULL THEN '{$tanpaVersi}'
            WHEN {$alias}.Tanggal_Review_Berikutnya IS NULL THEN '{$tanpaTanggal}'
            WHEN {$alias}.Tanggal_Review_Berikutnya < CAST(GETDATE() AS date) THEN '{$lewat}'
            WHEN {$alias}.Tanggal_Review_Berikutnya <= DATEADD(day, {$ambang}, CAST(GETDATE() AS date))
                THEN '{$segera}'
            ELSE '{$aman}'
        END";
    }

    /** 1 bila versi sudah lewat masa berlakunya hari ini. Dipakai sebagai penjumlah. */
    public static function sqlKadaluarsa(string $alias = 'vr'): string
    {
        return "CASE WHEN {$alias}.Tanggal_Kadaluarsa IS NOT NULL
                      AND {$alias}.Tanggal_Kadaluarsa < CAST(GETDATE() AS date)
                     THEN 1 ELSE 0 END";
    }

    /** 1 bila jadwal review berikutnya sudah terlewat hari ini. */
    public static function sqlReviewJatuhTempo(string $alias = 'vr'): string
    {
        return "CASE WHEN {$alias}.Tanggal_Review_Berikutnya IS NOT NULL
                      AND {$alias}.Tanggal_Review_Berikutnya < CAST(GETDATE() AS date)
                     THEN 1 ELSE 0 END";
    }

    /**
     * Keadaan masa berlaku SATU tanggal, di luar SQL.
     *
     * Kembarannya di atas menjawab pertanyaan yang sama untuk sekumpulan baris
     * di dalam kueri; yang ini untuk satu baris yang sudah di tangan — layar
     * detail versi, misalnya. Keduanya WAJIB memberi jawaban yang sama, karena
     * itu keduanya membaca konstanta yang sama. Menyalin ambangnya ke layar
     * akan membuat sebuah versi terbaca "akan kadaluarsa" di laporan dan
     * "berlaku" di modalnya, dan tak ada yang tahu mana yang benar.
     *
     * @return array{keadaan: string, sisa_hari: int|null}
     *                                                     sisa_hari negatif berarti sudah lewat sekian hari.
     */
    public static function keadaanMasaBerlaku($tanggal): array
    {
        [$lewat, $segera, $aman, $tanpaTanggal] = self::KEADAAN_MASA_BERLAKU;

        return self::nilaiTanggal($tanggal, $lewat, $segera, $aman, $tanpaTanggal);
    }

    /** Keadaan jadwal review berikutnya — bentuknya sengaja kembar dengan masa berlaku. */
    public static function keadaanReview($tanggal): array
    {
        [$lewat, $segera, $aman, $tanpaTanggal] = self::KEADAAN_REVIEW;

        return self::nilaiTanggal($tanggal, $lewat, $segera, $aman, $tanpaTanggal);
    }

    /**
     * Selisih hari terhadap hari ini, lalu dipetakan ke salah satu keadaan.
     *
     * Perbandingannya dibulatkan ke HARI di kedua sisi. Tanpa itu, tanggal
     * kadaluarsa hari ini yang tersimpan sebagai tengah malam akan terbaca
     * "sudah lewat" sepanjang hari terakhirnya masih berlaku.
     */
    private static function nilaiTanggal(
        $tanggal,
        string $lewat,
        string $segera,
        string $aman,
        string $tanpaTanggal,
    ): array {
        if (blank($tanggal)) {
            return ['keadaan' => $tanpaTanggal, 'sisa_hari' => null];
        }

        try {
            $target = \Carbon\CarbonImmutable::parse($tanggal)->startOfDay();
        } catch (\Exception $e) {
            return ['keadaan' => $tanpaTanggal, 'sisa_hari' => null];
        }

        $sisa = (int) \Carbon\CarbonImmutable::now()->startOfDay()->diffInDays($target, false);

        return [
            'keadaan' => match (true) {
                $sisa < 0 => $lewat,
                $sisa <= self::AMBANG_HARI => $segera,
                default => $aman,
            },
            'sisa_hari' => $sisa,
        ];
    }
}
