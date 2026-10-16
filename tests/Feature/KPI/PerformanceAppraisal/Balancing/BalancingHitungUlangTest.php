<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Balancing;

use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingException;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingHitungUlangService;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingKalkulator;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingNilaiBerlaku;
use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanBalancing;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanSiklusKurva;
use Tests\TestCase;

/**
 * Orkestrasi hitung ulang. `hitung()` dipalsukan: matematikanya dijaga Task 4, sedangkan yang
 * diuji di sini urutan buka → hitung → kunci, jejak, dan transaksinya.
 */
class BalancingHitungUlangTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanBalancing;
    use MenyiapkanLembarPenilaian;
    use MenyiapkanSiklusKurva;

    private array $kompetensi = [];

    /** User nyata: riwayat kalibrasi dan kolom *_By bisa ber-FK ke KPI_Users. */
    private function userUji(): int
    {
        return (int) DB::table('KPI_Users')->orderBy('Id_Users')->value('Id_Users');
    }

    /** Siklus KURVA 7 orang, tiap orang punya dua kompetensi radar 70/85 dan KPI 90; kurva dikunci. */
    private function siklusSiap(): array
    {
        $level = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $s = $this->siklusKurva([$level => [70, 72, 74, 76, 78, 80, 82]]);
        $this->kompetensi = DB::table('N_PA_Competency')->where('Flag_Aktif', 'Y')->where('Flag_Berskor', 'Y')
            ->orderBy('Id_PA_Competency')->limit(2)->pluck('Id_PA_Competency')->map(fn ($x) => (int) $x)->all();

        foreach ($s['peserta'][$level] as $pid) {
            $r = DB::table('N_PA_Result')->where('PA_Participant_Id', $pid)->first();
            DB::table('N_PA_Result')->where('Id_PA_Result', $r->Id_PA_Result)->update(['Nilai_KPI' => 90, 'Nilai_Kompetensi' => 77.5]);

            foreach ([70.0, 85.0] as $i => $nilai) {
                DB::table('N_PA_Result_Competency')->insert([
                    'PA_Result_Id' => $r->Id_PA_Result, 'PA_Competency_Id' => $this->kompetensi[$i], 'PA_Rater_Category_Id' => null,
                    'Nilai' => $nilai, 'Calculation_Version' => 1, 'Flag_Aktif' => 'Y',
                ]);
            }
        }

        app(PaKurvaService::class)->kunci($s['cycle_id'], 7);

        return [$s['cycle_id'], $s['peserta'][$level]];
    }

    /** hitung() tiruan: Nilai Kompetensi = rata-rata nilai berlaku (bobot 0,5/0,5), Final 50/50. */
    /** @var list<int> peserta yang dihitung ulang oleh hitung() tiruan */
    private array $dihitung = [];

    private function palsukanHitung(?int $pidTertahan = null): void
    {
        $this->partialMock(PaResultCalculationService::class, function ($m) use ($pidTertahan) {
            $m->shouldReceive('hitung')->andReturnUsing(function (int $pid) use ($pidTertahan) {
                $this->dihitung[] = $pid;

                if ($pid === $pidTertahan) {
                    return ['status' => 'TERTAHAN', 'alasan_tertahan' => 'KPI belum disetujui'];
                }

                $berlaku = app(PaBalancingNilaiBerlaku::class)->kompetensiBerlaku($pid) ?: [70.0, 85.0];
                $komp = round(array_sum($berlaku) / count($berlaku), 2);
                $final = PaBalancingKalkulator::nilaiAkhir(90.0, $komp, 50.0, 50.0);
                DB::table('N_PA_Result')->where('PA_Participant_Id', $pid)->where('Flag_Aktif', 'Y')->update([
                    'Nilai_Kompetensi' => $komp, 'Final_Score' => $final, 'Status_Hasil' => 'DIHITUNG',
                    'Kode_Grade_Sistem' => null, 'Kode_Grade_Final' => null, 'PA_Cycle_Curve_Id' => null,
                ]);

                return ['status' => 'DIHITUNG', 'alasan_tertahan' => null, 'final_score' => $final];
            });
        });
    }

    public function test_hitung_ulang_menulis_jejak_dan_mengunci_kurva_baru(): void
    {
        [$cycleId, $pids] = $this->siklusSiap();
        [$k1, $k2] = $this->kompetensi;
        $sesi = $this->sesiLangsung($cycleId, [$pids[0] => [$k1 => 100.0, $k2 => 100.0]]);
        $this->palsukanHitung();

        $r = app(PaBalancingHitungUlangService::class)->jalankan($cycleId, $this->userUji());

        $this->assertSame(1, $r['nomor']);
        $this->assertSame([$sesi], $r['sesi']);
        $this->assertSame(95.0, (float) DB::table('N_PA_Result')->where('PA_Participant_Id', $pids[0])->value('Final_Score'));
        $this->assertSame(1, DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')->count());
        $this->assertSame(1, DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'T')->count(), 'Kurva lama tetap tersimpan nonaktif.');
        $jejak = DB::table('N_PA_Balancing_Jejak_Hitung')->where('PA_Cycle_Id', $cycleId)->where('PA_Participant_Id', $pids[0])->first();
        $this->assertSame($sesi, (int) $jejak->PA_Balancing_Sesi_Id);
        $this->assertContains($jejak->Sebab, ['BALANCING', 'KEDUANYA', 'KURVA']);
        $this->assertNotNull(DB::table('N_PA_Balancing_Sesi')->where('Id_PA_Balancing_Sesi', $sesi)->value('Hitung_Ulang_At'));
    }

    public function test_hitung_ulang_ditolak_selama_ada_sesi_terbuka(): void
    {
        [$cycleId, $pids] = $this->siklusSiap();
        $this->sesiLangsung($cycleId, [$pids[0] => [$this->kompetensi[0] => 90.0]]);
        $this->sesiLangsung($cycleId, [$pids[1] => [$this->kompetensi[0] => 90.0]], 'DRAFT');

        try {
            app(PaBalancingHitungUlangService::class)->jalankan($cycleId, $this->userUji());
            $this->fail('Hitung ulang harus menunggu semua sesi final atau batal.');
        } catch (PaBalancingException $e) {
            $this->assertSame(PaBalancingException::SESI_MASIH_TERBUKA, $e->errorCode);
        }
    }

    public function test_hitung_ulang_ditolak_bila_ada_yang_sudah_dirilis_ke_leader(): void
    {
        [$cycleId, $pids] = $this->siklusSiap();
        $this->sesiLangsung($cycleId, [$pids[0] => [$this->kompetensi[0] => 90.0]]);
        DB::table('N_PA_Result')->where('PA_Participant_Id', $pids[3])->update(['Published_Leader_At' => now()]);

        try {
            app(PaBalancingHitungUlangService::class)->jalankan($cycleId, $this->userUji());
            $this->fail('Kurva tidak boleh dikunci ulang selagi ada hasil yang sudah dibaca leader.');
        } catch (PaBalancingException $e) {
            $this->assertSame(PaBalancingException::SUDAH_ADA_YANG_TERBIT, $e->errorCode);
        }
    }

    public function test_satu_peserta_tertahan_membatalkan_seluruh_hitung_ulang(): void
    {
        [$cycleId, $pids] = $this->siklusSiap();
        $this->sesiLangsung($cycleId, [$pids[0] => [$this->kompetensi[0] => 90.0], $pids[1] => [$this->kompetensi[0] => 90.0]]);
        $kurvaSebelum = DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $cycleId)->get();
        $this->palsukanHitung($pids[1]);

        try {
            app(PaBalancingHitungUlangService::class)->jalankan($cycleId, $this->userUji());
            $this->fail('Peserta yang menjadi TERTAHAN harus membatalkan hitung ulang.');
        } catch (PaBalancingException $e) {
            $this->assertSame(PaBalancingException::HITUNG_ULANG_TERTAHAN, $e->errorCode);
        }

        $this->assertEquals($kurvaSebelum, DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $cycleId)->get(), 'Kurva harus kembali seperti semula.');
        $this->assertSame(0, DB::table('N_PA_Balancing_Jejak_Hitung')->where('PA_Cycle_Id', $cycleId)->count());
    }

    public function test_kalibrasi_forum_lama_dilepas_dan_tercatat(): void
    {
        [$cycleId, $pids] = $this->siklusSiap();
        $this->sesiLangsung($cycleId, [$pids[0] => [$this->kompetensi[0] => 90.0]]);
        $kuadranForum = (int) DB::table('N_PA_Hav_Quadrant')->orderBy('Id_PA_Hav_Quadrant')->value('Id_PA_Hav_Quadrant');
        DB::table('N_PA_Result')->where('PA_Participant_Id', $pids[5])->update(['Flag_Dikalibrasi' => 'Y', 'Kode_Grade_Final' => 'A', 'PA_Hav_Quadrant_Id' => $kuadranForum]);
        $resultId = (int) DB::table('N_PA_Result')->where('PA_Participant_Id', $pids[5])->value('Id_PA_Result');
        $this->palsukanHitung();

        $r = app(PaBalancingHitungUlangService::class)->jalankan($cycleId, $this->userUji());

        $this->assertSame(1, $r['kalibrasi_dilepas']);
        // Review final, Penting 1: yang dilepas ikut dihitung ulang supaya kotak HAV pilihan forum tidak tertinggal.
        $this->assertContains($pids[5], $this->dihitung);
        $riwayat = DB::table('N_PA_Calibration_History')->where('PA_Result_Id', $resultId)->orderByDesc('Id_PA_Calibration_History')->first();
        $this->assertEquals(DB::table('N_PA_Result')->where('Id_PA_Result', $resultId)->value('PA_Hav_Quadrant_Id'), $riwayat->PA_Hav_Quadrant_Sesudah_Id);
        $this->assertSame(DB::table('N_PA_Result')->where('Id_PA_Result', $resultId)->value('Kode_Grade_Sistem'), $riwayat->Kode_Grade_Sesudah);
        $this->assertSame('T', DB::table('N_PA_Result')->where('Id_PA_Result', $resultId)->value('Flag_Dikalibrasi'));
        $this->assertStringContainsString('Balancing', (string) DB::table('N_PA_Calibration_History')->where('PA_Result_Id', $resultId)->orderByDesc('Id_PA_Calibration_History')->value('Alasan'));
    }

    /**
     * Siklus PITA tidak punya penjaga "sudah dibaca" tingkat siklus, jadi pelepasan kalibrasi
     * forum sendiri yang wajib melewati hasil yang sudah terbit atau dirilis ke leader (koreksi
     * 8 Okt 2026). Dulu semuanya ikut dilepas, lalu `hitung()` menolak yang TERBIT dan seluruh
     * hitung ulang batal.
     */
    public function test_pita_kalibrasi_hasil_yang_sudah_dibaca_tidak_dilepas(): void
    {
        [$cycleId, $pids] = $this->siklusSiap();
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->update(['Metode_Grade' => 'PITA']);
        $this->sesiLangsung($cycleId, [$pids[0] => [$this->kompetensi[0] => 90.0]]);
        DB::table('N_PA_Result')->whereIn('PA_Participant_Id', [$pids[4], $pids[5], $pids[6]])->update(['Flag_Dikalibrasi' => 'Y', 'Kode_Grade_Final' => 'A']);
        DB::table('N_PA_Result')->where('PA_Participant_Id', $pids[6])->update(['Status_Hasil' => 'TERBIT']);
        DB::table('N_PA_Result')->where('PA_Participant_Id', $pids[4])->update(['Published_Leader_At' => now()]);
        $this->palsukanHitung();

        $r = app(PaBalancingHitungUlangService::class)->jalankan($cycleId, $this->userUji());

        $this->assertSame(1, $r['kalibrasi_dilepas'], 'Hanya yang belum dibaca siapa pun yang dilepas.');
        $this->assertContains($pids[5], $this->dihitung);

        foreach ([$pids[4], $pids[6]] as $dibaca) {
            $this->assertNotContains($dibaca, $this->dihitung);
            $hasil = DB::table('N_PA_Result')->where('PA_Participant_Id', $dibaca)->first(['Flag_Dikalibrasi', 'Kode_Grade_Final']);
            $this->assertSame('Y', $hasil->Flag_Dikalibrasi);
            $this->assertSame('A', $hasil->Kode_Grade_Final);
        }
    }

    public function test_kualitatif_after_menjadi_catatan_admin(): void
    {
        [$cycleId, $pids] = $this->siklusSiap();
        $ql = (int) DB::table('N_PA_Competency as k')->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'k.PA_Competency_Type_Id')
            ->where('t.Kode_Type', 'KUALITATIF')->where('k.Flag_Aktif', 'Y')->orderBy('k.Id_PA_Competency')->value('k.Id_PA_Competency');
        $this->sesiLangsung($cycleId, [$pids[0] => [$this->kompetensi[0] => 90.0]], 'FINAL', [$pids[0] => [$ql => '<p>Teliti</p>']]);
        $this->palsukanHitung();

        app(PaBalancingHitungUlangService::class)->jalankan($cycleId, $this->userUji());

        $resultId = (int) DB::table('N_PA_Result')->where('PA_Participant_Id', $pids[0])->value('Id_PA_Result');
        $this->assertSame(['<p>Teliti</p>'], DB::table('N_PA_Result_Note')->where('PA_Result_Id', $resultId)
            ->where('PA_Competency_Id', $ql)->whereNull('PA_Assignment_Id')->where('Flag_Aktif', 'Y')->pluck('Isi')->all());
    }

    /* Koreksi tahap Balancing (7 Okt 2026) */

    public function test_kesimpulan_yang_diubah_admin_sesudah_sesi_dibuat_tidak_ditimpa(): void
    {
        [$cycleId, $pids] = $this->siklusSiap();
        $ql = (int) DB::table('N_PA_Competency as k')->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'k.PA_Competency_Type_Id')
            ->where('t.Kode_Type', 'KUALITATIF')->where('k.Flag_Aktif', 'Y')->orderBy('k.Id_PA_Competency')->value('k.Id_PA_Competency');
        // Before sesi: kosong. Forum menulis After "Teliti".
        $this->sesiLangsung($cycleId, [$pids[0] => [$this->kompetensi[0] => 90.0]], 'FINAL', [$pids[0] => [$ql => '<p>Teliti</p>']]);
        // Admin menulis kesimpulan SESUDAH sesi dibuat.
        $resultId = (int) DB::table('N_PA_Result')->where('PA_Participant_Id', $pids[0])->value('Id_PA_Result');
        DB::table('N_PA_Result_Note')->insert([
            'PA_Result_Id' => $resultId, 'PA_Competency_Id' => $ql, 'PA_Assignment_Id' => null,
            'Isi' => '<p>Versi admin terbaru</p>', 'Urutan_Tampil' => 1, 'Flag_Aktif' => 'Y',
        ]);
        $this->palsukanHitung();

        $r = app(PaBalancingHitungUlangService::class)->jalankan($cycleId, $this->userUji());

        $this->assertSame(['<p>Versi admin terbaru</p>'], DB::table('N_PA_Result_Note')->where('PA_Result_Id', $resultId)
            ->where('PA_Competency_Id', $ql)->whereNull('PA_Assignment_Id')->where('Flag_Aktif', 'Y')->pluck('Isi')->all());
        $this->assertCount(1, $r['kualitatif_dilewati']);
        $this->assertSame($pids[0], $r['kualitatif_dilewati'][0]['participant_id']);
    }

    public function test_boleh_buat_sesi_menyebut_sebab_sebelum_wizard(): void
    {
        $level = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $s = $this->siklusKurva([$level => [70, 80, 90]]);
        $layanan = app(\App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingSesiService::class);

        $belumKunci = $layanan->bolehBuat($s['cycle_id']);
        $this->assertFalse($belumKunci['boleh']);
        $this->assertStringContainsString('Kunci kurva', $belumKunci['alasan']);

        app(PaKurvaService::class)->kunci($s['cycle_id'], 7);
        $this->assertSame(['boleh' => true, 'alasan' => null], $layanan->bolehBuat($s['cycle_id']));

        DB::table('N_PA_Result')->where('PA_Participant_Id', $s['peserta'][$level][0])->update(['Published_Leader_At' => now()]);
        $dirilis = $layanan->bolehBuat($s['cycle_id']);
        $this->assertFalse($dirilis['boleh']);
        $this->assertStringContainsString('dirilis ke leader', $dirilis['alasan']);
    }
}
