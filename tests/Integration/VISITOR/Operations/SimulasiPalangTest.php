<?php

namespace Tests\Integration\VISITOR\Operations;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Simulator aplikasi palang.
 *
 * Perintah ini meniru pihak ketiga di gerbang: ia menulis Waktu_Aktual_Masuk /
 * Keluar HANYA ke N_VISITOR_Tamu_Efektif, persis seperti aslinya. Ia TIDAK boleh
 * menyentuh kolom di tabel Detail (itu jalur catatan manual) dan TIDAK boleh
 * mengubah Status_Kunjungan (itu status daur hidup pengajuan, bukan kehadiran).
 */
class SimulasiPalangTest extends TestCase
{
    use DatabaseTransactions;

    public function test_menandai_masuk_hanya_di_tabel_efektif(): void
    {
        $nomor = $this->buatKunjungan();

        Artisan::call('visitor:simulasi-palang', ['--masuk' => $nomor]);

        $this->assertNotNull($this->efektif($nomor)->Waktu_Aktual_Masuk);
        $this->assertNull(
            $this->detail($nomor)->Waktu_Aktual_Masuk,
            'Palang tidak pernah menulis kolom Detail; itu jalur catatan manual.',
        );
    }

    public function test_tidak_mengubah_status_kunjungan(): void
    {
        $nomor = $this->buatKunjungan();

        Artisan::call('visitor:simulasi-palang', ['--masuk' => $nomor]);

        // Status_Kunjungan adalah status daur hidup pengajuan. Palang tidak
        // menulisnya, dan kehadiran diturunkan dari kolom waktu.
        // Kode tersimpan DDL-2026-121: tetap SCHEDULED, bukan CHECKED_IN.
        $this->assertSame('SCHEDULED', $this->header($nomor)->Status_Kunjungan);
    }

    public function test_menandai_keluar_sesudah_masuk(): void
    {
        $nomor = $this->buatKunjungan();
        Artisan::call('visitor:simulasi-palang', ['--masuk' => $nomor]);

        Artisan::call('visitor:simulasi-palang', ['--keluar' => $nomor]);

        $this->assertNotNull($this->efektif($nomor)->Waktu_Aktual_Keluar);
    }

    public function test_keluar_tanpa_masuk_ditolak(): void
    {
        $nomor = $this->buatKunjungan();

        $kode = Artisan::call('visitor:simulasi-palang', ['--keluar' => $nomor]);

        $this->assertNotSame(0, $kode);
        $this->assertNull($this->efektif($nomor)->Waktu_Aktual_Keluar);
    }

    public function test_masuk_dua_kali_ditolak_dan_jam_asli_bertahan(): void
    {
        $nomor = $this->buatKunjungan();
        Artisan::call('visitor:simulasi-palang', ['--masuk' => $nomor]);
        $asli = $this->efektif($nomor)->Waktu_Aktual_Masuk;

        $kode = Artisan::call('visitor:simulasi-palang', ['--masuk' => $nomor]);

        $this->assertNotSame(0, $kode);
        $this->assertSame($asli, $this->efektif($nomor)->Waktu_Aktual_Masuk);
    }

    public function test_nomor_tak_dikenal_ditolak(): void
    {
        $this->assertNotSame(0, Artisan::call('visitor:simulasi-palang', ['--masuk' => 'VSR-TIDAK-ADA']));
    }

    // ---------------------------------------------------------------- helpers

    private function header(string $nomor): object
    {
        return DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $nomor)->first();
    }

    private function detail(string $nomor): object
    {
        return DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->first();
    }

    private function efektif(string $nomor): object
    {
        return DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $nomor)->first();
    }

    private function buatKunjungan(): string
    {
        $nomor = 'VSR-SIM-'.Str::upper(Str::random(10));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_PIC' => 'A1',
            'Status_Kunjungan' => 'SCHEDULED',
            'Tanggal_Kunjungan' => now()->toDateString(),
            'Needs_Approval' => 'T',
            'Flag_Approval' => 'Y',
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => 'A1',
            'Idempotency_Key' => 'SIM-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'No_Transaksi' => $nomor,
            'Id_Transaksi_Tamu' => $id,
            'Nama_Lengkap_Tamu' => 'Tamu Simulasi',
            'Jumlah_Tamu' => 1,
        ]);

        DB::table('N_VISITOR_Tamu_Efektif')->insert([
            'Kode_Perusahaan' => '001',
            'No_Transaksi' => $nomor,
            'Nama_Tamu' => 'Tamu Simulasi',
            'Kode_Karyawan_PIC' => 'A1',
            'Nama_PIC' => 'PIC Uji',
            'Tanggal_Kunjungan' => now()->toDateString(),
            'Status_Efektif' => 'AKTIF',
            'Disetujui_Oleh_Nama' => 'Sistem Uji',
            'Disetujui_At' => now(),
            'Riwayat_Approval' => '[]',
            'Dibuat_At' => now(),
            'Dibuat_Oleh_Nama' => 'Sistem Uji',
            'Updated_At' => now(),
            'Flag_Sync' => 'Y',
        ]);

        return $nomor;
    }
}
