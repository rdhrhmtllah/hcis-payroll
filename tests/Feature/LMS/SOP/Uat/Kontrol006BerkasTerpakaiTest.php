<?php

namespace Tests\Feature\LMS\SOP\Uat;

use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Feature\LMS\SOP\Uat\Support\UatAkun;
use Tests\Feature\LMS\SOP\Uat\Support\UatDunia;
use Tests\TestCase;

/**
 * UAT-SOP-006 — daftar pemakai berkas, dan berkas terpakai menolak dihapus.
 *
 * PERINGATAN UNTUK SIAPA PUN YANG MENAMBAH UJI DI BERKAS INI: jangan pernah
 * memanggil deleteKonten() dari sebuah test. Ia menghapus berkas fisik dari
 * Google Cloud Storage dan video dari Bunny secara PERMANEN — bukan soft delete
 * — dan penghapusan itu tak bisa dibatalkan oleh DatabaseTransactions maupun
 * oleh apa pun. Sisi "menolak menghapus" karena itu diperiksa lewat pembacaan
 * kode dan dilaporkan, bukan dijalankan.
 */
class Kontrol006BerkasTerpakaiTest extends TestCase
{
    use DatabaseTransactions;

    private function dunia(): UatDunia
    {
        $admin = UatAkun::admin();
        if (! $admin) {
            $this->markTestSkipped('Tidak ada user dengan keenam hak akses halaman dokumen.');
        }

        try {
            return UatDunia::mulai((int) $admin->Id_Users)
                ->buatDokumenTerbit()
                ->buatPaket()
                ->tambahItem('Item Berkas')
                ->terbitkanPaket();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }
    }

    /**
     * Sisi yang MEMANG ada: admin bisa mengetahui siapa saja pemakai sebuah
     * berkas sebelum memutuskan apa pun. "Sudah dipakai" tanpa menyebut di mana
     * tak menolong siapa pun menimbang.
     */
    #[Group('UAT-SOP-006')]
    public function test_pemakai_sebuah_berkas_bisa_ditelusuri(): void
    {
        $d = $this->dunia();

        $konten = (int) DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Item_Versi', $d->idVersi)
            ->value('Id_Master_Konten');

        $dokumen = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Dokumen as dk', 'dk.Id_SOP_Dokumen', '=', 'v.Id_SOP_Dokumen')
            ->where('v.Id_Master_Konten', $konten)
            ->where('dk.Flag_Aktif', SopStatus::AKTIF)
            ->distinct()
            ->pluck('dk.Id_SOP_Dokumen')
            ->map(fn ($x) => (int) $x)
            ->all();

        $this->assertContains($d->idDokumen, $dokumen,
            'Dokumen uji tidak muncul sebagai pemakai berkasnya sendiri, sehingga admin '
            .'yang hendak menghapus berkas itu tak akan tahu ada yang memakainya.');
    }

    /*
     * SISI "MENOLAK MENGHAPUS" ADA DI HapusKontenAmanTest.
     *
     * Celah yang semula dilaporkan di sini sudah ditutup: deleteKonten kini
     * memeriksa pemakaian lintas modul dan membatasi penghapusan berkas fisik
     * pada scope-nya sendiri.
     *
     * Uji penjaganya sengaja TIDAK dipindahkan ke sini, karena ia butuh
     * Storage::fake — dan uji yang berbagi berkas dengan uji lain yang menulis
     * ke penyimpanan sungguhan adalah undangan untuk suatu hari lupa memasang
     * fake-nya.
     *
     * Catatan untuk yang menulis penjaga serupa nanti: versi pertama penjaga di
     * sini MEMINDAI KODE SUMBER deleteKonten untuk mencari nama tabel, dengan
     * maksud gagal begitu celahnya ditutup. Ia tidak pernah menyala — karena
     * perbaikannya diletakkan di metode pembantu di luar jendela teks yang
     * dipindai. Penjaga yang memeriksa PERILAKU tak punya kelemahan itu.
     */
}
