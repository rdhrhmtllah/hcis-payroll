<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Kurva\PaGrupKurvaMasterService;
use App\Services\KPI\PerformanceAppraisal\PaMasterWriteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class GrupKurvaMasterTest extends TestCase
{
    use DatabaseTransactions;

    private function layanan(): PaGrupKurvaMasterService
    {
        return app(PaGrupKurvaMasterService::class);
    }

    /** @return list<int> */
    private function level(int $jumlah): array
    {
        return DB::table('HRIS_Level')->orderBy('ID_Level')->limit($jumlah)->pluck('ID_Level')->map(fn ($x) => (int) $x)->all();
    }

    public function test_simpan_mengganti_seluruh_konfigurasi(): void
    {
        [$a, $b, $c] = $this->level(3);

        $this->layanan()->simpan([
            ['kode' => 'UJI_STAF', 'nama' => 'Staf', 'level_ids' => [$a, $b]],
            ['kode' => 'UJI_MGR', 'nama' => 'Manajer', 'level_ids' => [$c]],
        ], 9);
        $hasil = $this->layanan()->simpan([['kode' => 'UJI_SEMUA', 'nama' => 'Semua', 'level_ids' => [$a, $b, $c]]], 9);

        $this->assertSame(['UJI_SEMUA'], array_column($hasil['grup'], 'kode'));
        $this->assertSame([$a, $b, $c], array_column($hasil['grup'][0]['level'], 'id'));
        $this->assertSame(0, DB::table('N_PA_Curve_Group')->where('Kode_Grup', 'UJI_STAF')->where('Flag_Aktif', 'Y')->count());
    }

    public function test_level_kembar_kode_kembar_dan_grup_kosong_ditolak(): void
    {
        [$a, $b] = $this->level(2);

        foreach ([
            [['kode' => 'X1', 'nama' => 'x', 'level_ids' => [$a]], ['kode' => 'X2', 'nama' => 'y', 'level_ids' => [$a]]],
            [['kode' => 'X1', 'nama' => 'x', 'level_ids' => [$a]], ['kode' => 'X1', 'nama' => 'y', 'level_ids' => [$b]]],
            [['kode' => 'X1', 'nama' => 'x', 'level_ids' => []]],
            [['kode' => 'X1', 'nama' => 'x', 'level_ids' => [999999]]],
        ] as $salah) {
            try {
                $this->layanan()->simpan($salah, null);
                $this->fail('Seharusnya ditolak: '.json_encode($salah));
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_level_tanpa_grup_dilaporkan(): void
    {
        [$a] = $this->level(1);
        $hasil = $this->layanan()->simpan([['kode' => 'UJI_SATU', 'nama' => 'Satu', 'level_ids' => [$a]]], null);

        $this->assertNotContains($a, array_column($hasil['level_tanpa_grup'], 'id'));
        $this->assertGreaterThan(0, count($hasil['level_tanpa_grup']));
    }

    public function test_batas_z_pita_grade_divalidasi_saat_disimpan(): void
    {
        $b = DB::table('N_PA_Score_Range')->where('Score_Type', 'FINAL')->where('Score_Usage', 'GRADE')
            ->where('Kode_Range', 'B')->where('Flag_Aktif', 'Y')->first();
        $this->assertNotNull($b, 'Grade B master tidak ada.');

        $data = [
            'sumbu' => 'FINAL', 'kegunaan' => 'GRADE', 'kode_range' => 'B', 'nama_range' => $b->Nama_Range,
            'nilai_dari' => (float) $b->Nilai_Dari, 'nilai_sampai' => (float) $b->Nilai_Sampai,
            'target_persen' => $b->Target_Persen, 'urutan_tampil' => $b->Urutan_Tampil,
        ];

        // Batas z sama dengan grade lain → ditolak.
        $this->expectException(RuntimeException::class);
        app(PaMasterWriteService::class)->simpan('pita-nilai', (int) $b->Id_PA_Score_Range, $data + ['batas_z' => 1.0], null);
    }
}
