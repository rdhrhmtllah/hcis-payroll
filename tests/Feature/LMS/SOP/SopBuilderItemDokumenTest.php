<?php

namespace Tests\Feature\LMS\SOP;

use App\Http\Controllers\LMS\SOP\Governance\SopAdminBuilderController;
use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopItemService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Item ber-versi di Builder menumpang versi DOKUMEN-nya.
 *
 * Sejak Fase 6 identitas item (judul, nomor SOP) dan seluruh siklus versinya
 * milik N_SOP_Dokumen. Form Builder pun sudah tak lagi punya field Judul untuk
 * tipe ber-versi. Tapi aturan servernya tertinggal di
 * `required_without:Id_SOP_Dokumen`, dan sisi klien mengira konten Library saja
 * sudah cukup ("dokumennya dibuat otomatis" — janji yang tak pernah dibangun).
 * Hasilnya: SETIAP penambahan item DOKUMEN/FORM/VIDEO/KUIS baru ditolak 422
 * dengan pesan mentah Laravel, dan tak ada satu pun field di layar yang bisa
 * diisi admin untuk memenuhinya.
 *
 * Uji ini menjaga kedua sisi kesepakatan itu: yang ditolak, dan yang diterima.
 */
class SopBuilderItemDokumenTest extends TestCase
{
    use DatabaseTransactions;

    /** Admin yang benar-benar memegang kunci Builder. */
    private function adminBuilder(): User
    {
        $uid = DB::table('HRIS_Page_Access')
            ->where('Flag_Aktif', 'Y')
            ->where('Jenis_page', 'menuSopBuilderPage')
            ->whereNotNull('UserID_Web')
            ->value('UserID_Web');

        $u = $uid ? User::find($uid) : null;
        if (! $u) {
            $this->markTestSkipped('Tak ada user dengan menuSopBuilderPage.');
        }

        return $u;
    }

    /**
     * Isian tata kelola yang sah untuk blok `Dokumen_Baru`.
     *
     * Sejak DDL-2026-016, Builder adalah pintu KEDUA tempat dokumen lahir, dan
     * kewajibannya sama dengan pintu Master: jenis (bila tipe itu punya jenis
     * yang sah) dan departemen pemilik. Dikumpulkan di satu helper supaya penjaga
     * LAIN yang diuji berkas ini — pengesah wajib, dua jalur dokumen — tetap
     * sampai ke gerbangnya masing-masing, bukan tersandung lebih dulu di sini.
     *
     * Departemen datang dari MASTER KODE DEPARTEMEN sejak DDL-2026-027, sebagai
     * hashid — bukan lagi id mentah sub-divisi HRIS.
     */
    private function tataKelola(string $tipe = 'DOKUMEN'): array
    {
        $opsi = app(\App\Services\LMS\SOP\SopJenisDokumenService::class)->opsi($tipe);
        $dep = app(\App\Services\SYSTEM\MasterData\Departemen\DepartemenService::class)->opsi();
        $entitas = app(\App\Services\SYSTEM\MasterData\Entitas\EntitasService::class)->opsi();
        $apqc = app(\App\Services\LMS\SOP\SopApqcService::class)->opsi($tipe);

        $out = ['Departemen_Id' => $dep !== [] ? $dep[0]['Id'] : null];
        if ($opsi !== []) {
            $out['Jenis_Dokumen_Id'] = $opsi[0]['Id'];
        }
        if ($entitas !== []) {
            $out['Entitas_Pemilik_Id'] = $entitas[0]['Id'];
            $out['Entitas_Lingkup_Id'] = $entitas[0]['Id'];
        }
        /* Kewajiban APQC diturunkan dari master: hanya disertakan bila tipe ini
           memang punya proses yang sah, sama seperti jenis dokumen. */
        if ($apqc !== []) {
            $out['APQC_Id'] = $apqc[0]['Id'];
        }

        return $out;
    }

