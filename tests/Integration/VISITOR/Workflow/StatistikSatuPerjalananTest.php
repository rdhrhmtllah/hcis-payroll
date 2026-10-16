<?php

namespace Tests\Integration\VISITOR\Workflow;

use App\Http\Controllers\VISITOR\Permit\VehiclePermit\VehiclePermitController;
use App\Http\Controllers\VISITOR\Workflow\Reporting\LaporanVisitorController;
use App\Http\Controllers\VISITOR\Workflow\Verification\VerifikatorVisitorController;
use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kartu statistik diambil dalam SATU perjalanan, bukan satu per angka.
 *
 * Basis datanya jauh: latensi terukur ±65 ms per kueri, jadi yang menentukan
 * biaya sebuah layar adalah JUMLAH kueri, bukan lamanya satu kueri. Bentuk
 * "satu COUNT per kartu" memindai populasi yang sama berulang kali dan
 * menghabiskan ratusan milidetik hanya untuk menunggu jaringan.
 *
 * Penjaga ini menagih batas jumlah kueri, bukan waktunya. Waktu berayun
 * mengikuti mesin dan jaringan — ambang berbasis waktu akan berkedip merah
 * tanpa sebab, lalu diabaikan orang, lalu tidak menjaga apa pun. Jumlah kueri
 * deterministik.
 */
class StatistikSatuPerjalananTest extends TestCase
{
    use DatabaseTransactions;

    private function user(): User
    {
        $user = User::find(DB::table('Karyawan')->where('Kode_Karyawan', 'C9')->value('UserID_Web'));
        $this->assertNotNull($user, 'Karyawan C9 tidak punya baris login; fixture perlu ditinjau.');

        return $user;
    }

