<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopItemVersionService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Panel versi Builder harus membaca BLOK penanda tangan, bukan kolom cerminnya.
 *
 * Kolom Disahkan_Oleh/Jabatan_Pengesah adalah CERMIN baris pertama blok — ada
 * demi register kepatuhan, ekspor, dan penyaring "TANPA_PENGESAHAN" yang sudah
 * membacanya sejak lama. Membaca layar dari kolom itu membuat versi yang
 * disetujui tiga orang tampil seolah disahkan satu orang, dan PERAN yang
 * dipilih admin ("Disetujui oleh", "Mengetahui") hilang sama sekali —
 * dilabeli keras "Disahkan".
 *
 * Master Dokumen sudah membaca bloknya sejak awal. Yang tertinggal pembacanya
 * di jalur Builder (SopItemVersionService::byItem).
 */
class SopBlokPengesahBuilderTest extends TestCase
{
    use DatabaseTransactions;

    /** Item ber-dokumen yang versinya punya blok penanda tangan >1 baris. */
    private function itemBerblok(): object
    {
        $versi = DB::table('N_SOP_Item_Versi_Pengesah')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->select('Id_SOP_Item_Versi', DB::raw('COUNT(*) as n'))
            ->groupBy('Id_SOP_Item_Versi')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('Id_SOP_Item_Versi')
            ->first();

        if (! $versi) {
            $this->markTestSkipped('Tak ada versi dengan blok penanda tangan lebih dari satu baris.');
        }

        $idDok = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $versi->Id_SOP_Item_Versi)
            ->value('Id_SOP_Dokumen');
        $idItem = $idDok
            ? DB::table('N_SOP_Paket_Item')->where('Id_SOP_Dokumen', $idDok)
                ->where('Flag_Aktif', SopStatus::AKTIF)->value('Id_SOP_Paket_Item')
            : null;

        if (! $idItem) {
            $this->markTestSkipped('Blok itu tak menempel pada item mana pun.');
        }

