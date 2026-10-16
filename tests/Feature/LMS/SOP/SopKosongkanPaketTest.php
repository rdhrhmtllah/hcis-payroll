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
 * "Mulai ulang" untuk paket yang BELUM terbit.
 *
 * Paket terbit sudah lama punya jalan keluar dari revisi yang kacau: salinan
 * kerjanya dibuang, data aktif tak tersentuh. Paket DRAFT justru tidak punya
 * apa-apa — endpoint itu menolaknya dengan tegas ("hanya berlaku untuk paket
 * yang sudah terbit") — padahal di sanalah orang paling banyak mencoba-coba,
 * dan paket hasil clone lahir persis dalam keadaan itu.
 *
 * Yang dijaga di sini tiga hal, dan yang KETIGA paling mudah rusak:
 *   1. paket draft benar-benar kosong sesudahnya;
 *   2. paket terbit ditolak — isinya sedang dibaca orang;
 *   3. DOKUMEN SOP-nya tidak ikut hilang. Item hanyalah penempatan dokumen
 *      pada paket; dokumennya milik bersama dan dikelola Master Dokumen.
 *      Menghapusnya dari sini akan melenyapkannya dari paket LAIN juga.
 */
class SopKosongkanPaketTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // APP_URL repo ini tanpa skema; tanpa ini permintaan uji meleset dari
        // route-nya dan berbalas 404. Lihat SopFormBerkasTest.
        URL::forceRootUrl('http://localhost');
    }

    private function admin(): User
    {
        $uid = DB::table('HRIS_Page_Access')->where('Flag_Aktif', 'Y')
            ->where('Jenis_page', 'menuSopBuilderPage')->whereNotNull('UserID_Web')->value('UserID_Web');

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

    /** Paket DRAFT yang benar-benar berisi — kalau kosong, uji ini tak menguji apa pun. */
    private function paketDraftBerisi(): int
    {
        $id = DB::table('N_SOP_Master_Paket as p')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket', '=', 'p.Id_SOP_Paket')
            ->where('p.Status', SopStatus::PAKET_DRAFT)
            ->where('p.Flag_Aktif', SopStatus::AKTIF)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->value('p.Id_SOP_Paket');

        if (! $id) {
            $this->markTestSkipped('Tak ada paket DRAFT berisi item di data dev.');
        }

        return (int) $id;
    }

    private function kirim(int $idPaket)
    {
        return $this->actingAs($this->admin())
            ->deleteJson("/api/v1/sop/admin/builder/paket/{$this->enc($idPaket)}/isi");
    }

    public function test_paket_draft_benar_benar_kosong_sesudahnya(): void
    {
        $id = $this->paketDraftBerisi();

        $itemSebelum = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket', $id)
            ->where('Flag_Aktif', SopStatus::AKTIF)->count();
        $this->assertGreaterThan(0, $itemSebelum, 'Fixture tak berisi — uji ini tak akan membuktikan apa pun.');

        $res = $this->kirim($id);
        $res->assertOk();

        $this->assertSame($itemSebelum, (int) $res->json('result.item'),
            'Jumlah yang dilaporkan tak sama dengan yang benar-benar dilepas.');

        $this->assertSame(0, DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket', $id)
            ->where('Flag_Aktif', SopStatus::AKTIF)->count());
        $this->assertSame(0, DB::table('N_SOP_Paket_Section')->where('Id_SOP_Paket', $id)
            ->where('Flag_Aktif', SopStatus::AKTIF)->count());

        // Paketnya sendiri tetap hidup: yang dikosongkan isinya, bukan paketnya.
        $this->assertSame(SopStatus::AKTIF,
            DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $id)->value('Flag_Aktif'));
    }

    public function test_dokumen_sop_dan_versinya_tidak_ikut_hilang(): void
    {
        $id = $this->paketDraftBerisi();

        $dokumen = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket', $id)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('Id_SOP_Dokumen')
            ->pluck('Id_SOP_Dokumen')->unique()->values()->all();

        if (! $dokumen) {
            $this->markTestSkipped('Paket draft itu tak punya item ber-dokumen.');
        }

        $versiSebelum = DB::table('N_SOP_Item_Versi')->whereIn('Id_SOP_Dokumen', $dokumen)
            ->where('Flag_Aktif', SopStatus::AKTIF)->count();

        $this->kirim($id)->assertOk();

        $this->assertSame(count($dokumen),
            DB::table('N_SOP_Dokumen')->whereIn('Id_SOP_Dokumen', $dokumen)
                ->where('Flag_Aktif', SopStatus::AKTIF)->count(),
            'Dokumen ikut dinonaktifkan. Ia milik bersama — paket LAIN yang memakainya '
            .'akan kehilangan isinya juga.');

        $this->assertSame($versiSebelum,
            DB::table('N_SOP_Item_Versi')->whereIn('Id_SOP_Dokumen', $dokumen)
                ->where('Flag_Aktif', SopStatus::AKTIF)->count(),
            'Versi dokumen ikut dinonaktifkan.');
    }

    public function test_paket_terbit_ditolak_dan_isinya_tak_tersentuh(): void
    {
        $id = DB::table('N_SOP_Master_Paket as p')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket', '=', 'p.Id_SOP_Paket')
            ->where('p.Status', SopStatus::PAKET_PUBLISHED)
            ->where('p.Flag_Aktif', SopStatus::AKTIF)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->value('p.Id_SOP_Paket');

        if (! $id) {
            $this->markTestSkipped('Tak ada paket terbit berisi item.');
        }

        $sebelum = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket', $id)
            ->where('Flag_Aktif', SopStatus::AKTIF)->count();

        $res = $this->kirim((int) $id);

        $res->assertStatus(422);
        $this->assertStringContainsString('Buang Draft Revisi', (string) $res->json('message'),
            'Penolakannya harus menunjuk jalan yang benar, bukan sekadar berkata tidak bisa.');

        // Penolakan yang cuma di layar tak ada gunanya.
        $this->assertSame($sebelum, DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket', $id)
            ->where('Flag_Aktif', SopStatus::AKTIF)->count(),
            'Isinya berkurang padahal permintaannya ditolak.');
    }

    /**
     * Penjaga penugasan — hari ini tak pernah menyala di data nyata (nol
     * penjadwalan pada seluruh paket DRAFT), jadi ia HARUS dibuktikan dengan
     * memaksakan keadaannya. Penjaga yang tak pernah terlihat menolak sama saja
     * dengan tidak ada.
     *
     * Alasannya tetap: gerbang "hanya paket terbit yang bisa dijadwalkan" hidup
     * di daftar pilihan penjadwalan, bukan sebagai aturan pada penulisannya.
     */
    public function test_paket_draft_yang_sudah_punya_penugasan_ditolak(): void
    {
        $id = $this->paketDraftBerisi();

        DB::table('N_SOP_Penjadwalan')->insert([
            'Id_SOP_Paket' => $id,
            'Nama_Batch' => 'UJI PENJAGA KOSONGKAN',
            'Sumber' => 'MANUAL',
            'Tipe_Tenggat' => 'SAMPAI_DICABUT',
            'Flag_Cabut_Akses' => 'T',
            'Tanggal_Assign' => now(),
            'Created_By' => 1,
            'Created_At' => now(),
            'Flag_Aktif' => SopStatus::AKTIF,
        ]);

        $sebelum = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket', $id)
            ->where('Flag_Aktif', SopStatus::AKTIF)->count();

        $res = $this->kirim($id);

        $res->assertStatus(422);
        $this->assertStringContainsString('penugasan', (string) $res->json('message'));

        $this->assertSame($sebelum, DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket', $id)
            ->where('Flag_Aktif', SopStatus::AKTIF)->count(),
            'Isinya tetap dikosongkan padahal ada penugasan — catatan baca karyawan terputus.');
    }
}
