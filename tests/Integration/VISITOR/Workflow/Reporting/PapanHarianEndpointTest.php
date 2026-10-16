<?php

namespace Tests\Integration\VISITOR\Workflow\Reporting;

use App\Http\Controllers\VISITOR\Workflow\Reporting\LaporanVisitorController;
use App\Services\VISITOR\Guest\Visit\TipeTamu;
use App\Services\VISITOR\Workflow\Reporting\Monitoring\VisitorPapanHarianService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Support\VISITOR\MelihatSeluruhKategoriTamu;
use Tests\TestCase;

class PapanHarianEndpointTest extends TestCase
{
    use DatabaseTransactions;
    use MelihatSeluruhKategoriTamu;

    private const KEDUA_KUNCI = ['visitorMonitoringTamuPage', 'visitorMonitoringPermitPage'];

    /** Permintaan ke controller dari pengguna berkunci (rute bersama memeriksa ulang ranahnya). */
    private function permintaan(array $q = [], array $kunci = self::KEDUA_KUNCI): Request
    {
        $r = Request::create('/x', 'GET', $q);
        $pengguna = $this->penggunaBerkunci($kunci);
        $r->setUserResolver(fn () => $pengguna);

        return $r;
    }

    public function test_route_papan_harian_terdaftar(): void
    {
        try {
            Route::getRoutes()->match(Request::create('/laporanvisitor-papan-harian', 'GET'));
        } catch (NotFoundHttpException) {
            $this->fail('Route papan harian tidak terdaftar.');
        }

        $this->addToAssertionCount(1);
    }

    public function test_endpoint_membalas_kelompok_dan_ringkasan(): void
    {
        $response = app(LaporanVisitorController::class)->getPapanHarian($this->permintaan());
        $isi = json_decode($response->getContent(), true)['result'] ?? [];

        $this->assertSame(200, $response->getStatusCode());

        foreach ([
            // Hanya kelompok yang PERLU DITINDAK. "Sudah keluar" dan "permit
            // berlaku hari ini" sengaja dicabut: keduanya catatan retrospektif
            // dan direktori, tempatnya di All Monitoring.
            'harus_datang',
            'menunggu_approval_hari_ini',
            'sedang_di_dalam',
            'menginap',
            'permit_kedaluwarsa_hari_ini',
            'permit_segera_kedaluwarsa',
            'ringkasan',
            'tanggal',
        ] as $kunci) {
            $this->assertArrayHasKey($kunci, $isi, "Kelompok {$kunci} hilang dari balasan.");
        }
    }

    public function test_domain_menyaring_kelompok_di_server(): void
    {
        $tamu = json_decode(
            app(LaporanVisitorController::class)
                ->getPapanHarian($this->permintaan(['domain' => 'tamu']))
                ->getContent(),
            true,
        )['result'];

        foreach (array_keys($tamu) as $kunci) {
            $this->assertStringNotContainsString('permit', $kunci);
        }
    }

    public function test_balasan_endpoint_sama_dengan_service(): void
    {
        // Endpoint yang menghitung ulang sendiri akan berselisih dengan papan
        // begitu salah satu aturan pengelompokan berubah.
        $dariService = app(VisitorPapanHarianService::class)->rakit();
        $dariEndpoint = json_decode(
            app(LaporanVisitorController::class)->getPapanHarian($this->permintaan())->getContent(),
            true,
        )['result'];

        $this->assertSame($dariService['ringkasan'], $dariEndpoint['ringkasan']);
        $this->assertSame($dariService['tanggal'], $dariEndpoint['tanggal']);
    }

    public function test_pemegang_kunci_permit_saja_tidak_bisa_membaca_tamu(): void
    {
        // Rute bersama dijaga kunci tamu ATAU permit; dulu domain=tamu (atau
        // tanpa domain) membuka nama, HP, dan plat tamu bagi pemegang kunci
        // permit saja (audit ulang 28 Sep).
        $c = app(LaporanVisitorController::class);

        $this->assertSame(403, $c->getPapanHarian($this->permintaan(['domain' => 'tamu'], ['visitorMonitoringPermitPage']))->getStatusCode());

        $tanpaDomain = json_decode($c->getPapanHarian($this->permintaan([], ['visitorMonitoringPermitPage']))->getContent(), true)['result'];
        foreach (array_keys($tanpaDomain['ringkasan']) as $kunci) {
            $this->assertStringStartsWith('permit', $kunci, "Kelompok tamu {$kunci} bocor ke pemegang kunci permit.");
        }

        $this->assertSame(403, $c->getPapanTamu($this->permintaan([], ['visitorMonitoringPermitPage']), app(\App\Services\VISITOR\Workflow\Reporting\Monitoring\VisitorPapanTamuService::class))->getStatusCode());
        $this->assertSame(403, $c->getPapanHarian($this->permintaan(['domain' => 'permit'], ['visitorMonitoringTamuPage']))->getStatusCode());
    }

    /**
     * DDL-2026-121: papan tamu per tipe. domain=tamu di rute Regular = papan
     * REGULAR; rute B2B (atribut visitor_tipe_tamu) = papan B2B.
     */
    public function test_papan_tamu_mengikuti_tipe_rute(): void
    {
        try {
            Route::getRoutes()->match(Request::create('/laporanvisitor-b2b-papan-harian', 'GET'));
        } catch (NotFoundHttpException) {
            $this->fail('Route papan harian B2B tidak terdaftar.');
        }

        $reguler = json_decode(
            app(LaporanVisitorController::class)
                ->getPapanHarian($this->permintaan(['domain' => 'tamu']))
                ->getContent(),
            true,
        )['result'];

        $permintaanB2b = $this->permintaan(['domain' => 'tamu'], ['visitorMonitoringTamuB2BPage']);
        $permintaanB2b->attributes->set(TipeTamu::ATRIBUT, TipeTamu::B2B);
        $b2b = json_decode(
            app(LaporanVisitorController::class)->getPapanHarian($permintaanB2b)->getContent(),
            true,
        )['result'];

        $service = app(VisitorPapanHarianService::class);
        $this->assertSame($service->rakit('tamu', TipeTamu::REGULAR)['ringkasan'], $reguler['ringkasan']);
        $this->assertSame($service->rakit('tamu', TipeTamu::B2B)['ringkasan'], $b2b['ringkasan']);
    }
}
