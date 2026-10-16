<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Support;

use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\PerformanceAppraisal\PaParticipantRuleResolver;
use Illuminate\Support\Facades\DB;

/**
 * Peserta yang sudah DIHITUNG, lengkap dengan baris agregat per kompetensi, untuk uji Balancing.
 * Memakai `lembarTerisi()` dari MenyiapkanLembarPenilaian: satu penilai ATASAN, dua kompetensi
 * berskor (bawaan 70 dan 85, tanpa bobot butir → porsi 0,5 dan 0,5).
 */
trait MenyiapkanBalancing
{
    /**
     * @param  array{cycle_id?:int, skor?:list<float>, kpi?:float, final?:float, grade?:string, status_hasil?:string}  $opsi
     * @return array{cycle_id:int, participant_id:int, result_id:int, kompetensi:list<int>, kategori_id:int, assignment_id:int}
     */
    protected function pesertaDihitung(array $opsi = []): array
    {
        $skor = $opsi['skor'] ?? [70.0, 85.0];
        $l = $this->lembarTerisi([
            'status_siklus' => 'CLOSED',
            'status_penugasan' => 'SELESAI',
            'skor' => $skor,
            ...(isset($opsi['cycle_id']) ? ['cycle_id' => $opsi['cycle_id']] : []),
            // Jawaban esai pada kompetensi kualitatif pertama (QL01) — untuk tahap Kesimpulan Kualitatif.
            ...(isset($opsi['naratif']) ? ['naratif' => $opsi['naratif']] : []),
        ]);

        // Siklus nyata selalu punya periode dinilai; konteks perhitungan menolak siklus tanpanya.
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $l['cycle_id'])->whereNull('Periode_Dinilai_Dari')->update([
            'Periode_Dinilai_Dari' => date('Y').'-01-01', 'Periode_Dinilai_Sampai' => date('Y').'-12-31',
        ]);

        $komp = round(array_sum($skor) / count($skor), 2);
        $kpi = $opsi['kpi'] ?? 90.0;

        $resultId = (int) DB::table('N_PA_Result')->insertGetId([
            'PA_Participant_Id' => $l['participant_id'],
            'Status_Hasil' => $opsi['status_hasil'] ?? 'DIHITUNG',
            'Nilai_KPI' => $kpi,
            'Nilai_Kompetensi' => $komp,
            'Final_Score' => $opsi['final'] ?? round($kpi * 0.5 + $komp * 0.5, 2),
            'Kode_Grade_Sistem' => $opsi['grade'] ?? 'B',
            'Kode_Grade_Final' => $opsi['grade'] ?? 'B',
            'Calculation_Version' => 1,
            'Flag_Aktif' => 'Y',
        ], 'Id_PA_Result');

        foreach ($l['kompetensi'] as $i => $cid) {
            DB::table('N_PA_Result_Competency')->insert([
                'PA_Result_Id' => $resultId, 'PA_Competency_Id' => $cid, 'PA_Rater_Category_Id' => null,
                'Nilai' => $skor[$i], 'Jumlah_Penilai' => 1, 'Calculation_Version' => 1, 'Flag_Aktif' => 'Y',
            ]);
        }

