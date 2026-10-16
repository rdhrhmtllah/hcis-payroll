<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Balancing;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanBalancing;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

class BalancingApiTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanBalancing;
    use MenyiapkanLembarPenilaian;

    private const API = '/api/v1/performance-appraisal';

    public function test_alur_buat_simpan_simulasi_final_lewat_http(): void
    {
        $p = $this->pesertaDihitung();
        $this->palsukanAturan($p['kategori_id']);
        $this->actingAs($this->penggunaHasil());
        [$k1] = $p['kompetensi'];

        $kandidat = $this->getJson(self::API."/cycles/{$p['cycle_id']}/balancing/kandidat");
        $kandidat->assertOk();
        $this->assertContains($p['participant_id'], array_column($kandidat->json('result'), 'participant_id'));

        $buat = $this->postJson(self::API."/cycles/{$p['cycle_id']}/balancing/sesi", [
            'nama' => 'Sesi HTTP', 'pic_user_id' => (int) DB::table('KPI_Users')->orderBy('Id_Users')->value('Id_Users'), 'forum' => $this->timPenilai(), 'peserta_ids' => [$p['participant_id']],
        ]);
        $buat->assertOk();
        $id = $buat->json('result.id');

        $simpan = $this->putJson(self::API."/balancing/sesi/{$id}", [
            'versi_data' => $buat->json('result.versi_data'),
            'peserta' => [['participant_id' => $p['participant_id'], 'alasan' => 'Konsisten', 'kompetensi' => [['competency_id' => $k1, 'nilai_sesudah' => 80]]]],
        ]);
        $simpan->assertOk();
        $this->assertSame('DIUBAH', $simpan->json('result.peserta.0.status_tinjau'));

        $sim = $this->postJson(self::API."/balancing/sesi/{$id}/simulasi", ['peserta' => []]);
        $sim->assertOk();
        $this->assertSame(82.5, $sim->json('result.peserta.0.sesudah.nilai_kompetensi'));

        $final = $this->postJson(self::API."/balancing/sesi/{$id}/final", ['versi_data' => $simpan->json('result.versi_data')]);
        $final->assertOk();
        $this->assertSame('FINAL', $final->json('result.status'));

        $daftar = $this->getJson(self::API."/cycles/{$p['cycle_id']}/balancing/sesi");
        $this->assertSame(1, $daftar->json('result.menunggu_hitung_ulang'));

        $riwayat = $this->getJson(self::API."/participants/{$p['participant_id']}/balancing");
        $riwayat->assertOk();
        $this->assertSame('Sesi HTTP', $riwayat->json('result.sesi.0.nama'));
    }

    public function test_galat_membawa_error_code(): void
    {
        $p = $this->pesertaDihitung();
        $this->actingAs($this->penggunaHasil());
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $p['cycle_id'])->update(['Status_Cycle' => 'OPEN']);

        $r = $this->postJson(self::API."/cycles/{$p['cycle_id']}/balancing/sesi", [
            'nama' => 'X', 'pic_user_id' => (int) DB::table('KPI_Users')->orderBy('Id_Users')->value('Id_Users'), 'forum' => $this->timPenilai(), 'peserta_ids' => [$p['participant_id']],
        ]);

        $r->assertStatus(422);
        $this->assertSame('PA_BAL_SIKLUS_BELUM_DITUTUP', $r->json('error_code'));
        $this->assertNotEmpty($r->json('request_id'));
    }

    public function test_tanpa_hak_hasil_ditolak(): void
    {
        $this->actingAs($this->penilaiUji());
        $this->getJson(self::API.'/cycles/1/balancing/sesi')->assertStatus(403);
    }

    /** Tim penilai wajib minimal satu karyawan aktif; perannya diisi server dari jabatan. */
    private function timPenilai(): array
    {
        $k = DB::table('Karyawan')->where('Aktif', 'Y')->whereNull('Tanggal_Resign')->orderBy('Kode_Karyawan')->first(['Kode_Perusahaan', 'Kode_Karyawan']);

        return [['kode_perusahaan' => $k->Kode_Perusahaan, 'kode_karyawan' => $k->Kode_Karyawan]];
    }
}
