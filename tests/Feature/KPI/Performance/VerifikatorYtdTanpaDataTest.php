<?php

namespace Tests\Feature\KPI\Performance;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `kpi:verify-ytd` tidak boleh mengarang skor untuk orang tanpa data.
 *
 * ── Bug aslinya ─────────────────────────────────────────────────────────────
 *
 * Perhitungan YTD memulangkan `max($minCap, min($maxCap, $pct))`. Ketika
 * Σbobot = 0 — artinya orang itu TIDAK PUNYA satu pun parameter/realisasi
 * terverifikasi — $pct bernilai 0 dan lantai cap mengubahnya menjadi 50.
 * Orang tanpa data lalu dilaporkan "YTD 50, Under Target".
 *
 * Terjadi pada E14 10 September 2026: pengajuannya masih menunggu approval dan
 * belum terverifikasi sama sekali, tetapi perintah ini melaporkannya sebagai
 * Under Target dengan skor 50.
 *
 * Yang membuatnya mahal: perintah ini justru alat untuk MEMVALIDASI angka
 * dashboard. Dashboard sendiri sudah benar — HomepageKpiService mengeluarkan
 * anggota tanpa data dari rata-rata, dengan komentarnya sendiri: "total_bobot
 * <= 0 berarti tidak ada parameter/realisasi -> 0 (bukan min-cap)". Alat
 * pembanding yang memakai aturan berbeda mengirim orang mengejar selisih yang
 * tidak ada.
 */
class VerifikatorYtdTanpaDataTest extends TestCase
{
    use DatabaseTransactions;

    public function test_karyawan_tanpa_realisasi_terverifikasi_dilaporkan_tanpa_data(): void
    {
        $kode = DB::table('Karyawan as k')
            ->whereNotNull('k.UserID_Web')
            ->whereNotExists(function ($q) {
                $q->from('KPI_Update_Performance')
                    ->whereColumn('KPI_Update_Performance.Id_User', 'k.UserID_Web')
                    ->where('Flag_Aktif', 'Y')
                    ->whereNull('Status')
                    ->where('Flag_Approval', 'Y')
                    ->where('Flag_Verifikasi', 'Y');
            })
            ->value('k.Kode_Karyawan');

        if (! $kode) {
            $this->markTestSkipped('Semua karyawan punya realisasi terverifikasi di data ini.');
        }

        $keluaran = $this->jalankan(['kode' => $kode, '--year' => (int) date('Y')]);

        $this->assertStringContainsString(
            'tidak ada data',
            $keluaran,
            "Karyawan {$kode} tanpa realisasi terverifikasi tetap diberi angka. Lantai cap ".
            'mengubah "tidak punya data" menjadi skor 50, dan orangnya terhitung Under Target.',
        );
        $this->assertStringNotContainsString(
            'YTD AKUMULASI (jepit/param)    : 50',
            $keluaran,
            'Skor lantai masih dicetak untuk karyawan tanpa data.',
        );
    }

    public function test_agregat_tim_tidak_menghitung_anggota_tanpa_data_sebagai_under_target(): void
    {
        $leader = DB::table('KPI_Karyawan_Team as kt')
            ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'kt.Kode_Karyawan_Team')
            ->where('kt.Flag_Aktif', 'Y')
            ->whereNotNull('k.UserID_Web')
            ->whereNotExists(function ($q) {
                $q->from('KPI_Update_Performance')
                    ->whereColumn('KPI_Update_Performance.Id_User', 'k.UserID_Web')
                    ->where('Flag_Aktif', 'Y')
                    ->whereNull('Status')
                    ->where('Flag_Approval', 'Y')
                    ->where('Flag_Verifikasi', 'Y');
            })
            ->value('kt.Kode_Karyawan_Leader');

        if (! $leader) {
            $this->markTestSkipped('Tidak ada leader dengan anggota tanpa realisasi terverifikasi.');
        }

        $keluaran = $this->jalankan(['--leader' => $leader, '--year' => (int) date('Y')]);

        $this->assertStringContainsString(
            'tidak ada data',
            $keluaran,
            'Anggota tanpa realisasi tidak ditandai; ia akan ikut terhitung dalam agregat.',
        );
    }

    /** @param  array<string,mixed>  $argumen */
    private function jalankan(array $argumen): string
    {
        // Artisan::call() dipakai, bukan $this->artisan(): yang kedua memulangkan
        // objek assertion tertunda dan keluarannya baru ada setelah dijalankan.
        \Illuminate\Support\Facades\Artisan::call('kpi:verify-ytd', $argumen);

        return \Illuminate\Support\Facades\Artisan::output();
    }
}
