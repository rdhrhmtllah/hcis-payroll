<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopPaketDraftService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Overlay Builder harus membaca versi lewat sumbu DOKUMEN.
 *
 * Sumbu penomoran dan kepemilikan versi pindah ke dokumen pada Fase 2, dan
 * jalur live (SopItemVersionService::byItem) ikut pindah. Jalur overlay
 * tertinggal: ia masih menyaring N_SOP_Item_Versi lewat v.Id_SOP_Paket_Item —
 * kolom yang sejak perpindahan itu tak pernah diisi lagi. Akibatnya panel versi
 * di Builder kosong untuk SETIAP item, dan layarnya berkata "Belum Ada Versi
 * Berlaku" pada dokumen yang versinya jelas-jelas terbit di Master Dokumen.
 *
 * Cacat yang sama punya sisi kedua: identitas item (judul & nomor) adalah
 * cerminan berpenulis-tunggal dari dokumennya, tapi baris overlay memotretnya
 * sekali saat draft dibuka lalu tak pernah disegarkan. Potret basi itu dulu ikut
 * dibandingkan dan ikut diterbitkan, sehingga diff melaporkan "Nomor Dokumen
 * berubah" selamanya dan publish menulis nilai basi itu balik ke item live.
 */
class SopBuilderVersiSumbuDokumenTest extends TestCase
{
    use DatabaseTransactions;

