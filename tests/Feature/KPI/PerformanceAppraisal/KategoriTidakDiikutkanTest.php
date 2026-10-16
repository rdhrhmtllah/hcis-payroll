<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\PerformanceAppraisal\Katalog\PaLevelDikecualikan;
use App\Services\KPI\PerformanceAppraisal\PaAssignmentBuilderService;
use App\Services\KPI\PerformanceAppraisal\PaAssignmentHealthService;
use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use App\Services\KPI\PerformanceAppraisal\PaCyclePreparationService;
use App\Services\KPI\PerformanceAppraisal\PaKategoriTidakDiikutkanService;
use App\Services\KPI\PerformanceAppraisal\PaMasterWriteService;
use App\Services\KPI\PerformanceAppraisal\PaParticipantRuleResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

/**
 * Kekurangan penilai wajib diputuskan sebelum dikunci (UAT 13 Okt 2026).
 *
 * ── Apa yang dijaga ───────────────────────────────────────────────────────────
 *
 *  1. Penguncian ditahan selama ada peserta x kategori di bawah minimal yang belum
 *     diputuskan — di server, dan `ringkasanPraKunci` menyebutnya dengan kalimat yang sama.
 *  2. "Tandai tidak diikutkan" mencabut kategori bagi SATU peserta, membagi ulang bobotnya
 *     proporsional (jumlahnya tetap 100), dan membatalkan penilai yang tersisa di kategori itu.
 *  3. Peserta yang ditandai tidak lagi dihitung kekurangan.
 *  4. Membatalkan tanda memulihkan aturan level dan penilai yang dibatalkan olehnya.
 *  5. Alasan wajib; tidak boleh mencabut seluruh kategori; penyimpangan manual tidak ditimpa.
 *  6. Ambang kekurangan = maks(Min_Rater, Min_Rater_Selesai); master menolak selesai > min.
 *  7. Penilai yang sudah nonaktif di HRIS ikut menahan penguncian.
 */
class KategoriTidakDiikutkanTest extends TestCase
{
    use DatabaseTransactions;

