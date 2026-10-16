<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaSimulasiPengisianService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

/**
 * Lembar simulasi (pengganti HC Learn di non-production) harus memakai KODE SOAL yang sama
 * dengan HC Learn — `Kode_Soal_Sumber`, kunci pemeta — bukan `Id_Jenis_Soal` (id bagian).
 */
class SimulasiPengisianTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanLembarPenilaian;

    /** @return array{0:string, 1:int} [token, user id] */
    private function lembarSimulasi(string $status): array
    {
        $user = $this->penilaiUji();
        $l = $this->lembarTerisi(['penilai' => $user, 'status_penugasan' => $status]);

        $paketId = (int) DB::table('N_PA_Cycle_Question_Package')->insertGetId([
            'PA_Cycle_Id' => $l['cycle_id'],
            'ID_Level' => (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level'),
            'Id_Master_Ujian' => 'PKG-SIM-'.bin2hex(random_bytes(2)),
            'Nama_Ujian_Sumber' => 'Paket Simulasi',
            'Sumber_Kontrak' => 'MANUAL',
            'Flag_Aktif' => 'Y',
        ], 'Id_PA_Cycle_Question_Package');

        DB::table('N_PA_Cycle_Question_Package_Item')->insert([
            'PA_Cycle_Question_Package_Id' => $paketId,
            'Id_Jenis_Indikator' => 'ALT-SIM',
            'Id_Jenis_Soal' => '900001',
            'Kode_Soal_Sumber' => 'KSIM1',
            'Nama_Bagian_Sumber' => 'Soal Simulasi',
            'PA_Competency_Id' => $l['kompetensi'][0],
            'Skor_Maksimal' => 5,
            'Urutan_Tampil' => 1,
        ]);

        $token = 'SIM-PA-'.bin2hex(random_bytes(6));
        DB::table('N_PA_Assignment_Integration')->where('PA_Assignment_Id', $l['assignment_id'])
            ->update(['Id_Ujian_Token' => $token, 'PA_Cycle_Question_Package_Id' => $paketId]);

        return [$token, (int) $user->Id_Users];
    }

    public function test_lembar_memakai_kode_soal_sumber_bukan_id_bagian(): void
    {
        [$token, $userId] = $this->lembarSimulasi('BERJALAN');

        $lembar = app(PaSimulasiPengisianService::class)->lembar($token, $userId);

        $this->assertSame(['KSIM1'], array_column($lembar['butir'], 'kode_soal'),
            'Kode yang dikirim layar harus kunci pemeta; id bagian membuat kiriman tak terpetakan.');
        $this->assertFalse($lembar['sudah_selesai']);
    }

    public function test_lembar_menunggu_verifikasi_dianggap_sudah_dikerjakan(): void
    {
        [$token, $userId] = $this->lembarSimulasi('MENUNGGU_VERIFIKASI');

        $this->assertTrue(app(PaSimulasiPengisianService::class)->lembar($token, $userId)['sudah_selesai']);
    }
}
