<?php

namespace Tests\Feature\KPI\Monitoring;

use App\Http\Controllers\KPI\Monitoring\Enforcement\KpiEnforcementController;
use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\KpiAkumulasiYtdService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Mode YTD pada Performance Tracking.
 *
 * ── Yang dijaga, dan kenapa persisnya ini ───────────────────────────────────
 *
 *  1. ANGKANYA HARUS SAMA DENGAN DASHBOARD. Skor YTD tampil di dashboard
 *     pribadi, dashboard tim, `kpi:verify-ytd`, dan sekarang monitoring. Kalau
 *     monitoring menghitung sendiri, admin menarik laporan yang berselisih
 *     dengan layar yang dilihat karyawan — dan tak seorang pun bisa menjelaskan
 *     mana yang benar. Semuanya dikunci ke KpiAkumulasiYtdService.
 *
 *  2. UNIVERSE-NYA HARUS SELURUH RENTANG. YTD Agustus mengakumulasi Jan..Agu,
 *     jadi orang yang terjadwal Februari tetapi tidak Agustus harus tetap
 *     muncul. Kalau universe-nya tetap satu bulan, nilainya ikut terhitung di
 *     angka lain tetapi orangnya hilang dari laporan.
 *
 *  3. BARIS YTD TIDAK BOLEH BISA DIBATALKAN. Ia agregat, bukan satu transaksi.
 *     Layar mematikan centang lewat id_update_performance yang null; kalau id
 *     itu bocor kembali, admin bisa mencentang sesuatu yang maknanya kabur —
 *     yang tampil gabungan, yang terbatalkan satu baris.
 *
 *  4. TANPA DATA BUKAN NOL. Orang tanpa realisasi terverifikasi sepanjang
 *     rentang memulangkan null, bukan angka lantai cap 50 — kekeliruan yang
 *     persis pernah terjadi pada `kpi:verify-ytd`.
 */
class PerformanceTrackingYtdTest extends TestCase
{
    use DatabaseTransactions;

    private const PERIODE = '2026-08';

    /** @return array<int,array<string,mixed>> */
    private function tracking(string $mode): array
    {
        $admin = User::first();

        if (! $admin) {
            $this->markTestSkipped('Tidak ada akun untuk berperan sebagai admin.');
        }

        Auth::setUser($admin);

        $req = Request::create('/x', 'GET', [
            'periode' => self::PERIODE,
            'mode' => $mode,
            'per_page' => 100,
            'page' => 1,
        ]);
        $req->setUserResolver(fn () => $admin);
        app()->instance('request', $req);

        $res = app(KpiEnforcementController::class)->getPerformanceTracking($req);

        if ($res->getStatusCode() !== 200) {
            $this->markTestSkipped('Tidak ada penjadwalan penilaian untuk periode uji.');
        }

        $isi = json_decode($res->getContent(), true);

        return $isi['result']['rows'] ?? $isi['result'] ?? [];
    }

    private function totalBaris(string $mode, ?string $periode = null): int
    {
        $admin = User::first();

        if (! $admin) {
            $this->markTestSkipped('Tidak ada akun untuk berperan sebagai admin.');
        }

        Auth::setUser($admin);

        $req = Request::create('/x', 'GET', [
            'periode' => $periode ?? self::PERIODE,
            'mode' => $mode,
            'per_page' => 5,
            'page' => 1,
        ]);
        $req->setUserResolver(fn () => $admin);
        app()->instance('request', $req);

        $res = app(KpiEnforcementController::class)->getPerformanceTracking($req);

        // 404 diperlakukan sebagai NOL, bukan skip. Bulan uji sengaja dipilih yang
        // tidak punya penjadwalan sendiri: di situlah bedanya terlihat — mode
        // bulanan memang kosong, sedangkan YTD harus tetap menemukan orang dari
        // bulan-bulan sebelumnya. Kalau 404 membuat uji ini melewati dirinya
        // sendiri, penyempitan universe lolos tanpa ketahuan.
        if ($res->getStatusCode() !== 200) {
            return 0;
        }

        return (int) (json_decode($res->getContent(), true)['total_data'] ?? 0);
    }

    public function test_skornya_sama_dengan_sumber_kanonik(): void
    {
        $rows = collect($this->tracking('ytd'))->filter(fn ($r) => ($r['total_score'] ?? null) !== null)->values();

        if ($rows->isEmpty()) {
            $this->markTestSkipped('Tidak ada baris berskor pada periode uji.');
        }

        $svc = app(KpiAkumulasiYtdService::class);
        $periods = $svc->periodeSampai(2026, 8);
        $harapan = $svc->skorPerUser($rows->pluck('id_user')->all(), 2026, $periods);

        foreach ($rows as $r) {
            $kunci = (string) $r['id_user'];

            $this->assertArrayHasKey($kunci, $harapan, "Baris {$r['kode_karyawan']} berskor tanpa dasar akumulasi.");
            $this->assertEqualsWithDelta(
                round((float) $harapan[$kunci], 2),
                (float) $r['total_score'],
                0.01,
                "Skor YTD {$r['kode_karyawan']} di monitoring berbeda dari sumber kanonik — ".
                'laporan admin akan berselisih dengan dashboard karyawan.',
            );
        }
    }

