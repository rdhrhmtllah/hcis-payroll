<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Response as InertiaResponse;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Halaman /update-performance tidak boleh membuka periode yang sudah dikirim.
 *
 * ── Bug aslinya ─────────────────────────────────────────────────────────────
 *
 * index() memilih jadwal yang sedang berjalan lalu membukanya begitu saja,
 * TANPA melihat getPeriodeTerkunciUser() — padahal halaman TIM sudah lama
 * melewati periode terkunci. Akibatnya seseorang bisa mengisi seluruh formulir
 * untuk periode yang sudah ia selesaikan, dan baru ditolak di langkah TERAKHIR:
 *
 *   FINALISASI_PERFORMANCE_ERROR: Periode 2026-05 sudah memiliki update
 *   performance yang aktif (AKPI-06-2026-0032, status disetujui).
 *
 * Semua pekerjaannya sudah telanjur dikerjakan saat penolakan itu muncul —
 * bentuk kegagalan yang sama dengan bug kolom Metode_Cap: temboknya dipasang di
 * detik terakhir.
 *
 * Nyata terjadi pada A4: enam periode berturut-turut (2026-01..2026-06) sudah
 * berstatus 'disetujui', tetapi jadwal yang berjalan menunjuk 2026-05.
 *
 * ── Yang dijaga ─────────────────────────────────────────────────────────────
 *
 *  1. Bila ada jadwal berjalan yang periodenya BELUM terkunci, itulah yang
 *     dibuka — walau jadwal terkunci datang lebih dulu dalam urutannya.
 *  2. Bila SEMUA jadwal berjalan terkunci, halaman tetap dibuka (riwayat dan
 *     alur persetujuannya masih perlu dilihat) tetapi membawa penanda beserta
 *     No_Transaksi yang menghalangi — dikatakan di muka, bukan di tombol akhir.
 */
class PeriodeTerkunciHalamanPribadiTest extends TestCase
{
    use DatabaseTransactions;

    private object $karyawan;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $kandidat = DB::table('Karyawan as k')
            ->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')
            ->whereNotNull('k.UserID_Web')
            ->whereNotExists(function ($q) {
                $q->from('KPI_Update_Performance')
                    ->whereColumn('KPI_Update_Performance.Id_User', 'k.UserID_Web')
                    ->where('Flag_Aktif', 'Y')
                    ->whereNull('Status');
            })
            ->select('k.UserID_Web', 'k.Kode_Karyawan')
            ->first();

        if (! $kandidat) {
            $this->markTestSkipped('Tidak ada karyawan berakun yang bebas dari pengajuan hidup.');
        }

        $this->karyawan = $kandidat;
        $this->user = User::find((int) $kandidat->UserID_Web);

        if (! $this->user) {
            $this->markTestSkipped('Kandidat tidak punya akun KPI_Users.');
        }

        // Jadwal global lain yang sedang berjalan bisa ikut terpilih dan membuat
        // uji ini mengukur hal lain. Dinonaktifkan di dalam transaksi.
        DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Penilaian_KPI', 'Y')
            ->where('Flag_Aktif', 'Y')
            ->where('Jadwal_Mulai', '<=', now())
            ->where('Jadwal_Akhir', '>=', now())
            ->update(['Flag_Aktif' => 'T']);

