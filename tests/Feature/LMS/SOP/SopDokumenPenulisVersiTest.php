<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopItemService;
use App\Services\LMS\SOP\SopItemVersionService;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 2 Master Dokumen SOP — setiap penulis baris N_SOP_Item_Versi live wajib
 * mengisi Id_SOP_Dokumen.
 *
 * KENAPA INI PENTING. Backfill Fase 1 bersih saat dijalankan, tapi ia MELURUH:
 * SopItemService::cloneRow() punya daftar kolom eksplisit yang tak memuat
 * Id_SOP_Dokumen, dan promosi draft->live di SopPaketDraftService menyalin dari
 * tabel draft yang tak punya kolom itu. Artinya tiap penyalinan item dan tiap
 * penerbitan revisi paket melahirkan baris tanpa dokumen. Obatnya menutup
 * sumbernya, bukan menjalankan backfill berulang kali.
 */
class SopDokumenPenulisVersiTest extends TestCase
{
    use DatabaseTransactions;

    /** Item ber-versi yang sudah bertaut dokumen — titik awal semua test di sini. */
    private function itemBerVersi(): ?object
    {
        return DB::table('N_SOP_Paket_Item')
            ->whereIn('Tipe_Item', SopTipeItem::VERSIONED)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('Id_SOP_Dokumen')
            ->first();
    }

    public function test_ensure_mengembalikan_dokumen_yang_sudah_ada_tanpa_membuat_baru(): void
    {
        $item = $this->itemBerVersi();
        if (! $item) {
            $this->markTestSkipped('Tidak ada item ber-versi bertaut dokumen di DB ini.');
        }

        $sebelum = DB::table('N_SOP_Dokumen')->count();
        $id = app(SopDokumenService::class)->ensureForItem((int) $item->Id_SOP_Paket_Item, 1);

        $this->assertSame((int) $item->Id_SOP_Dokumen, $id);
        $this->assertSame($sebelum, DB::table('N_SOP_Dokumen')->count(),
            'ensureForItem tidak boleh membuat dokumen kedua untuk item yang sudah punya.');
    }

    public function test_ensure_membuat_dokumen_untuk_item_ber_versi_yang_belum_punya(): void
    {
        $item = $this->itemBerVersi();
        if (! $item) {
            $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');
        }

        // Dilepas dulu supaya jalur "belum punya" yang benar-benar diuji.
        DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $item->Id_SOP_Paket_Item)
            ->update(['Id_SOP_Dokumen' => null]);

        $id = app(SopDokumenService::class)->ensureForItem((int) $item->Id_SOP_Paket_Item, 1);
        $this->assertNotNull($id);

        $dok = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->first();
        $this->assertSame($item->Tipe_Item, $dok->Tipe, 'Tipe dokumen mengikuti tipe itemnya.');
        $this->assertNotSame('', trim((string) $dok->Judul), 'N_SOP_Dokumen.Judul NOT NULL di 26A.');
        $this->assertNull($dok->Id_Versi_Aktif,
            'Dokumen baru belum punya versi PUBLISHED; memasang pointer lebih awal '
            .'berarti menyajikan versi yang belum sah ke karyawan.');
        $this->assertSame($id, (int) DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Paket_Item', $item->Id_SOP_Paket_Item)->value('Id_SOP_Dokumen'));
    }

    public function test_ensure_menolak_item_tipe_tak_ber_versi(): void
    {
        $paragraf = DB::table('N_SOP_Paket_Item')
            ->where('Tipe_Item', SopTipeItem::PARAGRAF)->where('Flag_Aktif', SopStatus::AKTIF)->first();
        if (! $paragraf) {
            $this->markTestSkipped('Tidak ada item PARAGRAF di DB ini.');
        }

        $this->assertNull(app(SopDokumenService::class)->ensureForItem((int) $paragraf->Id_SOP_Paket_Item, 1),
            'PARAGRAF tak boleh dipaksa punya dokumen — 18 versi yatim di dev justru miliknya, '
            .'dan unique index 26B sengaja tak menyentuh baris ber-dokumen NULL.');
    }

    // ---------------------------------------------------------------------
    // Penulis versi live
    // ---------------------------------------------------------------------

    public function test_create_versi_baru_bertaut_ke_dokumen_itemnya(): void
    {
        $item = $this->itemBerVersi();
        if (! $item) {
            $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');
        }

        $idVersi = app(SopItemVersionService::class)->create((int) $item->Id_SOP_Paket_Item, [], 1);

        $this->assertSame((int) $item->Id_SOP_Dokumen,
            (int) DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->value('Id_SOP_Dokumen'),
            'Versi baru wajib bertaut ke dokumen itemnya — kalau tidak, backfill Fase 1 '
            .'meluruh setiap kali admin menambah revisi.');
    }

