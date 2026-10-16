<?php

namespace Tests\Integration\VISITOR\Workflow\Reporting;

use App\Http\Controllers\VISITOR\Workflow\Reporting\LaporanVisitorController;
use App\Services\VISITOR\Workflow\Reporting\Dashboard\VisitorDashboardOperasionalService;
use App\Services\VISITOR\Workflow\Reporting\Monitoring\VisitorPapanTamuService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Support\VISITOR\MelihatSeluruhKategoriTamu;
use Tests\TestCase;

/**
 * Dua daftar dashboard dipotong DI SQL, bukan di PHP.
 *
 * Menarik seluruh baris lalu memotongnya dengan array_slice() menghasilkan
 * layar yang sama persis, tetapi biayanya tumbuh seiring data walau yang
 * tampil tetap sepuluh baris. Yang dijaga di sini: totalnya berasal dari
 * kueri yang sama dengan isinya, halaman kedua benar-benar berisi baris
 * yang berbeda, dan kunci urut di luar daftar putih tidak pernah sampai
 * ke ORDER BY.
 */
class PaginasiDashboardTest extends TestCase
{
    use DatabaseTransactions;
    use MelihatSeluruhKategoriTamu;

    private function permintaan(array $q = []): Request
    {
        $r = Request::create('/x', 'GET', $q);
        $pengguna = $this->penggunaBerkunci(['visitorMonitoringTamuPage', 'visitorMonitoringPermitPage']);
        $r->setUserResolver(fn () => $pengguna);

        return $r;
    }

    public function test_route_kedua_endpoint_terdaftar(): void
    {
        foreach (['/laporanvisitor-papan-tamu', '/laporanvisitor-antrian'] as $jalur) {
            try {
                Route::getRoutes()->match(Request::create($jalur, 'GET'));
            } catch (NotFoundHttpException) {
                $this->fail("Route {$jalur} tidak terdaftar.");
            }
        }

        $this->addToAssertionCount(1);
    }

    public function test_papan_tamu_membalas_baris_dan_paginasi(): void
    {
        $hasil = app(VisitorPapanTamuService::class)->daftar(['page' => 1, 'limit' => 5]);

        $this->assertArrayHasKey('baris', $hasil);
        $this->assertArrayHasKey('paginasi', $hasil);
        $this->assertLessThanOrEqual(5, count($hasil['baris']));

        foreach (['total', 'current_page', 'per_page', 'total_pages'] as $kunci) {
            $this->assertArrayHasKey($kunci, $hasil['paginasi']);
        }

        $this->assertSame(5, $hasil['paginasi']['per_page']);
    }

    public function test_status_kehadiran_hanya_tiga_sebutan_yang_dikenal_layar(): void
    {
        // Label ini juga dipakai warnaStatus() di PapanHarian.vue untuk memilih
        // warna lencana. Sebutan di luar daftar ini jatuh ke warna baku tanpa
        // satu pun error, jadi pergeserannya harus ketahuan di sini.
        $hasil = app(VisitorPapanTamuService::class)->daftar(['limit' => 100]);

        foreach ($hasil['baris'] as $baris) {
            $this->assertContains(
                $baris->Status_Kehadiran,
                ['Belum selesai', 'Di dalam', 'Belum datang'],
                'Sebutan status berubah tanpa layarnya ikut diubah.',
            );
        }

        // "Menginap" adalah sebutan lama; tamu yang belum tercatat keluar
        // sebagian besar bukan benar-benar bermalam.
        $lama = app(VisitorPapanTamuService::class)->daftar(['limit' => 100, 'cari' => 'Menginap']);
        $this->assertSame(0, $lama['paginasi']['total']);
    }

    public function test_papan_tamu_total_konsisten_dengan_isinya(): void
    {
        $service = app(VisitorPapanTamuService::class);

        $sepuluh = $service->daftar(['page' => 1, 'limit' => 10]);
        $seratus = $service->daftar(['page' => 1, 'limit' => 100]);

        // Total tidak boleh berubah karena ukuran halaman berubah; kalau
        // berubah, totalnya dihitung dari kueri yang lain.
        $this->assertSame($seratus['paginasi']['total'], $sepuluh['paginasi']['total']);
        $this->assertSame(count($seratus['baris']), min($seratus['paginasi']['total'], 100));
    }