        Auth::setUser($this->user);
    }

    public function test_memilih_jadwal_yang_periodenya_belum_terkunci(): void
    {
        // Yang TERKUNCI sengaja dibuat mulai lebih dulu: dengan aturan lama
        // (ambil yang pertama) ia yang akan terpilih.
        $terkunci = $this->buatJadwal('2026-05', now()->subDays(5));
        $bebas = $this->buatJadwal('2026-11', now()->subDays(1));

        $this->kunciPeriode('2026-05');

        $props = $this->propsIndex();

        $this->assertSame(
            'KPI/Performance/Update/updatePerformance',
            $props['component'],
            'Halaman tidak terbuka sama sekali.',
        );
        $this->assertFalse(
            $props['periodeTerkunci'],
            'Jadwal terkunci tetap dipilih padahal ada jadwal berjalan lain yang bebas.',
        );
        $this->assertSame(
            $this->hash($bebas),
            $props['IdMasterPenjadwalan'],
            "Yang dibuka bukan jadwal periode 2026-11 (bebas), melainkan {$terkunci}.",
        );
    }

    public function test_semua_terkunci_ditandai_di_muka_beserta_nomor_penghalangnya(): void
    {
        $this->buatJadwal('2026-05', now()->subDays(5));
        $noTransaksi = $this->kunciPeriode('2026-05');

        $props = $this->propsIndex();

        $this->assertTrue(
            $props['periodeTerkunci'],
            'Periode yang sudah punya pengajuan hidup dibuka tanpa peringatan apa pun — '.
            'penolakannya akan muncul di tombol finalisasi, setelah semuanya diisi.',
        );
        $this->assertSame('2026-05', $props['periodeTerkunciLabel']);
        $this->assertSame(
            $noTransaksi,
            $props['noTransaksiTerkunci'],
            'Nomor pengajuan yang menghalangi tidak disebutkan, jadi pengguna tak tahu apa yang harus dibatalkan.',
        );
        $this->assertSame(
            'disetujui',
            $props['statusTerkunci'],
            'Status baris pengunci tidak ikut dibawa, jadi layar hanya bisa bicara satu nada.',
        );
    }

    /**
     * Pengajuan yang sedang MENUNGGU persetujuan bukan masalah — itu keadaan
     * sehat. Layar harus bisa membedakannya dari pengajuan yang sudah disetujui,
     * karena hanya yang kedua masuk akal disusul "batalkan yang lama".
     */
    public function test_pengajuan_yang_masih_menunggu_dibedakan_dari_yang_sudah_disetujui(): void
    {
        $this->buatJadwal('2026-05', now()->subDays(5));

        DB::table('KPI_Update_Performance')->insert([
            'No_Transaksi' => $no = 'UJI-'.substr(bin2hex(random_bytes(6)), 0, 10),
            'Id_Pengajuan' => DB::table('KPI_Pengajuan')->value('Id_Pengajuan'),
            'Id_User' => (int) $this->karyawan->UserID_Web,
            'Periode' => '2026-05',
            'Status_Pengajuan' => 'menunggu',
            'Flag_Approval' => null,
            'Flag_Aktif' => 'Y',
            'Status' => null,
            'Tanggal' => now(),
        ]);

        $props = $this->propsIndex();

        $this->assertTrue($props['periodeTerkunci']);
        $this->assertSame($no, $props['noTransaksiTerkunci']);
        $this->assertSame(
            'menunggu',
            $props['statusTerkunci'],
            'Pengajuan yang sedang berjalan terbaca sama dengan yang sudah disetujui, '.
            'sehingga layar menyuruh membatalkan sesuatu yang justru sedang bekerja.',
        );
    }

    private function buatJadwal(string $periode, \Carbon\Carbon $mulai): int
    {
        return (int) DB::table('KPI_Master_Penjadwalan')->insertGetId([
            'Nama_Kegiatan' => 'Uji periode terkunci '.$periode,
            'Tahun' => (int) substr($periode, 0, 4),
            'Periode' => $periode,
            'Flag_Penilaian_KPI' => 'Y',
            'Flag_Perorangan' => 'T',
            'Flag_Aktif' => 'Y',
            'Jadwal_Mulai' => $mulai,
            'Jadwal_Akhir' => now()->addDays(5),
        ]);
    }

    private function kunciPeriode(string $periode): string
    {
        $no = 'UJI-'.substr(bin2hex(random_bytes(6)), 0, 10);

        DB::table('KPI_Update_Performance')->insert([
            'No_Transaksi' => $no,
            'Id_Pengajuan' => DB::table('KPI_Pengajuan')->value('Id_Pengajuan'),
            'Id_User' => (int) $this->karyawan->UserID_Web,
            'Periode' => $periode,
            'Status_Pengajuan' => 'disetujui',
            'Flag_Approval' => 'Y',
            'Flag_Aktif' => 'Y',
            'Status' => null,
            'Tanggal' => now(),
        ]);

        return $no;
    }

    private function hash(int $id): string
    {
        return \Vinkla\Hashids\Facades\Hashids::connection('custom')->encode($id);
    }

    /** @return array<string,mixed> */
    private function propsIndex(): array
    {
        $resp = app(UpdatePerformanceController::class)->index();

        $this->assertInstanceOf(InertiaResponse::class, $resp);

        $props = new ReflectionProperty(InertiaResponse::class, 'props');
        $props->setAccessible(true);
        $component = new ReflectionProperty(InertiaResponse::class, 'component');
        $component->setAccessible(true);

        return ['component' => $component->getValue($resp)] + $props->getValue($resp);
    }
}
