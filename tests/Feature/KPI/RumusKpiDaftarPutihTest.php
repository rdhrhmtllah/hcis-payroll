<?php

namespace Tests\Feature\KPI;

use App\Helpers\KpiFormulaHelper;
use Tests\TestCase;

/**
 * Rumus KPI disaring sebelum sampai ke eval().
 *
 * ── Apa yang diperbaiki ──────────────────────────────────────────────────────
 *
 * KpiFormulaHelper::evaluate() memanggil eval() atas isi kolom Rumus. Yang
 * berjalan di antaranya cuma preprocess(), dan preprocess() BUKAN penyaring —
 * ia hanya menerjemahkan MIN/MAX/IF menjadi PHP. Jalur tulisnya pun hanya
 * memvalidasi 'Rumus' => 'required|string'.
 *
 * Akibatnya izin "boleh menyunting formula" (halaman di balik
 * check.jabatan:MasterFormula) diam-diam juga berarti "boleh menjalankan PHP
 * apa pun sebagai pengguna web" — jauh melampaui yang dijanjikan izin itu, dan
 * berlaku pula bagi baris yang masuk lewat impor atau langsung ke basis data.
 *
 * ── Kenapa perbaikannya aman ─────────────────────────────────────────────────
 *
 * Diukur pada data sekarang: KPI_Master_Formula berisi tiga rumus, ketiganya
 * lolos daftar-putih ini, dan satu-satunya kata yang muncul di antaranya adalah
 * IF. Rumus yang ditolak menghasilkan rasio 0.0 — persis nasib rumus yang
 * melempar saat dihitung, jadi perilaku bagi rumus rusak tidak berubah.
 *
 * Muatan penolakan di bawah sengaja berbahaya-bentuk tapi tidak berefek: kalau
 * daftar-putihnya jebol, yang terjadi adalah angka yang bisa ditegaskan, bukan
 * kerusakan.
 */
class RumusKpiDaftarPutihTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function rumusSah(): array
    {
        return [
            'rasio sederhana' => ['[actualKpiEvo]/[targetKpiEvo]'],
            'selisih ternormalisasi' => ['(1+([targetKpiEvo]-[actualKpiEvo])/[targetKpiEvo])'],
            'IF dengan kesetaraan' => ['IF([actualKpiEvo]==0,1,0)'],
            'IF bersarang dengan MAX' => ['IF([actualKpiEvo]<=0,1,MAX(0,1-[actualKpiEvo]/100))'],
            'fungsi huruf kecil' => ['min(1,[actualKpiEvo]/[targetKpiEvo])'],
            'ROUND dan ABS' => ['ROUND(ABS([actualKpiEvo]-[targetKpiEvo]),2)'],
            'perbandingan tidak sama' => ['IF([targetKpiEvo]!=0,[actualKpiEvo]/[targetKpiEvo],0)'],
            'lebih besar sama dengan' => ['IF([actualKpiEvo]>=[targetKpiEvo],1,0)'],
        ];
    }

    /**
     * @dataProvider rumusSah
     */
    public function test_rumus_yang_memang_dipakai_tetap_lolos(string $rumus): void
    {
        $tersubstitusi = str_replace(
            ['[actualKpiEvo]', '[targetKpiEvo]'],
            ['80', '100'],
            $rumus,
        );

        $this->assertTrue(
            KpiFormulaHelper::bentukAman($tersubstitusi),
            "Rumus sah ditolak: {$rumus}. Daftar-putih yang terlalu ketat menolkan nilai "
            .'KPI orang tanpa satu pun pesan galat.',
        );
    }

    public function test_notasi_ilmiah_lolos_karena_php_yang_menghasilkannya(): void
    {
        /*
         * JEBAKAN YANG NYARIS TERLEWAT. Substitusinya memakai (string) $actual,
         * dan PHP merender pecahan kecil sebagai notasi ilmiah:
         * (string) 0.00001 menghasilkan '1.0E-5'. Daftar-putih yang tidak
         * mengizinkan huruf e akan menolak justru nilai-nilai terkecil — dan
         * menolkannya diam-diam.
         */
        $this->assertSame('1.0E-5', (string) 0.00001, 'Asumsi tentang PHP berubah.');

        $this->assertTrue(KpiFormulaHelper::bentukAman('1.0E-5/100'));
        $this->assertTrue(KpiFormulaHelper::bentukAman('1.0E+20/100'));

        $hasil = KpiFormulaHelper::evaluate(0.00001, 100.0, 10.0, '[actualKpiEvo]/[targetKpiEvo]');
        $this->assertGreaterThan(0.0, $hasil['achievement'] + 1, 'Perhitungan tidak boleh melempar.');
    }

    /** @return array<string, array{0: string}> */
    public static function rumusDitolak(): array
    {
        return [
            'nama fungsi di luar daftar' => ['strlen(12345)'],
            'fungsi waktu' => ['time()'],
            'konstanta PHP' => ['PHP_INT_MAX'],
            'variabel' => ['$x'],
            'variabel berkurawal' => ['${x}'],
            'pernyataan kedua' => ['1;2'],
            'backtick' => ['`12`'],
            'petik ganda' => ['"12"'],
            'petik tunggal' => ["'12'"],
            'kurung siku tersisa' => ['[1][0]'],
            'panah objek' => ['1->x'],
            'kurung kurawal' => ['{1}'],
            'garis miring terbalik' => ['1\\2'],
            'at-sign peredam galat' => ['@1'],
            'pagar komentar' => ['1#2'],
            'garis bawah' => ['1_0'],
            'placeholder yang tidak tersubstitusi' => ['[actualKpiEvo]/2'],
        ];
    }

    /**
     * @dataProvider rumusDitolak
     */
    public function test_bentuk_di_luar_daftar_putih_ditolak(string $rumus): void
    {
        $this->assertFalse(
            KpiFormulaHelper::bentukAman($rumus),
            "Bentuk {$rumus} lolos daftar-putih dan akan sampai ke eval().",
        );
    }

    public function test_bentuk_yang_lolos_saringan_tetapi_gagal_diurai_berakhir_nol(): void
    {
        /*
         * Beberapa bentuk LOLOS daftar-putih karena tersusun dari karakter yang
         * memang sah sendiri-sendiri — `=>` dari `=` dan `>`, `1=2` dari `=` —
         * lalu gugur sebagai ParseError di dalam eval(). Itu memang batas
         * daftar-putih ini: ia menyaring KEMAMPUAN (nama fungsi, variabel,
         * pernyataan kedua), bukan tata bahasa.
         *
         * Yang dijamin: keduanya tidak bisa memanggil apa pun, dan hasilnya nol,
         * bukan lemparan yang naik ke pemanggil. Uji ini menuliskan batas itu
         * supaya pembaca berikutnya tidak menyangkanya kelalaian.
         */
        foreach (['1=>2', '1=2', '(((', '1+'] as $bentuk) {
            $this->assertTrue(
                KpiFormulaHelper::bentukAman($bentuk),
                "{$bentuk} disusun dari karakter yang sah; penolakannya bukan tugas daftar-putih.",
            );

            $hasil = KpiFormulaHelper::evaluate(1.0, 1.0, 10.0, $bentuk);
            $this->assertSame(0.0, $hasil['score'], "{$bentuk} harus berakhir nol, bukan melempar.");
        }
    }

    public function test_rumus_ditolak_menghasilkan_nol_bukan_lemparan(): void
    {
        /*
         * Perilaku bagi rumus yang ditolak WAJIB sama dengan rumus yang melempar
         * saat dihitung: rasio 0.0. Kalau ia melempar, satu baris rumus rusak
         * menjatuhkan seluruh perhitungan KPI satu periode.
         */
        $hasil = KpiFormulaHelper::evaluate(80.0, 100.0, 10.0, 'strlen(12345)');

        $this->assertSame(0.0, $hasil['achievement']);
        $this->assertSame(0.0, $hasil['score']);
    }

    public function test_rumus_sah_tetap_dihitung_seperti_semula(): void
    {
        /*
         * Nilai-nilai ini yang membuktikan daftar-putihnya tidak mengubah
         * perhitungan, cuma menjaga pintunya.
         */
        $rasio = KpiFormulaHelper::evaluate(80.0, 100.0, 10.0, '[actualKpiEvo]/[targetKpiEvo]');
        $this->assertSame(80.0, $rasio['achievement']);
        $this->assertSame(8.0, $rasio['score']);

        $kondisi = KpiFormulaHelper::evaluate(0.0, 0.0, 20.0, 'IF([actualKpiEvo]==0,1,0)');
        $this->assertSame(100.0, $kondisi['achievement'], 'IF() harus tetap diterjemahkan preprocess().');
        $this->assertSame(20.0, $kondisi['score']);
    }

    public function test_daftar_fungsi_dan_terjemahannya_tidak_boleh_berpisah(): void
    {
        /*
         * Menambah nama ke daftar-putih tanpa menambahkannya di preprocess()
         * membuat kata itu lolos ke eval() sebagai nama fungsi PHP apa adanya —
         * daftar-putihnya justru jadi pintu. Keduanya harus bergerak bersama.
         */
        $isi = (string) file_get_contents(base_path('app/Helpers/KpiFormulaHelper.php'));

        $awal = strpos($isi, 'FUNGSI_DIIZINKAN = [');
        $this->assertNotFalse($awal, 'Daftar fungsi yang diizinkan hilang.');

        $baris = substr($isi, $awal, (int) strpos($isi, '];', $awal) - $awal);
        preg_match_all("/'([A-Z]+)'/", $baris, $m);
        $this->assertNotEmpty($m[1], 'Daftar fungsi kosong.');

        foreach ($m[1] as $fungsi) {
            $this->assertMatchesRegularExpression(
                '/\\\\b'.$fungsi.'\\\\s\*\\\\\(/',
                $isi,
                "{$fungsi} ada di daftar-putih tetapi tidak diterjemahkan preprocess(). ".
                'Ia akan sampai ke eval() sebagai nama fungsi PHP apa adanya.',
            );
        }
    }

    public function test_berkas_penjaga_tidak_kosong(): void
    {
        $this->assertGreaterThan(5000, (int) filesize(__FILE__), 'Berkas penjaga menyusut drastis.');
    }
}
