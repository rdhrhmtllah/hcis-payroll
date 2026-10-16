<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Support;

use Illuminate\Support\Facades\DB;

/**
 * Siklus bermetode KURVA tiruan: batas z G8 di salinan siklus, satu grup per level, hasil
 * DIHITUNG ditanam langsung. Memakai `siklusLembar()` dari MenyiapkanLembarPenilaian.
 */
trait MenyiapkanSiklusKurva
{
    /**
     * @param  array<int, list<float>>  $nilaiPerLevel  ID_Level => nilai akhir
     * @return array{cycle_id:int, peserta: array<int, list<int>>}
     */
    protected function siklusKurva(array $nilaiPerLevel, string $status = 'CLOSED'): array
    {
        $cycleId = $this->siklusLembar($status);
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->update(['Metode_Grade' => 'KURVA']);

        foreach ([['A', 2.0, 1], ['B_PLUS', 1.0, 2], ['B', 0.0, 3], ['C_PLUS', -1.0, 4], ['C', -2.0, 5], ['D', null, 6]] as [$k, $z, $u]) {
            DB::table('N_PA_Cycle_Score_Range_Snapshot')->insert([
                'PA_Cycle_Id' => $cycleId, 'Score_Type' => 'FINAL', 'Score_Usage' => 'GRADE',
                'Kode_Range' => $k, 'Nama_Range' => $k, 'Nilai_Dari' => 0, 'Nilai_Sampai' => 999.99,
                'Batas_Z' => $z, 'Urutan_Tampil' => $u, 'Flag_Aktif' => 'Y',
            ]);
        }

        $karyawan = DB::table('Karyawan')->where('Aktif', 'Y')->whereNotNull('ID_Level_Jabatan')->orderBy('Kode_Karyawan')
            ->limit(array_sum(array_map('count', $nilaiPerLevel)))->get(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan']);
        $i = 0;
        $peserta = [];

        foreach ($nilaiPerLevel as $level => $daftar) {
            $grup = (int) DB::table('N_PA_Cycle_Curve_Group')->insertGetId(
                ['PA_Cycle_Id' => $cycleId, 'Kode_Grup' => 'G'.$level, 'Nama_Grup' => 'G'.$level], 'Id_PA_Cycle_Curve_Group');
            DB::table('N_PA_Cycle_Curve_Group_Level')->insert(['PA_Cycle_Curve_Group_Id' => $grup, 'PA_Cycle_Id' => $cycleId, 'ID_Level' => $level]);

            foreach ($daftar as $nilai) {
                $k = $karyawan[$i++];
                $pid = (int) DB::table('N_PA_Participant')->insertGetId([
                    'PA_Cycle_Id' => $cycleId, 'Kode_Perusahaan' => $k->Kode_Perusahaan, 'Kode_Karyawan' => $k->Kode_Karyawan,
                    'Nama_Karyawan_Saat_Cycle' => $k->Nama, 'ID_Level_Jabatan_Saat_Cycle' => $k->ID_Level_Jabatan,
                    'ID_Level_Saat_Cycle' => $level, 'Status_Peserta' => 'DIIKUTKAN',
                ], 'Id_PA_Participant');
                DB::table('N_PA_Result')->insert([
                    'PA_Participant_Id' => $pid, 'Status_Hasil' => 'DIHITUNG', 'Final_Score' => $nilai,
                    'Calculation_Version' => 1, 'Flag_Aktif' => 'Y',
                ]);
                $peserta[$level][] = $pid;
            }
        }

        return ['cycle_id' => $cycleId, 'peserta' => $peserta];
    }
}
