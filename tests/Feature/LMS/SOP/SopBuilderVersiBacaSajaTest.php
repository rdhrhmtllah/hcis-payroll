<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Builder tak lagi bisa menulis versi — dan penjaganya ikut pindah, bukan hilang.
 *
 * Menggantikan SopBuilderTerbitVersiTest, yang dulu menjaga kebalikannya: bahwa
 * rute terbit versi ADA di Builder. Rute itu lahir untuk memecah jalan buntu
 * bersegi tiga pada rilis pertama sebuah paket — publish paket menolak selama
 * ada versi Draft, sementara satu-satunya penerbitnya ada di halaman lain.
 *
 * Jalan buntu itu tak bisa terjadi lagi: item hanya boleh menunjuk dokumen yang
 * SUDAH punya versi berlaku (pastikanDokumenSiap), jadi paket tak pernah lagi
 * menggendong versi draft milik dokumen yang belum sah.
 *
 * Dua hal yang dijaga di sini:
 *  1. Tak ada satu pun jalur tulis versi yang tersisa di Builder.
 *  2. Penjaga "tak boleh terbit tanpa penanda tangan" tetap berdiri — di pintu
 *     Master. Mencabut rute tanpa memastikan ini berarti menghapus penjaganya
 *     sekalian, dan tak ada yang akan tahu sampai ada SOP terbit tanpa tanda
 *     tangan siapa pun.
 */
class SopBuilderVersiBacaSajaTest extends TestCase
{
    use DatabaseTransactions;

    private function adminBuilder(): User
    {
        return $this->adminBerkunci('menuSopBuilderPage');
    }

    private function adminDokumen(): User
    {
        return $this->adminBerkunci('menuSopDokumenPage');
    }

    private function adminBerkunci(string $kunci): User
    {
        $uid = DB::table('HRIS_Page_Access')
            ->where('Flag_Aktif', 'Y')
            ->where('Jenis_page', $kunci)
            ->whereNotNull('UserID_Web')
            ->value('UserID_Web');

        $u = $uid ? User::find($uid) : null;
        if (! $u) {
            $this->markTestSkipped("Tak ada user dengan {$kunci}.");
        }

        return $u;
    }

    private function enc($id): string
    {
        return Hashids::connection('custom')->encode((int) $id);
    }

    /** Versi DRAFT milik dokumen, berkas ada, TANPA pengesah. */
    private function draftTanpaPengesah(): object
    {
        $dok = DB::table('N_SOP_Dokumen')->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Tipe', 'DOKUMEN')->orderByDesc('Id_SOP_Dokumen')->first(['Id_SOP_Dokumen']);
        $konten = DB::table('N_LMS_Master_Konten as m')
            ->join('N_LMS_Master_Konten_Berkas as b', 'b.Id_Master_Konten', '=', 'm.Id_Master_Konten')
            ->where('m.Scope', 'SOP')->whereIn('b.Status_Verifikasi', ['SIAP', 'LAMA'])
            ->where(fn ($q) => $q->where('b.Ext', 'pdf')->orWhere('b.Type_File', 'like', '%pdf%'))
            ->value('m.Id_Master_Konten');

        if (! $dok || ! $konten) {
            $this->markTestSkipped('Butuh satu dokumen dan satu konten PDF terverifikasi.');
        }

        $next = ((int) DB::table('N_SOP_Item_Versi')->where('Id_SOP_Dokumen', $dok->Id_SOP_Dokumen)
            ->max('Versi_Ke')) + 1;

        $id = DB::table('N_SOP_Item_Versi')->insertGetId([
            'Id_SOP_Paket_Item' => null,
            'Id_SOP_Dokumen' => $dok->Id_SOP_Dokumen,
            'Versi_Ke' => $next,
            'Status' => SopStatus::PAKET_DRAFT,
            'Id_Master_Konten' => $konten,
            'Created_By' => 1,
            'Created_At' => now(),
            'Flag_Aktif' => SopStatus::AKTIF,
        ], 'Id_SOP_Item_Versi');

        return (object) ['Id_SOP_Item_Versi' => (int) $id, 'Id_SOP_Dokumen' => (int) $dok->Id_SOP_Dokumen];
    }

    /* ── rutenya sudah tak ada ─────────────────────────────────────── */

    public function test_rute_tulis_versi_builder_dicabut(): void
    {
        $uri = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($r) => strtoupper(implode('|', $r->methods())).' '.$r->uri());

        $terlarang = [
            'api/v1/sop/admin/berkas/item/{idItem}/versi/store',
            'api/v1/sop/admin/berkas/versi/{id}/ready',
            'api/v1/sop/admin/berkas/versi/{id}/publish',
            'api/v1/sop/admin/berkas/versi/{id}/rollback',
        ];

        foreach ($terlarang as $rute) {
            $this->assertFalse($uri->contains(fn ($u) => str_ends_with($u, ' '.$rute)),
                "Rute tulis versi {$rute} masih terdaftar di Builder — siklus versi bocor keluar dari Master.");
        }

        // DELETE versi juga dicabut; GET konten pada URI yang sama TETAP ada.
        $this->assertFalse($uri->contains('DELETE api/v1/sop/admin/berkas/versi/{id}'),
            'Hapus versi masih bisa dilakukan dari Builder.');
        $this->assertTrue($uri->contains(fn ($u) => str_contains($u, 'api/v1/sop/admin/berkas/versi/{id}/konten')),
            'Membaca konten versi ikut hilang — panel versi jadi tak bisa menampilkan apa pun.');
    }

    /**
     * Bukan cuma daftar rutenya: permintaannya benar-benar tak sampai ke mana pun.
     *
     * Diperiksa terpisah karena rute bisa saja terdaftar dengan URI berbeda —
     * daftar yang bersih tak membuktikan pintunya tertutup.
     */
    public function test_permintaan_terbit_versi_dari_builder_tak_sampai_ke_mana_pun(): void
    {
        URL::forceRootUrl('http://localhost');

        $v = $this->draftTanpaPengesah();
        $hash = $this->enc($v->Id_SOP_Item_Versi);

        $this->actingAs($this->adminBuilder())
            ->patchJson("/api/v1/sop/admin/berkas/versi/{$hash}/publish")
            ->assertStatus(404);

        $this->assertSame(SopStatus::PAKET_DRAFT,
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $v->Id_SOP_Item_Versi)->value('Status'),
            'Statusnya bergeser padahal rutenya sudah tak ada.');
    }

    /* ── penjaganya tetap berdiri, di pintu Master ─────────────────── */

    public function test_versi_tanpa_pengesah_tetap_ditolak_di_master(): void
    {
        URL::forceRootUrl('http://localhost');

        $v = $this->draftTanpaPengesah();

        $res = $this->actingAs($this->adminDokumen())
            ->postJson("/api/v1/sop/admin/dokumen/versi/{$this->enc($v->Id_SOP_Item_Versi)}/terbitkan");

        $res->assertStatus(422);
        $this->assertStringContainsString('pengesah', strtolower((string) $res->json('message')));

        $this->assertSame(SopStatus::PAKET_DRAFT,
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $v->Id_SOP_Item_Versi)->value('Status'),
            'Statusnya tergeser padahal penerbitannya ditolak.');
    }
}
