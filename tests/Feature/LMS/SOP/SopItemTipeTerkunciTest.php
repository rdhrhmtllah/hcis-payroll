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
 * Tipe item ber-dokumen tak bisa diubah — dan penolakannya harus menjelaskan diri.
 *
 * Sejak Fase 6 tipe item adalah CERMINAN tipe dokumennya; terapkanDokumen()
 * memang sudah menolak setiap ketidakcocokan. Tapi pesannya berbunyi "Dokumen
 * SOP tidak valid", yang menyalahkan dokumennya dan menyuruh admin memeriksa
 * hal yang tak ada salahnya — sementara enam kotak tipe di layar tetap bisa
 * diklik.
 *
 * Alasannya bukan kerapian: pembaca tiap tipe berbeda, dan ratusan baris
 * catatan baca karyawan menempel pada tipe yang sekarang. Aturan yang sama
 * sudah lama berlaku di Master ("tipe dokumen tak bisa diubah selama masih
 * dipakai paket") — yang tertinggal hanya pintu Builder.
 */
class SopItemTipeTerkunciTest extends TestCase
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

    /** Item hidup ber-dokumen di paket yang belum terbit. */
    private function itemBerdokumen(): object
    {
        $row = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->join('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'i.Id_SOP_Dokumen')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('p.Status', SopStatus::PAKET_DRAFT)
            ->where('d.Tipe', 'DOKUMEN')
            ->first(['i.Id_SOP_Paket_Item', 'i.Id_SOP_Dokumen', 'i.Tipe_Item', 'd.Tipe as tipe_dokumen']);

        if (! $row) {
            $this->markTestSkipped('Tak ada item ber-dokumen di paket draft.');
        }

        return $row;
    }

    public function test_ubah_tipe_ditolak_dengan_sebab_yang_sebenarnya(): void
    {
        // APP_URL repo ini tanpa skema; tanpa ini permintaan uji meleset dari
        // route-nya dan berbalas 404. Lihat SopFormBerkasTest.
        URL::forceRootUrl('http://localhost');

        $it = $this->itemBerdokumen();

        $res = $this->actingAs($this->adminBuilder())
            ->putJson("/api/v1/sop/admin/builder/item/update/{$this->enc($it->Id_SOP_Paket_Item)}", [
                'Tipe_Item' => 'VIDEO', // dokumennya bertipe DOKUMEN
                'Id_SOP_Dokumen' => $this->enc($it->Id_SOP_Dokumen),
            ]);

        $res->assertStatus(422);

        $pesan = strtolower((string) $res->json('message'));
        $this->assertStringContainsString('tipe item mengikuti', $pesan,
            'Pesannya tak menyebut sebab sebenarnya — admin diarahkan memeriksa dokumen yang tak ada salahnya.');
        $this->assertStringNotContainsString('dokumen sop tidak valid', $pesan);

        // Dan tipenya benar-benar tak bergeser.
        $this->assertSame($it->Tipe_Item,
            DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $it->Id_SOP_Paket_Item)->value('Tipe_Item'));
    }

    public function test_menyimpan_tanpa_mengubah_tipe_tetap_boleh(): void
    {
        // Sisi lain: penjaganya tak boleh ikut menghalangi penyuntingan biasa.
        URL::forceRootUrl('http://localhost');

        $it = $this->itemBerdokumen();

        $res = $this->actingAs($this->adminBuilder())
            ->putJson("/api/v1/sop/admin/builder/item/update/{$this->enc($it->Id_SOP_Paket_Item)}", [
                'Tipe_Item' => $it->Tipe_Item, // sama
                'Id_SOP_Dokumen' => $this->enc($it->Id_SOP_Dokumen),
                'Deskripsi_Item' => 'Disunting tanpa menyentuh tipenya',
            ]);

        $res->assertOk();
    }

    /**
     * Cerminan tipe item ↔ dokumen tetap utuh di seluruh data.
     *
     * Penjaga di atas menutup pintunya ke depan; uji ini yang memberi tahu bila
     * suatu saat ada jalur lain yang membuatnya menyimpang — dan tipe yang
     * menyimpang berarti karyawan menemui pembaca yang tak bisa membuka isinya.
     */
    public function test_tak_ada_item_yang_tipenya_menyimpang_dari_dokumennya(): void
    {
        $menyimpang = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'i.Id_SOP_Dokumen')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->whereColumn('i.Tipe_Item', '<>', 'd.Tipe')
            ->count();

        $this->assertSame(0, $menyimpang,
            'Ada item yang tipenya beda dari dokumennya — pembacanya tak akan cocok dengan isinya.');
    }
}