    public function test_item_hasil_salin_berbagi_dokumen_sumber(): void
    {
        $item = $this->itemBerVersi();
        if (! $item) {
            $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');
        }

        // Tabel paket bernama N_SOP_Master_Paket, bukan N_SOP_Paket.
        $paket = DB::table('N_SOP_Master_Paket')
            ->where('Status', SopStatus::PAKET_DRAFT)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Id_SOP_Paket', '!=', $item->Id_SOP_Paket)
            ->first();
        if (! $paket) {
            $this->markTestSkipped('Tidak ada paket DRAFT tujuan selain paket asal.');
        }

        // Section diperiksa SEBELUM dipakai: (int) null = 0 akan menulis baris
        // item yatim ke section yang tak ada, bukan melewatkan test.
        $idSection = DB::table('N_SOP_Paket_Section')
            ->where('Id_SOP_Paket', $paket->Id_SOP_Paket)->value('Id_SOP_Section');
        if (! $idSection) {
            $this->markTestSkipped('Paket DRAFT tujuan tak punya section.');
        }

        $baru = app(SopItemService::class)->copyToPaket(
            (int) $item->Id_SOP_Paket_Item,
            (int) $paket->Id_SOP_Paket,
            (int) $idSection,
            1,
            app(SopItemVersionService::class),
        );
        $this->assertNotNull($baru, 'copyToPaket menolak item ini — fixture tak cocok.');

        $docBaru = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $baru)->value('Id_SOP_Dokumen');

        $this->assertNotNull($docBaru,
            'Item hasil salin wajib punya dokumen. cloneRow() punya daftar kolom eksplisit '
            .'yang tak memuat Id_SOP_Dokumen, jadi tanpa perbaikan ia lahir yatim.');

        /* KONTRAK BERUBAH DI FASE 6 (keputusan produk user 2026-08-10).
           Versi Fase 2 test ini menegaskan sebaliknya — salinan dapat dokumen
           SENDIRI — dan pesannya sendiri sudah menubuatkan perubahan ini:
           "Berbagi dokumen adalah Fase 6". Alasan penundaannya nyata dan masih
           berlaku: berbagi baru aman setelah cloneActiveTo() berhenti membuat
           baris Versi_Ke=1, yang tanpa itu ditolak unique index terfilter 26B
           dengan error 2601. Fase 6 melakukannya, jadi kontraknya kini dibalik. */
        $this->assertSame((int) $item->Id_SOP_Dokumen, (int) $docBaru,
            'Salin ke paket lain BERBAGI dokumen sumber — itulah yang membuat revisi menyebar '
            .'ke semua paket perujuk.');

