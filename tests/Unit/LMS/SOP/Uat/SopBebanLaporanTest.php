<?php

namespace Tests\Unit\LMS\SOP\Uat;

use App\Console\Commands\LMS\SOP\Operations\SopBebanLaporan;
use PHPUnit\Framework\TestCase;

/**
 * Penilaian kriteria uji beban.
 *
 * Diuji terpisah dari perakitan laporannya karena inilah bagian yang menentukan
 * kesimpulan. Kriteria yang salah menilai tetap menghasilkan laporan yang rapi,
 * dan laporan rapi dengan kesimpulan keliru adalah keluaran terburuk yang bisa
 * dihasilkan uji ini.
 */
class SopBebanLaporanTest extends TestCase
{
    private function fase(string $nama, float $p95, int $gagal = 0, int $timeout = 0, int $blok = 0): array
    {
        return [
            'nama' => $nama,
            'p95' => $p95,
            'gagal' => $gagal,
            'timeout' => $timeout,
            'blocking' => ['terlama_ms' => $blok, 'jumlah' => 0],
        ];
    }

    public function test_p95_lebih_dari_dua_kali_dasar_dinyatakan_gagal(): void
    {
        $n = SopBebanLaporan::nilaiKriteria([
            'fase' => [$this->fase('dasar', 100.0), $this->fase('assign', 250.0)],
            'transaksi_massal_ms' => [1200],
        ]);

        $this->assertFalse($n['lulus']);
        $this->assertStringContainsString('p95', implode(' ', $n['alasan']));
    }

    public function test_semua_dalam_batas_dinyatakan_lulus(): void
    {
        $n = SopBebanLaporan::nilaiKriteria([
            'fase' => [$this->fase('dasar', 100.0), $this->fase('assign', 150.0, 0, 0, 800)],
            'transaksi_massal_ms' => [3000],
        ]);

        $this->assertTrue($n['lulus'], implode(' | ', $n['alasan']));
    }

    public function test_satu_permintaan_gagal_sudah_menggugurkan(): void
    {
        $n = SopBebanLaporan::nilaiKriteria([
            'fase' => [$this->fase('dasar', 100.0, 1)],
            'transaksi_massal_ms' => [],
        ]);

        $this->assertFalse($n['lulus'],
            'Satu permintaan gagal harus menggugurkan — kriteria 1 tidak punya toleransi.');
    }

    public function test_pembaca_terblokir_lama_menggugurkan(): void
    {
        $n = SopBebanLaporan::nilaiKriteria([
            'fase' => [$this->fase('dasar', 100.0), $this->fase('cabut', 120.0, 0, 0, 4000)],
            'transaksi_massal_ms' => [],
        ]);

        $this->assertFalse($n['lulus'],
            'Pembaca terblokir 4 detik lolos — padahal itu justru pertanyaan yang '
            .'hendak dijawab seluruh uji ini.');
    }

    public function test_transaksi_massal_kelamaan_menggugurkan(): void
    {
        $n = SopBebanLaporan::nilaiKriteria([
            'fase' => [$this->fase('dasar', 100.0)],
            'transaksi_massal_ms' => [15872],
        ]);

        $this->assertFalse($n['lulus']);
        $this->assertStringContainsString('15872', implode(' ', $n['alasan']));
    }

    public function test_jalan_yang_tak_menugaskan_siapa_pun_dinyatakan_gagal(): void
    {
        $n = SopBebanLaporan::nilaiKriteria([
            'fase' => [$this->fase('dasar', 100.0), $this->fase('assign', 110.0)],
            'transaksi_massal_ms' => [294, 67],
            'assign' => ['ditugaskan' => 0, 'dilewati' => 18],
            'cabut' => ['dicabut' => 0, 'diminta' => 18],
        ]);

        $this->assertFalse($n['lulus'],
            'Operasi massal yang tak menyentuh satu baris pun menghasilkan angka yang '
            .'seluruhnya bagus — nol blocking, transaksi sekejap — dan itu terbaca aman. '
            .'Jalan kosong harus GAGAL, bukan lulus.');
        $this->assertStringContainsString('kosong', implode(' ', $n['alasan']));
    }

    public function test_dasar_nol_tidak_menggugurkan_seluruh_fase(): void
    {
        $n = SopBebanLaporan::nilaiKriteria([
            'fase' => [$this->fase('dasar', 0.0), $this->fase('assign', 500.0)],
            'transaksi_massal_ms' => [],
        ]);

        $this->assertTrue($n['lulus'],
            'Fase dasar tanpa data membuat pembagi nol; perbandingan p95 harus dilewati, '
            .'bukan menyatakan seluruh fase berikutnya gagal.');
    }
}
