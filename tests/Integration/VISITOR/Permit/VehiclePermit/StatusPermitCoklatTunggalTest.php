<?php

namespace Tests\Integration\VISITOR\Permit\VehiclePermit;

use App\Http\Controllers\VISITOR\Guest\Visit\TamuB2BApiController;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\VISITOR\MenerbitkanPermitCoklat;
use Tests\TestCase;

/**
 * Status permit COKLAT satu sumber (audit 27 Sep 2026, H4 & H5).
 *
 * Layar-layar tamu dulu menurunkan status izin gerbang dari kolom mentah
 * (Flag_Approval, Status_Efektif) dengan dua urutan berbeda, sehingga izin yang
 * jendelanya sudah habis tampil "AKTIF" selama penyapu belum jalan, dan permit
 * yang ditolak tampil "KEDALUWARSA" bila baris inspeksinya kedaluwarsa. Kini
 * semuanya diturunkan dari permitStatusSql().
 */
class StatusPermitCoklatTunggalTest extends TestCase
{
    use DatabaseTransactions;
    use MenerbitkanPermitCoklat;

    private const PIC = 'A1';

    public function test_izin_yang_jendelanya_habis_terbaca_kedaluwarsa_walau_penyapu_belum_jalan(): void
    {
        $tamu = $this->kunjungan(now()->subDays(2));
        $this->terbitkanPermitCoklat($tamu, self::PIC);
        $this->assertSame('AKTIF', $this->proyeksi($tamu), 'Prasyarat: proyeksi gerbang masih AKTIF (penyapu belum jalan).');

        $this->assertSame('KEDALUWARSA', $this->statusIzin($tamu, 'IZIN'));
        $this->assertSame('EXPIRED', $this->statusIzin($tamu, 'PROSES'));
        $this->assertSame('EXPIRED', $this->statusApiMitra($tamu));
    }

    public function test_permit_ditolak_tetap_ditolak_walau_baris_gerbangnya_kedaluwarsa(): void
    {
        $tamu = $this->kunjungan(now()->subDays(2));
        $noPermit = $this->ajukanPermitCoklat($tamu);
        DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $noPermit)->update(['Flag_Approval' => 'T']);
        DB::table('N_VISITOR_Permit_Efektif')->insert([
            'Kode_Perusahaan' => '001', 'No_Transaksi' => $noPermit, 'Kode_Karyawan_Pemohon' => self::PIC,
            'Nama_Pemohon' => 'Uji', 'Status_Efektif' => 'EXPIRED', 'Jenis_Akses' => 'INSPEKSI',
            'Tanggal_Mulai_Berlaku' => now()->subDays(3)->toDateString(), 'Tanggal_Kadaluarsa' => now()->subDays(2)->toDateString(),
            'Disetujui_Oleh_Nama' => 'Sistem Uji', 'Disetujui_At' => now()->subDays(3), 'Riwayat_Approval' => '[]',
            'Dibuat_At' => now()->subDays(3), 'Dibuat_Oleh_Nama' => 'Sistem Uji',
            'Flag_Sync' => 'Y', 'Updated_At' => now(),
        ]);

        $this->assertSame('DITOLAK', $this->statusIzin($tamu, 'IZIN'));
        $this->assertSame('DITOLAK', $this->statusIzin($tamu, 'PROSES'));
        $this->assertSame('REJECTED', $this->statusApiMitra($tamu));
    }

    public function test_izin_berlaku_dan_menunggu_tetap_seperti_semula(): void
    {
        $berlaku = $this->kunjungan(now());
        $this->terbitkanPermitCoklat($berlaku, self::PIC);
        $menunggu = $this->kunjungan(now());
        $this->ajukanPermitCoklat($menunggu);

        $this->assertSame('AKTIF', $this->statusIzin($berlaku, 'IZIN'));
        $this->assertSame('AKTIF', $this->statusIzin($berlaku, 'PROSES'));
        $this->assertSame('MENUNGGU', $this->statusIzin($menunggu, 'IZIN'));
        $this->assertSame('MENUNGGU_APPROVAL', $this->statusIzin($menunggu, 'PROSES'));
    }

    public function test_tanpa_permit_bernilai_null(): void
    {
        $tamu = $this->kunjungan(now());

        $this->assertNull($this->statusIzin($tamu, 'IZIN'));
    }

    public function test_tidak_ada_layar_yang_menurunkan_status_izin_dari_kolom_mentah(): void
    {
        $berkas = [
            'app/Services/VISITOR/Workflow/Reporting/Monitoring/VisitorPapanTamuService.php',
            'app/Http/Controllers/VISITOR/Workflow/Reporting/LaporanVisitorController.php',
            'app/Http/Controllers/VISITOR/Guest/Schedule/JadwalKedatanganTamuController.php',
            'app/Http/Controllers/VISITOR/Guest/Visit/GuestRegistrationController.php',
            'app/Services/VISITOR/Permit/VehiclePermit/VisitorPermitQueryService.php',
        ];

        foreach ($berkas as $b) {
            $isi = file_get_contents(base_path($b));
            $this->assertStringNotContainsString("WHEN pe.Status_Efektif = 'EXPIRED' THEN 'KEDALUWARSA'", $isi, $b);
            $this->assertStringNotContainsString("WHEN p.Flag_Approval IS NULL THEN 'MENUNGGU_APPROVAL'", $isi, $b);
        }
    }

    private function statusIzin(string $tamu, string $kosakata): ?string
    {
        $q = app(VisitorPermitQueryService::class);

        return DB::table('N_EMI_VISITOR_Transaksi_Tamu as h')
            ->where('h.No_Transaksi', $tamu)
            ->value(DB::raw($q->statusPermitKunjunganSql('h.No_Transaksi', $kosakata).' as S'));
    }

    private function statusApiMitra(string $tamu): ?string
    {
        $jawab = app(TamuB2BApiController::class)->status(Request::create('/x'), $tamu);

        return $jawab->getData(true)['result']['permit_coklat']['status'] ?? $jawab->getData(true)['data']['permit_coklat']['status'] ?? null;
    }

    private function proyeksi(string $tamu): ?string
    {
        return DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $this->nomorPermitCoklat($tamu))->value('Status_Efektif');
    }

    private function kunjungan(Carbon $tanggal): string
    {
        $nomor = 'VSR-STG-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor, 'Kode_Karyawan_PIC' => self::PIC,
            'Status_Kunjungan' => StatusKunjunganTamu::SCHEDULED, 'Flag_Approval' => 'Y',
            'Tanggal_Kunjungan' => $tanggal->toDateString(), 'Flag_Aktif' => 'Y',
            'Created_At' => now(), 'Created_By' => self::PIC, 'Idempotency_Key' => 'STG-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id, 'No_Transaksi' => $nomor, 'Nama_Lengkap_Tamu' => 'Tamu Status',
            'Tipe_Tamu' => 'B2B', 'Instansi_Atau_Asal' => 'PT Uji', 'Deksripsi_Keperluan' => 'Meeting',
            'Jumlah_Tamu' => 1, 'Jenis_Kendaraan' => 'Mobil', 'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(10000, 99999), 'Plat_Nomor_Suffix' => 'ST',
        ]);

        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);

        return $nomor;
    }
}
