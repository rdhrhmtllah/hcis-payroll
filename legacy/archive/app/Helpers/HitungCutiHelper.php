<?php

namespace App\Helpers;

use App\Models\Cuti;
use App\Models\ESS\Employee\Models\Karyawan;

class HitungCutiHelper
{
    public static function hitungSaldoCuti(Karyawan $karyawan)
    {
        if (! $karyawan->is_tetap) {
            return 0;
        }

        $kuotaTahunan = $karyawan->kuota_cuti ?? 12;

        $cutiDiambil = Cuti::where('Kode_Karyawan', $karyawan->Kode_Karyawan)
            ->where('status', 'Disetujui')
            ->where('is_active', 1)
            ->sum('jumlah_hari');

        $sisaCuti = max($kuotaTahunan - $cutiDiambil, 0);

        $karyawan->sis_cuti = $sisaCuti;
        $karyawan->save();

        return $sisaCuti;
    }
}
