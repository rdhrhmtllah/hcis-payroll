<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Dashboard\StaffPerformanceDashboardController;
use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Deskripsi parameter di modal "Detail Employee Performance".
 *
 * Kartu per parameter dulu hanya menyebut NAMA parameter, sementara kalimat
 * yang menjelaskan cara parameter itu dinilai tersimpan di
 * KPI_Pengajuan_Detail.Deskripsi dan tidak pernah sampai ke layar. Dua jalur
 * detail (bulanan dan YTD) merakit barisnya sendiri-sendiri, jadi keduanya
 * dijaga di sini — sebelumnya jalur YTD yang paling mudah tertinggal.
 */
class DeskripsiParameterDetailTest extends TestCase
{
    use DatabaseTransactions;

    private const TAHUN = 2019;

    private const DESKRIPSI = 'Dihitung dari jumlah tiket selesai dibagi tiket masuk pada bulan berjalan.';

    private object $karyawan;

    private int $idPengajuan;

    private int $idUpdate;

    protected function setUp(): void
    {
        parent::setUp();

        $karyawan = DB::table('Karyawan as k')
            ->join('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_Divisi_Sub_Divisi')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'k.Id_Level_Jabatan', '=', 'vg.ID_Level_Jabatan')
            ->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')
            ->where('k.Aktif', 'Y')
            ->select('k.UserID_Web', 'k.Kode_Karyawan')
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
            'Nama_Kegiatan' => 'Uji deskripsi parameter',
            'Tahun' => self::TAHUN,
            'Periode' => self::TAHUN.'-01',
            'Flag_Form_KPI' => 'Y',
            'Flag_Aktif' => 'Y',
        ], 'Id_Master_Penjadwalan');

        $this->idPengajuan = (int) DB::table('KPI_Pengajuan')->insertGetId([
            'No_Transaksi' => 'UJI-DESK-'.bin2hex(random_bytes(4)),
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
            'Nama_Parameter' => 'Parameter uji deskripsi',
            'Deskripsi' => self::DESKRIPSI,
            'Bobot' => 100,
            'Id_Master_Formula' => $formula,
            'Id_Master_Satuan' => DB::table('KPI_Master_Satuan')->value('Id_Master_Satuan'),
            'Id_Bsc_Kategori' => DB::table('KPI_Master_Bsc_Kategori')->value('Id_Master_Bsc_Kategori'),
            'Id_Master_Target' => DB::table('KPI_Master_Target')->where('Kode_Target', 'M')->value('Id_Master_Target'),
            'Kode_Karyawan' => $karyawan->Kode_Karyawan,
        ], 'Id_Pengajuan_Detail');

        $idDet = (int) DB::table('KPI_Pengajuan_Det')->insertGetId([
            'Id_Pengajuan_Detail' => $idDetail,
            'Nama_Target' => 'Target Januari',
            'Nilai_Target' => 100,
            'Periode' => self::TAHUN.'-01',
        ], 'Id_Pengajuan_Det');

        $this->idUpdate = (int) DB::table('KPI_Update_Performance')->insertGetId([
            'No_Transaksi' => 'UJI-DESKU-'.bin2hex(random_bytes(4)),
            'Id_Pengajuan' => $this->idPengajuan,
            'Id_User' => $karyawan->UserID_Web,
            'Periode' => self::TAHUN.'-01',
            'Status_Pengajuan' => 'disetujui',
            'Flag_Approval' => 'Y',
            'Flag_Verifikasi' => 'Y',
            'Flag_Aktif' => 'Y',
            'Status' => null,
            'Total_Score' => 105,
            'Id_Performance' => $performa,
            'Tanggal' => self::TAHUN.'-01-20 00:00:00',
            'Jam' => '10:00:00',
        ], 'Id_Update_Performance');

        DB::table('KPI_Update_Performance_Detail')->insert([
            'Id_Update_Performance' => $this->idUpdate,
            'Id_Pengajuan_Det' => $idDet,
            'Nilai_Actual' => 105,
        ]);
    }

    private function detail(string $isyear): array
    {
        $this->actingAs(User::findOrFail($this->karyawan->UserID_Web));

        return app(StaffPerformanceDashboardController::class)->recentKpiUserDetail(
            Hashids::connection('custom')->encode($this->idPengajuan),
            Hashids::connection('custom')->encode($this->idUpdate),
            $isyear,
        )->getData(true);
    }

    public function test_detail_bulanan_membawa_deskripsi_parameter(): void
    {
        $baris = collect(data_get($this->detail('false'), 'result.details', []))
            ->firstWhere('parameter', 'Parameter uji deskripsi');

        $this->assertNotNull($baris, 'Parameter uji tidak ikut di detail bulanan.');
        $this->assertSame(self::DESKRIPSI, $baris['deskripsi']);
    }

    /** Jalur YTD merakit barisnya sendiri; deskripsi tidak boleh hilang di sana. */
    public function test_detail_ytd_membawa_deskripsi_parameter(): void
    {
        $baris = collect(data_get($this->detail('ytd'), 'result.details', []))
            ->firstWhere('parameter', 'Parameter uji deskripsi');

        $this->assertNotNull($baris, 'Parameter uji tidak ikut di detail YTD.');
        $this->assertSame(self::DESKRIPSI, $baris['deskripsi']);
    }

    /** Parameter tanpa deskripsi menjawab null, bukan string kosong: UI menggantung blok itu pada null. */
    public function test_parameter_tanpa_deskripsi_menjawab_null(): void
    {
        DB::table('KPI_Pengajuan_Detail')
            ->where('Id_Pengajuan', $this->idPengajuan)
            ->update(['Deskripsi' => '   ']);

        $baris = collect(data_get($this->detail('false'), 'result.details', []))
            ->firstWhere('parameter', 'Parameter uji deskripsi');

        $this->assertNull($baris['deskripsi']);
    }
}
