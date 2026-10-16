<?php

namespace Tests\Integration\VISITOR\Operations;

use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Permit berjendela jam harus mati tepat pada jamnya — di sapuan MAUPUN di layar.
 *
 * Sebelum ini keduanya bersandar pada daftar sumber. Sapuannya hanya menyentuh
 * Sumber_Permit='TAMU', sehingga permit mitra tidak pernah tersapu sama sekali;
 * dan permitStatusSql() menghitung EXPIRED per hari, sehingga permit yang habis
 * pukul 12:00 terbaca APPROVED di meja verifikator sampai lewat tengah malam.
 *
 * Aturannya sekarang bersandar pada FAKTA — punya jendela atau tidak — supaya
 * sumber keempat kelak ikut benar tanpa menyentuh kode ini.
 */
class PermitApiBerakhirTest extends TestCase
{
    use DatabaseTransactions;

    /** Kode karyawan NYATA di Web_HRIS development. */
    private const PIC = 'A1';

    protected function setUp(): void
    {
        parent::setUp();

        // A1 tercatat resign di dev (3 Sep 2025). Di sini ia diperlakukan masih
        // bekerja; perubahannya ikut dibatalkan bersama transaksi uji.
        DB::table('Karyawan')->where('Kode_Karyawan', self::PIC)->update(['Tanggal_Resign' => null]);
    }

    public function test_permit_api_mati_di_jam_selesainya(): void
    {
        $no = $this->buatPermitEfektif('API', now()->subHours(3), now()->subHour());

        Artisan::call('visitor:expire-efektif');

        $baris = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $no)->first();

        $this->assertSame('EXPIRED', $baris->Status_Efektif);
        $this->assertSame('Jendela berlaku berakhir', $baris->Alasan_Status);
        $this->assertSame('T', $baris->Flag_Sync, 'Perubahan tidak ditandai untuk ditarik ulang gerbang.');
    }

    public function test_permit_api_yang_belum_habis_tetap_hidup(): void
    {
        $no = $this->buatPermitEfektif('API', now()->subHour(), now()->addHours(3));

        Artisan::call('visitor:expire-efektif');

        $this->assertSame(
            'AKTIF',
            DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $no)->value('Status_Efektif'),
        );
    }

    public function test_permit_karyawan_berbulan_tidak_ikut_tersapu_cabang_jam(): void
    {
        // Tidak punya jendela, dan masa berlakunya masih jauh. Kalau cabang jam
        // salah menyaring, permit karyawan yang sah akan ikut mati.
        $no = $this->buatPermitEfektif('KARYAWAN', null, null, kadaluarsa: now()->addMonths(2));

        Artisan::call('visitor:expire-efektif');

        $this->assertSame(
            'AKTIF',
            DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $no)->value('Status_Efektif'),
        );
    }

    public function test_permit_karyawan_yang_sudah_lewat_tetap_tersapu_per_hari(): void
    {
        $no = $this->buatPermitEfektif('KARYAWAN', null, null, kadaluarsa: now()->subDays(2));

        Artisan::call('visitor:expire-efektif');

        $this->assertSame(
            'EXPIRED',
            DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $no)->value('Status_Efektif'),
        );
    }

    public function test_status_di_layar_ikut_sadar_jam(): void
    {
        $no = $this->buatPengajuanDisetujui(now()->subHours(3), now()->subHour());

        $baris = app(VisitorPermitQueryService::class)->basePermitQuery()
            ->where('h.No_Transaksi', $no)->first();

        $this->assertSame('EXPIRED', $baris->Status_Permit);
    }

    public function test_permit_berjendela_yang_masih_berlaku_terbaca_approved(): void
    {
        $no = $this->buatPengajuanDisetujui(now()->subHour(), now()->addHours(3));

        $baris = app(VisitorPermitQueryService::class)->basePermitQuery()
            ->where('h.No_Transaksi', $no)->first();

        $this->assertSame('APPROVED', $baris->Status_Permit);
    }

    public function test_permit_karyawan_berbulan_tetap_approved_di_layar(): void
    {
        $no = $this->buatPengajuanDisetujui(null, null, kadaluarsa: now()->addMonths(2));

        $baris = app(VisitorPermitQueryService::class)->basePermitQuery()
            ->where('h.No_Transaksi', $no)->first();

        $this->assertSame('APPROVED', $baris->Status_Permit);
    }

    private function buatPermitEfektif(
        string $sumber,
        ?Carbon $mulai,
        ?Carbon $akhir,
        ?Carbon $kadaluarsa = null,
    ): string {
        $nomor = 'PMT-EXA-'.Str::upper(Str::random(8));
        $tanggalAkhir = $akhir ?? $kadaluarsa ?? now();

        DB::table('N_VISITOR_Permit_Efektif')->insert([
            'Kode_Perusahaan' => '001',
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => self::PIC,
            'Nama_Pemohon' => 'PIC Uji',
            'Nama_Kategori_Permit' => $sumber === 'API' ? 'COKLAT' : 'HIJAU',
            'Sumber_Permit' => $sumber,
            'Status_Efektif' => 'AKTIF',
            'Waktu_Mulai_Berlaku' => $mulai,
            'Waktu_Berakhir' => $akhir,
            'Tanggal_Mulai_Berlaku' => ($mulai ?? now()->subMonth())->toDateString(),
            'Tanggal_Kadaluarsa' => $tanggalAkhir->toDateString(),
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

    private function buatPengajuanDisetujui(
        ?Carbon $mulai,
        ?Carbon $akhir,
        ?Carbon $kadaluarsa = null,
    ): string {
        $nomor = 'PMT-STS-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => self::PIC,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => 'Y',
            'Approved_At' => now()->subDay(),
            'Sumber_Pengajuan' => $mulai ? 'API' : 'HCIS',
            'Created_At' => now()->subDays(2),
            'Created_By' => self::PIC,
            'Updated_At' => now(),
            'Idempotency_Key' => 'STS-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'Id_Transaksi_Permit' => $id,
            'No_Transaksi' => $nomor,
            'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(10000, 99999),
            'Plat_Nomor_Suffix' => 'ST',
            'Waktu_Mulai_Berlaku' => $mulai?->format('Y-m-d H:i:s'),
            'Waktu_Berakhir' => $akhir?->format('Y-m-d H:i:s'),
            'Tanggal_Mulai_Berlaku' => ($mulai ?? now()->subMonth())->toDateString(),
            'Tanggal_Kadaluarsa' => ($akhir ?? $kadaluarsa ?? now())->toDateString(),
        ]);

        return $nomor;
    }
}
