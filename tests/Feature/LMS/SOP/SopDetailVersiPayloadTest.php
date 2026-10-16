<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Endpoint detail versi harus BENAR-BENAR menjawab, bukan diam-diam 500.
 *
 * Modal Detail Versi punya jalan mundur: bila permintaan detail gagal, ia
 * memakai baris ringkas dari daftar riwayat. Jalan mundur itu masuk akal, tapi
 * ia juga membuat kegagalan total tak terlihat — layar tetap terisi, hanya saja
 * review, alur langkah, dan blok pengesah tampil kosong, dan "belum ada review"
 * terbaca admin sebagai fakta tata kelola. Itulah yang terjadi: tiga lookup
 * master di dalam perakitan payload menyebut nama tabel dan kolom yang tak
 * pernah ada — `N_HRIS_Departemen`, `N_HRIS_Entitas`, dan kolom
 * `Id_Jenis_Dokumen` — sehingga SETIAP dokumen yang punya departemen membuat
 * endpoint melempar 500, sejak endpointnya lahir.
 *
 * Ketiganya lolos karena tak satu pun pernah dieksekusi: nama tabelnya ditulis
 * dari ingatan, dan galatnya ditelan `catch` di layar. Penjaga ini menuntut
 * jawaban 200 beserta blok-blok yang hanya bisa datang dari server, supaya
 * kekeliruan sejenis muncul sebagai kegagalan uji, bukan sebagai layar yang
 * "kebetulan sepi".
 */
class SopDetailVersiPayloadTest extends TestCase
{
    use DatabaseTransactions;

    private function adminDokumen(): User
    {
        $uid = DB::table('HRIS_Page_Access')
            ->where('Flag_Aktif', 'Y')
            ->where('Jenis_page', 'menuSopDokumenPage')
            ->whereNotNull('UserID_Web')
            ->value('UserID_Web');

        $u = $uid ? User::find($uid) : null;
        if (! $u) {
            $this->markTestSkipped('Tak ada user dengan menuSopDokumenPage.');
        }

        return $u;
    }

