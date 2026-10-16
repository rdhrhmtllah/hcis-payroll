<?php

namespace App\Services\LMS\SOP\Support;

/**
 * Daftar sebab pencabutan akses SOP.
 *
 * Sengaja hidup di PHP, bukan sebagai CHECK constraint. Kolom lama `Alasan`
 * dikunci `CK_N_SOP_Rwt_Alasan` ke lima nilai yang semuanya menjelaskan "kenapa
 * penugasan dibuka lagi"; menambah satu sebab pencabutan ke sana berarti DDL
 * setiap kali daftarnya berkembang. `docs/database/table-design-rules.md`
 * melarang mengunci daftar nilai bisnis yang bisa tumbuh di skema — jadi kolom
 * `Sebab` dibiarkan bebas dan gerbangnya di sini.
 *
 * Menambah sebab baru = tambah satu baris di sini DAN di cerminnya pada
 * resources/js/Pages/LMS/SOP/Shared/support/constants.js. Kalau hanya salah
 * satu yang ditambah, gejalanya adalah pilihan yang tampil di layar lalu
 * ditolak server dengan pesan validasi yang membingungkan.
 */
class SopSebabCabut
{
    /** Sebab yang boleh dipilih admin di layar. */
    public const MANUAL = [
        'SALAH_TARGET' => 'Salah sasaran — tidak seharusnya ditugaskan',
        'PINDAH_UNIT' => 'Karyawan pindah unit atau jabatan',
        'KELUAR' => 'Karyawan keluar atau tidak lagi aktif',
        'TIDAK_RELEVAN' => 'Dokumen tidak relevan bagi karyawan ini',
        'PAKET_DITARIK' => 'Paket Dokumen ditarik atau digantikan',
        'DUPLIKAT' => 'Penugasan ganda',
        'LAINNYA' => 'Lainnya — dijelaskan di catatan',
    ];

    /** Sebab yang hanya boleh ditulis mesin; tak pernah ditawarkan di layar. */
    public const SISTEM = [
        'ATURAN_OTOMATIS' => 'Dicabut mesin aturan penugasan',
    ];

    /** Sebab yang wajib disertai catatan — tanpa itu ia tak menjelaskan apa pun. */
    public const WAJIB_CATATAN = ['LAINNYA'];

    /** @return array<string,string> */
    public static function semua(): array
    {
        return self::MANUAL + self::SISTEM;
    }

    /** @return list<string> */
    public static function kunciManual(): array
    {
        return array_keys(self::MANUAL);
    }

    public static function label(?string $kode): ?string
    {
        return $kode ? (self::semua()[$kode] ?? $kode) : null;
    }

    public static function sahManual(?string $kode): bool
    {
        return $kode !== null && isset(self::MANUAL[$kode]);
    }

    public static function butuhCatatan(?string $kode): bool
    {
        return in_array($kode, self::WAJIB_CATATAN, true);
    }
}
