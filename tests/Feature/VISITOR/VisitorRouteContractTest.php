<?php

namespace Tests\Feature\VISITOR;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class VisitorRouteContractTest extends TestCase
{
    public function test_self_service_mutations_use_explicit_safe_routes(): void
    {
        $permitCancel = app('router')->getRoutes()->match(Request::create('/transaksipermit/10/cancel', 'POST'));
        $this->assertSame('App\Http\Controllers\VISITOR\Permit\VehiclePermit\VehiclePermitController@cancel', $permitCancel->getActionName());

        $validity = app('router')->getRoutes()->match(Request::create('/verifikator-permit/PMT-1/validity', 'POST'));
        $this->assertSame('App\Http\Controllers\VISITOR\Workflow\Verification\VerifikatorVisitorController@setPermitValidity', $validity->getActionName());
    }

    public function test_route_koreksi_permit_terdaftar_di_controller_verifikator(): void
    {
        $router = app('router')->getRoutes();
        $c = 'App\Http\Controllers\VISITOR\Workflow\Verification\VerifikatorVisitorController';

        $this->assertSame("{$c}@koreksiPermit", $router->match(Request::create('/verifikator-permit/PMT-1/koreksi', 'POST'))->getActionName());
        $this->assertSame("{$c}@pratinjauKoreksiChecklist", $router->match(Request::create('/verifikator-permit/PMT-1/koreksi/pratinjau-checklist', 'GET'))->getActionName());
        $this->assertSame("{$c}@masterKoreksiPermit", $router->match(Request::create('/verifikator-permit-koreksi-master', 'GET'))->getActionName());

        $koreksi = $router->match(Request::create('/verifikator-permit/PMT-1/koreksi', 'POST'));
        $this->assertContains('check.jabatan:visitorVerifikatorPermitPage', $koreksi->middleware());
    }

    public function test_route_pindah_jadwal_inspeksi_milik_agenda(): void
    {
        $router = app('router')->getRoutes();
        $c = 'App\Http\Controllers\VISITOR\Permit\Inspection\AgendaInspeksiController';

        $pindah = $router->match(Request::create('/agenda-inspeksi/PMT-1/pindah', 'POST'));
        $this->assertSame("{$c}@pindah", $pindah->getActionName());
        $this->assertContains('check.jabatan:visitorAgendaInspeksiPage', $pindah->middleware());
        $this->assertSame("{$c}@slot", $router->match(Request::create('/agenda-inspeksi-slot', 'GET'))->getActionName());
    }

    public function test_cetak_kartu_massal_punya_pintu_karyawan_dan_admin_terpisah(): void
    {
        // Pintu karyawan menyaring pemilik; hanya pintu admin (kunci monitoring) yang boleh lintas pemilik.
        $router = app('router')->getRoutes();
        $c = 'App\\Http\\Controllers\\VISITOR\\Permit\\VehiclePermit\\VehiclePermitController';

        $this->assertSame("{$c}@kartuPdfBatch", $router->match(Request::create('/transaksipermit-kartu-pdf-batch', 'POST'))->getActionName());
        $this->assertSame("{$c}@kartuPdfBatchAdmin", $router->match(Request::create('/laporanvisitor-permit/kartu-pdf-batch', 'POST'))->getActionName());
    }

    /** @dataProvider removedMutationProvider */
    public function test_removed_mutation_routes_are_not_registered(string $method, string $uri): void
    {
        try {
            app('router')->getRoutes()->match(Request::create($uri, $method));
            $this->fail("Mutation route {$method} {$uri} masih terdaftar.");
        } catch (NotFoundHttpException|MethodNotAllowedHttpException $e) {
            $this->assertTrue(true);
        }
    }

    public static function removedMutationProvider(): array
    {
        return [
            ['POST', '/transaksitamu/check-in/1'],
            ['POST', '/transaksitamu/check-out/1'],
            ['DELETE', '/transaksitamu/1'],
            // Halaman B2B (DDL-2026-121) juga tidak punya jalur tulis kehadiran:
            // kehadiran dicatat palang, dan tombol check-out HCIS dibuang.
            ['POST', '/transaksi-tamu-b2b/check-in/1'],
            ['POST', '/transaksi-tamu-b2b/check-out/1'],
            ['DELETE', '/transaksi-tamu-b2b/1'],
            ['DELETE', '/transaksipermit/1'],
            ['POST', '/transaksipermit-upload'],
        ];
    }

    /**
     * Rute Tamu B2B (DDL-2026-121): salinan rute tamu dengan kunci halaman
     * sendiri. Tipe ditetapkan middleware `visitor.tipe-tamu:B2B`, bukan
     * isian klien; rute yang lupa middleware itu diam-diam dibaca REGULAR.
     *
     * @dataProvider ruteB2BProvider
     */
    public function test_b2b_routes_resolve_with_their_own_page_key_and_type(string $method, string $uri, string $action, string $kunci): void
    {
        $route = app('router')->getRoutes()->match(Request::create($uri, $method));
        $middleware = $route->gatherMiddleware();

        $this->assertSame($action, $route->getActionName(), "{$method} {$uri} menunjuk aksi yang salah.");
        $this->assertContains('check.jabatan:'.$kunci, $middleware, "{$method} {$uri} tidak dijaga {$kunci}.");
        $this->assertContains('visitor.tipe-tamu:B2B', $middleware, "{$method} {$uri} tidak menetapkan tipe B2B.");
    }

