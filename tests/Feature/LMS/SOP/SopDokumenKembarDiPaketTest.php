<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Satu dokumen hanya boleh muncul SEKALI dalam satu paket.
 *
 * Bukan soal kerapian — angka kepatuhannya yang rusak. Rollup berkunci
 * (Id_SOP_Paket, Id_SOP_Item_Versi) TANPA Id_SOP_Paket_Item, jadi dua item yang
 * menunjuk versi yang sama di satu paket berbagi SATU baris angka: konfirmasi
 * baca keduanya menambah penghitung yang sama, Jumlah_Dikonfirmasi melampaui
 * Total_Target, dan persennya melewati 100.
 *
 * Keadaan itu sudah pernah ditandai di SopPaketDraftService sebagai "pertanyaan
 * yang belum terjawab", dan pada 15 Agu 2026 ia benar-benar muncul di dev:
 * paket 924 punya dua item menunjuk dokumen 5969 setelah admin mengganti
 * dokumen item pertama dengan dokumen milik item kedua.
 *
 * Dijaga di DUA tempat, dan keduanya perlu: saat item ditautkan (menghentikan
 * yang baru) dan di gerbang publish (menahan yang sudah telanjur kembar sebelum
 * ada satu pun baris rollup yang salah).
 */
class SopDokumenKembarDiPaketTest extends TestCase
{
    use DatabaseTransactions;

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

    private function enc($id): string
    {
        return Hashids::connection('custom')->encode((int) $id);
    }

