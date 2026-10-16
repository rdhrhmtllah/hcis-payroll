<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanSiklusKurva;
use Tests\TestCase;

/** Rincian perhitungan kurva satu grup: angka yang ditampilkan modal detail harus bisa dihitung tangan. */
class KurvaDetailGrupTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanLembarPenilaian;
    use MenyiapkanSiklusKurva;

    public function test_langkah_mu_sigma_dan_batas_grade_dapat_dihitung_tangan(): void
    {
        $level = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $s = $this->siklusKurva([$level => [70.0, 80.0, 90.0]]);
        $grup = (int) DB::table('N_PA_Cycle_Curve_Group')->where('PA_Cycle_Id', $s['cycle_id'])->value('Id_PA_Cycle_Curve_Group');

        $d = app(PaKurvaService::class)->detailGrup($s['cycle_id'], $grup);

        // Σx = 240, μ = 80, Σ(x−μ)² = 200, σ = √(200 ÷ 2) = 10.
        $this->assertSame(3, $d['langkah']['n']);
        $this->assertEqualsWithDelta(240.0, $d['langkah']['jumlah'], 0.0001);
        $this->assertEqualsWithDelta(80.0, $d['langkah']['rata'], 0.0001);
        $this->assertEqualsWithDelta(200.0, $d['langkah']['jumlah_kuadrat'], 0.0001);
        $this->assertEqualsWithDelta(100.0, $d['langkah']['varians'], 0.0001);
        $this->assertEqualsWithDelta(10.0, $d['langkah']['sd'], 0.0001);

        // Batas bawah = μ + z·σ: B_PLUS (z=1) = 90, B (z=0) = 80, C_PLUS (z=−1) = 70.
        $pita = collect($d['pita'])->keyBy('kode');
        $this->assertEqualsWithDelta(90.0, $pita['B_PLUS']['batas_mentah'], 0.0001);
        $this->assertEqualsWithDelta(80.0, $pita['B']['batas_mentah'], 0.0001);
        $this->assertEqualsWithDelta(70.0, $pita['C_PLUS']['batas_mentah'], 0.0001);
        $this->assertSame(3, array_sum(array_column($d['pita'], 'jumlah')));

        // Anggota terurut dari Final tertinggi, dengan selisih, z, dan grade dari pita yang sama.
        $this->assertSame([90.0, 80.0, 70.0], array_column($d['anggota'], 'final'));
        $this->assertSame([10.0, 0.0, -10.0], array_map('floatval', array_column($d['anggota'], 'selisih')));
        $this->assertSame([1.0, 0.0, -1.0], array_map('floatval', array_column($d['anggota'], 'z')));
        $this->assertSame(['B_PLUS', 'B', 'C_PLUS'], array_column($d['anggota'], 'grade'));
    }

    public function test_grup_dari_siklus_lain_ditolak(): void
    {
        $level = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $a = $this->siklusKurva([$level => [70.0, 80.0]]);
        $b = $this->siklusKurva([$level => [60.0, 65.0]]);
        $grupB = (int) DB::table('N_PA_Cycle_Curve_Group')->where('PA_Cycle_Id', $b['cycle_id'])->value('Id_PA_Cycle_Curve_Group');

        $this->expectException(\RuntimeException::class);
        app(PaKurvaService::class)->detailGrup($a['cycle_id'], $grupB);
    }

    /** Dialog hasil admin: posisi satu orang di grup kurvanya, dihitung dari detailGrup yang sama. */
    public function test_posisi_peserta_peringkat_z_dan_jarak_ke_grade_berikutnya(): void
    {
        $level = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $s = $this->siklusKurva([$level => [70.0, 80.0, 90.0]]);
        $pid80 = $s['peserta'][$level][1];
        $pid90 = $s['peserta'][$level][2];

        $p = app(PaKurvaService::class)->posisiPeserta($pid80);

        // 80 = μ: peringkat 2 dari 3, z 0, grade B, dan B_PLUS mulai 90 → kurang 10 poin.
        $this->assertSame(3, $p['n']);
        $this->assertSame(2, $p['peringkat']);
        $this->assertSame(50, $p['persentil']);
        $this->assertEqualsWithDelta(0.0, $p['z'], 0.0001);
        $this->assertSame('B', $p['grade']);
        $this->assertSame('B_PLUS', $p['grade_berikut']['kode']);
        $this->assertEqualsWithDelta(10.0, $p['grade_berikut']['jarak'], 0.0001);

        // Orang di puncak: tidak ada grade berikutnya yang batasnya di atas nilainya... kecuali
        // grade yang lebih tinggi lagi (A, z=2 → 100) masih ada.
        $puncak = app(PaKurvaService::class)->posisiPeserta($pid90);
        $this->assertSame(1, $puncak['peringkat']);
        $this->assertSame(100, $puncak['persentil']);
    }

    /**
     * Grade BERLAKU (kalibrasi forum) dikirim berdampingan dengan grade menurut pita (koreksi
     * 8 Okt 2026). Dulu yang dikirim hanya grade pita: #1206270 di siklus 8781 berfinal C_PLUS
     * hasil kalibrasi, tetapi dialog kurvanya berkata C.
     */
    public function test_posisi_dan_detail_grup_membawa_grade_final_terkalibrasi(): void
    {
        $level = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $s = $this->siklusKurva([$level => [70.0, 80.0, 90.0]]);
        $kurva = app(PaKurvaService::class);
        $kurva->kunci($s['cycle_id'], null);
        $pid70 = $s['peserta'][$level][0];
        DB::table('N_PA_Result')->where('PA_Participant_Id', $pid70)->update(['Flag_Dikalibrasi' => 'Y', 'Kode_Grade_Final' => 'B']);

        $p = $kurva->posisiPeserta($pid70);

        $this->assertSame('C_PLUS', $p['grade_sistem']);
        $this->assertSame('B', $p['grade_final']);
        $this->assertSame('B', $p['grade'], 'Grade yang ditampilkan adalah grade yang berlaku.');

        $grup = (int) DB::table('N_PA_Cycle_Curve_Group')->where('PA_Cycle_Id', $s['cycle_id'])->value('Id_PA_Cycle_Curve_Group');
        $anggota = collect($kurva->detailGrup($s['cycle_id'], $grup)['anggota'])->keyBy('participant_id');

        $this->assertSame('C_PLUS', $anggota[$pid70]['grade_sistem']);
        $this->assertSame('B', $anggota[$pid70]['grade_final']);
        // Yang tidak dikalibrasi: kedua grade sama.
        $this->assertSame($anggota[$s['peserta'][$level][1]]['grade_sistem'], $anggota[$s['peserta'][$level][1]]['grade_final']);
    }

    public function test_posisi_peserta_null_bila_bukan_metode_kurva(): void
    {
        $level = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $s = $this->siklusKurva([$level => [70.0, 80.0]]);
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $s['cycle_id'])->update(['Metode_Grade' => 'PITA']);

        $this->assertNull(app(PaKurvaService::class)->posisiPeserta($s['peserta'][$level][0]));
    }

    /** Tren di dialog hasil: hasil orang yang sama di siklus aktif sebelumnya. */
    public function test_detail_hasil_membawa_tren_siklus_sebelumnya(): void
    {
        $level = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $lama = $this->siklusKurva([$level => [70.0]]);
        $baru = $this->siklusKurva([$level => [80.0]]);
        $pidLama = $lama['peserta'][$level][0];
        $pidBaru = $baru['peserta'][$level][0];
        DB::table('N_PA_Result')->where('PA_Participant_Id', $pidLama)->update(['Kode_Grade_Final' => 'C', 'Nilai_KPI' => 72.0]);

        // Satu orang yang sama di dua siklus (fixture memilih karyawan berurutan; disamakan di sini).
        $orang = DB::table('N_PA_Participant')->where('Id_PA_Participant', $pidBaru)->first(['Kode_Perusahaan', 'Kode_Karyawan']);
        DB::table('N_PA_Participant')->where('Id_PA_Participant', $pidLama)
            ->update(['Kode_Perusahaan' => $orang->Kode_Perusahaan, 'Kode_Karyawan' => $orang->Kode_Karyawan]);
        DB::table('N_PA_Cycle')->whereIn('Id_PA_Cycle', [$lama['cycle_id'], $baru['cycle_id']])->update(['Tahun' => 2099]);

        $d = app(\App\Services\KPI\PerformanceAppraisal\PaParticipantReadService::class)->detailHasil($pidBaru);

        $this->assertSame($lama['cycle_id'], $d['tren']['cycle']['cycle_id']);
        $this->assertEqualsWithDelta(70.0, $d['tren']['final'], 0.0001);
        $this->assertSame('C', $d['tren']['grade']);
        $this->assertEqualsWithDelta(72.0, $d['tren']['kpi'], 0.0001);

        // Siklus terhapus tidak dijadikan pembanding.
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $lama['cycle_id'])->update(['Flag_Aktif' => 'T']);
        $this->assertNull(app(\App\Services\KPI\PerformanceAppraisal\PaParticipantReadService::class)->detailHasil($pidBaru)['tren']);
    }
}
