<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopPrioritasService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Master Prioritas Dokumen benar-benar berisi, bukan cuma bertabel.
 *
 * DUA PEMBACA, HANYA SATU YANG PUNYA JALUR MUNDUR — dan itulah sebabnya
 * tabel kosong bisa bertahan lama tanpa ketahuan:
 *
 *   SopPrioritasService::semua()  jatuh ke tiga tingkat BAWAAN saat tabelnya
 *                                 kosong. Seluruh modul — validasi, urutan
 *                                 papan risiko, lencana — karena itu tetap
 *                                 bekerja seolah masternya terisi.
 *
 *   SopAdminPrioritasController::index()  membaca tabelnya APA ADANYA. Ia
 *                                 memang harus begitu: yang disunting admin
 *                                 adalah baris, bukan konstanta PHP.
 *
 * Akibatnya halaman masternya kosong sementara sisa aplikasi tampak sehat.
 * Penjaga tersedia() tak menolong: ia memeriksa KEBERADAAN tabel, bukan isinya,
 * sehingga pesan "jalankan skrip 35 lebih dulu" tak pernah muncul.
 *
 * Tabelnya lahir dari plans/sop/sql/35_master_prioritas.sql yang hanya jalan
 * separuh — CREATE TABLE-nya iya, INSERT-nya tidak.
 */
class SopMasterPrioritasTerisiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        app(SopPrioritasService::class)->lupakan();
        URL::forceRootUrl('http://localhost');
    }

    private function adminPrioritas(): User
    {
        $uid = DB::table('HRIS_Page_Access')->where('Flag_Aktif', 'Y')
            ->where('Jenis_page', 'menuSopPrioritasPage')
            ->whereNotNull('UserID_Web')->value('UserID_Web');

        $u = $uid ? User::find($uid) : null;
        if (! $u) {
            $this->markTestSkipped('Tak ada user dengan menuSopPrioritasPage.');
        }

        return $u;
    }

    /**
     * Masternya berisi. Ini penjaga arah-MERAH-nya.
     *
     * Tanpa baris, halaman master menyambut admin dengan keadaan kosong yang
     * membacanya sebagai "belum ada tingkat prioritas" — padahal seluruh
     * modul sedang memakai tiga tingkat yang tak terlihat olehnya.
     */
    public function test_master_prioritas_tidak_kosong(): void
    {
        $this->assertGreaterThan(0,
            DB::table(SopPrioritasService::TABEL)->where('Flag_Aktif', 'Y')->count(),
            'N_SOP_Prioritas kosong. Halaman masternya akan tampil tanpa satu baris pun, '
            .'sementara sisa modul diam-diam memakai tiga tingkat bawaan dari kode.');
    }

    /**
     * Isinya PERSIS sama dengan jalur mundur yang selama ini dipakai modul.
     *
     * Kalau berbeda, mengisi masternya bukan lagi tindakan netral: urutan papan
     * risiko dan warna lencana akan bergeser pada hari yang sama tanpa satu
     * baris kode pun berubah.
     */
    public function test_isinya_sama_dengan_tiga_tingkat_bawaan(): void
    {
        $baris = DB::table(SopPrioritasService::TABEL)
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan')
            ->get(['Kode', 'Nama', 'Urutan', 'Warna']);

        $this->assertGreaterThanOrEqual(3, $baris->count());

        $harus = [
            ['KRITIS', 'Sangat Penting', 10, '#EF4444'],
            ['TINGGI', 'Tinggi', 20, '#F59E0B'],
            ['NORMAL', 'Normal', 30, '#94A3B8'],
        ];

        foreach ($harus as [$kode, $nama, $urutan, $warna]) {
            $b = $baris->firstWhere('Kode', $kode);
            $this->assertNotNull($b, "Tingkat {$kode} tak ada di master.");
            $this->assertSame($nama, $b->Nama);
            $this->assertSame($urutan, (int) $b->Urutan);
            $this->assertSame($warna, strtoupper((string) $b->Warna));
        }
    }

    /**
     * Halaman masternya benar-benar mengirim baris ke layar.
     *
     * Diuji lewat endpoint yang dipakai tabelnya, bukan lewat service: service
     * punya jalur mundur, endpoint tidak — dan justru perbedaan itulah yang
     * membuat cacat ini tak terlihat selama ini.
     */
    public function test_endpoint_master_mengirim_baris_bukan_daftar_kosong(): void
    {
        $res = $this->actingAs($this->adminPrioritas())
            ->getJson('/api/v1/sop/admin/prioritas/current')
            ->assertOk();

        $hasil = $res->json('result');

        $this->assertIsArray($hasil);
        $this->assertNotEmpty($hasil, 'Endpoint master mengirim daftar kosong — halamannya akan tampak tak berisi.');
        $this->assertArrayHasKey('Kode', $hasil[0]);
        $this->assertArrayHasKey('Jumlah_Paket', $hasil[0]);
    }

    /**
     * Mengisi master tidak menggeser apa pun.
     *
     * Sebelum diisi, semua() mengembalikan tiga tingkat bawaan; sesudah diisi,
     * ia mengembalikan baris tabel. Keduanya harus menghasilkan urutan dan
     * kode yang sama — kalau tidak, pengisian ini diam-diam mengubah papan
     * risiko.
     */
    public function test_urutan_mendesak_tak_bergeser_setelah_master_terisi(): void
    {
        $urut = app(SopPrioritasService::class)->semua()->pluck('Kode')->all();

        $this->assertSame(['KRITIS', 'TINGGI', 'NORMAL'], array_slice($urut, 0, 3),
            'Urutan mendesak bergeser. Papan risiko akan berubah tanpa satu baris kode pun berubah.');
    }
}
