<?php

namespace Tests\Integration\SYSTEM\MasterData\KontakBantuan;

use App\Http\Requests\VISITOR\Governance\MasterData\SaveVisitorMasterDataRequest;
use App\Services\SYSTEM\MasterData\KontakBantuan\KontakBantuanService;
use App\Services\VISITOR\Governance\MasterData\VisitorMasterDataService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Tabel kontak bantuan GLOBAL (DDL-2026-144): halaman Master Data Permit hanya
 * boleh melihat dan menyentuh baris VISITOR/PERMIT, dan nilai lingkupnya dipaku
 * service — bukan dari form.
 */
class KontakBantuanLingkupTest extends TestCase
{
    use DatabaseTransactions;

    private function barisModulLain(): int
    {
        return (int) DB::table(KontakBantuanService::TABEL)->insertGetId([
            'Kode_Grup_Fitur' => 'KPI', 'Kode_Fitur' => 'PA', 'Nama_Kontak' => 'Tim PA Uji',
            'Email' => 'pa-uji@contoh.id', 'Urutan_Tampil' => 0, 'Flag_Aktif' => 'Y',
        ], 'Id_Kontak_Bantuan');
    }

    private function svc(): VisitorMasterDataService
    {
        return app(VisitorMasterDataService::class);
    }

    public function test_membuat_kontak_dari_master_permit_terkunci_ke_visitor_permit(): void
    {
        $baru = $this->svc()->create('kontak-bantuan', [
            'Nama_Kontak' => 'Admin Permit Uji', 'Keterangan' => 'SHE', 'No_HP' => '0812-3456-7890',
            'Email' => null, 'Jam_Layanan' => 'Sen–Jum 08.00–16.00', 'Urutan_Tampil' => 1,
        ]);

        $this->assertSame('VISITOR', $baru['Kode_Grup_Fitur']);
        $this->assertSame('PERMIT', $baru['Kode_Fitur']);
        $this->assertSame('Y', $baru['Flag_Aktif']);
        $this->assertNotNull($baru['Created_By']);
    }

    public function test_daftar_master_permit_tidak_memuat_kontak_modul_lain(): void
    {
        $idLain = $this->barisModulLain();

        $ids = array_column($this->svc()->list('kontak-bantuan', ['limit' => 500])['items'], 'Id_Kontak_Bantuan');

        $this->assertNotContains($idLain, array_map('intval', $ids));
    }

    public function test_master_permit_tidak_bisa_menyunting_atau_menonaktifkan_kontak_modul_lain(): void
    {
        $idLain = $this->barisModulLain();

        try {
            $this->svc()->update('kontak-bantuan', $idLain, ['Nama_Kontak' => 'Dibajak', 'Email' => 'x@contoh.id']);
            $this->fail('Kontak modul lain tersunting dari Master Data Permit.');
        } catch (\DomainException) {
            // benar: tidak ditemukan dalam lingkup VISITOR/PERMIT
        }

        try {
            $this->svc()->changeStatus('kontak-bantuan', $idLain, 'T');
            $this->fail('Kontak modul lain dinonaktifkan dari Master Data Permit.');
        } catch (\DomainException) {
        }

        $baris = DB::table(KontakBantuanService::TABEL)->where('Id_Kontak_Bantuan', $idLain)->first();
        $this->assertSame('Tim PA Uji', $baris->Nama_Kontak);
        $this->assertSame('Y', $baris->Flag_Aktif);
    }

    public function test_halaman_permit_hanya_menerima_kontak_aktif_visitor_permit(): void
    {
        $this->barisModulLain();
        $this->svc()->create('kontak-bantuan', ['Nama_Kontak' => 'Aktif Uji', 'No_HP' => '081234567890']);
        $nonaktif = $this->svc()->create('kontak-bantuan', ['Nama_Kontak' => 'Nonaktif Uji', 'Email' => 'n@contoh.id']);
        $this->svc()->changeStatus('kontak-bantuan', (int) $nonaktif['Id_Kontak_Bantuan'], 'T');

        $nama = array_column(app(KontakBantuanService::class)->daftar('VISITOR', 'PERMIT'), 'nama');

        $this->assertContains('Aktif Uji', $nama);
        $this->assertNotContains('Nonaktif Uji', $nama);
        $this->assertNotContains('Tim PA Uji', $nama);
    }

    public function test_form_menolak_kontak_tanpa_hp_maupun_email_dan_format_salah(): void
    {
        $aturan = (new SaveVisitorMasterDataRequest)->setRouteResolver(fn () => new class
        {
            public function parameter($k, $d = null)
            {
                return $k === 'master' ? 'kontak-bantuan' : $d;
            }
        })->rules();

        $gagal = fn (array $isi) => Validator::make($isi + ['Nama_Kontak' => 'Uji'], $aturan)->fails();

        $this->assertTrue($gagal([]), 'Tanpa HP dan email harus ditolak.');
        $this->assertTrue($gagal(['No_HP' => 'abc']), 'No. HP bukan angka harus ditolak.');
        $this->assertTrue($gagal(['Email' => 'bukan-email']), 'Email tidak sah harus ditolak.');
        $this->assertFalse($gagal(['No_HP' => '+62 812-3456-7890']));
        $this->assertFalse($gagal(['Email' => 'permit@contoh.id']));
    }
}
