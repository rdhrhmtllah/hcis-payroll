<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Jalur TULIS Update Performance Team hanya boleh menyentuh anggota tim sendiri.
 *
 * ── Lubangnya ───────────────────────────────────────────────────────────────
 *
 * storeTemporaryPerformanceStep1ByTeam() dan finalisasiPerformanceByTeam()
 * menerima id karyawan dari URL dan id pengajuan dari badan permintaan APA
 * ADANYA. Tidak ada satu pun kueri di dalamnya yang menyempitkan sasaran ke tim
 * si pemanggil. Artinya siapa pun yang punya kapabilitas UpdatePerformanceTeam
 * bisa menulis penilaian untuk karyawan mana pun di perusahaan hanya dengan
 * mengganti id di URL — lalu penilaian itu berjalan di alur approval seperti
 * biasa, tanpa satu pun tanda bahwa asalnya keliru.
 *
 * Lubang ini LEBIH TUA daripada perubahan keanggotaan berbasis roster; diperiksa
 * lewat git, commit roster tidak menyentuh kedua metode itu dan versi
 * sebelumnya pun nol pemeriksaan. Yang berubah hanya: sekarang ada satu definisi
 * keanggotaan yang jelas untuk dipakai sebagai gerbang.
 *
 * ── Yang dijaga ─────────────────────────────────────────────────────────────
 *
 * Dua pintu, dua-duanya. Draf dan finalisasi adalah permintaan TERPISAH — menutup
 * satu saja menyisakan jalan bagi yang lain, dan di proyek ini sudah berulang
 * kali terbukti aturan yang hanya dipasang di satu pintu akan menyimpang di
 * pintu lainnya.
 */
class GerbangTulisTimTest extends TestCase
{
    use DatabaseTransactions;

    private object $leader;

    private object $orangLuar;

    protected function setUp(): void
    {
        parent::setUp();

        $orang = DB::table('Karyawan as k')
            ->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')
            ->whereNotNull('k.UserID_Web')
            ->distinct()
            ->limit(2)
            ->get(['k.UserID_Web', 'k.Kode_Karyawan']);

        if ($orang->count() < 2) {
            $this->markTestSkipped('Butuh dua karyawan berakun.');
        }

        [$this->leader, $this->orangLuar] = $orang->all();

        // Pastikan sasaran benar-benar DI LUAR tim si pemanggil.
        DB::table('KPI_Karyawan_Team')
            ->where('Kode_Karyawan_Leader', $this->leader->Kode_Karyawan)
            ->where('Kode_Karyawan_Team', $this->orangLuar->Kode_Karyawan)
            ->update(['Flag_Aktif' => 'T']);

        $user = User::find((int) $this->leader->UserID_Web);

        if (! $user) {
            $this->markTestSkipped('Leader uji tidak punya akun KPI_Users.');
        }

        Auth::setUser($user);
    }

    public function test_simpan_draf_menolak_karyawan_di_luar_tim(): void
    {
        $req = Request::create('/x', 'POST', [
            'kpi_data' => [[
                'Id_Pengajuan' => 'x',
                'Id_Pengajuan_Detail' => 'x',
                'Id_Pengajuan_Det' => 'x',
                'is_input_period' => 'true',
            ]],
            'current_step' => 1,
            'Id_Master_Penjadwalan' => Hashids::connection('custom')->encode(1),
        ]);
        $req->setUserResolver(fn () => Auth::user());
        app()->instance('request', $req);

        $respons = app(UpdatePerformanceController::class)
            ->storeTemporaryPerformanceStep1ByTeam($req, $this->hashLuar());

        $this->assertSame(
            403,
            $respons->getStatusCode(),
            'Draf penilaian bisa disimpan untuk karyawan di luar tim hanya dengan mengganti id di URL.',
        );
    }

    public function test_finalisasi_menolak_karyawan_di_luar_tim(): void
    {
        $req = Request::create('/x', 'POST', [
            'Id_Update_Performance_Sementara' => Hashids::connection('custom')->encode(1),
        ]);
        $req->setUserResolver(fn () => Auth::user());
        app()->instance('request', $req);

        $respons = app(UpdatePerformanceController::class)
            ->finalisasiPerformanceByTeam($req, $this->hashLuar());

        $this->assertSame(
            403,
            $respons->getStatusCode(),
            'Finalisasi bisa dikirim untuk karyawan di luar tim. Menutup jalur draf saja tidak cukup — '.
            'draf dan finalisasi adalah dua permintaan terpisah.',
        );
    }

    public function test_anggota_tim_sendiri_tidak_ikut_tertolak(): void
    {
        // Jadikan orang itu anggota, lalu pastikan penolakannya BUKAN 403 lagi.
        // Permintaannya tetap gagal karena payload-nya karangan — yang diuji di
        // sini hanya bahwa gerbangnya tidak menutup orang yang berhak.
        DB::table('KPI_Karyawan_Team')->insert([
            'Kode_Karyawan_Leader' => $this->leader->Kode_Karyawan,
            'Kode_Karyawan_Team' => $this->orangLuar->Kode_Karyawan,
            'Flag_Aktif' => 'Y',
            'create_at' => now(),
        ]);

        $req = Request::create('/x', 'POST', [
            'Id_Update_Performance_Sementara' => Hashids::connection('custom')->encode(1),
        ]);
        $req->setUserResolver(fn () => Auth::user());
        app()->instance('request', $req);

        $respons = app(UpdatePerformanceController::class)
            ->finalisasiPerformanceByTeam($req, $this->hashLuar());

        $this->assertNotSame(
            403,
            $respons->getStatusCode(),
            'Anggota tim sendiri ikut tertolak — gerbangnya terlalu rapat.',
        );
    }

    private function hashLuar(): string
    {
        return Hashids::connection('custom')->encode((int) $this->orangLuar->UserID_Web);
    }
}
