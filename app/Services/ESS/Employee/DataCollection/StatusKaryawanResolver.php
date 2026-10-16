<?php

namespace App\Services\ESS\Employee\DataCollection;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatusKaryawanResolver
{
    /**
     * Angka level jabatan. TIDAK ada di tabel Karyawan — kolom
     * Karyawan.ID_Level_Jabatan berisi id baris jabatan (559-647), bukan angka
     * levelnya. Angka level hanya hidup di View_Golongan_Sub_Golongan_Level_Jabatan,
     * jadi kriteria ini wajib lewat join view.
     *
     * Diverifikasi ke DB 14 Agt 2026: ID_Level 21 = nama_level 'FREELANCE'
     * dengan Flag_Harian = 'Y'. Konvensi yang sama dipakai picker LMS dan
     * target penjadwalan SOP.
     */
    private const LEVEL_PHL = 21;

    /**
     * Kolom Karyawan.Cabang_Untuk_Gaji. Jaring pengaman kedua: freelance di
     * cabang lain TIDAK dianggap PHL untuk keperluan pengumpulan data ini.
     *
     * Di data 14 Agt 2026 kedua kriteria menghasilkan populasi yang sama
     * (8 orang) karena seluruh freelance kebetulan bercabang EMI — kriteria ini
     * baru terasa gunanya saat muncul freelance di cabang lain.
     */
    private const CABANG_PHL = 'EMI';

    public function isPhl(string $kodeKaryawan): bool
    {
        if ($kodeKaryawan === '') {
            return false;
        }

        // Kunci cache diberi versi: mengubah aturan tanpa menaikkannya membuat
        // orang tetap dinilai dengan aturan lama sampai TTL 6 jam habis.
        return Cache::remember(
            "pengumpulan-data:is-phl:v2:{$kodeKaryawan}",
            now()->addHours(6),
            function () use ($kodeKaryawan): bool {
                $baris = DB::table('Karyawan as k')
                    ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'lvl.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
                    ->where('k.Kode_Karyawan', $kodeKaryawan)
                    ->first(['lvl.ID_Level', 'k.Cabang_Untuk_Gaji']);

                if (! $baris) {
                    return false;
                }

                return $this->cocokKriteria(
                    $baris->ID_Level === null ? null : (int) $baris->ID_Level,
                    $baris->Cabang_Untuk_Gaji,
                );
            },
        );
    }

    /**
     * Aturannya sendiri, tanpa database, supaya seluruh kombinasi bisa diuji —
     * termasuk "level 21 tapi cabang lain" yang tidak ada satu pun contohnya di
     * data nyata.
     *
     * Cabang dicocokkan tanpa peduli besar-kecil huruf dan spasi pinggir: nilai
     * itu diketik manusia di sistem induk, dan salah menilainya berarti field
     * atasan hilang dari orang yang justru harus mengisinya.
     */
    public function cocokKriteria(?int $level, ?string $cabang): bool
    {
        if ($level === null || $cabang === null) {
            return false;
        }

        return $level === self::LEVEL_PHL
            && strcasecmp(trim($cabang), self::CABANG_PHL) === 0;
    }
}