    /** Paket DRAFT beserta salah satu sectionnya — cabang tabel live. */
    private function paketDraft(): object
    {
        $row = DB::table('N_SOP_Master_Paket as p')
            ->join('N_SOP_Paket_Section as s', 's.Id_SOP_Paket', '=', 'p.Id_SOP_Paket')
            ->where('p.Status', SopStatus::PAKET_DRAFT)
            ->where('p.Flag_Aktif', SopStatus::AKTIF)
            ->where('s.Flag_Aktif', SopStatus::AKTIF)
            ->orderByDesc('p.Id_SOP_Paket')
            ->first(['p.Id_SOP_Paket', 's.Id_SOP_Section']);

        if (! $row) {
            $this->markTestSkipped('Tak ada paket DRAFT bersection untuk diuji.');
        }

        return $row;
    }

    /**
     * Dokumen DOKUMEN yang versi aktifnya sudah TERBIT.
     *
     * @param  int|null  $kecualiPaket  Lewati dokumen yang SUDAH dipakai paket ini.
     *                                  Satu SOP hanya boleh muncul sekali per paket
     *                                  (rollup berkunci paket+versi, bukan item), jadi
     *                                  tanpa penyaringan ini uji bisa gagal karena
     *                                  aturan yang sama sekali lain.
     */
    private function dokumenTerbit(?int $kecualiPaket = null): object
    {
        $row = DB::table('N_SOP_Dokumen as d')
            ->join('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'd.Id_Versi_Aktif')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Tipe', 'DOKUMEN')
            ->where('v.Status', SopStatus::PAKET_PUBLISHED)
            ->when($kecualiPaket, fn ($q) => $q->whereNotExists(
                fn ($s) => $s->from('N_SOP_Paket_Item as i')
                    ->whereColumn('i.Id_SOP_Dokumen', 'd.Id_SOP_Dokumen')
                    ->where('i.Id_SOP_Paket', $kecualiPaket)
                    ->where('i.Flag_Aktif', SopStatus::AKTIF)))
            ->orderByDesc('d.Id_SOP_Dokumen')
            ->first(['d.Id_SOP_Dokumen', 'd.Judul', 'd.Nomor_SOP_Resmi', 'v.Id_SOP_Item_Versi']);

        if (! $row) {
            $this->markTestSkipped('Tak ada Dokumen bertipe DOKUMEN dengan versi terbit.');
        }

        return $row;
    }

    /** Konten PDF di library SOP yang lolos verifikasi — syarat item DOKUMEN. */
    private function kontenPdfSop(): int
    {
        $id = DB::table('N_LMS_Master_Konten as m')
            ->join('N_LMS_Master_Konten_Berkas as b', 'b.Id_Master_Konten', '=', 'm.Id_Master_Konten')
            ->where('m.Scope', 'SOP')
            ->where('m.Flag_Aktif', 'Y')
            ->whereIn('b.Status_Verifikasi', ['SIAP', 'LAMA'])
            ->where(fn ($q) => $q->where('b.Ext', 'pdf')->orWhere('b.Type_File', 'like', '%pdf%'))
            ->value('m.Id_Master_Konten');

        if (! $id) {
            $this->markTestSkipped('Tak ada konten PDF dokumen yang terverifikasi.');
        }

        return (int) $id;
    }

    private function enc($id): string
    {
        return Hashids::connection('custom')->encode((int) $id);
    }

    /* ── yang ditolak ──────────────────────────────────────────────── */

