<?php

namespace App\Helpers;

class KpiFormulaHelper
{
    /**
     * Kata yang boleh muncul di sebuah rumus — PERSIS yang diterjemahkan
     * preprocess() menjadi PHP, tidak lebih. Menambah entri di sini tanpa
     * menambahkannya di preprocess() membuat kata itu lolos ke eval() sebagai
     * konstanta atau nama fungsi PHP apa adanya.
     */
    private const FUNGSI_DIIZINKAN = ['MIN', 'MAX', 'ABS', 'ROUND', 'CEIL', 'FLOOR', 'SQRT', 'POW', 'IF'];

    /**
     * Karakter yang boleh tersisa setelah nama fungsi yang diizinkan dibuang.
     *
     * `e` dan `E` ada di sini BUKAN karena longgar melainkan karena wajib:
     * substitusi memakai (string) $actual, dan PHP merender pecahan kecil atau
     * besar dalam notasi ilmiah — (string) 0.00001 menghasilkan '1.0E-5'.
     * Daftar-putih yang lupa mengizinkannya akan diam-diam menolkan justru
     * nilai-nilai terkecil. Huruf `e` sendirian tidak membentuk nama fungsi
     * mana pun, dan tanpa `$` tidak ada variabel yang bisa dirujuk.
     */
    private const KARAKTER_DIIZINKAN = '/[^0-9.eE\s+\-*\/(),<>=!]/';

    /**
     * Apakah ekspresi (yang variabelnya SUDAH disubstitusi) aman diserahkan ke
     * eval()?
     */
    public static function bentukAman(string $ekspresi): bool
    {
        $sisa = preg_replace(
            '/\b('.implode('|', self::FUNGSI_DIIZINKAN).')\b/i',
            '',
            $ekspresi,
        );

        if ($sisa === null) {
            return false;   // preg gagal (mis. rumus raksasa) → jangan dieksekusi
        }

        return preg_match(self::KARAKTER_DIIZINKAN, $sisa) === 0;
    }

    /**
     * Preprocess formula expression:
     * - Konversi IF(cond, val_true, val_false) → PHP ternary (cond ? val_true : val_false)
     * - Konversi fungsi MIN, MAX, ABS, ROUND, CEIL, FLOOR ke lowercase PHP
     */
    public static function preprocess(string $expression): string
    {
        // Langkah 1: Fungsi matematika → PHP built-in (HARUS sebelum IF agar argumen IF bersih)
        $expression = preg_replace('/\bMIN\s*\(/i', 'min(', $expression);
        $expression = preg_replace('/\bMAX\s*\(/i', 'max(', $expression);
        $expression = preg_replace('/\bABS\s*\(/i', 'abs(', $expression);
        $expression = preg_replace('/\bROUND\s*\(/i', 'round(', $expression);
        $expression = preg_replace('/\bCEIL\s*\(/i', 'ceil(', $expression);
        $expression = preg_replace('/\bFLOOR\s*\(/i', 'floor(', $expression);
        $expression = preg_replace('/\bSQRT\s*\(/i', 'sqrt(', $expression);
        $expression = preg_replace('/\bPOW\s*\(/i', 'pow(', $expression);

        // Langkah 2: IF(cond, a, b) → PHP anonymous function call
        // Pendekatan lambda menghindari keterbatasan regex [^()]* yang gagal pada argumen
        // yang mengandung pemanggilan fungsi seperti IF(..., MAX(0, x), ...).
        // PHP native parser yang mengurus pencocokan kurung dan evaluasi argumen.
        $expression = preg_replace(
            '/\bIF\s*\(/i',
            '(function($__c,$__a,$__b){return $__c?$__a:$__b;})(', $expression
        );

        return $expression;
    }

    /**
     * Evaluasi formula KPI lengkap.
     *
     * Catatan penting untuk kasus "Semakin Kecil Semakin Baik" dengan target = 0:
     * - Gunakan formula: IF([actualKpiEvo]==0,1,0)
     * - Atau: IF([actualKpiEvo]<=0,1,MAX(0,1-[actualKpiEvo]/baseline))
     * - Target 0 diteruskan apa adanya ke formula; IF akan menangani kasus ini.
     *
     * @param  float  $actual  Nilai aktual realisasi
     * @param  float  $target  Nilai target (boleh 0 untuk "lower is better")
     * @param  float  $weight  Bobot parameter (%)
     * @param  string|null  $formula  Ekspresi formula raw, mis: [actualKpiEvo]/[targetKpiEvo]
     * @return array{achievement: float, score: float}
     */
    public static function evaluate(
        float $actual,
        float $target,
        float $weight,
        ?string $formula
    ): array {
        $ratio = 0.0;

        if (is_string($formula) && trim($formula) !== '') {
            // Substitusi variabel — target digunakan apa adanya, termasuk 0
            $expression = str_replace(
                ['[actualKpiEvo]', '[targetKpiEvo]'],
                [(string) $actual, (string) $target],
                $formula
            );

            /*
             * DAFTAR-PUTIH SEBELUM eval(). Diperiksa pada ekspresi yang variabelnya
             * SUDAH disubstitusi, jadi yang tersisa untuk dinilai memang persis apa
             * yang akan dieksekusi.
             *
             * Sebelum baris ini ada, isi kolom Rumus masuk ke eval() apa adanya:
             * preprocess() hanya menerjemahkan MIN/MAX/IF menjadi PHP, ia tidak
             * menyaring apa pun. Artinya izin "boleh menyunting formula" diam-diam
             * juga berarti "boleh menjalankan PHP apa pun sebagai pengguna web" —
             * jauh melampaui yang dijanjikan izin itu.
             *
             * Diukur pada data sekarang: ketiga rumus tersimpan lolos, dan satu-
             * satunya kata yang dipakai di antaranya adalah IF.
             */
            if (static::bentukAman($expression)) {
                $expression = static::preprocess($expression);

                try {
                    $evaluated = eval('return '.$expression.';');
                    if (is_numeric($evaluated) && is_finite((float) $evaluated)) {
                        $ratio = (float) $evaluated;
                    }
                } catch (\Throwable $e) {
                    $ratio = 0.0;
                }
            }
            // Ditolak daftar-putih: $ratio tetap 0.0, sama seperti formula yang
            // melempar saat dihitung. Perilaku bagi rumus rusak tidak berubah.
        } elseif ($target > 0) {
            // Fallback tanpa formula: rasio sederhana
            $ratio = $actual / $target;
        }

        return [
            'achievement' => round($ratio * 100, 2),
            'score' => round($weight * $ratio, 2),
        ];
    }

    /**
     * Format formula raw menjadi teks yang mudah dibaca manusia.
     * Contoh: [actualKpiEvo]/[targetKpiEvo] → Actual / Target
     */
    public static function toDisplayText(string $rawFormula): string
    {
        $display = $rawFormula;
        $display = str_replace('[actualKpiEvo]', 'Actual', $display);
        $display = str_replace('[targetKpiEvo]', 'Target', $display);
        $display = str_replace('*', '×', $display);
        $display = str_replace('/', '÷', $display);
        $display = str_replace('==', '=', $display);

        return $display;
    }
}