    public function test_papan_tamu_halaman_kedua_tidak_mengulang_halaman_pertama(): void
    {
        $service = app(VisitorPapanTamuService::class);
        $pertama = $service->daftar(['page' => 1, 'limit' => 1]);

        if ($pertama['paginasi']['total'] < 2) {
            $this->markTestSkipped('Butuh minimal dua baris pada papan tamu.');
        }

        $kedua = $service->daftar(['page' => 2, 'limit' => 1]);

        $this->assertNotSame(
            $pertama['baris'][0]->No_Transaksi,
            $kedua['baris'][0]->No_Transaksi,
            'Halaman kedua mengulang baris halaman pertama — ORDER BY tidak deterministik.',
        );
    }

    public function test_papan_tamu_pencarian_mempersempit_bukan_memperluas(): void
    {
        $service = app(VisitorPapanTamuService::class);
        $semua = $service->daftar(['limit' => 100]);
        $disaring = $service->daftar(['limit' => 100, 'cari' => 'zzzzzznihiltidakada']);

        $this->assertLessThanOrEqual($semua['paginasi']['total'], $disaring['paginasi']['total']);
        $this->assertSame(0, $disaring['paginasi']['total']);
    }

    public function test_kunci_urut_asing_jatuh_ke_baku_tanpa_error(): void
    {
        // Kalau nilai permintaan lolos ke ORDER BY, kueri ini akan meledak.
        $hasil = app(VisitorPapanTamuService::class)->daftar([
            'urut' => '1; DROP TABLE Karyawan --',
            'arah' => 'menyamping',
            'limit' => 3,
        ]);

        $this->assertArrayHasKey('baris', $hasil);
    }

    public function test_antrian_membalas_baris_dan_paginasi(): void
    {
        $hasil = app(VisitorDashboardOperasionalService::class)->antrianBerhalaman(['limit' => 5]);

        $this->assertArrayHasKey('baris', $hasil);
        $this->assertLessThanOrEqual(5, count($hasil['baris']));

        foreach ($hasil['baris'] as $baris) {
            // Identitas kini kolom SQL, bukan tempelan sesudah pemotongan —
            // tanpa itu pencarian dan pengurutan tidak bisa menyentuhnya.
            foreach (['no_transaksi', 'jenis', 'judul', 'pemohon', 'approver', 'umur_menit'] as $kunci) {
                $this->assertArrayHasKey($kunci, $baris);
            }

            $this->assertContains($baris['jenis'], ['TAMU', 'PERMIT']);
        }
    }

    public function test_antrian_total_sama_dengan_pending_pada_ringkasan(): void
    {
        $service = app(VisitorDashboardOperasionalService::class);

        $ringkasan = $service->ringkasan();
        $halaman = $service->antrianBerhalaman(['limit' => 1]);

        // Dua jalur berbeda menuju angka yang sama. Kalau berselisih, salah
        // satunya menghitung baris kedaluwarsa yang bukan bagian antrean.
        $this->assertSame(
            (int) $ringkasan['antrian']['pending'],
            (int) $halaman['paginasi']['total'],
        );
    }

    public function test_antrian_domain_menyaring_di_server(): void
    {
        $service = app(VisitorDashboardOperasionalService::class);

        foreach (['tamu' => 'TAMU', 'permit' => 'PERMIT'] as $domain => $jenis) {
            $hasil = $service->antrianBerhalaman(['limit' => 100], $domain);

            foreach ($hasil['baris'] as $baris) {
                $this->assertSame($jenis, $baris['jenis'], "Ranah {$domain} kebocoran baris {$baris['jenis']}.");
            }
        }
    }

    public function test_antrian_endpoint_membungkus_hasil_di_result(): void
    {
        $response = app(LaporanVisitorController::class)->getAntrianApproval(
            $this->permintaan(['limit' => 3]),
            app(VisitorDashboardOperasionalService::class),
        );

        $isi = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertArrayHasKey('baris', $isi['result'] ?? []);
        $this->assertArrayHasKey('paginasi', $isi['result'] ?? []);
    }

    public function test_papan_tamu_endpoint_membungkus_hasil_di_result(): void
    {
        $response = app(LaporanVisitorController::class)->getPapanTamu(
            $this->permintaan(['limit' => 3]),
            app(VisitorPapanTamuService::class),
        );

        $isi = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertArrayHasKey('baris', $isi['result'] ?? []);
        $this->assertArrayHasKey('paginasi', $isi['result'] ?? []);
    }
}