    /** Paket DRAFT + section + satu item ber-dokumen yang sudah ada di dalamnya. */
    private function paketBerdokumen(): object
    {
        $row = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->join('N_SOP_Paket_Section as s', 's.Id_SOP_Paket', '=', 'p.Id_SOP_Paket')
            ->join('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'i.Id_SOP_Dokumen')
            ->whereExists(fn ($q) => $q->from('N_SOP_Item_Versi as v')
                ->whereColumn('v.Id_SOP_Dokumen', 'i.Id_SOP_Dokumen')
                ->where('v.Status', SopStatus::PAKET_PUBLISHED))
            ->where('p.Status', SopStatus::PAKET_DRAFT)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('s.Flag_Aktif', SopStatus::AKTIF)
            ->first(['p.Id_SOP_Paket', 's.Id_SOP_Section', 'i.Id_SOP_Paket_Item',
                'i.Id_SOP_Dokumen', 'i.Tipe_Item', 'd.Judul']);

        if (! $row) {
            $this->markTestSkipped('Tak ada paket draft berisi item ber-dokumen terbit.');
        }

        return $row;
    }

    /* ── pintu 1: saat item ditautkan ──────────────────────────────── */

    public function test_menambah_item_dengan_dokumen_yang_sudah_ada_di_paket_ditolak(): void
    {
        // APP_URL repo ini tanpa skema; tanpa ini permintaan uji meleset dari
        // route-nya dan berbalas 404. Lihat SopFormBerkasTest.
        URL::forceRootUrl('http://localhost');

        $s = $this->paketBerdokumen();
        $sebelum = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket', $s->Id_SOP_Paket)->count();

        $res = $this->actingAs($this->adminBuilder())
            ->postJson("/api/v1/sop/admin/builder/paket/{$this->enc($s->Id_SOP_Paket)}/item/store", [
                'Tipe_Item' => $s->Tipe_Item,
                'Id_SOP_Section' => $this->enc($s->Id_SOP_Section),
                'Id_SOP_Dokumen' => $this->enc($s->Id_SOP_Dokumen),
            ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('sudah dipakai item', strtolower((string) $res->json('message')));

        $this->assertSame($sebelum, DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket', $s->Id_SOP_Paket)->count(),
            'Itemnya telanjur dibuat padahal dokumennya sudah dipakai di paket yang sama.');
    }

    /**
     * Dokumen yang sama di paket BERBEDA tetap boleh — itu justru inti Fase 6.
     *
     * Satu SOP dipakai banyak paket, dan revisinya menyebar ke semuanya. Penjaga
     * yang ikut melarang itu akan mematahkan seluruh alasan dokumen dipisahkan
     * dari paket.
     */
    public function test_dokumen_yang_sama_di_paket_lain_tetap_boleh(): void
    {
        URL::forceRootUrl('http://localhost');

        $s = $this->paketBerdokumen();

        $lain = DB::table('N_SOP_Master_Paket as p')
            ->join('N_SOP_Paket_Section as sec', 'sec.Id_SOP_Paket', '=', 'p.Id_SOP_Paket')
            ->where('p.Status', SopStatus::PAKET_DRAFT)
            ->where('p.Flag_Aktif', SopStatus::AKTIF)
            ->where('sec.Flag_Aktif', SopStatus::AKTIF)
            ->where('p.Id_SOP_Paket', '<>', $s->Id_SOP_Paket)
            ->whereNotExists(fn ($q) => $q->from('N_SOP_Paket_Item as i')
                ->whereColumn('i.Id_SOP_Paket', 'p.Id_SOP_Paket')
                ->where('i.Id_SOP_Dokumen', $s->Id_SOP_Dokumen)
                ->where('i.Flag_Aktif', SopStatus::AKTIF))
            ->first(['p.Id_SOP_Paket', 'sec.Id_SOP_Section']);

        if (! $lain) {
            $this->markTestSkipped('Tak ada paket draft lain yang belum memakai dokumen itu.');
        }

        $res = $this->actingAs($this->adminBuilder())
            ->postJson("/api/v1/sop/admin/builder/paket/{$this->enc($lain->Id_SOP_Paket)}/item/store", [
                'Tipe_Item' => $s->Tipe_Item,
                'Id_SOP_Section' => $this->enc($lain->Id_SOP_Section),
                'Id_SOP_Dokumen' => $this->enc($s->Id_SOP_Dokumen),
            ]);

        $res->assertStatus(201);
    }

    /* ── pintu 2: gerbang publish ──────────────────────────────────── */

    public function test_publish_ditahan_bila_paket_memuat_sop_kembar(): void
    {
        URL::forceRootUrl('http://localhost');

        $s = $this->paketBerdokumen();

        /* Kembarannya DIBUAT DI SINI, di dalam transaksi uji: penjaga pintu
           pertama sudah menutup jalur normalnya, sedangkan yang harus diuji di
           sini justru keadaan yang telanjur ada sebelum penjaga itu dipasang. */
        DB::table('N_SOP_Paket_Item')->insert([
            'Id_SOP_Paket' => $s->Id_SOP_Paket,
            'Id_SOP_Section' => $s->Id_SOP_Section,
            'Id_SOP_Dokumen' => $s->Id_SOP_Dokumen,
            'Tipe_Item' => $s->Tipe_Item,
            'Judul_Item' => $s->Judul,
            'Urutan' => 999,
            'Flag_Wajib' => 'Y',
            'Flag_Highlight' => 'T',
            'Flag_Aktif' => SopStatus::AKTIF,
            'Created_By' => 1,
            'Created_At' => now(),
        ]);

        $res = $this->actingAs($this->adminBuilder())
            ->patchJson("/api/v1/sop/admin/builder/paket/status/{$this->enc($s->Id_SOP_Paket)}", [
                'status' => SopStatus::PAKET_PUBLISHED,
                'Ringkasan_Perubahan' => 'Uji penjaga dokumen kembar',
            ]);

        $res->assertStatus(422);

        $pesan = (string) $res->json('message');
        $this->assertStringContainsString('lebih dari sekali', strtolower($pesan));
        $this->assertStringContainsString($s->Judul, $pesan,
            'Pesannya tak menyebut dokumen mana yang kembar — admin harus memeriksa seluruh item satu per satu.');

        // Dan paketnya benar-benar tak jadi terbit.
        $this->assertSame(SopStatus::PAKET_DRAFT,
            DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $s->Id_SOP_Paket)->value('Status'));
    }

    /**
     * Pratinjau perubahan tak boleh runtuh saat ada versi item DRAFT/READY.
     *
     * Bukan bagian dari aturan dokumen kembar, tapi ditemukan olehnya: uji di
     * atas sempat berbalas 500, bukan 422, karena
     * SopPaketVersionService::itemVersionDetails() memakai Hashids TANPA
     * impornya — sehingga diselesaikan sebagai App\Services\LMS\SOP\Hashids, kelas
     * yang tak ada.
     *
     * Cacatnya diam sepanjang isi paket sudah terbit semua: cabang itu hanya
     * tersentuh ketika ada versi berstatus DRAFT atau READY — persis keadaan
     * rilis pertama sebuah paket, yang justru paling sering dipakai.
     */
    public function test_pratinjau_perubahan_tidak_runtuh_pada_versi_draft(): void
    {
        $idPaket = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Dokumen', '=', 'v.Id_SOP_Dokumen')
            ->whereIn('v.Status', [SopStatus::VERSI_DRAFT, SopStatus::VERSI_READY])
            ->where('v.Flag_Aktif', SopStatus::AKTIF)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->value('i.Id_SOP_Paket');

        if (! $idPaket) {
            $this->markTestSkipped('Tak ada paket dengan versi item draft/ready.');
        }

        $hasil = app(\App\Services\LMS\SOP\SopPaketVersionService::class)->pendingChanges((int) $idPaket);

        $this->assertIsArray($hasil);
        $this->assertArrayHasKey('summary', $hasil);
        // Baris versi itulah yang dulu meruntuhkannya.
        $this->assertIsArray($hasil['details']['versions'] ?? null,
            'Rincian versi tak terbentuk — pratinjau publish akan berbalas 500, bukan daftar perubahan.');
    }
}
