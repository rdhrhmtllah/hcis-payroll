<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * "Salin item ke paket lain" — item + versi aktifnya digandakan jadi baris
 * BARU di paket DRAFT lain. Item asal & seluruh riwayat progres karyawan di
 * paket asal tidak pernah disentuh. Lihat SopItemService::copyToPaket() &
 * SopAdminBuilderController::copyItemToPaket().
 *
 * Data uji disisipkan ke tabel nyata lalu di-rollback oleh DatabaseTransactions
 * (pola sama dengan SopPenjadwalanTargetServiceTest) — perlu, karena tes ini
 * benar-benar menulis baris item+versi baru.
 */
class SopBuilderCopyItemToPaketTest extends TestCase
{
    use DatabaseTransactions;

    private function adminUser(): ?User
    {
        return User::whereNotNull('Id_Users')->get()
            ->first(fn ($u) => $u->memilikiJabatan('menuSopBuilderPage'));
    }

    private function enc(int $id): string
    {
        return Hashids::connection('custom')->encode($id);
    }

    private function draftSectionSelainPaket(int $idPaketDikecualikan): ?object
    {
        return DB::table('N_SOP_Paket_Section as s')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 's.Id_SOP_Paket')
            ->where('p.Status', SopStatus::PAKET_DRAFT)
            ->where('p.Flag_Aktif', SopStatus::AKTIF)
            ->where('s.Flag_Aktif', SopStatus::AKTIF)
            ->where('s.Id_SOP_Paket', '!=', $idPaketDikecualikan)
            ->first(['s.Id_SOP_Section', 's.Id_SOP_Paket']);
    }

    public function test_copy_creates_a_new_item_and_version_in_the_target_draft_package(): void
    {
        $admin = $this->adminUser();
        if (! $admin) {
            $this->markTestSkipped('Tak ada user dgn akses menuSopBuilderPage di data dev.');
        }

        $src = DB::table('N_SOP_Paket_Item')
            ->whereIn('Tipe_Item', ['DOKUMEN', 'FORM'])
            ->whereNotNull('Id_Item_Versi_Aktif')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->first();
        if (! $src) {
            $this->markTestSkipped('Tak ada item DOKUMEN/FORM ber-versi aktif di data dev.');
        }

        $target = $this->draftSectionSelainPaket((int) $src->Id_SOP_Paket);
        if (! $target) {
            $this->markTestSkipped('Tak ada paket draft lain ber-section di data dev.');
        }

        $countSebelum = DB::table('N_SOP_Paket_Item')->count();
        $progresSebelum = DB::table('N_SOP_Progress')->where('Id_SOP_Paket_Item', $src->Id_SOP_Paket_Item)->count();

        $res = $this->actingAs($admin)->postJson(
            "/api/v1/sop/admin/builder/item/{$this->enc($src->Id_SOP_Paket_Item)}/copy-to-paket",
            [
                'Id_SOP_Paket_Tujuan' => $this->enc($target->Id_SOP_Paket),
                'Id_SOP_Section_Tujuan' => $this->enc($target->Id_SOP_Section),
            ]
        );

        $res->assertStatus(201);
        $newId = Hashids::connection('custom')->decode($res->json('result.Id_SOP_Paket_Item'))[0] ?? null;
        $this->assertNotNull($newId, 'Respons sukses wajib menyertakan id item baru.');

        $newRow = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $newId)->first();
        $this->assertNotNull($newRow);
        $this->assertSame((int) $target->Id_SOP_Paket, (int) $newRow->Id_SOP_Paket);
        $this->assertSame((int) $target->Id_SOP_Section, (int) $newRow->Id_SOP_Section);
        $this->assertSame($src->Judul_Item, $newRow->Judul_Item, 'Judul tak boleh ditambah "(Salinan)" — beda konteks paket, bukan duplikat berdampingan.');
        $this->assertSame($src->Tipe_Item, $newRow->Tipe_Item);

        /*
         * KONTRAK BERUBAH DI FASE 6 (keputusan produk user 2026-08-10).
         *
         * Dulu: salinan mendapat DOKUMEN SENDIRI dan satu baris versi DRAFT
         * baru, sehingga pointer versi aktifnya sengaja NULL sampai paket
         * dirilis.
         *
         * Sekarang: salinan BERBAGI dokumen sumber. Ia mewarisi seluruh riwayat
         * versi dokumen itu, jadi TIDAK ADA baris versi baru — menulis
         * Versi_Ke=1 pada dokumen yang sudah punya versi 1 akan ditolak unique
         * index terfilter 26B (error 2601). Pointer justru DIISI, tapi hanya ke
         * versi PUBLISHED; invarian "pointer tak boleh menunjuk DRAFT" tetap
         * berlaku dan diuji di bawah.
         */
        $this->assertSame((int) $src->Id_SOP_Dokumen, (int) $newRow->Id_SOP_Dokumen,
            'Salin ke paket lain kini berbagi dokumen sumber — itulah yang membuat revisi menyebar.');

        $this->assertSame(0, DB::table('N_SOP_Item_Versi')->where('Id_SOP_Paket_Item', $newId)->count(),
            'Tak boleh ada baris versi baru: salinan mewarisi riwayat versi dokumennya.');

        if ($newRow->Id_Item_Versi_Aktif !== null) {
            $this->assertSame(SopStatus::PAKET_PUBLISHED,
                DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $newRow->Id_Item_Versi_Aktif)->value('Status'),
                'Pointer versi aktif hanya boleh menunjuk versi PUBLISHED.');
        }

        // Item asal tak berubah sama sekali.
        $srcSesudah = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $src->Id_SOP_Paket_Item)->first();
        $this->assertSame($src->Urutan, $srcSesudah->Urutan);
        $this->assertSame($src->Id_SOP_Paket, $srcSesudah->Id_SOP_Paket);
        $this->assertSame($src->Id_Item_Versi_Aktif, $srcSesudah->Id_Item_Versi_Aktif);

        $progresSesudah = DB::table('N_SOP_Progress')->where('Id_SOP_Paket_Item', $src->Id_SOP_Paket_Item)->count();
        $this->assertSame($progresSebelum, $progresSesudah, 'Progres karyawan pada item asal tak boleh berubah.');

        $this->assertSame($countSebelum + 1, DB::table('N_SOP_Paket_Item')->count());
    }

    public function test_copy_rejects_a_published_target_package(): void
    {
        $admin = $this->adminUser();
        if (! $admin) {
            $this->markTestSkipped('Tak ada user dgn akses menuSopBuilderPage di data dev.');
        }

        $src = DB::table('N_SOP_Paket_Item')->where('Flag_Aktif', SopStatus::AKTIF)->first();
        if (! $src) {
            $this->markTestSkipped('Tak ada item di data dev.');
        }

        $publishedPaket = DB::table('N_SOP_Master_Paket')
            ->where('Status', SopStatus::PAKET_PUBLISHED)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Id_SOP_Paket', '!=', $src->Id_SOP_Paket)
            ->value('Id_SOP_Paket');
        if (! $publishedPaket) {
            $this->markTestSkipped('Tak ada paket published lain di data dev.');
        }

        $countSebelum = DB::table('N_SOP_Paket_Item')->count();

        // Section tak dicek sama sekali di kasus ini — gerbang status paket lebih
        // dulu menolak, jadi id sembarang (asal valid di-encode) sudah cukup.
        $res = $this->actingAs($admin)->postJson(
            "/api/v1/sop/admin/builder/item/{$this->enc($src->Id_SOP_Paket_Item)}/copy-to-paket",
            [
                'Id_SOP_Paket_Tujuan' => $this->enc($publishedPaket),
                'Id_SOP_Section_Tujuan' => $this->enc($src->Id_SOP_Paket_Item),
            ]
        );

        $res->assertStatus(422);
        $this->assertSame($countSebelum, DB::table('N_SOP_Paket_Item')->count());
    }

    public function test_copy_rejects_a_section_that_belongs_to_a_different_package(): void
    {
        $admin = $this->adminUser();
        if (! $admin) {
            $this->markTestSkipped('Tak ada user dgn akses menuSopBuilderPage di data dev.');
        }

        $src = DB::table('N_SOP_Paket_Item')->where('Flag_Aktif', SopStatus::AKTIF)->first();
        if (! $src) {
            $this->markTestSkipped('Tak ada item di data dev.');
        }

        $targetPaket = DB::table('N_SOP_Master_Paket')
            ->where('Status', SopStatus::PAKET_DRAFT)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Id_SOP_Paket', '!=', $src->Id_SOP_Paket)
            ->value('Id_SOP_Paket');
        if (! $targetPaket) {
            $this->markTestSkipped('Tak ada paket draft lain di data dev.');
        }

        // Section MILIK PAKET LAIN (bukan target) — harus ditolak, bukan ditulis
        // sebagai baris item yatim lintas paket (peringatan di SopBuilderScope).
        $sectionAsing = DB::table('N_SOP_Paket_Section')
            ->where('Id_SOP_Paket', '!=', $targetPaket)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->value('Id_SOP_Section');
        if (! $sectionAsing) {
            $this->markTestSkipped('Tak ada section pembanding di data dev.');
        }

        $countSebelum = DB::table('N_SOP_Paket_Item')->count();

        $res = $this->actingAs($admin)->postJson(
            "/api/v1/sop/admin/builder/item/{$this->enc($src->Id_SOP_Paket_Item)}/copy-to-paket",
            [
                'Id_SOP_Paket_Tujuan' => $this->enc($targetPaket),
                'Id_SOP_Section_Tujuan' => $this->enc($sectionAsing),
            ]
        );

        $res->assertStatus(422);
        $this->assertSame($countSebelum, DB::table('N_SOP_Paket_Item')->count(), 'Tak boleh ada baris item yatim yang tertulis.');
    }

    public function test_copy_rejects_same_source_and_target_package(): void
    {
        $admin = $this->adminUser();
        if (! $admin) {
            $this->markTestSkipped('Tak ada user dgn akses menuSopBuilderPage di data dev.');
        }

        $src = DB::table('N_SOP_Paket_Item')->where('Flag_Aktif', SopStatus::AKTIF)->first();
        if (! $src) {
            $this->markTestSkipped('Tak ada item di data dev.');
        }

        $res = $this->actingAs($admin)->postJson(
            "/api/v1/sop/admin/builder/item/{$this->enc($src->Id_SOP_Paket_Item)}/copy-to-paket",
            [
                'Id_SOP_Paket_Tujuan' => $this->enc($src->Id_SOP_Paket),
                'Id_SOP_Section_Tujuan' => $this->enc($src->Id_SOP_Paket_Item),
            ]
        );

        $res->assertStatus(422);
    }

    /** Regresi utk fix SopItemService::cloneRow (isFile -> isVersioned). */
    public function test_kuis_item_copy_receives_a_version_row(): void
    {
        $admin = $this->adminUser();
        if (! $admin) {
            $this->markTestSkipped('Tak ada user dgn akses menuSopBuilderPage di data dev.');
        }

        $src = DB::table('N_SOP_Paket_Item')
            ->where('Tipe_Item', 'KUIS')
            ->whereNotNull('Id_Item_Versi_Aktif')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->first();
        if (! $src) {
            $this->markTestSkipped('Tak ada item KUIS ber-versi aktif di data dev.');
        }

        $target = $this->draftSectionSelainPaket((int) $src->Id_SOP_Paket);
        if (! $target) {
            $this->markTestSkipped('Tak ada paket draft lain ber-section di data dev.');
        }

        $res = $this->actingAs($admin)->postJson(
            "/api/v1/sop/admin/builder/item/{$this->enc($src->Id_SOP_Paket_Item)}/copy-to-paket",
            [
                'Id_SOP_Paket_Tujuan' => $this->enc($target->Id_SOP_Paket),
                'Id_SOP_Section_Tujuan' => $this->enc($target->Id_SOP_Section),
            ]
        );

        $res->assertStatus(201);
        $newId = Hashids::connection('custom')->decode($res->json('result.Id_SOP_Paket_Item'))[0] ?? null;
        $this->assertNotNull($newId);

        /*
         * KONTRAK BERUBAH DI FASE 6. Dulu KUIS salinan wajib mendapat baris
         * versi DRAFT sendiri — itu regresi isFile()/isVersioned yang diperbaiki
         * saat fitur ini dibangun.
         *
         * Sejak salinan BERBAGI dokumen, ia tak lagi butuh baris versi sendiri:
         * ia mewarisi versi dokumennya, berikut referensi ujiannya. Yang harus
         * dijaga sekarang adalah bahwa referensi ujian yang terpakai TETAP sama
         * dengan milik item asal — kalau tidak, kuis di paket tujuan menunjuk
         * ujian yang salah, dan itu justru kegagalan yang lebih buruk daripada
         * kuis yang tak bisa dipakai.
         */
        $newRow = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $newId)
            ->first(['Id_SOP_Dokumen', 'Id_Item_Versi_Aktif']);

        $this->assertSame((int) $src->Id_SOP_Dokumen, (int) $newRow->Id_SOP_Dokumen,
            'KUIS salinan berbagi dokumen sumber.');

        $this->assertSame(0, DB::table('N_SOP_Item_Versi')->where('Id_SOP_Paket_Item', $newId)->count(),
            'Tak ada baris versi baru: KUIS salinan mewarisi versi dokumennya.');

        $ujianAsli = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Item_Versi', $src->Id_Item_Versi_Aktif)->value('Id_Master_Ujian');
        $ujianTerpakai = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', $src->Id_SOP_Dokumen)
            ->where('Status', SopStatus::PAKET_PUBLISHED)
            ->orderByDesc('Versi_Ke')->value('Id_Master_Ujian');

        $this->assertSame($ujianAsli, $ujianTerpakai,
            'Referensi ujian yang dipakai paket tujuan wajib sama dengan milik item asal.');
    }

    /**
     * Item pada paket TERBIT yang punya draft revisi tampil di Builder dengan
     * token "di_…" — hashid dari PK N_SOP_Paket_Draft_Item, BUKAN dari
     * N_SOP_Paket_Item. Kedua tabel auto-increment sendiri-sendiri dan PK-nya
     * beririsan, jadi men-decode token itu lalu mencarinya di N_SOP_Paket_Item
     * akan menemukan item lain yang sama sekali tak berhubungan lalu
     * menyalinnya diam-diam. Endpoint wajib menolak token semacam ini.
     */
    public function test_draft_overlay_token_never_copies_an_unrelated_live_item(): void
    {
        $admin = $this->adminUser();
        if (! $admin) {
            $this->markTestSkipped('Tak ada user dgn akses menuSopBuilderPage di data dev.');
        }

        // Pasangan PK yang benar-benar bertabrakan antar dua tabel — inilah yang
        // membuat cacatnya menghasilkan data salah, bukan sekadar 404.
        $tabrakan = DB::table('N_SOP_Paket_Draft_Item as d')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'd.Id_SOP_Draft_Item')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->first(['d.Id_SOP_Draft_Item', 'i.Id_SOP_Paket_Item', 'i.Judul_Item', 'i.Id_SOP_Paket']);
        if (! $tabrakan) {
            $this->markTestSkipped('Tak ada PK draft-item yang beririsan dgn item live di data dev.');
        }

        $target = $this->draftSectionSelainPaket((int) $tabrakan->Id_SOP_Paket);
        if (! $target) {
            $this->markTestSkipped('Tak ada paket draft lain ber-section di data dev.');
        }

        $countSebelum = DB::table('N_SOP_Paket_Item')->count();

        $res = $this->actingAs($admin)->postJson(
            "/api/v1/sop/admin/builder/item/di_{$this->enc($tabrakan->Id_SOP_Draft_Item)}/copy-to-paket",
            [
                'Id_SOP_Paket_Tujuan' => $this->enc($target->Id_SOP_Paket),
                'Id_SOP_Section_Tujuan' => $this->enc($target->Id_SOP_Section),
            ]
        );

        $this->assertContains($res->status(), [400, 404, 409, 422], 'Token draft-overlay wajib ditolak, bukan diproses.');
        $this->assertSame(
            $countSebelum,
            DB::table('N_SOP_Paket_Item')->count(),
            "Item live yang tak berhubungan (\"{$tabrakan->Judul_Item}\") ikut tersalin gara-gara PK bertabrakan.",
        );
    }

    public function test_absensi_items_cannot_be_copied(): void
    {
        $admin = $this->adminUser();
        if (! $admin) {
            $this->markTestSkipped('Tak ada user dgn akses menuSopBuilderPage di data dev.');
        }

        $src = DB::table('N_SOP_Paket_Item')
            ->where('Tipe_Item', 'ABSENSI')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->first();
        if (! $src) {
            $this->markTestSkipped('Tak ada item ABSENSI di data dev.');
        }

        $target = $this->draftSectionSelainPaket((int) $src->Id_SOP_Paket);
        if (! $target) {
            $this->markTestSkipped('Tak ada paket draft lain ber-section di data dev.');
        }

        $countSebelum = DB::table('N_SOP_Paket_Item')->count();

        $res = $this->actingAs($admin)->postJson(
            "/api/v1/sop/admin/builder/item/{$this->enc($src->Id_SOP_Paket_Item)}/copy-to-paket",
            [
                'Id_SOP_Paket_Tujuan' => $this->enc($target->Id_SOP_Paket),
                'Id_SOP_Section_Tujuan' => $this->enc($target->Id_SOP_Section),
            ]
        );

        $res->assertStatus(422);
        $this->assertSame($countSebelum, DB::table('N_SOP_Paket_Item')->count());
    }
}
