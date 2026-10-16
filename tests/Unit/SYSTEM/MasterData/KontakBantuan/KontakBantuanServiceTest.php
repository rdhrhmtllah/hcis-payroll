<?php

namespace Tests\Unit\SYSTEM\MasterData\KontakBantuan;

use App\Services\SYSTEM\MasterData\KontakBantuan\KontakBantuanService;
use App\Services\VISITOR\Governance\MasterData\VisitorMasterDataCatalog;
use PHPUnit\Framework\TestCase;

class KontakBantuanServiceTest extends TestCase
{
    public function test_nomor_diubah_ke_format_wa_me(): void
    {
        $this->assertSame('6281234567890', KontakBantuanService::nomorInternasional('0812-3456-7890'));
        $this->assertSame('6281234567890', KontakBantuanService::nomorInternasional('+62 812 3456 7890'));
        $this->assertSame('6281234567890', KontakBantuanService::nomorInternasional('6281234567890'));
        $this->assertNull(KontakBantuanService::nomorInternasional('---'));
    }

    public function test_master_kontak_bantuan_visitor_berlingkup_permit_di_tabel_global(): void
    {
        $definisi = VisitorMasterDataCatalog::get('kontak-bantuan');

        $this->assertSame(KontakBantuanService::TABEL, $definisi['table']);
        $this->assertSame(['Kode_Grup_Fitur' => 'VISITOR', 'Kode_Fitur' => 'PERMIT'], $definisi['scope']);
        $this->assertContains('kontak-bantuan', VisitorMasterDataCatalog::keysDomain('permit'));
        $this->assertNotContains('kontak-bantuan', VisitorMasterDataCatalog::keysDomain('tamu'));

        // Kolom lingkup TIDAK boleh bisa disunting dari form: nilainya dipaku service.
        $kolomForm = array_column($definisi['fields'], 'key');
        $this->assertNotContains('Kode_Grup_Fitur', $kolomForm);
        $this->assertNotContains('Kode_Fitur', $kolomForm);
    }
}
