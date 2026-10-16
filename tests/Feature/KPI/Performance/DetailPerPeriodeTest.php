<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Dashboard\Executive\DashboardCeoController;
use App\Http\Controllers\KPI\Performance\Dashboard\StaffPerformanceDashboardController;
use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Ganti periode di dalam modal "Detail Employee Performance".
 *
 * Modal dulu terkunci pada satu transaksi. Endpoint detail-periode memilih
 * transaksi rujukan per periode lalu memakai ulang recentKpiUserDetail(), jadi
 * isinya HARUS sama persis dengan detail yang dibuka dari daftar; strip 12 bulan
 * memberi status tiap bulan sebelum diklik.
 *
 * Fixture tahun 2019 (lampau, bebas data nyata — tahun mendatang membuat semua
 * bulan "mendatang"): Jan disetujui & terverifikasi, Feb menunggu, Mar punya
 * target tanpa update, Apr tanpa target.
 */
class DetailPerPeriodeTest extends TestCase
{
    use DatabaseTransactions;

    private const TAHUN = 2019;

    private object $karyawan;

    private int $idPengajuan;

    private int $idUpdateJan;

    protected function setUp(): void
    {
        parent::setUp();

        $karyawan = DB::table('Karyawan as k')
            ->join('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_Divisi_Sub_Divisi')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'k.Id_Level_Jabatan', '=', 'vg.ID_Level_Jabatan')
            ->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')
            ->where('k.Aktif', 'Y')
            ->select('k.UserID_Web', 'k.Kode_Karyawan', 'k.Nama')
            ->first();
        $formula = DB::table('KPI_Master_Formula')
            ->whereRaw('CAST(Rumus AS NVARCHAR(MAX)) = ?', ['[actualKpiEvo]/[targetKpiEvo]'])
            ->value('Id_Master_Formula');
        $performa = DB::table('KPI_Master_Performance')->orderByDesc('Range_Akhir')->value('Id_Master_Performance');

        if (! $karyawan || ! $formula || ! $performa) {
            $this->markTestSkipped('Butuh karyawan lengkap, rumus aktual/target, dan master performance.');
        }
        $this->karyawan = $karyawan;

        $jadwal = (int) DB::table('KPI_Master_Penjadwalan')->insertGetId([
            'Nama_Kegiatan' => 'Uji detail per periode',
            'Tahun' => self::TAHUN,
            'Periode' => self::TAHUN.'-01',
            'Flag_Form_KPI' => 'Y',
            'Flag_Aktif' => 'Y',
        ], 'Id_Master_Penjadwalan');

        $this->idPengajuan = (int) DB::table('KPI_Pengajuan')->insertGetId([
            'No_Transaksi' => 'UJI-DPP-'.bin2hex(random_bytes(4)),
            'Id_Penjadwalan' => $jadwal,
            'Id_User' => $karyawan->UserID_Web,
            'Tanggal' => self::TAHUN.'-01-05 00:00:00',
            'Jam' => '09:00:00',
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => 'Y',
            'Status' => null,
        ], 'Id_Pengajuan');

        $idDetail = (int) DB::table('KPI_Pengajuan_Detail')->insertGetId([
            'Id_Pengajuan' => $this->idPengajuan,
            'Nama_Parameter' => 'Parameter uji periode',
            'Bobot' => 100,
            'Id_Master_Formula' => $formula,
            'Id_Master_Satuan' => DB::table('KPI_Master_Satuan')->value('Id_Master_Satuan'),
            'Id_Bsc_Kategori' => DB::table('KPI_Master_Bsc_Kategori')->value('Id_Master_Bsc_Kategori'),
            'Id_Master_Target' => DB::table('KPI_Master_Target')->where('Kode_Target', 'M')->value('Id_Master_Target'),
            'Kode_Karyawan' => $karyawan->Kode_Karyawan,
        ], 'Id_Pengajuan_Detail');

        // Target Jan, Feb, Mar. April sengaja tanpa target.
        $det = [];
        foreach (['01', '02', '03'] as $b) {
            $det[$b] = (int) DB::table('KPI_Pengajuan_Det')->insertGetId([
                'Id_Pengajuan_Detail' => $idDetail,
                'Nama_Target' => 'Target '.$b,
                'Nilai_Target' => 100,
                'Periode' => self::TAHUN.'-'.$b,
            ], 'Id_Pengajuan_Det');
        }

        $update = function (string $bulan, bool $selesai) use ($karyawan, $performa, $det) {
            $id = (int) DB::table('KPI_Update_Performance')->insertGetId([
                'No_Transaksi' => 'UJI-DPPU-'.bin2hex(random_bytes(4)),
                'Id_Pengajuan' => $this->idPengajuan,
                'Id_User' => $karyawan->UserID_Web,
                'Periode' => self::TAHUN.'-'.$bulan,
                'Status_Pengajuan' => $selesai ? 'disetujui' : 'menunggu',
                'Flag_Approval' => $selesai ? 'Y' : null,
                'Flag_Verifikasi' => $selesai ? 'Y' : null,
                'Flag_Aktif' => 'Y',
                'Status' => null,
                'Total_Score' => 105,
                'Id_Performance' => $performa,
                'Tanggal' => self::TAHUN.'-'.$bulan.'-20 00:00:00',
                'Jam' => '10:00:00',
            ], 'Id_Update_Performance');
            DB::table('KPI_Update_Performance_Detail')->insert([
                'Id_Update_Performance' => $id,
                'Id_Pengajuan_Det' => $det[$bulan],
                'Nilai_Actual' => 105,
            ]);

            return $id;
        };

