<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\ModeAkses;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RBAC Fase 5 (5a): layar lama selama masa pensiun — memberi tahu modul yang
 * sudah BARU, dan tidak merusak peta modul yang kini juga menentukan pemilik
 * halaman (ModeAkses).
 */
class LayarLamaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        ModeAkses::lupakan();
        KeputusanAkses::lupakanCache();
    }

    private function sa(): User
    {
        return User::where('Id_Users', 4)->firstOrFail();
    }

    private function peta(int $grup)
    {
        return DB::table('N_HRIS_Grup_Fitur_Map')->where('Id_Grup_Fitur', $grup)->orderBy('Jenis_page')
            ->get(['Jenis_page', 'Flag_Hibah_Role', 'Flag_Aktif', 'Sort_Order', 'Is_Default_Landing', 'Is_Show_Sidebar'])
            ->map(fn ($r) => (array) $r)->all();
    }

    /** Simpan ulang daftar yang sama tidak boleh menghidupkan hibah yang sengaja dimatikan (DDL-2026-110). */
    public function test_simpan_binding_mempertahankan_bendera_baris_lama(): void
    {
        $sebelum = $this->peta(4);
        $halaman = array_column($sebelum, 'Jenis_page');

        $this->actingAs($this->sa())->postJson('/api/v1/system-auth/menu-binding/4', ['pages' => $halaman])->assertOk();

        $this->assertSame($sebelum, $this->peta(4), 'Simpan binding mengubah bendera/urutan baris yang sudah ada.');
    }

    public function test_binding_baru_langsung_mengubah_pemilik_halaman(): void
    {
        $baru = (string) DB::table('N_HRIS_Menus')->whereNotIn('Jenis_page', DB::table('N_HRIS_Grup_Fitur_Map')->select('Jenis_page'))->value('Jenis_page');
        $this->assertNotSame('', $baru);
        $this->assertNull(app(ModeAkses::class)->modulHalaman($baru), 'Prasyarat: halaman belum terikat.');
        $halaman = DB::table('N_HRIS_Grup_Fitur_Map')->where('Id_Grup_Fitur', 7)->pluck('Jenis_page')->push($baru)->all();

        $this->actingAs($this->sa())->postJson('/api/v1/system-auth/menu-binding/7', ['pages' => $halaman])->assertOk();

        $this->assertSame(7, app(ModeAkses::class)->modulHalaman($baru), 'Peta pemilik halaman basi setelah binding berubah.');
    }

    public function test_layar_lama_membawa_daftar_modul_baru(): void
    {
        DB::table('N_HRIS_Akses_Mode_Modul')->updateOrInsert(['Id_Grup_Fitur' => 7], ['Mode' => 'BARU']);
        ModeAkses::lupakan();

        foreach (['/admin/system-manager', '/admin/role-manager', '/page-access'] as $url) {
            $props = $this->actingAs($this->sa())->get($url)->assertOk()->viewData('page')['props'];
            $this->assertContains('Document Management System', $props['modulBaru'] ?? [], "Banner modul BARU tidak dikirim ke {$url}.");
        }
    }
}
