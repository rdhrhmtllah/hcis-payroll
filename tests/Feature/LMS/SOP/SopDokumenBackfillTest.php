<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Backfill 1:1 Fase 1 — tiap item ber-versi jadi SATU dokumen sendiri.
 * Sengaja tidak menggabungkan duplikat: nilai pengesahan untuk konten yang sama
 * sudah menyimpang di data nyata dan hanya manusia yang bisa memutuskan mana
 * yang benar (penggabungan = Fase 7).
 */
class SopDokumenBackfillTest extends TestCase
{
    use DatabaseTransactions;

    private function itemBerVersi()
    {
        return DB::table('N_SOP_Paket_Item as i')
            ->whereIn('i.Tipe_Item', SopTipeItem::VERSIONED)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('N_SOP_Item_Versi as v')
                ->whereColumn('v.Id_SOP_Paket_Item', 'i.Id_SOP_Paket_Item'));
    }

    /**
     * Kembalikan sebagian data ke keadaan PRA-backfill supaya command punya
     * pekerjaan sungguhan.
     *
     * KENAPA WAJIB ADA. Setelah backfill sungguhan dijalankan di dev (Task 5),
     * backlog-nya habis; handle() lalu mengambil jalur early-return dan tak
     * menulis apa pun. Tes yang cuma memanggil command lalu memeriksa keadaan
     * tabel sesudahnya jadi memeriksa DATA WARISAN, bukan perilaku command —
     * test_idempoten_… bahkan tak bisa lagi gagal untuk sebab apa pun. Dengan
     * backlog buatan ini, tiap tes kembali benar-benar menjalankan lintasan
     * tulis. Semuanya di-rollback DatabaseTransactions.
     *
     * @return array<int,int> Id_SOP_Paket_Item yang dilepas tautannya
     */
    private function buatBacklog(int $berapa = 2): array
    {
        $sasaran = $this->itemBerVersi()
            ->whereNotNull('i.Id_SOP_Dokumen')
            ->orderBy('i.Id_SOP_Paket_Item')
            ->limit($berapa)
            ->get(['i.Id_SOP_Paket_Item', 'i.Id_SOP_Dokumen']);

        if ($sasaran->isEmpty()) {
            return [];
        }

        $idItem = $sasaran->pluck('Id_SOP_Paket_Item')->map(fn ($v) => (int) $v)->all();
        $idDokumen = $sasaran->pluck('Id_SOP_Dokumen')->map(fn ($v) => (int) $v)->all();

        // Urutan penting: lepaskan dulu semua rujukan, baru hapus dokumennya —
        // kedua kolom rujukan punya FOREIGN KEY ke N_SOP_Dokumen (26A).
        //
        // DILEPAS LEWAT DUA JALUR, dan itu perlu. Melepas hanya lewat
        // Id_SOP_Paket_Item mengasumsikan setiap versi sebuah dokumen bisa
        // dicapai dari itemnya — asumsi yang berhenti berlaku sejak DDL 28
        // membuat kolom itu nullable dan versi bisa dimiliki DOKUMEN tanpa
        // item sama sekali (dibuat lewat Master SOP). Versi semacam itu
        // terlewat, lalu DELETE di bawah gagal dengan galat kunci asing yang
        // tak menyebut sebabnya.
        DB::table('N_SOP_Item_Versi')->whereIn('Id_SOP_Paket_Item', $idItem)->update(['Id_SOP_Dokumen' => null]);
        DB::table('N_SOP_Item_Versi')->whereIn('Id_SOP_Dokumen', $idDokumen)->update(['Id_SOP_Dokumen' => null]);

        /* SEMUA item yang menunjuk dokumen itu dilepas, bukan cuma yang terpilih.
           Satu Dokumen SOP memang boleh dipakai banyak paket — itu inti Fase 6 —
           jadi dokumen yang dipilih di atas hampir pasti punya pemakai lain, dan
           DELETE di bawah ditolak FK_N_SOP_Paket_Item_Dokumen dengan galat yang
           tak menyebut sebabnya. Yang dikembalikan tetap hanya $idItem: itu yang
           dijadikan sasaran pemeriksaan tiap tes. Semuanya di-rollback. */
        $idItem = array_values(array_unique(array_merge($idItem,
            DB::table('N_SOP_Paket_Item')->whereIn('Id_SOP_Dokumen', $idDokumen)
                ->pluck('Id_SOP_Paket_Item')->map(fn ($v) => (int) $v)->all())));

        DB::table('N_SOP_Paket_Item')->whereIn('Id_SOP_Paket_Item', $idItem)->update(['Id_SOP_Dokumen' => null]);
        DB::table('N_SOP_Dokumen')->whereIn('Id_SOP_Dokumen', $idDokumen)->delete();

        return $idItem;
    }

    /** Baris versi yang itemnya bertaut dokumen tapi versinya tidak — keadaan yang lahir dari alur revisi normal. */
    private function versiLepasCount(): int
    {
        return (int) DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'v.Id_SOP_Paket_Item')
            ->whereNotNull('i.Id_SOP_Dokumen')
            ->whereNull('v.Id_SOP_Dokumen')
            ->count();
    }

    /**
     * Tiru apa yang SopItemVersionService::create() lakukan: baris versi baru
     * pada item yang SUDAH bertaut dokumen, tapi tanpa Id_SOP_Dokumen sendiri.
     *
     * @return int|null Id_SOP_Item_Versi yang dilepas, atau null bila data tak cukup
     */
    private function lepasSatuBarisVersi(): ?int
    {
        $versi = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'v.Id_SOP_Paket_Item')
            ->whereNotNull('v.Id_SOP_Dokumen')
            ->whereNotNull('i.Id_SOP_Dokumen')
            ->orderBy('v.Id_SOP_Item_Versi')
            ->first(['v.Id_SOP_Item_Versi']);

        if (! $versi) {
            return null;
        }

        DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Item_Versi', $versi->Id_SOP_Item_Versi)
            ->update(['Id_SOP_Dokumen' => null]);

        return (int) $versi->Id_SOP_Item_Versi;
    }

    public function test_dry_run_tidak_menulis_apa_pun(): void
    {
        // Membandingkan SEBELUM vs SESUDAH, bukan menuntut nol: data dev sudah
        // ter-backfill, jadi assertion "nol dokumen" akan selalu gagal. Yang
        // ingin dibuktikan cuma satu — dry-run tak mengubah apa pun.
        if (! $this->buatBacklog()) {
            $this->markTestSkipped('Tak ada item ber-versi ber-dokumen untuk dijadikan backlog.');
        }

        // Sengaja juga melepas satu baris versi pada item yang TETAP bertaut,
        // supaya lintasan penyembuhan ikut punya pekerjaan — dry-run harus
        // menahan diri pada KEDUA lintasan, bukan cuma yang pertama.
        $versi = $this->lepasSatuBarisVersi();

        $dokumenSebelum = DB::table('N_SOP_Dokumen')->count();
        $tertautSebelum = (int) $this->itemBerVersi()->whereNotNull('i.Id_SOP_Dokumen')->count();
        $lepasSebelum = $this->versiLepasCount();

        $this->artisan('sop:dokumen-backfill --dry-run')->assertSuccessful();

        $this->assertSame($dokumenSebelum, DB::table('N_SOP_Dokumen')->count(),
            'dry-run tidak boleh membuat dokumen.');
        $this->assertSame($tertautSebelum, (int) $this->itemBerVersi()->whereNotNull('i.Id_SOP_Dokumen')->count(),
            'dry-run tidak boleh menautkan item ke dokumen.');
        $this->assertSame($lepasSebelum, $this->versiLepasCount(),
            'dry-run tidak boleh menyembuhkan baris versi lepas.');
        if ($versi !== null) {
            $this->assertNull(DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $versi)->value('Id_SOP_Dokumen'),
                'dry-run menulis Id_SOP_Dokumen pada baris versi.');
        }
    }

    public function test_setiap_item_berversi_dapat_tepat_satu_dokumen(): void
    {
        $harusnya = (int) $this->itemBerVersi()->count();
        if ($harusnya === 0) {
            $this->markTestSkipped('Tak ada item ber-versi di data dev.');
        }
        $this->buatBacklog();

        $this->artisan('sop:dokumen-backfill')->assertSuccessful();

        $this->assertSame(0, (int) $this->itemBerVersi()->whereNull('i.Id_SOP_Dokumen')->count(),
            'Masih ada item ber-versi tanpa dokumen.');

        // 1:1 — tak ada dokumen yang dipakai dua item pada tahap ini.
        $ganda = DB::table('N_SOP_Paket_Item')->whereNotNull('Id_SOP_Dokumen')
            ->select('Id_SOP_Dokumen')->groupBy('Id_SOP_Dokumen')
            ->havingRaw('COUNT(*) > 1')->get();
        $this->assertCount(0, $ganda, 'Fase 1 wajib 1:1 — penggabungan adalah pekerjaan Fase 7.');
    }

    public function test_setiap_baris_versi_ikut_dokumen_itemnya(): void
    {
        if ((int) $this->itemBerVersi()->count() === 0) {
            $this->markTestSkipped('Tak ada item ber-versi di data dev.');
        }
        $this->buatBacklog();

        $this->artisan('sop:dokumen-backfill')->assertSuccessful();

        $nyasar = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'v.Id_SOP_Paket_Item')
            ->whereNotNull('i.Id_SOP_Dokumen')
            ->where(fn ($q) => $q->whereNull('v.Id_SOP_Dokumen')->orWhereColumn('v.Id_SOP_Dokumen', '<>', 'i.Id_SOP_Dokumen'))
            ->count();

        $this->assertSame(0, $nyasar, 'Ada baris versi yang tak ikut dokumen itemnya.');
    }

    /**
     * KONVERGEN, bukan cuma idempoten.
     *
     * Alur revisi normal — SopItemVersionService::create(), dipanggil
     * SopBerkasController::storeVersi & SopAdminBuilderController — menulis
     * baris versi TANPA Id_SOP_Dokumen. Begitu admin menambah v2 pada item yang
     * sudah di-backfill, lahirlah keadaan ini: item bertaut, versinya tidak.
     * Lintasan 1 tak bisa menyentuhnya (ia menyaring item yang BELUM bertaut),
     * jadi tanpa lintasan penyembuhan gerbang sop:dokumen-verify merah permanen
     * dan cuma bisa dibersihkan lewat SQL manual.
     */
    public function test_menyembuhkan_baris_versi_yang_lahir_tanpa_dokumen(): void
    {
        $versi = $this->lepasSatuBarisVersi();
        if ($versi === null) {
            $this->markTestSkipped('Tak ada baris versi ber-dokumen di data dev.');
        }

        $this->assertSame(1, $this->versiLepasCount(), 'Prasyarat: tepat satu baris versi lepas.');

        $this->artisan('sop:dokumen-backfill')->assertSuccessful();

        $sesudah = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'v.Id_SOP_Paket_Item')
            ->where('v.Id_SOP_Item_Versi', $versi)
            ->first(['v.Id_SOP_Dokumen as versi_dok', 'i.Id_SOP_Dokumen as item_dok']);

        $this->assertNotNull($sesudah->versi_dok, 'Baris versi lepas tidak disembuhkan backfill.');
        $this->assertEquals($sesudah->item_dok, $sesudah->versi_dok,
            'Baris versi disembuhkan ke dokumen yang salah — harus mengikuti dokumen itemnya.');
        $this->assertSame(0, $this->versiLepasCount(), 'Masih ada baris versi lepas setelah backfill.');
    }

    public function test_pointer_versi_aktif_dokumen_menyalin_pointer_item(): void
    {
        if ((int) $this->itemBerVersi()->whereNotNull('i.Id_Item_Versi_Aktif')->count() === 0) {
            $this->markTestSkipped('Tak ada item ber-versi aktif di data dev.');
        }
        $this->buatBacklog();

        $this->artisan('sop:dokumen-backfill')->assertSuccessful();

        $beda = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'i.Id_SOP_Dokumen')
            ->whereNotNull('i.Id_Item_Versi_Aktif')
            ->whereColumn('d.Id_Versi_Aktif', '<>', 'i.Id_Item_Versi_Aktif')
            ->count();

        $this->assertSame(0, $beda, 'Pointer versi aktif dokumen tak sama dengan pointer item asalnya.');
    }

    public function test_identitas_dokumen_diambil_dari_itemnya(): void
    {
        // Item dipilih dari backlog buatan, dan HARUS yang berjudul: baris
        // berjudul kosong adalah satu-satunya yang menempuh fallback
        // 'Dokumen tanpa judul' (kolom Judul NOT NULL di 26A), sehingga
        // memasukkannya ke assertion identitas berarti menuntut command
        // melanggar constraint-nya sendiri. Sisi fallback diuji terpisah oleh
        // test_dokumen_selalu_punya_judul.
        $idItem = $this->buatBacklog(5);
        if (! $idItem) {
            $this->markTestSkipped('Tak ada item ber-versi ber-dokumen untuk dijadikan backlog.');
        }

        $item = DB::table('N_SOP_Paket_Item as i')
            ->whereIn('i.Id_SOP_Paket_Item', $idItem)
            ->whereNotNull('i.Judul_Item')->where('i.Judul_Item', '<>', '')
            ->orderBy('i.Id_SOP_Paket_Item')
            ->first(['i.Id_SOP_Paket_Item', 'i.Judul_Item', 'i.Nomor_SOP_Resmi', 'i.Tipe_Item']);
        if (! $item) {
            $this->markTestSkipped('Tak ada item ber-versi berjudul di data dev.');
        }

        $this->artisan('sop:dokumen-backfill')->assertSuccessful();

        $dok = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'i.Id_SOP_Dokumen')
            ->where('i.Id_SOP_Paket_Item', $item->Id_SOP_Paket_Item)
            ->first(['d.Judul', 'd.Nomor_SOP_Resmi', 'd.Tipe', 'd.Flag_Aktif']);

        $this->assertSame($item->Judul_Item, $dok->Judul);
        $this->assertSame($item->Nomor_SOP_Resmi, $dok->Nomor_SOP_Resmi);
        $this->assertSame($item->Tipe_Item, $dok->Tipe);
        $this->assertSame(SopStatus::AKTIF, $dok->Flag_Aktif);
    }

    /** N_SOP_Dokumen.Judul NOT NULL — item tak berjudul pun harus menghasilkan dokumen sah. */
    public function test_dokumen_selalu_punya_judul(): void
    {
        if (! $this->buatBacklog(5)) {
            $this->markTestSkipped('Tak ada item ber-versi ber-dokumen untuk dijadikan backlog.');
        }

        $this->artisan('sop:dokumen-backfill')->assertSuccessful();

        $kosong = DB::table('N_SOP_Dokumen')
            ->where(fn ($q) => $q->whereNull('Judul')->orWhere('Judul', ''))
            ->count();

        $this->assertSame(0, $kosong, 'Ada dokumen tanpa judul — kolomnya NOT NULL.');
    }

    public function test_tipe_tanpa_versi_tidak_diberi_dokumen(): void
    {
        if ((int) $this->itemBerVersi()->count() === 0) {
            $this->markTestSkipped('Tak ada item ber-versi di data dev.');
        }
        $this->buatBacklog();

        $this->artisan('sop:dokumen-backfill')->assertSuccessful();

        $salah = DB::table('N_SOP_Paket_Item')
            ->whereNotIn('Tipe_Item', SopTipeItem::VERSIONED)
            ->whereNotNull('Id_SOP_Dokumen')->count();

        $this->assertSame(0, $salah, 'PARAGRAF/TAUTAN/ABSENSI tak boleh punya dokumen.');
    }

    public function test_idempoten_dijalankan_dua_kali_tidak_menggandakan(): void
    {
        if (! $this->buatBacklog()) {
            $this->markTestSkipped('Tak ada item ber-versi ber-dokumen untuk dijadikan backlog.');
        }

        $this->artisan('sop:dokumen-backfill')->assertSuccessful();
        $setelahSekali = DB::table('N_SOP_Dokumen')->count();

        $this->artisan('sop:dokumen-backfill')->assertSuccessful();

        $this->assertSame($setelahSekali, DB::table('N_SOP_Dokumen')->count(),
            'Backfill kedua membuat dokumen baru — tidak idempoten.');
    }

    /**
     * `--paket=abc` dulu di-cast jadi 0, `when(0)` falsy, filternya lenyap, dan
     * command menyapu SELURUH paket — kebalikan dari yang diminta operator.
     */
    public function test_paket_bukan_angka_ditolak_dan_tidak_menulis(): void
    {
        if (! $this->buatBacklog()) {
            $this->markTestSkipped('Tak ada item ber-versi ber-dokumen untuk dijadikan backlog.');
        }

        $sebelum = DB::table('N_SOP_Dokumen')->count();

        $this->artisan('sop:dokumen-backfill --paket=abc')->assertExitCode(1);
        $this->artisan('sop:dokumen-backfill --paket=0')->assertExitCode(1);

        $this->assertSame($sebelum, DB::table('N_SOP_Dokumen')->count(),
            '--paket tak valid harus berhenti, bukan menyapu semua paket.');
    }
}
