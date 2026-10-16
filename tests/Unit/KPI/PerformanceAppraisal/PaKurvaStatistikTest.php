<?php

namespace Tests\Unit\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaStatistik;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Reproduksi `Dummy Data_ Normal Curve.xlsx` milik HC (47 orang). Kalau angka di sini
 * berubah, grade seluruh siklus berubah — ujinya harus dibaca sebagai kontrak dengan HC.
 */
class PaKurvaStatistikTest extends TestCase
{
    private const NILAI_EXCEL = [
        76.6675, 70.5225, 61.59, 62.0875, 56.1375, 54.62, 59.3175, 81.97, 57.1975, 56.44, 86.7375,
        56.0, 53.41, 56.25, 97.4875, 59.653, 56.2875, 82.855, 87.295, 84.6525, 56.44, 69.1425,
        55.8325, 93.75, 56.2875, 88.0725, 83.7325, 91.41, 115.4175, 87.5925, 56.25, 94.501,
        81.8625, 57.045, 59.1675, 57.045, 76.1825, 53.41, 62.3475, 99.0325, 52.6525, 61.1375,
        90.145, 57.045, 104.82, 55.2275, 88.32,
    ];

    private function grade(array $z): array
    {
        $kode = array_keys($z);

        return array_map(fn ($k, $i) => ['kode' => $k, 'nama' => $k, 'batas_z' => $z[$k], 'urutan' => $i + 1], $kode, array_keys($kode));
    }

    private function sebaran(array $pita): array
    {
        $hitung = array_fill_keys(array_column($pita, 'kode'), 0);

        foreach (self::NILAI_EXCEL as $v) {
            $hitung[PaKurvaStatistik::gradeUntuk($pita, $v)]++;
        }

        return $hitung;
    }

    public function test_rata_dan_simpangan_baku_sampel_sama_dengan_excel(): void
    {
        $r = PaKurvaStatistik::ringkas(self::NILAI_EXCEL);

        $this->assertSame(47, $r['n']);
        $this->assertEqualsWithDelta(71.5116, $r['rata'], 0.0001);
        $this->assertEqualsWithDelta(17.1790, $r['sd'], 0.0001);
    }

    public function test_empat_grade_excel_direproduksi_persis(): void
    {
        $r = PaKurvaStatistik::ringkas(self::NILAI_EXCEL);
        $pita = PaKurvaStatistik::pita($this->grade(['A' => 1.0, 'B+' => 0.0, 'B-' => -1.0, 'C' => null]), $r['rata'], $r['sd']);

        $this->assertSame(['A' => 8, 'B+' => 12, 'B-' => 24, 'C' => 3], $this->sebaran($pita));
    }

    public function test_enam_grade_selebar_satu_sd_keputusan_g8(): void
    {
        $r = PaKurvaStatistik::ringkas(self::NILAI_EXCEL);
        $pita = PaKurvaStatistik::pita(
            $this->grade(['A' => 2.0, 'B_PLUS' => 1.0, 'B' => 0.0, 'C_PLUS' => -1.0, 'C' => -2.0, 'D' => null]),
            $r['rata'], $r['sd'],
        );

        $this->assertSame(['A' => 1, 'B_PLUS' => 7, 'B' => 12, 'C_PLUS' => 24, 'C' => 3, 'D' => 0], $this->sebaran($pita));
        $this->assertEqualsWithDelta(71.5116, $pita[2]['nilai_dari'], 0.0001, 'μ adalah batas bawah B.');
        $this->assertSame(PaKurvaStatistik::ATAS, $pita[0]['nilai_sampai']);
        $this->assertSame(0.0, $pita[5]['nilai_dari']);
        $this->assertSame($pita[0]['nilai_dari'], $pita[1]['nilai_sampai'], 'Pita harus bersambung tanpa lubang.');
    }

    public function test_urutan_masukan_tidak_berpengaruh(): void
    {
        $r = PaKurvaStatistik::ringkas(self::NILAI_EXCEL);
        $acak = $this->grade(['C' => null, 'A' => 1.0, 'B-' => -1.0, 'B+' => 0.0]);

        $this->assertSame(['A', 'B+', 'B-', 'C'], array_column(PaKurvaStatistik::pita($acak, $r['rata'], $r['sd']), 'kode'));
    }

    public function test_sebaran_datar_semua_ke_grade_yang_memuat_z_nol(): void
    {
        $pita = PaKurvaStatistik::pita($this->grade(['A' => 1.0, 'B' => 0.0, 'C' => null]), 80.0, 0.0);

        $this->assertSame('B', PaKurvaStatistik::gradeUntuk($pita, 80.0));
        $this->assertSame('B', PaKurvaStatistik::gradeUntuk($pita, 120.0));

        $satu = PaKurvaStatistik::ringkas([75.0]);
        $this->assertNull($satu['sd']);
        $this->assertSame('B', PaKurvaStatistik::gradeUntuk(PaKurvaStatistik::pita($this->grade(['A' => 1.0, 'B' => 0.0, 'C' => null]), $satu['rata'], $satu['sd']), 75.0));
    }

    public function test_batas_z_harus_tepat_satu_kosong_dan_unik(): void
    {
        foreach ([['A' => 1.0, 'B' => 0.0], ['A' => null, 'B' => null], ['A' => 1.0, 'B' => 1.0, 'C' => null]] as $salah) {
            try {
                PaKurvaStatistik::pastikanBatasZ(array_map(fn ($k) => ['kode' => $k, 'batas_z' => $salah[$k]], array_keys($salah)));
                $this->fail('Seharusnya ditolak: '.json_encode($salah));
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Batas z', $e->getMessage());
            }
        }

        PaKurvaStatistik::pastikanBatasZ([['kode' => 'A', 'batas_z' => 1.0], ['kode' => 'B', 'batas_z' => null]]);
        $this->addToAssertionCount(1);
    }

    public function test_proporsi_teoretis_g8_mengikuti_kurva_normal_baku(): void
    {
        $p = PaKurvaStatistik::proporsiTeoretis($this->grade(['A' => 2.0, 'B_PLUS' => 1.0, 'B' => 0.0, 'C_PLUS' => -1.0, 'C' => -2.0, 'D' => null]));

        $this->assertEqualsWithDelta(2.28, $p['A'], 0.01);
        $this->assertEqualsWithDelta(13.59, $p['B_PLUS'], 0.01);
        $this->assertEqualsWithDelta(34.13, $p['B'], 0.01);
        $this->assertEqualsWithDelta(34.13, $p['C_PLUS'], 0.01);
        $this->assertEqualsWithDelta(13.59, $p['C'], 0.01);
        $this->assertEqualsWithDelta(2.28, $p['D'], 0.01);
        $this->assertEqualsWithDelta(100.0, array_sum($p), 0.05);
        $this->assertEqualsWithDelta(0.5, PaKurvaStatistik::phi(0.0), 1e-7);
    }
}