    /**
     * Permintaan yang pemakainya SUDAH diselesaikan.
     *
     * setUserResolver() menerima closure, dan closure itu baru dieksekusi saat
     * $request->user() dipanggil di dalam controller — yaitu di tengah blok
     * yang sedang diukur. Akibatnya tiga kueri milik fixture (Karyawan ->
     * KPI_Users -> Karyawan) ikut terhitung sebagai biaya endpoint, dan
     * ambangnya menagih sesuatu yang bukan miliknya.
     *
     * Menaikkan ambang akan membuat uji ini hijau tanpa menjaga apa pun.
     * Pemakainya diselesaikan lebih dulu supaya yang terhitung benar-benar
     * kerja endpointnya.
     */
    private function permintaan(string $uri, array $query = []): Request
    {
        $user = $this->user();
        $request = Request::create($uri, 'GET', $query);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    /**
     * @param  callable():mixed  $aksi
     * @return array{0:int,1:mixed}
     */
    private function hitungKueri(callable $aksi): array
    {
        $n = 0;
        DB::listen(function () use (&$n) {
            $n++;
        });

        $hasil = $aksi();

        return [$n, $hasil];
    }

    public function test_statistik_permit_karyawan_tidak_menembak_satu_kueri_per_kartu(): void
    {
        $request = $this->permintaan('/transaksipermit-stats');

        [$kueri, $respons] = $this->hitungKueri(
            fn () => app(VehiclePermitController::class)->getStats($request),
        );

        $isi = json_decode($respons->getContent(), true);

        $this->assertTrue($isi['success'] ?? false, 'Statistik permit gagal dimuat.');
        /*
         * Lima: identitas pemohon (1) + SATU kueri untuk keempat kartunya (1) +
         * tiga BATAS yang masing-masing punya definisinya sendiri di
         * VisitorPermitQuotaService (jatah permit, pengajuan menunggu
         * keputusan, akses inspeksi harian).
         *
         * Yang dijaga tetap sama: kartunya tidak boleh kembali menembak satu
         * COUNT per kartu (bentuk lamanya 6 kueri). Ketiga batas itu memang
         * kueri terpisah dan dibiarkan begitu — menggabungkannya berarti
         * menyalin ulang definisi kuota di luar service-nya, dan dua definisi
         * yang berbeda tipis membuat layar berkata "2/2 terpakai" sementara
         * penjaganya menghitung 1.
         */
        $this->assertLessThanOrEqual(
            5,
            $kueri,
            "Statistik permit kembali menembak satu kueri per kartu ({$kueri} kueri).",
        );
    }

    public function test_statistik_verifikator_cukup_satu_kueri_per_tabel(): void
    {
        [$kueri, $respons] = $this->hitungKueri(
            // Tanpa atribut tipe = rute Regular (DDL-2026-121).
            fn () => app(VerifikatorVisitorController::class)->getStats(\Illuminate\Http\Request::create('/verifikator-visitor-stats')),
        );

        $isi = json_decode($respons->getContent(), true);

        $this->assertTrue($isi['success'] ?? false, 'Statistik verifikator gagal dimuat.');
        // Enam angka dari dua tabel. Bentuk lamanya 6 kueri; sekarang 2 —
        // satu per tabel, plus overhead sesi.
        $this->assertLessThanOrEqual(
            3,
            $kueri,
            "Statistik verifikator kembali menembak satu kueri per angka ({$kueri} kueri).",
        );
    }

    public function test_laporan_permit_menghitung_kartunya_sekali_jalan(): void
    {
        $request = $this->permintaan('/laporanvisitor-permit', ['limit' => 20]);

        [$kueri, $respons] = $this->hitungKueri(
            fn () => app(LaporanVisitorController::class)->getLaporanPermit($request),
        );

        $isi = json_decode($respons->getContent(), true);

        $this->assertTrue($isi['success'] ?? false);
        $this->assertArrayHasKey('stats', $isi);
        // Daftar + total + kartu + hak revoke. Bentuk lamanya 7 kueri.
        $this->assertLessThanOrEqual(
            5,
            $kueri,
            "Laporan permit kembali menembak satu kueri per kartu ({$kueri} kueri).",
        );
    }

    /**
     * Angka kartu WAJIB sama dengan bentuk lamanya.
     *
     * Risiko sebenarnya dari agregasi bersyarat bukan kecepatan, melainkan arti
     * yang menyimpang diam-diam: satu `CASE WHEN` yang salah batas menghasilkan
     * angka yang tetap masuk akal dan tidak pernah dipertanyakan. Penjaga ini
     * menghitung ulang dengan cara lama lalu membandingkannya.
     */
    public function test_angka_kartu_laporan_permit_identik_dengan_hitungan_terpisah(): void
    {
        $permitQuery = app(\App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService::class);
        $status = $permitQuery->permitStatusSql();
        // Definisi yang sama dengan layar: izin yang PERNAH hidup (audit M7) dan
        // batas akhir berjendela jam lebih dulu (audit ulang 28 Sep).
        $akhir = 'COALESCE(d.Waktu_Berakhir, DATEADD(day, 1, CAST(d.Tanggal_Kadaluarsa AS datetime2(0))))';

        $dasar = fn () => DB::table('N_EMI_VISITOR_Transaksi_Permit as h')
            ->leftJoin('N_EMI_VISITOR_Transaksi_Permit_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->where('h.Flag_Aktif', 'Y')
            ->whereRaw("({$status} IN ('APPROVED','REVOKED') OR ({$status} = 'EXPIRED' AND h.Flag_Approval = 'Y'))");

        $berlaku = fn () => $dasar()->whereRaw('NOT '.$permitQuery->dicabutSql());

        $terpisah = [
            'permit_aktif' => $berlaku()->whereRaw("{$akhir} >= GETDATE()")->count(),
            'hampir_habis' => $berlaku()
                ->whereRaw("{$akhir} >= GETDATE()")
                ->whereRaw("{$akhir} <= DATEADD(day, 30, GETDATE())")
                ->count(),
            'kadaluarsa' => $berlaku()->whereRaw("{$akhir} < GETDATE()")->count(),
            'dicabut' => $dasar()->whereRaw($permitQuery->dicabutSql())->count(),
        ];

        $request = $this->permintaan('/laporanvisitor-permit', ['limit' => 1]);
        $stats = json_decode(
            app(LaporanVisitorController::class)->getLaporanPermit($request)->getContent(),
            true,
        )['stats'];

        foreach ($terpisah as $kunci => $nilai) {
            $this->assertSame(
                $nilai,
                (int) $stats[$kunci],
                "Kartu '{$kunci}' menyimpang dari hitungan terpisahnya.",
            );
        }
    }
}
