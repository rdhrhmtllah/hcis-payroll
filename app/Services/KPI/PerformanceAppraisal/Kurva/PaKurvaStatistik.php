<?php

namespace App\Services\KPI\PerformanceAppraisal\Kurva;

use RuntimeException;

/**
 * Matematika kurva grade — MURNI, tanpa basis data, supaya bisa dibuktikan terhadap Excel HC.
 *
 * Grade = pita nilai absolut yang diturunkan dari z: batas bawah grade = μ + z·σ. Tiap grade
 * dalam isi awal (G8) selebar satu σ dan μ adalah batas bawah B. σ SAMPEL (n−1), setara
 * `STDEV` Excel — memakai σ populasi menggeser setiap batas dan grade sebagian orang.
 */
final class PaKurvaStatistik
{
    public const ATAS = 999.99;

    /**
     * @param  list<float|int>  $nilai
     * @return array{n:int, rata:?float, sd:?float}
     */
    public static function ringkas(array $nilai): array
    {
        $n = count($nilai);

        if ($n === 0) {
            return ['n' => 0, 'rata' => null, 'sd' => null];
        }

        $rata = array_sum($nilai) / $n;

        if ($n < 2) {
            return ['n' => $n, 'rata' => $rata, 'sd' => null];
        }

        $kuadrat = 0.0;

        foreach ($nilai as $v) {
            $kuadrat += ($v - $rata) ** 2;
        }

        return ['n' => $n, 'rata' => $rata, 'sd' => sqrt($kuadrat / ($n - 1))];
    }

    /**
     * @param  list<array{kode:string, nama:string, batas_z:?float, urutan:int}>  $grade
     * @return list<array{kode:string, nama:string, batas_z:?float, nilai_dari:float, nilai_sampai:float, urutan:int}>
     */
    public static function pita(array $grade, ?float $rata, ?float $sd): array
    {
        self::pastikanBatasZ($grade);

        // z turun; grade tanpa batas (terendah) paling akhir.
        usort($grade, fn ($a, $b) => ($b['batas_z'] ?? -INF) <=> ($a['batas_z'] ?? -INF));

        $datar = $rata === null || $sd === null || $sd <= 0.0;
        $hasil = [];
        $atas = self::ATAS;

        foreach ($grade as $i => $g) {
            if ($datar) {
                /* Tanpa sebaran, satu-satunya jawaban jujur adalah "semua di rata-rata":
                   grade yang rentang z-nya memuat 0 menerima seluruh nilai. */
                $zAtas = $i === 0 ? INF : (float) $grade[$i - 1]['batas_z'];
                $memuatNol = ($g['batas_z'] === null || $g['batas_z'] <= 0.0) && $zAtas > 0.0;
                $dari = 0.0;
                $sampai = $memuatNol ? self::ATAS : 0.0;
            } else {
                $dari = $g['batas_z'] === null ? 0.0 : max(0.0, round($rata + $g['batas_z'] * $sd, 4));
                $sampai = $atas;
                $atas = $dari;
            }

            $hasil[] = [
                'kode' => $g['kode'],
                'nama' => $g['nama'],
                'batas_z' => $g['batas_z'],
                'nilai_dari' => $dari,
                'nilai_sampai' => max($dari, $sampai),
                'urutan' => $i + 1,
            ];
        }

        return $hasil;
    }

    /** @param list<array{kode:string, nilai_dari:float, nilai_sampai:float}> $pita */
    public static function gradeUntuk(array $pita, float $nilai): ?string
    {
        foreach ($pita as $p) {
            if ($p['nilai_dari'] <= $nilai && $nilai < $p['nilai_sampai']) {
                return $p['kode'];
            }
        }

        return null;
    }

