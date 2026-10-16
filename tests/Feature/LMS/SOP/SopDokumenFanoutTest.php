<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopDokumenFanoutService;
use App\Services\LMS\SOP\SopItemVersionService;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Fase 3 Master Dokumen SOP — fan-out revisi ke semua paket perujuk.
 *
 * KENAPA SETIAP TEST DI SINI MENSIMULASIKAN BERBAGI. Data dev sepenuhnya 1
 * dokumen = 1 item (invarian yang dijaga sop:dokumen-verify), sehingga
 * fan-out adalah no-op pada data nyata. Tanpa simulasi, seluruh Fase 3 akan
 * "lulus" tanpa sekali pun dieksekusi. Tekniknya sama dengan yang sudah
 * terbukti di test_sumbu_penomoran_benar_benar_dokumen_bukan_item (Fase 2):
 * tautkan item KEDUA ke dokumen yang sama di dalam transaksi test, yang
 * di-rollback DatabaseTransactions di akhir.
 */
class SopDokumenFanoutTest extends TestCase
{
    use DatabaseTransactions;

    private function itemBerVersi(): ?object
    {
        return DB::table('N_SOP_Paket_Item')
            ->whereIn('Tipe_Item', SopTipeItem::VERSIONED)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('Id_SOP_Dokumen')
            ->first();
    }

    /** Item kedua yang merujuk dokumen yang sama, di paket BERBEDA. Null bila tak ada paket lain. */
    private function tautkanItemKeduaKeDokumen(object $item): ?int
    {
        $paketLain = DB::table('N_SOP_Master_Paket')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Id_SOP_Paket', '!=', $item->Id_SOP_Paket)
            ->value('Id_SOP_Paket');
        if (! $paketLain) {
            return null;
        }

        return (int) DB::table('N_SOP_Paket_Item')->insertGetId([
            'Id_SOP_Paket' => $paketLain,
            'Tipe_Item' => $item->Tipe_Item,
            'Judul_Item' => 'Uji fan-out',
            'Id_SOP_Dokumen' => $item->Id_SOP_Dokumen,
            'Urutan' => 9999,
            'Flag_Wajib' => $item->Flag_Wajib,
            'Flag_Highlight' => $item->Flag_Highlight,
            'Created_By' => 1,
            'Created_At' => now(),
            'Flag_Aktif' => SopStatus::AKTIF,
        ], 'Id_SOP_Paket_Item');
    }

    public function test_menemukan_semua_item_perujuk_bukan_hanya_pemicunya(): void
    {
        $item = $this->itemBerVersi();
        if (! $item) {
            $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');
        }

        $kedua = $this->tautkanItemKeduaKeDokumen($item);
        if (! $kedua) {
            $this->markTestSkipped('Tidak ada paket lain untuk simulasi berbagi.');
        }

        $items = app(SopDokumenFanoutService::class)->itemPerujuk((int) $item->Id_SOP_Dokumen);

        $this->assertContains((int) $item->Id_SOP_Paket_Item, $items);
        $this->assertContains($kedua, $items,
            'Item kedua yang merujuk dokumen yang sama WAJIB ikut terdaftar — kalau tidak, '
            .'karyawan di paketnya tetap membaca versi lama tanpa ada yang memberi tahu.');
    }

    public function test_paket_perujuk_unik_dan_mencakup_paket_lain(): void
    {
        $item = $this->itemBerVersi();
        if (! $item) {
            $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');
        }
        if (! $this->tautkanItemKeduaKeDokumen($item)) {
            $this->markTestSkipped('Tak ada paket lain.');
        }

        $paket = app(SopDokumenFanoutService::class)->paketPerujuk((int) $item->Id_SOP_Dokumen);

        $this->assertSame(array_values(array_unique($paket)), array_values($paket),
            'Daftar paket wajib unik — satu paket dengan dua item perujuk tak boleh '
            .'direkonsiliasi dua kali.');
        $this->assertGreaterThanOrEqual(2, count($paket));
    }

    public function test_item_nonaktif_tidak_ikut_fan_out(): void
    {
        $item = $this->itemBerVersi();
        if (! $item) {
            $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');
        }

        $kedua = $this->tautkanItemKeduaKeDokumen($item);
        if (! $kedua) {
            $this->markTestSkipped('Tak ada paket lain.');
        }

        DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $kedua)
            ->update(['Flag_Aktif' => SopStatus::INAKTIF]);

