<?php

namespace Tests\Feature\VISITOR;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\VISITOR\MenegaskanPemecahanKunciHalaman;
use Tests\TestCase;

/**
 * Export Studio dipecah per dataset (DML-2026-106).
 *
 * Penjaga utamanya bukan soal tampilan, melainkan satu kegagalan yang benar-benar
 * terjadi saat pekerjaan ini dibuat: dua grup rute berparameter `{studioDataset}`
 * dengan `where()` berbeda terdaftar dengan POLA URI yang identik, sehingga grup
 * kedua MENIMPA yang pertama. Seluruh jalur tamu lenyap tanpa satu pun galat —
 * yang tersisa hanya 404 saat dipanggil.
 *
 * Karena itu yang ditagih di sini adalah RESOLUSI setiap URL, bukan keberadaan
 * rutenya: rute yang terdaftar belum tentu rute yang dipanggil.
 */
class PemisahanExportStudioTest extends TestCase
{
    use DatabaseTransactions;
    use MenegaskanPemecahanKunciHalaman;

    /** @return array<string, array{0:string,1:string,2:string,3:string}> */
    public static function petaEndpointStudio(): array
    {
        $tamu = 'visitorExportStudioTamuPage';
        $permit = 'visitorExportStudioPermitPage';

        return [
            'halaman tamu' => ['GET', 'visitor/export-studio/tamu', $tamu, ''],
            'halaman permit' => ['GET', 'visitor/export-studio/permit', $permit, ''],
            'galeri tamu' => ['GET', 'api/v1/visitor/export-studio/tamu/template', $tamu, 'tamu'],
            'galeri permit' => ['GET', 'api/v1/visitor/export-studio/permit/template', $permit, 'permit'],
            'katalog tamu' => ['GET', 'api/v1/visitor/export-studio/tamu/katalog/tamu', $tamu, 'tamu'],
            'katalog permit' => ['GET', 'api/v1/visitor/export-studio/permit/katalog/permit', $permit, 'permit'],
            'pratinjau tamu' => ['POST', 'api/v1/visitor/export-studio/tamu/pratinjau', $tamu, 'tamu'],
            'pratinjau permit' => ['POST', 'api/v1/visitor/export-studio/permit/pratinjau', $permit, 'permit'],
            'jalankan tamu' => ['POST', 'api/v1/visitor/export-studio/tamu/jalankan', $tamu, 'tamu'],
            'jalankan permit' => ['POST', 'api/v1/visitor/export-studio/permit/jalankan', $permit, 'permit'],
            'simpan template tamu' => ['POST', 'api/v1/visitor/export-studio/tamu/template', $tamu, 'tamu'],
            'hapus template permit' => ['DELETE', 'api/v1/visitor/export-studio/permit/template/ABC', $permit, 'permit'],
        ];
    }

    /** @dataProvider petaEndpointStudio */
    public function test_setiap_url_mendarat_pada_kunci_dan_dataset_yang_benar(
        string $metode,
        string $uri,
        string $kunciDiharapkan,
        string $datasetDiharapkan,
    ): void {
        $rute = Route::getRoutes()->match(Request::create('/'.$uri, $metode));

        $kunci = [];
        foreach ($rute->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'check.jabatan:')) {
                $kunci = explode(',', substr($middleware, strlen('check.jabatan:')));
            }
        }

        $this->assertSame([$kunciDiharapkan], $kunci, "Penjaga {$uri} tidak sesuai.");

        if ($datasetDiharapkan !== '') {
            $this->assertSame(
                $datasetDiharapkan,
                $rute->defaults['studioDataset'] ?? null,
                "Dataset {$uri} tidak dipaku dari rutenya; controller akan merakit laporan domain lain.",
            );
        }
    }

    public function test_tidak_ada_rute_studio_yang_masih_memakai_kunci_gabungan_lama(): void
    {
        $pelanggar = [];

        foreach (Route::getRoutes() as $rute) {
            if (! str_contains($rute->uri(), 'visitor/export-studio')) {
                continue;
            }

            foreach ($rute->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_contains($middleware, 'visitorExportStudioPage')) {
                    $pelanggar[] = $rute->uri();
                }
            }
        }

        $this->assertSame([], $pelanggar, "Rute studio masih dijaga kunci lama:\n".implode("\n", $pelanggar));
    }

    public function test_url_studio_lama_mengalihkan_bukan_hilang(): void
    {
        $pengguna = $this->penggunaStudio();

        $this->assertMengalihkanKe(
            '/visitor/export-studio/tamu',
            $this->actingAs($pengguna)->get('/visitor/export-studio')->headers->get('Location'),
        );
    }

    public function test_kedua_halaman_terdaftar_di_menu_dan_grup_fitur(): void
    {
        foreach (['visitorExportStudioTamuPage', 'visitorExportStudioPermitPage'] as $halaman) {
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
            DB::table('N_HRIS_Menus')->where('Jenis_page', 'visitorExportStudioPage')->value('Is_Show_Sidebar'),
            'Menu studio gabungan masih tampil; ada dua jalan ke pekerjaan yang sama.',
        );
    }

    public function test_pemecahan_kunci_tidak_menghilangkan_maupun_menambah_akses(): void
    {
        $this->assertPemecahanKunciTidakMerugikan('visitorExportStudioPage', ['visitorExportStudioTamuPage', 'visitorExportStudioPermitPage']);
    }

    public function test_halaman_mengunci_datasetnya_dari_server(): void
    {
        // Tombol pengganti dataset yang tetap hidup akan memanggil endpoint
        // domain lain dan dibalas 403 tanpa sebab yang terbaca pemakainya.
        $sumber = (string) file_get_contents(
            base_path('resources/js/Pages/VISITOR/Workflow/Reporting/ExportStudio/exportStudioVisitor.vue'),
        );

        $this->assertStringContainsString('datasetTerkunci', $sumber);
        $this->assertStringContainsString('v-if="!datasetTerkunci"', $sumber);
        // Penjaganya juga di dalam fungsinya, bukan hanya pada tombolnya.
        $this->assertStringContainsString('if (props.datasetTerkunci) return;', $sumber);
    }

    private function penggunaStudio(): \App\Models\ESS\Employee\Models\User
    {
        $userId = DB::table('HRIS_Page_Access')
            ->where('Jenis_page', 'visitorExportStudioTamuPage')
            ->where('Flag_Aktif', 'Y')
            ->value('UserID_Web');

        $pengguna = $userId ? \App\Models\ESS\Employee\Models\User::find($userId) : null;

        if (! $pengguna) {
            $this->markTestSkipped('Tak ada user development dengan akses Export Studio Tamu.');
        }

        return $pengguna;
    }
}
