<?php

namespace Tests\Integration\VISITOR\Workflow\Reporting;

use App\Http\Controllers\VISITOR\Workflow\Reporting\VisitorDashboardController;
use App\Services\VISITOR\Guest\Visit\TipeTamu;
use App\Services\VISITOR\Workflow\Reporting\Dashboard\VisitorDashboardAnalitikService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\Support\VISITOR\MelihatSeluruhKategoriTamu;
use Tests\TestCase;

/**
 * Grafik halaman Monitoring Tamu Regular dan B2B dihitung per tipe, dan ranah
 * analitik dijaga kunci halamannya (audit ulang 28 Sep).
 */
class AnalitikPerTipeTamuTest extends TestCase
{
    use DatabaseTransactions;
    use MelihatSeluruhKategoriTamu;

    public function test_tren_regular_dan_b2b_menjumlah_ke_tren_gabungan(): void
    {
        $svc = app(VisitorDashboardAnalitikService::class);
        [$dari, $sampai] = [now()->subDays(30)->toDateString(), now()->toDateString()];

        $jumlah = fn (array $tren) => array_sum(array_column($tren, 'transaksi'));
        $gabungan = $jumlah($svc->tren($dari, $sampai));
        $regular = $jumlah($svc->tren($dari, $sampai, TipeTamu::REGULAR));
        $b2b = $jumlah($svc->tren($dari, $sampai, TipeTamu::B2B));

        $this->assertSame($gabungan, $regular + $b2b);
        if ($regular > 0 && $b2b > 0) {
            $this->assertLessThan($gabungan, $regular, 'Grafik Regular masih memuat tamu B2B.');
        }
    }

    public function test_ranah_analitik_dijaga_kunci_halaman(): void
    {
        $c = app(VisitorDashboardController::class);
        $svc = app(VisitorDashboardAnalitikService::class);

        $permintaan = function (array $q, array $kunci) {
            $r = Request::create('/x', 'GET', $q + ['segarkan' => 1]);
            $pengguna = $this->penggunaBerkunci($kunci);
            $r->setUserResolver(fn () => $pengguna);

            return $r;
        };

        $this->assertSame(403, $c->analitik($permintaan(['domain' => 'tamu'], ['visitorMonitoringPermitPage']), $svc)->getStatusCode());
        $this->assertSame(403, $c->analitik($permintaan(['domain' => 'tamu', 'tipe' => 'B2B'], ['visitorMonitoringTamuPage']), $svc)->getStatusCode());
        $this->assertSame(200, $c->analitik($permintaan(['domain' => 'tamu', 'tipe' => 'B2B'], ['visitorMonitoringTamuB2BPage']), $svc)->getStatusCode());
        $this->assertSame(200, $c->analitik($permintaan(['domain' => 'permit'], ['visitorMonitoringPermitPage']), $svc)->getStatusCode());
    }
}
