<?php

namespace App\Services\KPI\PerformanceAppraisal\Balancing;

use Illuminate\Support\Collection;

/**
 * Matematika Balancing — murni, tanpa basis data (spec §6).
 *
 * SATU FUNGSI UNTUK TIGA PEMAKAI. Hitung biasa, simulasi, dan hitung ulang Balancing semuanya
 * memanggil kelas ini. Itulah yang menjamin angka simulasi di rapat sama dengan angka yang
 * tersimpan sesudah hitung ulang.
 */
final class PaBalancingKalkulator
{
    /**
     * Porsi tiap kompetensi di nilai SATU penilai, dengan aturan yang sama persis dengan
     * `PaResultCalculationService::nilaiSatuPenilai`: bobot butir (`Bobot_Sumber`) dipakai
     * hanya bila SEMUA butir berbobot dan totalnya > 0; selain itu setiap butir bernilai 1.
     * Butir tanpa kompetensi tetap masuk penyebut, sehingga Σ porsi bisa < 1.
     *
     * @param  Collection<int, object>  $butir  baris berskor: PA_Competency_Id, Bobot_Sumber
     * @return array<int, float>
     */
    public static function porsiPenilai(Collection $butir): array
    {
        if ($butir->isEmpty()) {
            return [];
        }

        $semuaBerbobot = $butir->every(fn ($b) => $b->Bobot_Sumber !== null);
        $total = $semuaBerbobot ? (float) $butir->sum(fn ($b) => (float) $b->Bobot_Sumber) : 0.0;
        $pakaiBobot = $semuaBerbobot && $total > 0;
        $penyebut = $pakaiBobot ? $total : (float) $butir->count();

        $porsi = [];

        foreach ($butir as $b) {
            if ($b->PA_Competency_Id === null) {
                continue;
            }

            $c = (int) $b->PA_Competency_Id;
            $porsi[$c] = ($porsi[$c] ?? 0.0) + ($pakaiBobot ? (float) $b->Bobot_Sumber : 1.0) / $penyebut;
        }

        return $porsi;
    }

    /**
     * Bobot kompetensi = turunan Nilai Kompetensi terhadap skor kompetensi itu (spec §6.1).
     * Penilai dinormalkan seperti `nilaiSatuKategori`: total bobot ≤ 0 → rata-rata sederhana.
     *
     * @param  list<array{bobot_terpakai:float, penilai:list<array{bobot:float, porsi:array<int,float>}>}>  $kategori
     * @return array<int, float>
     */
    public static function bobotKompetensi(array $kategori): array
    {
        $hasil = [];

        foreach ($kategori as $k) {
            $penilai = $k['penilai'];

            if ($penilai === []) {
                continue;
            }

            $total = array_sum(array_map(fn ($p) => (float) $p['bobot'], $penilai));

            foreach ($penilai as $p) {
                $w = $total > 0 ? (float) $p['bobot'] / $total : 1 / count($penilai);

                foreach ($p['porsi'] as $c => $porsi) {
                    $hasil[(int) $c] = ($hasil[(int) $c] ?? 0.0) + ((float) $k['bobot_terpakai'] / 100) * $w * $porsi;
                }
            }
        }

        ksort($hasil);

        return $hasil;
    }

    /**
     * mesin + Σ bobotₖ × (berlakuₖ − mesinₖ), dibatasi 0–100, dibulatkan 2 desimal.
     * Selisih SELALU diukur dari nilai mesin, jadi sesi berurutan tidak menjumlah dua kali.
     *
     * @param  array<int, float>  $bobot
     * @param  array<int, float>  $mesinPerKompetensi
     * @param  array<int, float>  $berlaku
     */
    public static function nilaiKompetensi(float $mesin, array $bobot, array $mesinPerKompetensi, array $berlaku): float
    {
        $geser = 0.0;

        foreach ($berlaku as $c => $nilai) {
            if (! isset($bobot[$c], $mesinPerKompetensi[$c])) {
                continue;
            }

            $geser += $bobot[$c] * ((float) $nilai - (float) $mesinPerKompetensi[$c]);
        }

        return round(max(0.0, min(100.0, $mesin + $geser)), 2);
    }

    public static function nilaiAkhir(float $kpi, float $kompetensi, float $bobotKpi, float $bobotKompetensi): float
    {
        return round($kpi * $bobotKpi / 100 + $kompetensi * $bobotKompetensi / 100, 2);
    }

    public static function berubah(?float $a, ?float $b): bool
    {
        if ($a === null || $b === null) {
            return $a !== $b;
        }

        return abs($a - $b) >= 0.005;
    }

    /**
     * Sebab perubahan grade/final seseorang.
     *
     * `$gradeTengah` = grade Final LAMA menurut kurva BARU. Bila itu sudah sama dengan grade baru,
     * kurvalah yang menggeser; bila sama dengan grade lama, angka orangnya yang menggeser.
     */
    public static function sebab(?float $finalLama, ?float $finalBaru, ?string $gradeLama, ?string $gradeTengah, ?string $gradeBaru): ?string
    {
        $finalBerubah = self::berubah($finalLama, $finalBaru);
        $gradeBerubah = $gradeLama !== $gradeBaru;

        if (! $finalBerubah && ! $gradeBerubah) {
            return null;
        }

        if (! $finalBerubah) {
            return PaBalancingStatus::SEBAB_KURVA;
        }

        if (! $gradeBerubah || $gradeTengah === $gradeLama) {
            return PaBalancingStatus::SEBAB_BALANCING;
        }

        return $gradeTengah === $gradeBaru ? PaBalancingStatus::SEBAB_KURVA : PaBalancingStatus::SEBAB_KEDUANYA;
    }
}
