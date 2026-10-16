<?php

namespace Tests\Unit\KPI\Scoring;

use App\Services\KPI\KpiScoringService;
use Tests\TestCase;

/**
 * Perilaku penjepitan skor KPI.
 *
 * Seluruh tes di berkas ini bekerja pada fungsi murni KpiScoringService
 * (scoreParameter / aggregate / applyCap) sehingga tidak menyentuh database.
 */
class KpiScoringServiceCapTest extends TestCase
{
    private function svc(): KpiScoringService
    {
        return app(KpiScoringService::class);
    }

    private function matikanCapParameter(): void
    {
        config()->set('services.kpi.param_max_achievement_score', null);
        config()->set('services.kpi.param_min_achievement_score', null);
    }

    /**
     * Dengan penjepitan dimatikan, rasio diteruskan apa adanya — perilaku yang
     * berlaku sebelum cap per parameter ada. Bobot 50, target 10, aktual 15 ->
     * rasio 1,5 -> achievement 150, skor 75.
     *
     * Prasyaratnya dinyatakan eksplisit, tidak bersandar pada nilai baku: baku
     * aplikasi sekarang MENJEPIT, dan itu diuji tersendiri di
     * test_baku_sistem_menjepit_pada_125_dan_50().
     */
    public function test_tanpa_penjepitan_parameter_melampaui_target_diteruskan_apa_adanya(): void
    {
        $this->matikanCapParameter();

        $hasil = $this->svc()->scoreParameter(15, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');

        $this->assertSame(150.0, $hasil['achievement']);
        $this->assertSame(75.0, $hasil['score']);
    }

    /**
     * Idem untuk parameter yang jauh di bawah target.
     */
    public function test_tanpa_penjepitan_parameter_jauh_di_bawah_target_diteruskan_apa_adanya(): void
    {
        $this->matikanCapParameter();

        $hasil = $this->svc()->scoreParameter(2, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');

        $this->assertSame(20.0, $hasil['achievement']);
        $this->assertSame(10.0, $hasil['score']);
    }

    /**
     * Garis dasar agregasi: total = (Sigma skor / Sigma bobot) x 100.
     * A 300% (skor 150) + B 20% (skor 10) -> 160%.
     */
    public function test_garis_dasar_agregasi_menjumlahkan_skor_bukan_achievement(): void
    {
        $detail = collect([
            (object) ['Achievement' => 150.0, 'Bobot' => 50.0],
            (object) ['Achievement' => 10.0, 'Bobot' => 50.0],
        ]);

        $total = $this->svc()->aggregate($detail);

        $this->assertSame(160.0, $total['sum_of_scores']);
        $this->assertSame(100.0, $total['sum_of_active_bobot']);
        $this->assertSame(160.0, $total['final_score_percentage']);
    }

    /**
     * Garis dasar cap total: 160% dijepit ke 125 dan meninggalkan jejak audit.
     * Lapisan ini tetap ada sesudah perubahan, hanya tidak akan menggigit lagi.
     */
    public function test_garis_dasar_cap_total_masih_menjepit(): void
    {
        $total = $this->svc()->applyCap([
            'sum_of_scores' => 160.0,
            'sum_of_active_bobot' => 100.0,
            'final_score_percentage' => 160.0,
        ]);

        $this->assertSame(125.0, $total['final_score_percentage']);
        $this->assertSame(160.0, $total['raw_final_score_percentage']);
        $this->assertTrue($total['is_capped']);
        $this->assertSame(160.0, $total['sum_of_scores'], 'Sigma skor faktual tidak boleh ikut dijepit');
    }

    private function nyalakanCapParameter(float $max = 125, float $min = 50): void
    {
        config()->set('services.kpi.param_max_achievement_score', $max);
        config()->set('services.kpi.param_min_achievement_score', $min);
    }

    public function test_parameter_di_atas_batas_dijepit_dan_skornya_ikut_turun(): void
    {
        $this->nyalakanCapParameter();

        $hasil = $this->svc()->scoreParameter(15, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');

        $this->assertSame(125.0, $hasil['achievement']);
        $this->assertSame(62.5, $hasil['score'], 'Skor harus turun dari rasio yang sudah dijepit, bukan rasio mentah');
        $this->assertSame(150.0, $hasil['achievement_raw']);
        $this->assertSame(75.0, $hasil['score_raw']);
        $this->assertTrue($hasil['is_capped']);
    }

    public function test_parameter_di_bawah_batas_dinaikkan_ke_lantai(): void
    {
        $this->nyalakanCapParameter();

        $hasil = $this->svc()->scoreParameter(2, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');

        $this->assertSame(50.0, $hasil['achievement']);
        $this->assertSame(25.0, $hasil['score']);
        $this->assertSame(20.0, $hasil['achievement_raw']);
        $this->assertTrue($hasil['is_capped']);
    }

    public function test_parameter_tepat_di_batas_tidak_dianggap_dijepit(): void
    {
        $this->nyalakanCapParameter();

        $atas = $this->svc()->scoreParameter(12.5, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');
        $bawah = $this->svc()->scoreParameter(5, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');

        $this->assertSame(125.0, $atas['achievement']);
        $this->assertFalse($atas['is_capped']);
        $this->assertSame(50.0, $bawah['achievement']);
        $this->assertFalse($bawah['is_capped']);
    }

    public function test_parameter_di_dalam_rentang_lewat_tanpa_perubahan(): void
    {
        $this->nyalakanCapParameter();

        $hasil = $this->svc()->scoreParameter(9, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');

        $this->assertSame(90.0, $hasil['achievement']);
        $this->assertSame(45.0, $hasil['score']);
        $this->assertFalse($hasil['is_capped']);
    }

    public function test_rumus_bernilai_negatif_dijepit_ke_lantai_bukan_melempar(): void
    {
        $this->nyalakanCapParameter();

        $hasil = $this->svc()->scoreParameter(30, 10, 50, '1-[actualKpiEvo]/[targetKpiEvo]');

        $this->assertSame(50.0, $hasil['achievement']);
        $this->assertSame(25.0, $hasil['score']);
        $this->assertSame(-200.0, $hasil['achievement_raw']);
    }

    public function test_rumus_kosong_menghasilkan_nol_lalu_dijepit_ke_lantai(): void
    {
        $this->nyalakanCapParameter();

        $hasil = $this->svc()->scoreParameter(9, 10, 50, null);

        $this->assertSame(50.0, $hasil['achievement']);
        $this->assertSame(0.0, $hasil['achievement_raw']);
        $this->assertTrue($hasil['is_capped']);
    }

    public function test_batas_mati_membuat_perilaku_identik_dengan_sebelumnya(): void
    {
        $this->matikanCapParameter();

        $hasil = $this->svc()->scoreParameter(15, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');

        $this->assertSame(150.0, $hasil['achievement']);
        $this->assertSame(75.0, $hasil['score']);
        $this->assertFalse($hasil['is_capped']);
    }

    public function test_hanya_satu_sisi_dinyalakan(): void
    {
        config()->set('services.kpi.param_max_achievement_score', 125);
        config()->set('services.kpi.param_min_achievement_score', null);

        $atas = $this->svc()->scoreParameter(15, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');
        $bawah = $this->svc()->scoreParameter(2, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');

        $this->assertSame(125.0, $atas['achievement']);
        $this->assertSame(20.0, $bawah['achievement'], 'Sisi bawah mati, jadi tidak diangkat');
    }

    public function test_agregasi_menghitung_jumlah_mentah_dan_cacah_yang_dijepit(): void
    {
        $detail = collect([
            (object) ['Achievement' => 62.5, 'Achievement_Raw' => 150.0, 'Bobot' => 50.0],
            (object) ['Achievement' => 25.0, 'Achievement_Raw' => 10.0, 'Bobot' => 50.0],
        ]);

        $total = $this->svc()->aggregate($detail);

        $this->assertSame(87.5, $total['sum_of_scores']);
        $this->assertSame(87.5, $total['final_score_percentage']);
        $this->assertSame(160.0, $total['raw_sum_of_scores']);
        $this->assertSame(160.0, $total['raw_final_score_percentage']);
        $this->assertSame(2, $total['capped_details_count']);
    }

    public function test_agregasi_baris_lama_tanpa_kolom_mentah_tidak_dianggap_dijepit(): void
    {
        $detail = collect([
            (object) ['Achievement' => 150.0, 'Achievement_Raw' => null, 'Bobot' => 50.0],
            (object) ['Achievement' => 10.0, 'Achievement_Raw' => null, 'Bobot' => 50.0],
        ]);

        $total = $this->svc()->aggregate($detail);

        $this->assertSame(160.0, $total['sum_of_scores']);
        $this->assertSame(160.0, $total['raw_sum_of_scores'], 'Tanpa kolom mentah, mentah = tersimpan');
        $this->assertSame(0, $total['capped_details_count']);
    }

    /**
     * Kasus acuan dari spec: A 300%, B 20%, bobot 50 : 50.
     * Tanpa penjepitan parameter total 160 -> dijepit total jadi 125.
     * Dengan penjepitan parameter total menjadi 87,5 dan cap total tak menggigit.
     */
    public function test_kasus_acuan_rantai_penuh_menghasilkan_87_5(): void
    {
        $this->nyalakanCapParameter();
        $svc = $this->svc();

        $a = $svc->scoreParameter(30, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');
        $b = $svc->scoreParameter(2, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');

        $detail = collect([
            (object) ['Achievement' => $a['score'], 'Achievement_Raw' => $a['score_raw'], 'Bobot' => 50.0],
            (object) ['Achievement' => $b['score'], 'Achievement_Raw' => $b['score_raw'], 'Bobot' => 50.0],
        ]);

        $total = $svc->applyCap($svc->aggregate($detail));

        $this->assertSame(87.5, $total['final_score_percentage']);
        $this->assertSame(160.0, $total['raw_final_score_percentage']);
        $this->assertTrue($total['is_capped'], 'Ditandai karena ada parameter yang dijepit');
        $this->assertSame(2, $total['capped_details_count']);
    }

    public function test_baku_sistem_menjepit_pada_125_dan_50(): void
    {
        // Sengaja TIDAK menyetel config: menguji nilai baku aplikasi.
        [$max, $min] = $this->svc()->paramCapBounds();

        $this->assertSame(125.0, $max);
        $this->assertSame(50.0, $min);
    }

    public function test_keputusan_pica_tidak_berubah_oleh_penjepitan(): void
    {
        $this->nyalakanCapParameter();
        $ambangPica = 100;

        $jeblok = $this->svc()->scoreParameter(2, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');
        $tembus = $this->svc()->scoreParameter(30, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');

        $this->assertLessThan($ambangPica, $jeblok['achievement'], 'Yang jeblok tetap wajib PICA');
        $this->assertGreaterThanOrEqual($ambangPica, $tembus['achievement'], 'Yang tembus tetap bebas PICA');
    }

    /**
     * Enforcement tidak mengevaluasi rumus: ia menetapkan rasio nol karena
     * karyawannya tidak menyerahkan apa pun. Barisnya terangkat ke lantai cap,
     * tetapi nol yang sebenarnya harus tetap terbaca di kolom mentah.
     */
    public function test_enforcement_rasio_nol_terangkat_ke_lantai_dengan_mentah_nol(): void
    {
        $this->nyalakanCapParameter();

        $hasil = $this->svc()->scoreFromRatio(0.0, 50);

        $this->assertSame(50.0, $hasil['achievement']);
        $this->assertSame(25.0, $hasil['score']);
        $this->assertSame(0.0, $hasil['achievement_raw']);
        $this->assertSame(0.0, $hasil['score_raw']);
        $this->assertTrue($hasil['is_capped']);
    }

    /**
     * `KPI_PARAM_MAX_ACHIEVEMENT_SCORE=` di .env membuat env() memulangkan
     * STRING KOSONG — bukan null, bukan nilai baku. Bila string itu dicor ke
     * float begitu saja hasilnya 0,0, dan penjepit pada nol menolkan
     * achievement SETIAP parameter tanpa memberi tanda apa pun.
     *
     * Kosong berarti "tidak disetel", dan tidak disetel berarti BAKU — bukan
     * nol, dan bukan pula mati. Mematikan penjepitan adalah pernyataan yang
     * harus disengaja; lihat test_batas_parameter_null_mematikan_penjepitan().
     */
    public function test_batas_parameter_string_kosong_kembali_ke_baku(): void
    {
        config()->set('services.kpi.param_max_achievement_score', '');
        config()->set('services.kpi.param_min_achievement_score', '');

        $this->assertSame([125.0, 50.0], $this->svc()->paramCapBounds());

        // Rasio 3 -> achievement 300, dijepit ke 125. Kalau plafonnya terbaca
        // sebagai 0, angka ini jadi 50 (terjepit ke nol lalu diangkat lantai).
        $hasil = $this->svc()->scoreParameter(30, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');

        $this->assertSame(125.0, $hasil['achievement']);
        $this->assertSame(300.0, $hasil['achievement_raw']);
    }

    public function test_batas_parameter_bukan_angka_kembali_ke_baku(): void
    {
        config()->set('services.kpi.param_max_achievement_score', 'seratus dua puluh lima');
        config()->set('services.kpi.param_min_achievement_score', '  ');

        $this->assertSame([125.0, 50.0], $this->svc()->paramCapBounds());
    }

    /**
     * Satu-satunya jalan mematikan penjepitan: null yang disengaja
     * (`KPI_PARAM_MAX_ACHIEVEMENT_SCORE=null` di .env).
     */
    public function test_batas_parameter_null_mematikan_penjepitan(): void
    {
        $this->matikanCapParameter();

        $this->assertSame([null, null], $this->svc()->paramCapBounds());

        $hasil = $this->svc()->scoreParameter(30, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');

        $this->assertSame(300.0, $hasil['achievement']);
        $this->assertFalse($hasil['is_capped']);
    }

    public function test_batas_parameter_angka_berbentuk_teks_tetap_dibaca(): void
    {
        // .env selalu menyerahkan STRING; angka yang sah harus tetap berlaku.
        config()->set('services.kpi.param_max_achievement_score', '120');
        config()->set('services.kpi.param_min_achievement_score', '60');

        $this->assertSame([120.0, 60.0], $this->svc()->paramCapBounds());
    }

    /**
     * Batas TOTAL berbeda sikapnya: ia jaring pengaman terakhir dan TIDAK bisa
     * dimatikan. Baik dikosongkan maupun di-null-kan, ia kembali ke baku.
     */
    public function test_batas_total_string_kosong_kembali_ke_baku(): void
    {
        $this->matikanCapParameter();
        config()->set('services.kpi.max_achievement_score', '');
        config()->set('services.kpi.min_achievement_score', null);

        $skor = $this->svc()->applyCap([
            'sum_of_scores' => 300.0,
            'sum_of_active_bobot' => 100.0,
            'final_score_percentage' => 300.0,
        ]);

        $this->assertSame(125.0, $skor['final_score_percentage'], 'Plafon total harus tetap 125, bukan 0');
        $this->assertSame(50.0, $this->svc()->capValue(-10), 'Lantai total harus tetap 50, bukan hilang');
    }

    /**
     * Keputusan PICA menanyakan "apakah target tercapai" — pertanyaan faktual
     * yang tidak boleh dijawab oleh angka yang sudah dipangkas plafon. Selama
     * plafon di atas ambang PICA keduanya sama, tetapi begitu ambangnya
     * dinaikkan melewati plafon, angka terjepit membuat SETIAP parameter
     * tampak gagal selamanya. Angka mentah harus tetap tersedia untuk itu.
     */
    public function test_achievement_mentah_tetap_terbaca_saat_plafon_di_bawah_ambang_pica(): void
    {
        config()->set('services.kpi.param_max_achievement_score', 90);
        config()->set('services.kpi.param_min_achievement_score', 50);
        $ambangPica = 100;

        $tembus = $this->svc()->scoreParameter(13, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');

        $this->assertSame(90.0, $tembus['achievement'], 'Nilai resmi tetap dipangkas plafon');
        $this->assertGreaterThanOrEqual(
            $ambangPica,
            $tembus['achievement_raw'],
            'Angka mentah yang dipakai keputusan PICA harus tetap 130',
        );
    }
}
