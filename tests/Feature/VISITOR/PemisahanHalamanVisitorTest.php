<?php

namespace Tests\Feature\VISITOR;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\VISITOR\MenegaskanPemecahanKunciHalaman;
use Tests\TestCase;

/**
 * Monitoring dan Verifikator dipecah per domain (DML-2026-105).
 *
 * Yang dijaga berkas ini bukan tampilannya, melainkan hal-hal yang gagal
 * DIAM-DIAM:
 *
 *  1. Pemisahannya nyata sampai ke data. Endpoint yang masih dijaga kunci lama
 *     — atau dijaga kunci domain yang salah — membuat admin yang hanya diberi
 *     satu domain tetap bisa menarik data domain lain, dan tidak ada galat yang
 *     menyalakannya.
 *  2. URL lamanya mengalihkan, bukan 404. Notifikasi yang sudah terkirim,
 *     bookmark, dan papan harian semuanya menautkan URL lama.
 *  3. Menu lamanya benar-benar hilang dari sidebar, dan hak akses lamanya TIDAK
 *     dihapus — ia satu-satunya catatan siapa yang dulu berhak.
 */
class PemisahanHalamanVisitorTest extends TestCase
{
    use DatabaseTransactions;
    use MenegaskanPemecahanKunciHalaman;

    /** @return array<string, array{0:string,1:array<int,string>}> */
    public static function petaPenjagaRute(): array
    {
        return [
            // Halaman
            'monitoring-tamu' => ['monitoring-tamu', ['visitorMonitoringTamuPage']],
            'monitoring-permit' => ['monitoring-permit', ['visitorMonitoringPermitPage']],
            'verifikator permit' => ['verifikator-visitor/permit', ['visitorVerifikatorPermitPage']],
            'verifikator tamu' => ['verifikator-visitor/tamu', ['visitorVerifikatorTamuPage']],

            // Data per domain
            'daftar tamu' => ['laporanvisitor-tamu', ['visitorMonitoringTamuPage']],
            'daftar permit' => ['laporanvisitor-permit', ['visitorMonitoringPermitPage']],
            'antrean verifikator permit' => ['verifikator-permit-list', ['visitorVerifikatorPermitPage']],
            'antrean verifikator tamu' => ['verifikator-tamu-list', ['visitorVerifikatorTamuPage']],

            // Dibaca kedua domain
            'papan harian' => ['laporanvisitor-papan-harian', ['visitorMonitoringTamuPage', 'visitorMonitoringPermitPage']],
            'antrean keputusan' => ['laporanvisitor-antrian', ['visitorMonitoringTamuPage', 'visitorMonitoringPermitPage']],
            'statistik verifikator' => ['verifikator-visitor-stats', ['visitorVerifikatorPermitPage', 'visitorVerifikatorTamuPage']],

            // Tamu B2B (DDL-2026-121): kunci halamannya sendiri. Endpoint
            // Regular TIDAK boleh memakai kunci ini, dan sebaliknya.
            'pengajuan tamu b2b' => ['transaksi-tamu-b2b', ['visitorTamuB2BPage']],
            'daftar pengajuan tamu b2b' => ['transaksi-tamu-b2b-list', ['visitorTamuB2BPage']],
            'monitoring tamu b2b' => ['monitoring-b2b', ['visitorMonitoringTamuB2BPage']],
            'daftar monitoring tamu b2b' => ['laporanvisitor-b2b-tamu', ['visitorMonitoringTamuB2BPage']],
            'papan harian b2b' => ['laporanvisitor-b2b-papan-harian', ['visitorMonitoringTamuB2BPage']],
            'verifikator tamu b2b' => ['verifikator-visitor/b2b', ['visitorVerifikatorTamuB2BPage']],
            'antrean verifikator tamu b2b' => ['verifikator-b2b-list', ['visitorVerifikatorTamuB2BPage']],
            'statistik verifikator b2b' => ['verifikator-b2b-stats', ['visitorVerifikatorTamuB2BPage']],
            'aksi verifikator tamu b2b' => ['verifikator-b2b/{noTransaksi}/approve', ['visitorVerifikatorTamuB2BPage']],

            // Kunjungan asal sebuah permit, dibaca meja & monitoring PERMIT.
            'tamu asal permit' => ['laporanvisitor-permit-tamu/{id}', ['visitorMonitoringPermitPage', 'visitorVerifikatorPermitPage']],
        ];
    }

