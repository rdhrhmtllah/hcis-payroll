<?php

namespace App\Services\KPI\PerformanceAppraisal\Balancing;

use Illuminate\Support\Facades\DB;

/** Final, grade, dan kotak HAV seluruh hasil aktif satu siklus — untuk sebelum/sesudah. */
class PaBalancingPotretHasil
{
    /** @return array<int, object> participant_id => baris */
    public function siklus(int $cycleId): array
    {
        return DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->leftJoin('N_PA_Hav_Quadrant as q', 'q.Id_PA_Hav_Quadrant', '=', 'r.PA_Hav_Quadrant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)->where('p.Status_Peserta', 'DIIKUTKAN')->where('p.Flag_Aktif', 'Y')
            ->where('r.Flag_Aktif', 'Y')
            ->get([
                'p.Id_PA_Participant', 'p.Nama_Karyawan_Saat_Cycle as Nama', 'r.Status_Hasil', 'r.Nilai_KPI',
                'r.Nilai_Kompetensi', 'r.Final_Score', 'r.Kode_Grade_Final', 'r.PA_Hav_Quadrant_Id', 'q.Kode_Kuadran',
            ])
            ->mapWithKeys(fn ($r) => [(int) $r->Id_PA_Participant => (object) [
                'Nama' => $r->Nama,
                'Status_Hasil' => $r->Status_Hasil,
                'Nilai_KPI' => $r->Nilai_KPI === null ? null : (float) $r->Nilai_KPI,
                'Nilai_Kompetensi' => $r->Nilai_Kompetensi === null ? null : (float) $r->Nilai_Kompetensi,
                'Final_Score' => $r->Final_Score === null ? null : (float) $r->Final_Score,
                'Kode_Grade_Final' => $r->Kode_Grade_Final,
                'PA_Hav_Quadrant_Id' => $r->PA_Hav_Quadrant_Id === null ? null : (int) $r->PA_Hav_Quadrant_Id,
                'Kode_Kuadran' => $r->Kode_Kuadran,
            ]])
            ->all();
    }
}
