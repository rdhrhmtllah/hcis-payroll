<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopPaketService;
use App\Services\LMS\SOP\Support\SopGerbangPublikKuis;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PAKET PUBLIK TIDAK BOLEH MEMBAWA ITEM KUIS (keputusan produk 10 Sep 2026).
 *
 * Aturan ini MEMBALIK perilaku sebelumnya — kombinasi itu dulu didukung penuh
 * lewat pendaftaran mandiri. Karena itu berkas ini menguji dua arah sekaligus:
 * yang dilarang benar-benar ditolak, DAN yang sah tidak ikut tertahan. Penjaga
 * yang hanya diuji pada kasus larangannya bisa saja menolak segalanya tanpa ada
 * yang tahu.
 *
 * Pintunya DUA, dan keduanya harus dijaga: publish langsung
 * (SopPaketService::updateStatus) dan publish lewat working draft
 * (SopPaketDraftService::publish → validationErrors). Aturan yang hidup di satu
 * pemanggil sementara pemanggil lain memakai jalurnya sendiri sudah dua kali
 * bocor di modul ini — dan pintu yang tak terjaga selalu yang lebih sering
 * dilewati.
 */
class SopPaketPublikKuisTest extends TestCase
{
    use DatabaseTransactions;

    private function paket(): SopPaketService
    {
        return app(SopPaketService::class);
    }

    /** Paket DRAFT beserta satu item; kembalikan id paketnya. */
    private function buatPaket(string $modeDistribusi, string $tipeItem, string $judulItem): int
    {
        $idPaket = DB::table('N_SOP_Master_Paket')->insertGetId([
            'Nama_Paket' => 'UJI PUBLIK-KUIS '.substr((string) microtime(true), -6),
            'Status' => SopStatus::PAKET_DRAFT,
            'Mode_Distribusi' => $modeDistribusi,
            'Flag_Aktif' => SopStatus::AKTIF,
            'Created_By' => 1,
            'Created_At' => now(),
        ], 'Id_SOP_Paket');

        /* Tipe ber-versi menuntut versi berlaku sebelum terbit
           (pastikanItemSiapTerbit), jadi kolomnya harus terisi — kalau tidak,
           yang menolak nanti adalah penjaga LAIN yang kebetulan lebih dulu, dan
           berkas ini akan hijau tanpa pernah menyentuh gerbang publik-kuis.
           Nilainya dipinjam dari versi yang benar-benar ada: kolom itu berkunci
           asing (FK_N_SOP_Paket_Item_Versi_Aktif), sehingga angka karangan
           ditolak basis data. */
        $versiAda = SopTipeItem::isVersioned($tipeItem)
            ? DB::table('N_SOP_Item_Versi')->value('Id_SOP_Item_Versi')
            : null;

        if (SopTipeItem::isVersioned($tipeItem)) {
            $this->assertNotNull($versiAda, 'Tak ada satu pun baris N_SOP_Item_Versi untuk dipinjam fixture ini.');
        }

        DB::table('N_SOP_Paket_Item')->insert([
            'Id_SOP_Paket' => $idPaket,
            'Tipe_Item' => $tipeItem,
            'Judul_Item' => $judulItem,
            'Id_Item_Versi_Aktif' => $versiAda,
            'Flag_Aktif' => SopStatus::AKTIF,
            'Created_By' => 1,
            'Created_At' => now(),
        ]);

        return $idPaket;
    }

    private function terbitkan(int $idPaket): void
    {
        $this->paket()->updateStatus($idPaket, SopStatus::PAKET_PUBLISHED, 1);
    }

    /* ── Aturannya sendiri, tanpa basis data ────────────────────────────── */

    public function test_null_dibaca_sebagai_publik(): void
    {
        /* Kolomnya berbawaan TERBATAS di jalur tulis, tetapi SETIAP jalur baca
           memperlakukan NULL sebagai publik. Penjaga yang lebih longgar
           daripada runtime meloloskan justru baris yang paling mungkin
           terlewat. */
        $this->assertTrue(SopGerbangPublikKuis::publik(null));
        $this->assertTrue(SopGerbangPublikKuis::publik(''));
        $this->assertTrue(SopGerbangPublikKuis::publik('PUBLIK'));
        $this->assertFalse(SopGerbangPublikKuis::publik('TERBATAS'));
    }