    public static function ruteB2BProvider(): array
    {
        $reg = 'App\Http\Controllers\VISITOR\Guest\Visit\GuestRegistrationController@';
        $ver = 'App\Http\Controllers\VISITOR\Workflow\Verification\VerifikatorVisitorController@';
        $lap = 'App\Http\Controllers\VISITOR\Workflow\Reporting\LaporanVisitorController@';

        return [
            ['GET', '/transaksi-tamu-b2b', $reg.'index', 'visitorTamuB2BPage'],
            ['POST', '/transaksi-tamu-b2b', $reg.'store', 'visitorTamuB2BPage'],
            ['GET', '/transaksi-tamu-b2b/10', $reg.'show', 'visitorTamuB2BPage'],
            ['POST', '/transaksi-tamu-b2b/cancel/10', $reg.'cancel', 'visitorTamuB2BPage'],
            ['GET', '/transaksi-tamu-b2b-stats', $reg.'getStats', 'visitorTamuB2BPage'],
            ['GET', '/transaksi-tamu-b2b-list', $reg.'getList', 'visitorTamuB2BPage'],
            ['GET', '/transaksi-tamu-b2b-master', $reg.'getMasterData', 'visitorTamuB2BPage'],
            ['GET', '/verifikator-visitor/b2b', $ver.'indexTamu', 'visitorVerifikatorTamuB2BPage'],
            ['GET', '/verifikator-b2b-stats', $ver.'getStats', 'visitorVerifikatorTamuB2BPage'],
            ['GET', '/verifikator-b2b-list', $ver.'getTamuList', 'visitorVerifikatorTamuB2BPage'],
            ['GET', '/verifikator-b2b/10', $ver.'showTamu', 'visitorVerifikatorTamuB2BPage'],
            ['POST', '/verifikator-b2b/VSR-1/approve', $ver.'approveTamu', 'visitorVerifikatorTamuB2BPage'],
            ['POST', '/verifikator-b2b/VSR-1/reject', $ver.'rejectTamu', 'visitorVerifikatorTamuB2BPage'],
            ['POST', '/verifikator-b2b/VSR-1/revoke', $ver.'revokeTamu', 'visitorVerifikatorTamuB2BPage'],
            ['GET', '/monitoring-b2b', $lap.'indexTamu', 'visitorMonitoringTamuB2BPage'],
            ['GET', '/laporanvisitor-b2b-tamu', $lap.'getLaporanTamu', 'visitorMonitoringTamuB2BPage'],
            ['GET', '/laporanvisitor-b2b-detail-tamu/10', $lap.'getDetailTamu', 'visitorMonitoringTamuB2BPage'],
            ['GET', '/laporanvisitor-b2b-papan-harian', $lap.'getPapanHarian', 'visitorMonitoringTamuB2BPage'],
            ['GET', '/laporanvisitor-b2b-papan-tamu', $lap.'getPapanTamu', 'visitorMonitoringTamuB2BPage'],
            ['GET', '/laporanvisitor-b2b-antrian', $lap.'getAntrianApproval', 'visitorMonitoringTamuB2BPage'],
        ];
    }

    /**
     * URL lama menjadi Regular SAJA (DDL-2026-121); tanpa penetapan tipe,
     * penyaringnya memang membaca REGULAR, tetapi penetapan eksplisit mencegah
     * rute ini ikut berubah bila bawaan itu kelak diubah.
     *
     * @dataProvider ruteRegularProvider
     */
    public function test_regular_tamu_routes_are_pinned_to_regular(string $method, string $uri): void
    {
        $route = app('router')->getRoutes()->match(Request::create($uri, $method));

        $this->assertContains('visitor.tipe-tamu:REGULAR', $route->gatherMiddleware(), "{$method} {$uri} tidak menetapkan tipe REGULAR.");
    }

    public static function ruteRegularProvider(): array
    {
        return [
            ['POST', '/transaksitamu'],
            ['GET', '/transaksitamu-list'],
            ['POST', '/transaksitamu/cancel/10'],
            ['GET', '/verifikator-tamu-list'],
            ['POST', '/verifikator-tamu/VSR-1/approve'],
            ['POST', '/verifikator-tamu/VSR-1/revoke'],
            ['GET', '/laporanvisitor-tamu'],
        ];
    }

    public function test_guest_detail_for_permit_desk_is_guarded_by_permit_keys(): void
    {
        $route = app('router')->getRoutes()->match(Request::create('/laporanvisitor-permit-tamu/10', 'GET'));

        $this->assertSame('App\Http\Controllers\VISITOR\Workflow\Reporting\LaporanVisitorController@getDetailTamuPermit', $route->getActionName());
        $this->assertContains(
            'check.jabatan:visitorMonitoringPermitPage,visitorVerifikatorPermitPage',
            $route->gatherMiddleware(),
        );
    }

    public function test_legacy_approval_mutations_resolve_to_retirement_handler(): void
    {
        $route = app('router')->getRoutes()->match(Request::create('/approvalvisitor-approve/PMT-1', 'POST'));
        $this->assertSame('Closure', $route->getActionName());
    }
}
