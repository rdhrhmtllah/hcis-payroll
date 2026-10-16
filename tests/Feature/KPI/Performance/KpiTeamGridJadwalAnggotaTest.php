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
 * Grid tim harus menemukan pengajuan anggota MESKIPUN tersimpan di jadwal lain.
 *
 * Sejak bulan mulai ditentukan per anggota, satu penyimpanan dari satu form tim
 * bisa menghasilkan Id_Penjadwalan yang BERBEDA antar anggota: yang punya jadwal
 * perorangan memakai jadwalnya sendiri, sisanya memakai jadwal global. Pembaca
 * yang mengunci Id_Penjadwalan ke jadwal yang sedang dibuka leader jadi buta
 * terhadap anggota kelompok pertama — barisnya tampil "Belum Ada KPI" padahal
 * pengajuannya ada, sudah aktif, dan approver-nya sudah dinotifikasi.
 *
 * Patokan yang benar adalah aturan identitas parameter yang berlaku di modul ini:
 * Flag_Aktif='Y' + Status IS NULL + TAHUN jadwal — bukan id jadwalnya. Aturan yang
 * sama sudah dipakai cariPengajuanParameterDigantikan() saat membatalkan pengajuan
 * lama, jadi tanpa perbaikan ini tulis dan baca memakai dua definisi berbeda.
 */
class KpiTeamGridJadwalAnggotaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /**
     * Cari leader yang punya anggota aktif, jadwal Form-KPI aktif untuk dibuka,
     * dan jadwal LAIN di tahun yang sama untuk menampung pengajuan anggota.
     *
     * @return array{leader: object, anggota: object, dibuka: object, jadwalLain: int}|null
     */
    private function cariSkenario(): ?array
    {
        $jadwalDibuka = DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Form_KPI', 'Y')
            ->orderByDesc('Id_Master_Penjadwalan')
            ->first();

        if (! $jadwalDibuka) {
            return null;
        }

        // Jadwal lain, tahun sama. Inilah yang ditempati anggota berjadwal perorangan.
        $jadwalLain = DB::table('KPI_Master_Penjadwalan')
            ->where('Tahun', $jadwalDibuka->Tahun)
            ->where('Id_Master_Penjadwalan', '!=', $jadwalDibuka->Id_Master_Penjadwalan)
            ->value('Id_Master_Penjadwalan');

        if (! $jadwalLain) {
            return null;
        }

        // Leader harus punya akun user (controller membaca Auth::user()->karyawan).
        $tim = DB::table('KPI_Karyawan_Team as kt')
            ->join('Karyawan as kl', 'kl.Kode_Karyawan', '=', 'kt.Kode_Karyawan_Leader')
            ->join('Karyawan as ka', 'ka.Kode_Karyawan', '=', 'kt.Kode_Karyawan_Team')
            ->join('View_Divisi_Sub_Divisi as vd', 'ka.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'ka.ID_Level_Jabatan', '=', 'vg.ID_Level_Jabatan')
            ->where('kt.Flag_Aktif', 'Y')
            ->whereNotNull('kl.UserId_Web')
            ->whereNotNull('ka.UserId_Web')
            ->select(
                'kt.Kode_Karyawan_Leader',
                'kl.UserId_Web as leader_user',
                'ka.Kode_Karyawan as anggota_kode',
                'ka.UserId_Web as anggota_user',
            )
            ->first();

        if (! $tim) {
            return null;
        }

        $leader = User::where('Id_Users', $tim->leader_user)->first();

        if (! $leader) {
            return null;
        }

        return [
            'leader' => $leader,
            'anggota' => $tim,
            'dibuka' => $jadwalDibuka,
            'jadwalLain' => (int) $jadwalLain,
        ];
    }

    public function test_anggota_yang_tersimpan_di_jadwal_lain_tetap_terlihat_di_grid(): void
    {
        $skenario = $this->cariSkenario();

        if (! $skenario) {
            $this->markTestSkipped('Data dev tidak punya kombinasi leader/tim/dua jadwal setahun.');
        }

        // Bersihkan pengajuan hidup anggota ini supaya premis tesnya jelas:
        // satu-satunya pengajuan hidup adalah yang kita tanam di jadwal lain.
        DB::table('KPI_Pengajuan')
            ->where('Id_User', $skenario['anggota']->anggota_user)
            ->where('Flag_Aktif', 'Y')
            ->whereNull('Status')
            ->update(['Flag_Aktif' => 'T', 'Status' => 'Y']);

        $idPengajuan = DB::table('KPI_Pengajuan')->insertGetId([
            'No_Transaksi' => 'UJI-GRID-'.$skenario['anggota']->anggota_user,
            'Id_Penjadwalan' => $skenario['jadwalLain'],
            'Id_User' => $skenario['anggota']->anggota_user,
            'Tanggal' => now()->format('Y-m-d'),
            'Jam' => now()->format('H:i:s'),
            'Flag_Aktif' => 'Y',
            'Status' => null,
            'Flag_Team' => 'Y',
            'Id_User_Sender' => $skenario['leader']->Id_Users,
        ], 'Id_Pengajuan');

        $this->assertNotEmpty($idPengajuan, 'Gagal menanam pengajuan uji.');

        $this->actingAs($skenario['leader']);

        $response = (new FormKpiController)->getDataCurrentTeam(
            Hashids::connection('custom')->encode($skenario['dibuka']->Id_Master_Penjadwalan),
        );

        $isi = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode(), 'Grid tim gagal dimuat: '.$response->getContent());

        $baris = collect($isi['result'])
            ->firstWhere('employeeId', $skenario['anggota']->anggota_kode);

        $this->assertNotNull(
            $baris,
            'Anggota tidak muncul sama sekali di grid tim.',
        );

        $this->assertTrue(
            $baris['hasSubmittedKPI'],
            'Pengajuan anggota tersimpan di jadwal lain pada tahun yang sama, '
                .'tapi grid masih menyebutnya belum mengisi. Pembaca kemungkinan '
                .'masih mengunci Id_Penjadwalan ke jadwal yang dibuka leader.',
        );
    }
}
