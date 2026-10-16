<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopDocumentControlService;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 5 Master Dokumen SOP — register kontrol dokumen bersumbu DOKUMEN.
 *
 * KENAPA INI PERLU. register() mengembalikan satu baris per ITEM. Hari ini
 * 1 dokumen = 1 item sehingga tampak setara, tapi begitu Fase 6 menyalakan
 * berbagi, satu dokumen yang dipakai 5 paket muncul 5 KALI — duplikasi yang
 * sama persis dengan penyakit yang diobati proyek ini, sekarang justru di
 * layar yang mengaku mengendalikannya.
 */
class SopMasterDokumenTest extends TestCase
{
    use DatabaseTransactions;

    private function itemBerDokumen(): ?object
    {
        return DB::table('N_SOP_Paket_Item')
            ->whereIn('Tipe_Item', SopTipeItem::VERSIONED)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('Id_SOP_Dokumen')
            ->whereNotNull('Id_Item_Versi_Aktif')
            ->first();
    }

    /** Item kedua di paket lain, berbagi dokumen yang sama. */
    private function itemKeduaBerbagi(object $item, int $idPaketLain): int
    {
        return (int) DB::table('N_SOP_Paket_Item')->insertGetId([
            'Id_SOP_Paket' => $idPaketLain,
            'Tipe_Item' => $item->Tipe_Item,
            'Judul_Item' => 'PEMAKAI KEDUA',
            'Id_SOP_Dokumen' => $item->Id_SOP_Dokumen,
            'Id_Item_Versi_Aktif' => $item->Id_Item_Versi_Aktif,
            'Urutan' => 9999,
            'Flag_Wajib' => $item->Flag_Wajib,
            'Flag_Highlight' => $item->Flag_Highlight,
            'Created_By' => 1,
            'Created_At' => now(),
            'Flag_Aktif' => SopStatus::AKTIF,
        ], 'Id_SOP_Paket_Item');
    }

    public function test_baris_register_membawa_id_dokumen(): void
    {
        [$rows] = app(SopDocumentControlService::class)->register([], 1, 50);
        if (! count($rows)) {
            $this->markTestSkipped('Register kosong di data dev.');
        }

        $this->assertTrue(property_exists($rows[0], 'Id_SOP_Dokumen'),
            'Tanpa Id_SOP_Dokumen, layar tak bisa mengelompokkan per dokumen — dan begitu '
            .'Fase 6 menyalakan berbagi, satu dokumen muncul sekali per paket, kembali ke '
            .'duplikasi yang justru diobati proyek ini.');

        $ber = collect($rows)->firstWhere(fn ($r) => $r->Id_SOP_Dokumen !== null);
        if ($ber) {
            $this->assertFalse(ctype_digit((string) $ber->Id_SOP_Dokumen),
                'Id_SOP_Dokumen wajib ter-encode Hashid seperti id lain di register. Id DB '
                .'mentah bocor ke klien, dan endpoint detail yang men-decode Hashid tak akan '
                .'bisa memuatnya.');

            $this->assertNotEmpty(
                \Vinkla\Hashids\Facades\Hashids::connection('custom')->decode($ber->Id_SOP_Dokumen),
                'Id_SOP_Dokumen harus bisa di-decode kembali — kalau tidak, drawer detail buntu.');
        }
    }

    public function test_paket_pemakai_mencakup_semua_item_perujuk(): void
    {
        $item = $this->itemBerDokumen();
        if (! $item) {
            $this->markTestSkipped('Tak ada item ber-dokumen di data dev.');
        }

        $paketLain = DB::table('N_SOP_Master_Paket')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Id_SOP_Paket', '!=', $item->Id_SOP_Paket)
            ->value('Id_SOP_Paket');
        if (! $paketLain) {
            $this->markTestSkipped('Tak ada paket lain.');
        }

        $this->itemKeduaBerbagi($item, (int) $paketLain);

        $ids = collect(app(SopDocumentControlService::class)->paketPemakai((int) $item->Id_SOP_Dokumen))
            ->pluck('Id_SOP_Paket')->map(fn ($v) => (int) $v)->all();

        $this->assertContains((int) $item->Id_SOP_Paket, $ids);
        $this->assertContains((int) $paketLain, $ids,
            'Pertanyaan yang harus dijawab halaman ini adalah "kalau saya merevisi dokumen '
            .'ini, siapa saja yang terdampak?" — paket kedua wajib ikut terdaftar.');
        $this->assertSame(count(array_unique($ids)), count($ids),
            'Daftar paket wajib unik; satu paket dengan dua item perujuk tak boleh muncul dua kali.');
    }

