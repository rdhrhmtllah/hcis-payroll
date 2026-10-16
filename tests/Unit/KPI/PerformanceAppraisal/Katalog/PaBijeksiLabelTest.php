<?php

namespace Tests\Unit\KPI\PerformanceAppraisal\Katalog;

use App\Services\KPI\PerformanceAppraisal\Katalog\PaBijeksiLabel;
use Tests\TestCase;

/**
 * Aturan pemetaan satu-satu, diuji tanpa basis data sama sekali.
 *
 * Aturannya kelihatan sepele sampai dijalankan pada level ASSISTEN MANAGER ke atas:
 * 21 kompetensi wajib berarti ujiannya HARUS punya 21 bagian, dan satu bagian nyasar
 * membuat seluruh sel tak bisa disimpan.
 *
 * Cara membuat tiap uji MERAH: hapus blok pemeriksaan yang bersangkutan dari periksa().
 */
class PaBijeksiLabelTest extends TestCase
{
    /** @return list<array{id_jenis_indikator: string, id_jenis_soal: string}> */
    private function bagian(int $n): array
    {
        return array_map(
            fn ($i) => ['id_jenis_indikator' => '100', 'id_jenis_soal' => (string) $i],
            range(1, $n),
        );
    }

    public function test_pemetaan_lengkap_dinyatakan_sah(): void
    {
        $masalah = PaBijeksiLabel::periksa(
            $this->bagian(3),
            [11, 12, 13],
            ['100|1' => 11, '100|2' => 12, '100|3' => 13],
        );

        $this->assertSame([], $masalah);
    }

    public function test_pasangan_kembar_ditolak_dan_dilaporkan_paling_dulu(): void
    {
        // Selama pasangannya ambigu, pemeriksaan lain tidak bermakna — keduanya menunjuk
        // baris yang sama. Karena itu ia harus muncul PERTAMA, bukan terkubur di bawah
        // keluhan kompetensi kurang yang menyesatkan.
        $bagian = [
            ['id_jenis_indikator' => '100', 'id_jenis_soal' => '77'],
            ['id_jenis_indikator' => '100', 'id_jenis_soal' => '77'],
        ];

        $masalah = PaBijeksiLabel::periksa($bagian, [11, 12], ['100|77' => 11]);

        $this->assertSame('BAGIAN_KEMBAR', $masalah[0]['jenis']);
        $this->assertStringContainsString('100|77', $masalah[0]['pesan']);
    }

    public function test_cacah_bagian_lebih_banyak_ditolak(): void
    {
        $masalah = PaBijeksiLabel::periksa(
            $this->bagian(4),
            [11, 12, 13],
            ['100|1' => 11, '100|2' => 12, '100|3' => 13],
        );

        $this->assertContains('JUMLAH_TAK_SEPADAN', array_column($masalah, 'jenis'));

        // Pesannya wajib menyebut KEDUA kemungkinan: bagian berubah di HC Learn, atau
        // matriks kompetensi berubah di sisi kita. Gejalanya persis sama.
        $pesan = collect($masalah)->firstWhere('jenis', 'JUMLAH_TAK_SEPADAN')['pesan'];
        $this->assertMatchesRegularExpression('/HC Learn.*matriks|matriks.*HC Learn/is', $pesan);
    }

    public function test_bagian_tanpa_label_ditolak(): void
    {
        $masalah = PaBijeksiLabel::periksa(
            $this->bagian(3),
            [11, 12, 13],
            ['100|1' => 11, '100|2' => 12],
        );

        $this->assertContains('KOMPETENSI_KURANG', array_column($masalah, 'jenis'));
    }

    public function test_label_di_luar_daftar_wajib_ditolak(): void
    {
        $masalah = PaBijeksiLabel::periksa(
            $this->bagian(3),
            [11, 12, 13],
            ['100|1' => 11, '100|2' => 12, '100|3' => 99],
        );

        $jenis = array_column($masalah, 'jenis');

        $this->assertContains('KOMPETENSI_LEBIH', $jenis);
        $this->assertContains('KOMPETENSI_KURANG', $jenis);
    }

    public function test_satu_kompetensi_dipakai_dua_bagian_ditolak(): void
    {
        $masalah = PaBijeksiLabel::periksa(
            $this->bagian(3),
            [11, 12, 13],
            ['100|1' => 11, '100|2' => 11, '100|3' => 12],
        );

        $this->assertContains('KOMPETENSI_KURANG', array_column($masalah, 'jenis'));
    }

    public function test_label_menunjuk_bagian_yang_tidak_ada_dilaporkan(): void
    {
        // Bagian dinonaktifkan di HC Learn setelah dilabeli. Ini skenario yang justru
        // menjadi alasan pemeriksaan kesehatan ada.
        $masalah = PaBijeksiLabel::periksa(
            $this->bagian(3),
            [11, 12, 13],
            ['100|1' => 11, '100|2' => 12, '100|99' => 13],
        );

        $this->assertContains('BAGIAN_HILANG', array_column($masalah, 'jenis'));
    }
}
