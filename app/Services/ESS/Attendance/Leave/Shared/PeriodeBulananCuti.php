<?php

namespace App\Services\ESS\Attendance\Leave\Shared;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Jendela periode bulanan untuk perintah akrual kuota cuti.
 *
 * ── Kenapa dipusatkan ────────────────────────────────────────────────────────
 *
 * resolveTargetPeriod() ada dua salinan — di HakCutiTambahan dan
 * tambahKuotaCutiBulanan — identik sampai ke tanda bacanya; satu-satunya beda
 * adalah nama parameternya ($today vs $now). Keduanya perintah terjadwal yang
 * MENAMBAH saldo cuti. Jendela periode yang menyimpang di salah satunya berarti
 * kuota bulan yang salah masuk ke buku cuti orang, dan koreksinya harus dikejar
 * lewat ledger.
 *
 * ── Lubang yang sekalian ditutup ─────────────────────────────────────────────
 *
 * Kedua salinan memeriksa bentuk --month dengan /^\d{4}-\d{2}$/ lalu langsung
 * menyerahkannya ke Carbon::createFromFormat('Y-m-d', "$month-01"). Regex itu
 * meloloskan bulan di luar 01-12, dan Carbon MELIMPAHKANNYA tanpa protes:
 *
 *     --month=2026-13  ->  2027-01-01   (tertangkap penjaga "masa depan")
 *     --month=2026-00  ->  2025-12-01   (LOLOS: masa lalu, jadi diterima)
 *
 * Jadi salah ketik "00" menambahkan kuota untuk Desember 2025 tanpa satu pun
 * peringatan. Bulan kini diperiksa 01-12 secara eksplisit. Ini perubahan
 * perilaku yang disengaja: masukan yang tadinya diterima diam-diam sekarang
 * ditolak dengan pesan yang menyebut apa yang salah.
 *
 * ── Bentuk hasilnya ──────────────────────────────────────────────────────────
 *
 * Mengembalikan larik, bukan melempar, supaya pemanggilnya tetap bisa memakai
 * $this->error() lalu mengembalikan null — persis seperti sebelumnya. Kelasnya
 * murni: tidak menyentuh Command, tidak menyentuh basis data, jadi aturannya
 * benar-benar bisa diuji.
 */
class PeriodeBulananCuti
{
    /**
     * @return array{mulai: ?Carbon, selesai: ?Carbon, galat: ?string}
     */
    public static function untuk(?string $bulan, CarbonInterface $acuan): array
    {
        $acuan = Carbon::instance($acuan);

        if ($bulan !== null && $bulan !== '') {
            if (! preg_match('/^(\d{4})-(\d{2})$/', $bulan, $bagian)) {
                return self::galat('Format --month harus YYYY-MM, contoh: --month=2026-06');
            }

            $angkaBulan = (int) $bagian[2];
            if ($angkaBulan < 1 || $angkaBulan > 12) {
                /*
                 * Tanpa pemeriksaan ini, "2026-00" menjadi Desember 2025 dan
                 * "2026-13" menjadi Januari 2027 — keduanya diam-diam, dan yang
                 * pertama lolos penjaga masa depan.
                 */
                return self::galat("Bulan pada --month harus 01-12, diterima: {$bagian[2]}");
            }

            $mulai = Carbon::createFromFormat('Y-m-d', $bulan.'-01')->startOfDay();
        } else {
            $mulai = $acuan->clone()->startOfMonth()->startOfDay();
        }

        if ($mulai->gt($acuan->clone()->startOfMonth())) {
            return self::galat('Future month tidak boleh diproses.');
        }

        /*
         * Bulan berjalan berhenti HARI INI, bukan akhir bulan: hari yang belum
         * terjadi tidak boleh ikut menghasilkan akrual.
         */
        $selesai = $mulai->isSameMonth($acuan)
            ? $acuan->clone()->startOfDay()
            : $mulai->clone()->endOfMonth()->startOfDay();

        return ['mulai' => $mulai, 'selesai' => $selesai, 'galat' => null];
    }

    /** @return array{mulai: null, selesai: null, galat: string} */
    private static function galat(string $pesan): array
    {
        return ['mulai' => null, 'selesai' => null, 'galat' => $pesan];
    }
}
