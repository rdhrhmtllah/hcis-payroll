<?php

namespace App\Services\KPI;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Navigasi periode di modal "Detail Employee Performance".
 *
 * Modal itu dulu terkunci pada SATU transaksi (id_update_performance) — periode
 * yang dibuka dari daftar. Supaya periodenya bisa diganti di dalam modal tanpa
 * mengubah dashboard di belakangnya, dua hal perlu dijawab per orang & per
 * periode:
 *
 *   rujukan()  — transaksi mana yang menjadi isi detail periode itu, lalu
 *                recentKpiUserDetail() yang sudah ada merakit isinya (angka
 *                sama persis dengan detail yang dibuka dari daftar).
 *   navigasi() — status tiap bulan untuk strip 12 bulan, supaya pengguna tahu
 *                bulan mana yang punya data sebelum mengklik.
 *
 * Dipakai dashboard Tim (DashboardCeoController) dan Individu
 * (StaffPerformanceDashboardController); hak akses tetap milik controllernya.
 */
class KpiDetailPeriodeService
{
    /** Status strip. 'belum' & 'tanpa_target' dari aturan resmi KpiEnforcementService. */
    public const TERISI = 'terisi';

    public const MENUNGGU = 'menunggu';

    public const BELUM = 'belum';

    public const TANPA_TARGET = 'tanpa_target';

    public const MENDATANG = 'mendatang';

    /**
     * Transaksi rujukan untuk satu periode.
     *
     * - bulan: update performance HIDUP, disetujui, dan terverifikasi di bulan
     *   itu — sama dengan yang tampil di daftar dashboard.
     * - ytd/full: pengajuan yang BERLAKU tahun itu (aktif, disetujui, terbaru),
     *   dengan salah satu update hidup-terverifikasi tahun itu sebagai rujukan
     *   orangnya. Bila tahun itu belum punya satu pun, tidak ada rujukan.
     *
     * @return array{id_pengajuan:int, id_update:int}|null
     */
    public function rujukan(int $idUser, int $tahun, string $mode, ?int $bulan = null): ?array
    {
        $update = $this->updateHidup($idUser)
            ->when(
                $mode === 'bulan',
                fn ($q) => $q->where('up.Periode', sprintf('%04d-%02d', $tahun, (int) $bulan)),
                fn ($q) => $q->where('up.Periode', 'like', $tahun.'-%'),
            )
            ->orderByDesc('up.Id_Update_Performance')
            ->first(['up.Id_Update_Performance', 'up.Id_Pengajuan']);

        if (! $update) {
            return null;
        }

        if ($mode === 'bulan') {
            return ['id_pengajuan' => (int) $update->Id_Pengajuan, 'id_update' => (int) $update->Id_Update_Performance];
        }

        $berlaku = DB::table('KPI_Pengajuan as p')
            ->join('KPI_Master_Penjadwalan as j', 'j.Id_Master_Penjadwalan', '=', 'p.Id_Penjadwalan')
            ->where('p.Id_User', $idUser)
            ->where('j.Tahun', (string) $tahun)
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Flag_Approval', 'Y')
            ->whereNull('p.Status')
            ->orderByDesc(DB::raw('CAST(p.Tanggal + p.Jam AS DATETIME)'))
            ->value('p.Id_Pengajuan');

        return [
            'id_pengajuan' => (int) ($berlaku ?: $update->Id_Pengajuan),
            'id_update' => (int) $update->Id_Update_Performance,
        ];
    }

    /**
     * Status 12 bulan satu tahun untuk strip navigasi.
     *
     * @return array<int, array{bulan:int, periode:string, status:string}>
     */
    public function navigasi(int $idUser, int $tahun): array
    {
        $hidup = DB::table('KPI_Update_Performance')
            ->where('Id_User', $idUser)
            ->where('Flag_Aktif', 'Y')
            ->whereNull('Status')
            ->where('Periode', 'like', $tahun.'-%')
            ->get(['Periode', 'Flag_Approval', 'Flag_Verifikasi', 'Status_Pengajuan'])
            ->groupBy('Periode');

        $sekarang = Carbon::now('Asia/Jakarta');
        // Aturan resmi, 12 bulan sekaligus (bukan 12× classifyParameterTargetForPeriod).
        $bertarget = app(KpiEnforcementService::class)->bulanBertargetSetahun($idUser, $tahun);
        $hasil = [];

        foreach (range(1, 12) as $bulan) {
            $periode = sprintf('%04d-%02d', $tahun, $bulan);
            $baris = $hidup->get($periode, collect());

            if ($baris->contains(fn ($r) => $r->Flag_Approval === 'Y' && $r->Flag_Verifikasi === 'Y' && $r->Status_Pengajuan === 'disetujui')) {
                $status = self::TERISI;
            } elseif ($baris->isNotEmpty()) {
                $status = self::MENUNGGU;
            } elseif ($tahun > $sekarang->year || ($tahun === $sekarang->year && $bulan > $sekarang->month)) {
                $status = self::MENDATANG;
            } else {
                $status = in_array($bulan, $bertarget, true) ? self::BELUM : self::TANPA_TARGET;
            }

            $hasil[] = ['bulan' => $bulan, 'periode' => $periode, 'status' => $status];
        }

        return $hasil;
    }

    /** Update performance hidup, disetujui, dan terverifikasi milik satu orang. */
    private function updateHidup(int $idUser)
    {
        return DB::table('KPI_Update_Performance as up')
            ->where('up.Id_User', $idUser)
            ->where('up.Flag_Aktif', 'Y')
            ->whereNull('up.Status')
            ->where('up.Status_Pengajuan', 'disetujui')
            ->where('up.Flag_Approval', 'Y')
            ->where('up.Flag_Verifikasi', 'Y');
    }
}
