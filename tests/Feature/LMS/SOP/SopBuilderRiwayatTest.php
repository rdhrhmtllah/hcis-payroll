<?php

namespace Tests\Feature\LMS\SOP;

use App\Http\Controllers\LMS\SOP\Governance\SopAdminBuilderController;
use App\Services\LMS\SOP\SopItemVersionService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Jejak audit Builder: cakupannya jujur, konteksnya cukup, pelakunya bernama.
 *
 * Riwayat yang memotong diam-diam lebih buruk daripada tak ada riwayat — ia
 * membuat orang menyimpulkan tak ada yang terjadi sesudah baris terakhir.
 * Karena itu jumlah keseluruhan ikut dikirim, dan layar menyebut "40 dari 312".
 */
class SopBuilderRiwayatTest extends TestCase
{
    use DatabaseTransactions;

    private function paketBerjejakAtauSkip(): int
    {
        $id = DB::table('N_SOP_Log')
            ->whereNotNull('Id_SOP_Paket')
            ->groupBy('Id_SOP_Paket')
            ->orderByRaw('COUNT(*) DESC')
            ->value('Id_SOP_Paket');

        if (! $id) {
            $this->markTestSkipped('Belum ada jejak Builder pada paket mana pun.');
        }

        return (int) $id;
    }

    private function riwayat(int $idPaket, array $params = []): array
    {
        $res = app(SopAdminBuilderController::class)->riwayatPaket(
            Request::create('/x', 'GET', $params),
            Hashids::connection('custom')->encode($idPaket),
        );

        return json_decode($res->getContent(), true)['result'] ?? [];
    }

    public function test_menyebut_jumlah_keseluruhan_bukan_cuma_yang_tampil(): void
    {
        $idPaket = $this->paketBerjejakAtauSkip();
        $hasil = $this->riwayat($idPaket, ['limit' => 2]);

        $this->assertArrayHasKey('total', $hasil,
            'Tanpa jumlah keseluruhan, daftar yang terpotong terbaca sebagai daftar yang lengkap.');
        $this->assertLessThanOrEqual(2, count($hasil['rows']));
        $this->assertSame(
            (int) DB::table('N_SOP_Log')->where('Id_SOP_Paket', $idPaket)->count(),
            (int) $hasil['total'],
        );
    }

    /**
     * "Muat lebih banyak" harus benar-benar melanjutkan, bukan mengulang.
     *
     * Offset yang diabaikan menghasilkan daftar yang bertambah panjang berisi
     * baris yang sama — dan itu tak kelihatan sebagai galat, hanya sebagai
     * riwayat yang membingungkan.
     */
    public function test_offset_melanjutkan_bukan_mengulang(): void
    {
        $idPaket = $this->paketBerjejakAtauSkip();

        $awal = $this->riwayat($idPaket, ['limit' => 2]);
        if (count($awal['rows']) < 2 || $awal['total'] <= 2) {
            $this->markTestSkipped('Jejaknya terlalu sedikit untuk menguji sambungan.');
        }

        $lanjut = $this->riwayat($idPaket, ['limit' => 2, 'offset' => 2]);

        $idAwal = array_column($awal['rows'], 'Id_SOP_Log');
        $idLanjut = array_column($lanjut['rows'], 'Id_SOP_Log');

        $this->assertNotEmpty($idLanjut);
        $this->assertEmpty(array_intersect($idAwal, $idLanjut),
            'Halaman kedua mengulang baris halaman pertama — offset tak dipakai.');
    }

    public function test_saringan_aksi_menyaring_sungguhan(): void
    {
        $idPaket = $this->paketBerjejakAtauSkip();
        $semua = $this->riwayat($idPaket, ['limit' => 200]);

        $aksi = $semua['aksi'][0] ?? null;
        if (! $aksi || count($semua['aksi']) < 2) {
            $this->markTestSkipped('Paketnya cuma punya satu jenis aksi.');
        }

        $tersaring = $this->riwayat($idPaket, ['limit' => 200, 'aksi' => $aksi]);

        $this->assertNotEmpty($tersaring['rows']);
        foreach ($tersaring['rows'] as $r) {
            $this->assertSame($aksi, $r['Aksi']);
        }
        $this->assertLessThan((int) $semua['total'], (int) $tersaring['total'],
            'Totalnya tak ikut tersaring — angka di kaki daftar akan membohongi.');
    }

    /**
     * Konteks item & versi. "Versi item dipublikasikan" tanpa menyebut item yang
     * mana bukan jejak yang bisa ditelusuri pada paket berisi belasan item.
     */
    public function test_baris_bertingkat_item_menyebut_itemnya(): void
    {
        $baris = DB::table('N_SOP_Log')
            ->whereNotNull('Id_SOP_Paket')
            ->whereNotNull('Id_SOP_Paket_Item')
            ->first(['Id_SOP_Paket', 'Id_SOP_Log']);

        if (! $baris) {
            $this->markTestSkipped('Belum ada jejak yang menunjuk item.');
        }

        $hasil = $this->riwayat((int) $baris->Id_SOP_Paket, ['limit' => 200]);
        $row = collect($hasil['rows'])->firstWhere('Id_SOP_Log', $baris->Id_SOP_Log);

        if (! $row) {
            $this->markTestSkipped('Barisnya di luar 200 terbaru.');
        }

        $this->assertArrayHasKey('Judul_Item', $row);
    }

    /**
     * Setiap versi menyebut pembuat dan pengubah terakhirnya.
     *
     * Created_By/Updated_By sudah lama ikut turun, tapi sebagai ANGKA Id_Users —
     * yang tak memberi tahu siapa pun apa pun. Working draft satu paket dipakai
     * bersama beberapa admin, jadi "siapa yang mengubah versi ini" tak bisa
     * dijawab dengan menebak.
     */
    public function test_daftar_versi_menyebut_pembuat_dan_pengubahnya(): void
    {
        $item = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'v.Id_SOP_Paket_Item')
            ->whereNotNull('v.Created_By')
            ->where('v.Flag_Aktif', SopStatus::AKTIF)
            ->value('i.Id_SOP_Paket_Item');

        if (! $item) {
            $this->markTestSkipped('Tak ada versi ber-Created_By.');
        }

        $rows = app(SopItemVersionService::class)->byItem((int) $item);
        if ($rows->isEmpty()) {
            $this->markTestSkipped('Item itu tak punya versi aktif.');
        }

        $this->assertObjectHasProperty('Dibuat_Oleh_Nama', $rows->first());
        $this->assertObjectHasProperty('Diubah_Oleh_Nama', $rows->first());

        $bernama = $rows->first(fn ($r) => ! empty($r->Dibuat_Oleh_Nama));
        $this->assertNotNull($bernama,
            'Tak satu pun versi menyebut pembuatnya — kolomnya turun tapi selalu kosong.');
    }
}
