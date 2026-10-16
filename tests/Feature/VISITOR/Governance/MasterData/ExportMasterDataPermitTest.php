<?php

namespace Tests\Feature\VISITOR\Governance\MasterData;

use App\Http\Requests\VISITOR\Governance\MasterData\ExportVisitorMasterDataRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Gerbang dan kontrak endpoint export master data permit.
 *
 * Menggerbangi halamannya saja hanya menyembunyikan tombolnya: berkas berisi
 * SELURUH master permit tetap bisa ditarik langsung dari endpointnya, jadi
 * kuncinya harus sama persis dengan kunci halamannya.
 */
class ExportMasterDataPermitTest extends TestCase
{
    private const KUNCI = 'visitorMasterDataPermitPage';

    public static function jalurExport(): array
    {
        return [
            'pratinjau' => ['/api/v1/visitor/master-data/export/permit/pratinjau'],
            'unduh' => ['/api/v1/visitor/master-data/export/permit'],
        ];
    }

    /**
     * @dataProvider jalurExport
     */
    public function test_endpoint_export_dijaga_kunci_halaman_permit(string $jalur): void
    {
        $rute = Route::getRoutes()->match(Request::create($jalur, 'GET'));
        $middleware = $rute->gatherMiddleware();

        $this->assertContains('auth', $middleware);
        $this->assertContains('check.jabatan:'.self::KUNCI, $middleware);
        $this->assertSame('permit', $rute->defaults['domain'] ?? null);
    }

    public function test_pratinjau_tidak_menimpa_rute_master_mana_pun(): void
    {
        // 'export' hidup di ruas yang sama dengan kunci master. Kalau suatu
        // saat ada master bernama 'export', salah satu rute akan menimpa yang
        // lain dan seluruh jalurnya lenyap tanpa galat — hanya 404 saat
        // dipanggil.
        $rute = Route::getRoutes()->match(Request::create('/api/v1/visitor/master-data/export/permit', 'GET'));

        $this->assertSame(
            'App\Http\Controllers\VISITOR\Governance\MasterData\VisitorMasterDataController@exportUnduh',
            $rute->getActionName(),
        );
    }

    public function test_parameter_halaman_tidak_memotong_berkas(): void
    {
        // Export bukan halaman. Menerima page/limit berarti berkas terpotong
        // sebesar satu halaman tabel tanpa satu pun tanda di layar.
        $rules = (new ExportVisitorMasterDataRequest)->rules();

        $this->assertArrayNotHasKey('page', $rules);
        $this->assertArrayNotHasKey('limit', $rules);
        $this->assertArrayHasKey('master', $rules);
        $this->assertArrayHasKey('search', $rules);
        $this->assertArrayHasKey('Flag_Aktif', $rules);
    }
}
