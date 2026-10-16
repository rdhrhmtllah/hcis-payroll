<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat yang menerjemahkan status kontrak karyawan menjadi label
 * "Status Kepegawaian" yang tampil di layar (Probation / Kontrak / Tetap).
 *
 * Sumber nilai  : Karyawan.status_kontrak
 * Sumber label  : N_HRIS_Aturan_Kelayakan_Cuti — dicocokkan lewat kolom
 *                 Status_Kontrak, labelnya diambil dari Keterangan.
 *                 Bila Keterangan kosong (kolomnya nullable) label jatuh ke
 *                 Status_Karyawan, dan bila itu pun kosong barisnya diabaikan.
 *
 * Nilai yang tidak dikenal, kosong, atau NULL SELALU menjadi '-'. Halaman tidak
 * boleh menebak: 950 dari 958 karyawan aktif punya status_kontrak NULL, jadi
 * nilai bawaan apa pun akan melabeli mayoritas orang dengan status yang salah.
 *
 * Kolom Keterangan bisa disunting lewat halaman Ketentuan Cuti, karena itu
 * KetentuanCutiController wajib memanggil lupakanPeta() setiap kali aturan
 * disimpan, diubah, atau dihapus.
 */
class StatusKepegawaianHelper
{
    /** Label untuk status kontrak yang kosong atau tidak terdaftar di master. */
    public const TIDAK_DIKETAHUI = '-';

    private const CACHE_KEY = 'status_kepegawaian:peta_label';

    private const CACHE_TTL = 600;

    /** Memo per-request supaya satu halaman tidak membaca cache berkali-kali. */
    private static ?array $memo = null;

    /**
     * Label yang tampil untuk sebuah nilai Karyawan.status_kontrak.
     */
    public static function labelUntuk(?string $statusKontrak): string
    {
        return self::labelDariPeta($statusKontrak, self::peta());
    }

    /**
     * Versi murni tanpa sentuhan database — dipakai pemanggil yang sudah
     * memegang petanya sendiri, dan oleh pengujian.
     *
     * @param  array<string, string>  $peta  kunci sudah ternormalisasi
     */
    public static function labelDariPeta(?string $statusKontrak, array $peta): string
    {
        $kunci = self::normalisasi($statusKontrak);

        if ($kunci === '') {
            return self::TIDAK_DIKETAHUI;
        }

        return $peta[$kunci] ?? self::TIDAK_DIKETAHUI;
    }

    /**
     * Peta status kontrak (ternormalisasi) → label, dari master aturan cuti.
     *
     * @return array<string, string>
     */
    public static function peta(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        return self::$memo = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn () => self::bacaMaster(),
        );
    }

    /**
     * Buang cache peta. Wajib dipanggil setiap kali baris aturan kelayakan
     * cuti berubah, kalau tidak suntingan admin baru terlihat 10 menit kemudian.
     */
    public static function lupakanPeta(): void
    {
        self::$memo = null;
        Cache::forget(self::CACHE_KEY);
    }

    public static function normalisasi(?string $nilai): string
    {
        return strtoupper(trim((string) $nilai));
    }

    /**
     * @return array<string, string>
     */
    private static function bacaMaster(): array
    {
        $baris = DB::table('N_HRIS_Aturan_Kelayakan_Cuti')
            ->select('Id_Aturan_Cuti', 'Status_Kontrak', 'Status_Karyawan', 'Keterangan')
            ->whereNotNull('Status_Kontrak')
            // Urutan wajib pasti: bila dua baris memakai Status_Kontrak yang sama,
            // baris ber-Id terbesar yang menang — bukan urutan acak dari server.
            ->orderBy('Id_Aturan_Cuti')
            ->get();

        return self::petaDariBaris($baris);
    }

    /**
     * Perasan baris master menjadi peta label. Dipisah dari kuerinya supaya
     * aturan pemilihan label bisa diuji tanpa menyentuh database.
     *
     * @param  iterable<object>  $baris  sudah terurut, yang belakangan menang
     * @return array<string, string>
     */
    public static function petaDariBaris(iterable $baris): array
    {
        $peta = [];

        foreach ($baris as $b) {
            $kunci = self::normalisasi($b->Status_Kontrak);

            if ($kunci === '') {
                continue;
            }

            $label = trim((string) ($b->Keterangan ?? ''));

            if ($label === '') {
                $label = trim((string) ($b->Status_Karyawan ?? ''));
            }

            if ($label === '') {
                continue;
            }

            $peta[$kunci] = $label;
        }

        return $peta;
    }
}
