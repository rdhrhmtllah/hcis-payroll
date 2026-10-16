<?php

namespace App\Services\KPI\PerformanceAppraisal\Balancing;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Pertanyaan status Balancing yang dipakai lintas service (hitung, publish, sesi). */
class PaBalancingPenjaga
{
    public function sesiTerbuka(int $cycleId): Collection
    {
        return DB::table('N_PA_Balancing_Sesi')->where('PA_Cycle_Id', $cycleId)
            ->whereIn('Status_Sesi', PaBalancingStatus::SESI_BISA_DIUBAH)
            ->orderBy('Id_PA_Balancing_Sesi')->get(['Id_PA_Balancing_Sesi', 'Nama_Sesi', 'Status_Sesi']);
    }

    public function sesiFinalBelumDihitung(int $cycleId): Collection
    {
        return DB::table('N_PA_Balancing_Sesi')->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Sesi', PaBalancingStatus::FINAL)->whereNull('Hitung_Ulang_At')
            ->orderBy('Final_At')->orderBy('Id_PA_Balancing_Sesi')->get(['Id_PA_Balancing_Sesi', 'Nama_Sesi', 'Status_Sesi']);
    }

    public function sesiTerbukaPeserta(int $participantId): ?object
    {
        return DB::table('N_PA_Balancing_Peserta as bp')
            ->join('N_PA_Balancing_Sesi as s', 's.Id_PA_Balancing_Sesi', '=', 'bp.PA_Balancing_Sesi_Id')
            ->where('bp.PA_Participant_Id', $participantId)
            ->whereIn('s.Status_Sesi', PaBalancingStatus::SESI_BISA_DIUBAH)
            ->first(['s.Id_PA_Balancing_Sesi', 's.Nama_Sesi']);
    }

    /**
     * Hasil siklus yang sudah dibaca orang: terbit ke karyawan atau dirilis ke leader. Pada siklus
     * KURVA hitung ulang mengunci ulang kurva seluruh grup, jadi satu saja sudah menghalanginya.
     */
    public function jumlahSudahDibaca(int $cycleId): int
    {
        return (int) DB::table('N_PA_Result as r')->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)->where('r.Flag_Aktif', 'Y')
            ->where(fn ($w) => $w->where('r.Status_Hasil', 'TERBIT')->orWhereNotNull('r.Published_Leader_At'))
            ->count();
    }

    /**
     * Hasil berkalibrasi Forum yang akan DILEPAS kalibrasinya oleh hitung ulang Balancing: yang
     * belum dibaca siapa pun. Hasil yang sudah terbit atau dirilis ke leader tetap memegang
     * keputusan forumnya — angka yang sudah dibaca orang tidak digeser diam-diam. Satu sumber
     * untuk hitung ulang dan simulasinya, supaya keduanya tidak menyimpang.
     */
    public function kalibrasiForumBelumDibaca(int $cycleId): Collection
    {
        return DB::table('N_PA_Result as r')->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)->where('r.Flag_Aktif', 'Y')->where('r.Flag_Dikalibrasi', 'Y')
            ->where('r.Status_Hasil', '<>', 'TERBIT')->whereNull('r.Published_Leader_At')
            ->get(['r.Id_PA_Result', 'r.PA_Participant_Id', 'r.Kode_Grade_Final', 'r.PA_Hav_Quadrant_Id']);
    }

    /** Syarat publish (rilis leader maupun terbit): tidak ada sesi terbuka, semua FINAL sudah dihitung ulang. */
    public function pastikanTuntas(int $cycleId): void
    {
        $terbuka = $this->sesiTerbuka($cycleId);

        if ($terbuka->isNotEmpty()) {
            throw new PaBalancingException(
                'Masih ada sesi Balancing yang terbuka: '.$terbuka->pluck('Nama_Sesi')->implode(', ')
                .'. Finalkan atau batalkan sesinya lebih dulu.',
                PaBalancingException::SESI_MASIH_TERBUKA,
            );
        }

        $belum = $this->sesiFinalBelumDihitung($cycleId);

        if ($belum->isNotEmpty()) {
            throw new PaBalancingException(
                'Sesi Balancing '.$belum->pluck('Nama_Sesi')->implode(', ')
                .' sudah FINAL tetapi belum dihitung ulang. Jalankan hitung ulang Balancing lebih dulu.',
                PaBalancingException::BELUM_DIHITUNG_ULANG,
            );
        }
    }
}
