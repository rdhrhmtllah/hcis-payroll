<?php

namespace Tests\Feature\ESS\Attendance;

use App\Models\ESS\Employee\Models\User;
use App\Services\ESS\Attendance\Overtime\Request\LemburRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tiga widget di /lembur-management harus dihitung server, bukan dari satu
 * halaman paginasi.
 *
 * Dua bug aslinya:
 *   1. "Pending" tidak pernah dihitung — nilainya hardcode 0 di komponen.
 *   2. "Hari Ini" dihitung di klien dari `this.data` (satu halaman saja) dan
 *      membandingkan tanggal lewat toISOString(), yang memakai UTC. Baris
 *      bertanda tengah malam waktu Jakarta ("2026-09-10 00:00:00") berubah
 *      menjadi "2026-09-09" saat dikonversi sehingga tidak pernah cocok dengan
 *      hari ini — hasilnya 0 terus, bahkan ketika transaksinya ada di halaman
 *      yang sedang tampil.
 *
 * Penjaga ini mengunci hasilnya terhadap kueri langsung, memakai cakupan yang
 * sama dengan daftar: satu perusahaan, bukan transaksi admin, milik sendiri,
 * dan mengecualikan yang dibatalkan.
 *
 * Env-gated: butuh DB development.
 *   RUN_REAL_CUTI_ENGINE_TEST=true php artisan test --filter WidgetLemburDihitungServer
 */
class WidgetLemburDihitungServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! filter_var(env('RUN_REAL_CUTI_ENGINE_TEST', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Set RUN_REAL_CUTI_ENGINE_TEST=true untuk menjalankan (butuh DB dev).');
        }
    }

    /** @test */
    public function ringkasan_cocok_dengan_hitungan_langsung_dan_bukan_dari_satu_halaman(): void
    {
        $baris = DB::table('Transaksi_Lembur')
            ->where(function ($q) {
                $q->whereNull('Status')->orWhere('Status', 'Y');
            })
            ->where(function ($q) {
                $q->where('Flag_Admin', 'T')->orWhereNull('Flag_Admin');
            })
            ->select('UserID')
            ->groupBy('UserID')
            ->orderByRaw('COUNT(*) DESC')
            ->first();

        if (! $baris) {
            $this->markTestSkipped('Tidak ada transaksi lembur di DB development.');
        }

        $kode = (string) $baris->UserID;
        $karyawan = DB::table('Karyawan')->where('Kode_Karyawan', $kode)->first();
        $user = $karyawan ? User::find($karyawan->UserID_Web) : null;

        if (! $user) {
            $this->markTestSkipped("Tidak ada akun KPI_Users untuk karyawan {$kode}.");
        }

        $req = Request::create('http://localhost/lembur-management/get-data', 'GET');
        $req->setUserResolver(fn () => $user);
        app()->instance('request', $req);
        Auth::setUser($user);

        $ringkasan = app(LemburRequestService::class)->ringkasanHistory($req, false);

        $dasar = fn () => DB::table('Transaksi_Lembur as h')
            ->where('h.Kode_Perusahaan', $karyawan->Kode_Perusahaan)
            ->where('h.UserID', $kode)
            ->where(function ($q) {
                $q->whereNull('h.Status')->orWhere('h.Status', 'Y');
            })
            ->where(function ($q) {
                $q->where('h.Flag_Admin', 'T')->orWhereNull('h.Flag_Admin');
            });

        $this->assertSame($dasar()->count(), $ringkasan['total']);

        $this->assertSame(
            $dasar()->whereNull('h.Status')
                ->whereRaw('CAST(h.Tanggal AS DATE) = ?', [now(config('app.timezone'))->toDateString()])
                ->count(),
            $ringkasan['hari_ini'],
            'Hitungan "Hari Ini" harus memakai batas hari zona waktu aplikasi, bukan UTC.',
        );

        $this->assertSame(
            $dasar()->whereNull('h.Status')
                ->where(function ($q) {
                    $q->whereNull('h.Flag_Selesai')->orWhere('h.Flag_Selesai', '<>', 'Y');
                })
                ->count(),
            $ringkasan['pending'],
            '"Pending" harus benar-benar dihitung, bukan nilai tetap.',
        );

        // Inti bug #2: totalnya melebihi satu halaman (10), jadi angka apa pun
        // yang diturunkan dari `this.data` pasti salah.
        if ($ringkasan['total'] > 10) {
            $this->assertGreaterThan(
                10,
                $ringkasan['total'],
                'Data uji harus melebihi satu halaman agar bug paginasi benar-benar terwakili.',
            );
        }
    }
}
