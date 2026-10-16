<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopRepositoryReadService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Perpustakaan jadi daftar paket datar.
 *
 * Lensa "Paket" berak per folder Kategori sampai 28 Agu 2026. Setelah Kategori
 * dicabut, satu-satunya sumbu yang melekat di PAKET tinggal Prioritas — dan
 * prioritas bukan topik, jadi ia tak layak jadi folder. APQC dan Jenis Dokumen
 * melekat di DOKUMEN, bukan paket, sehingga satu paket akan muncul di beberapa
 * folder sekaligus kalau dipakai.
 *
 * Lensa "Dokumen" TIDAK ikut berubah: ia memang berak per departemen, jenis,
 * dan proses — ketiganya milik dokumen, dan di sana pengelompokan itu jujur.
 */
class SopRepositoryDatarTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('http://localhost');
    }

    /**
     * Karyawan penerima tugas SOP yang akunnya BENAR-BENAR ada.
     *
     * Tautannya Karyawan.UserID_Web -> KPI_Users.Id_Users; tabel Karyawan tak
     * punya kolom Id_Users. Dan UserID_Web boleh menunjuk akun yang sudah tak
     * ada — karena itu join-nya sampai ke tabel user, bukan berhenti di kolom
     * penunjuknya. Bentuk lama meng-skip diam-diam pada baris pertama yang
     * penunjuknya menggantung, dan uji yang di-skip tak menguji apa pun.
     */
    private function karyawan(): User
    {
        $uid = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'd.Kode_Karyawan')
            ->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')
            ->where('d.Flag_Aktif', 'Y')
            ->value('u.Id_Users');

        $u = $uid ? User::find($uid) : null;
        if (! $u) {
            $this->markTestSkipped('Tak ada penerima tugas SOP yang tertaut akun.');
        }

        return $u;
    }

    private function kodeKaryawanFikstur(): string
    {
        $kode = DB::table('N_SOP_Penjadwalan_Detail')
            ->where('Flag_Aktif', 'Y')->whereNotNull('Kode_Karyawan')
            ->value('Kode_Karyawan');

        if (! $kode) {
            $this->markTestSkipped('Tak ada penugasan SOP di database ini.');
        }

        return (string) $kode;
    }

    /** Alamat drilldown folder sudah tak ada — bukan sekadar tak dipakai layar. */
    public function test_rute_kategori_perpustakaan_sudah_tidak_ada(): void
    {
        $tersisa = [];
        foreach (Route::getRoutes() as $r) {
            if (str_contains($r->uri(), 'sop/repository/kategori')) {
                $tersisa[] = $r->uri();
            }
        }

        $this->assertSame([], $tersisa, 'Rute drilldown kategori masih ada: '.implode(', ', $tersisa));

        $this->actingAs($this->karyawan())
            ->getJson('/api/v1/sop/repository/kategori/semua')
            ->assertNotFound();
    }

    /** Penggantinya ada dan benar-benar mengembalikan daftar. */
    public function test_endpoint_daftar_paket_melayani_perpustakaan(): void
    {
        $this->actingAs($this->karyawan())
            ->getJson('/api/v1/sop/repository/paket?page=1&limit=12')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    /**
     * Beranda tak lagi mengirim folder, tapi TETAP mengirim totalnya.
     *
     * Totalnya dulu dijumlahkan dari folder. Membuang folder tanpa mengganti
     * sumber totalnya akan membuat angka "Prosedur Publik" di kepala halaman
     * diam-diam jadi nol — kerusakan yang tak melempar galat apa pun.
     */
    public function test_beranda_tak_lagi_mengirim_folder_tapi_totalnya_tetap_benar(): void
    {
        $d = app(SopRepositoryReadService::class)->beranda($this->kodeKaryawanFikstur());

        $this->assertArrayNotHasKey('kategori', $d);
        $this->assertArrayHasKey('total', $d);

        $harusnya = DB::table('N_SOP_Master_Paket')
            ->where('Status', 'PUBLISHED')
            ->where('Flag_Aktif', 'Y')
            ->where(fn ($q) => $q->where('Mode_Distribusi', 'PUBLIK')->orWhereNull('Mode_Distribusi'))
            ->count();

        $this->assertGreaterThan(0, $harusnya, 'Tak ada paket publik — uji ini tak menguji apa pun.');
        $this->assertSame($harusnya, (int) $d['total']);
    }

    /** Kartu paket tak lagi membawa kategori ke layar. */
    public function test_kartu_paket_tak_lagi_membawa_kategori(): void
    {
        $d = app(SopRepositoryReadService::class)
            ->daftarPaket($this->kodeKaryawanFikstur(), [], 1, 12);

        $this->assertNotEmpty($d['rows'], 'Daftar paket kosong — uji ini tak menguji apa pun.');

        $baris = $d['rows'][0];
        $this->assertArrayNotHasKey('kategori', $baris);
        $this->assertArrayNotHasKey('id_kategori', $baris);
        $this->assertArrayHasKey('Nama_Paket', $baris, 'Bentuk kartunya rusak, bukan sekadar kehilangan kategori.');
    }

    /**
     * Lensa Dokumen tidak ikut terseret.
     *
     * Katalog dokumen tetap membawa ketiga sumbu miliknya sendiri. Kalau ini
     * merah, pencabutan Kategori merembet ke lensa yang sama sekali tak
     * memakainya.
     */
    public function test_katalog_dokumen_tetap_membawa_sumbunya_sendiri(): void
    {
        $rows = app(SopRepositoryReadService::class)->katalogDokumen();

        $this->assertNotEmpty($rows, 'Katalog dokumen kosong — uji ini tak menguji apa pun.');

        $baris = $rows[0];
        $this->assertArrayNotHasKey('Kategori', $baris);
        $this->assertArrayHasKey('Departemen', $baris);
        $this->assertArrayHasKey('Jenis_Dokumen', $baris);
        $this->assertArrayHasKey('Apqc', $baris);
    }
}
