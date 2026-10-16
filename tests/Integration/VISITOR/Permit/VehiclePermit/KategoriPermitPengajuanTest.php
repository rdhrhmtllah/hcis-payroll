<?php

namespace Tests\Integration\VISITOR\Permit\VehiclePermit;

use App\Services\VISITOR\Permit\VehiclePermit\KategoriPermitPengajuan;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitMasterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class KategoriPermitPengajuanTest extends TestCase
{
    use DatabaseTransactions;

    private function buatKategori(?string $muncul, string $aktif = 'Y'): int
    {
        return (int) DB::table('N_EMI_VISITOR_Master_Kategori_Permit')->insertGetId([
            'Nama_Kategori' => 'UJI '.Str::upper(Str::random(8)),
            'Deskripsi_Akses' => 'Kategori uji',
            'Kode_Warna_UI' => '#6366F1',
            'Flag_Aktif' => $aktif,
            'Flag_Muncul_Pengajuan' => $muncul,
        ]);
    }

    public function test_temukan_menerima_kategori_yang_muncul_dan_null(): void
    {
        $this->assertNotNull(KategoriPermitPengajuan::temukan($this->buatKategori('Y')));
        $this->assertNotNull(KategoriPermitPengajuan::temukan($this->buatKategori(null)));
    }

    public function test_temukan_menolak_kategori_tersembunyi_dan_nonaktif(): void
    {
        $this->assertNull(KategoriPermitPengajuan::temukan($this->buatKategori('T')));
        $this->assertNull(KategoriPermitPengajuan::temukan($this->buatKategori('Y', 'T')));
    }

    public function test_coklat_tidak_pernah_ditemukan(): void
    {
        $idCoklat = (int) DB::table('N_EMI_VISITOR_Master_Kategori_Permit')->where('Nama_Kategori', 'COKLAT')->value('Id_Kategori_Permit');

        $this->assertGreaterThan(0, $idCoklat, 'Master COKLAT tidak ada di DB development.');
        $this->assertNull(KategoriPermitPengajuan::temukan($idCoklat));
    }

    public function test_master_pengajuan_hanya_berisi_kategori_yang_boleh_diajukan(): void
    {
        $tersembunyi = $this->buatKategori('T');
        $master = app(VisitorPermitMasterService::class)->untukPengajuan();

        $this->assertSame(
            ['plat_codes', 'vehicle_types', 'models', 'colors', 'kategoris', 'durations'],
            array_keys($master),
        );
        $this->assertNotContains(
            $tersembunyi,
            collect($master['kategoris'])->pluck('Id_Kategori_Permit')->map(fn ($v) => (int) $v)->all(),
        );
    }

    public function test_master_pengajuan_tidak_menawarkan_model_berjenis_non_permit(): void
    {
        // Model di bawah jenis khusus tamu atau jenis nonaktif ditolak server
        // saat disimpan; menawarkannya di dropdown hanya menunda kegagalan.
        $tipeTamu = (int) DB::table('N_EMI_VISITOR_Master_Vehicle_Type')->insertGetId(['Nama_Tipe_Kendaraan' => 'UJI TAMU', 'Akses' => 'TAMU', 'Flag_Aktif' => 'Y']);
        $tipeMati = (int) DB::table('N_EMI_VISITOR_Master_Vehicle_Type')->insertGetId(['Nama_Tipe_Kendaraan' => 'UJI MATI', 'Akses' => 'ALL', 'Flag_Aktif' => 'T']);
        $modelTamu = (int) DB::table('N_EMI_VISITOR_Master_Vehicle_Model')->insertGetId(['Id_Master_Tipe' => $tipeTamu, 'Nama_Model_Kendaraan' => 'Model Uji Tamu', 'Flag_Aktif' => 'Y']);
        $modelMati = (int) DB::table('N_EMI_VISITOR_Master_Vehicle_Model')->insertGetId(['Id_Master_Tipe' => $tipeMati, 'Nama_Model_Kendaraan' => 'Model Uji Mati', 'Flag_Aktif' => 'Y']);

        $ids = collect(app(VisitorPermitMasterService::class)->untukPengajuan()['models'])
            ->pluck('Id_Vehicle_Model')->map(fn ($v) => (int) $v)->all();

        $this->assertNotContains($modelTamu, $ids);
        $this->assertNotContains($modelMati, $ids);
        $this->assertContains(3, $ids, 'Model biasa (Avanza, mobil penumpang) ikut hilang.');
    }
}
