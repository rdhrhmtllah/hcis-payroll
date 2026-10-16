<?php

namespace Tests\Feature\VISITOR\Workflow\Reporting;

use App\Http\Controllers\VISITOR\Workflow\Reporting\LaporanVisitorController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * `boleh_revoke` harus dijawab dengan kunci halaman yang sama dengan penjaga
 * rute revoke. Dulu memakai kunci lama `visitorVerifikatorPage` (dipensiunkan
 * saat meja dipecah), jadi tombol tampil untuk yang ditolak rutenya dan hilang
 * untuk yang justru berhak.
 */
class BolehRevokeSelarasRuteTest extends TestCase
{
    public function test_kunci_boleh_revoke_sama_dengan_penjaga_rute_revoke(): void
    {
        $kunci = (new \ReflectionClassConstant(LaporanVisitorController::class, 'HALAMAN_VERIFIKATOR'))->getValue();

        foreach (['verifikator-permit/{noTransaksi}/revoke', 'verifikator-permit-bulk-revoke'] as $uri) {
            $rute = collect(Route::getRoutes()->getRoutes())->first(fn ($r) => $r->uri() === $uri);
            $this->assertNotNull($rute, "Rute {$uri} tidak ditemukan.");

            $jabatan = collect($rute->gatherMiddleware())
                ->filter(fn ($m) => is_string($m) && str_starts_with($m, 'check.jabatan:'))
                ->flatMap(fn ($m) => explode(',', substr($m, strlen('check.jabatan:'))))
                ->all();

            $this->assertContains($kunci, $jabatan, "boleh_revoke memakai '{$kunci}', rute {$uri} dijaga ".implode(',', $jabatan));
        }
    }
}
