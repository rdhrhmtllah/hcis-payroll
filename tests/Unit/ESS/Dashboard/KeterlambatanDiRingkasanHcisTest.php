<?php

namespace Tests\Unit\ESS\Dashboard;

use App\Services\ESS\Attendance\Processing\KlasifikasiKeterlambatan as K;
use App\Services\ESS\Dashboard\Homepage\HomepageHcisService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Ringkasan HCIS harus MEMBAWA keterlambatan sebenarnya, bukan hanya angka
 * yang sudah dimaafkan SP.
 *
 * Penjaga ini menuntut dua hal:
 *  1. baris absen membawa kunci 'keterlambatan' tanpa mengubah 'late_minutes'
 *  2. kalimat sebabnya menyebut ANGKA, bukan istilah sistem
 */
class KeterlambatanDiRingkasanHcisTest extends TestCase
{
    private function panggil(string $metode, array $argumen): mixed
    {
        // Konstruktor service menarik banyak dependensi; yang diuji di sini
        // murni fungsi pemetaan, jadi objeknya dibuat tanpa konstruktor.
        $svc = (new \ReflectionClass(HomepageHcisService::class))->newInstanceWithoutConstructor();
        $m = new ReflectionMethod($svc, $metode);
        $m->setAccessible(true);

        return $m->invokeArgs($svc, $argumen);
    }

    private function baris(array $ganti = []): object
    {
        return (object) array_merge([
            'Tanggal' => '2026-05-02',
            'Kode_Karyawan' => 'H45',
            'Nama' => 'Ridho',
            'Nama_Shift' => 'EMI-S1',
            'StatusHari' => 'WORK',
            'Jam_Masuk' => '07:00:00',
            'Jam_Keluar' => '15:00:00',
            'CheckIn' => '2026-05-02 07:10:00.000',
            'CheckOut' => '2026-05-02 15:00:00.000',
            'TLate' => '30',
            'RealLate' => '0',
            'Koefisien_OT' => '0',
            'No_Transaksi_Terlambat' => null,
            'Status_Kehadiran' => null,
        ], $ganti);
    }

    public function test_baris_membawa_keterlambatan_sebenarnya(): void
    {
        $hasil = $this->panggil('normalizeAbsensiRow', [$this->baris()]);

        $this->assertArrayHasKey('keterlambatan', $hasil);
        $this->assertSame(K::TOLERANSI, $hasil['keterlambatan']['kategori']);
        $this->assertSame(10, $hasil['keterlambatan']['mentah_menit']);
        $this->assertSame(10, $hasil['keterlambatan']['tak_tercatat_menit']);
    }

    /**
     * Angka resmi tidak boleh ikut berubah. Banyak layar sudah bersandar
     * padanya, dan tujuan perubahan ini menambah keterangan — bukan menggeser
     * arti angka lama.
     */
    public function test_late_minutes_tidak_ikut_berubah(): void
    {
        $dimaafkan = $this->panggil('normalizeAbsensiRow', [$this->baris()]);
        $this->assertSame(0, $dimaafkan['late_minutes'], 'Yang dimaafkan SP tetap nol di angka resmi.');

        $dihitung = $this->panggil('normalizeAbsensiRow', [
            $this->baris(['CheckIn' => '2026-05-02 07:40:00.000', 'RealLate' => '40']),
        ]);
        $this->assertSame(40, $dihitung['late_minutes']);
        $this->assertSame(K::TERLAMBAT, $dihitung['keterlambatan']['kategori']);
    }

    public function test_kalimat_toleransi_menyebut_angkanya(): void
    {
        $kalimat = $this->panggil('sebabKeterlambatanTakTercatat', [
            K::dari($this->baris()),
        ]);

        $this->assertStringContainsString('30 menit', $kalimat, 'Besaran toleransinya harus disebut.');
        $this->assertStringNotContainsStringIgnoringCase('RealLate', $kalimat);
        $this->assertStringNotContainsStringIgnoringCase('TLate', $kalimat);
    }

    public function test_kalimat_izin_menyebut_izinnya(): void
    {
        $kalimat = $this->panggil('sebabKeterlambatanTakTercatat', [
            K::dari($this->baris([
                'CheckIn' => '2026-05-02 07:20:00.000',
                'TLate' => '0',
                'No_Transaksi_Terlambat' => 'TP06-26-0017',
            ])),
        ]);

        $this->assertMatchesRegularExpression('/izin terlambat/i', $kalimat);
    }

    /**
     * Plafon menang atas sebab lain: ia menjelaskan selisih menit yang jauh
     * lebih besar, dan di data dev justru inilah yang paling banyak
     * menyembunyikan keterlambatan.
     */
    public function test_kalimat_plafon_menyebut_menit_yang_hilang(): void
    {
        $kalimat = $this->panggil('sebabKeterlambatanTakTercatat', [
            K::dari($this->baris([
                'Tanggal' => '2026-05-20',
                'Jam_Masuk' => '23:00',
                'CheckIn' => '2026-05-21 00:00:00.000',
                'TLate' => '0',
                'RealLate' => '50',
            ])),
        ]);

        $this->assertStringContainsString('50 menit', $kalimat);
        $this->assertStringContainsString('10 menit', $kalimat, 'Selisih yang hilang harus disebut.');
    }

    public function test_tepat_waktu_tidak_memunculkan_keterangan_apa_pun(): void
    {
        $hasil = $this->panggil('normalizeAbsensiRow', [
            $this->baris(['CheckIn' => '2026-05-02 07:00:00.000']),
        ]);

        $this->assertSame(K::TEPAT_WAKTU, $hasil['keterlambatan']['kategori']);
        $this->assertFalse($hasil['keterlambatan']['terlambat_sebenarnya']);
        $this->assertSame(0, $hasil['keterlambatan']['tak_tercatat_menit']);
    }
}
