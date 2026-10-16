<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Dashboard\StaffPerformanceDashboardController;
use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Deskripsi parameter harus sampai ke daftar parameter KPI / My Performance.
 *
 * Halaman menampilkan deskripsi tiap parameter (dipotong 2 baris, bisa dibuka
 * penuh). Backend-nya punya TIGA jalur penyusun daftar: mode bulanan, mode
 * YTD/Full-Year (agregat), dan parameter yang belum punya realisasi. Dulu hanya
 * jalur bulanan yang membawa Deskripsi, sehingga di mode lain teksnya hilang.
 *
 * Fixture tahun 2036 (bebas data nyata): parameter A punya realisasi Januari,
 * parameter B belum punya realisasi sama sekali.
 */
class DeskripsiParameterMyPerformanceTest extends TestCase
{
    use DatabaseTransactions;

    private const TAHUN = 2036;

    private int $idUser;

    /** @var array<string,string> nama parameter => deskripsi */
    private array $deskripsi = [
        'Parameter uji A' => 'Deskripsi A: persentase output sesuai rencana produksi bulanan.',
        'Parameter uji B' => 'Deskripsi B: belum ada realisasi, tetap harus terlihat.',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $karyawan = DB::table('Karyawan as k')
            ->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')
            ->where('k.Aktif', 'Y')
            ->select('k.UserID_Web', 'k.Kode_Karyawan')
            ->first();
        $formula = DB::table('KPI_Master_Formula')
            ->whereRaw('CAST(Rumus AS NVARCHAR(MAX)) = ?', ['[actualKpiEvo]/[targetKpiEvo]'])
            ->value('Id_Master_Formula');

        if (! $karyawan || ! $formula) {
            $this->markTestSkipped('Butuh satu karyawan dan rumus aktual/target.');
        }
        $this->idUser = (int) $karyawan->UserID_Web;

        $jadwal = (int) DB::table('KPI_Master_Penjadwalan')->insertGetId([
            'Nama_Kegiatan' => 'Uji deskripsi parameter',
            'Tahun' => self::TAHUN,
            'Periode' => self::TAHUN.'-01',
            'Flag_Form_KPI' => 'Y',
            'Flag_Aktif' => 'Y',
        ], 'Id_Master_Penjadwalan');

        $idPengajuan = (int) DB::table('KPI_Pengajuan')->insertGetId([
            'No_Transaksi' => 'UJI-DESK-'.bin2hex(random_bytes(4)),
            'Id_Penjadwalan' => $jadwal,
            'Id_User' => $this->idUser,
            'Tanggal' => self::TAHUN.'-01-05 00:00:00',
            'Jam' => '09:00:00',
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => 'Y',
            'Status' => null,
        ], 'Id_Pengajuan');

        $idUpdate = (int) DB::table('KPI_Update_Performance')->insertGetId([
            'No_Transaksi' => 'UJI-DESKU-'.bin2hex(random_bytes(4)),
            'Id_Pengajuan' => $idPengajuan,
            'Id_User' => $this->idUser,
            'Periode' => self::TAHUN.'-01',
            'Status_Pengajuan' => 'disetujui',
            'Flag_Approval' => 'Y',
            'Flag_Verifikasi' => 'Y',
            'Flag_Aktif' => 'Y',
            'Status' => null,
            'Total_Score' => 100,
            'Tanggal' => self::TAHUN.'-01-20 00:00:00',
        ], 'Id_Update_Performance');

        foreach ($this->deskripsi as $nama => $teks) {
            $idDetail = (int) DB::table('KPI_Pengajuan_Detail')->insertGetId([
                'Id_Pengajuan' => $idPengajuan,
                'Nama_Parameter' => $nama,
                'Deskripsi' => $teks,
                'Bobot' => 50,
                'Id_Master_Formula' => $formula,
                'Id_Master_Satuan' => DB::table('KPI_Master_Satuan')->value('Id_Master_Satuan'),
                'Id_Bsc_Kategori' => DB::table('KPI_Master_Bsc_Kategori')->value('Id_Master_Bsc_Kategori'),
                'Id_Master_Target' => DB::table('KPI_Master_Target')->where('Kode_Target', 'M')->value('Id_Master_Target'),
                'Kode_Karyawan' => $karyawan->Kode_Karyawan,
            ], 'Id_Pengajuan_Detail');

            $idDet = (int) DB::table('KPI_Pengajuan_Det')->insertGetId([
                'Id_Pengajuan_Detail' => $idDetail,
                'Nama_Target' => 'Target Jan',
                'Nilai_Target' => 100,
                'Periode' => self::TAHUN.'-01',
            ], 'Id_Pengajuan_Det');

            // Hanya A yang punya realisasi; B lewat jalur "parameter tanpa data".
            if ($nama === 'Parameter uji A') {
                DB::table('KPI_Update_Performance_Detail')->insert([
                    'Id_Update_Performance' => $idUpdate,
                    'Id_Pengajuan_Det' => $idDet,
                    'Nilai_Actual' => 90,
                ]);
            }
        }
    }

    /** @return array<string,?string> nama parameter => Deskripsi yang dikirim */
    private function deskripsiTerkirim(string $bulan): array
    {
        $this->actingAs(User::findOrFail($this->idUser));
        $permintaan = Request::create('/', 'GET', ['bulan' => $bulan, 'tahun' => (string) self::TAHUN]);
        $permintaan->setUserResolver(fn () => auth()->user());

        $hasil = json_decode(app(StaffPerformanceDashboardController::class)->getDataInformasiByUserId($permintaan)->getContent(), true);

        return collect(data_get($hasil, 'result.kpis', []))
            ->mapWithKeys(fn ($k) => [$k['name'] => $k['Deskripsi'] ?? null])
            ->all();
    }

    public function test_mode_full_year_membawa_deskripsi_semua_parameter(): void
    {
        $terkirim = $this->deskripsiTerkirim('all');

        foreach ($this->deskripsi as $nama => $teks) {
            $this->assertArrayHasKey($nama, $terkirim, "{$nama} tidak ada di daftar — fixture tak menguji apa pun.");
            $this->assertSame($teks, $terkirim[$nama], "Deskripsi {$nama} tidak terkirim di mode Full-Year.");
        }
    }

    public function test_mode_bulanan_membawa_deskripsi_semua_parameter(): void
    {
        $terkirim = $this->deskripsiTerkirim('01');

        foreach ($this->deskripsi as $nama => $teks) {
            $this->assertArrayHasKey($nama, $terkirim, "{$nama} tidak ada di daftar — fixture tak menguji apa pun.");
            $this->assertSame($teks, $terkirim[$nama], "Deskripsi {$nama} tidak terkirim di mode bulanan.");
        }
    }
}