        return [
            'cycle_id' => $l['cycle_id'], 'participant_id' => $l['participant_id'], 'result_id' => $resultId,
            'kompetensi' => $l['kompetensi'], 'kategori_id' => $l['kategori_id'], 'assignment_id' => $l['assignment_id'],
        ];
    }

    /** Aturan penilai tiruan: satu kategori ATASAN 100%, tanpa jenis wajib, pilar KPI/Kompetensi. */
    protected function palsukanAturan(int $kategoriId, float $bobotKpi = 50.0): void
    {
        $this->mock(PaParticipantRuleResolver::class, function ($m) use ($kategoriId, $bobotKpi) {
            $m->shouldReceive('raterRules')->andReturn(['ATASAN' => [
                'kategori_id' => $kategoriId, 'kode_kategori' => 'ATASAN', 'nama_kategori' => 'Atasan',
                'bobot_persen' => 100.0, 'min_rater' => 1, 'min_rater_selesai' => 1, 'maks_rater' => null,
            ]]);
            $m->shouldReceive('competencyRequirements')->andReturn([]);
            $m->shouldReceive('pillarWeight')->andReturn([
                'bobot_kpi' => $bobotKpi, 'bobot_kompetensi' => 100 - $bobotKpi, 'sumber' => 'SALINAN', 'alasan_perubahan' => null,
            ]);
            $m->shouldReceive('muatOverrideSiklus')->andReturnNull();
        });
    }

    /**
     * Sesi yang ditanam langsung ke tabel — untuk uji pembaca dan hitung ulang, bukan uji penulis.
     *
     * @param  array<int, array<int, float>>  $afterPerPeserta  participant_id => [competency_id => After]
     * @param  array<int, array<int, string>>  $kualitatif  participant_id => [competency_id => isi sesudah]
     */
    protected function sesiLangsung(int $cycleId, array $afterPerPeserta, string $status = 'FINAL', array $kualitatif = []): int
    {
        $sesiId = (int) DB::table('N_PA_Balancing_Sesi')->insertGetId([
            'PA_Cycle_Id' => $cycleId, 'Nama_Sesi' => 'Uji '.substr(bin2hex(random_bytes(3)), 0, 6),
            'PIC_User_Id' => (int) DB::table('KPI_Users')->orderBy('Id_Users')->value('Id_Users'),
            'Status_Sesi' => $status, 'Final_At' => $status === 'FINAL' ? now() : null,
        ], 'Id_PA_Balancing_Sesi');

        foreach ($afterPerPeserta as $pid => $after) {
            $hasil = DB::table('N_PA_Result')->where('PA_Participant_Id', $pid)->where('Flag_Aktif', 'Y')->first();
            $bpId = (int) DB::table('N_PA_Balancing_Peserta')->insertGetId([
                'PA_Balancing_Sesi_Id' => $sesiId, 'PA_Participant_Id' => $pid, 'PA_Result_Id' => $hasil->Id_PA_Result,
                'Calculation_Version_Sebelum' => $hasil->Calculation_Version, 'Final_Score_Sebelum' => $hasil->Final_Score,
                'Nilai_Kompetensi_Sebelum' => $hasil->Nilai_Kompetensi, 'Nilai_KPI_Sebelum' => $hasil->Nilai_KPI,
                'Kode_Grade_Sebelum' => $hasil->Kode_Grade_Final, 'Status_Tinjau' => 'DIUBAH',
            ], 'Id_PA_Balancing_Peserta');

            $sebelum = DB::table('N_PA_Result_Competency')->where('PA_Result_Id', $hasil->Id_PA_Result)
                ->where('Flag_Aktif', 'Y')->whereNull('PA_Rater_Category_Id')->pluck('Nilai', 'PA_Competency_Id');

            foreach ($after as $cid => $nilai) {
                DB::table('N_PA_Balancing_Kompetensi')->insert([
                    'PA_Balancing_Peserta_Id' => $bpId, 'PA_Competency_Id' => $cid, 'Bobot' => 0.5,
                    'Nilai_Sebelum' => (float) ($sebelum[$cid] ?? $nilai), 'Nilai_Sesudah' => $nilai, 'Alasan' => 'Uji',
                ]);
            }

            foreach ($kualitatif[$pid] ?? [] as $cid => $isi) {
                DB::table('N_PA_Balancing_Kualitatif')->insert([
                    'PA_Balancing_Peserta_Id' => $bpId, 'PA_Competency_Id' => $cid, 'Isi_Sebelum' => null, 'Isi_Sesudah' => $isi,
                ]);
            }
        }

        return $sesiId;
    }

    protected function penggunaHasil(): User
    {
        $userId = DB::table('HRIS_Page_Access')->where('Jenis_page', 'PaHasilPage')->where('Flag_Aktif', 'Y')
            ->whereNotNull('UserID_Web')->value('UserID_Web');
        $this->assertNotNull($userId, 'Tidak ada user berakses PaHasilPage.');

        return User::find($userId);
    }
}
