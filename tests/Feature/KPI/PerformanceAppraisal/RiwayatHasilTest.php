<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use App\Services\KPI\PerformanceAppraisal\PaResultHistoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanSiklusKurva;
use Tests\TestCase;

/** Riwayat angka hasil (DDL-2026-168): kapan dihitung, oleh siapa, dari berapa ke berapa. */
class RiwayatHasilTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanLembarPenilaian;
    use MenyiapkanSiklusKurva;

    private function riwayat(): PaResultHistoryService
    {
        return app(PaResultHistoryService::class);
    }

    private function resultId(int $pid): int
    {
        return (int) DB::table('N_PA_Result')->where('PA_Participant_Id', $pid)->value('Id_PA_Result');
    }

    public function test_potret_berurutan_menghasilkan_perubahan_sebelum_sesudah_dengan_nama_pelaku(): void
    {
        $level = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $pid = $this->siklusKurva([$level => [80.0]])['peserta'][$level][0];
        $rid = $this->resultId($pid);
        $user = DB::table('KPI_Users')->orderBy('Id_Users')->first(['Id_Users', 'Nama', 'Username']);

        $this->riwayat()->potret($rid, PaResultHistoryService::HITUNG, (int) $user->Id_Users, null, '2026-10-01 08:00:00');
        DB::table('N_PA_Result')->where('Id_PA_Result', $rid)
            ->update(['Final_Score' => 85.5, 'Calculation_Version' => 2, 'Kode_Grade_Sistem' => 'B']);
        $this->riwayat()->potret($rid, PaResultHistoryService::HITUNG_ULANG, (int) $user->Id_Users, null, '2026-10-02 09:00:00');

        $l = $this->riwayat()->lini($pid);
        $hitung = array_values(array_filter($l['peristiwa'], fn ($e) => str_starts_with($e['jenis'], 'HITUNG')));

        // Terbaru di atas; hanya versi yang punya pembanding yang membawa perubahan.
        $this->assertSame(['HITUNG_ULANG', 'HITUNG'], array_column($hitung, 'jenis'));
        $this->assertSame([2, 1], array_column($hitung, 'versi'));
        $this->assertSame([], $hitung[1]['perubahan']);
        $ubah = collect($hitung[0]['perubahan'])->keyBy('kolom');
        $this->assertEqualsWithDelta(80.0, $ubah['final']['sebelum'], 0.001);
        $this->assertEqualsWithDelta(85.5, $ubah['final']['sesudah'], 0.001);
        $this->assertSame([null, 'B'], [$ubah['grade_sistem']['sebelum'], $ubah['grade_sistem']['sesudah']]);
        $this->assertSame(trim((string) $user->Nama) ?: $user->Username, $hitung[0]['oleh']);
        $this->assertSame('2026-10-02 09:00:00', $hitung[0]['pada']);
    }

    public function test_kunci_dan_buka_kurva_memotret_setiap_anggota_grup(): void
    {
        $level = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $s = $this->siklusKurva([$level => [70.0, 80.0, 90.0]]);
        $rids = array_map(fn ($pid) => $this->resultId($pid), $s['peserta'][$level]);

        app(PaKurvaService::class)->kunci($s['cycle_id'], null);
        app(PaKurvaService::class)->buka($s['cycle_id'], 'Salah grup level', null);

        $baris = DB::table('N_PA_Result_History')->whereIn('PA_Result_Id', $rids)
            ->orderBy('Id_PA_Result_History')->get(['PA_Result_Id', 'Jenis_Peristiwa', 'Kode_Grade_Sistem', 'Keterangan']);

        $this->assertCount(6, $baris);
        $kunci = $baris->where('Jenis_Peristiwa', 'KURVA_DIKUNCI')->keyBy('PA_Result_Id');
        $this->assertSame(['C_PLUS', 'B', 'B_PLUS'], array_map(fn ($r) => $kunci[$r]->Kode_Grade_Sistem, $rids));
        $buka = $baris->where('Jenis_Peristiwa', 'KURVA_DIBUKA');
        $this->assertCount(3, $buka);
        $this->assertSame([null], $buka->pluck('Kode_Grade_Sistem')->unique()->values()->all());
        $this->assertSame('Salah grup level', $buka->first()->Keterangan);
    }

    public function test_versi_sebelum_riwayat_ada_dibangun_dari_baris_anak(): void
    {
        $level = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $pid = $this->siklusKurva([$level => [77.0]])['peserta'][$level][0];
        $rid = $this->resultId($pid);
        DB::table('N_PA_Result')->where('Id_PA_Result', $rid)->update(['Calculation_Version' => 2]);
        $kategori = (int) DB::table('N_PA_Rater_Category')->orderBy('Id_PA_Rater_Category')->value('Id_PA_Rater_Category');
        $kompetensi = (int) DB::table('N_PA_Competency')->orderBy('Id_PA_Competency')->value('Id_PA_Competency');

        foreach ([[1, 'T', '2026-09-01 10:00:00', 60.0], [2, 'Y', '2026-09-02 10:00:00', 70.0]] as [$versi, $aktif, $pada, $nilai]) {
            DB::table('N_PA_Result_Rater_Category')->insert([
                'PA_Result_Id' => $rid, 'PA_Rater_Category_Id' => $kategori, 'Calculation_Version' => $versi,
                'Flag_Aktif' => $aktif, 'Created_At' => $pada,
            ]);
            DB::table('N_PA_Result_Competency')->insert([
                'PA_Result_Id' => $rid, 'PA_Competency_Id' => $kompetensi, 'PA_Rater_Category_Id' => null,
                'Nilai' => $nilai, 'Calculation_Version' => $versi, 'Flag_Aktif' => $aktif, 'Created_At' => $pada,
            ]);
        }

        $l = $this->riwayat()->lini($pid);
        $this->assertSame(2, $l['versi_kini']);
        [$v2, $v1] = $l['peristiwa'];

        // Versi yang berlaku angkanya masih di N_PA_Result; versi lama hanya punya nilai kompetensi.
        $this->assertSame(['HITUNG_LAMA', 2], [$v2['jenis'], $v2['versi']]);
        $this->assertEqualsWithDelta(77.0, $v2['angka']['final'], 0.001);
        $this->assertSame(['HITUNG_LAMA', 1], [$v1['jenis'], $v1['versi']]);
        $this->assertNull($v1['angka']);
        $this->assertStringContainsString('tidak tersimpan', $v1['keterangan']);
        $this->assertEqualsWithDelta(70.0, $v2['kompetensi'][0]['nilai'], 0.001);
        $this->assertEqualsWithDelta(60.0, $v2['kompetensi'][0]['sebelum'], 0.001);
    }

    public function test_catatan_kualitatif_hanya_kategori_yang_berubah_per_kali_simpan(): void
    {
        $level = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $pid = $this->siklusKurva([$level => [80.0]])['peserta'][$level][0];
        $rid = $this->resultId($pid);
        $user = (int) DB::table('KPI_Users')->orderBy('Id_Users')->value('Id_Users');
        $catatan = app(\App\Services\KPI\PerformanceAppraisal\PaResultNoteService::class);
        // Memindahkan tulisan simpan TERAKHIR (yang lebih baru dari $ke) ke waktu $ke, supaya tiap simpan berjarak.
        $mundur = function (string $ke) use ($rid) {
            DB::table('N_PA_Result_Note')->where('PA_Result_Id', $rid)->where('Created_At', '>', $ke)->update(['Created_At' => $ke]);
            DB::table('N_PA_Result_Note')->where('PA_Result_Id', $rid)->where('Updated_At', '>', $ke)->update(['Updated_At' => $ke]);
        };

        $catatan->simpanCatatanAdmin($pid, ['QL01' => ['<p>Teliti &amp; rapi</p>'], 'QL02' => ['Presentasi']], $user);
        $mundur('2026-10-01 08:00:00');
        $catatan->simpanCatatanAdmin($pid, ['QL01' => ['Teliti, rapi, dan cepat'], 'QL02' => ['Presentasi']], $user);
        $mundur('2026-10-02 08:00:00');
        // Simpan ulang tanpa perubahan apa pun: tidak boleh muncul sebagai peristiwa.
        $catatan->simpanCatatanAdmin($pid, ['QL01' => ['Teliti, rapi, dan cepat'], 'QL02' => ['Presentasi']], $user);

        $e = array_values(array_filter($this->riwayat()->lini($pid)['peristiwa'], fn ($x) => $x['jenis'] === 'CATATAN'));

        $this->assertCount(2, $e);
        // Terbaru di atas: hanya Strength yang berubah, teks kaya sudah jadi teks polos.
        $this->assertSame([['kolom' => 'catatan', 'kode' => 'QL01', 'nama' => 'Strength', 'sebelum' => 'Teliti & rapi', 'sesudah' => 'Teliti, rapi, dan cepat']], $e[0]['perubahan']);
        $this->assertSame(['QL01', 'QL02'], array_column($e[1]['perubahan'], 'kode'));
        $this->assertSame([null, null], array_column($e[1]['perubahan'], 'sebelum'));
        $this->assertSame($user, $e[0]['oleh_id']);
    }

    public function test_peserta_tanpa_hasil_mengembalikan_lini_kosong(): void
    {
        $this->assertSame(['result_id' => null, 'versi_kini' => null, 'peristiwa' => []], $this->riwayat()->lini(-1));
    }
}