    /** @return array<string, array{0:string,1:string}> */
    public static function petaTipeRute(): array
    {
        return [
            'pengajuan regular' => ['transaksitamu-list', 'REGULAR'],
            'verifikator regular' => ['verifikator-tamu-list', 'REGULAR'],
            'aksi verifikator regular' => ['verifikator-tamu/{noTransaksi}/approve', 'REGULAR'],
            'monitoring regular' => ['laporanvisitor-tamu', 'REGULAR'],
            'pengajuan b2b' => ['transaksi-tamu-b2b-list', 'B2B'],
            'verifikator b2b' => ['verifikator-b2b-list', 'B2B'],
            'aksi verifikator b2b' => ['verifikator-b2b/{noTransaksi}/revoke', 'B2B'],
            'monitoring b2b' => ['laporanvisitor-b2b-tamu', 'B2B'],
        ];
    }

    /**
     * Tipe tamu DITETAPKAN RUTE (DDL-2026-121). Rute yang lupa middleware ini
     * dibaca REGULAR diam-diam — halaman B2B lalu menampilkan data Regular.
     *
     * @dataProvider petaTipeRute
     */
    public function test_rute_tamu_menetapkan_tipe_lewat_middleware(string $uri, string $tipe): void
    {
        $this->assertContains(
            'visitor.tipe-tamu:'.$tipe,
            $this->middlewareRute($uri),
            "Rute {$uri} tidak menetapkan tipe tamu {$tipe}.",
        );
    }

    /**
     * @param  array<int,string>  $kunciDiharapkan
     *
     * @dataProvider petaPenjagaRute
     */
    public function test_rute_dijaga_kunci_domainnya(string $uri, array $kunciDiharapkan): void
    {
        $this->assertSame($kunciDiharapkan, $this->kunciPenjaga($uri), "Penjaga rute {$uri} tidak sesuai.");
    }

    public function test_tidak_ada_rute_yang_masih_memakai_kunci_gabungan_lama(): void
    {
        // Kunci lama masih ada di HRIS_Page_Access sebagai catatan, tetapi tidak
        // boleh lagi menjaga satu rute pun: pemegangnya yang belum dipindahkan
        // akan diam-diam tetap punya akses penuh ke kedua domain.
        $pelanggar = [];

        foreach (Route::getRoutes() as $rute) {
            foreach ($rute->gatherMiddleware() as $middleware) {
                if (! is_string($middleware) || ! str_starts_with($middleware, 'check.jabatan:')) {
                    continue;
                }

                $kunci = explode(',', substr($middleware, strlen('check.jabatan:')));

                if (array_intersect($kunci, ['visitorLaporanPage', 'visitorVerifikatorPage'])) {
                    $pelanggar[] = $rute->uri();
                }
            }
        }

        $this->assertSame([], $pelanggar, "Rute masih dijaga kunci gabungan lama:\n".implode("\n", $pelanggar));
    }

    public function test_url_lama_mengalihkan_bukan_hilang(): void
    {
        // Papan harian, notifikasi, dan bookmark semuanya menautkan URL lama.
        $this->assertSame('/monitoring-tamu', $this->tujuanPengalihan('laporanvisitor'));
        $this->assertSame('/verifikator-visitor/permit', $this->tujuanPengalihan('verifikator-visitor'));
    }

    public function test_pengalihan_verifikator_menghormati_jenis_pada_tautan_notifikasi(): void
    {
        // Notifikasi menautkan ?detail=<token>&jenis=<permit|tamu>. Tanpa ini,
        // setiap tautan tamu mendarat di meja permit dan detailnya tidak pernah
        // terbuka — tanpa satu pun pesan yang menjelaskan sebabnya.
        $tujuan = $this->tujuanPengalihan('verifikator-visitor?detail=abc123&jenis=tamu');

        $this->assertStringStartsWith('/verifikator-visitor/tamu', $tujuan);
        $this->assertStringContainsString('detail=abc123', $tujuan);
    }

    public function test_keempat_halaman_terdaftar_di_menu_dan_grup_fitur(): void
    {
        foreach ([
            'visitorMonitoringTamuPage',
            'visitorMonitoringPermitPage',
            'visitorVerifikatorPermitPage',
            'visitorVerifikatorTamuPage',
        ] as $halaman) {
            $this->assertTrue(
                DB::table('N_HRIS_Menus')->where('Jenis_page', $halaman)->where('Is_Show_Sidebar', 'Y')->exists(),
                "Menu {$halaman} belum terdaftar atau tidak tampil di sidebar.",
            );
            $this->assertTrue(
                DB::table('N_HRIS_Grup_Fitur_Map')->where('Jenis_page', $halaman)->where('Flag_Aktif', 'Y')->exists(),
                "Pemetaan grup fitur {$halaman} belum ada; menunya tidak akan pernah muncul.",
            );
        }
    }

