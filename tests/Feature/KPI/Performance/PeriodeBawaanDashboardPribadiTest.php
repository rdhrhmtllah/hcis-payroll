<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Dashboard\StaffPerformanceDashboardController;
use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Periode bawaan dashboard pribadi harus periode yang benar-benar berisi.
 *
 * ── Bug aslinya ─────────────────────────────────────────────────────────────
 *
 * Pemilih periode bawaan menyaring Flag_Approval + Flag_Verifikasi saja. Ia
 * TIDAK menuntut barisnya masih hidup, padahal setiap kueri di bawahnya menuntut
 * itu — bahkan dengan komentar yang menjelaskan kenapa. Akibatnya baris yang
 * sudah DIBATALKAN ikut menentukan periode yang dibuka, lalu seluruh isi halaman
 * (yang memang menyaringnya) tidak menemukan apa pun: widget "N/A", achievement
 * 0, daftar parameter kosong — pada orang yang sebenarnya punya nilai
 * terverifikasi.
 *
 * Terjadi pada A4 10 September 2026: satu-satunya baris periode 2026-09 sudah
 * dibatalkan, tetapi ia yang terpilih sebagai periode bawaan, sehingga dashboard
 * pribadinya tampak kosong sama sekali.
 *
 * Pengajuan induknya ikut dituntut hidup dengan alasan yang sama: periode yang
 * pengajuannya sudah digantikan tetap punya header terverifikasi, tetapi
 * parameternya tak bisa lagi diambil — halaman terbuka pada periode yang pasti
 * kosong.
 */
class PeriodeBawaanDashboardPribadiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_periode_bawaan_melewati_baris_yang_sudah_dibatalkan(): void
    {
        $kandidat = DB::table('KPI_Update_Performance as up')
            ->join('KPI_Pengajuan as p', 'p.Id_Pengajuan', '=', 'up.Id_Pengajuan')
            ->where('up.Flag_Aktif', 'Y')
            ->whereNull('up.Status')
            ->where('up.Flag_Approval', 'Y')
            ->where('up.Flag_Verifikasi', 'Y')
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Flag_Approval', 'Y')
            ->whereNull('p.Status')
            ->orderByDesc('up.Periode')
            ->first(['up.Id_User', 'up.Periode', 'up.Id_Pengajuan']);

        if (! $kandidat) {
            $this->markTestSkipped('Tidak ada baris performance hidup+terverifikasi di data ini.');
        }

        $user = User::find((int) $kandidat->Id_User);

        if (! $user) {
            $this->markTestSkipped('Pemilik baris itu tidak punya akun KPI_Users.');
        }

        // Baris DIBATALKAN pada periode yang LEBIH BARU. Dengan aturan lama ia
        // yang menang karena urutannya menurun berdasarkan Periode.
        $periodeLebihBaru = \Carbon\Carbon::parse($kandidat->Periode.'-01')->addMonths(2)->format('Y-m');

        DB::table('KPI_Update_Performance')->insert([
            'No_Transaksi' => 'UJI-BATAL-'.substr(bin2hex(random_bytes(6)), 0, 10),
            'Id_Pengajuan' => $kandidat->Id_Pengajuan,
            'Id_User' => (int) $kandidat->Id_User,
            'Periode' => $periodeLebihBaru,
            'Status_Pengajuan' => 'disetujui',
            'Flag_Approval' => 'Y',
            'Flag_Verifikasi' => 'Y',
            // Inti fixture: sudah dibatalkan.
            'Flag_Aktif' => 'T',
            'Status' => 'Y',
            'Tanggal' => now(),
        ]);

        Auth::setUser($user);
        $req = Request::create('/x', 'GET');
        $req->setUserResolver(fn () => $user);
        app()->instance('request', $req);

        $isi = json_decode(
            app(StaffPerformanceDashboardController::class)->getDataInformasiByUserId($req)->getContent(),
            true,
        );
        $hasil = $isi['result'] ?? $isi;

        $terpilih = sprintf('%04d-%s', (int) ($hasil['selectedYear'] ?? 0), (string) ($hasil['selectedMonth'] ?? ''));

        $this->assertNotSame(
            $periodeLebihBaru,
            $terpilih,
            'Periode bawaan diambil dari baris yang sudah dibatalkan. Seluruh isi halaman '.
            'menyaring baris itu, jadi layar akan menampilkan N/A dan nol di semua tempat.',
        );
        $this->assertSame(
            $kandidat->Periode,
            $terpilih,
            'Periode bawaan bukan periode hidup terbaru milik orang ini.',
        );
    }
}