        return (object) [
            'idItem' => (int) $idItem,
            'idVersi' => (int) $versi->Id_SOP_Item_Versi,
            'jumlah' => (int) $versi->n,
        ];
    }

    public function test_byitem_membawa_seluruh_blok_penanda_tangan(): void
    {
        $s = $this->itemBerblok();

        $rows = collect(app(SopItemVersionService::class)->byItem($s->idItem));
        $target = $rows->first(fn ($v) => ! empty($v->Pengesah));

        $this->assertNotNull($target, 'byItem() tak membawa blok pengesah sama sekali.');

        $cocok = $rows->first(fn ($v) => count($v->Pengesah ?? []) === $s->jumlah);
        $this->assertNotNull($cocok,
            "Blok yang berisi {$s->jumlah} penanda tangan tak muncul utuh — layar hanya akan menampilkan sebagiannya.");
    }

    public function test_peran_ikut_terbawa_bukan_diratakan(): void
    {
        /* Peran yang hilang bukan cacat kecil: "Disetujui oleh" dan "Disahkan
           oleh" adalah dua tanggung jawab berbeda di blok tanda tangan kertas,
           dan catatan yang meratakannya jadi satu kata bukan lagi cerminan
           dokumennya. */
        $s = $this->itemBerblok();

        $peranTersimpan = DB::table('N_SOP_Item_Versi_Pengesah')
            ->where('Id_SOP_Item_Versi', $s->idVersi)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('Peran')->where('Peran', '<>', '')
            ->value('Peran');

        if (! $peranTersimpan) {
            $this->markTestSkipped('Blok itu tersimpan tanpa peran.');
        }

        $semua = collect(app(SopItemVersionService::class)->byItem($s->idItem))
            ->flatMap(fn ($v) => $v->Pengesah ?? [])
            ->pluck('Peran')
            ->filter()
            ->all();

        $this->assertContains($peranTersimpan, $semua,
            'Peran yang tersimpan tak sampai ke layar — semuanya akan terbaca "Disahkan".');
    }

    /**
     * Versi LAMA tetap menampilkan pengesahnya.
     *
     * Aturan mundur di blokPengesah() membaca kolom tunggal sebagai blok satu
     * baris. Tanpa itu, setiap versi yang terbit sebelum blok ini ada akan
     * mendadak tampil tanpa pengesah sama sekali — dan yang hilang justru
     * catatan kepatuhan paling lama.
     */
    /**
     * Versi baru menyimpan BLOKNYA, bukan cuma kolom tunggal.
     *
     * Dulu jalur ini ada di Builder (storeVersi) dan memanggil
     * SopItemVersionService::create(), yang hanya mengenal
     * Disahkan_Oleh/Jabatan_Pengesah — peran hilang, penanda tangan kedua dan
     * seterusnya hilang, rujukan Kode_Karyawan-nya tak pernah tersimpan.
     *
     * Sejak 27 Agu 2026 Builder tak menulis versi sama sekali; pintunya tinggal
     * satu, di Master. Uji ini ikut pindah ke sana — yang dijaga tetap sama:
     * blok penanda tangan tersimpan utuh, dan kolom cerminnya terisi dari baris
     * pertama.
     */
    public function test_versi_baru_menyimpan_blok_penanda_tangan(): void
    {
        \Illuminate\Support\Facades\URL::forceRootUrl('http://localhost');

        $item = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->whereNotNull('i.Id_SOP_Dokumen')
            ->where('i.Tipe_Item', 'DOKUMEN')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('p.Status', SopStatus::PAKET_DRAFT)
            ->first(['i.Id_SOP_Paket_Item', 'i.Id_SOP_Dokumen']);

        $konten = DB::table('N_LMS_Master_Konten as m')
            ->join('N_LMS_Master_Konten_Berkas as b', 'b.Id_Master_Konten', '=', 'm.Id_Master_Konten')
            ->where('m.Scope', 'SOP')->whereIn('b.Status_Verifikasi', ['SIAP', 'LAMA'])
            ->where(fn ($q) => $q->where('b.Ext', 'pdf')->orWhere('b.Type_File', 'like', '%pdf%'))
            ->value('m.Id_Master_Konten');

        if (! $item || ! $konten) {
            $this->markTestSkipped('Butuh item ber-dokumen di paket draft dan satu konten PDF.');
        }

        $uid = DB::table('HRIS_Page_Access')->where('Flag_Aktif', 'Y')
            ->where('Jenis_page', 'menuSopDokumenPage')->whereNotNull('UserID_Web')->value('UserID_Web');
        if (! $uid) {
            $this->markTestSkipped('Tak ada user dengan menuSopDokumenPage.');
        }

        $enc = fn ($id) => \Vinkla\Hashids\Facades\Hashids::connection('custom')->encode((int) $id);

        $res = $this->actingAs(\App\Models\ESS\Employee\Models\User::find($uid))
            ->postJson("/api/v1/sop/admin/dokumen/{$enc($item->Id_SOP_Dokumen)}/versi", [
                'Id_Master_Konten' => $enc($konten),
                'Ringkasan_Perubahan' => 'Uji blok penanda tangan dari Master',
                'Pengesah' => [
                    ['Peran' => 'Diperiksa oleh', 'Nama' => 'Pemeriksa Uji', 'Jabatan' => 'QA', 'Kode_Karyawan' => null],
                    ['Peran' => 'Disetujui oleh', 'Nama' => 'Penyetuju Uji', 'Jabatan' => 'Manager', 'Kode_Karyawan' => null],
                ],
            ]);

        $res->assertStatus(201);

        $idVersi = \Vinkla\Hashids\Facades\Hashids::connection('custom')
            ->decode($res->json('result.Id_SOP_Item_Versi'))[0] ?? null;
        $this->assertNotNull($idVersi);

        $this->assertSame(2, DB::table('N_SOP_Item_Versi_Pengesah')
            ->where('Id_SOP_Item_Versi', $idVersi)->count(),
            'Blok tak tersimpan — versi kehilangan peran dan penanda tangan keduanya.');

        // Kolom cermin tetap terisi dari baris pertama: register kepatuhan
        // dan penyaring "TANPA_PENGESAHAN" membacanya dari sana.
        $this->assertSame('Pemeriksa Uji',
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->value('Disahkan_Oleh'));

        // Dan versinya bertaut DOKUMEN, bukan yatim milik item.
        $this->assertSame((int) $item->Id_SOP_Dokumen,
            (int) DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->value('Id_SOP_Dokumen'));
    }

    public function test_versi_tanpa_baris_blok_tetap_menampilkan_kolom_cerminnya(): void
    {
        $versi = DB::table('N_SOP_Item_Versi as v')
            ->whereNotNull('v.Disahkan_Oleh')->where('v.Disahkan_Oleh', '<>', '')
            ->whereNotNull('v.Id_SOP_Dokumen')
            ->where('v.Flag_Aktif', SopStatus::AKTIF)
            ->whereNotExists(fn ($q) => $q->from('N_SOP_Item_Versi_Pengesah as p')
                ->whereColumn('p.Id_SOP_Item_Versi', 'v.Id_SOP_Item_Versi'))
            ->first(['v.Id_SOP_Item_Versi', 'v.Id_SOP_Dokumen', 'v.Disahkan_Oleh']);

        if (! $versi) {
            $this->markTestSkipped('Semua versi berpengesah sudah punya baris blok.');
        }

        $idItem = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Dokumen', $versi->Id_SOP_Dokumen)
            ->where('Flag_Aktif', SopStatus::AKTIF)->value('Id_SOP_Paket_Item');
        if (! $idItem) {
            $this->markTestSkipped('Versi itu tak menempel pada item mana pun.');
        }

        $nama = collect(app(SopItemVersionService::class)->byItem((int) $idItem))
            ->flatMap(fn ($v) => $v->Pengesah ?? [])
            ->pluck('Nama')
            ->all();

        $this->assertContains($versi->Disahkan_Oleh, $nama,
            'Versi lama kehilangan pengesahnya — catatan kepatuhan paling lama justru yang hilang.');
    }
}