    public function test_ketiga_halaman_b2b_terdaftar_di_menu_dan_grup_fitur(): void
    {
        // DDL-2026-121: halaman B2B dikunci per orang (Flag_Hibah_Role='T').
        foreach ([
            'visitorTamuB2BPage',
            'visitorVerifikatorTamuB2BPage',
            'visitorMonitoringTamuB2BPage',
        ] as $halaman) {
            $this->assertTrue(
                DB::table('N_HRIS_Menus')->where('Jenis_page', $halaman)->exists(),
                "Menu {$halaman} belum terdaftar; jalankan DML DDL-2026-121 lebih dulu.",
            );
            $this->assertSame(
                'T',
                DB::table('N_HRIS_Grup_Fitur_Map')->where('Jenis_page', $halaman)->where('Flag_Aktif', 'Y')->value('Flag_Hibah_Role'),
                "Pemetaan grup fitur {$halaman} belum ada atau tidak dikunci per orang.",
            );
        }
    }

    public function test_menu_gabungan_lama_hilang_dari_sidebar(): void
    {
        foreach (['visitorLaporanPage', 'visitorVerifikatorPage'] as $halaman) {
            $this->assertSame(
                'T',
                DB::table('N_HRIS_Menus')->where('Jenis_page', $halaman)->value('Is_Show_Sidebar'),
                "Menu {$halaman} masih tampil; ada dua jalan ke pekerjaan yang sama.",
            );
        }
    }

    public function test_pemecahan_kunci_tidak_menghilangkan_maupun_menambah_akses(): void
    {
        // Diturunkan dari data, bukan daftar user yang ditulis tangan: satu
        // orang yang terlewat kehilangan halamannya tanpa pemberitahuan.
        foreach ([
            'visitorLaporanPage' => ['visitorMonitoringTamuPage', 'visitorMonitoringPermitPage'],
            'visitorVerifikatorPage' => ['visitorVerifikatorPermitPage', 'visitorVerifikatorTamuPage'],
        ] as $lama => $baruSemua) {
            $this->assertPemecahanKunciTidakMerugikan($lama, $baruSemua);
        }
    }

    public function test_hak_akses_lama_tidak_dihapus(): void
    {
        // Ia satu-satunya catatan siapa yang dulu berhak; menghapusnya membuat
        // pembatalan pemecahan ini mustahil.
        foreach (['visitorLaporanPage', 'visitorVerifikatorPage'] as $halaman) {
            $this->assertTrue(
                DB::table('HRIS_Page_Access')->where('Jenis_page', $halaman)->where('Flag_Aktif', 'Y')->exists(),
                "Catatan hak akses {$halaman} ikut terhapus.",
            );
        }
    }

    /** @return array<int,mixed> */
    private function middlewareRute(string $uri): array
    {
        foreach (Route::getRoutes() as $rute) {
            if ($rute->uri() === $uri) {
                return $rute->gatherMiddleware();
            }
        }

        $this->fail("Rute {$uri} tidak terdaftar.");
    }

    /** @return array<int,string> */
    private function kunciPenjaga(string $uri): array
    {
        foreach (Route::getRoutes() as $rute) {
            if ($rute->uri() !== $uri) {
                continue;
            }

            foreach ($rute->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'check.jabatan:')) {
                    return explode(',', substr($middleware, strlen('check.jabatan:')));
                }
            }

            return [];
        }

        $this->fail("Rute {$uri} tidak terdaftar.");
    }

    private function tujuanPengalihan(string $uri): string
    {
        $user = DB::table('HRIS_Page_Access')
            ->where('Jenis_page', 'visitorMonitoringTamuPage')
            ->where('Flag_Aktif', 'Y')
            ->value('UserID_Web');

        $pengguna = $user ? \App\Models\ESS\Employee\Models\User::find($user) : null;

        if (! $pengguna) {
            $this->markTestSkipped('Tak ada user development dengan akses monitoring tamu.');
        }

        return $this->pathPengalihan($this->actingAs($pengguna)->get('/'.$uri)->headers->get('Location'));
    }
}
