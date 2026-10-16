<?php

namespace App\Services\VISITOR\Permit\VehiclePermit;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Kategori permit yang boleh dipilih karyawan. Satu sumber untuk dropdown
 * pengajuan, validasi pengajuan, dan koreksi verifikator: penyaring yang
 * hanya ada di satu jalur membiarkan COKLAT lolos lewat jalur lainnya.
 * NULL dibaca boleh tampil (lihat KategoriPermitMunculPengajuanTest).
 */
final class KategoriPermitPengajuan
{
    public static function saring(Builder $query): Builder
    {
        return $query->whereNull('Flag_Muncul_Pengajuan')->orWhere('Flag_Muncul_Pengajuan', 'Y');
    }

    public static function temukan(int $idKategori): ?object
    {
        return DB::table('N_EMI_VISITOR_Master_Kategori_Permit')
            ->where('Id_Kategori_Permit', $idKategori)
            ->where('Flag_Aktif', 'Y')
            ->where(fn (Builder $query) => self::saring($query))
            ->first();
    }
}