    /**
     * Tanpa dokumen, penolakannya harus bisa DIKERJAKAN admin.
     *
     * Pesan lama — "The judul item field is required when id s o p dokumen is
     * not present" — menyebut dua field yang keduanya tak ada di layar. Itu
     * bukan sekadar jelek: ia mengarahkan admin mencari kolom judul yang memang
     * sudah dihapus Fase 6.
     */
    public function test_item_ber_versi_tanpa_dokumen_ditolak_dengan_pesan_yang_bisa_dikerjakan(): void
    {
        // APP_URL repo ini tanpa skema; tanpa ini permintaan uji meleset dari
        // route-nya dan berbalas 404. Lihat SopFormBerkasTest.
        URL::forceRootUrl('http://localhost');

        $p = $this->paketDraft();

        $res = $this->actingAs($this->adminBuilder())
            ->postJson("/api/v1/sop/admin/builder/paket/{$this->enc($p->Id_SOP_Paket)}/item/store", [
                'Tipe_Item' => 'DOKUMEN',
                'Id_SOP_Section' => $this->enc($p->Id_SOP_Section),
                'Judul_Item' => '',
            ]);

        $res->assertStatus(422);

        $pesan = (string) $res->json('message');
        $this->assertStringContainsString('Dokumen', $pesan);
        $this->assertStringNotContainsString('id s o p dokumen', strtolower($pesan),
            'Pesannya masih pesan mentah Laravel — admin tak bisa berbuat apa-apa dengannya.');
    }

    /* ── yang diterima ─────────────────────────────────────────────── */

    /**
     * Item baru MENUMPANG versi dokumennya, bukan melahirkan v1 sendiri.
     *
     * Versi milik-item (Id_SOP_Dokumen NULL) tak terlihat oleh apa pun yang
     * menelusuri lewat dokumen, DAN lolos dari penjaga "tak boleh terbit tanpa
     * pengesah" yang menyaring lewat join ke N_SOP_Dokumen. Jadi yang diperiksa
     * di sini bukan cuma "berhasil 201", melainkan bahwa TIDAK ADA baris versi
     * baru yang lahir.
     */
    public function test_item_baru_menumpang_versi_dokumennya(): void
    {
        URL::forceRootUrl('http://localhost');

        $p = $this->paketDraft();
        $d = $this->dokumenTerbit((int) $p->Id_SOP_Paket);

        $versiSebelum = DB::table('N_SOP_Item_Versi')->count();

        $res = $this->actingAs($this->adminBuilder())
            ->postJson("/api/v1/sop/admin/builder/paket/{$this->enc($p->Id_SOP_Paket)}/item/store", [
                'Tipe_Item' => 'DOKUMEN',
                'Id_SOP_Section' => $this->enc($p->Id_SOP_Section),
                'Id_SOP_Dokumen' => $this->enc($d->Id_SOP_Dokumen),
                'Mode_Akses' => 'VIEW_ONLY',
                'Flag_Wajib' => true,
            ]);

        $res->assertStatus(201);

        $idItem = Hashids::connection('custom')->decode($res->json('result.Id_SOP_Paket_Item'))[0] ?? null;
        $this->assertNotNull($idItem);

        $item = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $idItem)->first();

        $this->assertSame((int) $d->Id_SOP_Dokumen, (int) $item->Id_SOP_Dokumen);
        $this->assertSame((int) $d->Id_SOP_Item_Versi, (int) $item->Id_Item_Versi_Aktif,
            'Pointer tak menunjuk versi aktif dokumennya — itemnya tak menyajikan apa pun.');
        $this->assertSame($d->Judul, $item->Judul_Item,
            'Identitas tak dicerminkan dari dokumen; judulnya akan "kembali sendiri" pada sinkron berikutnya.');

