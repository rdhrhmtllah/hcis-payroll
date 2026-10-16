<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Keanggotaan tim di Update Performance Team berasal dari roster, bukan dari
 * siapa yang mengirim parameter.
 *
 * ── Aturan lama dan kerusakannya ────────────────────────────────────────────
 *
 * Dulu seluruh halaman memakai `KPI_Pengajuan.Id_User_Sender = leader`. Artinya
 * hanya orang yang kebetulan MENGINPUT parameter yang boleh menilai. Leader yang
 * sah kehilangan anggotanya sendiri begitu parameternya diinput orang lain —
 * dan itu keadaan yang lumrah di sini: dari 856 pengajuan hidup+disetujui di
 * Web_HRIS 10 September 2026, hanya 3 yang berstempel Flag_Team='Y'.
 *
 * Sekarang KPI_Karyawan_Team yang menentukan, dan itu berlaku DUA ARAH:
 * anggota roster masuk walau parameternya dikirim orang lain, dan orang di luar
 * roster tetap di luar walaupun leader ini sendiri yang mengirim parameternya.
 * Arah kedua itu penting: saringan pengirim dulu merangkap sebagai gerbang
 * kepemilikan, jadi membuangnya tanpa pengganti berarti siapa pun berkapabilitas
 * UpdatePerformanceTeam bisa menilai karyawan mana pun.
 */
class KeanggotaanTimDariRosterTest extends TestCase
{
    use DatabaseTransactions;

    private object $leader;

    private object $anggotaRoster;

    private object $bukanAnggota;

    private object $pengirimLain;

    private int $idJadwal;

    protected function setUp(): void
    {
        parent::setUp();

        $orang = DB::table('karyawan')
            ->join('View_Divisi_Sub_Divisi as vd', 'karyawan.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
            ->join(
                'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                'karyawan.ID_Level_Jabatan',
                '=',
                'vg.ID_Level_Jabatan',
            )
            ->whereNotNull('karyawan.UserID_Web')
            ->whereIn('karyawan.UserID_Web', DB::table('KPI_Users')->select('Id_Users'))
            ->whereNotExists(function ($q) {
                $q->from('KPI_Karyawan_Team')
                    ->whereColumn('KPI_Karyawan_Team.Kode_Karyawan_Leader', 'karyawan.Kode_Karyawan');
            })
            ->distinct()
            ->limit(4)
            ->get(['karyawan.UserID_Web', 'karyawan.Kode_Karyawan']);

        if ($orang->count() < 4) {
            $this->markTestSkipped('Butuh empat karyawan berakun yang belum jadi leader.');
        }

        [$this->leader, $this->anggotaRoster, $this->bukanAnggota, $this->pengirimLain] = $orang->all();

        DB::table('KPI_Karyawan_Team')->insert([
            'Kode_Karyawan_Leader' => $this->leader->Kode_Karyawan,
            'Kode_Karyawan_Team' => $this->anggotaRoster->Kode_Karyawan,
            'Flag_Aktif' => 'Y',
            'create_at' => now(),
        ]);

        $this->idJadwal = (int) DB::table('KPI_Master_Penjadwalan')->insertGetId([
            'Nama_Kegiatan' => 'Uji keanggotaan roster',
            'Tahun' => date('Y'),
            'Periode' => date('Y-m'),
            'Flag_Form_KPI' => 'Y',
            'Flag_Penilaian_KPI' => 'Y',
            'Flag_Perorangan' => 'T',
            'Flag_Aktif' => 'Y',
            'Jadwal_Mulai' => now()->subDays(3)->format('Y-m-d'),
            'Jadwal_Akhir' => now()->addDays(3)->format('Y-m-d'),
        ]);
    }

    public function test_anggota_roster_masuk_walau_parameternya_dikirim_orang_lain(): void
    {
        // Sengaja dikirim orang lain — persis keadaan yang dulu membuat anggota
        // ini tidak terlihat oleh leader-nya sendiri.
        $this->buatPengajuan((int) $this->anggotaRoster->UserID_Web, (int) $this->pengirimLain->UserID_Web);

        $pasangan = $this->pasanganToUpdate();

        $this->assertContains(
            $this->anggotaRoster->Kode_Karyawan,
            $pasangan->pluck('kode_karyawan')->all(),
            'Anggota roster tidak muncul karena parameternya diinput orang lain — '.
            'saringan pengirim kemungkinan hidup lagi.',
        );
    }

    public function test_orang_di_luar_roster_tetap_di_luar_walau_saya_yang_mengirim_parameternya(): void
    {
        // Kebalikannya, dan inilah gerbang kepemilikannya: leader ini sendiri yang
        // mengirim parameternya, tetapi orangnya bukan anggotanya.
        $this->buatPengajuan((int) $this->bukanAnggota->UserID_Web, (int) $this->leader->UserID_Web);

        $pasangan = $this->pasanganToUpdate();

        $this->assertNotContains(
            $this->bukanAnggota->Kode_Karyawan,
            $pasangan->pluck('kode_karyawan')->all(),
            'Orang di luar roster ikut terbawa — roster tidak lagi menjadi penentu.',
        );
    }

    public function test_jalur_detail_menolak_karyawan_di_luar_roster(): void
    {
        $this->buatPengajuan((int) $this->bukanAnggota->UserID_Web, (int) $this->leader->UserID_Web);

        $user = User::find((int) $this->leader->UserID_Web);

        if (! $user) {
            $this->markTestSkipped('Leader uji tidak punya akun KPI_Users.');
        }

        Auth::setUser($user);

        $hash = \Vinkla\Hashids\Facades\Hashids::connection('custom')
            ->encode((int) $this->bukanAnggota->UserID_Web);

        $req = Request::create('/x', 'POST', ['Id_Master_Penjadwalan' => null]);
        $req->setUserResolver(fn () => $user);
        app()->instance('request', $req);

        $respons = app(UpdatePerformanceController::class)
            ->getDataTemporaryDataStep1ByTeamUserId($req, $hash);

        $this->assertSame(
            403,
            $respons->getStatusCode(),
            'Jalur detail per anggota tidak lagi menjaga kepemilikan. Saringan pengirim dulu '.
            'merangkap gerbang; membuangnya tanpa pengganti membuka penilaian siapa pun.',
        );
    }

    private function pasanganToUpdate(): \Illuminate\Support\Collection
    {
        $m = new ReflectionMethod(UpdatePerformanceController::class, 'buildToUpdatePairs');
        $m->setAccessible(true);

        return $m->invoke(app(UpdatePerformanceController::class), (int) $this->leader->UserID_Web, null);
    }

    private function buatPengajuan(int $idUser, int $pengirim): void
    {
        DB::table('KPI_Pengajuan')->insert([
            'No_Transaksi' => 'UJI-ROS-'.substr(bin2hex(random_bytes(6)), 0, 10),
            'Id_Penjadwalan' => $this->idJadwal,
            'Id_User' => $idUser,
            'Id_User_Sender' => $pengirim,
            'Flag_Team' => 'Y',
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => 'Y',
            'Status' => null,
            'Tanggal' => now(),
        ]);
    }
}