    /**
     * paketPemakai() SENGAJA tidak menyaring PUBLISHED, berbeda dari register().
     * Paket DRAFT akan terbit membawa dokumen ini, jadi ia bagian dari jawaban
     * "siapa terdampak" — dan statusnya ditampilkan sebagai lencana, bukan
     * disembunyikan dengan cara membuangnya dari daftar.
     */
    public function test_paket_pemakai_memuat_paket_draft_beserta_statusnya(): void
    {
        $item = $this->itemBerDokumen();
        if (! $item) {
            $this->markTestSkipped('Tak ada item ber-dokumen di data dev.');
        }

        $draft = DB::table('N_SOP_Master_Paket')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Status', SopStatus::PAKET_DRAFT)
            ->where('Id_SOP_Paket', '!=', $item->Id_SOP_Paket)
            ->value('Id_SOP_Paket');
        if (! $draft) {
            $this->markTestSkipped('Tak ada paket DRAFT lain.');
        }

        $this->itemKeduaBerbagi($item, (int) $draft);

        $baris = collect(app(SopDocumentControlService::class)->paketPemakai((int) $item->Id_SOP_Dokumen))
            ->firstWhere('Id_SOP_Paket', $draft);

        $this->assertNotNull($baris, 'Paket DRAFT wajib ikut — ia akan terbit membawa dokumen ini.');
        $this->assertSame(SopStatus::PAKET_DRAFT, $baris->Status,
            'Statusnya wajib ikut supaya UI bisa membedakannya, bukan menyamakannya dengan terbit.');
    }

    public function test_riwayat_versi_terbaru_dulu(): void
    {
        $dokumen = DB::table('N_SOP_Item_Versi')
            ->whereNotNull('Id_SOP_Dokumen')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->groupBy('Id_SOP_Dokumen')
            ->havingRaw('COUNT(*) > 1')
            ->value('Id_SOP_Dokumen');
        if (! $dokumen) {
            $this->markTestSkipped('Tak ada dokumen dengan >1 versi di data dev.');
        }

        $versi = collect(app(SopDocumentControlService::class)->riwayatVersi((int) $dokumen))
            ->pluck('Versi_Ke')->map(fn ($v) => (int) $v)->all();

        $urut = $versi;
        rsort($urut);
        $this->assertSame($urut, $versi, 'Riwayat versi wajib terbaru dulu.');
    }

    // ---------------------------------------------------------------------
    // Endpoint detail dokumen
    // ---------------------------------------------------------------------

    private function adminMonitoring(): ?\App\Models\ESS\Employee\Models\User
    {
        return \App\Models\ESS\Employee\Models\User::whereNotNull('Id_Users')->get()
            ->first(fn ($u) => $u->memilikiJabatan('menuSopMonitoringPage')
                            || $u->memilikiJabatan('menuSopBuilderPage'));
    }

    public function test_endpoint_detail_dokumen_mengembalikan_paket_dan_riwayat(): void
    {
        $admin = $this->adminMonitoring();
        if (! $admin) {
            $this->markTestSkipped('Tak ada user dgn akses monitoring/builder SOP.');
        }

        $item = $this->itemBerDokumen();
        if (! $item) {
            $this->markTestSkipped('Tak ada item ber-dokumen di data dev.');
        }

        $id = \Vinkla\Hashids\Facades\Hashids::connection('custom')->encode($item->Id_SOP_Dokumen);

        $this->actingAs($admin)
            ->getJson("/api/v1/sop/admin/monitoring/dokumen/{$id}/detail")
            ->assertOk()
            // ResponseHelper::success() membungkus payload di kunci `result`.
            ->assertJsonStructure(['result' => ['dokumen', 'paket_pemakai', 'riwayat_versi']])
            /* sqlsrv mengembalikan kolom numerik sebagai STRING, jadi payload
               memuat "1652" bukan 1652. Dicatat di sini karena sisi Vue
               mengelompokkan baris register berdasarkan Id_SOP_Dokumen:
               selama kedua sisi sama-sama string, perbandingan === aman —
               tapi mencampur int dan string akan memecah satu dokumen jadi
               dua kelompok tanpa gejala yang jelas. */
            ->assertJsonPath('result.dokumen.Id_SOP_Dokumen', (string) $item->Id_SOP_Dokumen);
    }

    public function test_endpoint_detail_dokumen_menolak_id_asing(): void
    {
        $admin = $this->adminMonitoring();
        if (! $admin) {
            $this->markTestSkipped('Tak ada user dgn akses monitoring/builder SOP.');
        }

        $asing = \Vinkla\Hashids\Facades\Hashids::connection('custom')->encode(999999999);

        $this->actingAs($admin)
            ->getJson("/api/v1/sop/admin/monitoring/dokumen/{$asing}/detail")
            ->assertStatus(404);
    }
}
