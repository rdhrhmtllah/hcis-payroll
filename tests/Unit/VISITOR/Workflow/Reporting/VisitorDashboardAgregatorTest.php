<?php

namespace Tests\Unit\VISITOR\Workflow\Reporting;

use App\Services\VISITOR\Workflow\Reporting\Dashboard\VisitorDashboardOperasionalService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class VisitorDashboardAgregatorTest extends TestCase
{
    private function now(): Carbon
    {
        return Carbon::parse('2026-08-24 15:00:00');
    }

    private function baris(array $ubah = []): object
    {
        return (object) array_merge([
            'No_Transaksi' => 'VSR0826-0001',
            'Nama_Tamu' => 'Budi',
            'Instansi' => 'PT Contoh',
            'Nama_PIC' => 'Sari',
            'Jumlah_Tamu' => 1,
            'Waktu_Aktual_Masuk' => '2026-08-24 08:00:00',
            'Tanggal_Kunjungan' => '2026-08-24 00:00:00',
        ], $ubah);
    }

    /** Rombongan: satu transaksi berisi lima orang tetap satu transaksi. */
    public function test_rombongan_dihitung_sebagai_orang_bukan_transaksi(): void
    {
        $hasil = VisitorDashboardOperasionalService::agregasiDiDalamArea([
            $this->baris(['Jumlah_Tamu' => 5]),
            $this->baris(['No_Transaksi' => 'VSR0826-0002', 'Jumlah_Tamu' => 1]),
        ], $this->now());

        $this->assertSame(6, $hasil['orang']);
        $this->assertSame(2, $hasil['transaksi']);
    }

    /** Jumlah_Tamu kosong dihitung satu orang, bukan nol. */
    public function test_jumlah_tamu_kosong_dihitung_satu(): void
    {
        $hasil = VisitorDashboardOperasionalService::agregasiDiDalamArea([
            $this->baris(['Jumlah_Tamu' => null]),
        ], $this->now());

        $this->assertSame(1, $hasil['orang']);
    }

    /** Overdue = tanggal kunjungan sudah lewat, bukan lama di dalam area. */
    public function test_overdue_hanya_untuk_tanggal_yang_sudah_lewat(): void
    {
        $hasil = VisitorDashboardOperasionalService::agregasiDiDalamArea([
            $this->baris(['No_Transaksi' => 'KEMARIN', 'Tanggal_Kunjungan' => '2026-08-23 00:00:00', 'Jumlah_Tamu' => 3]),
            $this->baris(['No_Transaksi' => 'HARI_INI', 'Tanggal_Kunjungan' => '2026-08-24 00:00:00']),
        ], $this->now());

        $this->assertSame(1, $hasil['overdue']['transaksi']);
        $this->assertSame(3, $hasil['overdue']['orang']);
        $this->assertSame('KEMARIN', $hasil['daftar_perlu_ditutup'][0]['no_transaksi']);
    }

    /** Daftar dipotong, dan sisanya dilaporkan - bukan hilang diam-diam. */
    public function test_daftar_dipotong_dan_sisanya_dilaporkan(): void
    {
        $rows = [];
        for ($i = 1; $i <= 11; $i++) {
            $rows[] = $this->baris([
                'No_Transaksi' => 'VSR'.$i,
                'Tanggal_Kunjungan' => '2026-08-23 00:00:00',
                'Waktu_Aktual_Masuk' => '2026-08-23 '.str_pad((string) $i, 2, '0', STR_PAD_LEFT).':00:00',
            ]);
        }

        $hasil = VisitorDashboardOperasionalService::agregasiDiDalamArea($rows, $this->now());

        $this->assertCount(VisitorDashboardOperasionalService::BATAS_DAFTAR, $hasil['daftar_perlu_ditutup']);
        $this->assertSame(11, $hasil['daftar_perlu_ditutup_total']);
        $this->assertSame('VSR1', $hasil['daftar_perlu_ditutup'][0]['no_transaksi']);
    }

    /** Umur antrian dipecah tiga ember, dan kedaluwarsa bukan pending. */
    public function test_antrian_dipecah_umur_dan_kedaluwarsa_terpisah(): void
    {
        $hasil = VisitorDashboardOperasionalService::agregasiAntrian([
            (object) ['No_Transaksi' => 'A', 'masuk_antrian' => '2026-08-24 14:30:00', 'ada_pending' => 1, 'ada_kadaluarsa' => 0],
            (object) ['No_Transaksi' => 'B', 'masuk_antrian' => '2026-08-24 12:30:00', 'ada_pending' => 1, 'ada_kadaluarsa' => 0],
            (object) ['No_Transaksi' => 'C', 'masuk_antrian' => '2026-08-24 03:00:00', 'ada_pending' => 1, 'ada_kadaluarsa' => 0],
            (object) ['No_Transaksi' => 'D', 'masuk_antrian' => '2026-08-20 03:00:00', 'ada_pending' => 0, 'ada_kadaluarsa' => 1],
        ], $this->now());

        $this->assertSame(3, $hasil['pending']);
        $this->assertSame(1, $hasil['umur']['kurang_1_jam']);
        $this->assertSame(1, $hasil['umur']['1_sampai_4_jam']);
        $this->assertSame(1, $hasil['umur']['lebih_4_jam']);
        $this->assertSame(1, $hasil['kadaluarsa']);
        $this->assertSame('C', $hasil['daftar'][0]['no_transaksi']);
    }

    /** Identitas ditempelkan ke baris yang cocok; yang tak ditemukan tetap tampil dengan strip. */
    public function test_penggabungan_identitas_tidak_menjatuhkan_baris(): void
    {
        $daftar = [
            ['no_transaksi' => 'A', 'sejak' => null, 'umur_menit' => 10],
            ['no_transaksi' => 'B', 'sejak' => null, 'umur_menit' => 5],
        ];

        $meta = [
            'A' => ['jenis' => 'TAMU', 'judul' => 'Budi', 'pemohon' => 'Sari'],
        ];

        $hasil = VisitorDashboardOperasionalService::gabungIdentitas($daftar, $meta, ['A' => 'SECURITY']);

        $this->assertSame('TAMU', $hasil[0]['jenis']);
        $this->assertSame('SECURITY', $hasil[0]['approver']);
        $this->assertCount(2, $hasil, 'Baris tanpa identitas tidak boleh hilang diam-diam.');
        $this->assertSame('-', $hasil[1]['judul']);
        $this->assertSame('-', $hasil[1]['approver']);
    }
}
