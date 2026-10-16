<?php

namespace Tests\Feature\SYSTEM;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\MasterData\GrupFitur\KodeGrupFitur;
use App\Services\SYSTEM\MasterData\KontakBantuan\KontakBantuanService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Production menamai grup KPI dan VISITOR sebagai PMS dan VMS (Id sama: 2 dan 4), dan dibiarkan
 * begitu (keputusan user 14 Okt 2026). Uji ini meniru production di dalam transaksi: kodenya
 * diganti, lalu halaman yang dulu 404 di sana harus terbuka.
 */
class KodeGrupFiturProduksiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_alias_kode_grup(): void
    {
        $this->assertSame('KPI', KodeGrupFitur::kanonik('pms'));
        $this->assertSame('VISITOR', KodeGrupFitur::kanonik(' VMS '));
        $this->assertSame('LMS', KodeGrupFitur::kanonik('lms'));
        $this->assertSame(['KPI', 'PMS'], KodeGrupFitur::varian('PMS'));
        $this->assertSame(['ESS'], KodeGrupFitur::varian('ess'));
    }

    public function test_menu_dan_matrix_kpi_dan_visitor_terbuka_dengan_kode_production(): void
    {
        $admin = User::query()->where('Flag_Active', 'Y')->where('Is_System_Admin', 'Y')->first();
        if (! $admin) {
            $this->markTestSkipped('Data dev tidak punya admin sistem.');
        }
        $this->tirukanProduction();

        $this->actingAs($admin)->get('/admin/kpi/menu-approval-matrix')->assertOk();
        $this->actingAs($admin)->get('/admin/visitor/menu-approval-matrix')->assertOk();
    }

    public function test_kontak_bantuan_mengenali_modul_halaman_dengan_kode_production(): void
    {
        $this->tirukanProduction();
        DB::table(KontakBantuanService::TABEL)->delete(); // dibatalkan lagi oleh transaksi uji
        DB::table(KontakBantuanService::TABEL)->insert([
            'Kode_Grup_Fitur' => 'KPI', 'Kode_Fitur' => 'UMUM', 'Nama_Kontak' => 'PIC Uji KPI',
            'Email' => 'pic.uji@contoh.id', 'Urutan_Tampil' => 0, 'Flag_Aktif' => 'Y', 'Created_By' => 'UJI',
        ]);
        $halamanKpi = DB::table('N_HRIS_Grup_Fitur_Map')->where('Id_Grup_Fitur', 2)->where('Flag_Aktif', 'Y')->value('Jenis_page');
        $this->assertNotNull($halamanKpi, 'Butuh satu halaman milik grup 2.');

        $kpi = collect(app(KontakBantuanService::class)->kelompokPusatBantuan($halamanKpi))->firstWhere('kode_grup', 'KPI');

        $this->assertNotNull($kpi);
        $this->assertTrue($kpi['relevan'], 'Halaman grup PMS harus menandai kontak KPI sebagai relevan.');
        $this->assertSame('PIC Uji KPI', $kpi['bagian'][0]['kontak'][0]['nama']);
        $this->assertSame(1, count(app(KontakBantuanService::class)->daftar('PMS', 'UMUM')));
    }

    private function tirukanProduction(): void
    {
        DB::table('N_HRIS_Master_Grup_Fitur')->where('Id_Grup_Fitur', 2)->update(['Kode_Grup_Fitur' => 'PMS']);
        DB::table('N_HRIS_Master_Grup_Fitur')->where('Id_Grup_Fitur', 4)->update(['Kode_Grup_Fitur' => 'VMS']);
    }
}
