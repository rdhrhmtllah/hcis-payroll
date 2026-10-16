<?php

namespace Tests\Feature\ESS\Attendance;

use App\Http\Controllers\ESS\Attendance\Leave\Personal\CutiController;
use App\Models\ESS\Employee\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Bug: widget "Pengajuan Pending" di /cuti selalu 0.
 *
 * Sebabnya di sisi klien: nilainya dibaca dari `riwayatData.total`, sedangkan
 * loadRiwayat() hanya pernah mengisi `riwayatData.data` dan
 * `riwayatData.pagination`. `total` tak pernah ada → `undefined || 0` → 0
 * selamanya, berapa pun pengajuan yang menggantung.
 *
 * Fix-nya memindahkan perhitungan ke server (`/api/v1/cuti/check-saldo`), memakai
 * definisi PENDING yang sama dengan saringan `status_filter=pending` di
 * getRiwayat(). Penjaga ini mengunci dua hal yang membuat bug itu bisa hidup lagi:
 *
 *   1. hitungan berasal dari SELURUH pengajuan milik pengguna, bukan satu
 *      halaman paginasi — perbedaan inilah yang bikin angka klien tak pernah
 *      bisa dipercaya;
 *   2. payload check-saldo benar-benar membawa kunci `pending`, karena tanpa
 *      kunci itu klien jatuh ke 0 tanpa suara sama sekali.
 *
 * Env-gated: butuh DB development karena membaca N_HRIS_VW_All_Transactions.
 *   RUN_REAL_CUTI_ENGINE_TEST=true php artisan test --filter CutiWidgetPengajuanPending
 */
class CutiWidgetPengajuanPendingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! filter_var(env('RUN_REAL_CUTI_ENGINE_TEST', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Set RUN_REAL_CUTI_ENGINE_TEST=true untuk menjalankan (butuh DB dev).');
        }
    }

    /** @test */
    public function hitungan_pending_sama_dengan_seluruh_pengajuan_menggantung_milik_pengguna(): void
    {
        $kode = $this->kodeKaryawanDenganPengajuanPending();

        $harapan = (int) DB::table('N_HRIS_VW_All_Transactions')
            ->where('Kode_Karyawan_Requester', $kode)
            ->where('Tipe_Transaksi', 'CUTI')
            ->whereNull('Flag_Approval')
            ->count();

        $m = new ReflectionMethod(CutiController::class, 'hitungPengajuanCutiPending');
        $m->setAccessible(true);
        $aktual = $m->invoke(app(CutiController::class), $kode);

        $this->assertSame($harapan, $aktual);

        // Inti bug: angkanya harus datang dari seluruh data, bukan satu halaman.
        // Kalau perhitungan kelak dipindah kembali ke potongan ter-paginasi,
        // assertion ini merah begitu orangnya punya lebih dari satu halaman.
        $this->assertGreaterThan(
            0,
            $aktual,
            "Karyawan {$kode} punya pengajuan menggantung tetapi hitungannya 0 — persis bug aslinya.",
        );
    }

    /** @test */
    public function payload_check_saldo_membawa_kunci_pending(): void
    {
        $kode = $this->kodeKaryawanDenganPengajuanPending();

        $userIdWeb = DB::table('Karyawan')->where('Kode_Karyawan', $kode)->value('UserID_Web');
        $user = $userIdWeb ? User::find($userIdWeb) : null;

        if (! $user) {
            $this->markTestSkipped("Tidak ada akun KPI_Users untuk karyawan {$kode}.");
        }

        // Controller dipanggil langsung, bukan lewat HTTP: rute /cuti dijaga
        // middleware check.jabatan:cutiPage, dan yang diuji di sini bentuk
        // payload-nya, bukan hak aksesnya.
        Auth::setUser($user);
        $payload = json_decode(
            app(CutiController::class)->getSaldoCuti()->getContent(),
            true,
        );

        // Tanpa kunci ini klien diam-diam menampilkan 0 - bentuk bug yang sama,
        // hanya berpindah dari `riwayatData.total` ke `result.pending`.
        $this->assertArrayHasKey('pending', $payload['result'] ?? []);
        $this->assertIsInt($payload['result']['pending']);
        $this->assertSame(
            $payload['result']['pending'],
            (int) DB::table('N_HRIS_VW_All_Transactions')
                ->where('Kode_Karyawan_Requester', $kode)
                ->where('Tipe_Transaksi', 'CUTI')
                ->whereNull('Flag_Approval')
                ->count(),
        );
    }

    /** @test */
    public function seluruh_halaman_riwayat_menghasilkan_baris_sebanyak_yang_dijanjikan_paginasi(): void
    {
        $kode = $this->kodeKaryawanDenganPengajuanPending();

        $userIdWeb = DB::table('Karyawan')->where('Kode_Karyawan', $kode)->value('UserID_Web');
        $user = $userIdWeb ? User::find($userIdWeb) : null;

        if (! $user) {
            $this->markTestSkipped("Tidak ada akun KPI_Users untuk karyawan {$kode}.");
        }

        Auth::setUser($user);

        $terkumpul = 0;
        $dijanjikan = null;
        $totalHalaman = 1;

        for ($halaman = 1; $halaman <= $totalHalaman && $halaman <= 20; $halaman++) {
            $req = Request::create("http://localhost/api/v1/cuti/riwayat?page={$halaman}&limit=10", 'GET');
            $req->setUserResolver(fn () => $user);
            app()->instance('request', $req);

            $payload = json_decode(
                app(CutiController::class)->getRiwayat($req)->getContent(),
                true,
            );

            $dijanjikan ??= (int) ($payload['pagination']['total'] ?? 0);
            $totalHalaman = (int) ($payload['pagination']['total_pages'] ?? 1);

            // Baris null adalah bug aslinya: transaksi tanpa jejak approval
            // dipetakan menjadi null lalu dibuang klien, sementara `total`
            // sudah terlanjur dihitung dari kueri mentah. Halaman terakhir bisa
            // kosong sama sekali dan yang tampil justru ilustrasi "Tidak Ada
            // Data" bergambar 404.
            foreach ($payload['result'] ?? [] as $baris) {
                $this->assertIsArray(
                    $baris,
                    "Halaman {$halaman} memuat baris null — transaksi dibuang diam-diam oleh mapper.",
                );
                $terkumpul++;
            }
        }

        $this->assertSame(
            $dijanjikan,
            $terkumpul,
            'Jumlah baris yang benar-benar dikirim seluruh halaman harus sama dengan `pagination.total`.',
        );
    }

    /** Karyawan mana pun yang benar-benar punya pengajuan cuti menggantung. */
    private function kodeKaryawanDenganPengajuanPending(): string
    {
        $baris = DB::table('N_HRIS_VW_All_Transactions')
            ->where('Tipe_Transaksi', 'CUTI')
            ->whereNull('Flag_Approval')
            ->select('Kode_Karyawan_Requester')
            ->groupBy('Kode_Karyawan_Requester')
            ->orderByRaw('COUNT(*) DESC')
            ->first();

        if (! $baris) {
            $this->markTestSkipped('Tidak ada satu pun pengajuan cuti pending di DB development.');
        }

        return (string) $baris->Kode_Karyawan_Requester;
    }
}
