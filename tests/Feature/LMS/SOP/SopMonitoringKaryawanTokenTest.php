<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\Support\SopKaryawanToken;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * URL halaman monitoring per karyawan tidak boleh memuat Kode_Karyawan polos.
 *
 * Yang dijaga di sini adalah dua hal yang gampang bocor lagi diam-diam:
 * (1) kode polos di URL harus DITOLAK — kalau tidak, jalur tebak-tebakan
 *     /karyawan/A4, /karyawan/A5, ... tetap terbuka meski token sudah ada;
 * (2) daftar karyawan harus ikut mengirim token, karena front-end tidak bisa
 *     menghitungnya sendiri dan akan diam-diam merakit link kode polos lagi.
 *
 * Akun uji dicari dari HRIS_Page_Access mengikuti pola SopExportStudioApiTest —
 * tidak mengarang user, dan di-skip dengan pesan jelas bila database dev tak
 * punya akun yang cocok.
 */
class SopMonitoringKaryawanTokenTest extends TestCase
{
    private const HALAMAN = '/sop/admin/monitoring/karyawan';

    private const API = '/api/v1/sop/admin/monitoring';

    private function adminSop(): User
    {
        $uid = DB::table('HRIS_Page_Access')->where('Flag_Aktif', 'Y')
            ->where('Jenis_page', 'menuSopMonitoringPage')
            ->whereNotNull('UserID_Web')->value('UserID_Web');

        $u = $uid ? User::find($uid) : null;
        if (! $u) {
            $this->markTestSkipped('Tak ada user dengan menuSopMonitoringPage di database ini.');
        }

        return $u;
    }

