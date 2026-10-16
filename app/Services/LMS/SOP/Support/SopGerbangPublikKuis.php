<?php

namespace App\Services\LMS\SOP\Support;

/**
 * Paket berdistribusi PUBLIK tidak boleh memuat item KUIS.
 *
 * KEPUTUSAN PRODUK 10 SEPTEMBER 2026, dan ia MEMBALIK perilaku sebelumnya —
 * itu perlu ditulis terang-terangan supaya tak ada yang menyangka aturan ini
 * selalu begitu, lalu menghapusnya sebagai "penjaga yang mengada-ada".
 *
 * Sebelum ini kombinasi tersebut justru DIDUKUNG: karyawan yang belum
 * ditugaskan membuka SOP publik, lalu SopPendaftaranMandiri membuatkan
 * penugasan bersumber MANDIRI supaya skor kuisnya punya tempat disimpan —
 * seluruh jejak kerja (N_SOP_Progress, absensi, skor) berkunci pada
 * Id_SOP_Penjadwalan_Detail. Builder bahkan menerangkannya di layar.
 *
 * Yang berubah adalah kebijakannya, bukan temuan teknis: kuis adalah alat
 * verifikasi pemahaman atas kewajiban yang DITUGASKAN, dan paket publik tidak
 * menugaskan siapa pun. Nilai yang lahir dari orang yang tak pernah diwajibkan
 * tak bisa dibaca sebagai kepatuhan, tetapi tetap masuk ke angka yang sama.
 *
 * TIDAK BERLAKU SURUT — dan itu disengaja, sama seperti penjaga pengesah versi.
 * Paket publik ber-kuis yang sudah terbit tetap berjalan; menolaknya belakangan
 * berarti mencabut sesuatu yang sedang dikerjakan orang, dan penugasan mandiri
 * yang sudah ada tak punya tempat berpindah. Yang ditutup adalah KELAHIRAN
 * keadaan itu, bukan keberadaannya.
 */
final class SopGerbangPublikKuis
{
    public const PUBLIK = 'PUBLIK';

    /**
     * Distribusi ini terbuka untuk semua karyawan?
     *
     * NULL DIBACA SEBAGAI PUBLIK, bukan TERBATAS. Kolomnya memang berbawaan
     * 'TERBATAS' di jalur tulis, tetapi setiap jalur BACA memperlakukan NULL
     * sebagai publik (`Mode_Distribusi ?? 'PUBLIK'` di SopKuisDispatchService,
     * SopProgressReadService, SopRepositoryReadService). Penjaga yang membacanya
     * lebih longgar daripada runtime akan meloloskan justru baris yang paling
     * mungkin terlewat.
     */
    public static function publik(?string $mode): bool
    {
        return ($mode ?: self::PUBLIK) === self::PUBLIK;
    }

    /**
     * Aturannya dilanggar?
     *
     * @param  list<string>  $judulKuis  judul item KUIS yang AKTIF di paket itu
     */
    public static function dilanggar(?string $mode, array $judulKuis): bool
    {
        return $judulKuis !== [] && self::publik($mode);
    }

    /**
     * Kalimat penolakan — satu sumber untuk kedua pintu publish.
     *
     * Menyebut item MANA dan DUA jalan keluarnya. Penolakan yang hanya
     * menyatakan larangan memaksa admin menebak apakah yang harus diubah
     * distribusinya atau kuisnya, padahal keduanya sah.
     *
     * @param  list<string>  $judulKuis
     */
    public static function pesan(array $judulKuis): string
    {
        $daftar = implode(', ', array_map(
            fn ($j) => '"'.(trim((string) $j) !== '' ? $j : 'tanpa judul').'"',
            $judulKuis,
        ));

        return 'Paket berdistribusi Publik tidak boleh memuat item Kuis. '
            .'Item kuis di paket ini: '.$daftar.'. '
            .'Ubah Distribusi menjadi Terbatas, atau keluarkan item kuisnya lebih dulu.';
    }
}