        $this->assertSame($versiSebelum, DB::table('N_SOP_Item_Versi')->count(),
            'Ada baris versi baru yang lahir — item kembali punya versi sendiri di luar dokumennya.');
    }

    /* ── syaratnya kini SAMA di kedua cabang ──────────────────────── */

    /**
     * Paket TERBIT tak lagi boleh menukar dokumen dengan judul.
     *
     * Dulu cabang itu ada dan beralasan: N_SOP_Paket_Draft_Item belum punya
     * kolom Id_SOP_Dokumen, jadi item baru di paket terbit membawa judulnya
     * sendiri dan dokumennya dibuat belakangan saat promosi (ensureForItem).
     *
     * Yang berubah bukan tabelnya melainkan penilaian atas jalur itu: dokumen
     * yang lahir dari promosi melewati SETIAP tahap pengesahan, dan
     * createForItem() menyalin Nomor_SOP_Resmi tanpa lewat penjaga nomor kembar.
     * Sejak 27 Agu 2026 kedua cabang menuntut hal yang sama — dokumennya sudah
     * ada — dan environment yang belum menjalankan DDL-2026-033 ditolak di
     * storeItem() dengan pesan yang menyebut DDL-nya, bukan dibiarkan menempuh
     * jalur yang diam-diam melahirkan dokumen.
     */
    public function test_kedua_cabang_menuntut_dokumen_bukan_judul(): void
    {
        $c = app(SopAdminBuilderController::class);
        $m = new \ReflectionMethod($c, 'pastikanIdentitasItem');
        $m->setAccessible(true);

        $terbit = fn (array $d) => $m->invoke($c, $d, true);
        $draft = fn (array $d) => $m->invoke($c, $d, false);

        // Judul saja TIDAK cukup lagi, di cabang mana pun.
        $this->assertNotNull($terbit(['Tipe_Item' => 'DOKUMEN', 'Judul_Item' => 'SOP Uji']),
            'Judul masih diterima sebagai pengganti dokumen di paket terbit — pintu keduanya belum tertutup.');
        $this->assertNotNull($draft(['Tipe_Item' => 'DOKUMEN', 'Judul_Item' => 'SOP Uji']));

        // Dokumennya yang menentukan, juga di kedua cabang.
        $this->assertNull($terbit(['Tipe_Item' => 'DOKUMEN', 'Id_SOP_Dokumen' => 7]));
        $this->assertNull($draft(['Tipe_Item' => 'DOKUMEN', 'Id_SOP_Dokumen' => 7]));

        // Tipe tanpa dokumen tetap membawa judulnya sendiri, di kedua cabang.
        $this->assertNull($draft(['Tipe_Item' => 'PARAGRAF', 'Judul_Item' => 'Pengantar']));
        $this->assertNotNull($draft(['Tipe_Item' => 'PARAGRAF', 'Judul_Item' => '']));
        $this->assertNull($terbit(['Tipe_Item' => 'PARAGRAF', 'Judul_Item' => 'Pengantar']));
    }

    /* ── dokumen baru langsung dari Builder ───────────────────────── */

    /**
     * Builder bukan lagi pintu lompat penerbitan.
     *
     * Sejak versi punya Pengajuan → Review → Pengesahan → Terbit & Sebar,
     * dokumen baru harus diselesaikan di Master lalu dipilih di Builder setelah
     * Filing and Registered. Pagar hidup di server, bukan cuma tab UI.
     */
    public function test_dokumen_baru_dari_builder_ditolak_dan_tak_menulis_apa_pun(): void
    {
        URL::forceRootUrl('http://localhost');

        $p = $this->paketDraft();
        $konten = $this->kontenPdfSop();
        $sebelumDok = DB::table('N_SOP_Dokumen')->count();
        $sebelumItem = DB::table('N_SOP_Paket_Item')->count();

        $res = $this->actingAs($this->adminBuilder())
            ->postJson("/api/v1/sop/admin/builder/paket/{$this->enc($p->Id_SOP_Paket)}/item/store", [
                'Tipe_Item' => 'DOKUMEN',
                'Id_SOP_Section' => $this->enc($p->Id_SOP_Section),
                'Dokumen_Baru' => $this->tataKelola() + [
                    'Judul' => 'SOP Uji Pintu Lompat',
                    'Id_Master_Konten' => $this->enc($konten),
                    'Pengesah' => [[
                        'Peran' => 'Disahkan oleh', 'Nama' => 'Penguji', 'Jabatan' => 'QA',
                    ]],
                ],
            ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('master dokumen', strtolower((string) $res->json('message')));
        $this->assertSame($sebelumDok, DB::table('N_SOP_Dokumen')->count());
        $this->assertSame($sebelumItem, DB::table('N_SOP_Paket_Item')->count());
    }

    /**
     * Dokumen terpilih DITAMBAH muatan dokumen baru: tetap ditolak, tetap tanpa kembar.
     *
     * Bentuk permintaan ini datang dari tab yang belum disegarkan — layar lama
     * masih memegang blok "buat dokumen baru" yang sudah dicabut. Dulu ia
     * ditolak oleh penjaga khusus ("kirim salah satu"); sejak jalurnya hilang,
     * yang menolaknya penjaga yang lebih pokok: Dokumen_Baru tak diterima sama
     * sekali.
     *
     * Yang dijaga tetap sama dan itulah bagian yang penting — tak ada dokumen
     * terkendali kembar yang telanjur lahir dari permintaan yang ditolak.
     */
    public function test_muatan_dokumen_baru_ditolak_walau_dokumen_sudah_dipilih(): void
    {
        URL::forceRootUrl('http://localhost');

        $p = $this->paketDraft();
        $d = $this->dokumenTerbit((int) $p->Id_SOP_Paket);
        $konten = $this->kontenPdfSop();

        $sebelum = DB::table('N_SOP_Dokumen')->count();

        $res = $this->actingAs($this->adminBuilder())
            ->postJson("/api/v1/sop/admin/builder/paket/{$this->enc($p->Id_SOP_Paket)}/item/store", [
                'Tipe_Item' => 'DOKUMEN',
                'Id_SOP_Section' => $this->enc($p->Id_SOP_Section),
                'Id_SOP_Dokumen' => $this->enc($d->Id_SOP_Dokumen),
                'Dokumen_Baru' => $this->tataKelola() + [
                    'Judul' => 'SOP Kembar Yang Tak Boleh Lahir',
                    'Id_Master_Konten' => $this->enc($konten),
                    'Disahkan_Oleh' => 'Penguji Otomatis',
                ],
            ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('master dokumen', strtolower((string) $res->json('message')),
            'Penolakannya tak menyebut ke mana dokumen baru harus dibuat.');

        $this->assertSame($sebelum, DB::table('N_SOP_Dokumen')->count(),
            'Ada dokumen yang telanjur dibuat padahal permintaannya ditolak.');
        $this->assertSame(0,
            DB::table('N_SOP_Dokumen')->where('Judul', 'SOP Kembar Yang Tak Boleh Lahir')->count());
    }

    /**
     * Dokumen tanpa versi terbit TAK BOLEH ditautkan ke item.
     *
     * Itemnya akan sah menurut skema — judul benar, nomor SOP benar, kartunya
     * terlihat lengkap — dan karyawan yang membukanya tak menerima apa pun.
     * Cacatnya tak terdeteksi di mana pun sampai ada yang benar-benar mencoba
     * membaca, jadi penjaganya harus di pintu masuk, bukan di laporan.
     */
    public function test_dokumen_tanpa_versi_terbit_tak_bisa_ditautkan(): void
    {
        URL::forceRootUrl('http://localhost');

        $p = $this->paketDraft();

        $kosong = DB::table('N_SOP_Dokumen as d')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Tipe', 'DOKUMEN')
            ->whereNotExists(fn ($q) => $q->from('N_SOP_Item_Versi as v')
                ->whereColumn('v.Id_SOP_Dokumen', 'd.Id_SOP_Dokumen')
                ->where('v.Status', SopStatus::PAKET_PUBLISHED))
            ->value('d.Id_SOP_Dokumen');

        if (! $kosong) {
            $this->markTestSkipped('Tak ada dokumen tanpa versi terbit untuk diuji.');
        }

        $sebelum = DB::table('N_SOP_Paket_Item')->count();

        $res = $this->actingAs($this->adminBuilder())
            ->postJson("/api/v1/sop/admin/builder/paket/{$this->enc($p->Id_SOP_Paket)}/item/store", [
                'Tipe_Item' => 'DOKUMEN',
                'Id_SOP_Section' => $this->enc($p->Id_SOP_Section),
                'Id_SOP_Dokumen' => $this->enc($kosong),
            ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('belum punya versi berlaku', strtolower((string) $res->json('message')));

        $this->assertSame($sebelum, DB::table('N_SOP_Paket_Item')->count(),
            'Itemnya telanjur dibuat padahal dokumennya belum bisa dipakai.');
    }

    /**
     * Tapi item yang TERLANJUR menunjuk dokumen kosong tetap bisa disunting.
     *
     * Penjaganya hanya berlaku saat tautannya baru atau berubah. Menolaknya
     * pada penyuntingan biasa akan mengunci admin dari satu-satunya layar
     * tempat item itu bisa dibetulkan — penjaga yang justru menyandera.
     */
    public function test_item_lama_bertaut_dokumen_kosong_tetap_bisa_disunting(): void
    {
        URL::forceRootUrl('http://localhost');

        $item = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->whereNotNull('i.Id_SOP_Dokumen')
            ->whereNull('i.Id_Item_Versi_Aktif')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('p.Status', SopStatus::PAKET_DRAFT)
            ->first(['i.Id_SOP_Paket_Item', 'i.Id_SOP_Dokumen', 'i.Tipe_Item']);

        if (! $item) {
            $this->markTestSkipped('Tak ada item lama yang bertaut dokumen tanpa versi.');
        }

        $res = $this->actingAs($this->adminBuilder())
            ->putJson("/api/v1/sop/admin/builder/item/update/{$this->enc($item->Id_SOP_Paket_Item)}", [
                'Tipe_Item' => $item->Tipe_Item,
                'Id_SOP_Dokumen' => $this->enc($item->Id_SOP_Dokumen), // TIDAK berubah
                'Deskripsi_Item' => 'Disunting saat dokumennya masih kosong',
            ]);

        $res->assertOk();
    }

    /* ── invarian pointer ──────────────────────────────────────────── */

    /**
     * Pointer versi aktif tak boleh menunjuk versi yang belum terbit.
     *
     * Dokumen yang versi aktifnya masih DRAFT menyisakan pointer NULL — itemnya
     * belum menyajikan apa pun, dan itu keadaan yang SAH. Menunjuk baris DRAFT
     * berarti menyajikan aturan kerja yang belum disahkan siapa pun.
     */
    public function test_pointer_tak_pernah_menunjuk_versi_belum_terbit(): void
    {
        /* Dokumen yang belum punya SATU PUN versi terbit — hanya draft.
           Pointernya sengaja diarahkan ke baris draft itu di dalam transaksi
           uji: keadaan ini tak boleh ada di data nyata (invarian pointer), tapi
           justru itulah yang harus ditolak kalau suatu saat terjadi. */
        $draft = DB::table('N_SOP_Item_Versi as v')
            ->whereNotNull('v.Id_SOP_Dokumen')
            ->where('v.Status', SopStatus::PAKET_DRAFT)
            ->whereNotExists(fn ($q) => $q->from('N_SOP_Item_Versi as p')
                ->whereColumn('p.Id_SOP_Dokumen', 'v.Id_SOP_Dokumen')
                ->where('p.Status', SopStatus::PAKET_PUBLISHED))
            ->first(['v.Id_SOP_Dokumen', 'v.Id_SOP_Item_Versi']);

        if (! $draft) {
            $this->markTestSkipped('Tak ada dokumen yang seluruh versinya masih draft.');
        }

        $dok = (int) $draft->Id_SOP_Dokumen;
        DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $dok)
            ->update(['Id_Versi_Aktif' => $draft->Id_SOP_Item_Versi]);

        $item = DB::table('N_SOP_Paket_Item')->where('Flag_Aktif', SopStatus::AKTIF)
            ->value('Id_SOP_Paket_Item');
        if (! $item) {
            $this->markTestSkipped('Tak ada item untuk diuji.');
        }

        app(SopItemService::class)->tautkanDokumen((int) $item, (int) $dok, 1);

        $this->assertNull(
            DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $item)->value('Id_Item_Versi_Aktif'),
            'Pointer diarahkan ke versi yang belum terbit.',
        );
    }
}
