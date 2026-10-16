<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopPaketService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Paket SOP tak lagi menuntut Kategori.
 *
 * ATURAN DUA PINTU. Penjaga "Kategori tidak valid" dulu berdiri di DUA tempat
 * yang setara — storePaket dan updatePaket. Mencabutnya di satu pintu saja
 * meninggalkan yang lain, dan gejalanya baru muncul pada admin yang menyunting
 * paket lama, bukan pada yang membuat paket baru. Karena itu kedua pintu diuji,
 * dan diuji lewat EFEK (permintaan yang benar-benar diterima), bukan lewat
 * pemanggilan.
 *
 * Kolomnya sendiri tetap ada di database. Yang dicabut adalah kewajiban
 * mengisinya, bukan tempat menyimpannya.
 */
class SopPaketTanpaKategoriTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('http://localhost');
    }

    private function adminBuilder(): User
    {
        $uid = DB::table('HRIS_Page_Access')->where('Flag_Aktif', 'Y')
            ->where('Jenis_page', 'menuSopBuilderPage')
            ->whereNotNull('UserID_Web')->value('UserID_Web');

        $u = $uid ? User::find($uid) : null;
        if (! $u) {
            $this->markTestSkipped('Tak ada user dengan menuSopBuilderPage.');
        }

        return $u;
    }

    private function nama(): string
    {
        return 'TANPA-KAT '.substr(md5(uniqid('', true)), 0, 8);
    }

    /** PINTU 1 — paket baru lahir tanpa kategori. Dulu ditolak 400. */
    public function test_pintu_simpan_menerima_paket_tanpa_kategori(): void
    {
        $nama = $this->nama();

        $res = $this->actingAs($this->adminBuilder())
            ->postJson('/api/v1/sop/admin/builder/paket/store', ['Nama_Paket' => $nama]);

        $res->assertSuccessful();

        $baris = DB::table('N_SOP_Master_Paket')->where('Nama_Paket', $nama)->first();
        $this->assertNotNull($baris, 'Paket tak benar-benar tersimpan.');
        $this->assertNull($baris->Id_Kategori_SOP,
            'Paket baru masih diisi kategori. Penulisannya belum berhenti.');
    }

    /** PINTU 2 — paket yang sudah ada bisa disunting tanpa mengirim kategori. */
    public function test_pintu_perbarui_menerima_paket_tanpa_kategori(): void
    {
        $admin = $this->adminBuilder();
        $nama = $this->nama();

        $id = app(SopPaketService::class)->create(['Nama_Paket' => $nama], (int) $admin->Id_Users);
        $this->assertGreaterThan(0, $id, 'Fikstur gagal membuat paket — uji ini tak menguji apa pun.');

        $hash = Hashids::connection('custom')->encode($id);

        $this->actingAs($admin)
            ->putJson("/api/v1/sop/admin/builder/paket/update/{$hash}", [
                'Nama_Paket' => $nama.' (ubah)',
            ])
            ->assertSuccessful();

        $this->assertSame($nama.' (ubah)',
            DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $id)->value('Nama_Paket'));
    }

    /** Pemilih kategori di Builder ikut hilang. */
    public function test_options_kategori_sudah_tidak_ada(): void
    {
        $this->actingAs($this->adminBuilder())
            ->getJson('/api/v1/sop/admin/builder/options/kategori')
            ->assertNotFound();
    }

    /**
     * Baris paket tak lagi membawa kategori ke layar.
     *
     * Penjaga kerja-nyata di baris pertama bukan hiasan: daftar kosong membuat
     * seluruh asersi "properti tidak ada" lolos dengan sempurna tanpa pernah
     * menyentuh satu baris pun.
     */
    public function test_baris_paket_tak_lagi_membawa_kategori(): void
    {
        [$rows] = app(SopPaketService::class)->paginate([], 1, 10);

        $this->assertNotEmpty($rows, 'Daftar paket kosong — uji ini tak menguji apa pun.');

        $baris = $rows->first();
        $this->assertObjectNotHasProperty('Nama_Kategori', $baris);
        $this->assertObjectNotHasProperty('Id_Kategori_SOP', $baris);
        $this->assertObjectHasProperty('Nama_Paket', $baris, 'Bentuk barisnya rusak, bukan sekadar kehilangan kategori.');
    }
}
