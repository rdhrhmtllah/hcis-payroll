<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopItemService;
use App\Services\LMS\SOP\SopPaketService;
use App\Services\LMS\SOP\SopSectionService;
use App\Services\LMS\SOP\Support\SopSetting;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use App\Services\LMS\SOP\Support\SopTipeItemAktif;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Sakelar hidup/mati per TIPE item.
 *
 * Artinya sempit dan disengaja: mematikan sebuah tipe menutup PEMBUATAN BARU,
 * tidak lebih. Item serta dokumen lama bertipe itu tetap hidup dan tetap
 * disajikan ke karyawan — sakelar yang ikut menyembunyikan data lama akan
 * mengubah angka kepatuhan historis, dan kewajiban baca yang lenyap tanpa jejak
 * jauh lebih mahal daripada tipe yang terlanjur dipakai.
 *
 * Yang diuji di sini EFEKNYA, bukan konstantanya: daftar yang isinya benar tak
 * membuktikan apa pun kalau tak ada satu pun penjaga yang membacanya.
 */
class SopTipeItemSakelarTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        /* WAJIB. Nilainya di-cache 10 menit lintas permintaan; tanpa
           pembersihan ini uji berikutnya mewarisi tipe yang sengaja dimatikan
           di sini, dan kegagalannya muncul di berkas yang sama sekali lain. */
        SopSetting::flush();

        parent::tearDown();
    }

    /** Matikan satu tipe untuk permintaan berikutnya. */
    private function matikan(string $tipe): void
    {
        DB::table('N_SOP_Setting')->updateOrInsert(
            ['Kode_Setting' => SopTipeItemAktif::kunci($tipe)],
            ['Nilai' => 'T', 'Updated_At' => now()],
        );
        SopSetting::flush();
    }

    private function adminBerkunci(string $kunci): User
    {
        $uid = DB::table('HRIS_Page_Access')
            ->where('Flag_Aktif', 'Y')
            ->where('Jenis_page', $kunci)
            ->whereNotNull('UserID_Web')
            ->value('UserID_Web');

        $u = $uid ? User::find($uid) : null;
        if (! $u) {
            $this->markTestSkipped("Tak ada user dengan {$kunci}.");
        }

        return $u;
    }

    /**
     * Paket DRAFT bersection — DIBUAT kalau belum ada.
     *
     * Sengaja tidak markTestSkipped(). Penjaga yang ujinya melewati diri sendiri
     * karena kebetulan basis data pengembangan tak punya paket draft adalah
     * penjaga yang tak pernah terlihat merah — dan penjaga yang tak pernah
     * terlihat merah tak membuktikan apa pun. Barisnya lahir di dalam transaksi
     * uji, jadi ikut mundur begitu ujinya selesai.
     */
    private function paketDraft(): object
    {
        $row = DB::table('N_SOP_Master_Paket as p')
            ->join('N_SOP_Paket_Section as s', 's.Id_SOP_Paket', '=', 'p.Id_SOP_Paket')
            ->where('p.Status', SopStatus::PAKET_DRAFT)
            ->where('p.Flag_Aktif', SopStatus::AKTIF)
            ->where('s.Flag_Aktif', SopStatus::AKTIF)
            ->orderByDesc('p.Id_SOP_Paket')
            ->first(['p.Id_SOP_Paket', 's.Id_SOP_Section']);

        if ($row) {
            return $row;
        }

        $uid = (int) $this->adminBerkunci('menuSopBuilderPage')->Id_Users;
        $paket = app(SopPaketService::class)->create([
            'Nama_Paket' => 'UJI-SAKELAR-TIPE Paket',
        ], $uid);

        $section = app(SopSectionService::class)->create($paket, [
            'Nama_Section' => 'UJI-SAKELAR-TIPE Section',
        ], $uid);

        return (object) ['Id_SOP_Paket' => (int) $paket, 'Id_SOP_Section' => (int) $section];
    }

    /** Item PARAGRAF di paket draft — juga dibuat kalau belum ada. */
    private function itemParagraf(): object
    {
        $item = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->where('i.Tipe_Item', 'PARAGRAF')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('p.Status', SopStatus::PAKET_DRAFT)
            ->first(['i.Id_SOP_Paket_Item', 'i.Judul_Item']);

        if ($item) {
            return $item;
        }

        $p = $this->paketDraft();
        $uid = (int) $this->adminBerkunci('menuSopBuilderPage')->Id_Users;

        $id = app(SopItemService::class)->create((int) $p->Id_SOP_Paket, [
            'Id_SOP_Section' => (int) $p->Id_SOP_Section,
            'Tipe_Item' => 'PARAGRAF',
            'Judul_Item' => 'UJI-SAKELAR-TIPE Pengantar',
            'Konten_Teks' => '<p>isi</p>',
        ], $uid);

        return (object) ['Id_SOP_Paket_Item' => (int) $id, 'Judul_Item' => 'UJI-SAKELAR-TIPE Pengantar'];
    }

    private function enc($id): string
    {
        return Hashids::connection('custom')->encode((int) $id);
    }

    /* ── bawaan = perilaku sebelum sakelar ini ada ─────────────────── */

    public function test_bawaan_menyalakan_semua_kecuali_absensi(): void
    {
        /* Menyalakan fitur ini tak boleh mengubah apa pun. ABSENSI memang sudah
           mati sebelumnya — dulu lewat satu baris filter di layar yang tak
           menyebut alasan dan tak bisa diubah siapa pun. */
        DB::table('N_SOP_Setting')
            ->whereIn('Kode_Setting', array_map(
                fn ($t) => SopTipeItemAktif::kunci($t), SopTipeItem::SEMUA))
            ->delete();
        SopSetting::flush();

        $aktif = SopTipeItemAktif::aktif();

        foreach (['DOKUMEN', 'FORM', 'VIDEO', 'KUIS', 'TAUTAN', 'PARAGRAF'] as $t) {
            $this->assertContains($t, $aktif, "Tipe {$t} mati padahal bawaannya hidup.");
        }
        $this->assertNotContains('ABSENSI', $aktif,
            'ABSENSI menyala — padahal storeItem menolaknya terang-terangan.');
    }

    public function test_urutannya_mengikuti_konstanta_bukan_urutan_baris_setelan(): void
    {
        /* Palet tipe dirender dari daftar ini. Susunan yang berubah mengikuti
           kapan admin terakhir menyentuh setelan membuat tombol berpindah
           tempat tanpa sebab yang bisa dilihat. */
        $aktif = SopTipeItemAktif::aktif();
        $urutSeharusnya = array_values(array_filter(SopTipeItem::SEMUA,
            fn ($t) => in_array($t, $aktif, true)));

        $this->assertSame($urutSeharusnya, $aktif);
    }

    /* ── pintu server, bukan cuma palet ────────────────────────────── */

    public function test_item_bertipe_mati_ditolak_walau_layar_tak_menawarkannya(): void
    {
        URL::forceRootUrl('http://localhost');

        $this->matikan('PARAGRAF');

        $p = $this->paketDraft();
        $sebelum = DB::table('N_SOP_Paket_Item')->count();

        $res = $this->actingAs($this->adminBerkunci('menuSopBuilderPage'))
            ->postJson("/api/v1/sop/admin/builder/paket/{$this->enc($p->Id_SOP_Paket)}/item/store", [
                'Tipe_Item' => 'PARAGRAF',
                'Id_SOP_Section' => $this->enc($p->Id_SOP_Section),
                'Judul_Item' => 'Paragraf Uji Sakelar',
                'Konten_Teks' => '<p>isi</p>',
            ]);

        $res->assertStatus(422);
        $pesan = strtolower((string) $res->json('message'));
        $this->assertStringContainsString('dinonaktifkan', $pesan);
        $this->assertStringContainsString('kebijakan global dokumen', $pesan,
            'Penolakannya tak menyebut di mana sakelarnya — admin tak tahu harus berbuat apa. '
            .'Namanya harus sama persis dengan label menu di sidebar, bukan nama internal halaman.');

        $this->assertSame($sebelum, DB::table('N_SOP_Paket_Item')->count(),
            'Itemnya telanjur tertulis padahal permintaannya ditolak.');
    }

    public function test_dokumen_master_bertipe_mati_ditolak(): void
    {
        URL::forceRootUrl('http://localhost');

        $identitas = DB::table('N_SOP_Dokumen')
            ->whereNotNull('Departemen_Id')
            ->whereNotNull('Entitas_Pemilik_Id')
            ->whereNotNull('Entitas_Lingkup_Id')
            ->first(['Departemen_Id', 'Entitas_Pemilik_Id', 'Entitas_Lingkup_Id']);

        if (! $identitas) {
            $this->markTestSkipped('Tak ada dokumen beridentitas lengkap untuk dijadikan contoh.');
        }

        $this->matikan('VIDEO');

        $sebelum = DB::table('N_SOP_Dokumen')->count();

        $res = $this->actingAs($this->adminBerkunci('menuSopDokumenPage'))
            ->postJson('/api/v1/sop/admin/dokumen/store', [
                'Judul' => 'SOP Video Uji Sakelar',
                'Tipe' => 'VIDEO',
                'Departemen_Id' => $this->enc($identitas->Departemen_Id),
                'Entitas_Pemilik_Id' => $this->enc($identitas->Entitas_Pemilik_Id),
                'Entitas_Lingkup_Id' => $this->enc($identitas->Entitas_Lingkup_Id),
            ]);

        $res->assertStatus(422);
        $this->assertSame($sebelum, DB::table('N_SOP_Dokumen')->count(),
            'Dokumen bertipe mati telanjur lahir.');
    }

    public function test_pemilih_dokumen_berhenti_menawarkan_tipe_mati(): void
    {
        URL::forceRootUrl('http://localhost');

        $admin = $this->adminBerkunci('menuSopBuilderPage');

        $sebelum = $this->actingAs($admin)->getJson('/api/v1/sop/admin/builder/dokumen/options');
        $sebelum->assertOk();
        $adaDokumen = collect($sebelum->json('result.rows') ?? [])
            ->contains(fn ($d) => ($d['Tipe'] ?? null) === 'DOKUMEN');
        if (! $adaDokumen) {
            $this->markTestSkipped('Pemilih tak memuat satu pun dokumen bertipe DOKUMEN.');
        }

        $this->matikan('DOKUMEN');

        $sesudah = $this->actingAs($admin)->getJson('/api/v1/sop/admin/builder/dokumen/options');
        $sesudah->assertOk();

        $this->assertFalse(
            collect($sesudah->json('result.rows') ?? [])->contains(fn ($d) => ($d['Tipe'] ?? null) === 'DOKUMEN'),
            'Pemilih masih menawarkan dokumen bertipe mati — memasang item baru adalah pembuatan juga.');
    }

    /* ── yang TIDAK boleh ikut mati ────────────────────────────────── */

    public function test_item_lama_bertipe_mati_tetap_hidup(): void
    {
        /* Inti kesepakatannya, dan diuji lewat EFEK: baris itemnya masih ada dan
           masih Flag_Aktif setelah tipenya dimatikan. Uji yang cuma memeriksa
           konstanta tak akan pernah menangkap kalau suatu saat ada yang
           menambahkan penyaringan tipe di hilir. */
        $tipe = DB::table('N_SOP_Paket_Item')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereIn('Tipe_Item', ['DOKUMEN', 'FORM', 'VIDEO', 'PARAGRAF', 'TAUTAN'])
            ->value('Tipe_Item');

        if (! $tipe) {
            $this->markTestSkipped('Tak ada item aktif untuk diuji.');
        }

        $sebelum = DB::table('N_SOP_Paket_Item')
            ->where('Flag_Aktif', SopStatus::AKTIF)->where('Tipe_Item', $tipe)->count();

        $this->matikan($tipe);

        $sesudah = DB::table('N_SOP_Paket_Item')
            ->where('Flag_Aktif', SopStatus::AKTIF)->where('Tipe_Item', $tipe)->count();

        $this->assertSame($sebelum, $sesudah,
            "Mematikan tipe {$tipe} ikut menyentuh item lama — kewajiban baca karyawan hilang tanpa jejak.");
        $this->assertGreaterThan(0, $sesudah);
    }

    public function test_menyunting_item_lama_bertipe_mati_tetap_boleh(): void
    {
        URL::forceRootUrl('http://localhost');

        $item = $this->itemParagraf();

        $this->matikan('PARAGRAF');

        /* Mematikan sebuah tipe tak boleh membekukan item lama bertipe itu.
           Kalau ikut beku, satu sakelar mengunci setiap perbaikan yang tak ada
           hubungannya dengan tipenya — termasuk memperbaiki salah ketik. */
        $res = $this->actingAs($this->adminBerkunci('menuSopBuilderPage'))
            ->putJson("/api/v1/sop/admin/builder/item/update/{$this->enc($item->Id_SOP_Paket_Item)}", [
                'Tipe_Item' => 'PARAGRAF',
                'Judul_Item' => $item->Judul_Item,
                'Deskripsi_Item' => 'Disunting saat tipenya sedang dimatikan',
            ]);

        $res->assertOk();
    }

    public function test_tipe_form_menerima_seluruh_jenis_konten_library(): void
    {
        $this->assertTrue(SopTipeItem::kontenCocok(SopTipeItem::FORM, 'document'), 'FORM harus menerima document');
        $this->assertTrue(SopTipeItem::kontenCocok(SopTipeItem::FORM, 'image'), 'FORM harus menerima image');
        $this->assertTrue(SopTipeItem::kontenCocok(SopTipeItem::FORM, 'video'), 'FORM harus menerima video');
        $this->assertFalse(SopTipeItem::kontenCocok(SopTipeItem::DOKUMEN, 'image'), 'DOKUMEN hanya menerima document');
        $this->assertFalse(SopTipeItem::kontenCocok(SopTipeItem::VIDEO, 'document'), 'VIDEO hanya menerima video');
    }
}

