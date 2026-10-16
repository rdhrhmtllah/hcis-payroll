<?php

namespace Tests\Unit\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaTeksKaya;
use PHPUnit\Framework\TestCase;

/**
 * Penyaring teks kaya jawaban esai. HTML hasilnya digambar `v-html` di layar karyawan dan
 * leader, jadi setiap kasus di sini adalah jalur XSS yang harus tertutup di SERVER.
 */
class PaTeksKayaTest extends TestCase
{
    private PaTeksKaya $t;

    protected function setUp(): void
    {
        parent::setUp();
        $this->t = new PaTeksKaya;
    }

    public function test_tag_format_dasar_bertahan(): void
    {
        $html = '<p>Satu <strong>tebal</strong> <em>miring</em> <u>garis</u> <s>coret</s></p>'
            .'<ol><li>a</li></ol><ul><li>b</li></ul><blockquote>kutip</blockquote>';

        $this->assertSame($html, $this->t->bersihkan($html));
    }

    public function test_skrip_dan_isinya_dibuang(): void
    {
        $this->assertSame('<p>Hai</p>', $this->t->bersihkan('<p>Hai<script>alert(1)</script></p>'));
        $this->assertSame('<p>Hai</p>', $this->t->bersihkan('<p>Hai<style>p{}</style><iframe src="x"></iframe></p>'));
    }

    public function test_semua_atribut_dibuang(): void
    {
        $this->assertSame(
            '<p>klik</p>',
            $this->t->bersihkan('<p onclick="curi()" style="color:red" class="x">klik</p>'),
        );
    }

    public function test_tag_asing_dibuka_isinya_dipertahankan(): void
    {
        $this->assertSame(
            '<p>tautan dan gambar</p>',
            $this->t->bersihkan('<p><a href="javascript:alert(1)">tautan</a> dan <img src=x onerror=alert(1)>gambar</p>'),
        );
        $this->assertSame('<p>judul</p>', $this->t->bersihkan('<p><span><font>judul</font></span></p>'));
    }

    public function test_daftar_quill_2_menjadi_ul_dan_ol_yang_sebenarnya(): void
    {
        $quill = '<ol><li data-list="bullet"><span class="ql-ui" contenteditable="false"></span>a</li>'
            .'<li data-list="bullet"><span class="ql-ui" contenteditable="false"></span>b</li>'
            .'<li data-list="ordered"><span class="ql-ui" contenteditable="false"></span>c</li></ol>';

        $this->assertSame('<ul><li>a</li><li>b</li></ul><ol><li>c</li></ol>', $this->t->bersihkan($quill));
    }

    public function test_komentar_dibuang(): void
    {
        $this->assertSame('<p>a</p>', $this->t->bersihkan('<p>a<!-- <script>x</script> --></p>'));
    }

    public function test_tanpa_teks_berarti_kosong(): void
    {
        foreach (['', '   ', '<p><br></p>', '<p> </p><p><br></p>', '<script>alert(1)</script>', '<p>&nbsp;</p>'] as $kosong) {
            $this->assertSame('', $this->t->bersihkan($kosong), "Seharusnya kosong: {$kosong}");
        }
    }

    public function test_teks_polos_hclearn_di_escape_per_baris(): void
    {
        $this->assertSame(
            '<p>Nilai &lt;b&gt; tinggi &amp; stabil</p><p><br></p><p>baris dua</p>',
            $this->t->dariTeksPolos("Nilai <b> tinggi & stabil\n\nbaris dua"),
        );
    }

    public function test_teks_polos_dari_html(): void
    {
        $this->assertSame(
            "Satu tebal\ndua\na & b",
            $this->t->teksPolos('<p>Satu <strong>tebal</strong></p><ul><li>dua</li></ul><p>a &amp; b</p>'),
        );
    }

    public function test_utf8_tidak_rusak(): void
    {
        $this->assertSame('<p>Kerja sama — baik ✓</p>', $this->t->bersihkan('<p>Kerja sama — baik ✓</p>'));
    }
}
