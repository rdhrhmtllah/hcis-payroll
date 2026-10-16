<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use Tests\TestCase;

/**
 * Halaman penilaian tim tidak boleh digerbangi jadwal milik LEADER.
 *
 * Leader di sini berperan sebagai PENILAI, bukan yang dinilai. Jadwal yang
 * menentukan boleh-tidaknya sebuah penilaian diisi adalah jadwal ANGGOTA, dan
 * itu sudah divalidasi per anggota di dalam tab To Update.
 *
 * Gerbang lama menilai dari jadwal penilaian leader sendiri lalu menutup
 * SELURUH halaman dengan "Periode Penilaian Team Telah Berakhir". Selain
 * salah sasaran, itu juga ikut mengunci tab On Going dan Returned — approval
 * yang menunggu dan penolakan yang harus diperbaiki, dua hal yang memang
 * sengaja tidak bergantung pada jadwal.
 *
 * Nyata terjadi: leader H45 terkunci total padahal 9 anggotanya punya jadwal
 * perorangan yang sedang aktif.
 */
class HalamanTimTidakDigerbangiJadwalLeaderTest extends TestCase
{
    use DatabaseTransactions;

    private function komponenDari($response): string
    {
        $ref = new ReflectionClass($response);
        $prop = $ref->getProperty('component');
        $prop->setAccessible(true);

        return (string) $prop->getValue($response);
    }

    public function test_leader_tanpa_jadwal_aktif_tetap_bisa_membuka_halaman_tim(): void
    {
        // Leader yang punya anggota tim (parameter KPI terkirim & disetujui).
        $leaderId = DB::table('KPI_Pengajuan')
            ->where('Flag_Team', 'Y')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Approval', 'Y')
            ->whereNull('Status')
            ->whereNotNull('Id_User_Sender')
            ->value('Id_User_Sender');

        if (! $leaderId) {
            $this->markTestSkipped('Data dev tidak punya pengajuan tim yang disetujui.');
        }

        $leader = User::where('Id_Users', $leaderId)->first();

        if (! $leader) {
            $this->markTestSkipped('Leader tidak punya akun user.');
        }

        $kodeLeader = DB::table('Karyawan')->where('UserID_Web', $leaderId)->value('Kode_Karyawan');

        // Pastikan premisnya benar-benar terjadi: matikan semua jadwal
        // penilaian milik leader ini, supaya kalau gerbang lama masih ada,
        // testnya PASTI kena.
        DB::table('N_KPI_Master_Penjadwalan_Perorangan')
            ->where('Kode_Karyawan', $kodeLeader)
            ->update(['Flag_Aktif' => 'T']);

        $this->actingAs($leader);

        $komponen = $this->komponenDari(
            (new UpdatePerformanceController)->indexUpdatePerformanceTeam(),
        );

        $this->assertSame(
            'KPI/Performance/Update/team/HomeTeamPerformance',
            $komponen,
            'Leader tanpa jadwal aktif sendiri malah diarahkan ke halaman status. '
                .'Gerbang halaman kemungkinan masih menilai dari jadwal leader.',
        );
    }

    /**
     * Leader yang punya anggota di roster tetapi belum satu pun parameternya
     * disetujui HARUS bisa masuk.
     *
     * Gerbangnya dulu memakai definisi milik tab To Update saja, sehingga ia
     * menutup halaman tepat pada orang yang paling membutuhkannya: tab "Belum
     * Parameter" dibuat untuk menampilkan anggota tanpa parameter, lalu
     * disembunyikan di balik syarat "harus sudah punya parameter".
     */
    public function test_leader_dengan_roster_tanpa_pengajuan_disetujui_tetap_bisa_masuk(): void
    {
        $kandidat = DB::table('Karyawan as k')
            ->whereNotNull('k.UserID_Web')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('KPI_Karyawan_Team')
                    ->whereColumn('KPI_Karyawan_Team.Kode_Karyawan_Leader', 'k.Kode_Karyawan')
                    ->where('Flag_Aktif', 'Y');
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('KPI_Pengajuan')
                    ->whereColumn('KPI_Pengajuan.Id_User_Sender', 'k.UserID_Web')
                    ->where('Flag_Team', 'Y')
                    ->where('Flag_Aktif', 'Y')
                    ->where('Flag_Approval', 'Y')
                    ->whereNull('Status');
            })
            ->value('k.UserID_Web');

        if (! $kandidat) {
            $this->markTestSkipped('Tidak ada leader ber-roster tanpa pengajuan disetujui di data ini.');
        }

        $user = User::where('Id_Users', $kandidat)->first();

        if (! $user) {
            $this->markTestSkipped('Leader ber-roster itu tidak punya akun user.');
        }

        $this->actingAs($user);

        $this->assertSame(
            'KPI/Performance/Update/team/HomeTeamPerformance',
            $this->komponenDari((new UpdatePerformanceController)->indexUpdatePerformanceTeam()),
            'Leader dengan anggota di roster masih dikunci pesan "Belum Ada Anggota Tim".',
        );
    }

    public function test_pengguna_tanpa_anggota_tim_diarahkan_ke_halaman_status(): void
    {
        // Kebalikannya: gerbang kepemilikan tim harus tetap ada, kalau tidak
        // orang tanpa tim akan mendarat di halaman kosong tanpa penjelasan.
        //
        // "Tanpa tim" kini berarti DUA-DUANYA tidak ada: tidak punya pengajuan tim
        // disetujui DAN tidak punya roster. Sejak tab "Belum Parameter" bersumber
        // dari roster, leader yang hanya punya roster memang berhak masuk.
        $tanpaTim = DB::table('Karyawan as k')
            ->whereNotNull('k.UserID_Web')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('KPI_Pengajuan')
                    ->whereColumn('KPI_Pengajuan.Id_User_Sender', 'k.UserID_Web')
                    ->where('Flag_Team', 'Y')
                    ->where('Flag_Aktif', 'Y')
                    ->where('Flag_Approval', 'Y')
                    ->whereNull('Status');
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('KPI_Karyawan_Team')
                    ->whereColumn('KPI_Karyawan_Team.Kode_Karyawan_Leader', 'k.Kode_Karyawan')
                    ->where('Flag_Aktif', 'Y');
            })
            ->value('k.UserID_Web');

        if (! $tanpaTim) {
            $this->markTestSkipped('Semua karyawan punya tim.');
        }

        $user = User::where('Id_Users', $tanpaTim)->first();

        if (! $user) {
            $this->markTestSkipped('Karyawan tanpa tim tidak punya akun user.');
        }

        $this->actingAs($user);

        $this->assertSame(
            'KPI/components/ui/statusPage',
            $this->komponenDari((new UpdatePerformanceController)->indexUpdatePerformanceTeam()),
        );
    }
}
