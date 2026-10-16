<?php

namespace Tests\Unit\ESS\Attendance\Monitoring;

use App\Services\ESS\Attendance\Monitoring\KategoriKehadiran;
use App\Services\ESS\Attendance\Monitoring\SaringanMonitoring as S;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SaringanMonitoringTest extends TestCase
{
    public function test_nilai_kosong_duplikat_dan_bukan_skalar_dibuang(): void
    {
        $f = S::normalisasi(['lokasi' => ['HO', ' HO ', '', null, ['x'], 'Pabrik'], 'divisi' => 'Produksi']);

        $this->assertSame(['HO', 'Pabrik'], $f['lokasi']);
        $this->assertSame(['Produksi'], $f['divisi']);
        $this->assertSame([], $f['shift']);
    }

    public function test_jumlah_nilai_dibatasi(): void
    {
        $f = S::normalisasi(['jabatan' => array_map(fn ($i) => "J{$i}", range(1, 500))]);

        $this->assertCount(S::MAKS_NILAI, $f['jabatan']);
    }

    public function test_kategori_urut_dan_halaman(): void
    {
        $f = S::normalisasi(['kategori' => 'tidak_ada', 'urut' => 'x', 'page' => -3, 'limit' => 5000], ['nama', 'terlambat']);

        $this->assertNull($f['kategori']);
        $this->assertSame('nama', $f['urut']);
        $this->assertSame(1, $f['page']);
        $this->assertSame(100, $f['limit']);
        $this->assertSame(KategoriKehadiran::BELUM_ABSEN, S::normalisasi(['kategori' => 'belum_absen'])['kategori']);
    }

    public function test_rentang_62_hari_diterima(): void
    {
        $this->assertSame(['dari' => '2026-09-01', 'sampai' => '2026-11-01'], S::rentang('2026-09-01', '2026-11-01'));
    }

    public function test_rentang_63_hari_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        S::rentang('2026-09-01', '2026-11-02');
    }

    public function test_rentang_terbalik_dan_tanggal_mustahil_ditolak(): void
    {
        foreach ([['2026-10-15', '2026-10-01'], ['2026-02-30', '2026-03-01'], ['', '2026-03-01']] as [$d, $s]) {
            try {
                S::rentang($d, $s);
                $this->fail("Rentang {$d}..{$s} seharusnya ditolak");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
