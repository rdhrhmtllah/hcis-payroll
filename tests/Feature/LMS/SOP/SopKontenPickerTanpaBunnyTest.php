<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Memilih BERKAS di Master Dokumen SOP tak boleh menuntut kredensial VIDEO.
 *
 * Daftar konten membangun BunnyStreamService di muka, padahal layanan itu hanya
 * dipakai pada cabang untuk baris video pihak ketiga. Konstruktornya menugaskan
 * config('services.lms.bunny.api_key') — yang sengaja tak punya nilai default,
 * karena kredensialnya pernah ikut tersimpan di riwayat git — ke properti
 * bertipe string. Di lingkungan tanpa BUNNY_API_KEY, itu berarti kategori
 * BERISI DOKUMEN SAJA pun gagal 500, dan admin tak bisa melampirkan satu pun
 * berkas PDF ke SOP.
 */
class SopKontenPickerTanpaBunnyTest extends TestCase
{
    private function adminAtauSkip(): User
    {
        $u = User::whereNotNull('Id_Users')->get()
            ->first(fn ($x) => $x->memilikiJabatan('menuSopDokumenPage'));

        if (! $u) {
            $this->markTestSkipped('Belum ada user dengan akses menuSopDokumenPage.');
        }

        return $u;
    }

    public function test_daftar_konten_terbuka_tanpa_kredensial_bunny(): void
    {
        $admin = $this->adminAtauSkip();

        /* Kunci dikosongkan SECARA SENGAJA di dalam tes ini: inilah keadaan
           lingkungan yang membuat picker mati, dan satu-satunya cara
           membuktikan perbaikannya adalah menirunya. */
        config(['services.lms.bunny.api_key' => null]);

        $kategori = DB::table('N_LMS_Kategori_Konten')->where('Flag_Aktif', 'Y')
            ->value('Id_Kategori_Konten');
        if (! $kategori) {
            $this->markTestSkipped('Tak ada kategori konten.');
        }

        $this->actingAs($admin)
            ->getJson('/sop/admin/konten/kategori')
            ->assertOk();

        $this->actingAs($admin)
            ->getJson('/sop/admin/konten/konten/'.Hashids::connection('custom')->encode($kategori))
            ->assertOk();
    }
}
