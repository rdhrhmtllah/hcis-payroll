<?php

namespace Tests\Unit\LMS\SOP\Uat;

use App\Services\LMS\SOP\Uat\UatBebanStatistik;
use PHPUnit\Framework\TestCase;

class UatBebanStatistikTest extends TestCase
{
    public function test_persentil_memakai_interpolasi_linier(): void
    {
        $ms = [10, 20, 30, 40];

        $this->assertSame(25.0, UatBebanStatistik::persentil($ms, 0.5));
        $this->assertSame(10.0, UatBebanStatistik::persentil($ms, 0.0));
        $this->assertSame(40.0, UatBebanStatistik::persentil($ms, 1.0));
    }

    public function test_urutan_masukan_tidak_mempengaruhi_hasil(): void
    {
        $this->assertSame(25.0, UatBebanStatistik::persentil([40, 10, 30, 20], 0.5),
            'Persentil bergantung pada urutan masukan — berarti datanya tidak diurutkan '
            .'dulu. Hasil pengukuran datang sesuai urutan selesainya permintaan, yang acak.');
    }

    public function test_daftar_kosong_tidak_menghentikan_laporan(): void
    {
        $r = UatBebanStatistik::ringkas([]);

        $this->assertSame(0, $r['n']);
        $this->assertSame(0.0, $r['p95'],
            'Fase tanpa satu pun permintaan harus terbaca 0, bukan melempar dan '
            .'menghentikan seluruh laporan.');
        $this->assertSame(0.0, $r['maks']);
    }

    public function test_ringkas_melaporkan_maks_yang_benar(): void
    {
        $r = UatBebanStatistik::ringkas([5, 100, 20]);

        $this->assertSame(3, $r['n']);
        $this->assertSame(100.0, $r['maks']);
    }
}