        $this->idUpdateJan = $update('01', true);
        $update('02', false);
    }

    private function hash(int $id): string
    {
        return Hashids::connection('custom')->encode($id);
    }

    private function staff(string $periode): array
    {
        $this->actingAs(User::findOrFail($this->karyawan->UserID_Web));
        $r = Request::create('/', 'GET', ['tahun' => self::TAHUN, 'periode' => $periode]);
        $r->setUserResolver(fn () => auth()->user());

        return app(StaffPerformanceDashboardController::class)->detailPerPeriode($r)->getData(true);
    }

    public function test_strip_navigasi_memberi_status_tiap_bulan(): void
    {
        $status = collect(data_get($this->staff('01'), 'result.navigasi', []))->pluck('status', 'bulan');

        $this->assertSame('terisi', $status[1]);
        $this->assertSame('menunggu', $status[2]);
        $this->assertSame('belum', $status[3]);
        $this->assertSame('tanpa_target', $status[4]);
    }

    public function test_isi_bulan_terisi_sama_dengan_detail_yang_dibuka_dari_daftar(): void
    {
        $baru = $this->staff('01');
        $this->actingAs(User::findOrFail($this->karyawan->UserID_Web));
        $lama = app(StaffPerformanceDashboardController::class)
            ->recentKpiUserDetail($this->hash($this->idPengajuan), $this->hash($this->idUpdateJan), 'false')
            ->getData(true);

        $this->assertFalse(data_get($baru, 'result.kosong'));
        $this->assertSame(data_get($lama, 'result.header.Achievement'), data_get($baru, 'result.header.Achievement'));
        $this->assertCount(count(data_get($lama, 'result.details', [])), data_get($baru, 'result.details', []));
        $this->assertSame('Januari '.self::TAHUN, data_get($baru, 'result.periode.label'));
    }

    public function test_bulan_tanpa_isi_dijelaskan_bukan_galat(): void
    {
        $maret = $this->staff('03');
        $this->assertSame(200, $maret['status']);
        $this->assertTrue(data_get($maret, 'result.kosong'));
        $this->assertSame('belum', data_get($maret, 'result.status_kosong'));

        $this->assertSame('tanpa_target', data_get($this->staff('04'), 'result.status_kosong'));
        $this->assertSame('menunggu', data_get($this->staff('02'), 'result.status_kosong'));
    }

    public function test_full_year_memakai_pengajuan_berlaku(): void
    {
        $full = $this->staff('full');

        $this->assertFalse(data_get($full, 'result.kosong'));
        $this->assertSame('Full Year '.self::TAHUN, data_get($full, 'result.periode.label'));
        $this->assertSame($this->hash($this->idPengajuan), data_get($full, 'result.rujukan.id_pengajuan'));
    }

    /**
     * bulanBertargetSetahun() adalah classify versi 12-bulan-sekaligus (demi
     * kinerja strip). Keduanya harus menjawab sama untuk setiap bulan, supaya
     * aturan "punya target" tetap satu walau jalannya dua.
     */
    public function test_bulan_bertarget_setahun_setara_dengan_aturan_resmi(): void
    {
        $svc = app(\App\Services\KPI\KpiEnforcementService::class);
        $idUser = (int) $this->karyawan->UserID_Web;
        $setahun = $svc->bulanBertargetSetahun($idUser, self::TAHUN);

        foreach (range(1, 12) as $bulan) {
            $kelas = $svc->classifyParameterTargetForPeriod([$idUser], \Carbon\Carbon::create(self::TAHUN, $bulan, 1))[$idUser] ?? 'no_parameter';
            $this->assertSame($kelas === 'has', in_array($bulan, $setahun, true), "Bulan {$bulan} berbeda dari aturan resmi.");
        }
    }

    /** Dashboard Tim: karyawannya dari transaksi rujukan, bukan dari user yang login. */
    public function test_dashboard_tim_menurunkan_karyawan_dari_rujukan(): void
    {
        $lain = DB::table('KPI_Users')->where('Id_Users', '!=', $this->karyawan->UserID_Web)->value('Id_Users');
        $this->actingAs(User::findOrFail($lain));
        $r = Request::create('/', 'GET', ['tahun' => self::TAHUN, 'periode' => 'full', 'ref_update' => $this->hash($this->idUpdateJan)]);
        $r->setUserResolver(fn () => auth()->user());

        $hasil = app(DashboardCeoController::class)->detailPerPeriode($r)->getData(true);

        $this->assertFalse(data_get($hasil, 'result.kosong'));
        $this->assertSame(1, collect(data_get($hasil, 'result.navigasi'))->where('status', 'terisi')->count());
        $this->assertSame($this->hash($this->idPengajuan), data_get($hasil, 'result.rujukan.id_pengajuan'));
    }
}
