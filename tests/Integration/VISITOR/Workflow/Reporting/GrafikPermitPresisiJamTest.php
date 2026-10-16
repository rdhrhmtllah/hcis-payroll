<?php

namespace Tests\Integration\VISITOR\Workflow\Reporting;

use App\Services\VISITOR\Workflow\Reporting\Dashboard\VisitorDashboardAnalitikService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Grafik permit aktif per kategori tidak menghitung izin berjam yang jendelanya sudah habis (audit M10). */
class GrafikPermitPresisiJamTest extends TestCase
{
    use DatabaseTransactions;

    public function test_izin_berjam_yang_sudah_habis_pagi_ini_tidak_terhitung_aktif(): void
    {
        $kategori = 'UJI-JAM-'.Str::upper(Str::random(6));
        foreach (['habis' => now()->subHour(), 'hidup' => now()->addHours(3)] as $akhir) {
            DB::table('N_VISITOR_Permit_Efektif')->insert([
                'Kode_Perusahaan' => '001', 'No_Transaksi' => 'PMT-JAM-'.Str::upper(Str::random(8)),
                'Kode_Karyawan_Pemohon' => 'A1', 'Nama_Pemohon' => 'Uji', 'Status_Efektif' => 'AKTIF',
                'Jenis_Akses' => 'PERMIT', 'Sumber_Permit' => 'TAMU', 'Nama_Kategori_Permit' => $kategori,
                'Tanggal_Mulai_Berlaku' => now()->toDateString(), 'Tanggal_Kadaluarsa' => now()->toDateString(),
                'Waktu_Mulai_Berlaku' => now()->subHours(5), 'Waktu_Berakhir' => $akhir,
                'Disetujui_Oleh_Nama' => 'Sistem Uji', 'Disetujui_At' => now(), 'Riwayat_Approval' => '[]',
                'Dibuat_At' => now(), 'Dibuat_Oleh_Nama' => 'Sistem Uji', 'Flag_Sync' => 'Y', 'Updated_At' => now(),
            ]);
        }

        $baris = collect(app(VisitorDashboardAnalitikService::class)->permitPerKategori())->firstWhere('kategori', $kategori);

        $this->assertSame(1, (int) ($baris['jumlah'] ?? 0));
    }
}