        $this->assertSame(0, DB::table('N_SOP_Item_Versi')->where('Id_SOP_Paket_Item', $baru)->count(),
            'Tak boleh ada baris versi baru: salinan mewarisi riwayat versi dokumennya. '
            .'Menulis Versi_Ke=1 pada dokumen yang sudah punya versi 1 ditolak 26B (2601).');
    }

    // ---------------------------------------------------------------------
    // Sumbu identitas versi: dokumen, bukan item
    // ---------------------------------------------------------------------

    /**
     * Selama 1 dokumen = 1 item, test ini bisa lulus SEBELUM implementasi —
     * kedua sumbu menghasilkan angka yang sama. Itu bukan alasan melewatinya:
     * ia gerbang yang menahan Fase 3, saat 1:1 berakhir dan dua paket bisa
     * memproduksi "Versi 3" yang berbeda isi.
     */
    public function test_penomoran_versi_dihitung_per_dokumen(): void
    {
        $item = $this->itemBerVersi();
        if (! $item) {
            $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');
        }

        $tertinggiDokumen = (int) DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', $item->Id_SOP_Dokumen)->max('Versi_Ke');

        $idVersi = app(SopItemVersionService::class)->create((int) $item->Id_SOP_Paket_Item, [], 1);

        $this->assertSame($tertinggiDokumen + 1,
            (int) DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->value('Versi_Ke'),
            'Versi_Ke wajib melanjutkan nomor tertinggi DOKUMEN, bukan item.');
    }

    /**
     * Test yang BENAR-BENAR membedakan kedua sumbu, hari ini.
     *
     * Kedua test sumbu lainnya lulus bahkan sebelum implementasi, karena 1
     * dokumen = 1 item membuat "max per item" dan "max per dokumen"
     * menghasilkan angka yang sama — mereka gerbang untuk Fase 3, bukan bukti
     * hari ini. Di sini keadaan Fase 3 disimulasikan: satu item KEDUA
     * ditautkan ke dokumen yang sama, lalu versi baru dibuat untuknya.
     *
     *   sumbu ITEM     -> item baru belum punya versi -> Versi_Ke = 1
     *   sumbu DOKUMEN  -> dokumen sudah punya N versi -> Versi_Ke = N+1
     *
     * Selisih itulah seluruh isi Fase 2. DatabaseTransactions me-rollback
     * item bantu ini di akhir test.
     */
    public function test_sumbu_penomoran_benar_benar_dokumen_bukan_item(): void
    {
        $item = $this->itemBerVersi();
        if (! $item) {
            $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');
        }

        $tertinggiDokumen = (int) DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', $item->Id_SOP_Dokumen)->max('Versi_Ke');
        if ($tertinggiDokumen < 1) {
            $this->markTestSkipped('Dokumen sumber belum punya versi.');
        }

        // Item KEDUA yang berbagi dokumen — keadaan yang baru sah di Fase 6,
        // dipakai di sini semata untuk memisahkan kedua sumbu.
        $itemKedua = DB::table('N_SOP_Paket_Item')->insertGetId([
            'Id_SOP_Paket' => $item->Id_SOP_Paket,
            'Id_SOP_Section' => $item->Id_SOP_Section,
            'Tipe_Item' => $item->Tipe_Item,
            'Judul_Item' => 'Uji sumbu penomoran',
            'Id_SOP_Dokumen' => $item->Id_SOP_Dokumen,
            'Urutan' => 9999,
            'Flag_Wajib' => $item->Flag_Wajib,
            'Flag_Highlight' => $item->Flag_Highlight,
            'Created_By' => 1,
            'Created_At' => now(),
            'Flag_Aktif' => SopStatus::AKTIF,
        ], 'Id_SOP_Paket_Item');

        $idVersi = app(SopItemVersionService::class)->create((int) $itemKedua, [], 1);
        $versiKe = (int) DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->value('Versi_Ke');

        $this->assertNotSame(1, $versiKe,
            'Versi_Ke = 1 berarti penomoran masih memakai sumbu ITEM. Begitu satu dokumen '
            .'dirujuk dua item, sumbu item memproduksi dua "Versi 1" berbeda isi pada satu '
            .'dokumen terkendali — dan unique index terfilter 26B menolaknya dengan 2601.');
        $this->assertSame($tertinggiDokumen + 1, $versiKe,
            'Versi_Ke wajib melanjutkan nomor tertinggi DOKUMEN.');
    }

    /**
     * Membaca lewat dokumen harus MEMUAT seluruh versi yang bertaut ke itemnya.
     *
     * Dulu asersinya "sama persis", dan itu benar selama Fase 2 masih 1:1 —
     * setiap versi punya Id_SOP_Paket_Item. Sejak Master SOP bisa menambah versi
     * yang dimiliki DOKUMEN tanpa item sama sekali (DDL 28), pembacaan lewat
     * dokumen sah bila mengembalikan LEBIH banyak: versi milik dokumen memang
     * tak punya item untuk dicapai.
     *
     * Yang tetap wajib: tak satu pun versi milik item boleh hilang. Itulah
     * jaminan yang sebenarnya dilindungi test ini — bukan jumlah barisnya.
     */
    public function test_by_item_membaca_lewat_dokumen_dan_hasilnya_tetap_sama(): void
    {
        $item = $this->itemBerVersi();
        if (! $item) {
            $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');
        }

        $lewatDokumen = app(SopItemVersionService::class)
            ->byItem((int) $item->Id_SOP_Paket_Item)->pluck('Versi_Ke')->all();

        $langsung = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Paket_Item', $item->Id_SOP_Paket_Item)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderByDesc('Versi_Ke')->pluck('Versi_Ke')->all();

        foreach ($langsung as $versiKe) {
            $this->assertContains($versiKe, $lewatDokumen,
                'Versi milik item hilang saat dibaca lewat dokumen — itu kehilangan data, '
                .'bukan sekadar beda urutan.');
        }

        /* Urutan tetap menurun: riwayat versi dibaca dari yang terbaru. */
        $urut = $lewatDokumen;
        rsort($urut);
        $this->assertSame($urut, $lewatDokumen,
            'Riwayat versi wajib menurun; urutan naik membuat versi lama tampil sebagai terbaru.');
    }

    /**
     * Item tanpa dokumen (PARAGRAF dengan versi yatim) TIDAK boleh kehilangan
     * barisnya saat pembacaan pindah ke dokumen. Memaksa mereka lewat dokumen
     * akan menyembunyikan versi yang masih ada di tabel.
     */
    public function test_by_item_tetap_menampilkan_versi_yatim_item_tanpa_dokumen(): void
    {
        $yatim = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'v.Id_SOP_Paket_Item')
            ->whereNull('v.Id_SOP_Dokumen')->whereNull('i.Id_SOP_Dokumen')
            ->where('v.Flag_Aktif', SopStatus::AKTIF)
            ->select('v.Id_SOP_Paket_Item')->first();
        if (! $yatim) {
            $this->markTestSkipped('Tidak ada versi yatim di DB ini.');
        }

        $this->assertNotEmpty(
            app(SopItemVersionService::class)->byItem((int) $yatim->Id_SOP_Paket_Item),
            'Versi yatim tetap harus terlihat lewat itemnya — kalau tidak, 18 baris di dev lenyap dari layar.');
    }
}
