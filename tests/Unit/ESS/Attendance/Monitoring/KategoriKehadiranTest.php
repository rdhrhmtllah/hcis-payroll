<?php

namespace Tests\Unit\ESS\Attendance\Monitoring;

use App\Services\ESS\Attendance\Monitoring\KategoriKehadiran as K;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Tiap karyawan masuk tepat satu kategori; urutan cek = prioritas.
 * Bentuk baris mengikuti N_HRIS_Absen_Snapshot apa adanya.
 */
class KategoriKehadiranTest extends TestCase
{
    private function baris(array $ganti = []): array
    {
        return array_merge([
            'StatusHari' => 'WORK',
            'Jam_Masuk' => '08:00',
            'CheckIn' => null,
            'RealLate' => 0,
            'No_Transaksi_Cuti' => '-',
            'No_Transaksi_Sakit' => '-',
            'No_Transaksi_Izin' => '-',
        ], $ganti);
    }

    public function test_hadir_tepat_waktu_dan_terlambat_dari_real_late(): void
    {
        $this->assertSame(K::TEPAT_WAKTU, K::tentukan($this->baris(['CheckIn' => '2026-10-13 07:55:00']), '12:00:00'));
        $this->assertSame(K::TERLAMBAT, K::tentukan($this->baris(['CheckIn' => '2026-10-13 08:20:00', 'RealLate' => 20]), '12:00:00'));
    }

    public function test_cuti_mengalahkan_day_off_dan_check_in(): void
    {
        $r = $this->baris(['No_Transaksi_Cuti' => 'CT/001', 'StatusHari' => 'OFF', 'CheckIn' => '2026-10-13 08:00:00']);

        $this->assertSame(K::CUTI, K::tentukan($r, '12:00:00'));
    }

    public function test_sakit_dan_izin_mengalahkan_day_off(): void
    {
        $this->assertSame(K::SAKIT_IZIN, K::tentukan($this->baris(['No_Transaksi_Sakit' => 'SK/1', 'StatusHari' => 'OFF']), '12:00:00'));
        $this->assertSame(K::SAKIT_IZIN, K::tentukan($this->baris(['No_Transaksi_Izin' => 'IZ/1']), '12:00:00'));
    }

    public function test_tanda_strip_dan_kosong_berarti_tidak_ada_transaksi(): void
    {
        foreach (['-', '', ' - ', null] as $kosong) {
            $this->assertFalse(K::ada($kosong));
            $this->assertSame(K::BELUM_ABSEN, K::tentukan($this->baris(['No_Transaksi_Cuti' => $kosong]), '12:00:00'));
        }
    }

    public function test_semua_varian_off_adalah_libur(): void
    {
        foreach (['OFF', 'OFF-Default_Shift', 'OFF-Hari_Libur'] as $off) {
            $this->assertSame(K::LIBUR, K::tentukan($this->baris(['StatusHari' => $off, 'CheckIn' => '2026-10-13 08:00:00']), '12:00:00'));
        }
    }

    /** StatusHari kosong terlihat sebagai kategorinya sendiri, bukan hilang atau dianggap alpha. */
    public function test_status_hari_kosong_jadi_tanpa_jadwal(): void
    {
        $this->assertSame(K::TANPA_JADWAL, K::tentukan($this->baris(['StatusHari' => null]), '12:00:00'));
        $this->assertSame(K::TANPA_JADWAL, K::tentukan($this->baris(['StatusHari' => '  ']), '12:00:00'));
    }

    public function test_belum_jam_masuk_hanya_bila_jam_masuk_belum_tiba(): void
    {
        $this->assertSame(K::BELUM_JAM, K::tentukan($this->baris(), '07:30:00'));
        $this->assertSame(K::BELUM_ABSEN, K::tentukan($this->baris(), '08:00:01'));
        $this->assertSame(K::BELUM_ABSEN, K::tentukan($this->baris(['Jam_Masuk' => '08:00:00']), '08:00:00'));
    }

    public function test_jam_masuk_tak_terbaca_dianggap_sudah_lewat(): void
    {
        $this->assertSame(K::BELUM_ABSEN, K::tentukan($this->baris(['Jam_Masuk' => null]), '00:00:00'));
        $this->assertSame(K::BELUM_ABSEN, K::tentukan($this->baris(['Jam_Masuk' => '-']), '00:00:00'));
    }

    /** Tanggal lampau tak pernah "belum jam masuk": semua tanpa check-in = belum absen. */
    public function test_jam_acuan_lampau_hari_ini_dan_depan(): void
    {
        $sekarang = Carbon::parse('2026-10-14 07:15:30');

        $this->assertSame('23:59:59', K::jamAcuan('2026-10-13', $sekarang));
        $this->assertSame('07:15:30', K::jamAcuan('2026-10-14', $sekarang));
        $this->assertSame('00:00:00', K::jamAcuan('2026-10-15', $sekarang));

        $shiftMalam = $this->baris(['Jam_Masuk' => '23:00']);
        $this->assertSame(K::BELUM_ABSEN, K::tentukan($shiftMalam, K::jamAcuan('2026-10-13', $sekarang)));
        $this->assertSame(K::BELUM_JAM, K::tentukan($shiftMalam, K::jamAcuan('2026-10-14', $sekarang)));
    }

    public function test_normal_jam(): void
    {
        $this->assertSame('07:00:00', K::normalJam('07:00'));
        $this->assertSame('07:05:09', K::normalJam('7:05:09'));
        $this->assertNull(K::normalJam('-'));
        $this->assertNull(K::normalJam(''));
    }

    public function test_sql_case_memuat_tepat_satu_binding_dan_semua_kategori(): void
    {
        $sql = K::sqlCase('s');

        $this->assertSame(1, substr_count($sql, '?'));
        foreach (K::URUTAN as $kategori) {
            $this->assertStringContainsString("'{$kategori}'", $sql);
        }
        $this->assertCount(count(K::URUTAN), K::LABEL);
    }
}
