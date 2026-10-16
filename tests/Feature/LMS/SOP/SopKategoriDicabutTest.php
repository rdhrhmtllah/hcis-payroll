<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Kategori dicabut dari modul DMS.
 *
 * Halaman masternya dibongkar, endpoint CRUD-nya hilang, dan tak ada lagi
 * pintu untuk mengelolanya. Yang TIDAK dikerjakan: tabel N_SOP_Kategori dan
 * kolom N_SOP_Master_Paket.Id_Kategori_SOP dibiarkan berdiri apa adanya —
 * riwayat tetap terbaca, dan itu satu-satunya jalan pulang kalau kategori
 * ternyata dibutuhkan lagi.
 *
 * Uji ini juga yang menjaga pencabutannya tidak diam-diam dibatalkan dengan
 * cara menghidupkan lagi rutenya tanpa menghidupkan yang lain.
 */
class SopKategoriDicabutTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('http://localhost');
    }

    private function adminSop(): User
    {
        $uid = DB::table('HRIS_Page_Access')->where('Flag_Aktif', 'Y')
            ->where('Jenis_page', 'menuSopBuilderPage')
            ->whereNotNull('UserID_Web')->value('UserID_Web');

        $u = $uid ? User::find($uid) : null;
        if (! $u) {
            $this->markTestSkipped('Tak ada user admin SOP.');
        }

        return $u;
    }

    /**
     * Tak ada satu pun rute yang tersisa menyebut kategori admin.
     *
     * Diuji pada tabel rute, bukan lewat permintaan HTTP satu per satu: yang
     * dijaga adalah TIDAK ADANYA rute, dan mendaftar lima permintaan hanya
     * membuktikan lima alamat itu — rute keenam yang tertinggal lolos.
     */
    public function test_tak_ada_rute_kategori_admin_yang_tersisa(): void
    {
        $tersisa = [];
        foreach (Route::getRoutes() as $r) {
            $uri = $r->uri();
            if (str_contains($uri, 'sop/admin/kategori')) {
                $tersisa[] = $r->methods()[0].' '.$uri;
            }
        }

        $this->assertSame([], $tersisa,
            'Masih ada rute kategori admin: '.implode(', ', $tersisa));
    }

    /** Halaman dan endpoint CRUD-nya benar-benar tak bisa dicapai. */
    public function test_halaman_dan_endpoint_kategori_mengembalikan_404(): void
    {
        $user = $this->adminSop();

        $this->actingAs($user)->get('/sop/admin/kategori')->assertNotFound();
        $this->actingAs($user)->getJson('/api/v1/sop/admin/kategori/current')->assertNotFound();
        $this->actingAs($user)->postJson('/api/v1/sop/admin/kategori/store', [
            'Nama_Kategori' => 'Percobaan',
        ])->assertNotFound();
    }

    /** Endpoint pemilih kategori di Builder ikut hilang. */
    public function test_options_kategori_builder_sudah_tidak_ada(): void
    {
        $this->actingAs($this->adminSop())
            ->getJson('/api/v1/sop/admin/builder/options/kategori')
            ->assertNotFound();
    }

    /**
     * Datanya SENGAJA dibiarkan berdiri.
     *
     * Kalau asersi ini merah, seseorang menghapus tabel atau kolomnya — dan
     * itu justru yang paling tidak boleh terjadi pada pencabutan ini.
     */
    public function test_tabel_dan_kolom_kategori_tidak_ikut_dihapus(): void
    {
        $this->assertTrue(
            DB::getSchemaBuilder()->hasTable('N_SOP_Kategori'),
            'Tabel N_SOP_Kategori ikut terhapus. Pencabutan ini tidak boleh menyentuh skema.');

        $this->assertTrue(
            DB::getSchemaBuilder()->hasColumn('N_SOP_Master_Paket', 'Id_Kategori_SOP'),
            'Kolom Id_Kategori_SOP ikut terhapus. Riwayat paket lama jadi tak terbaca.');
    }
}
