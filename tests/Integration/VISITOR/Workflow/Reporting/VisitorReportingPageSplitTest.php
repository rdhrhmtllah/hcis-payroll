<?php

namespace Tests\Integration\VISITOR\Workflow\Reporting;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Pemisahan Laporan Visitor menjadi dua halaman.
 *
 * Yang diuji bukan "ada dua route", melainkan bahwa gerbangnya benar-benar
 * menggigit: pemegang kunci lama TIDAK otomatis masuk Export Studio. Tanpa uji
 * ini, kunci baru cuma hiasan dan pemisahannya semu.
 */
class VisitorReportingPageSplitTest extends TestCase
{
    use DatabaseTransactions;

    // Kunci DOMAIN, bukan kunci gabungan lama.
    //
    // URL gabungan (/laporanvisitor, /visitor/export-studio) kini MENGALIHKAN
    // ke halaman domainnya, dan pemegang kunci lama tidak memegang kunci
    // tujuannya — sehingga menuntut 200 di URL lama berarti menuntut
    // pemisahan yang justru sudah dibatalkan. Pengalihannya sendiri dijaga
    // PemisahanHalamanVisitorTest; yang ditagih di sini adalah gerbang
    // halaman tujuannya.
    private const KUNCI_MONITORING = 'visitorMonitoringTamuPage';

    private const KUNCI_STUDIO = 'visitorExportStudioTamuPage';

    private const URL_MONITORING = '/monitoring-tamu';

    // Halaman Monitoring Tamu B2B (DDL-2026-121).
    private const KUNCI_MONITORING_B2B = 'visitorMonitoringTamuB2BPage';

    private const URL_MONITORING_B2B = '/monitoring-b2b';

    private const URL_STUDIO = '/visitor/export-studio/tamu';