    public function test_universe_mencakup_seluruh_rentang_bukan_satu_bulan(): void
    {
        // Fixture dibangun sendiri: di data dev SETIAP bulan 2026 punya jadwal
        // penilaian, jadi tak ada bulan yang bisa membedakan kedua cakupan —
        // uji yang bersandar pada data yang ada akan hijau tanpa menguji apa pun.
        //
        // Yang dibangun: satu tahun jauh ke depan dengan jadwal HANYA di Januari.
        // Maret pada tahun itu kosong, sehingga mode bulanan memang tidak
        // menemukan siapa pun, sedangkan YTD harus tetap menjangkau Januari.
        $tahun = 2033;

        DB::table('KPI_Master_Penjadwalan')->insert([
            'Nama_Kegiatan' => 'Uji cakupan YTD',
            'Tahun' => $tahun,
            'Periode' => $tahun.'-01',
            'Flag_Penilaian_KPI' => 'Y',
            'Flag_Perorangan' => 'T',
            'Flag_Aktif' => 'Y',
            'Jadwal_Mulai' => now()->subDays(3),
            'Jadwal_Akhir' => now()->addDays(3),
        ]);

        $bulanan = $this->totalBaris('bulan', $tahun.'-03');
        $ytd = $this->totalBaris('ytd', $tahun.'-03');

        $this->assertSame(
            0,
            $bulanan,
            'Prasyarat fixture gagal: bulan Maret seharusnya tidak punya penjadwalan sendiri.',
        );
        $this->assertGreaterThan(
            0,
            $ytd,
            'Mode YTD tidak menemukan siapa pun untuk '.$tahun.'-03, padahal Januari terjadwal. '.
            'Cakupannya menyempit ke satu bulan, sehingga orang yang dinilai di bulan lain '.
            'dalam rentang hilang dari laporan meski nilainya ikut terhitung.',
        );
    }

    public function test_baris_ytd_tidak_bisa_dipilih_untuk_dibatalkan(): void
    {
        $rows = $this->tracking('ytd');

        if ($rows === []) {
            $this->markTestSkipped('Tidak ada baris pada periode uji.');
        }

        foreach ($rows as $r) {
            $this->assertTrue($r['is_ytd'] ?? false, 'Baris mode YTD tidak ditandai is_ytd.');
            $this->assertNull(
                $r['id_update_performance'] ?? null,
                "Baris YTD {$r['kode_karyawan']} masih membawa id transaksi — centang pembatalan ".
                'akan hidup untuk sebuah agregat.',
            );
            $this->assertNotEmpty(
                $r['cancel_disabled_reason'] ?? null,
                'Centang mati tanpa alasan tertulis akan terbaca sebagai kerusakan.',
            );
        }
    }

    public function test_tanpa_realisasi_terverifikasi_dilaporkan_null_bukan_lantai_cap(): void
    {
        $rows = collect($this->tracking('ytd'));

        $tanpaData = $rows->filter(fn ($r) => ($r['has_ytd_score'] ?? true) === false);

        if ($tanpaData->isEmpty()) {
            $this->markTestSkipped('Semua karyawan pada periode uji punya realisasi terverifikasi.');
        }

        foreach ($tanpaData as $r) {
            $this->assertNull(
                $r['total_score'],
                "Karyawan {$r['kode_karyawan']} tanpa realisasi terverifikasi diberi angka. ".
                'Lantai cap mengubah "tidak punya data" menjadi 50 — kekeliruan yang sama '.
                'sudah pernah terjadi di kpi:verify-ytd.',
            );
        }
    }

    public function test_mode_bulanan_tidak_berubah_perilakunya(): void
    {
        $rows = collect($this->tracking('bulan'));

        if ($rows->isEmpty()) {
            $this->markTestSkipped('Tidak ada baris pada periode uji.');
        }

        $this->assertEmpty(
            $rows->filter(fn ($r) => ($r['is_ytd'] ?? false) === true)->all(),
            'Mode bulanan ikut ditandai YTD.',
        );

        $berskor = $rows->filter(fn ($r) => ($r['id_update_performance'] ?? null) !== null);

        if ($berskor->isNotEmpty()) {
            $baris = $berskor->first();
            $header = DB::table('KPI_Update_Performance')
                ->where('Id_Update_Performance', $baris['id_update_performance'])
                ->first(['Total_Score']);

            $this->assertEqualsWithDelta(
                (float) $header->Total_Score,
                (float) $baris['total_score'],
                0.01,
                'Skor mode bulanan tidak lagi berasal dari header transaksinya.',
            );
        }
    }
}
