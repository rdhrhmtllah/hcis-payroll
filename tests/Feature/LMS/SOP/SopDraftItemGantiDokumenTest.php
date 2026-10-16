<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopPaketDraftService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Dua pintu untuk satu aturan — pola cacat yang berulang di modul ini.
 *
 * Item pada paket TERBIT punya dua asal-usul yang perlakuannya berlawanan:
 *
 *   CERMINAN (Id_SOP_Paket_Item_Asal terisi) — sudah pernah terbit, sudah punya
 *   pembaca. Menukar dokumennya harus DITOLAK LANTANG: catatan "sudah selesai"
 *   akan menunjuk SOP yang tak pernah dibaca siapa pun.
 *
 *   LAHIR DI OVERLAY (Asal kosong) — belum pernah terbit, belum punya pembaca.
 *   Pilihannya masih boleh diperbaiki, dan perbaikan itu HARUS TERSIMPAN.
 *
 * Sebelum ini pintu kedua gagal diam-diam: Id_SOP_Dokumen tak masuk daftar
 * kolom yang ditulis updateItem(), jadi nilainya lewat begitu saja dan layar
 * tetap berkata "Item tersimpan".
 */
class SopDraftItemGantiDokumenTest extends TestCase
{
    use DatabaseTransactions;

    private SopPaketDraftService $draftSvc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->draftSvc = app(SopPaketDraftService::class);