    private SopPaketDraftService $draftSvc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->draftSvc = app(SopPaketDraftService::class);
    }

    /**
     * Satu item overlay yang bertaut dokumen ber-versi PUBLISHED, berikut paketnya.
     *
     * Dicari lewat item LIVE karena di situlah tautan dokumen pasti tersimpan;
     * baris overlay-nya lahir dari ensure() yang dipanggil items().
     */
    private function itemOverlayBerdokumen(): object
    {
        $live = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->join('N_SOP_Item_Versi as v', 'v.Id_SOP_Dokumen', '=', 'i.Id_SOP_Dokumen')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('p.Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('i.Id_SOP_Dokumen')
            ->where('v.Flag_Aktif', SopStatus::AKTIF)
            ->where('v.Status', SopStatus::VERSI_PUBLISHED)
            ->first(['i.Id_SOP_Paket_Item', 'i.Id_SOP_Paket', 'i.Id_SOP_Dokumen']);

        if (! $live) {
            $this->markTestSkipped('Tak ada item ber-dokumen yang dokumennya punya versi PUBLISHED.');
        }

        // ensure() membuat working draft-nya bila belum ada.
        $baris = collect($this->draftSvc->items((int) $live->Id_SOP_Paket, 1))
            ->first(fn ($r) => (int) ($r->Id_SOP_Paket_Item_Asal ?? 0) === (int) $live->Id_SOP_Paket_Item);

        if (! $baris) {
            $this->markTestSkipped('Item live itu tak muncul di working draft paketnya.');
        }

        return (object) [
            'idDraftItem' => (int) $baris->Id_SOP_Draft_Item,
            'idPaket' => (int) $live->Id_SOP_Paket,
            'idLive' => (int) $live->Id_SOP_Paket_Item,
            'idDokumen' => (int) $live->Id_SOP_Dokumen,
        ];
    }

    /**
     * Inilah regresi yang dilaporkan: panel versi Builder kosong.
     *
     * Penjaga sengaja menuntut DUA hal sekaligus. Yang pertama, versinya ketemu.
     * Yang kedua — dan ini yang membuat penjaga ini tak bisa dilewati dengan
     * mengembalikan sumbu lama — baris versi itu memang TIDAK punya
     * Id_SOP_Paket_Item, sehingga penyaringan lewat sumbu item mustahil
     * menemukannya. Tanpa tuntutan kedua, penjaga ini akan tetap hijau di
     * environment yang kebetulan masih menyimpan sisa data sumbu lama.
     */
    public function test_panel_versi_overlay_menemukan_versi_lewat_sumbu_dokumen(): void
    {
        $s = $this->itemOverlayBerdokumen();

        $versi = collect($this->draftSvc->versions($s->idDraftItem));

        $this->assertNotEmpty($versi,
            'Panel versi Builder tak menemukan satu pun versi untuk item yang dokumennya '
            .'punya versi PUBLISHED — layarnya akan berkata "Belum Ada Versi Berlaku".');

        $sumbuItem = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', $s->idDokumen)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereIn('Status', [SopStatus::VERSI_PUBLISHED, SopStatus::VERSI_OBSOLETE])
            ->whereNotNull('Id_SOP_Paket_Item')
            ->count();

        $this->assertSame(0, $sumbuItem,
            'Versi dokumen ini masih mengisi Id_SOP_Paket_Item, jadi penjaga ini tak bisa '
            .'membedakan sumbu dokumen dari sumbu item. Cari fikstur lain.');
    }

    /**
     * ?ringkas=1 menjawab satu pertanyaan: mana versi yang BERLAKU.
     *
     * Panel ringkas membaca baris pertama apa adanya. Menyerahkan seluruh
     * riwayat terurut nomor akan menempatkan versi bernomor tertinggi di depan —
     * dan nomor tertinggi belum tentu yang sah: dokumen yang revisi terbarunya
     * dicabut menyimpan OBSOLETE di atas PUBLISHED. Layar lalu menyebut versi
     * yang sudah dicabut sebagai versi berlaku, yang lebih berbahaya daripada
     * mengaku tak tahu.
     */
    public function test_ringkas_hanya_menyerahkan_versi_berlaku(): void
    {
        $s = $this->itemOverlayBerdokumen();

        $ringkas = collect($this->draftSvc->versions($s->idDraftItem, true));

        $this->assertNotEmpty($ringkas, 'Versi berlaku tak ditemukan lewat mode ringkas.');

        foreach ($ringkas as $v) {
            $this->assertSame(SopStatus::VERSI_PUBLISHED, $v->Status,
                'Mode ringkas menyerahkan versi yang belum sah; baris pertamanya dibaca '
                .'layar sebagai "versi berlaku".');
        }
    }

    /**
     * Kasus yang membuat urutan-nomor berbahaya, dibangun sendiri supaya tak
     * bergantung pada dokumen dev tertentu: OBSOLETE bernomor LEBIH TINGGI
     * daripada PUBLISHED.
     */
    public function test_ringkas_melewati_obsolete_yang_bernomor_lebih_tinggi(): void
    {
        $s = $this->itemOverlayBerdokumen();

        $berlaku = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', $s->idDokumen)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Status', SopStatus::VERSI_PUBLISHED)
            ->orderByDesc('Versi_Ke')
            ->first(['Id_SOP_Item_Versi', 'Versi_Ke']);

        if (! $berlaku) {
            $this->markTestSkipped('Dokumen fikstur tak punya versi berlaku.');
        }

        /* Versi OBSOLETE bernomor satu di atas yang berlaku. Nomornya harus
           belum terpakai — unique index terfilter (Id_SOP_Dokumen, Versi_Ke)
           menolak kembaran, dan menabraknya di sini hanya akan meributkan
           penjaga dengan hal yang bukan urusannya. */
        $nomor = (int) DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', $s->idDokumen)->max('Versi_Ke') + 1;

        DB::table('N_SOP_Item_Versi')->insert([
            'Id_SOP_Dokumen' => $s->idDokumen,
            'Versi_Ke' => $nomor,
            'Status' => SopStatus::VERSI_OBSOLETE,
            'Flag_Aktif' => SopStatus::AKTIF,
            'Created_By' => 1,
            'Created_At' => now(),
        ]);

        $ringkas = collect($this->draftSvc->versions($s->idDraftItem, true));
        $pertama = $ringkas->first();

        $this->assertNotNull($pertama, 'Versi berlaku hilang begitu ada OBSOLETE bernomor lebih tinggi.');
        $this->assertSame((int) $berlaku->Id_SOP_Item_Versi, (int) DB::table('N_SOP_Item_Versi')
            ->where('Versi_Ke', $pertama->Versi_Ke)
            ->where('Id_SOP_Dokumen', $s->idDokumen)
            ->where('Status', SopStatus::VERSI_PUBLISHED)
            ->value('Id_SOP_Item_Versi'),
            'Baris pertama mode ringkas bukan versi yang berlaku.');

        $this->assertNotSame($nomor, (int) $pertama->Versi_Ke,
            'Mode ringkas menyerahkan versi OBSOLETE bernomor tertinggi sebagai versi berlaku.');
    }

    /**
     * Nomor dokumen yang basi di overlay BUKAN perubahan paket.
     *
     * Nomor SOP bergeser sendiri setiap versi berlaku berganti — segmen Revisi
     * ikut bergerak — dan penulisnya satu: sinkronIdentitasKeItem() dari
     * dokumennya, yang hanya menyentuh item live. Selama potret overlay ikut
     * dibandingkan, tiap pergeseran nomor menghasilkan "Belum Dipublish ·
     * Diubah" yang tak punya pintu keluar: formnya sendiri sudah tak punya field
     * untuk membetulkannya.
     */
    public function test_nomor_dokumen_basi_di_overlay_bukan_perubahan_paket(): void
    {
        $s = $this->itemOverlayBerdokumen();

        $sebelum = $this->draftSvc->pendingChanges($s->idPaket, 1);
        $berubahSebelum = collect($sebelum['details']['items'] ?? [])
            ->firstWhere('id', $this->tokenDraft($s->idDraftItem));

        DB::table('N_SOP_Paket_Draft_Item')->where('Id_SOP_Draft_Item', $s->idDraftItem)->update([
            'Nomor_SOP_Resmi' => 'UJI/NOMOR/BASI/99',
            'Judul_Item' => 'JUDUL BASI HASIL POTRET LAMA',
        ]);

        $sesudah = $this->draftSvc->pendingChanges($s->idPaket, 1);
        $berubahSesudah = collect($sesudah['details']['items'] ?? [])
            ->firstWhere('id', $this->tokenDraft($s->idDraftItem));

        $fields = collect($berubahSesudah['fields'] ?? []);
        $this->assertFalse($fields->contains('Nomor Dokumen'),
            'Nomor dokumen yang basi di overlay dilaporkan sebagai perubahan paket.');
        $this->assertFalse($fields->contains('Judul'),
            'Judul yang basi di overlay dilaporkan sebagai perubahan paket.');

        /* Diukur relatif, bukan terhadap angka mutlak: paket fikstur boleh saja
           sudah menyimpan perubahan sah lain, dan penjaga ini tak berurusan
           dengan itu. Yang dituntut adalah pergeseran identitas TIDAK menambah
           apa pun. */
        $this->assertSame((int) $sebelum['total'], (int) $sesudah['total'],
            'Menggeser identitas di overlay menaikkan hitungan perubahan paket.');

        $this->assertSame(
            $berubahSebelum['fields'] ?? [],
            $berubahSesudah['fields'] ?? [],
            'Daftar field yang berubah ikut bergerak karena identitas.');
    }

    /**
     * Publish paket tak boleh menulis identitas basi itu balik ke item live.
     *
     * Kalau ia menulis, cerminan yang baru saja disinkronkan dari dokumen jadi
     * mundur, lalu penyegaran nomor berikutnya membalikkannya lagi — dan
     * diff-nya hidup kembali. Lingkaran itulah yang membuat item tampak "selalu
     * berubah".
     */
    public function test_publish_tak_menulis_balik_identitas_basi(): void
    {
        $s = $this->itemOverlayBerdokumen();

        $paket = DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $s->idPaket)
            ->first(['Status']);
        if (! $paket || $paket->Status !== SopStatus::PAKET_PUBLISHED) {
            $this->markTestSkipped('Paket fikstur belum terbit; jalur applyItems tak terlewati.');
        }

        $nomorDokumen = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $s->idDokumen)
            ->value('Nomor_SOP_Resmi');

        /* Identitas digeser DAN satu field milik paket ikut diubah. Yang kedua
           wajib: tanpa perubahan sah, publish menolak dengan "Belum ada
           perubahan yang siap dirilis" dan penjaga ini akan hijau tanpa pernah
           menyentuh applyItems — hijau yang tak menguji apa pun. */
        $penanda = 'DESKRIPSI UJI '.uniqid();
        DB::table('N_SOP_Paket_Draft_Item')->where('Id_SOP_Draft_Item', $s->idDraftItem)->update([
            'Nomor_SOP_Resmi' => 'UJI/NOMOR/BASI/99',
            'Judul_Item' => 'JUDUL BASI HASIL POTRET LAMA',
            'Deskripsi_Item' => $penanda,
        ]);

        try {
            $this->draftSvc->publish($s->idPaket, 1, 'Uji penjaga identitas overlay');
        } catch (\DomainException $e) {
            $this->markTestSkipped('Publish ditolak karena sebab lain: '.$e->getMessage());
        }

        $live = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $s->idLive)
            ->first(['Nomor_SOP_Resmi', 'Judul_Item', 'Deskripsi_Item']);

        // Publish benar-benar berjalan dan menyentuh baris ini.
        $this->assertSame($penanda, $live->Deskripsi_Item,
            'Publish tak menulis apa pun ke item live — sisa penjaga ini tak menguji apa pun.');

        $this->assertSame($nomorDokumen, $live->Nomor_SOP_Resmi,
            'Publish paket menimpa nomor resmi item dengan potret basi milik overlay.');
        $this->assertNotSame('JUDUL BASI HASIL POTRET LAMA', $live->Judul_Item,
            'Publish paket menimpa judul item dengan potret basi milik overlay.');
    }

    /** Token 'di_' yang dipakai daftar perubahan untuk menunjuk baris overlay. */
    private function tokenDraft(int $idDraftItem): string
    {
        return 'di_'.\Vinkla\Hashids\Facades\Hashids::connection('custom')->encode($idDraftItem);
    }
}