        $this->assertNotContains($kedua,
            app(SopDokumenFanoutService::class)->itemPerujuk((int) $item->Id_SOP_Dokumen),
            'Item nonaktif tak boleh ikut — fan-out ke item mati membuka ulang kewajiban hantu.');
    }

    public function test_hitung_dampak_melaporkan_item_paket_dan_penugasan(): void
    {
        $item = $this->itemBerVersi();
        if (! $item) {
            $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');
        }
        if (! $this->tautkanItemKeduaKeDokumen($item)) {
            $this->markTestSkipped('Tak ada paket lain.');
        }

        $d = app(SopDokumenFanoutService::class)->hitungDampak((int) $item->Id_SOP_Dokumen);

        $this->assertGreaterThanOrEqual(2, $d['item']);
        $this->assertGreaterThanOrEqual(2, $d['paket']);
        $this->assertGreaterThanOrEqual(0, $d['penugasan'],
            'Angka penugasan dipakai memberi tahu admin berapa karyawan terdampak '
            .'SEBELUM ia menekan rollback — sejak Fase 3 rollback membuka ulang kewajiban.');
    }

    // ---------------------------------------------------------------------
    // Fan-out pointer saat publish
    // ---------------------------------------------------------------------

    /** Versi terbaru milik dokumen item ini. */
    private function versiTerbaru(object $item): ?object
    {
        return DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', $item->Id_SOP_Dokumen)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderByDesc('Versi_Ke')
            ->first();
    }

    public function test_publish_menggeser_pointer_di_semua_item_perujuk(): void
    {
        $item = $this->itemBerVersi();
        if (! $item) {
            $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');
        }

        $versi = $this->versiTerbaru($item);
        if (! $versi) {
            $this->markTestSkipped('Dokumen sumber tak punya versi.');
        }

        $kedua = $this->tautkanItemKeduaKeDokumen($item);
        if (! $kedua) {
            $this->markTestSkipped('Tak ada paket lain.');
        }

        app(SopItemVersionService::class)->publish((int) $versi->Id_SOP_Item_Versi, 1);

        $this->assertSame((int) $versi->Id_SOP_Item_Versi,
            (int) DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $kedua)->value('Id_Item_Versi_Aktif'),
            'Item kedua yang merujuk dokumen yang sama wajib ikut menunjuk versi baru. Kalau '
            .'tidak, dua paket menyajikan revisi berbeda dari satu dokumen terkendali.');
    }

    public function test_pointer_hasil_fan_out_selalu_menunjuk_versi_published(): void
    {
        $item = $this->itemBerVersi();
        if (! $item) {
            $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');
        }

        $versi = $this->versiTerbaru($item);
        if (! $versi) {
            $this->markTestSkipped('Dokumen sumber tak punya versi.');
        }

        $kedua = $this->tautkanItemKeduaKeDokumen($item);
        if (! $kedua) {
            $this->markTestSkipped('Tak ada paket lain.');
        }

        app(SopItemVersionService::class)->publish((int) $versi->Id_SOP_Item_Versi, 1);

        foreach ([(int) $item->Id_SOP_Paket_Item, $kedua] as $idItem) {
            $pointer = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $idItem)->value('Id_Item_Versi_Aktif');
            $this->assertNotNull($pointer);
            $this->assertSame(SopStatus::PAKET_PUBLISHED,
                DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $pointer)->value('Status'),
                'Pointer versi aktif hanya boleh menunjuk versi PUBLISHED — pada SETIAP item '
                .'yang disentuh fan-out, bukan hanya item pemicu.');
        }
    }

    public function test_publish_membuat_versi_published_lain_pada_dokumen_jadi_obsolete(): void
    {
        $item = $this->itemBerVersi();
        if (! $item) {
            $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');
        }

        $semua = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', $item->Id_SOP_Dokumen)
            ->where('Flag_Aktif', SopStatus::AKTIF)->orderByDesc('Versi_Ke')->get();
        if ($semua->count() < 2) {
            $this->markTestSkipped('Dokumen sumber punya <2 versi.');
        }

        $target = $semua->first();
        app(SopItemVersionService::class)->publish((int) $target->Id_SOP_Item_Versi, 1);

        $masihPublished = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', $item->Id_SOP_Dokumen)
            ->where('Status', SopStatus::PAKET_PUBLISHED)
            ->where('Id_SOP_Item_Versi', '!=', $target->Id_SOP_Item_Versi)
            ->count();

        $this->assertSame(0, $masihPublished,
            'Satu dokumen terkendali hanya boleh punya SATU versi PUBLISHED. Dua versi '
            .'published sekaligus berarti dua paket bisa menunjuk revisi berbeda dan '
            .'keduanya sama-sama tampak sah.');
    }

    // ---------------------------------------------------------------------
    // Rollback ikut fan-out — keputusan produk user 2026-08-09
    // ---------------------------------------------------------------------

    public function test_rollback_menggeser_pointer_di_semua_item_perujuk(): void
    {
        $item = $this->itemBerVersi();
        if (! $item) {
            $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');
        }

        $versiLama = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', $item->Id_SOP_Dokumen)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('Versi_Ke')
            ->first();
        if (! $versiLama) {
            $this->markTestSkipped('Dokumen sumber tak punya versi.');
        }

        $kedua = $this->tautkanItemKeduaKeDokumen($item);
        if (! $kedua) {
            $this->markTestSkipped('Tak ada paket lain.');
        }

        app(SopItemVersionService::class)->rollback((int) $versiLama->Id_SOP_Item_Versi, 1);

        $this->assertSame((int) $versiLama->Id_SOP_Item_Versi,
            (int) DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $kedua)->value('Id_Item_Versi_Aktif'),
            'Keputusan produk 2026-08-09: rollback IKUT fan-out — semua paket perujuk mundur '
            .'bersama. Sebelumnya rollback sengaja senyap dan hanya menyentuh satu item.');
    }

    public function test_rollback_meninggalkan_jejak_yang_menyebut_cakupannya(): void
    {
        $item = $this->itemBerVersi();
        if (! $item) {
            $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');
        }

        $versiLama = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', $item->Id_SOP_Dokumen)
            ->where('Flag_Aktif', SopStatus::AKTIF)->orderBy('Versi_Ke')->first();
        if (! $versiLama) {
            $this->markTestSkipped('Dokumen sumber tak punya versi.');
        }

        $sebelum = DB::table('N_SOP_Log')->count();
        app(SopItemVersionService::class)->rollback((int) $versiLama->Id_SOP_Item_Versi, 1);

        $this->assertGreaterThan($sebelum, DB::table('N_SOP_Log')->count(),
            'Rollback kini membuka ulang kewajiban karyawan di banyak paket. Justru karena '
            .'dampaknya melebar, jejaknya wajib ada — dan jalur terantri tak berjalan bila '
            .'worker mati, sehingga admin butuh bekas untuk menelusurinya.');
    }

    // ---------------------------------------------------------------------
    // Endpoint pratinjau dampak
    // ---------------------------------------------------------------------

    private function adminUser(): ?User
    {
        return User::whereNotNull('Id_Users')->get()
            ->first(fn ($u) => $u->memilikiJabatan('menuSopBuilderPage'));
    }

    public function test_endpoint_dampak_melaporkan_cakupan_dan_ambang(): void
    {
        $admin = $this->adminUser();
        if (! $admin) {
            $this->markTestSkipped('Tak ada user dgn akses menuSopBuilderPage di data dev.');
        }

        $item = $this->itemBerVersi();
        if (! $item) {
            $this->markTestSkipped('Tidak ada item ber-versi di DB ini.');
        }

        $versi = $this->versiTerbaru($item);
        if (! $versi) {
            $this->markTestSkipped('Dokumen sumber tak punya versi.');
        }
        /* Diukur RELATIF terhadap keadaan yang ada, bukan dipatok ke angka 2.
           Satu dokumen boleh dipakai berapa pun item — itu inti Fase 6 — jadi
           angka mutlak apa pun akan meleset begitu data dev bertambah, dan
           kegagalannya menyamar jadi cacat endpoint padahal cuma data berubah.
           Yang diuji: item baru itu IKUT terhitung. */
        $sebelum = count(app(SopDokumenFanoutService::class)->itemPerujuk((int) $item->Id_SOP_Dokumen));
        if (! $this->tautkanItemKeduaKeDokumen($item)) {
            $this->markTestSkipped('Tak ada paket lain.');
        }

        $id = Hashids::connection('custom')->encode($versi->Id_SOP_Item_Versi);

        $this->actingAs($admin)
            ->getJson("/api/v1/sop/admin/builder/versi/{$id}/dampak")
            ->assertOk()
            // ResponseHelper::success() membungkus payload di kunci `result`.
            ->assertJsonPath('result.item', $sebelum + 1)
            ->assertJsonStructure(['result' => ['item', 'paket', 'penugasan', 'ambang', 'akan_antri']]);
    }

    public function test_endpoint_dampak_menolak_token_draft_overlay(): void
    {
        $admin = $this->adminUser();
        if (! $admin) {
            $this->markTestSkipped('Tak ada user dgn akses menuSopBuilderPage di data dev.');
        }

        $draft = DB::table('N_SOP_Paket_Draft_Item_Versi')->first();
        if (! $draft) {
            $this->markTestSkipped('Tak ada baris versi draft di data dev.');
        }

        $token = 'dv_'.Hashids::connection('custom')->encode($draft->Id_SOP_Draft_Item_Versi);

        $this->actingAs($admin)
            ->getJson("/api/v1/sop/admin/builder/versi/{$token}/dampak")
            ->assertStatus(422);
    }
}