    /** Tiap uji memakai pengguna berbeda; lihat penggunaBerkunci(). */
    private static int $urutanPengguna = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // APP_URL di lingkungan ini tak bermuatan skema ('n8n.ajt.my.id'), jadi
        // url() menjadikannya ruas path dan permintaan PERTAMA tiap proses uji
        // nyasar ke /n8n.ajt.my.id/... lalu 404. Dipaku supaya uji ini menguji
        // gerbang, bukan salah konfigurasi .env.
        URL::forceRootUrl('http://localhost');
    }

    public function test_monitoring_terbuka_bagi_pemegang_kunci_domainnya(): void
    {
        $user = $this->penggunaBerkunci(self::KUNCI_MONITORING);

        $this->actingAs($user)
            ->get(self::URL_MONITORING)
            ->assertOk();
    }

    /**
     * DDL-2026-121: halaman monitoring tamu kini per tipe. Tipe dan endpoint
     * dikirim server (ditetapkan rute), bukan disimpulkan layar.
     */
    public function test_monitoring_regular_mengirim_tipe_dan_endpoint_regular(): void
    {
        $user = $this->penggunaBerkunci(self::KUNCI_MONITORING);

        $this->actingAs($user)
            ->get(self::URL_MONITORING)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tipeTamu', 'REGULAR')
                ->where('endpoints.laporan', '/laporanvisitor-tamu')
                ->where('endpoints.detail', '/laporanvisitor-detail-tamu')
                ->where('endpoints.papan_harian', '/laporanvisitor-papan-harian')
                ->where('endpoints.papan_tamu', '/laporanvisitor-papan-tamu')
                ->where('endpoints.antrian', '/laporanvisitor-antrian')
                ->where('endpoints.verifikator', '/verifikator-visitor/tamu')
                ->etc());
    }

    public function test_monitoring_b2b_dijaga_kuncinya_sendiri(): void
    {
        // Pemegang kunci Regular tidak otomatis membuka halaman B2B: tanpa
        // penjaga ini pemisahan tipe hanya tab yang disembunyikan.
        $reguler = $this->penggunaBerkunci(self::KUNCI_MONITORING);
        $this->assertNotSame(200, $this->actingAs($reguler)->get(self::URL_MONITORING_B2B)->getStatusCode());
        $this->assertNotSame(200, $this->actingAs($reguler)->get('/laporanvisitor-b2b-tamu')->getStatusCode());

        $b2b = $this->penggunaBerkunci(self::KUNCI_MONITORING_B2B);
        $this->actingAs($b2b)
            ->get(self::URL_MONITORING_B2B)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('VISITOR/Workflow/Reporting/Monitoring/monitoringTamu')
                ->where('tipeTamu', 'B2B')
                ->where('endpoints.laporan', '/laporanvisitor-b2b-tamu')
                ->where('endpoints.detail', '/laporanvisitor-b2b-detail-tamu')
                ->where('endpoints.papan_harian', '/laporanvisitor-b2b-papan-harian')
                ->where('endpoints.papan_tamu', '/laporanvisitor-b2b-papan-tamu')
                ->where('endpoints.antrian', '/laporanvisitor-b2b-antrian')
                ->where('endpoints.verifikator', '/verifikator-visitor/b2b')
                ->etc());
    }

    public function test_studio_menolak_pemegang_kunci_monitoring_saja(): void
    {
        $user = $this->penggunaBerkunci(self::KUNCI_MONITORING);

        $response = $this->actingAs($user)->get(self::URL_STUDIO);

        $this->assertNotSame(
            200,
            $response->getStatusCode(),
            'Kunci monitoring tidak boleh membuka Export Studio; kalau lolos, pemisahannya semu.',
        );
    }

    public function test_studio_terbuka_bagi_pemegang_kunci_studio(): void
    {
        $user = $this->penggunaBerkunci(self::KUNCI_STUDIO);

        $this->actingAs($user)
            ->get(self::URL_STUDIO)
            ->assertOk();
    }

    public function test_api_studio_menolak_pemegang_kunci_monitoring_saja(): void
    {
        $user = $this->penggunaBerkunci(self::KUNCI_MONITORING);

        // Gerbang halaman tanpa gerbang API hanya menyembunyikan tombolnya;
        // datanya tetap bisa ditarik langsung.
        $response = $this->actingAs($user)->get('/api/v1/visitor/export-studio/tamu/template');

        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_menu_studio_terdaftar_beserta_pemetaan_grupnya(): void
    {
        // Tanpa ketiga baris ini halaman baru 403 untuk semua orang, dan
        // menunya tidak pernah muncul di sidebar.
        $this->assertSame(
            1,
            DB::table('N_HRIS_Menus')->where('Jenis_page', self::KUNCI_STUDIO)->count(),
            'Baris N_HRIS_Menus untuk Export Studio belum ada.',
        );
        $this->assertSame(
            1,
            DB::table('N_HRIS_Grup_Fitur_Map')->where('Jenis_page', self::KUNCI_STUDIO)->count(),
            'Pemetaan grup fitur untuk Export Studio belum ada.',
        );
        $this->assertGreaterThan(
            0,
            DB::table('HRIS_Page_Access')
                ->where('Jenis_page', self::KUNCI_STUDIO)
                ->where('Flag_Aktif', 'Y')
                ->count(),
            'Belum ada seorang pun yang diberi hak Export Studio.',
        );
    }

    public function test_kunci_monitoring_lama_tidak_dicabut(): void
    {
        // Monitoring mewarisi URL dan kuncinya; mencabutnya berarti dua orang
        // kehilangan akses tanpa diminta.
        $this->assertSame(
            1,
            DB::table('N_HRIS_Menus')->where('Jenis_page', self::KUNCI_MONITORING)->count(),
        );
        $this->assertGreaterThan(
            0,
            DB::table('HRIS_Page_Access')
                ->where('Jenis_page', self::KUNCI_MONITORING)
                ->where('Flag_Aktif', 'Y')
                ->count(),
        );
    }

    public function test_kedua_halaman_menunjuk_berkas_vue_yang_benar_benar_ada(): void
    {
        // Inertia::render tidak memeriksa apakah komponennya ada. Salah ketik
        // nama halaman tetap membalas 200 di uji dan baru terlihat sebagai
        // layar kosong di browser.
        $halaman = [
            self::URL_MONITORING => 'VISITOR/Workflow/Reporting/Monitoring/monitoringTamu',
            self::URL_STUDIO => 'VISITOR/Workflow/Reporting/ExportStudio/exportStudioVisitor',
        ];

        foreach ($halaman as $uri => $komponen) {
            $user = $this->penggunaBerkunci(
                $uri === self::URL_MONITORING ? self::KUNCI_MONITORING : self::KUNCI_STUDIO,
            );

            $this->actingAs($user)
                ->get($uri)
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component($komponen));

            $this->assertFileExists(
                resource_path('js/Pages/'.$komponen.'.vue'),
                "Halaman {$uri} menunjuk komponen yang berkasnya tidak ada.",
            );
        }
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Pengguna yang memegang TEPAT satu kunci lewat jalur HRIS_Page_Access,
     * dan tidak memegang role apa pun yang bisa memberinya kunci lain.
     *
     * Setiap pemanggilan mengambil pengguna BERBEDA. User menyimpan hasil cek
     * hak dalam cache statis berkunci Id_Users yang hidup sepanjang proses uji;
     * memakai pengguna yang sama dua kali membuat uji kedua membaca hak lama
     * dan lulus atau gagal karena urutan, bukan karena gerbangnya.
     */
    private function penggunaBerkunci(string $kunci): User
    {
        $user = User::query()
            ->whereNotIn('Id_Users', function ($q) {
                $q->select('UserId_Web')->from('N_HRIS_Role_User')->where('Flag_Aktif', 'Y');
            })
            ->orderBy('Id_Users')
            ->skip(self::$urutanPengguna++)
            ->first();

        $this->assertNotNull($user, 'Tidak ada user tanpa role untuk menguji gerbang.');

        DB::table('HRIS_Page_Access')->insert([
            'Kode_Perusahaan' => '001',
            'ID_Level' => 5,
            'ID_Divisi' => 22,
            'Jenis_page' => $kunci,
            'UserID_Web' => $user->Id_Users,
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
        ]);

        return $user->fresh();
    }
}