    private int $cycleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::whereNotNull('User_Key')->firstOrFail());
        Queue::fake();

        DB::table('N_PA_Level_Exclusion')->where('Flag_Aktif', 'Y')->whereIn('ID_Level', [2])->update(['Flag_Aktif' => 'T']);
        PaLevelDikecualikan::lupakan();

        $kode = 'UJI-TDK-'.substr(bin2hex(random_bytes(4)), 0, 8);
        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => $kode,
            'Nama_Cycle' => 'Siklus uji kategori tidak diikutkan',
            'Tahun' => 2026,
            'Status_Cycle' => 'DRAFT',
            'Periode_Dinilai_Dari' => '2026-01-01',
            'Periode_Dinilai_Sampai' => '2026-06-30',
            'Pengisian_Dari' => now()->subDay()->format('Y-m-d H:i:s'),
            'Pengisian_Sampai' => now()->addDays(20)->format('Y-m-d H:i:s'),
        ]);
        $this->cycleId = (int) DB::table('N_PA_Cycle')->where('Kode_Cycle', $kode)->value('Id_PA_Cycle');

        app(PaCycleGate::class)->lupakanMemo();
        app(PaCyclePreparationService::class)->siapkan($this->cycleId, 4);
        app(PaCycleGate::class)->lupakanMemo();
    }

    public function test_kunci_ditahan_selama_ada_kekurangan_yang_belum_diputuskan(): void
    {
        config(['services.kpi.pa_kunci_tahan_kekurangan' => true]);

        $prep = app(PaCyclePreparationService::class);
        $pra = $prep->ringkasanPraKunci($this->cycleId);

        $this->assertNotNull($pra['penghalang_kekurangan'], 'Siklus uji tanpa kekurangan; uji ini tidak menguji apa pun.');
        $this->assertContains($pra['penghalang_kekurangan'], $pra['penghalang']);
        $this->assertFalse($pra['boleh_dikunci']);

        try {
            $prep->kunci($this->cycleId, 4);
            $this->fail('Siklus dengan kekurangan penilai seharusnya tidak bisa dikunci.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('di bawah minimal', $e->getMessage());
        }

        $this->assertSame('PREPARED', DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)->value('Status_Cycle'));
    }

    public function test_tandai_mencabut_kategori_membagi_ulang_bobot_dan_membatalkan_penilai(): void
    {
        [$pid, $kategori] = $this->pesertaDenganKategoriBerpenilai();
        $kode = $kategori['kode_kategori'];
        $asal = $this->aturan($pid);
        $penilaiKategori = $this->aktifDiKategori($pid, $kategori['kategori_id']);
        $this->assertGreaterThan(0, $penilaiKategori);

        $hasil = $this->layanan()->tandai($pid, $kategori['kategori_id'], 'Tidak ada rekan selevel (uji).', 4);

        $efektif = $this->aturan($pid);
        $this->assertArrayNotHasKey($kode, $efektif);
        $this->assertEqualsWithDelta(100.0, array_sum(array_column($efektif, 'bobot_persen')), 0.01);

        /* Proporsional terhadap aturan level: rasio antar kategori yang tersisa tidak berubah. */
        $sisaAsal = array_diff_key($asal, [$kode => true]);
        $totalSisa = array_sum(array_column($sisaAsal, 'bobot_persen'));
        foreach ($sisaAsal as $k => $a) {
            $this->assertEqualsWithDelta($a['bobot_persen'] / $totalSisa * 100, $efektif[$k]['bobot_persen'], 0.02, "Bobot {$k}");
        }

        $this->assertSame($penilaiKategori, $hasil['dibatalkan']);
        $this->assertSame(0, $this->aktifDiKategori($pid, $kategori['kategori_id']));

        /* Tidak lagi dihitung kekurangan, dan keputusannya terlihat beserta alasannya. */
        $kesehatan = app(PaAssignmentHealthService::class);
        $baris = collect($kesehatan->pesertaBermasalah($this->cycleId, ['per_halaman' => 100, 'cari' => $this->kodePeserta($pid)])['baris'])
            ->firstWhere('participant_id', $pid);
        $kurangKategori = collect($baris['masalah'] ?? [])->where('jenis', 'KURANG')->where('kategori_id', $kategori['kategori_id']);
        $this->assertCount(0, $kurangKategori);

        $tanda = collect($kesehatan->kategoriTidakDiikutkan($this->cycleId))->firstWhere('participant_id', $pid);
        $this->assertSame('Tidak ada rekan selevel (uji).', $tanda['alasan']);
        $this->assertSame($kode, $tanda['kode_kategori']);
    }

    public function test_batalkan_tanda_memulihkan_aturan_level_dan_penilainya(): void
    {
        [$pid, $kategori] = $this->pesertaDenganKategoriBerpenilai();
        $asal = $this->aturan($pid);
        $penilaiKategori = $this->aktifDiKategori($pid, $kategori['kategori_id']);

        $this->layanan()->tandai($pid, $kategori['kategori_id'], 'Uji.', 4);
        $hasil = $this->layanan()->batalkanTanda($pid, $kategori['kategori_id'], 4);

        $this->assertSame($penilaiKategori, $hasil['dipulihkan']);
        $this->assertSame($penilaiKategori, $this->aktifDiKategori($pid, $kategori['kategori_id']));
        $this->assertEquals(
            array_map(fn ($a) => $a['bobot_persen'], $asal),
            array_map(fn ($a) => $a['bobot_persen'], $this->aturan($pid)),
        );
        $this->assertSame(0, DB::table('N_PA_Participant_Rater_Override')
            ->where('PA_Participant_Id', $pid)->where('Flag_Aktif', 'Y')->count());
    }

    public function test_alasan_wajib_dan_tidak_boleh_mencabut_seluruh_kategori(): void
    {
        [$pid, $kategori] = $this->pesertaDenganKategoriBerpenilai();

        try {
            $this->layanan()->tandai($pid, $kategori['kategori_id'], '   ', 4);
            $this->fail('Tanda tanpa alasan seharusnya ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Alasan wajib', $e->getMessage());
        }

        $semua = array_values($this->aturan($pid));
        $terakhir = array_pop($semua);
        foreach ($semua as $a) {
            $this->layanan()->tandai($pid, $a['kategori_id'], 'Uji.', 4);
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('semua kategori');
        $this->layanan()->tandai($pid, $terakhir['kategori_id'], 'Uji.', 4);
    }

    public function test_penyimpangan_manual_tidak_ditimpa(): void
    {
        [$pid, $kategori] = $this->pesertaDenganKategoriBerpenilai();

        DB::table('N_PA_Participant_Rater_Override')->insert([
            'PA_Participant_Id' => $pid,
            'PA_Rater_Category_Id' => $kategori['kategori_id'],
            'Flag_Dipakai' => 'T',
            'Alasan_Perubahan' => 'Diatur manual (uji).',
            'Flag_Aktif' => 'Y',
            'Created_By' => 4,
            'Created_At' => now()->format('Y-m-d H:i:s'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('diatur manual');
        $this->layanan()->tandai($pid, $kategori['kategori_id'], 'Uji.', 4);
    }

    public function test_ambang_memakai_min_selesai_bila_lebih_besar(): void
    {
        [$pid, $kategori] = $this->pesertaDenganKategoriBerpenilai();
        $terpasang = $this->aktifDiKategori($pid, $kategori['kategori_id']);

        /* "Ditugaskan N, harus selesai N+1": cukup terpasang menurut Min_Rater, tetapi pasti
           gugur. Ditulis langsung karena kedua jalur tulis kini menolaknya. */
        DB::table('N_PA_Cycle_Rater_Policy_Detail_Snapshot')
            ->where('PA_Cycle_Rater_Policy_Snapshot_Id', DB::table('N_PA_Participant')->where('Id_PA_Participant', $pid)->value('PA_Cycle_Rater_Policy_Snapshot_Id'))
            ->where('PA_Rater_Category_Id', $kategori['kategori_id'])
            ->update(['Min_Rater' => $terpasang, 'Min_Rater_Selesai' => $terpasang + 1]);

        $baris = collect(app(PaAssignmentHealthService::class)
            ->pesertaBermasalah($this->cycleId, ['per_halaman' => 100, 'hanya_bermasalah' => true, 'cari' => $this->kodePeserta($pid)])['baris'])
            ->firstWhere('participant_id', $pid);

        $kurang = collect($baris['masalah'] ?? [])->where('jenis', 'KURANG')->firstWhere('kategori_id', $kategori['kategori_id']);
        $this->assertNotNull($kurang, 'Kategori yang pasti gugur harus dilaporkan kurang.');
        $this->assertSame($terpasang + 1, $kurang['min_rater']);
    }

    public function test_master_menolak_min_selesai_melebihi_min_penilai(): void
    {
        $policyId = (int) DB::table('N_PA_Rater_Policy_Detail')->where('Flag_Aktif', 'Y')
            ->groupBy('PA_Rater_Policy_Id')->havingRaw('SUM(Bobot_Persen) = 100')
            ->orderBy('PA_Rater_Policy_Id')->value('PA_Rater_Policy_Id');
        $rincian = DB::table('N_PA_Rater_Policy_Detail')->where('PA_Rater_Policy_Id', $policyId)->where('Flag_Aktif', 'Y')
            ->get()->map(fn ($d) => [
                'pa_rater_category_id' => (int) $d->PA_Rater_Category_Id,
                'bobot_persen' => (float) $d->Bobot_Persen,
                'min_rater' => 1,
                'maks_rater' => null,
                'min_rater_selesai' => null,
            ])->all();
        $rincian[0]['min_rater_selesai'] = 2;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('melebihi Min_Rater');
        app(PaMasterWriteService::class)->simpanRincianAturan($policyId, $rincian, 4);
    }

    public function test_penilai_nonaktif_menahan_kunci(): void
    {
        config(['services.kpi.pa_kunci_tahan_kekurangan' => true]);
        [$pid, $kategori] = $this->pesertaDenganKategoriBerpenilai();

        $keluar = DB::table('Karyawan')->where('Aktif', '<>', 'Y')->first(['Kode_Perusahaan', 'Kode_Karyawan']);
        $this->assertNotNull($keluar, 'Data uji tanpa karyawan nonaktif.');

        DB::table('N_PA_Assignment')->where('PA_Participant_Id', $pid)
            ->where('PA_Rater_Category_Id', $kategori['kategori_id'])->where('Flag_Aktif', 'Y')
            ->orderBy('Id_PA_Assignment')->limit(1)
            ->update(['Kode_Perusahaan_Penilai' => $keluar->Kode_Perusahaan, 'Kode_Karyawan_Penilai' => $keluar->Kode_Karyawan]);

        $pra = app(PaCyclePreparationService::class)->ringkasanPraKunci($this->cycleId);

        $this->assertNotNull($pra['penghalang_nonaktif']);
        $this->assertContains($pra['penghalang_nonaktif'], $pra['penghalang']);
        $this->assertFalse($pra['boleh_dikunci']);
    }

    /** @return array{0:int, 1:array<string, mixed>} */
    private function pesertaDenganKategoriBerpenilai(): array
    {
        $pembentuk = app(PaAssignmentBuilderService::class);

        foreach (DB::table('N_PA_Participant')->where('PA_Cycle_Id', $this->cycleId)->where('Status_Peserta', 'DIIKUTKAN')
            ->orderBy('Id_PA_Participant')->limit(40)->get() as $p) {
            $pid = (int) $p->Id_PA_Participant;

            if ($pembentuk->bentukUntukPeserta($pid, 4)['dibuat'] < 2) {
                continue;
            }
            $pembentuk->lupakanMemo();

            $aturan = $this->aturan($pid);
            if (count($aturan) < 2) {
                continue;
            }

            foreach ($aturan as $a) {
                if ($a['kode_kategori'] !== PaAssignmentBuilderService::KATEGORI_SELF
                    && $this->aktifDiKategori($pid, $a['kategori_id']) > 0) {
                    return [$pid, $a];
                }
            }
        }

        $this->fail('Tidak ada peserta dengan kategori berpenilai di data uji.');
    }

    /** @return array<string, array<string, mixed>> */
    private function aturan(int $pid): array
    {
        $r = app(PaParticipantRuleResolver::class);
        $r->lupakanMemo();

        return $r->raterRules($pid);
    }

    private function aktifDiKategori(int $pid, int $kategoriId): int
    {
        return DB::table('N_PA_Assignment')->where('PA_Participant_Id', $pid)
            ->where('PA_Rater_Category_Id', $kategoriId)->where('Flag_Aktif', 'Y')->count();
    }

    private function kodePeserta(int $pid): string
    {
        return (string) DB::table('N_PA_Participant')->where('Id_PA_Participant', $pid)->value('Kode_Karyawan');
    }

    private function layanan(): PaKategoriTidakDiikutkanService
    {
        return app(PaKategoriTidakDiikutkanService::class);
    }
}
