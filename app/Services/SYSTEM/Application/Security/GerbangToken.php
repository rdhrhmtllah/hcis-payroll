<?php

namespace App\Services\SYSTEM\Application\Security;

/**
 * Pemeriksaan bearer token untuk endpoint operasional yang tidak lewat `auth`.
 *
 * ── Kenapa ini punya rumah, dan kenapa bentuknya begini ──────────────────────
 *
 * Beberapa endpoint dipanggil mesin, bukan orang: pemicu penjadwal, webhook
 * Cloud Tasks, pemeriksa koneksi. Mereka tidak bisa memakai middleware `auth`,
 * jadi masing-masing memeriksa sendiri sebuah bearer token.
 *
 * Bentuk yang dipakai sebelumnya:
 *
 *     if ($request->bearerToken() !== config('...TOKEN', '<literal panjang>')) {
 *         abort(403);
 *     }
 *
 * Ada dua hal yang salah di situ, dan yang kedua tidak kentara.
 *
 * ── Satu: perbandingan yang bocor waktu ──────────────────────────────────────
 *
 * `!==` pada string berhenti di karakter pertama yang berbeda. Selisih
 * waktunya kecil, tetapi endpoint ini bisa dipanggil berulang tanpa batas oleh
 * siapa pun yang tahu URL-nya. hash_equals() membandingkan dalam waktu tetap.
 *
 * ── Dua: membuang nilai cadangan justru membuatnya GAGAL-TERBUKA ─────────────
 *
 * Menghapus literal cadangan dari config() tampak seperti perbaikan, padahal:
 *
 *     config('...TOKEN')            -> null   (kunci tak diset)
 *     $request->bearerToken()       -> null   (permintaan tanpa Authorization)
 *     null !== null                 -> false  -> GERBANG LOLOS
 *
 * Jadi perbaikan yang naif mengubah "memakai token yang tersimpan di kode"
 * menjadi "tidak memeriksa apa pun". Kelas ini menolak lebih dulu bila
 * konfigurasinya kosong: tanpa token yang dikonfigurasi, tidak ada yang boleh
 * masuk.
 */
class GerbangToken
{
    /**
     * @param  string|null  $token  nilai dari $request->bearerToken()
     * @param  string  $kunciConfig  mis. 'services.project_config.SCHEDULER_BEARER_TOKEN'
     * @return bool false bila tokennya salah ATAU konfigurasinya belum diisi
     */
    public static function sah(?string $token, string $kunciConfig): bool
    {
        $harap = (string) config($kunciConfig, '');

        // GAGAL TERTUTUP: tanpa token terkonfigurasi, tidak ada yang lolos.
        if ($harap === '') {
            return false;
        }

        return hash_equals($harap, (string) $token);
    }
}
