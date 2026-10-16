<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Balancing;

use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingException;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingSimulasiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanBalancing;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

class BalancingSimulasiTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanBalancing;
    use MenyiapkanLembarPenilaian;

    public function test_simulasi_pita_memakai_isian_layar_dan_tidak_menulis(): void
    {
        $p = $this->pesertaDihitung(['kpi' => 90.0]);
        $this->palsukanAturan($p['kategori_id']);
        [$k1, $k2] = $p['kompetensi'];
        $sesi = $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$k1 => 70.0, $k2 => 85.0]], 'DRAFT');
        $jumlahHasil = DB::table('N_PA_Result')->where('PA_Participant_Id', $p['participant_id'])->get();

        $r = app(PaBalancingSimulasiService::class)->simulasikan($sesi, [[
            'participant_id' => $p['participant_id'], 'kompetensi' => [['competency_id' => $k1, 'nilai_sesudah' => 90.0]],
        ]]);

        $orang = $r['peserta'][0];
        $this->assertSame('PITA', $r['metode']);
        $this->assertSame(83.75, $orang['sebelum']['final']);
        $this->assertSame(87.5, $orang['sesudah']['nilai_kompetensi']);   // 77,5 + 0,5 × 20
        $this->assertSame(88.75, $orang['sesudah']['final']);             // 90×0,5 + 87,5×0,5
        $this->assertSame('BALANCING', $orang['sebab']);
        $this->assertEquals($jumlahHasil, DB::table('N_PA_Result')->where('PA_Participant_Id', $p['participant_id'])->get());
    }

    /**
     * Simulasi PITA memperlihatkan kalibrasi forum yang AKAN dilepas hitung ulang — dan hanya
     * itu: hasil yang sudah terbit tetap memegang grade forumnya (koreksi 8 Okt 2026).
     */
    public function test_simulasi_pita_memperlihatkan_pelepasan_kalibrasi_yang_belum_dibaca(): void
    {
        $p = $this->pesertaDihitung(['kpi' => 90.0]);
        $belumDibaca = $this->pesertaDihitung(['cycle_id' => $p['cycle_id'], 'kpi' => 90.0]);
        $terbit = $this->pesertaDihitung(['cycle_id' => $p['cycle_id'], 'kpi' => 90.0]);
        $this->palsukanAturan($p['kategori_id']);

        foreach ([['B', 0, 90, 2], ['A', 90, 999.99, 1]] as [$kode, $dari, $sampai, $urut]) {
            DB::table('N_PA_Cycle_Score_Range_Snapshot')->insert([
                'PA_Cycle_Id' => $p['cycle_id'], 'Score_Type' => 'FINAL', 'Score_Usage' => 'GRADE',
                'Kode_Range' => $kode, 'Nama_Range' => $kode, 'Nilai_Dari' => $dari, 'Nilai_Sampai' => $sampai,
                'Urutan_Tampil' => $urut, 'Flag_Aktif' => 'Y',
            ]);
        }

        DB::table('N_PA_Result')->whereIn('Id_PA_Result', [$belumDibaca['result_id'], $terbit['result_id']])
            ->update(['Flag_Dikalibrasi' => 'Y', 'Kode_Grade_Final' => 'A']);
        DB::table('N_PA_Result')->where('Id_PA_Result', $terbit['result_id'])->update(['Status_Hasil' => 'TERBIT']);
        $sesi = $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$p['kompetensi'][0] => 70.0]], 'DRAFT');

        $semua = collect(app(PaBalancingSimulasiService::class)->simulasikan($sesi, [])['semua'])->keyBy('participant_id');

        $this->assertSame('A', $semua[$belumDibaca['participant_id']]['grade_sebelum']);
        $this->assertSame('B', $semua[$belumDibaca['participant_id']]['grade_sesudah'], 'Final 83,75 jatuh di pita B begitu kalibrasinya dilepas.');
        $this->assertSame('A', $semua[$terbit['participant_id']]['grade_sesudah'], 'Hasil yang sudah terbit tetap memegang grade forumnya.');
    }

    public function test_isian_di_luar_rentang_ditolak(): void
    {
        $p = $this->pesertaDihitung();
        $this->palsukanAturan($p['kategori_id']);
        $sesi = $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$p['kompetensi'][0] => 70.0]], 'DRAFT');

        $this->expectException(PaBalancingException::class);
        app(PaBalancingSimulasiService::class)->simulasikan($sesi, [[
            'participant_id' => $p['participant_id'], 'kompetensi' => [['competency_id' => $p['kompetensi'][0], 'nilai_sesudah' => 120]],
        ]]);
    }
}
