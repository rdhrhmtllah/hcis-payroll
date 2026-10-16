<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Balancing;

use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingKalkulator as K;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingStatus as S;
use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use Tests\TestCase;

/**
 * Matematika Balancing, dipaku angka per angka. Setiap harapan dihitung tangan di komentar.
 */
class BalancingKalkulatorTest extends TestCase
{
    private function butir(array $b): \Illuminate\Support\Collection
    {
        return collect(array_map(fn ($x) => (object) [
            'Skor' => $x[0], 'Skor_Maksimal' => 100, 'PA_Competency_Id' => $x[1], 'Bobot_Sumber' => $x[2] ?? null,
        ], $b));
    }

    public function test_porsi_tanpa_bobot_butir_menurut_jumlah_butir(): void
    {
        // 3 butir: dua kompetensi 1, satu kompetensi 2 → 2/3 dan 1/3.
        $p = K::porsiPenilai($this->butir([[70, 1], [80, 1], [90, 2]]));
        $this->assertEqualsWithDelta(2 / 3, $p[1], 1e-9);
        $this->assertEqualsWithDelta(1 / 3, $p[2], 1e-9);
    }

    public function test_porsi_dengan_bobot_butir_dan_butir_tanpa_kompetensi(): void
    {
        // Bobot 2, 1, 1; butir ketiga tanpa kompetensi tetap masuk penyebut → 2/4 dan 1/4.
        $p = K::porsiPenilai($this->butir([[70, 1, 2], [80, 2, 1], [90, null, 1]]));
        $this->assertEqualsWithDelta(0.5, $p[1], 1e-9);
        $this->assertEqualsWithDelta(0.25, $p[2], 1e-9);
        $this->assertArrayNotHasKey(0, $p);
    }

    public function test_bobot_kompetensi_lintas_kategori_dan_penilai(): void
    {
        // ATASAN 60%: satu penilai, porsi k1 0,5 k2 0,5.
        // REKAN 40%: dua penilai bobot 3 dan 1 (dinormalkan 0,75/0,25), porsi k1 1,0 / 0,5.
        // k1 = 0,6×0,5 + 0,4×(0,75×1 + 0,25×0,5) = 0,3 + 0,4×0,875 = 0,65
        // k2 = 0,6×0,5 + 0,4×(0,25×0,5)           = 0,3 + 0,05       = 0,35
        $b = K::bobotKompetensi([
            ['bobot_terpakai' => 60.0, 'penilai' => [['bobot' => 100.0, 'porsi' => [1 => 0.5, 2 => 0.5]]]],
            ['bobot_terpakai' => 40.0, 'penilai' => [
                ['bobot' => 3.0, 'porsi' => [1 => 1.0]],
                ['bobot' => 1.0, 'porsi' => [1 => 0.5, 2 => 0.5]],
            ]],
        ]);
        $this->assertEqualsWithDelta(0.65, $b[1], 1e-9);
        $this->assertEqualsWithDelta(0.35, $b[2], 1e-9);
    }

    public function test_bobot_penilai_nol_semua_dirata_rata_biasa(): void
    {
        // Sama dengan nilaiSatuKategori: total bobot ≤ 0 → rata-rata sederhana.
        $b = K::bobotKompetensi([
            ['bobot_terpakai' => 100.0, 'penilai' => [
                ['bobot' => 0.0, 'porsi' => [1 => 1.0]],
                ['bobot' => 0.0, 'porsi' => [2 => 1.0]],
            ]],
        ]);
        $this->assertEqualsWithDelta(0.5, $b[1], 1e-9);
        $this->assertEqualsWithDelta(0.5, $b[2], 1e-9);
    }

    public function test_nilai_kompetensi_selisih_berbobot_dari_mesin(): void
    {
        // 77,5 + 0,5×(80 − 70) + 0,5×(85 − 85) = 82,5
        $this->assertSame(82.5, K::nilaiKompetensi(77.5, [1 => 0.5, 2 => 0.5], [1 => 70.0, 2 => 85.0], [1 => 80.0, 2 => 85.0]));
    }

    public function test_tanpa_nilai_berlaku_sama_dengan_mesin(): void
    {
        $this->assertSame(77.46, K::nilaiKompetensi(77.4567, [1 => 0.5], [1 => 70.0], []));
    }

    public function test_kompetensi_tanpa_bobot_atau_tanpa_mesin_diabaikan(): void
    {
        // Kompetensi 3 tidak punya bobot; kompetensi 4 tidak ada di mesin. Tidak ada yang digeser.
        $this->assertSame(70.0, K::nilaiKompetensi(70.0, [1 => 1.0], [1 => 70.0, 3 => 60.0], [3 => 90.0, 4 => 90.0]));
    }

    public function test_nilai_kompetensi_dibatasi_0_sampai_100(): void
    {
        $this->assertSame(100.0, K::nilaiKompetensi(95.0, [1 => 1.0], [1 => 90.0], [1 => 100.0]) + 0.0);
        $this->assertSame(0.0, K::nilaiKompetensi(2.0, [1 => 1.0], [1 => 10.0], [1 => 0.0]));
    }

    public function test_nilai_akhir_sama_dengan_rumus_hitung(): void
    {
        // 90 × 50% + 82,5 × 50% = 86,25
        $this->assertSame(86.25, K::nilaiAkhir(90.0, 82.5, 50.0, 50.0));
    }

    public function test_sebab(): void
    {
        $this->assertNull(K::sebab(80.0, 80.0, 'B', 'B', 'B'));
        $this->assertSame(S::SEBAB_KURVA, K::sebab(80.0, 80.0, 'B', 'B_PLUS', 'B_PLUS'));
        $this->assertSame(S::SEBAB_BALANCING, K::sebab(80.0, 81.0, 'B', 'B', 'B'));
        $this->assertSame(S::SEBAB_BALANCING, K::sebab(80.0, 85.0, 'B', 'B', 'B_PLUS'));
        $this->assertSame(S::SEBAB_KURVA, K::sebab(80.0, 81.0, 'B', 'B_PLUS', 'B_PLUS'));
        $this->assertSame(S::SEBAB_KEDUANYA, K::sebab(80.0, 90.0, 'B', 'B_PLUS', 'A'));
    }

    public function test_gerbang_balancing_hanya_saat_closed(): void
    {
        $this->assertSame(['CLOSED'], app(PaCycleGate::class)->statusUntukOperasi(PaCycleGate::BALANCING));
    }
}
