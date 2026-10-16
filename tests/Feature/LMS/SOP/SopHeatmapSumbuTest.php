<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopMonitoringAnalyticsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Heatmap penyelesaian dengan sumbu X yang bisa dipilih.
 *
 * YANG DIJAGA: butiran ikut pilihan sumbu, dan angkanya tak melampaui totalnya
 * sendiri.
 *
 * Prioritas menempel pada PAKET, jadi satu penugasan jatuh tepat
 * di satu kolom. APQC, Jenis, dan Entitas menempel pada DOKUMEN — mencapainya
 * dari penugasan menuntut join ke Paket_Item yang MELIPATGANDAKAN barisnya,
 * sebab satu paket memuat banyak dokumen dengan proses berbeda. Kalau
 * butirannya tidak ikut berpindah, satu orang terhitung di beberapa kolom
 * sekaligus dan jumlah kolom melampaui total sebenarnya — salah tanpa satu pun
 * galat yang menandainya.
 */
class SopHeatmapSumbuTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): SopMonitoringAnalyticsService
    {
        return app(SopMonitoringAnalyticsService::class);
    }

    public function test_daftar_sumbu_menyebutkan_butirannya(): void
    {
        /* Layar menampilkan satuan yang berlaku dari sini. Sumbu tanpa butiran
           membuat kartunya diam soal satuan, dan diam adalah cara termudah
           membuat dua angka tak sebanding dibandingkan. */
        $sumbu = SopMonitoringAnalyticsService::sumbuX();

        $this->assertNotEmpty($sumbu);
        foreach ($sumbu as $s) {
            $this->assertArrayHasKey('kunci', $s);
            $this->assertArrayHasKey('label', $s);
            $this->assertContains($s['butiran'], ['penugasan', 'kewajiban'],
                "Sumbu {$s['kunci']} tak menyebut butiran yang dikenali.");
        }
    }

    public function test_sumbu_paket_berbutir_penugasan(): void
    {
        foreach (['prioritas'] as $x) {
            $this->assertSame('penugasan', $this->svc()->heatmap([], $x)['butiran'],
                "Sumbu {$x} menempel pada paket — butirannya harus penugasan.");
        }
    }

    public function test_sumbu_dokumen_berbutir_kewajiban(): void
    {
        /* REGRESI. Inilah sebab heatmap dipecah jadi dua jalur: memakai butiran
           penugasan untuk dimensi dokumen menghasilkan angka yang melipat. */
        foreach (['apqc', 'jenis', 'entitas'] as $x) {
            $this->assertSame('kewajiban', $this->svc()->heatmap([], $x)['butiran'],
                "Sumbu {$x} menempel pada dokumen — butirannya harus kewajiban baca.");
        }
    }

    public function test_sumbu_karangan_jatuh_ke_baku(): void
    {
        /* Nilai dari query string tak pernah dipercaya sebagai nama kolom.
           Sumbu karangan harus mendarat di sumbu baku, bukan menghasilkan SQL
           yang menyebut kolom yang tak ada. Bakunya Prioritas sejak Kategori
           dicabut 28 Agu 2026. */
        $r = $this->svc()->heatmap([], 'kolom-karangan');

        $this->assertSame('prioritas', $r['sumbu_x']);
        $this->assertSame('penugasan', $r['butiran']);

        // 'kategori' kini SUMBU KARANGAN juga — ia harus jatuh ke baku, bukan
        // diam-diam dikenali lagi oleh sisa kode yang belum ikut dicabut.
        $this->assertSame('prioritas', $this->svc()->heatmap([], 'kategori')['sumbu_x']);
    }

    public function test_paginasi_baris_tidak_tumpang_tindih(): void
    {
        $h1 = $this->svc()->heatmap([], 'prioritas', 1, 4);

        if ($h1['total_dept'] <= 4) {
            $this->markTestSkipped('Departemen terlalu sedikit untuk menguji paginasi.');
        }

        $h2 = $this->svc()->heatmap([], 'prioritas', 2, 4);

        $this->assertLessThanOrEqual(4, count($h1['dept']));
        $this->assertSame([], array_intersect($h1['dept'], $h2['dept']),
            'Dua halaman tak boleh memuat departemen yang sama.');
        $this->assertSame(2, $h2['halaman']);
    }

    public function test_halaman_di_luar_jangkauan_dijepit_bukan_kosong(): void
    {
        /* Halaman 999 harus mendarat di halaman terakhir yang ADA. Membalas
           kosong membuat layar menampilkan matriks hampa yang terbaca sebagai
           "tak ada data", padahal datanya ada. */
        $r = $this->svc()->heatmap([], 'prioritas', 999, 4);

        $this->assertSame($r['total_halaman'], $r['halaman']);
    }

    public function test_setiap_baris_punya_sel_sebanyak_kolomnya(): void
    {
        /* Matriks yang barisnya berbeda panjang bukan matriks. ECharts membaca
           indeks kolom dari posisi, jadi baris pendek menggeser seluruh nilai
           di kanannya ke kolom yang salah — tanpa galat. */
        $r = $this->svc()->heatmap([], 'apqc', 1, 6);

        if ($r['kosong']) {
            $this->markTestSkipped('Belum ada data heatmap.');
        }

        foreach ($r['seri'] as $s) {
            $this->assertCount(count($r['kategori']), $s['data'],
                "Baris \"{$s['name']}\" tak sepanjang daftar kolomnya.");
        }
    }

    public function test_persentase_sel_tak_pernah_melampaui_seratus(): void
    {
        foreach (['prioritas', 'apqc'] as $x) {
            $r = $this->svc()->heatmap([], $x, 1, 12);
            foreach ($r['seri'] as $s) {
                foreach ($s['data'] as $sel) {
                    if ($sel['y'] === null) {
                        continue;
                    }
                    $this->assertGreaterThanOrEqual(0, $sel['y']);
                    $this->assertLessThanOrEqual(100, $sel['y'],
                        "Sel {$s['name']} × {$sel['x']} melampaui 100% — butirannya melipat.");
                }
            }
        }
    }
}
