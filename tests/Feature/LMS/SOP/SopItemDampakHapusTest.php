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
 * Apa yang ikut hilang kalau sebuah item dihapus.
 *
 * Konfirmasi hapus selama ini cuma berbunyi "Hapus item X?" — dan itu
 * menyembunyikan satu-satunya hal yang perlu ditimbang. Catatan baca karyawan
 * menempel PADA ITEM: 559 baris di dev, seluruhnya di paket terbit. Menghapus
 * item berarti mencabut sesuatu yang sudah ditugaskan, dan angka kepatuhan
 * paketnya berubah tanpa penjelasan bagi siapa pun yang membacanya belakangan.
 */
class SopItemDampakHapusTest extends TestCase
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

    public function test_menyebut_berapa_karyawan_yang_catatannya_menempel(): void
    {
        // APP_URL repo ini tanpa skema; tanpa ini permintaan uji meleset dari
        // route-nya dan berbalas 404. Lihat SopFormBerkasTest.
        URL::forceRootUrl('http://localhost');

        $idItem = DB::table('N_SOP_Progress')
            ->select('Id_SOP_Paket_Item', DB::raw('COUNT(*) as n'))
            ->groupBy('Id_SOP_Paket_Item')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->value('Id_SOP_Paket_Item');

        if (! $idItem) {
            $this->markTestSkipped('Tak ada item yang punya catatan baca.');
        }

        $res = $this->actingAs($this->adminBuilder())
            ->getJson("/api/v1/sop/admin/builder/item/{$this->enc($idItem)}/dampak");

        $res->assertOk();

        $harusnya = DB::table('N_SOP_Progress')->where('Id_SOP_Paket_Item', $idItem)->count();
        $this->assertSame($harusnya, $res->json('result.progres'),
            'Angka yang ditampilkan sebelum menghapus tak sama dengan yang benar-benar menempel.');

        // "Sudah menyelesaikan" adalah bagian dari progres, tak pernah melebihinya.
        $this->assertLessThanOrEqual($harusnya, $res->json('result.selesai'));
        $this->assertIsBool($res->json('result.paket_terbit'));
    }

    public function test_item_tanpa_catatan_baca_dijawab_nol_bukan_galat(): void
    {
        /* Konfirmasi yang gagal memuat dampak lebih buruk daripada konfirmasi
           yang menyebut nol dengan jujur — admin akan menyimpulkan sistemnya
           rusak lalu menekan Hapus tanpa membaca apa pun. */
        URL::forceRootUrl('http://localhost');

        $idItem = DB::table('N_SOP_Paket_Item as i')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->whereNotExists(fn ($q) => $q->from('N_SOP_Progress as g')
                ->whereColumn('g.Id_SOP_Paket_Item', 'i.Id_SOP_Paket_Item'))
            ->value('i.Id_SOP_Paket_Item');

        if (! $idItem) {
            $this->markTestSkipped('Semua item sudah punya catatan baca.');
        }

        $res = $this->actingAs($this->adminBuilder())
            ->getJson("/api/v1/sop/admin/builder/item/{$this->enc($idItem)}/dampak");

        $res->assertOk();
        $this->assertSame(0, $res->json('result.progres'));
        $this->assertSame(0, $res->json('result.selesai'));
    }

    /**
     * Token overlay draft dijawab nol, bukan ditolak.
     *
     * Item working-draft belum punya catatan baca sama sekali — yang dibaca
     * karyawan adalah item live. Menolaknya dengan 400/404 membuat konfirmasi
     * hapus gagal memuat justru di paket yang paling sering disunting.
     */
    public function test_item_working_draft_dijawab_nol(): void
    {
        URL::forceRootUrl('http://localhost');

        $draft = DB::table('N_SOP_Paket_Draft_Item')->value('Id_SOP_Draft_Item');
        if (! $draft) {
            $this->markTestSkipped('Tak ada item working draft.');
        }

        $res = $this->actingAs($this->adminBuilder())
            ->getJson('/api/v1/sop/admin/builder/item/di_'.$this->enc($draft).'/dampak');

        $res->assertOk();
        $this->assertSame(0, $res->json('result.progres'));
        $this->assertTrue($res->json('result.draft_overlay'));
    }
}
