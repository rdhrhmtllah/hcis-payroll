<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopProgressReadService;
use App\Services\LMS\SOP\Support\SopSequenceGate;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Revisi satu dokumen tak boleh mengunci ulang pekerjaan yang sudah rampung.
 *
 * Pada paket SEKUENSIAL, item terkunci selama masih ada item WAJIB sebelumnya
 * yang belum selesai. Itu benar untuk orang yang baru mulai. Tapi ketika sebuah
 * dokumen di TENGAH paket direvisi, statusnya kembali ke BELUM — dan gerbang
 * yang sama lalu mengunci SELURUH item sesudahnya, termasuk yang sudah
 * dituntaskan karyawan berminggu-minggu sebelumnya. Layarnya jadi membantah
 * dirinya sendiri: lencana "Selesai" bersanding dengan gembok yang menyuruh
 * "selesaikan item wajib sebelumnya".
 *
 * Dua pintu, satu aturan. Tampilan (detailBundle) dan penegakan (SopSequenceGate)
 * sama-sama menggerbangi, dan sebelum ini KEDUANYA salah pada kasus yang sama —
 * hanya dengan akibat berbeda: tampilan menyembunyikan kartunya, penegakan
 * menolak buka() sehingga dokumen yang sudah dituntaskan pun tak bisa dibuka
 * lagi. Memperbaiki satu pintu saja meninggalkan kartu yang tampak terbuka tapi
 * ditolak begitu ditekan, jadi uji ini memaku keduanya.
 */
class SopGerbangUrutanRevisiTest extends TestCase
{
    use DatabaseTransactions;

    /** Penugasan pada paket SEKUENSIAL yang punya ≥2 item wajib berurutan. */
    private function skenarioAtauSkip(): object
    {
        $baris = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->where('p.Mode_Urutan_Item', 'SEKUENSIAL')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->orderByDesc('d.Id_SOP_Penjadwalan_Detail')
            ->get(['d.Id_SOP_Penjadwalan_Detail as detail', 'd.Kode_Karyawan as kode', 'p.Id_SOP_Paket as paket']);

        foreach ($baris as $b) {
            $items = DB::table('N_SOP_Paket_Item')
                ->where('Id_SOP_Paket', $b->paket)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->where('Flag_Wajib', SopStatus::AKTIF)
                ->whereNotNull('Id_Item_Versi_Aktif')
                ->orderBy('Urutan')
                ->get(['Id_SOP_Paket_Item', 'Id_Item_Versi_Aktif', 'Tipe_Item']);

            if ($items->count() >= 2) {
                $b->awal = $items->first();
                $b->akhir = $items->last();

                return $b;
            }
        }

        $this->markTestSkipped('Tak ada penugasan pada paket SEKUENSIAL dengan ≥2 item wajib.');
    }

    /**
     * Menyusun keadaan sesudah revisi: item pertama BELUM (versinya baru terbit),
     * item terakhir SELESAI (dituntaskan sebelum revisi itu ada).
     */
    private function pasangKeadaan(object $s): void
    {
        foreach ([[$s->awal, SopStatus::ITEM_BELUM], [$s->akhir, SopStatus::ITEM_SELESAI]] as [$item, $status]) {
            $this->tulisProgress($s, $item, (int) $item->Id_Item_Versi_Aktif, $status);
        }
    }

    /** Kode_Karyawan & Tipe_Item wajib NOT NULL — ikut ditulis, bukan diandaikan. */
    private function tulisProgress(object $s, object $item, int $versi, string $status): void
    {
        DB::table('N_SOP_Progress')->updateOrInsert(
            [
                'Id_SOP_Penjadwalan_Detail' => $s->detail,
                'Id_SOP_Paket_Item' => $item->Id_SOP_Paket_Item,
                'Id_SOP_Item_Versi' => $versi,
            ],
            [
                'Kode_Karyawan' => $s->kode,
                'Tipe_Item' => $item->Tipe_Item,
                'Status' => $status,
            ],
        );
    }

    private function itemDari(array $bundle, int $idItem): ?array
    {
        $hash = Hashids::connection('custom')->encode($idItem);

        return collect($bundle['items'] ?? [])
            ->first(fn ($x) => ($x['Id_SOP_Paket_Item'] ?? null) === $hash);
    }

    public function test_item_yang_sudah_selesai_tak_terkunci_lagi_saat_item_sebelumnya_direvisi(): void
    {
        $s = $this->skenarioAtauSkip();
        $this->pasangKeadaan($s);

        $bundle = app(SopProgressReadService::class)->detailBundle($s->kode, (int) $s->paket);

        $awal = $this->itemDari($bundle, (int) $s->awal->Id_SOP_Paket_Item);
        $akhir = $this->itemDari($bundle, (int) $s->akhir->Id_SOP_Paket_Item);

        if (! $awal || ! $akhir) {
            $this->markTestSkipped('Item uji tak muncul di bundle penugasan itu.');
        }

        // Prasyaratnya memang terpasang: yang di depan belum selesai.
        $this->assertNotContains($awal['Status'], [SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI]);

        $this->assertContains($akhir['Status'], [SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI],
            'Keadaan ujinya tak terpasang — item terakhir tak terbaca selesai.');
        $this->assertFalse($akhir['Terkunci'],
            'Item yang sudah selesai dikunci ulang: layar menampilkan "Selesai" dan gembok sekaligus.');
    }