    /**
     * Header X-Inertia membuat respons berupa JSON, bukan blade root yang
     * memanggil @vite — supaya test tak bergantung pada hasil `npm run build`.
     * Versinya ikut dikirim, kalau tidak Inertia membalas 409 (minta reload).
     */
    private function kepalaInertia(): array
    {
        // Versinya diambil dari middleware yang sama dengan yang memeriksanya;
        // Inertia::getVersion() belum terisi sebelum ada request yang berjalan.
        $versi = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $versi,
        ];
    }

    /* ============ helper token ============ */

    public function test_token_bolak_balik_kembali_ke_kode_asal(): void
    {
        $this->assertSame('A4', SopKaryawanToken::decode(SopKaryawanToken::encode('A4')));
    }

    /** Token dipasang di segmen path, jadi '+', '/', dan '=' tidak boleh lolos. */
    public function test_token_aman_dipakai_sebagai_segmen_url(): void
    {
        $token = SopKaryawanToken::encode('A4');

        $this->assertSame($token, rawurlencode($token), 'Token berubah setelah rawurlencode: ada karakter yang tak aman di path.');
        $this->assertDoesNotMatchRegularExpression('#[+/=]#', $token);
    }

    public function test_token_tak_dapat_ditebak_dari_kode(): void
    {
        $a = SopKaryawanToken::encode('A4');
        $b = SopKaryawanToken::encode('A4');

        $this->assertNotSame($a, $b, 'Token deterministik: dua kali encode menghasilkan string sama.');
        $this->assertSame('A4', SopKaryawanToken::decode($a));
        $this->assertSame('A4', SopKaryawanToken::decode($b));
    }

    public function test_token_rusak_didekode_menjadi_null(): void
    {
        $this->assertNull(SopKaryawanToken::decode('A4'));
        $this->assertNull(SopKaryawanToken::decode('bukan-token-yang-sah'));
        $this->assertNull(SopKaryawanToken::decode(''));
    }

    /* ============ halaman profil ============ */

    public function test_halaman_menolak_kode_karyawan_polos(): void
    {
        $this->actingAs($this->adminSop())
            ->get(self::HALAMAN.'/A4')
            ->assertNotFound();
    }

    public function test_halaman_menolak_token_asal(): void
    {
        $this->actingAs($this->adminSop())
            ->get(self::HALAMAN.'/bukan-token-yang-sah')
            ->assertNotFound();
    }

    public function test_halaman_menerima_token_dan_meneruskan_kode_asli(): void
    {
        $token = SopKaryawanToken::encode('A4');

        $this->actingAs($this->adminSop())
            ->withHeaders($this->kepalaInertia())
            ->get(self::HALAMAN.'/'.$token)
            ->assertOk()
            ->assertJsonPath('component', 'LMS/SOP/Governance/Admin/SopMonitoringKaryawan')
            ->assertJsonPath('props.kode', 'A4')
            ->assertJsonPath('props.token', $token);
    }

    /* ============ halaman paket ============ */

    public function test_halaman_paket_menolak_kode_karyawan_polos(): void
    {
        $this->actingAs($this->adminSop())
            ->get(self::HALAMAN.'/A4/paket/1')
            ->assertNotFound();
    }

    public function test_halaman_paket_menerima_token_dan_meneruskan_kode_asli(): void
    {
        $token = SopKaryawanToken::encode('A4');

        $this->actingAs($this->adminSop())
            ->withHeaders($this->kepalaInertia())
            ->get(self::HALAMAN.'/'.$token.'/paket/1')
            ->assertOk()
            ->assertJsonPath('component', 'LMS/SOP/Governance/Admin/SopMonitoringKaryawanPaket')
            ->assertJsonPath('props.kode', 'A4')
            ->assertJsonPath('props.token', $token)
            ->assertJsonPath('props.idPaket', '1');
    }

    /* ============ token pada muatan daftar ============ */

    /**
     * Tanpa token pada baris daftar, front-end tidak punya bahan untuk merakit
     * href dan akan kembali memakai Kode_Karyawan polos.
     */
    public function test_daftar_karyawan_membawa_token_yang_dapat_didekode(): void
    {
        $r = $this->actingAs($this->adminSop())
            ->getJson(self::API.'/karyawan?page=1&limit=5')
            ->assertOk();

        $baris = $r->json('result') ?? [];
        if (empty($baris)) {
            $this->markTestSkipped('Database ini tak punya karyawan bertugas dokumen untuk diuji.');
        }

        foreach ($baris as $b) {
            $this->assertArrayHasKey('Token', $b, 'Baris daftar karyawan tak membawa Token.');
            $this->assertSame($b['Kode_Karyawan'], SopKaryawanToken::decode($b['Token']));
        }
    }

    /** Sumber tautan di TabPaket.vue. */
    public function test_detail_paket_membawa_token_pada_daftar_karyawannya(): void
    {
        $idPaket = DB::table('N_SOP_Penjadwalan as pj')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan', '=', 'pj.Id_SOP_Penjadwalan')
            ->value('pj.Id_SOP_Paket');

        if (! $idPaket) {
            $this->markTestSkipped('Database ini tak punya paket berpenugasan untuk diuji.');
        }

        $r = $this->actingAs($this->adminSop())
            ->getJson(self::API.'/paket/'.Hashids::connection('custom')->encode($idPaket))
            ->assertOk();

        $baris = $r->json('result.karyawan') ?? [];
        if (empty($baris)) {
            $this->markTestSkipped('Paket terpilih tak punya baris karyawan.');
        }

        foreach ($baris as $b) {
            $this->assertArrayHasKey('Token', $b, 'Baris karyawan pada detail paket tak membawa Token.');
            $this->assertSame($b['Kode_Karyawan'], SopKaryawanToken::decode($b['Token']));
        }
    }

    /** Sumber tautan di BatchKaryawanTable.vue. */
    public function test_daftar_karyawan_batch_membawa_token(): void
    {
        $idPenjadwalan = DB::table('N_SOP_Penjadwalan_Detail')->value('Id_SOP_Penjadwalan');

        if (! $idPenjadwalan) {
            $this->markTestSkipped('Database ini tak punya penjadwalan berdetail untuk diuji.');
        }

        $r = $this->actingAs($this->adminSop())
            ->getJson('/api/v1/sop/admin/penjadwalan/batch/'.Hashids::connection('custom')->encode($idPenjadwalan).'/karyawan')
            ->assertOk();

        $baris = $r->json('result') ?? [];
        if (empty($baris)) {
            $this->markTestSkipped('Batch terpilih tak punya baris karyawan.');
        }

        foreach ($baris as $b) {
            $this->assertArrayHasKey('Token', $b, 'Baris karyawan pada batch tak membawa Token.');
            $this->assertSame($b['Kode_Karyawan'], SopKaryawanToken::decode($b['Token']));
        }
    }

    /**
     * Sumber tautan lewat KaryawanDrawer di ZonaTindakan.vue — drawer memuat
     * tautan ke halaman profil, jadi barisnya butuh token juga.
     *
     * @dataProvider muatanPembukaDrawer
     */
    public function test_muatan_pembuka_drawer_membawa_token(string $jalur): void
    {
        $r = $this->actingAs($this->adminSop())
            ->getJson(self::API.$jalur)
            ->assertOk();

        $baris = $r->json('result.rows') ?? $r->json('result') ?? [];
        if (empty($baris)) {
            $this->markTestSkipped("Tak ada baris di {$jalur} pada database ini.");
        }

        foreach ($baris as $b) {
            if (! isset($b['Kode_Karyawan'])) {
                continue;
            }
            $this->assertArrayHasKey('Token', $b, "Baris {$jalur} tak membawa Token.");
            $this->assertSame($b['Kode_Karyawan'], SopKaryawanToken::decode($b['Token']));
        }
    }

    public static function muatanPembukaDrawer(): array
    {
        return [
            'kepatuhan kilat' => ['/kepatuhan/kilat?limit=8'],
            'kepatuhan mandek' => ['/kepatuhan/mandek?limit=8'],
        ];
    }

    /* ============ penjaga sisi front-end ============ */

    /**
     * Token di server tidak ada gunanya kalau front-end masih merakit alamatnya
     * sendiri dari Kode_Karyawan — hasilnya hanya 404 di mana-mana. Penjaga ini
     * memindai sumber Vue/JS, dan sengaja mencetak berkas yang melanggar supaya
     * kegagalannya bisa langsung ditindak.
     */
    public function test_tak_ada_tautan_monitoring_yang_dirakit_dari_kode_polos(): void
    {
        $akar = base_path('resources/js');
        $pelanggar = [];

        $berkas = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($akar));
        foreach ($berkas as $f) {
            if (! $f->isFile() || ! preg_match('/\.(vue|js)$/', $f->getFilename())) {
                continue;
            }

            foreach (file($f->getPathname()) as $i => $baris) {
                if (! str_contains($baris, '/sop/admin/monitoring/karyawan/')) {
                    continue;
                }
                /* Bukan sekadar melarang Kode_Karyawan: apa pun yang disisipkan
                   harus jelas-jelas token. Drawer dulu memakai `${kode}` — polos,
                   tapi lolos kalau larangannya hanya menyebut nama kolomnya. */
                if (preg_match('/monitoring\/karyawan\/\$\{([^}]*)\}/', $baris, $m) && ! preg_match('/token/i', $m[1])) {
                    $pelanggar[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $f->getPathname()).':'.($i + 1);
                }
            }
        }

        $this->assertSame([], $pelanggar, "Tautan monitoring masih memakai Kode_Karyawan polos:\n".implode("\n", $pelanggar));
    }
}
