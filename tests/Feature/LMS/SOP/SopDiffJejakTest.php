<?php

namespace Tests\Feature\LMS\SOP;

use App\Http\Controllers\LMS\SOP\Governance\SopAdminBuilderController;
use App\Services\LMS\SOP\Support\SopDiff;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Jejak audit menyebut APA yang berubah, bukan sekadar BAHWA sesuatu berubah.
 *
 * "Item X diubah" adalah stempel waktu, bukan jejak audit: ia tak menjawab satu
 * pun pertanyaan yang membuat orang membuka riwayat ("kenapa mode aksesnya jadi
 * VIEW_ONLY?"). Master Dokumen sudah menyimpan pasangan lama→baru sejak awal;
 * Builder tidak — dan justru di Builder-lah struktur paket disunting.
 */
class SopDiffJejakTest extends TestCase
{
    use DatabaseTransactions;

    /* ── pembandingnya sendiri ─────────────────────────────────────── */

    public function test_hanya_kolom_yang_benar_benar_berubah_yang_dicatat(): void
    {
        $beda = SopDiff::antara(
            ['Judul_Item' => 'Lama', 'Mode_Akses' => 'VIEW_ONLY', 'Urutan' => 3],
            ['Judul_Item' => 'Baru', 'Mode_Akses' => 'VIEW_ONLY'],
        );

        $this->assertCount(1, $beda);
        $this->assertSame('Judul item', $beda[0]['field'], 'Label mentah bocor ke layar admin.');
        $this->assertSame('Lama', $beda[0]['lama']);
        $this->assertSame('Baru', $beda[0]['baru']);
    }

    /**
     * 1 dari form dan '1' dari SQL Server adalah nilai yang SAMA. Membandingkan
     * apa adanya akan melaporkan perubahan pada setiap penyimpanan, dan jejak
     * yang selalu berisi perubahan palsu tak lagi dibaca siapa pun.
     */
    public function test_beda_tipe_dengan_nilai_sama_bukan_perubahan(): void
    {
        $this->assertSame([], SopDiff::antara(['Urutan' => 1], ['Urutan' => '1']));
        $this->assertSame([], SopDiff::antara(['Deskripsi' => null], ['Deskripsi' => '']));
    }

    public function test_kolom_yang_tak_dikirim_pemanggil_tak_dianggap_dikosongkan(): void
    {
        // Payload update biasanya cuma memuat field yang disunting. Menganggap
        // sisanya "dihapus" akan melaporkan penghapusan yang tak pernah terjadi.
        $this->assertSame([], SopDiff::antara(
            ['Judul_Item' => 'Tetap', 'Deskripsi_Item' => 'Ada'],
            ['Judul_Item' => 'Tetap'],
        ));
    }

    public function test_kalimatnya_menyebut_field_dan_mengakui_simpan_kosong(): void
    {
        $beda = SopDiff::antara(['Judul_Item' => 'A'], ['Judul_Item' => 'B']);

        $this->assertStringContainsString('Judul item', SopDiff::kalimat('Item "X"', $beda));
        /* Menekan simpan tanpa mengubah apa pun adalah fakta audit yang sah;
           menyembunyikannya membuat jejaknya seolah orang itu tak pernah
           membuka layarnya. */
        $this->assertStringContainsString('tanpa perubahan', SopDiff::kalimat('Item "X"', []));
    }

    /* ── dan diff-nya sampai ke layar ──────────────────────────────── */

    /**
     * Diff yang tersimpan tapi tak pernah turun ke layar sama tak bergunanya
     * dengan diff yang tak pernah ditulis.
     */
    public function test_riwayat_menurunkan_daftar_perubahan_bukan_meta_mentah(): void
    {
        $idPaket = DB::table('N_SOP_Log')->whereNotNull('Id_SOP_Paket')->value('Id_SOP_Paket');
        if (! $idPaket) {
            $this->markTestSkipped('Belum ada jejak paket.');
        }

        $res = app(SopAdminBuilderController::class)->riwayatPaket(
            Request::create('/x', 'GET', ['limit' => 5]),
            Hashids::connection('custom')->encode((int) $idPaket),
        );
        $rows = json_decode($res->getContent(), true)['result']['rows'] ?? [];

        $this->assertNotEmpty($rows);
        foreach ($rows as $r) {
            $this->assertArrayHasKey('Perubahan', $r);
            $this->assertIsArray($r['Perubahan']);
            /* Meta_JSON adalah kantong serba-guna berisi id internal dan bendera
               teknis. Menurunkannya utuh membocorkan bentuk dalam sekaligus
               menyerahkan penafsirannya ke browser. */
            $this->assertArrayNotHasKey('Meta_JSON', $r);
        }
    }

    /**
     * Master dan Builder harus menghasilkan BENTUK yang sama.
     *
     * Dua bentuk Meta_JSON untuk satu pertanyaan memaksa layar riwayat mengenali
     * keduanya — persis cara satu aturan pelan-pelan jadi dua aturan yang
     * menyimpang. Master kini meneruskan ke SopDiff, bukan menyalinnya.
     */
    public function test_master_dan_builder_memakai_pembanding_yang_sama(): void
    {
        $m = new \ReflectionMethod(\App\Services\LMS\SOP\SopDokumenService::class, 'bedaNilai');
        $m->setAccessible(true);

        $lama = ['Judul' => 'A', 'Nomor_SOP_Resmi' => 'X-1'];
        $baru = ['Judul' => 'B', 'Nomor_SOP_Resmi' => 'X-1'];

        $this->assertSame(
            SopDiff::antara($lama, $baru),
            $m->invoke(app(\App\Services\LMS\SOP\SopDokumenService::class), $lama, $baru),
        );
    }
}
