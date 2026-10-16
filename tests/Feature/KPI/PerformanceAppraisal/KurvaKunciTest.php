<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use App\Services\KPI\PerformanceAppraisal\PaResultHistoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanSiklusKurva;
use Tests\TestCase;

/**
 * Siklus KURVA tiruan: dua grup level dengan sebaran berbeda, hasil DIHITUNG ditanam langsung.
 */
class KurvaKunciTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanLembarPenilaian;
    use MenyiapkanSiklusKurva;

    private function layanan(): PaKurvaService
    {
        return app(PaKurvaService::class);
    }

    private function grade(int $pid): ?string
    {
        return DB::table('N_PA_Result')->where('PA_Participant_Id', $pid)->where('Flag_Aktif', 'Y')->value('Kode_Grade_Sistem');
    }

    private function levelUji(): array
    {
        return DB::table('HRIS_Level')->orderBy('ID_Level')->limit(2)->pluck('ID_Level')->map(fn ($x) => (int) $x)->all();
    }

    public function test_kunci_memberi_grade_per_grup_dari_kurvanya_sendiri(): void
    {
        [$l1, $l2] = $this->levelUji();
        // Nilai 80 di grup rendah adalah bintang; di grup tinggi adalah rata-rata.
        $s = $this->siklusKurva([$l1 => [50, 55, 60, 65, 80], $l2 => [70, 75, 80, 85, 90]]);

        $hasil = $this->layanan()->kunci($s['cycle_id'], 7);

        $this->assertTrue($hasil['terkunci']);
        $this->assertSame('B_PLUS', $this->grade($s['peserta'][$l1][4]), 'z = +1,37 di grup rendah.');
        $this->assertSame('B', $this->grade($s['peserta'][$l2][2]), 'z = 0 di grup tinggi.');
        $this->assertSame(2, DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $s['cycle_id'])->where('Flag_Aktif', 'Y')->count());
        $this->assertNotNull(DB::table('N_PA_Result')->where('PA_Participant_Id', $s['peserta'][$l1][0])->value('PA_Cycle_Curve_Id'));
        $this->assertContains('Grup G'.$l1.' hanya 5 orang (di bawah 30); kurvanya kurang bermakna.', $hasil['grup'][0]['peringatan']);
    }

    public function test_sebelum_kunci_grade_hanya_sementara_dan_kosong_di_basis_data(): void
    {
        [$l1] = $this->levelUji();
        $s = $this->siklusKurva([$l1 => [50, 60, 70, 80, 90]]);

        $p = $this->layanan()->pratinjau($s['cycle_id']);

        $this->assertFalse($p['terkunci']);
        $this->assertSame(1, $p['grup'][0]['sebaran']['B_PLUS']);
        $this->assertNull($this->grade($s['peserta'][$l1][4]));
    }

    public function test_kunci_ganda_dan_siklus_pita_ditolak(): void
    {
        [$l1] = $this->levelUji();
        $s = $this->siklusKurva([$l1 => [50, 60, 70]]);
        $this->layanan()->kunci($s['cycle_id'], null);

        $this->expectException(RuntimeException::class);
        $this->layanan()->kunci($s['cycle_id'], null);
    }

    public function test_buka_wajib_alasan_dan_ditolak_bila_ada_yang_terbit(): void
    {
        [$l1] = $this->levelUji();
        $s = $this->siklusKurva([$l1 => [50, 60, 70]]);
        $this->layanan()->kunci($s['cycle_id'], null);

        try {
            $this->layanan()->buka($s['cycle_id'], '  ', null);
            $this->fail('Buka tanpa alasan seharusnya ditolak.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        DB::table('N_PA_Result')->where('PA_Participant_Id', $s['peserta'][$l1][0])->update(['Status_Hasil' => 'TERBIT']);

        try {
            $this->layanan()->buka($s['cycle_id'], 'Salah grouping', null);
            $this->fail('Buka sesudah ada yang terbit seharusnya ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('terbit', $e->getMessage());
        }
    }

    public function test_buka_mengosongkan_grade_mesin_tetapi_mempertahankan_keputusan_forum(): void
    {
        [$l1] = $this->levelUji();
        $s = $this->siklusKurva([$l1 => [50, 60, 70]]);
        $this->layanan()->kunci($s['cycle_id'], null);
        [$p0, $p1] = $s['peserta'][$l1];
        DB::table('N_PA_Result')->where('PA_Participant_Id', $p1)->update(['Flag_Dikalibrasi' => 'Y', 'Kode_Grade_Final' => 'A']);

        $this->layanan()->buka($s['cycle_id'], 'Grouping diperbaiki', 3);

        $this->assertNull($this->grade($p0));
        $this->assertNull(DB::table('N_PA_Result')->where('PA_Participant_Id', $p0)->value('Kode_Grade_Final'));
        $this->assertSame('A', DB::table('N_PA_Result')->where('PA_Participant_Id', $p1)->value('Kode_Grade_Final'));
        $this->assertSame(0, DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $s['cycle_id'])->where('Flag_Aktif', 'Y')->count());
        $this->assertSame(1, DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $s['cycle_id'])->whereNotNull('Alasan_Buka')->count());
    }

    public function test_grade_beku_dipakai_sesudah_kunci_dan_tidak_menggeser_kurva(): void
    {
        [$l1] = $this->levelUji();
        $s = $this->siklusKurva([$l1 => [50, 60, 70, 80, 90]]);
        $this->layanan()->kunci($s['cycle_id'], null);
        $rata = (float) DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $s['cycle_id'])->where('Flag_Aktif', 'Y')->value('Rata_Rata');

        // μ 70, σ 15,81 → A mulai 101,62: 100 masih B_PLUS, 105 sudah A.
        $this->assertSame('B_PLUS', $this->layanan()->gradeBeku($s['peserta'][$l1][0], 100.0)['kode']);
        $beku = $this->layanan()->gradeBeku($s['peserta'][$l1][0], 105.0);

        $this->assertSame('A', $beku['kode']);
        $this->assertEqualsWithDelta(70.0, $rata, 0.0001);
    }

    public function test_grup_kosong_tidak_dikunci_dan_peserta_susulannya_ditahan_beralasan(): void
    {
        [$l1, $l2] = $this->levelUji();
        $s = $this->siklusKurva([$l1 => [50, 60, 70], $l2 => [80]]);
        $susulan = $s['peserta'][$l2][0];
        // Hasil grup kedua belum ada saat kurva dikunci — dikunci dengan sadar, beralasan.
        DB::table('N_PA_Result')->where('PA_Participant_Id', $susulan)->update(['Status_Hasil' => 'BELUM_DIHITUNG', 'Final_Score' => null]);

        $hasil = $this->layanan()->kunci($s['cycle_id'], null, 'Susulan menyusul belakangan');

        $this->assertSame(1, DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $s['cycle_id'])->where('Flag_Aktif', 'Y')->count(),
            'Grup kosong tidak boleh mendapat kurva datar yang memberi grade tengah otomatis.');
        $this->assertSame([], $hasil['grup'][1]['pita']);
        $this->assertStringContainsString('kosong saat kurva dikunci', $hasil['grup'][1]['peringatan'][0]);

        $beku = $this->layanan()->gradeBeku($susulan, 80.0);

        $this->assertNull($beku['kode']);
        $this->assertNull($beku['curve_id']);
        $this->assertStringContainsString('Nilai akhir 80,00 sudah terhitung', $beku['alasan']);
        $this->assertStringContainsString('kunci ulang', $beku['alasan']);
    }

    public function test_peserta_yang_levelnya_tanpa_grup_ditahan_beralasan_sesudah_kunci(): void
    {
        [$l1, $l2] = $this->levelUji();
        $s = $this->siklusKurva([$l1 => [50, 60, 70], $l2 => [80]]);
        $susulan = $s['peserta'][$l2][0];
        DB::table('N_PA_Result')->where('PA_Participant_Id', $susulan)->update(['Status_Hasil' => 'BELUM_DIHITUNG', 'Final_Score' => null]);
        DB::table('N_PA_Cycle_Curve_Group_Level')->where('PA_Cycle_Id', $s['cycle_id'])->where('ID_Level', $l2)->update(['Flag_Aktif' => 'T']);
        $this->layanan()->kunci($s['cycle_id'], null, 'Susulan menyusul belakangan');

        $this->assertStringContainsString('tidak masuk grup kurva', $this->layanan()->gradeBeku($susulan, 80.0)['alasan']);
    }

    /* Koreksi tahap Kurva (7 Okt 2026) */

    public function test_buka_ditolak_bila_ada_hasil_yang_dirilis_ke_leader(): void
    {
        [$l1] = $this->levelUji();
        $s = $this->siklusKurva([$l1 => [50, 60, 70]]);
        $this->layanan()->kunci($s['cycle_id'], null);
        DB::table('N_PA_Result')->where('PA_Participant_Id', $s['peserta'][$l1][0])->update(['Published_Leader_At' => now()]);

        $this->assertSame(1, $this->layanan()->pratinjau($s['cycle_id'])['sudah_dirilis']);

        try {
            $this->layanan()->buka($s['cycle_id'], 'Salah grouping', null);
            $this->fail('Buka sesudah ada yang dirilis ke leader seharusnya ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('dirilis ke leader', $e->getMessage());
        }
    }

    public function test_level_tanpa_grup_disebut_dan_kunci_dimatikan_sebelum_diklik(): void
    {
        [$l1, $l2] = $this->levelUji();
        $s = $this->siklusKurva([$l1 => [50, 60, 70], $l2 => [80]]);
        DB::table('N_PA_Cycle_Curve_Group_Level')->where('PA_Cycle_Id', $s['cycle_id'])->where('ID_Level', $l2)->update(['Flag_Aktif' => 'T']);

        $p = $this->layanan()->pratinjau($s['cycle_id']);

        $this->assertCount(1, $p['level_tanpa_grup']);
        $this->assertFalse($p['boleh_kunci']);
        $this->assertStringContainsString('belum masuk grup kurva', implode(' ', $p['peringatan']));
    }

    public function test_peringatan_sebelum_kunci_menyebut_hasil_belum_dihitung(): void
    {
        [$l1] = $this->levelUji();
        $s = $this->siklusKurva([$l1 => [50, 60, 70]]);
        DB::table('N_PA_Result')->where('PA_Participant_Id', $s['peserta'][$l1][0])->update(['Status_Hasil' => 'BELUM_DIHITUNG', 'Final_Score' => null]);

        $p = $this->layanan()->pratinjau($s['cycle_id']);

        $this->assertSame(1, $p['belum_dihitung']);
        $this->assertStringContainsString('1 hasil belum dihitung', implode(' ', $p['peringatan']));

        /* Koreksi 8 Okt 2026: hasil yang belum berangka tidak lagi lolos diam-diam. Tombolnya
           tetap bisa dipakai, tetapi hanya dengan alasan tertulis. */
        $this->assertFalse($p['boleh_kunci']);
        $this->assertTrue($p['boleh_kunci_dengan_alasan']);
        $this->assertStringContainsString('1 hasil belum dihitung dan 0 tertahan', $p['alasan_kunci']);
    }

    /* Koreksi 8 Okt 2026: kurva tidak bisa dikunci diam-diam selagi hasil belum lengkap. */

    public function test_kunci_tanpa_alasan_ditolak_selama_hasil_belum_lengkap(): void
    {
        [$l1] = $this->levelUji();
        $s = $this->siklusKurva([$l1 => [50, 60, 70, 80]]);
        DB::table('N_PA_Result')->where('PA_Participant_Id', $s['peserta'][$l1][0])->update(['Status_Hasil' => 'BELUM_DIHITUNG', 'Final_Score' => null]);
        DB::table('N_PA_Result')->where('PA_Participant_Id', $s['peserta'][$l1][1])->update(['Status_Hasil' => 'TERTAHAN', 'Final_Score' => null]);

        foreach ([null, '  ab '] as $alasan) {
            try {
                $this->layanan()->kunci($s['cycle_id'], 7, $alasan);
                $this->fail('Kunci tanpa alasan selagi hasil belum lengkap seharusnya ditolak.');
            } catch (AssertionFailedError $gagal) {
                throw $gagal;
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('1 hasil belum dihitung dan 1 tertahan', $e->getMessage());
            }
        }

        $this->assertFalse($this->layanan()->terkunci($s['cycle_id']));
    }

    /* 14 Okt 2026: grup tanpa sebaran (satu orang / nilai sama) memberi semua anggotanya grade
       tengah; kunci wajib beralasan supaya itu keputusan, bukan kebetulan. */
    public function test_grup_tanpa_sebaran_wajib_beralasan_saat_kunci(): void
    {
        [$l1, $l2] = $this->levelUji();
        $s = $this->siklusKurva([$l1 => [50, 60, 70, 80], $l2 => [95]]);

        $p = $this->layanan()->pratinjau($s['cycle_id']);
        $this->assertFalse($p['boleh_kunci']);
        $this->assertTrue($p['boleh_kunci_dengan_alasan']);
        $this->assertStringContainsString('tidak punya sebaran', $p['alasan_kunci']);

        try {
            $this->layanan()->kunci($s['cycle_id'], 7);
            $this->fail('Kunci dengan grup tanpa sebaran seharusnya meminta alasan.');
        } catch (AssertionFailedError $gagal) {
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('tidak punya sebaran', $e->getMessage());
        }

        $this->layanan()->kunci($s['cycle_id'], 7, 'Level khusus, memang satu orang (uji).');
        $this->assertTrue($this->layanan()->terkunci($s['cycle_id']));
    }

    public function test_kunci_beralasan_mencatat_keputusannya_di_riwayat_hasil(): void
    {
        [$l1] = $this->levelUji();
        $s = $this->siklusKurva([$l1 => [50, 60, 70, 80]]);
        DB::table('N_PA_Result')->where('PA_Participant_Id', $s['peserta'][$l1][0])->update(['Status_Hasil' => 'TERTAHAN', 'Final_Score' => null]);

        $hasil = $this->layanan()->kunci($s['cycle_id'], 7, 'Tanpa akun KPI, tidak akan pernah berangka');

        $this->assertTrue($hasil['terkunci']);
        $keterangan = DB::table('N_PA_Result_History as h')->join('N_PA_Result as r', 'r.Id_PA_Result', '=', 'h.PA_Result_Id')
            ->where('r.PA_Participant_Id', $s['peserta'][$l1][1])->where('h.Jenis_Peristiwa', PaResultHistoryService::KURVA_DIKUNCI)
            ->value('h.Keterangan');
        $this->assertStringContainsString('0 hasil belum dihitung dan 1 tertahan', $keterangan);
        $this->assertStringContainsString('Tanpa akun KPI', $keterangan);
    }

    public function test_peserta_dikeluarkan_tidak_menahan_kunci(): void
    {
        [$l1] = $this->levelUji();
        $s = $this->siklusKurva([$l1 => [50, 60, 70]]);
        $keluar = $s['peserta'][$l1][0];
        DB::table('N_PA_Result')->where('PA_Participant_Id', $keluar)->update(['Status_Hasil' => 'TERTAHAN', 'Final_Score' => null]);
        DB::table('N_PA_Participant')->where('Id_PA_Participant', $keluar)->update(['Status_Peserta' => 'DIKECUALIKAN']);

        $p = $this->layanan()->pratinjau($s['cycle_id']);

        $this->assertTrue($p['boleh_kunci']);
        $this->assertNull($p['alasan_kunci']);
        $this->assertTrue($this->layanan()->kunci($s['cycle_id'], null)['terkunci']);
    }

    public function test_boleh_kunci_menyebut_batas_z_kosong_dan_status_siklus(): void
    {
        [$l1] = $this->levelUji();
        $s = $this->siklusKurva([$l1 => [50, 60, 70]], 'OPEN');
        DB::table('N_PA_Cycle_Score_Range_Snapshot')->where('PA_Cycle_Id', $s['cycle_id'])->update(['Batas_Z' => null]);

        $p = $this->layanan()->pratinjau($s['cycle_id']);

        $this->assertFalse($p['boleh_kunci']);
        $this->assertFalse($p['boleh_kunci_dengan_alasan']);
        $this->assertStringContainsString('status sekarang OPEN', $p['alasan_kunci']);
        $this->assertStringContainsString('Batas z grade belum diatur', $p['alasan_kunci']);
    }

    public function test_kunci_ditolak_bila_belum_ada_hasil_sama_sekali(): void
    {
        [$l1] = $this->levelUji();
        $s = $this->siklusKurva([$l1 => [50]]);
        DB::table('N_PA_Result')->where('PA_Participant_Id', $s['peserta'][$l1][0])->update(['Status_Hasil' => 'TERTAHAN', 'Final_Score' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Belum ada hasil DIHITUNG');
        $this->layanan()->kunci($s['cycle_id'], null);
    }

    public function test_pita_beku_bisa_diturunkan_ulang_dari_mu_dan_sigma_yang_tersimpan(): void
    {
        /* pa:verify menurunkan ulang pita dari Rata_Rata & Simpangan_Baku TERSIMPAN (4 desimal).
           Pita yang dihitung dari μ/σ tak dibulatkan meleset 0,0001 dan terbaca "disunting
           tangan" — terukur di PA-2026-11 (C_PLUS 72,7444 vs 72,7443). */
        [$l1] = $this->levelUji();
        $nilai = [61.37, 64.12, 70.03, 72.58, 77.91, 81.44, 85.26, 88.19, 90.77, 93.05, 95.62, 99.13, 100.48, 104.9];
        $s = $this->siklusKurva([$l1 => $nilai]);
        $this->layanan()->kunci($s['cycle_id'], null);

        $k = DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $s['cycle_id'])->where('Flag_Aktif', 'Y')->first();
        $pita = DB::table('N_PA_Cycle_Curve_Band')->where('PA_Cycle_Curve_Id', $k->Id_PA_Cycle_Curve)->whereNotNull('Batas_Z')->get();

        $this->assertCount(5, $pita);
        foreach ($pita as $b) {
            $harus = max(0.0, round((float) $k->Rata_Rata + (float) $b->Batas_Z * (float) $k->Simpangan_Baku, 4));
            $this->assertSame(sprintf('%.4f', $harus), sprintf('%.4f', (float) $b->Nilai_Dari), "Pita {$b->Kode_Range} tidak bisa diturunkan ulang.");
        }
    }

    public function test_pindah_ke_kurva_ditolak_bila_ada_yang_terbit(): void
    {
        [$l1] = $this->levelUji();
        $s = $this->siklusKurva([$l1 => [50, 60]]);
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $s['cycle_id'])->update(['Metode_Grade' => 'PITA']);
        DB::table('N_PA_Result')->where('PA_Participant_Id', $s['peserta'][$l1][0])->update(['Status_Hasil' => 'TERBIT']);

        $this->expectException(RuntimeException::class);
        $this->layanan()->ubahMetode($s['cycle_id'], 'KURVA', null);
    }

    public function test_pastikan_terkunci_menjaga_kalibrasi_dan_penerbitan(): void
    {
        [$l1] = $this->levelUji();
        $s = $this->siklusKurva([$l1 => [50, 60]]);

        try {
            $this->layanan()->pastikanTerkunci($s['cycle_id']);
            $this->fail('Siklus kurva belum dikunci seharusnya ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Kunci kurva', $e->getMessage());
        }

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $s['cycle_id'])->update(['Metode_Grade' => 'PITA']);
        $this->layanan()->pastikanTerkunci($s['cycle_id']);
        $this->addToAssertionCount(1);
    }
}
