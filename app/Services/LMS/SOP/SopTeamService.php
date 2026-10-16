<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Facades\DB;

/**
 * Kepatuhan Tim (read-only untuk leader).
 * Sumber tim: HRIS_Karyawan_Team (Kode_Karyawan_Team = leader, Kode_Karyawan = anggota).
 * Ringkasan kepatuhan per anggota dari N_SOP_Penjadwalan_Detail (paket-level).
 * TANPA eskalasi/aksi — murni visibilitas (sesuai keputusan Cluster B).
 */
class SopTeamService
{
    /** Kode anggota tim (trim, unik). */
    public function teamKodes(string $leader): array
    {
        return DB::table('HRIS_Karyawan_Team')
            ->where('Kode_Karyawan_Team', $leader)
            ->pluck('Kode_Karyawan')
            ->map(fn ($x) => trim((string) $x))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function isLeader(string $kode): bool
    {
        return DB::table('HRIS_Karyawan_Team')->where('Kode_Karyawan_Team', $kode)->exists();
    }

    /** Ringkasan kepatuhan seluruh anggota tim + KPI. */
    public function ringkasan(string $leader): array
    {
        $kodes = $this->teamKodes($leader);
        if (empty($kodes)) {
            return ['is_leader' => false, 'kpi' => ['anggota' => 0, 'coverage' => 0, 'terlambat' => 0], 'anggota' => []];
        }

        // agregat per anggota (hanya yang punya penugasan aktif)
        $agg = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->whereIn('d.Kode_Karyawan', $kodes)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->groupBy('d.Kode_Karyawan')
            ->selectRaw("d.Kode_Karyawan,
                COUNT(*) as total,
                SUM(CASE WHEN d.Status_Paket = 'SELESAI' THEN 1 ELSE 0 END) as selesai,
                SUM(CASE WHEN d.Status_Paket = 'PROGRESS' THEN 1 ELSE 0 END) as progress,
                SUM(CASE WHEN pj.Tipe_Tenggat = 'DEADLINE' AND pj.Tanggal_Deadline < CAST(GETDATE() AS DATE)
                         AND d.Status_Paket <> 'SELESAI' THEN 1 ELSE 0 END) as terlambat")
            ->get()
            ->keyBy(fn ($r) => trim((string) $r->Kode_Karyawan));

        // nama anggota
        $nama = DB::table('Karyawan')->whereIn('Kode_Karyawan', $kodes)
            ->pluck('Nama', 'Kode_Karyawan')
            ->mapWithKeys(fn ($v, $k) => [trim((string) $k) => $v])
            ->all();

        $anggota = [];
        $sumTotal = 0;
        $sumSelesai = 0;
        $sumTerlambat = 0;
        foreach ($kodes as $kode) {
            $a = $agg->get($kode);
            $total = (int) ($a->total ?? 0);
            $selesai = (int) ($a->selesai ?? 0);
            $progress = (int) ($a->progress ?? 0);
            $terlambat = (int) ($a->terlambat ?? 0);
            $sumTotal += $total;
            $sumSelesai += $selesai;
            $sumTerlambat += $terlambat;

            $anggota[] = [
                'Kode_Karyawan' => $kode,
                'Nama' => $nama[$kode] ?? $kode,
                'Total' => $total,
                'Selesai' => $selesai,
                'Progress' => $progress,
                'Belum' => max(0, $total - $selesai - $progress),
                'Terlambat' => $terlambat,
                'Persen' => $total > 0 ? (int) round($selesai / $total * 100) : 0,
            ];
        }

        // urut: terlambat terbanyak dulu, lalu persen terendah
        usort($anggota, fn ($x, $y) => ($y['Terlambat'] <=> $x['Terlambat']) ?: ($x['Persen'] <=> $y['Persen']));

        return [
            'is_leader' => true,
            'kpi' => [
                'anggota' => count($kodes),
                'coverage' => $sumTotal > 0 ? (int) round($sumSelesai / $sumTotal * 100) : 0,
                'terlambat' => $sumTerlambat,
            ],
            'anggota' => $anggota,
        ];
    }

    /**
     * Daftar SOP satu anggota (authz: harus anggota tim leader).
     *
     * @return array|null null bila bukan anggota tim (tolak akses).
     */
    public function anggota(string $leader, string $kodeAnggota): ?array
    {
        $kodeAnggota = trim($kodeAnggota);
        if (! in_array($kodeAnggota, $this->teamKodes($leader), true)) {
            return null;
        }

        $nama = DB::table('Karyawan')->where('Kode_Karyawan', $kodeAnggota)->value('Nama') ?? $kodeAnggota;

        $rows = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->where('d.Kode_Karyawan', $kodeAnggota)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->orderBy('d.Status_Paket')
            ->get([
                'p.Nama_Paket', 'p.Prioritas', 'd.Status_Paket', 'd.Tanggal_Selesai',
                'pj.Tipe_Tenggat', 'pj.Tanggal_Deadline',
            ])
            ->map(function ($r) {
                $terlambat = $r->Tipe_Tenggat === 'DEADLINE' && $r->Tanggal_Deadline
                    && \Illuminate\Support\Carbon::parse($r->Tanggal_Deadline)->lt(\Illuminate\Support\Carbon::today())
                    && $r->Status_Paket !== SopStatus::STATUS_SELESAI;

                return [
                    'Nama_Paket' => $r->Nama_Paket,
                    'Prioritas' => $r->Prioritas,
                    'Status_Paket' => $r->Status_Paket,
                    'Is_Terlambat' => $terlambat,
                    'Tenggat' => $r->Tipe_Tenggat === 'DEADLINE' && $r->Tanggal_Deadline
                        ? \Illuminate\Support\Carbon::parse($r->Tanggal_Deadline)->format('Y-m-d') : null,
                    'Tanggal_Selesai' => $r->Tanggal_Selesai
                        ? \Illuminate\Support\Carbon::parse($r->Tanggal_Selesai)->format('Y-m-d H:i') : null,
                ];
            });

        return ['nama' => $nama, 'kode' => $kodeAnggota, 'sop' => $rows];
    }
}
