<?php

namespace Tests\Feature\VISITOR;

use App\Services\VISITOR\Governance\MasterData\VisitorMasterDataCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\VISITOR\MenegaskanPemecahanKunciHalaman;
use Tests\TestCase;

/**
 * Master Data dipecah per domain (DML-2026-107).
 *
 * Pembagiannya hidup di `VisitorMasterDataCatalog::DOMAIN` dan dibaca TIGA
 * pemakai: rute, gerbang API, dan katalog yang dikirim ke layar. Yang dijaga di
 * sini adalah ketiganya tetap sepakat — daftar yang menua di salah satunya
 * menghasilkan halaman yang menampilkan master yang endpointnya justru
 * menolaknya, dan itu baru ketahuan ketika admin menekan Simpan.
 */
class PemisahanMasterDataTest extends TestCase
{
    use DatabaseTransactions;
    use MenegaskanPemecahanKunciHalaman;

    private const KUNCI_TAMU = 'visitorMasterDataTamuPage';

    private const KUNCI_PERMIT = 'visitorMasterDataPermitPage';

    public function test_pembagian_domainnya_meliputi_seluruh_master(): void
    {
        // Master yang tidak punya domain akan hilang dari KEDUA halaman tanpa
        // satu pun galat — admin hanya menemukan tabnya lenyap.
        $tanpaDomain = array_diff(VisitorMasterDataCatalog::KEYS, array_keys(VisitorMasterDataCatalog::DOMAIN));

        $this->assertSame([], array_values($tanpaDomain), 'Ada master tanpa domain.');
    }

    public function test_setiap_master_muncul_di_halaman_yang_benar(): void
    {
        $tamu = VisitorMasterDataCatalog::keysDomain('tamu');
        $permit = VisitorMasterDataCatalog::keysDomain('permit');

        $this->assertContains('purpose', $tamu);
        $this->assertNotContains('purpose', $permit);

        $this->assertContains('kategori-permit', $permit);
        $this->assertNotContains('kategori-permit', $tamu);

        // Dipakai registrasi tamu DAN pengajuan permit.
        foreach (['plat-kendaraan', 'vehicle-type'] as $bersama) {
            $this->assertContains($bersama, $tamu, "{$bersama} hilang dari halaman tamu.");
            $this->assertContains($bersama, $permit, "{$bersama} hilang dari halaman permit.");
        }
    }

    public function test_katalog_disaring_di_server(): void
    {
        // Katalog utuh yang dikirim lalu disembunyikan di layar tetap memberi
        // tahu halaman tamu seluruh bentuk master permit.
        $kunciKatalog = array_column(VisitorMasterDataCatalog::forFrontend('tamu'), 'key');

        $this->assertSame(VisitorMasterDataCatalog::keysDomain('tamu'), $kunciKatalog);
        $this->assertNotContains('durasi-permit', $kunciKatalog);
    }

    public function test_setiap_endpoint_master_dijaga_kunci_domainnya(): void
    {
        foreach (VisitorMasterDataCatalog::DOMAIN as $master => $domain) {
            $rute = Route::getRoutes()->match(
                Request::create('/api/v1/visitor/master-data/'.$master, 'GET'),
            );

            $kunci = [];
            foreach ($rute->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'check.jabatan:')) {
                    $kunci = explode(',', substr($middleware, strlen('check.jabatan:')));
                }
            }

            sort($kunci);

            $diharapkan = match ($domain) {
                'tamu' => [self::KUNCI_TAMU],
                'permit' => [self::KUNCI_PERMIT],
                default => [self::KUNCI_PERMIT, self::KUNCI_TAMU],
            };
            sort($diharapkan);

            $this->assertSame($diharapkan, $kunci, "Penjaga endpoint {$master} tidak sesuai domainnya.");

            // Tanpa default ini controller menerima nama master dari URI yang
            // sudah tidak berparameter, dan gagal dengan galat yang menyebut
            // argumen — bukan masternya.
            $this->assertSame($master, $rute->defaults['master'] ?? null);
        }
    }

    public function test_kedua_halaman_dijaga_kunci_masing_masing(): void
    {
        foreach ([
            'admin/visitor/master-data/tamu' => self::KUNCI_TAMU,
            'admin/visitor/master-data/permit' => self::KUNCI_PERMIT,
        ] as $uri => $kunciDiharapkan) {
            $rute = Route::getRoutes()->match(Request::create('/'.$uri, 'GET'));

            $kunci = [];
            foreach ($rute->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'check.jabatan:')) {
                    $kunci = explode(',', substr($middleware, strlen('check.jabatan:')));
                }
            }

            $this->assertSame([$kunciDiharapkan], $kunci, "Penjaga halaman {$uri} tidak sesuai.");
        }
    }

    public function test_tidak_ada_rute_master_data_yang_masih_memakai_kunci_lama(): void
    {
        $pelanggar = [];

        foreach (Route::getRoutes() as $rute) {
            if (! str_contains($rute->uri(), 'visitor/master-data')) {
                continue;
            }

            foreach ($rute->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_contains($middleware, 'check.jabatan:visitorMasterDataPage')) {
                    $pelanggar[] = $rute->uri();
                }
            }
        }

        $this->assertSame([], $pelanggar, "Rute master data masih dijaga kunci lama:\n".implode("\n", $pelanggar));
    }

    public function test_url_lama_mengalihkan_bukan_hilang(): void
    {
        $userId = DB::table('HRIS_Page_Access')
            ->where('Jenis_page', self::KUNCI_PERMIT)
            ->where('Flag_Aktif', 'Y')
            ->value('UserID_Web');

        $pengguna = $userId ? \App\Models\ESS\Employee\Models\User::find($userId) : null;

        if (! $pengguna) {
            $this->markTestSkipped('Tak ada user development dengan akses Master Data Permit.');
        }

        $this->assertMengalihkanKe(
            '/admin/visitor/master-data/permit',
            $this->actingAs($pengguna)->get('/admin/visitor/master-data')->headers->get('Location'),
        );
    }

    public function test_kedua_halaman_terdaftar_di_menu_dan_grup_fitur(): void
    {
        foreach ([self::KUNCI_PERMIT, self::KUNCI_TAMU] as $halaman) {
            $this->assertTrue(
                DB::table('N_HRIS_Menus')->where('Jenis_page', $halaman)->where('Is_Show_Sidebar', 'Y')->exists(),
                "Menu {$halaman} belum terdaftar atau tidak tampil di sidebar.",
            );
            $this->assertTrue(
                DB::table('N_HRIS_Grup_Fitur_Map')->where('Jenis_page', $halaman)->where('Flag_Aktif', 'Y')->exists(),
                "Pemetaan grup fitur {$halaman} belum ada; menunya tidak akan pernah muncul.",
            );
        }

        $this->assertSame(
            'T',
            DB::table('N_HRIS_Menus')->where('Jenis_page', 'visitorMasterDataPage')->value('Is_Show_Sidebar'),
            'Menu master data gabungan masih tampil; ada dua jalan ke pekerjaan yang sama.',
        );
    }

    public function test_pemecahan_kunci_tidak_menghilangkan_maupun_menambah_akses(): void
    {
        $this->assertPemecahanKunciTidakMerugikan('visitorMasterDataPage', [self::KUNCI_PERMIT, self::KUNCI_TAMU]);
    }
}
