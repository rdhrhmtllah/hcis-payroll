<?php

namespace Tests\Integration\VISITOR\Workflow\Reporting;

use App\Services\VISITOR\Workflow\Reporting\Dashboard\VisitorDashboardAnalitikService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sumber kehadiran pada analitik dashboard.
 *
 * Waktu masuk/keluar TIDAK ditulis HCIS. Aplikasi palang di gerbang menuliskannya
 * langsung ke N_VISITOR_Tamu_Efektif, dan syncTamuEfektif sengaja tidak menyentuh
 * kedua kolom itu. Analitik yang hanya membaca kolom detail karena itu buta:
 * "Sebaran jam kedatangan" selamanya kosong dan "sudah masuk" selamanya nol,
 * berapa pun orang yang benar-benar lewat palang.
 */
class DashboardAnalitikSumberKehadiranTest extends TestCase
{
    use DatabaseTransactions;

    private const PIC = 'ZZANL1';

    public function test_sebaran_jam_melihat_kedatangan_yang_ditandai_palang(): void
    {
        $this->buatKunjungan(masuk: now()->setTime(14, 30));

        $sebaran = app(VisitorDashboardAnalitikService::class)
            ->sebaranJam(now()->toDateString(), now()->toDateString());

        $jam = array_column($sebaran, 'jam');

        $this->assertContains(14, $jam, 'Kedatangan yang ditandai palang tidak terlihat di sebaran jam.');
    }

    public function test_kesehatan_data_menghitung_kehadiran_dari_baris_efektif(): void
    {
        $this->buatKunjungan(masuk: now()->setTime(9, 0));
        $this->buatKunjungan(masuk: now()->setTime(10, 0), keluar: now()->setTime(11, 30));

        $sehat = app(VisitorDashboardAnalitikService::class)
            ->kesehatanData(now()->toDateString(), now()->toDateString());

        $this->assertGreaterThanOrEqual(2, $sehat['sudah_masuk']);
        $this->assertGreaterThanOrEqual(1, $sehat['tanpa_waktu_keluar']);
    }

    public function test_catatan_manual_di_detail_tetap_terhitung_bila_palang_belum_menulis(): void
    {
        // Cadangan, bukan sumber utama.
        $this->buatKunjungan(masukManual: now()->setTime(8, 15));

        $sebaran = app(VisitorDashboardAnalitikService::class)
            ->sebaranJam(now()->toDateString(), now()->toDateString());

        $this->assertContains(8, array_column($sebaran, 'jam'));
    }

    // ---------------------------------------------------------------- helpers

    private function buatKunjungan(
        ?\DateTimeInterface $masuk = null,
        ?\DateTimeInterface $keluar = null,
        ?\DateTimeInterface $masukManual = null,
    ): string {
        $nomor = 'VSR-ANL-'.Str::upper(Str::random(10));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_PIC' => self::PIC,
            'Status_Kunjungan' => 'CHECKED_IN',
            'Tanggal_Kunjungan' => now()->toDateString(),
            'Needs_Approval' => 'T',
            'Flag_Approval' => 'Y',
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Idempotency_Key' => 'ANL-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'No_Transaksi' => $nomor,
            'Id_Transaksi_Tamu' => $id,
            'Nama_Lengkap_Tamu' => 'Tamu Analitik',
            'Jumlah_Tamu' => 1,
            'Waktu_Aktual_Masuk' => $masukManual,
        ]);

        if ($masuk !== null || $keluar !== null) {
            DB::table('N_VISITOR_Tamu_Efektif')->insert([
                'Kode_Perusahaan' => '001',
                'No_Transaksi' => $nomor,
                'Nama_Tamu' => 'Tamu Analitik',
                'Kode_Karyawan_PIC' => self::PIC,
                'Nama_PIC' => 'PIC Uji',
                'Tanggal_Kunjungan' => now()->toDateString(),
                'Status_Efektif' => 'AKTIF',
                'Waktu_Aktual_Masuk' => $masuk,
                'Waktu_Aktual_Keluar' => $keluar,
                'Disetujui_Oleh_Nama' => 'Sistem Uji',
                'Disetujui_At' => now(),
                'Riwayat_Approval' => '[]',
                'Dibuat_At' => now(),
                'Dibuat_Oleh_Nama' => 'Sistem Uji',
                'Updated_At' => now(),
                'Flag_Sync' => 'Y',
            ]);
        }

        return $nomor;
    }
}
