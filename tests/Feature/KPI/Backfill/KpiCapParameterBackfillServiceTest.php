<?php

namespace Tests\Feature\KPI\Backfill;

use App\Services\KPI\KpiCapParameterBackfillService as Svc;
use App\Services\KPI\KpiCapParameterKalkulator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Service backfill cap per parameter: pratinjau, potongan, cadangan, rollback.
 * Memakai header sasaran nyata di DB dev, di dalam transaksi yang di-rollback.
 */
class KpiCapParameterBackfillServiceTest extends TestCase
{
    use DatabaseTransactions;

    private Svc $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(Svc::class);
        if (! $this->svc->tabelCadanganAda()) {
            $this->markTestSkipped('DDL-2026-167 belum dijalankan di database ini.');
        }
    }

    /** Dua header sasaran; diutamakan yang punya detail di luar 50–125. */
    private function duaSasaran(): array
    {
        $ids = DB::table('KPI_Update_Performance as h')
            ->where('h.Flag_Aktif', 'Y')->whereNull('h.Status')
            ->where(fn ($q) => $q->whereNull('h.Metode_Cap')->orWhere('h.Metode_Cap', '<>', 'PARAMETER'))
            ->whereExists(fn ($q) => $q->from('KPI_Update_Performance_Detail as d')->whereColumn('d.Id_Update_Performance', 'h.Id_Update_Performance')
                ->where(fn ($w) => $w->where('d.Score', '>', 125)->orWhere('d.Score', '<', 50)))
            ->orderBy('h.Id_Update_Performance')->limit(2)->pluck('h.Id_Update_Performance')->map(fn ($v) => (int) $v)->all();

        if (count($ids) < 2) {
            $this->markTestSkipped('Butuh minimal dua header sasaran berdetail di luar 50–125 di DB dev.');
        }

        return $ids;
    }

    private function potret(array $ids): array
    {
        return [
            'h' => DB::table('KPI_Update_Performance')->whereIn('Id_Update_Performance', $ids)->orderBy('Id_Update_Performance')->get()->map(fn ($r) => (array) $r)->all(),
            'd' => DB::table('KPI_Update_Performance_Detail')->whereIn('Id_Update_Performance', $ids)->orderBy('Id_Update_Performance_Detail')->get()->map(fn ($r) => (array) $r)->all(),
        ];
    }

    public function test_pratinjau_tidak_menulis_dan_kuerinya_tak_tumbuh_per_header(): void
    {
        $sebelum = DB::table(Svc::TABEL_CADANGAN)->count();
        DB::enableQueryLog();
        $p = $this->svc->pratinjau();
        $jumlahKueri = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($sebelum, DB::table(Svc::TABEL_CADANGAN)->count(), 'Pratinjau menulis cadangan.');
        $this->assertSame(count($this->svc->idSasaran()), $p['header_sasaran']);
        // per 500 header: header + detail; + sasaran + pita + label level. Tidak per header.
        $this->assertLessThan(10 + 4 * (int) ceil(max(1, $p['header_sasaran']) / 500), $jumlahKueri,
            "Pratinjau memakai {$jumlahKueri} kueri untuk {$p['header_sasaran']} header.");
        foreach (['batas', 'per_status', 'detail_berubah', 'header_skor_berubah', 'perpindahan_level', 'contoh'] as $k) {
            $this->assertArrayHasKey($k, $p);
        }
    }

    public function test_potongan_menulis_hasil_kalkulator_dan_mencadangkan_nilai_lama(): void
    {
        $ids = $this->duaSasaran();
        $lama = $this->potret($ids);
        $batch = $this->svc->mulai('jalankan', 'UJI-BF');

        $this->assertSame(2, $this->svc->prosesPotongan($batch, 0, $ids));

        $k = app(KpiCapParameterKalkulator::class);
        $pita = $k->muatPita();
        foreach ($ids as $id) {
            $detail = DB::table('KPI_Update_Performance_Detail as d')
                ->join('KPI_Pengajuan_Det as det', 'det.Id_Pengajuan_Det', '=', 'd.Id_Pengajuan_Det')
                ->join('KPI_Pengajuan_Detail as pd', 'pd.Id_Pengajuan_Detail', '=', 'det.Id_Pengajuan_Detail')
                ->where('d.Id_Update_Performance', $id)
                ->get(['d.Id_Update_Performance_Detail', 'd.Score', 'd.Achievement', 'd.Score_Raw', 'd.Achievement_Raw', 'pd.Bobot']);
            $harap = $k->hitung($detail, $pita);   // idempoten: hitung ulang hasil = hasil
            $h = DB::table('KPI_Update_Performance')->where('Id_Update_Performance', $id)->first();
            $this->assertSame('PARAMETER', $h->Metode_Cap);
            $this->assertEqualsWithDelta($harap['header']['Total_Score'], (float) $h->Total_Score, 0.01);
            $this->assertSame($harap['header']['Id_Performance'], (int) $h->Id_Performance);
            foreach ($detail as $d) {
                $this->assertGreaterThanOrEqual(50, (float) $d->Score);
                $this->assertLessThanOrEqual(125, (float) $d->Score);
            }
        }

        $this->assertSame(2, DB::table(Svc::TABEL_CADANGAN)->where('Batch_Id', $batch)->where('Jenis', 'HEADER')->count());
        $this->assertSame(count($lama['d']), DB::table(Svc::TABEL_CADANGAN)->where('Batch_Id', $batch)->where('Jenis', 'DETAIL')->count());
    }

    public function test_potongan_terkirim_ulang_tidak_dihitung_dua_kali(): void
    {
        $ids = $this->duaSasaran();
        $batch = $this->svc->mulai('jalankan', 'UJI-BF');
        $this->svc->setelTotal($batch, 10);
        $this->svc->prosesPotongan($batch, 0, $ids);
        $this->assertSame(0, $this->svc->prosesPotongan($batch, 0, $ids));

        $this->assertSame(2, (int) DB::table('N_HRIS_Async_Tasks')->where('Export_Id', $batch)->value('Processed_Records'));
        $this->assertSame(2, DB::table(Svc::TABEL_CADANGAN)->where('Batch_Id', $batch)->where('Jenis', 'HEADER')->count());
    }

    public function test_header_yang_sudah_parameter_dilewati_tetapi_batch_tetap_selesai(): void
    {
        $ids = $this->duaSasaran();
        DB::table('KPI_Update_Performance')->where('Id_Update_Performance', $ids[1])->update(['Metode_Cap' => 'PARAMETER']);
        $batch = $this->svc->mulai('jalankan', 'UJI-BF');
        $this->svc->setelTotal($batch, 2);

        $this->assertSame(1, $this->svc->prosesPotongan($batch, 0, $ids));
        $this->assertSame('ready', DB::table('N_HRIS_Async_Tasks')->where('Export_Id', $batch)->value('Status'));
    }

    public function test_rollback_memulihkan_identik_dan_kali_kedua_tanpa_efek(): void
    {
        $ids = $this->duaSasaran();
        $lama = $this->potret($ids);
        $batch = $this->svc->mulai('jalankan', 'UJI-BF');
        $this->svc->prosesPotongan($batch, 0, $ids);
        $this->assertNotEquals($lama, $this->potret($ids), 'Prasyarat: backfill harus mengubah sesuatu.');

        $rb = $this->svc->mulai('rollback', 'UJI-BF', $batch);
        $this->assertSame(2, $this->svc->pulihkanPotongan($rb, $batch, 0, $ids));

        $kini = $this->potret($ids);
        foreach (['h', 'd'] as $t) {
            foreach ($lama[$t] as $i => $baris) {
                foreach ($baris as $kolom => $nilai) {
                    $sekarang = $kini[$t][$i][$kolom];
                    if (is_numeric($nilai) && is_numeric($sekarang)) {
                        $this->assertEqualsWithDelta((float) $nilai, (float) $sekarang, 1e-9, "{$t}.{$kolom} tidak pulih.");
                    } else {
                        $this->assertSame($nilai, $sekarang, "{$t}.{$kolom} tidak pulih.");
                    }
                }
            }
        }

        $rb2 = $this->svc->mulai('rollback', 'UJI-BF', $batch);
        $this->assertSame([], $this->svc->idUntukBatch($rb2), 'Rollback kedua masih menemukan header untuk dipulihkan.');
    }

    /**
     * Keputusan user 12 Okt 2026 (sesudah pratinjau dev): skor & level hanya
     * dihitung ulang bila cap per parameter memang mengubah sesuatu. Header yang
     * semua parameternya di dalam 50–125 hanya diberi label — data lama yang
     * menyimpan Achievement dengan rumus berbeda tak boleh ikut berubah totalnya.
     */
    public function test_header_tanpa_parameter_di_luar_rentang_hanya_diberi_label(): void
    {
        $id = DB::table('KPI_Update_Performance as h')
            ->where('h.Flag_Aktif', 'Y')->whereNull('h.Status')->whereNull('h.Metode_Cap')
            ->whereExists(fn ($q) => $q->from('KPI_Update_Performance_Detail as d')->whereColumn('d.Id_Update_Performance', 'h.Id_Update_Performance'))
            ->whereNotExists(fn ($q) => $q->from('KPI_Update_Performance_Detail as d')->whereColumn('d.Id_Update_Performance', 'h.Id_Update_Performance')
                ->where(fn ($w) => $w->where('d.Score', '>', 125)->orWhere('d.Score', '<', 50)))
            ->orderBy('h.Id_Update_Performance')->value('h.Id_Update_Performance');
        if (! $id) {
            $this->markTestSkipped('Tak ada header sasaran yang semua parameternya di dalam 50–125.');
        }
        $lama = $this->potret([(int) $id]);
        $batch = $this->svc->mulai('jalankan', 'UJI-BF');

        $this->svc->prosesPotongan($batch, 0, [(int) $id]);

        $kini = $this->potret([(int) $id]);
        $this->assertSame('PARAMETER', $kini['h'][0]['Metode_Cap']);
        foreach (['Total_Score', 'Total_Score_Achievement', 'Id_Performance', 'Flag_Capped', 'Total_Score_Raw'] as $kolom) {
            $this->assertSame($lama['h'][0][$kolom], $kini['h'][0][$kolom], "Header tak terdampak cap ikut berubah di {$kolom}.");
        }
        $this->assertSame($lama['d'], $kini['d'], 'Detail header tak terdampak cap ikut berubah.');
        $this->assertSame(1, DB::table(Svc::TABEL_CADANGAN)->where('Batch_Id', $batch)->where('Jenis', 'HEADER')->count(),
            'Label pun harus bisa di-rollback.');
        $this->assertSame(0, DB::table(Svc::TABEL_CADANGAN)->where('Batch_Id', $batch)->where('Jenis', 'DETAIL')->count());
    }

    public function test_pratinjau_memisahkan_yang_dihitung_ulang_dari_yang_hanya_dilabeli(): void
    {
        $p = $this->svc->pratinjau();

        $this->assertArrayHasKey('header_dihitung_ulang', $p);
        $this->assertArrayHasKey('header_hanya_label', $p);
        $this->assertSame($p['header_sasaran'], $p['header_dihitung_ulang'] + $p['header_hanya_label']);
        $this->assertLessThanOrEqual($p['header_dihitung_ulang'], $p['header_skor_berubah'],
            'Skor berubah pada header yang mestinya hanya dilabeli.');
    }

    /**
     * Diagnosis kolom (12 Okt 2026): data dev menyimpan persen pencapaian kadang di
     * Score, kadang di Achievement (tertukar), kadang tak cocok dengan rumus sama
     * sekali. Aturan final backfill ditetapkan dari angka ini di produksi.
     */
    public function test_pratinjau_mendiagnosis_letak_persen_pencapaian_setiap_baris(): void
    {
        $p = $this->svc->pratinjau();

        $this->assertArrayHasKey('diagnosis_kolom', $p);
        $d = $p['diagnosis_kolom']['detail'];
        foreach (['persen_di_score', 'persen_di_achievement', 'tak_cocok', 'target_nol', 'rumus_galat'] as $k) {
            $this->assertArrayHasKey($k, $d);
        }
        $this->assertSame($p['detail_sasaran'], array_sum($d), 'Setiap baris detail harus masuk tepat satu golongan.');
        $this->assertArrayHasKey('header', $p['diagnosis_kolom']);
        $this->assertArrayHasKey('contoh', $p['diagnosis_kolom']);
    }

    public function test_diagnosis_mengenali_baris_tertukar(): void
    {
        // Baris nyata dari dev: aktual 8, target 12 → 66,67%, tersimpan di
        // Achievement sedangkan Score berisi nilai berbobot 16,67.
        $golongan = $this->svc->golonganKolom((object) [
            'Nilai_Actual' => 8.0, 'Nilai_Target' => 12.0, 'Bobot' => 25.0,
            'Score' => 16.67, 'Achievement' => 66.67, 'Score_Raw' => null, 'Achievement_Raw' => null,
        ], 66.67);

        $this->assertSame('persen_di_achievement', $golongan);
        $this->assertSame('persen_di_score', $this->svc->golonganKolom((object) [
            'Nilai_Target' => 12.0, 'Score' => 66.67, 'Achievement' => 16.67, 'Score_Raw' => null, 'Achievement_Raw' => null,
        ], 66.67));
        $this->assertSame('tak_cocok', $this->svc->golonganKolom((object) [
            'Nilai_Target' => 12.0, 'Score' => 40.0, 'Achievement' => 10.0, 'Score_Raw' => null, 'Achievement_Raw' => null,
        ], 66.67));
        $this->assertSame('target_nol', $this->svc->golonganKolom((object) [
            'Nilai_Target' => 0.0, 'Score' => 0.0, 'Achievement' => 0.0, 'Score_Raw' => null, 'Achievement_Raw' => null,
        ], null));
    }

    public function test_hanya_satu_batch_aktif(): void
    {
        $batch = $this->svc->mulai('jalankan', 'UJI-BF');
        $this->assertSame($batch, $this->svc->batchAktif()->Export_Id);
    }
}
