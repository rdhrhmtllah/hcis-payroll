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
 * Paket TERBIT bisa diberi SOP yang sudah ada — langkah kedua alur pembetulan.
 *
 * Alur yang benar saat SOP yang ditugaskan keliru adalah: hapus item lama
 * (progresnya tetap menempel, jejaknya tercatat), lalu tambahkan item baru
 * dengan SOP yang benar. Bukan menukar dokumen di tempat — itu membuat
 * "sudah selesai" menunjuk SOP yang tak pernah dibaca.
 *
 * Sebelum DDL 33, langkah KEDUA mustahil: N_SOP_Paket_Draft_Item tak punya
 * kolom Id_SOP_Dokumen, jadi item baru di paket terbit selalu melahirkan
 * dokumen BARU saat promosi. Alurnya berhenti di tengah jalan.
 */
class SopPaketTerbitPakaiDokumenAdaTest extends TestCase
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
    }

    private function adminBuilder(): User
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

    /** Paket TERBIT + section overlay-nya + dokumen terbit yang belum dipakai di sana. */
    private function skenario(): object
    {
        $idPaket = DB::table('N_SOP_Master_Paket as p')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket', '=', 'p.Id_SOP_Paket')
            ->where('p.Status', SopStatus::PAKET_PUBLISHED)
            ->where('p.Flag_Aktif', SopStatus::AKTIF)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            /* Seluruh item ber-versinya harus punya pointer aktif. Cerminan draft
               menyalin pointer itu jadi Id_SOP_Item_Versi_Target; item live yang
               pointernya kosong akan lahir di draft tanpa target maupun kandidat,
               lalu menahan publish — cacat data lama, bukan yang diuji di sini. */
            ->whereNotExists(fn ($q) => $q->from('N_SOP_Paket_Item as x')
                ->whereColumn('x.Id_SOP_Paket', 'p.Id_SOP_Paket')
                ->where('x.Flag_Aktif', SopStatus::AKTIF)
                ->whereIn('x.Tipe_Item', ['DOKUMEN', 'FORM', 'VIDEO'])
                ->whereNull('x.Id_Item_Versi_Aktif'))
            ->value('p.Id_SOP_Paket');

        if (! $idPaket) {
            $this->markTestSkipped('Tak ada paket terbit yang seluruh itemnya punya versi berlaku.');
        }

        /* Working draft yang SUDAH ADA dibuang dulu supaya ia dicerminkan ulang
           dari paket live. Draft basi di dev menyimpan item yang tak punya versi
           target maupun kandidat siap-rilis — sisa percobaan lama — dan itu
           menahan publish karena alasan yang sama sekali tak berhubungan dengan
           yang diuji di sini. Semuanya di dalam transaksi uji, jadi tak ada yang
           benar-benar dibuang. */
        $this->draftSvc->discard((int) $idPaket, 1);

        // ensure() membuat working draft-nya bila belum ada.
        $sections = $this->draftSvc->sections((int) $idPaket, 1);
        if (collect($sections)->isEmpty()) {
            $this->markTestSkipped('Working draft paket itu tak punya section.');
        }

        $dok = DB::table('N_SOP_Dokumen as d')
            ->join('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'd.Id_Versi_Aktif')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Tipe', 'DOKUMEN')
            ->where('v.Status', SopStatus::PAKET_PUBLISHED)
            ->whereNotExists(fn ($q) => $q->from('N_SOP_Paket_Item as i')
                ->whereColumn('i.Id_SOP_Dokumen', 'd.Id_SOP_Dokumen')
                ->where('i.Id_SOP_Paket', $idPaket)
                ->where('i.Flag_Aktif', SopStatus::AKTIF))
            ->first(['d.Id_SOP_Dokumen', 'd.Judul', 'v.Id_SOP_Item_Versi']);

        if (! $dok) {
            $this->markTestSkipped('Tak ada dokumen terbit yang belum dipakai paket itu.');
        }

        return (object) [
            'idPaket' => (int) $idPaket,
            'section' => collect($sections)->first()->Id_SOP_Section,
            'dok' => $dok,
        ];
    }

    public function test_item_baru_di_paket_terbit_menyimpan_tautan_dokumennya(): void
    {
        // APP_URL repo ini tanpa skema; tanpa ini permintaan uji meleset dari
        // route-nya dan berbalas 404. Lihat SopFormBerkasTest.
        URL::forceRootUrl('http://localhost');

        $s = $this->skenario();

        $res = $this->actingAs($this->adminBuilder())
            ->postJson("/api/v1/sop/admin/builder/paket/{$this->enc($s->idPaket)}/item/store", [
                'Tipe_Item' => 'DOKUMEN',
                'Id_SOP_Section' => $s->section,
                'Id_SOP_Dokumen' => $this->enc($s->dok->Id_SOP_Dokumen),
                'Mode_Akses' => 'VIEW_ONLY',
            ]);

        $res->assertStatus(201);

        $token = $res->json('result.Id_SOP_Paket_Item');
        $this->assertStringStartsWith('di_', (string) $token, 'Item paket terbit harus lahir di overlay draft.');

        $idDraftItem = Hashids::connection('custom')->decode(substr($token, 3))[0] ?? null;
        $this->assertSame(
            (int) $s->dok->Id_SOP_Dokumen,
            (int) DB::table('N_SOP_Paket_Draft_Item')->where('Id_SOP_Draft_Item', $idDraftItem)->value('Id_SOP_Dokumen'),
            'Tautan dokumennya tak tersimpan — sebelum DDL 33 inilah yang membuat pilihannya hilang senyap.',
        );

        // Judul TIDAK wajib lagi di jalur ini: identitasnya milik dokumennya.
        $this->assertNotEmpty(
            DB::table('N_SOP_Paket_Draft_Item')->where('Id_SOP_Draft_Item', $idDraftItem)->value('Judul_Item'),
            'Judul item kosong — daftar Builder akan menampilkan baris tanpa nama.',
        );
    }

    /**
     * Dan promosinya MENAUTKAN dokumen itu, bukan membuat dokumen baru.
     *
     * Inilah inti perbaikannya. Sebelum ini `ensureForItem()` selalu melahirkan
     * dokumen terkendali baru untuk item yang belum bertaut — sehingga SOP yang
     * benar tak pernah bisa ditugaskan ke paket terbit, hanya salinannya.
     */
    public function test_promosi_menautkan_dokumen_yang_dipilih_bukan_membuat_baru(): void
    {
        URL::forceRootUrl('http://localhost');

        $s = $this->skenario();
        $dokumenSebelum = DB::table('N_SOP_Dokumen')->count();
        $versiSebelum = DB::table('N_SOP_Item_Versi')->count();

        $this->actingAs($this->adminBuilder())
            ->postJson("/api/v1/sop/admin/builder/paket/{$this->enc($s->idPaket)}/item/store", [
                'Tipe_Item' => 'DOKUMEN',
                'Id_SOP_Section' => $s->section,
                'Id_SOP_Dokumen' => $this->enc($s->dok->Id_SOP_Dokumen),
                'Mode_Akses' => 'VIEW_ONLY',
            ])->assertStatus(201);

        $this->draftSvc->publish($s->idPaket, 1, 'Uji promosi tautan dokumen');

        $itemBaru = DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Paket', $s->idPaket)
            ->where('Id_SOP_Dokumen', $s->dok->Id_SOP_Dokumen)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->first(['Id_SOP_Paket_Item', 'Judul_Item', 'Id_Item_Versi_Aktif']);

        $this->assertNotNull($itemBaru,
            'Item live tak bertaut dokumen yang dipilih — promosi membuat dokumen baru lagi.');

        // Identitasnya dicerminkan dari dokumennya, bukan judul yang diketik.
        $this->assertSame($s->dok->Judul, $itemBaru->Judul_Item);

        // Pointer versi aktif ikut terpasang — tanpa itu item tak menyajikan apa pun.
        $this->assertSame((int) $s->dok->Id_SOP_Item_Versi, (int) $itemBaru->Id_Item_Versi_Aktif);

        $this->assertSame($dokumenSebelum, DB::table('N_SOP_Dokumen')->count(),
            'Ada dokumen terkendali baru yang lahir — persis yang seharusnya tidak terjadi lagi.');

        /* Dan tak ada baris versi baru. Menautkan SOP yang sudah ada bukan
           merevisinya: berkasnya, pengesahnya, tanggal berlakunya tetap milik
           versi yang sudah sah. Versi kembar di sini akan menggeser nomor versi
           dokumen itu di SELURUH paket pemakainya, bukan cuma paket ini. */
        $this->assertSame($versiSebelum, DB::table('N_SOP_Item_Versi')->count(),
            'Promosi melahirkan versi baru untuk dokumen yang tak direvisi.');
    }
}
