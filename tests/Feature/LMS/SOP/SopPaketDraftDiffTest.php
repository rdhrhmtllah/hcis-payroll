<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopPaketDraftService;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DRAFT YANG BARU DIBUKA HARUS NOL PERUBAHAN.
 *
 * Builder membuatkan working copy begitu paket terbit dibuka. Kalau
 * perbandingannya melaporkan perubahan pada draft yang belum disentuh siapa
 * pun, admin melihat "ada revisi tertunda" selamanya — tak bisa dibereskan,
 * karena tak ada yang bisa dikembalikan, dan tak bisa diabaikan, karena
 * angkanya juga yang memutuskan tombol Terbitkan menyala.
 *
 * Cacat yang ditutup berkas ini: kolom yang boleh NULL di `N_SOP_Paket_Item`
 * tetapi NOT NULL di kembarannya pada tabel draft. Penyalinan mengisinya dengan
 * baku ('T'/'Y'), pembanding tidak — sehingga `''` di sisi live selalu kalah
 * melawan `'T'` di sisi draft. Terukur 10 Sep 2026: enam dari enam paket terbit
 * melaporkan perubahan sebanyak jumlah itemnya, seluruhnya dari `Flag_Camera`.
 *
 * Pelajarannya sudah pernah dicatat di modul ini dengan bentuk lain: gejala
 * "selalu terdeteksi berubah padahal tak disentuh" hampir selalu berarti satu
 * nilai disimpan dua kali dengan aturan normalisasi yang berbeda.
 */
class SopPaketDraftDiffTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): SopPaketDraftService
    {
        return app(SopPaketDraftService::class);
    }

    /**
     * Paket TERBIT berisi satu section dan satu item yang beberapa benderanya
     * NULL — keadaan yang memang ada di data live, bukan karangan.
     *
     * @return array{0:int,1:int} [idPaket, idItem]
     */
    private function paketTerbit(): array
    {
        $idPaket = DB::table('N_SOP_Master_Paket')->insertGetId([
            'Nama_Paket' => 'UJI DIFF DRAFT '.substr((string) microtime(true), -6),
            'Status' => SopStatus::PAKET_PUBLISHED,
            'Mode_Distribusi' => 'TERBATAS',
            'Flag_Aktif' => SopStatus::AKTIF,
            'Created_By' => 1,
            'Created_At' => now(),
        ], 'Id_SOP_Paket');

        $idSection = DB::table('N_SOP_Paket_Section')->insertGetId([
            'Id_SOP_Paket' => $idPaket,
            'Nama_Section' => 'Bagian Uji',
            'Urutan' => 1,
            'Flag_Aktif' => SopStatus::AKTIF,
            'Created_By' => 1,
            'Created_At' => now(),
        ], 'Id_SOP_Section');

        /* Kolom berkunci asing, jadi nilainya dipinjam dari versi yang
           benar-benar ada; angka karangan ditolak basis data. */
        $idVersi = DB::table('N_SOP_Item_Versi')->value('Id_SOP_Item_Versi');
        $this->assertNotNull($idVersi, 'Tak ada baris N_SOP_Item_Versi untuk dipinjam fixture ini.');

        $idItem = DB::table('N_SOP_Paket_Item')->insertGetId([
            'Id_SOP_Paket' => $idPaket,
            'Id_SOP_Section' => $idSection,
            'Tipe_Item' => SopTipeItem::DOKUMEN,
            'Judul_Item' => 'Item Uji Diff',
            'Id_Item_Versi_Aktif' => $idVersi,
            /* INTI FIXTURE INI: bendera NULL, persis yang dulu membuat setiap
               item terbaca berubah. Mengisinya akan membuat berkas ini hijau
               tanpa pernah menyentuh cacatnya.

               Tetapi hanya SATU kolom yang benar-benar bisa NULL. Dibaca dari
               katalog Web_HRIS 11 September 2026: Flag_Wajib, Flag_Highlight,
               dan Flag_Watermark semuanya NOT NULL dengan DEFAULT, dan nol dari
               36 baris live memuat NULL di sana — basis data menolak INSERT-nya,
               jadi fixture lama tidak pernah bisa jalan sama sekali. Flag_Camera
               memang nullable, dan 27 dari 36 baris live NULL di situ.

               Jadi keadaan yang ditiru di sini adalah keadaan yang sungguh bisa
               terjadi, bukan yang diandaikan. Ketiga kolom lain diisi nilai yang
               memang dipakai data live supaya cacatnya tetap datang dari kolom
               yang betul-betul NULL. */
            'Flag_Camera' => null,
            'Flag_Wajib' => SopStatus::AKTIF,
            'Flag_Highlight' => SopStatus::INAKTIF,
            'Flag_Watermark' => SopStatus::AKTIF,
            'Urutan' => 1,
            'Flag_Aktif' => SopStatus::AKTIF,
            'Created_By' => 1,
            'Created_At' => now(),
        ], 'Id_SOP_Paket_Item');

        return [$idPaket, $idItem];
    }

    public function test_draft_yang_baru_dibuka_nol_perubahan(): void
    {
        if (! $this->svc()->available()) {
            $this->markTestSkipped('Tabel working draft belum tersedia di environment ini.');
        }

        [$idPaket] = $this->paketTerbit();

        $this->assertNotNull($this->svc()->ensure($idPaket, 1), 'Working draft gagal dibuat.');

        $ringkas = $this->svc()->pendingChanges($idPaket, 1);

        $this->assertSame(
            0,
            (int) $ringkas['total'],
            'Draft yang belum disentuh siapa pun melaporkan perubahan: '
            .json_encode($ringkas['details']['items'] ?? [], JSON_UNESCAPED_UNICODE),
        );
        $this->assertSame([], $ringkas['details']['items'] ?? []);
    }

    /**
     * UJI PENOLAKAN — tanpa ini, pembanding yang selalu menjawab "nol" pun
     * lulus, dan penjaganya berhenti menjaga apa pun.
     */
    public function test_perubahan_sungguhan_tetap_terbaca(): void
    {
        if (! $this->svc()->available()) {
            $this->markTestSkipped('Tabel working draft belum tersedia di environment ini.');
        }

        [$idPaket] = $this->paketTerbit();
        $draft = $this->svc()->ensure($idPaket, 1);
        $this->assertNotNull($draft);

        DB::table('N_SOP_Paket_Draft_Item')
            ->where('Id_SOP_Paket_Draft', $draft->Id_SOP_Paket_Draft)
            ->update(['Deskripsi_Item' => 'Diubah admin']);

        $ringkas = $this->svc()->pendingChanges($idPaket, 1);

        $this->assertSame(1, (int) $ringkas['total'], 'Perubahan nyata pada item tidak terbaca.');
    }

    /**
     * Bendera yang MEMANG dimatikan admin tidak boleh ikut ternormalkan.
     *
     * Baku hanya berlaku bagi nilai kosong. Kalau ia juga menimpa nilai yang
     * terisi, mematikan Wajib pada sebuah item akan terbaca "tidak berubah" —
     * penjaga yang menyembuhkan gejala sambil menelan perubahan sah.
     */
    public function test_bendera_yang_sengaja_dimatikan_tetap_terbaca_berubah(): void
    {
        if (! $this->svc()->available()) {
            $this->markTestSkipped('Tabel working draft belum tersedia di environment ini.');
        }

        [$idPaket] = $this->paketTerbit();
        $draft = $this->svc()->ensure($idPaket, 1);
        $this->assertNotNull($draft);

        /* Live NULL → draft menyalinnya sebagai 'Y' (baku). Admin lalu
           mematikannya menjadi 'T' di draft: itu perubahan sah. */
        DB::table('N_SOP_Paket_Draft_Item')
            ->where('Id_SOP_Paket_Draft', $draft->Id_SOP_Paket_Draft)
            ->update(['Flag_Wajib' => SopStatus::INAKTIF]);

        $ringkas = $this->svc()->pendingChanges($idPaket, 1);

        $this->assertSame(1, (int) $ringkas['total'], 'Bendera yang dimatikan admin tak terbaca sebagai perubahan.');
    }
}