    public function test_tanpa_item_kuis_tidak_pernah_dilanggar(): void
    {
        $this->assertFalse(SopGerbangPublikKuis::dilanggar('PUBLIK', []));
        $this->assertFalse(SopGerbangPublikKuis::dilanggar(null, []));
    }

    public function test_pesannya_menyebut_item_dan_dua_jalan_keluar(): void
    {
        $pesan = SopGerbangPublikKuis::pesan(['Kuis K3']);

        $this->assertStringContainsString('Kuis K3', $pesan);
        $this->assertStringContainsString('Terbatas', $pesan);
        $this->assertStringContainsString('keluarkan item kuisnya', $pesan);
    }

    /* ── Pintu 1: publish langsung ──────────────────────────────────────── */

    public function test_paket_publik_berkuis_ditolak_saat_terbit(): void
    {
        $idPaket = $this->buatPaket('PUBLIK', SopTipeItem::KUIS, 'Kuis Pemahaman K3');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Publik.*tidak boleh memuat item Kuis/i');

        $this->terbitkan($idPaket);
    }

    public function test_mode_distribusi_null_ikut_ditolak(): void
    {
        $idPaket = $this->buatPaket('PUBLIK', SopTipeItem::KUIS, 'Kuis Tanpa Mode');
        DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idPaket)
            ->update(['Mode_Distribusi' => null]);

        $this->expectException(\DomainException::class);

        $this->terbitkan($idPaket);
    }

    /* ── UJI PENOLAKAN: yang sah tidak boleh ikut tertahan ──────────────── */

    public function test_paket_terbatas_berkuis_tetap_boleh_terbit(): void
    {
        $idPaket = $this->buatPaket('TERBATAS', SopTipeItem::KUIS, 'Kuis Pemahaman K3');

        $this->terbitkan($idPaket);

        $this->assertSame(
            SopStatus::PAKET_PUBLISHED,
            DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idPaket)->value('Status'),
            'Kuis pada paket TERBATAS tak pernah dilarang — hanya paket publik yang ditutup.',
        );
    }

    public function test_paket_publik_tanpa_kuis_tetap_boleh_terbit(): void
    {
        $idPaket = $this->buatPaket('PUBLIK', SopTipeItem::PARAGRAF, 'Pengantar');

        $this->terbitkan($idPaket);

        $this->assertSame(
            SopStatus::PAKET_PUBLISHED,
            DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idPaket)->value('Status'),
        );
    }

    public function test_item_kuis_yang_sudah_dinonaktifkan_tidak_menahan(): void
    {
        /* Item yang dikeluarkan admin dari paket ditandai Flag_Aktif = 'T',
           bukan dihapus. Menghitungnya berarti paket yang JUSTRU sudah
           dibereskan tetap tertahan, tanpa satu pun item kuis yang terlihat di
           layar untuk dikeluarkan. */
        $idPaket = $this->buatPaket('PUBLIK', SopTipeItem::KUIS, 'Kuis Dikeluarkan');
        DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket', $idPaket)
            ->update(['Flag_Aktif' => SopStatus::INAKTIF]);

        $this->terbitkan($idPaket);

        $this->assertSame(
            SopStatus::PAKET_PUBLISHED,
            DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idPaket)->value('Status'),
        );
    }

    /* ── Tidak berlaku surut ────────────────────────────────────────────── */

    public function test_paket_publik_berkuis_yang_sudah_terbit_tidak_diganggu(): void
    {
        /* Penjaga ini menutup KELAHIRAN keadaan itu, bukan keberadaannya.
           Menolak yang sudah terbit berarti mencabut sesuatu yang sedang
           dikerjakan orang, dan penugasan mandiri yang terlanjur ada tak punya
           tempat berpindah. Diarsipkan pun harus tetap lolos — jalur itu tak
           melewati gerbang publish sama sekali. */
        $idPaket = $this->buatPaket('TERBATAS', SopTipeItem::KUIS, 'Kuis Lama');
        $this->terbitkan($idPaket);

        DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idPaket)
            ->update(['Mode_Distribusi' => 'PUBLIK']);

        $this->paket()->updateStatus($idPaket, SopStatus::PAKET_ARCHIVED, 1);

        $this->assertSame(
            SopStatus::PAKET_ARCHIVED,
            DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idPaket)->value('Status'),
        );
    }
}