    /**
     * Aturan yang sama harus dipasang di KEDUA pintu.
     *
     * Membuka kunci di tampilan saja tidak menyelesaikan apa pun: SopCompletionService::buka()
     * memakai gerbang yang sama, jadi kartu yang tampak terbuka tetap akan
     * ditolak 'TERKUNCI' begitu ditekan. Akibat nyatanya, karyawan tak bisa lagi
     * membuka dokumen yang SUDAH ia tuntaskan hanya karena berkas lain di
     * hulunya direvisi — arsip pribadinya ikut terkunci.
     */
    public function test_penegakan_tak_menghalangi_membuka_item_yang_sudah_dituntaskan(): void
    {
        $s = $this->skenarioAtauSkip();
        $this->pasangKeadaan($s);

        $this->assertFalse(
            SopSequenceGate::isLocked($s->kode, (int) $s->paket, (int) $s->akhir->Id_SOP_Paket_Item),
            'Item yang sudah selesai ditolak gerbang urutan — dokumennya tak bisa dibuka lagi.',
        );
    }

    /**
     * Item yang BELUM selesai tetap digerbangi — jalan keluar di atas tidak
     * boleh melar jadi "gerbangnya mati".
     */
    public function test_item_belum_selesai_tetap_terkunci_di_belakang_wajib_yang_tertunggak(): void
    {
        $s = $this->skenarioAtauSkip();

        // Dua-duanya belum selesai: yang di depan menghalangi yang di belakang.
        $this->tulisProgress($s, $s->awal, (int) $s->awal->Id_Item_Versi_Aktif, SopStatus::ITEM_BELUM);
        DB::table('N_SOP_Progress')
            ->where('Id_SOP_Penjadwalan_Detail', $s->detail)
            ->where('Id_SOP_Paket_Item', $s->akhir->Id_SOP_Paket_Item)
            ->delete();

        $this->assertTrue(
            SopSequenceGate::isLocked($s->kode, (int) $s->paket, (int) $s->akhir->Id_SOP_Paket_Item),
            'Gerbang urutan berhenti menggerbangi apa pun.',
        );

        $bundle = app(SopProgressReadService::class)->detailBundle($s->kode, (int) $s->paket);
        $akhir = $this->itemDari($bundle, (int) $s->akhir->Id_SOP_Paket_Item);
        if ($akhir) {
            $this->assertTrue((bool) $akhir['Terkunci'], 'Tampilan berhenti mengunci padahal penegakan masih menolak.');
        }
    }

    /**
     * "Lanjut dari sini" dan "baca ulang" adalah dua permintaan berbeda, dan
     * hanya server yang bisa membedakannya: bedanya ada pada baris progres versi
     * LAIN, yang tak pernah ikut turun ke browser.
     */
    public function test_dokumen_yang_pernah_dituntaskan_ditandai_perlu_baca_ulang(): void
    {
        $s = $this->skenarioAtauSkip();

        // Item pertama: BELUM pada versi aktif, tapi SELESAI pada versi lama.
        // Persis jejak yang ditinggalkan sebuah revisi.
        $versiLama = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Paket_Item', $s->awal->Id_SOP_Paket_Item)
            ->where('Id_SOP_Item_Versi', '!=', $s->awal->Id_Item_Versi_Aktif)
            ->value('Id_SOP_Item_Versi');

        if (! $versiLama) {
            $this->markTestSkipped('Item ujinya belum pernah direvisi.');
        }

        $this->tulisProgress($s, $s->awal, (int) $s->awal->Id_Item_Versi_Aktif, SopStatus::ITEM_BELUM);
        $this->tulisProgress($s, $s->awal, (int) $versiLama, SopStatus::ITEM_SELESAI);

        $bundle = app(SopProgressReadService::class)->detailBundle($s->kode, (int) $s->paket);
        $awal = $this->itemDari($bundle, (int) $s->awal->Id_SOP_Paket_Item);

        if (! $awal) {
            $this->markTestSkipped('Item uji tak muncul di bundle penugasan itu.');
        }

        $this->assertArrayHasKey('Perlu_Baca_Ulang', $awal);
        $this->assertTrue($awal['Perlu_Baca_Ulang'],
            'Dokumen yang pernah dituntaskan lalu direvisi tampil seolah belum pernah disentuh.');
    }

    public function test_item_yang_belum_pernah_disentuh_bukan_baca_ulang(): void
    {
        $s = $this->skenarioAtauSkip();

        DB::table('N_SOP_Progress')
            ->where('Id_SOP_Penjadwalan_Detail', $s->detail)
            ->where('Id_SOP_Paket_Item', $s->akhir->Id_SOP_Paket_Item)
            ->delete();

        $bundle = app(SopProgressReadService::class)->detailBundle($s->kode, (int) $s->paket);
        $akhir = $this->itemDari($bundle, (int) $s->akhir->Id_SOP_Paket_Item);

        if (! $akhir) {
            $this->markTestSkipped('Item uji tak muncul di bundle penugasan itu.');
        }

        $this->assertFalse($akhir['Perlu_Baca_Ulang'],
            'Item tanpa riwayat apa pun disebut "baca ulang" — ajakan mengulangi sesuatu yang belum pernah terjadi.');
    }
}