    /**
     * Proporsi yang DIHARAPKAN kurva normal baku untuk tiap grade, dalam persen (0–100).
     *
     * Pada siklus KURVA inilah pembanding sebaran grade — bukan `Target_Persen` master pita,
     * yang milik metode PITA. Dengan G8: A 2,28 · B_PLUS 13,59 · B 34,13 · C_PLUS 34,13 ·
     * C 13,59 · D 2,28.
     *
     * @param  list<array{kode:string, batas_z:?float}>  $grade
     * @return array<string, float>
     */
    public static function proporsiTeoretis(array $grade): array
    {
        usort($grade, fn ($a, $b) => ($b['batas_z'] ?? -INF) <=> ($a['batas_z'] ?? -INF));
        $hasil = [];
        $atas = 1.0;

        foreach ($grade as $g) {
            $bawah = $g['batas_z'] === null ? 0.0 : self::phi((float) $g['batas_z']);
            $hasil[$g['kode']] = round(($atas - $bawah) * 100, 2);
            $atas = $bawah;
        }

        return $hasil;
    }

    /** Fungsi sebaran kumulatif normal baku — Abramowitz & Stegun 7.1.26, galat < 1,5·10⁻⁷. */
    public static function phi(float $z): float
    {
        $x = abs($z) / M_SQRT2;
        $t = 1.0 / (1.0 + 0.3275911 * $x);
        $erf = 1.0 - (((((1.061405429 * $t - 1.453152027) * $t) + 1.421413741) * $t - 0.284496736) * $t + 0.254829592) * $t * exp(-$x * $x);

        return $z >= 0 ? 0.5 * (1.0 + $erf) : 0.5 * (1.0 - $erf);
    }

    /**
     * @param  list<array{kode:string, batas_z:?float}>  $grade
     * @param  bool  $urutTertinggiDulu  true bila `$grade` diurutkan dari grade TERTINGGI (menurut
     *                                   rentang nilai pitanya) ke terendah. Hanya dengan urutan itu
     *                                   arah batas z bisa diperiksa.
     */
    public static function pastikanBatasZ(array $grade, bool $urutTertinggiDulu = false): void
    {
        $kosong = array_filter($grade, fn ($g) => $g['batas_z'] === null);
        $isi = array_map(fn ($g) => (string) $g['batas_z'], array_filter($grade, fn ($g) => $g['batas_z'] !== null));

        if (count($kosong) !== 1) {
            throw new RuntimeException(
                'Batas z grade harus diisi untuk setiap grade kecuali tepat SATU grade terendah. '
                .'Sekarang ada '.count($kosong).' grade tanpa batas z.'
            );
        }

        if (count($isi) !== count(array_unique($isi))) {
            throw new RuntimeException('Batas z grade tidak boleh kembar: dua grade dengan batas sama membuat salah satunya tidak pernah dipakai.');
        }

        if (! $urutTertinggiDulu) {
            return;
        }

        /* ARAH batas z wajib searah urutan grade. Kurva mengurutkan grade semata dari z-nya,
           jadi set "A tanpa z, D = -3" lolos pemeriksaan di atas dan menjadikan A grade
           penampung TERENDAH — orang dengan nilai paling rendah mendapat A. */
        $terakhir = end($grade);

        if ($terakhir['batas_z'] !== null) {
            throw new RuntimeException(sprintf(
                'Grade tanpa batas z harus grade TERENDAH (%s), karena ia menampung semua nilai di bawah batas grade lain.',
                $terakhir['kode'],
            ));
        }

        $sebelumnya = null;

        foreach (array_slice($grade, 0, -1) as $g) {
            if ($sebelumnya !== null && ! ($g['batas_z'] < $sebelumnya['batas_z'])) {
                throw new RuntimeException(sprintf(
                    'Batas z harus menurun dari grade tertinggi ke terendah: %s (z %s) berada di bawah %s (z %s), '
                    .'jadi batas z-nya wajib lebih kecil.',
                    $g['kode'], $g['batas_z'], $sebelumnya['kode'], $sebelumnya['batas_z'],
                ));
            }

            $sebelumnya = $g;
        }
    }
}