    /**
     * Versi yang dokumennya punya departemen, jenis, DAN entitas sekaligus.
     *
     * Ketiga lookup itu yang dulu meledak, dan masing-masing hanya dijalankan
     * bila kolomnya terisi. Versi tanpa departemen akan melewati cabang yang
     * rusak dan menghasilkan hijau palsu.
     */
    private function versiBermaster(): ?object
    {
        return DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'v.Id_SOP_Dokumen')
            ->whereNotNull('d.Departemen_Id')
            ->whereNotNull('d.Jenis_Dokumen_Id')
            ->whereNotNull('d.Entitas_Pemilik_Id')
            ->orderByDesc('v.Id_SOP_Item_Versi')
            ->first(['v.Id_SOP_Item_Versi', 'd.Departemen_Id', 'd.Jenis_Dokumen_Id', 'd.Entitas_Pemilik_Id']);
    }

    public function test_detail_versi_menjawab_200_dan_menamai_ketiga_masternya(): void
    {
        $versi = $this->versiBermaster();
        if (! $versi) {
            $this->markTestSkipped('Tak ada versi yang dokumennya lengkap bermaster.');
        }

        $enc = Hashids::connection('custom')->encode($versi->Id_SOP_Item_Versi);

        $res = $this->actingAs($this->adminDokumen())
            ->getJson("/api/v1/sop/admin/dokumen/versi/{$enc}/detail");

        $res->assertOk();

        $d = $res->json('result.Dokumen');

        /* Bukan sekadar "kunci ada": nama master yang null berarti lookupnya
           gagal diam-diam, persis keadaan yang membuat cacat ini tak terlihat. */
        $this->assertNotNull($d['Departemen_Nama'] ?? null, 'Nama departemen tak terisi.');
        $this->assertNotNull($d['Jenis_Dokumen_Nama'] ?? null, 'Nama jenis dokumen tak terisi.');
        $this->assertNotNull($d['Entitas_Nama'] ?? null, 'Nama entitas tak terisi.');
    }

    /**
     * Blok yang HANYA bisa datang dari server.
     *
     * Baris riwayat yang dipakai sebagai jalan mundur tak pernah membawa Alur,
     * dan itu sebabnya alur langkah tak pernah tampil. Menuntutnya di sini
     * membuat "layar kosong karena endpointnya mati" gagal sebagai uji.
     */
    public function test_detail_versi_membawa_alur_langkah_dan_daftar_review(): void
    {
        $versi = $this->versiBermaster();
        if (! $versi) {
            $this->markTestSkipped('Tak ada versi yang dokumennya lengkap bermaster.');
        }

        $enc = Hashids::connection('custom')->encode($versi->Id_SOP_Item_Versi);

        $res = $this->actingAs($this->adminDokumen())
            ->getJson("/api/v1/sop/admin/dokumen/versi/{$enc}/detail");

        $res->assertOk();

        $this->assertNotEmpty($res->json('result.Alur'), 'Alur langkah tak terkirim.');
        $this->assertIsArray($res->json('result.Review'), 'Daftar review tak terkirim.');
    }

    /**
     * Review yang ADA di basis data wajib sampai ke layar.
     *
     * Inilah gejala yang dilaporkan: versi yang jelas-jelas punya satu putaran
     * review tampil tanpa review sama sekali. Uji ini memakai versi yang
     * memang punya barisnya, jadi jumlah nol berarti cacat, bukan data sepi.
     */
    public function test_review_yang_tercatat_ikut_terkirim(): void
    {
        $idVersi = DB::table('N_SOP_Item_Versi_Review as r')
            ->join('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'r.Id_SOP_Item_Versi')
            ->where('r.Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('v.Id_SOP_Dokumen')
            ->orderByDesc('r.Id_Review')
            ->value('r.Id_SOP_Item_Versi');

        if (! $idVersi) {
            $this->markTestSkipped('Tak ada versi ber-review aktif.');
        }

        $harusnya = DB::table('N_SOP_Item_Versi_Review')
            ->where('Id_SOP_Item_Versi', $idVersi)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->count();

        $enc = Hashids::connection('custom')->encode($idVersi);

        $res = $this->actingAs($this->adminDokumen())
            ->getJson("/api/v1/sop/admin/dokumen/versi/{$enc}/detail");

        $res->assertOk();
        $this->assertCount($harusnya, $res->json('result.Review'));
    }

    /**
     * "Berlaku" itu penunjuk dokumen, bukan status versi.
     *
     * Is_Versi_Aktif harus menyala HANYA pada versi yang ditunjuk
     * N_SOP_Dokumen.Id_Versi_Aktif. Tanpa penjaga ini, dokumen yang punya lebih
     * dari satu baris PUBLISHED akan menyebut keduanya sedang dipakai karyawan.
     */
    public function test_is_versi_aktif_mengikuti_penunjuk_dokumen(): void
    {
        $dok = DB::table('N_SOP_Dokumen')
            ->whereNotNull('Id_Versi_Aktif')
            ->orderByDesc('Id_SOP_Dokumen')
            ->first(['Id_SOP_Dokumen', 'Id_Versi_Aktif']);

        if (! $dok) {
            $this->markTestSkipped('Tak ada dokumen dengan penunjuk versi aktif.');
        }

        $lain = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', $dok->Id_SOP_Dokumen)
            ->where('Id_SOP_Item_Versi', '!=', $dok->Id_Versi_Aktif)
            ->value('Id_SOP_Item_Versi');

        $admin = $this->adminDokumen();

        $aktif = $this->actingAs($admin)->getJson(
            '/api/v1/sop/admin/dokumen/versi/'
            .Hashids::connection('custom')->encode($dok->Id_Versi_Aktif).'/detail');
        $aktif->assertOk();
        $this->assertTrue($aktif->json('result.Is_Versi_Aktif'));

        if ($lain) {
            $bukan = $this->actingAs($admin)->getJson(
                '/api/v1/sop/admin/dokumen/versi/'
                .Hashids::connection('custom')->encode($lain).'/detail');
            $bukan->assertOk();
            $this->assertFalse($bukan->json('result.Is_Versi_Aktif'));
        }
    }

    /**
     * Pembuat dan pengubah dikirim sebagai NAMA.
     *
     * Kolom Created_By berisi Id_Users, dan layar jejak audit dulu mencetaknya
     * apa adanya — "Oleh: 4". Yang dijaga di sini bukan sekadar adanya kunci,
     * melainkan bahwa isinya bukan angka.
     */
    public function test_pembuat_versi_dikirim_sebagai_nama_bukan_id(): void
    {
        $idVersi = DB::table('N_SOP_Item_Versi as v')
            ->join('KPI_Users as u', 'u.Id_Users', '=', 'v.Created_By')
            ->whereNotNull('v.Id_SOP_Dokumen')
            ->orderByDesc('v.Id_SOP_Item_Versi')
            ->value('v.Id_SOP_Item_Versi');

        if (! $idVersi) {
            $this->markTestSkipped('Tak ada versi dengan pembuat yang tertaut akun.');
        }

        $enc = Hashids::connection('custom')->encode($idVersi);

        $res = $this->actingAs($this->adminDokumen())
            ->getJson("/api/v1/sop/admin/dokumen/versi/{$enc}/detail");

        $res->assertOk();

        $nama = $res->json('result.Dibuat_Oleh_Nama');
        $this->assertNotNull($nama, 'Nama pembuat tak terkirim.');
        $this->assertFalse(is_numeric($nama), "Nama pembuat masih berupa id: {$nama}");
    }
}
