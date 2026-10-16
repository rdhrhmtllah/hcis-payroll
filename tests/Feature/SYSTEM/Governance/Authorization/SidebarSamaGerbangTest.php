<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Application\Shell\InertiaShellService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RBAC Fase 1: menu yang tampil = halaman yang lolos gerbang. Diukur 9 Okt
 * 2026 di dev: 4 user bisa membuka 37 halaman yang tak tampil, System Admin
 * melihat 12 menu yang ditolak gerbang.
 */
class SidebarSamaGerbangTest extends TestCase
{
    public function test_sidebar_sama_dengan_gerbang_untuk_semua_pemegang_role(): void
    {
        $shell = app(InertiaShellService::class);
        $tampil = new \ReflectionMethod($shell, 'menuIsVisible');
        $tampil->setAccessible(true);

        $menus = DB::table('N_HRIS_Menus')->get(['Jenis_page', 'Is_Global_Menu']);
        $ids = DB::table('N_HRIS_Role_User')->where('Flag_Aktif', 'Y')->pluck('UserId_Web')
            ->merge(DB::table('KPI_Users')->where('Is_System_Admin', 'Y')->pluck('Id_Users'))->unique();

        // Rumus spec ditulis ulang DI SINI (bukan memanggil KeputusanAkses), supaya
        // uji tidak membandingkan fungsi dengan dirinya sendiri: tampil =
        // System Admin ATAU memilikiJabatan. Plus jaminan kompatibilitas: tak
        // satu pun menu yang dulu tampil (resolver lama) kini hilang.
        $selisih = [];
        $hilang = [];
        foreach ($ids as $id) {
            $u = User::where('Id_Users', $id)->first();
            if (! $u) {
                continue;
            }
            $sa = ($u->Is_System_Admin ?? null) === 'Y';
            foreach ($menus as $m) {
                // Menu bergerbang khusus (bukan check.jabatan): dasbor DB, Akses & Peran (SYSTEM & per modul).
                if (in_array($m->Jenis_page, ['systemDatabaseChangeDashboardPage'], true) || preg_match('/AksesPeranPage$/i', (string) $m->Jenis_page)) {
                    continue;
                }
                $global = strtoupper(trim((string) $m->Is_Global_Menu)) === 'Y';
                $harap = $global || $sa || $u->memilikiJabatan((string) $m->Jenis_page);
                $kini = $tampil->invoke($shell, $m, $u);
                if ($kini !== $harap) {
                    $selisih[] = "#{$id} {$m->Jenis_page} (harap=".($harap ? 'ya' : 'tidak').')';
                }
                if (! $kini && \App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationScopeResolver::canAccessPage($u, (string) $m->Jenis_page)) {
                    $hilang[] = "#{$id} {$m->Jenis_page}";
                }
            }
        }

        $this->assertSame([], $hilang, "Menu yang dulu tampil kini hilang:\n".implode("\n", array_slice($hilang, 0, 30)));
        $this->assertSame([], $selisih, "Sidebar berselisih dengan gerbang:\n".implode("\n", array_slice($selisih, 0, 30)));
    }
}
