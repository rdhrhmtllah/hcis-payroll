<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KpiScheduleHelper
{
    /**
     * Tentukan batas bulan (cap) YTD untuk sebuah tahun berdasarkan PENJADWALAN
     * pengisian update performance (KPI_Master_Penjadwalan, Flag_Penilaian_KPI='Y'),
     * bukan sekadar kalender (bulan berjalan - 1).
     *
     * Aturan: ambil periode TERJADWAL terakhir yang sudah DIMULAI (Jadwal_Mulai <= now)
     * dengan Periode <= batas kalender (bulan berjalan - 1 untuk tahun berjalan; Des utk
     * tahun lampau). Kalau bulan yang diharapkan (mis. Juni) belum terjadwal, otomatis
     * mundur ke bulan terjadwal terakhir sebelumnya (mis. Mei).
     *
     * Jadwal dicek untuk GLOBAL maupun PERORANGAN (tanpa membatasi ke satu karyawan) —
     * cukup "ada penjadwalan Penilaian utk periode itu". Kalau tetap tak ada penjadwalan
     * yang valid (mis. Periode null / config jadwal belum lengkap), FALLBACK ke periode
     * terakhir yang benar-benar punya realisasi update performance disetujui, supaya YTD
     * tidak pernah kosong padahal datanya jelas ada. $kodeKaryawan (jika diisi) hanya
     * membatasi fallback data ke karyawan tsb.
     *
     * @param  int  $year  Tahun yang dievaluasi (mis. 2026)
     * @param  string|null  $kodeKaryawan  Batasi fallback-data ke karyawan ini; null = agregat.
     * @return int Bulan cap YTD (1..12). 0 jika benar-benar tak ada jadwal & tak ada data.
     */
    public static function resolveYtdCapMonth(int $year, ?string $kodeKaryawan = null): int
    {
        $now = Carbon::now('Asia/Jakarta');
        $currentYear = (int) $now->year;
        $currentMonth = (int) $now->month;

        // Batas atas bulan menurut kalender.
        if ($year < $currentYear) {
            $upperMonth = 12;
        } elseif ($year === $currentYear) {
            $upperMonth = $currentMonth - 1;
            if ($upperMonth < 1) {
                return 0; // Januari tahun berjalan → belum ada bulan yang lewat.
            }
        } else {
            return 0; // Tahun masa depan → tidak ada data YTD.
        }

        $lowerPeriode = sprintf('%04d-01', $year);
        $upperPeriode = sprintf('%04d-%02d', $year, $upperMonth);

        // 1) Periode terjadwal terakhir (Flag_Penilaian_KPI) — global maupun perorangan.
        //    Periode 'YYYY-MM' zero-padded → perbandingan string = kronologis (sargable).
        $periode = DB::table('KPI_Master_Penjadwalan as m')
            ->where('m.Flag_Penilaian_KPI', 'Y')
            ->where('m.Flag_Aktif', 'Y')
            ->where('m.Jadwal_Mulai', '<=', $now)
            ->whereNotNull('m.Periode')
            ->whereBetween('m.Periode', [$lowerPeriode, $upperPeriode])
            ->orderByDesc('m.Periode')
            ->value('m.Periode');

        if ($periode) {
            return (int) substr($periode, 5, 2);
        }

        // 2) Fallback: periode terakhir yang punya realisasi update performance disetujui.
        //    Melindungi dari jadwal yang belum/ tidak ter-set Periode-nya.
        $dataQuery = DB::table('KPI_Update_Performance')
            ->whereNull('Status')
            ->where('Flag_Approval', 'Y')
            ->where('Flag_Verifikasi', 'Y')
            ->whereBetween('Periode', [$lowerPeriode, $upperPeriode]);

        if ($kodeKaryawan !== null) {
            $dataQuery->whereIn('Id_User', function ($sub) use ($kodeKaryawan) {
                $sub->select('UserID_Web')
                    ->from('Karyawan')
                    ->where('Kode_Karyawan', $kodeKaryawan);
            });
        }

        $dataPeriode = $dataQuery->orderByDesc('Periode')->value('Periode');

        if ($dataPeriode) {
            return (int) substr($dataPeriode, 5, 2);
        }

        return 0; // Tidak ada jadwal & tidak ada realisasi → YTD kosong.
    }
}
