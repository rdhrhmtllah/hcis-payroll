<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Parameter\FormKpiController;
use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Pengajuan di jadwal NON-parameter tidak boleh dianggap pengisian parameter.
 *
 * Pencarian pengajuan parameter memakai TAHUN jadwal, bukan id jadwal — itu
 * perlu, karena anggota berjadwal perorangan menyimpan ke jadwal miliknya
 * sendiri. Tapi tahun saja terlalu longgar: jadwal update performance hidup di
 * tahun yang sama, dan pengajuan yang bernaung di sana ikut tertarik masuk.
 *
 * Akibatnya anggota tampil "sudah mengisi" dengan 0 KPI dan tanpa approver —
 * padahal parameternya belum disentuh dan approver-nya sebenarnya sudah diatur.
 * Badge "TIDAK ADA APPROVAL" muncul untuk orang yang justru punya approver.
 *
 * Pembatasnya Flag_Form_KPI = 'Y'.
 */
class JadwalNonParameterTidakIkutTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_pengajuan_di_jadwal_update_performance_tidak_dihitung(): void
    {
        // Jadwal pengisian parameter yang akan dibuka leader.
        $jadwalParam = DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Form_KPI', 'Y')
            ->orderByDesc('Id_Master_Penjadwalan')
            ->first();

        if (! $jadwalParam) {
            $this->markTestSkipped('Tidak ada jadwal pengisian parameter yang aktif.');
        }

        // Jadwal NON-parameter di tahun yang sama — inilah pengganggunya.
        $jadwalLain = DB::table('KPI_Master_Penjadwalan')
            ->where('Tahun', $jadwalParam->Tahun)
            ->where(function ($q) {
                $q->whereNull('Flag_Form_KPI')->orWhere('Flag_Form_KPI', '!=', 'Y');
            })
            ->value('Id_Master_Penjadwalan');

        if (! $jadwalLain) {
            $this->markTestSkipped('Tidak ada jadwal non-parameter di tahun yang sama.');
        }

        // Anggota tim yang belum punya pengajuan parameter hidup.
        $anggota = DB::table('KPI_Karyawan_Team as kt')
            ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'kt.Kode_Karyawan_Team')
            ->join('Karyawan as kl', 'kl.Kode_Karyawan', '=', 'kt.Kode_Karyawan_Leader')
            ->where('kt.Flag_Aktif', 'Y')
            ->whereNotNull('k.UserId_Web')
            ->whereNotNull('kl.UserId_Web')
            ->select('k.Kode_Karyawan', 'k.UserId_Web', 'kl.UserId_Web as leader_user')
            ->first();

        if (! $anggota) {
            $this->markTestSkipped('Tidak ada anggota tim yang bisa dipakai.');
        }

        $leader = User::where('Id_Users', $anggota->leader_user)->first();

        if (! $leader) {
            $this->markTestSkipped('Leader tidak punya akun user.');
        }

        // Bersihkan pengajuan parameter hidup miliknya supaya premisnya jelas:
        // satu-satunya pengajuan hidup adalah yang bernaung di jadwal NON-parameter.
        DB::table('KPI_Pengajuan')
            ->where('Id_User', $anggota->UserId_Web)
            ->where('Flag_Aktif', 'Y')
            ->whereNull('Status')
            ->update(['Flag_Aktif' => 'T', 'Status' => 'Y']);

        DB::table('KPI_Pengajuan')->insert([
            'No_Transaksi' => 'UJI-NONPARAM-'.$anggota->UserId_Web,
            'Id_Penjadwalan' => $jadwalLain,
            'Id_User' => $anggota->UserId_Web,
            'Tanggal' => now()->format('Y-m-d'),
            'Jam' => now()->format('H:i:s'),
            'Flag_Aktif' => 'Y',
            'Status' => null,
            'Flag_Approval' => 'Y',
        ]);

        $this->actingAs($leader);

        $hasil = json_decode(
            (new FormKpiController)
                ->getDataCurrentTeam(Hashids::connection('custom')->encode($jadwalParam->Id_Master_Penjadwalan))
                ->getContent(),
            true,
        );

        $baris = collect($hasil['result'] ?? [])->firstWhere('employeeId', $anggota->Kode_Karyawan);

        $this->assertNotNull($baris, 'Anggota tidak muncul di grid tim.');

        $this->assertFalse(
            $baris['hasSubmittedKPI'],
            'Pengajuan di jadwal NON-parameter ikut dihitung sebagai pengisian parameter. '
                .'Anggota jadi tampil sudah mengisi dengan 0 KPI dan tanpa approver.',
        );
    }
}