        if (! $this->draftSvc->dukungDokumen()) {
            $this->markTestSkipped('DDL 33 belum dijalankan di environment ini.');
        }
        URL::forceRootUrl('http://localhost');
    }

    private function admin(): User
    {
        $uid = DB::table('HRIS_Page_Access')->where('Flag_Aktif', 'Y')
            ->where('Jenis_page', 'menuSopBuilderPage')->whereNotNull('UserID_Web')->value('UserID_Web');

        $u = $uid ? User::find($uid) : null;
        if (! $u) {
            $this->markTestSkipped('Tak ada user dengan menuSopBuilderPage.');
        }

        return $u;
    }

    private function enc($id): string
    {
        return Hashids::connection('custom')->encode((int) $id);
    }

    public function test_dokumen_item_yang_lahir_di_overlay_bisa_diperbaiki(): void
    {
        $idPaket = DB::table('N_SOP_Master_Paket as p')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket', '=', 'p.Id_SOP_Paket')
            ->where('p.Status', SopStatus::PAKET_PUBLISHED)
            ->where('p.Flag_Aktif', SopStatus::AKTIF)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->value('p.Id_SOP_Paket');

        if (! $idPaket) {
            $this->markTestSkipped('Tak ada paket terbit berisi item.');
        }

        $sections = collect($this->draftSvc->sections((int) $idPaket, 1));
        if ($sections->isEmpty()) {
            $this->markTestSkipped('Working draft paket itu tak punya section.');
        }

        // Dua dokumen terbit yang belum dipakai paket itu: yang salah, lalu yang benar.
        $dok = DB::table('N_SOP_Dokumen as d')
            ->join('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'd.Id_Versi_Aktif')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Tipe', 'DOKUMEN')
            ->where('v.Status', SopStatus::PAKET_PUBLISHED)
            ->whereNotExists(fn ($q) => $q->from('N_SOP_Paket_Item as i')
                ->whereColumn('i.Id_SOP_Dokumen', 'd.Id_SOP_Dokumen')
                ->where('i.Id_SOP_Paket', $idPaket)
                ->where('i.Flag_Aktif', SopStatus::AKTIF))
            ->limit(2)->get(['d.Id_SOP_Dokumen', 'd.Judul', 'v.Id_SOP_Item_Versi']);

        if ($dok->count() < 2) {
            $this->markTestSkipped('Butuh dua dokumen terbit yang belum dipakai paket itu.');
        }

        [$salah, $benar] = [$dok[0], $dok[1]];

        $res = $this->actingAs($this->admin())
            ->postJson("/api/v1/sop/admin/builder/paket/{$this->enc($idPaket)}/item/store", [
                'Tipe_Item' => 'DOKUMEN',
                'Id_SOP_Section' => $sections->first()->Id_SOP_Section,
                'Id_SOP_Dokumen' => $this->enc($salah->Id_SOP_Dokumen),
                'Mode_Akses' => 'VIEW_ONLY',
            ])->assertStatus(201);

        $token = $res->json('result.Id_SOP_Paket_Item');
        $idDraftItem = Hashids::connection('custom')->decode(substr($token, 3))[0] ?? null;

        $this->actingAs($this->admin())
            ->putJson("/api/v1/sop/admin/builder/item/update/{$token}", [
                'Tipe_Item' => 'DOKUMEN',
                'Judul_Item' => $salah->Judul, // sengaja judul lama — identitas ikut dokumennya
                'Id_SOP_Dokumen' => $this->enc($benar->Id_SOP_Dokumen),
                'Mode_Akses' => 'VIEW_ONLY',
            ])->assertStatus(200);

        $baris = DB::table('N_SOP_Paket_Draft_Item')->where('Id_SOP_Draft_Item', $idDraftItem)
            ->first(['Id_SOP_Dokumen', 'Judul_Item', 'Id_SOP_Item_Versi_Target']);

        $this->assertSame((int) $benar->Id_SOP_Dokumen, (int) $baris->Id_SOP_Dokumen,
            'Dokumennya tak berpindah — perbaikannya hilang diam-diam, persis cacat yang ditutup di sini.');

        $this->assertSame($benar->Judul, $baris->Judul_Item,
            'Judulnya masih judul dokumen lama: identitas item ikut dokumennya.');

        /* Versi targetnya ikut pindah. Kalau tertinggal, promosi akan menerbitkan
           item yang menunjuk dokumen A dengan versi milik dokumen B. */
        $this->assertSame((int) $benar->Id_SOP_Item_Versi, (int) $baris->Id_SOP_Item_Versi_Target,
            'Versi targetnya masih milik dokumen lama.');
    }

    /**
     * Layar tak boleh menyimpulkan sendiri item mana yang boleh diganti.
     *
     * Kedua jenis item duduk berdampingan di daftar yang sama dan tampak
     * serupa. Tanpa penanda ini Builder hanya tahu "paketnya sudah terbit",
     * lalu mengunci keduanya — termasuk item yang belum pernah dilihat siapa
     * pun, yang justru masih boleh diperbaiki.
     */
    public function test_daftar_overlay_membedakan_item_yang_pernah_terbit(): void
    {
        $idPaket = DB::table('N_SOP_Master_Paket as p')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket', '=', 'p.Id_SOP_Paket')
            ->where('p.Status', SopStatus::PAKET_PUBLISHED)
            ->where('p.Flag_Aktif', SopStatus::AKTIF)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->value('p.Id_SOP_Paket');

        if (! $idPaket) {
            $this->markTestSkipped('Tak ada paket terbit berisi item.');
        }

        $sections = collect($this->draftSvc->sections((int) $idPaket, 1));
        if ($sections->isEmpty()) {
            $this->markTestSkipped('Working draft paket itu tak punya section.');
        }

        $this->actingAs($this->admin())
            ->postJson("/api/v1/sop/admin/builder/paket/{$this->enc($idPaket)}/item/store", [
                'Tipe_Item' => 'PARAGRAF',
                'Id_SOP_Section' => $sections->first()->Id_SOP_Section,
                'Judul_Item' => 'Item baru di revisi',
                'Konten_Teks' => '<p>uji</p>',
            ])->assertStatus(201);

        $rows = collect($this->draftSvc->items((int) $idPaket, 1));

        $cerminan = $rows->filter(fn ($r) => ! empty($r->Id_SOP_Paket_Item_Asal));
        $lahirDiSini = $rows->filter(fn ($r) => empty($r->Id_SOP_Paket_Item_Asal));

        $this->assertTrue($lahirDiSini->isNotEmpty(), 'Item yang baru dibuat tak muncul di daftar overlay.');

        foreach ($lahirDiSini as $r) {
            $this->assertFalse($r->Sudah_Pernah_Terbit,
                'Item yang lahir di revisi ini ditandai pernah terbit — layar akan menguncinya tanpa sebab.');
        }
        foreach ($cerminan as $r) {
            $this->assertTrue($r->Sudah_Pernah_Terbit,
                'Item yang sudah dibaca karyawan ditandai belum terbit — layar akan menawarkan penggantian yang ditolak server.');
        }
    }

    public function test_dokumen_item_cerminan_tetap_ditolak_lantang(): void
    {
        $live = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->whereNotNull('i.Id_SOP_Dokumen')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('i.Tipe_Item', 'DOKUMEN')
            ->where('p.Status', SopStatus::PAKET_PUBLISHED)
            ->first(['p.Id_SOP_Paket', 'i.Id_SOP_Paket_Item', 'i.Id_SOP_Dokumen', 'i.Judul_Item']);

        if (! $live) {
            $this->markTestSkipped('Tak ada item ber-dokumen di paket terbit.');
        }

        $rows = collect($this->draftSvc->items((int) $live->Id_SOP_Paket, 1));
        $cermin = $rows->first(fn ($r) => (int) ($r->Id_SOP_Paket_Item_Asal ?? 0) === (int) $live->Id_SOP_Paket_Item);
        if (! $cermin) {
            $this->markTestSkipped('Item itu tak muncul di overlay.');
        }

        $lain = DB::table('N_SOP_Dokumen')->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Tipe', 'DOKUMEN')->whereNotNull('Id_Versi_Aktif')
            ->where('Id_SOP_Dokumen', '<>', $live->Id_SOP_Dokumen)->value('Id_SOP_Dokumen');

        if (! $lain) {
            $this->markTestSkipped('Tak ada dokumen pembanding.');
        }

        $res = $this->actingAs($this->admin())
            ->putJson("/api/v1/sop/admin/builder/item/update/{$cermin->Id_SOP_Paket_Item}", [
                'Tipe_Item' => 'DOKUMEN',
                'Judul_Item' => $live->Judul_Item,
                'Id_SOP_Dokumen' => $this->enc($lain),
                'Mode_Akses' => 'VIEW_ONLY',
            ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('tak bisa diganti', (string) $res->json('message'));

        // Dan benar-benar tak berubah — penolakan yang cuma di layar tak ada gunanya.
        $this->assertSame(
            (int) $live->Id_SOP_Dokumen,
            (int) DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $live->Id_SOP_Paket_Item)->value('Id_SOP_Dokumen'),
        );
    }
}
