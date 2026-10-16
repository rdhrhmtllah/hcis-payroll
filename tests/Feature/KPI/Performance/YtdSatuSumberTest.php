<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Dashboard\Executive\DashboardCeoController;
use App\Http\Controllers\KPI\Performance\Dashboard\StaffPerformanceDashboardController;
use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\KpiAkumulasiYtdService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Semua layar YTD/Full-Year harus menjawab angka yang SAMA.
 *
 * ── Kenapa ──────────────────────────────────────────────────────────────────
 *
 * KpiAkumulasiYtdService sudah dipakai Monitoring, homepage, dan export, tetapi
 * dashboard CEO (daftar & modal), dashboard staff (agregat, titik grafik, modal),
 * dan `kpi:verify-ytd` masih menghitung sendiri. Selisih yang terbukti di dev:
 *
 *   - Dashboard CEO menjumlahkan TARGET semua bulan dalam rentang, termasuk bulan
 *     tanpa realisasi (dan pada Full-Year: bulan yang belum datang). Sumber
 *     hanya memasangkan target dengan bulan yang punya realisasi.
 *   - Modal dashboard staff tidak menyaring Flag_Aktif, sehingga realisasi yang
 *     sudah digantikan revisi ikut terjumlah.
 *
 * ── Fixture ─────────────────────────────────────────────────────────────────
 *
 * Satu karyawan, tahun 2036 (bebas data nyata), dua parameter berbobot 50/50,
 * rumus aktual/target, target 100 per bulan:
 *
 *   A: realisasi Jan & Feb 100 + target Mar TANPA realisasi
 *      sumber: 200/200 = 100%        lama CEO: 200/300 = 66,7%
 *   B: realisasi Jan & Feb 60
 *      sumber: 120/200 =  60%
 *   + satu update Jan yang sudah digantikan (Flag_Aktif='T'), aktual A = 1000.
 *
 *   Skor resmi = (100 + 60) / 2 = 80,0
 *
 * Pintu yang sejak awal sudah sama bentuknya dengan sumber (agregat staff,
 * `kpi:verify-ytd`) tetap diuji: yang dijaga adalah bahwa semuanya SAMA, bukan
 * hanya bahwa yang dulu salah kini benar.
 */
class YtdSatuSumberTest extends TestCase
{
    use DatabaseTransactions;

    private const TAHUN = 2036;

    private const SKOR_RESMI = 80.0;

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
            ->select('k.UserID_Web', 'k.Kode_Karyawan', 'k.Nama')
            ->first();
        // Rumus bertipe text: tak bisa dibandingkan dengan '=' tanpa CAST.
        $formulaRasio = DB::table('KPI_Master_Formula')
            ->whereRaw('CAST(Rumus AS NVARCHAR(MAX)) = ?', ['[actualKpiEvo]/[targetKpiEvo]'])
            ->value('Id_Master_Formula');

        if (! $karyawan || ! $formulaRasio) {
            $this->markTestSkipped('Butuh satu karyawan lengkap dan rumus aktual/target.');
        }
        $this->karyawan = $karyawan;

        $jadwal = (int) DB::table('KPI_Master_Penjadwalan')->insertGetId([
            'Nama_Kegiatan' => 'Uji YTD satu sumber',
            'Tahun' => self::TAHUN,
            'Periode' => self::TAHUN.'-01',
            'Flag_Form_KPI' => 'Y',
            'Flag_Aktif' => 'Y',
        ], 'Id_Master_Penjadwalan');

        $this->idPengajuan = (int) DB::table('KPI_Pengajuan')->insertGetId([
            'No_Transaksi' => 'UJI-YTD-'.bin2hex(random_bytes(4)),
            'Id_Penjadwalan' => $jadwal,
            'Id_User' => $karyawan->UserID_Web,
            'Tanggal' => self::TAHUN.'-01-05 00:00:00',
            'Jam' => '09:00:00',
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => 'Y',
            'Status' => null,
        ], 'Id_Pengajuan');

        $parameter = [];
        foreach (['A' => 100, 'B' => 60] as $nama => $aktualPerBulan) {
            $parameter[$nama] = [
                'id' => (int) DB::table('KPI_Pengajuan_Detail')->insertGetId([
                    'Id_Pengajuan' => $this->idPengajuan,
                    'Nama_Parameter' => 'Parameter uji '.$nama,
                    'Bobot' => 50,
                    'Id_Master_Formula' => $formulaRasio,
                    'Id_Master_Satuan' => DB::table('KPI_Master_Satuan')->value('Id_Master_Satuan'),
                    'Id_Bsc_Kategori' => DB::table('KPI_Master_Bsc_Kategori')->value('Id_Master_Bsc_Kategori'),
                    'Id_Master_Target' => DB::table('KPI_Master_Target')->where('Kode_Target', 'M')->value('Id_Master_Target'),
                    'Kode_Karyawan' => $karyawan->Kode_Karyawan,
                ], 'Id_Pengajuan_Detail'),
                'aktual' => $aktualPerBulan,
            ];
        }

        $detPerPeriode = [];
        foreach ([self::TAHUN.'-01', self::TAHUN.'-02'] as $periode) {
            $this->idUpdate = (int) DB::table('KPI_Update_Performance')->insertGetId([
                'No_Transaksi' => 'UJI-YTDU-'.bin2hex(random_bytes(4)),
                'Id_Pengajuan' => $this->idPengajuan,
                'Id_User' => $karyawan->UserID_Web,
                'Periode' => $periode,
                'Status_Pengajuan' => 'disetujui',
                'Flag_Approval' => 'Y',
                'Flag_Verifikasi' => 'Y',
                'Flag_Aktif' => 'Y',
                'Status' => null,
                'Total_Score' => 100,
                'Tanggal' => $periode.'-20 00:00:00',
            ], 'Id_Update_Performance');

            foreach ($parameter as $p) {
                $idDet = (int) DB::table('KPI_Pengajuan_Det')->insertGetId([
                    'Id_Pengajuan_Detail' => $p['id'],
                    'Nama_Target' => 'Target '.$periode,
                    'Nilai_Target' => 100,
                    'Periode' => $periode,
                ], 'Id_Pengajuan_Det');

                DB::table('KPI_Update_Performance_Detail')->insert([
                    'Id_Update_Performance' => $this->idUpdate,
                    'Id_Pengajuan_Det' => $idDet,
                    'Nilai_Actual' => $p['aktual'],
                ]);

                $detPerPeriode[$periode][$p['id']] = $idDet;
            }
        }

        // Target Maret untuk A, TANPA realisasi.
        DB::table('KPI_Pengajuan_Det')->insert([
            'Id_Pengajuan_Detail' => $parameter['A']['id'],
            'Nama_Target' => 'Target '.self::TAHUN.'-03',
            'Nilai_Target' => 100,
            'Periode' => self::TAHUN.'-03',
        ]);

        // Update Januari yang sudah digantikan revisi: tidak boleh terhitung.
        $digantikan = (int) DB::table('KPI_Update_Performance')->insertGetId([
            'No_Transaksi' => 'UJI-YTDX-'.bin2hex(random_bytes(4)),
            'Id_Pengajuan' => $this->idPengajuan,
            'Id_User' => $karyawan->UserID_Web,
            'Periode' => self::TAHUN.'-01',
            'Status_Pengajuan' => 'disetujui',
            'Flag_Approval' => 'Y',
            'Flag_Verifikasi' => 'Y',
            'Flag_Aktif' => 'T',
            'Status' => null,
            'Total_Score' => 125,
            'Tanggal' => self::TAHUN.'-01-10 00:00:00',
        ], 'Id_Update_Performance');
        DB::table('KPI_Update_Performance_Detail')->insert([
            'Id_Update_Performance' => $digantikan,
            'Id_Pengajuan_Det' => $detPerPeriode[self::TAHUN.'-01'][$parameter['A']['id']],
            'Nilai_Actual' => 1000,
        ]);
    }

    private function hash(int $id): string
    {
        return Hashids::connection('custom')->encode($id);
    }

    private function isiJson($respons): array
    {
        return json_decode($respons->getContent(), true) ?? [];
    }

    /** Premis seluruh berkas: layanan sumbernya memang menghasilkan 80. */
    public function test_layanan_sumber_menghasilkan_skor_resmi(): void
    {
        $svc = app(KpiAkumulasiYtdService::class);

        $this->assertEqualsWithDelta(
            self::SKOR_RESMI,
            $svc->skorPerUser([$this->karyawan->UserID_Web], self::TAHUN, $svc->periodeSampai(self::TAHUN, 12))[(string) $this->karyawan->UserID_Web] ?? null,
            0.05,
        );
    }

    public function test_daftar_dashboard_ceo_full_year_sama_dengan_sumber(): void
    {
        // Daftar CEO disaring hak akses: login sebagai leader yang timnya memuat
        // karyawan fixture.
        $leader = DB::table('Karyawan as k')
            ->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')
            ->where('k.Kode_Karyawan', '!=', $this->karyawan->Kode_Karyawan)
            ->select('k.UserID_Web', 'k.Kode_Karyawan')
            ->first();
        DB::table('KPI_Karyawan_Team')->insert([
            'Kode_Karyawan_Leader' => $leader->Kode_Karyawan,
            'Kode_Karyawan_Team' => $this->karyawan->Kode_Karyawan,
            'Flag_Aktif' => 'Y',
            'create_at' => now(),
        ]);
        $this->actingAs(User::findOrFail($leader->UserID_Web));
        $permintaan = Request::create('/', 'GET', ['bulan' => 'ALL', 'tahun' => (string) self::TAHUN, 'page' => 1, 'limit' => 1000]);
        $permintaan->setUserResolver(fn () => auth()->user());

        $baris = collect(data_get($this->isiJson(app(DashboardCeoController::class)->getDataInformasiV2($permintaan)), 'result.Recent_Kpi_Update.data', []))
            ->firstWhere('name', $this->karyawan->Nama);

        $this->assertNotNull($baris, 'Karyawan fixture tidak muncul di daftar CEO — uji ini tak menguji apa pun.');
        $this->assertEqualsWithDelta(self::SKOR_RESMI, $baris['achievement'], 0.05, 'Daftar dashboard CEO masih menghitung sendiri (target bulan tanpa realisasi ikut terjumlah).');
    }

    public function test_modal_detail_ceo_setahun_sama_dengan_sumber(): void
    {
        $this->actingAs(User::findOrFail($this->karyawan->UserID_Web));

        $hasil = $this->isiJson(app(DashboardCeoController::class)->recentKpiUserDetail(
            $this->hash($this->idPengajuan),
            $this->hash($this->idUpdate),
            'true',
        ));

        $this->assertEqualsWithDelta(self::SKOR_RESMI, data_get($hasil, 'result.header.Achievement'), 0.05, 'Modal detail CEO masih menghitung sendiri (target bulan tanpa realisasi ikut terjumlah).');
    }

    public function test_dashboard_staff_full_year_sama_dengan_sumber(): void
    {
        $this->actingAs(User::findOrFail($this->karyawan->UserID_Web));
        $permintaan = Request::create('/', 'GET', ['bulan' => 'all', 'tahun' => (string) self::TAHUN]);
        $permintaan->setUserResolver(fn () => auth()->user());

        $hasil = $this->isiJson(app(StaffPerformanceDashboardController::class)->getDataInformasiByUserId($permintaan));

        $this->assertEqualsWithDelta(self::SKOR_RESMI, data_get($hasil, 'result.Widget.Achievement'), 0.05, 'Dashboard staff masih menghitung sendiri.');
    }

    public function test_modal_detail_staff_setahun_sama_dengan_sumber(): void
    {
        $this->actingAs(User::findOrFail($this->karyawan->UserID_Web));

        $hasil = $this->isiJson(app(StaffPerformanceDashboardController::class)->recentKpiUserDetail(
            $this->hash($this->idPengajuan),
            $this->hash($this->idUpdate),
            'true',
        ));

        $this->assertEqualsWithDelta(self::SKOR_RESMI, data_get($hasil, 'result.header.Achievement'), 0.05, 'Modal detail staff masih menghitung sendiri (realisasi yang sudah digantikan ikut terjumlah).');
    }

    /**
     * Rincian YTD di Monitoring harus MENJUMLAH ke skor barisnya: angka yang
     * tampil di tabel dan rincian yang menjelaskannya datang dari sumber sama.
     */
    public function test_rincian_ytd_monitoring_menjumlah_ke_skor_sumber(): void
    {
        $hasil = $this->isiJson(app(\App\Http\Controllers\KPI\Monitoring\Enforcement\KpiEnforcementController::class)->getPerformanceDetailYtd(
            Request::create('/', 'GET', ['id_user' => $this->karyawan->UserID_Web, 'periode' => self::TAHUN.'-03']),
        ));
        $header = data_get($hasil, 'result.header', []);

        $this->assertTrue($header['ada_data'] ?? false);
        $this->assertEqualsWithDelta(self::SKOR_RESMI, $header['skor'], 0.05, 'Rincian YTD tidak sama dengan skor sumber.');
        $this->assertEqualsWithDelta(
            $header['skor'],
            $header['sum_weighted'] / $header['total_bobot'] * 100,
            0.05,
            'Ringkasan hitungan tidak menjumlah ke skor yang ditampilkan.',
        );
        $this->assertSame(
            [self::TAHUN.'-01', self::TAHUN.'-02'],
            $header['bulan_terhitung'],
            'Bulan tanpa realisasi (Maret) atau revisi yang digantikan ikut terhitung.',
        );
        $this->assertCount(2, data_get($hasil, 'result.parameters', []));
    }

    public function test_kpi_verify_ytd_melaporkan_angka_sumber(): void
    {
        Artisan::call('kpi:verify-ytd', [
            'kode' => $this->karyawan->Kode_Karyawan,
            '--year' => self::TAHUN,
            '--ytd-month' => 3,
        ]);

        $this->assertMatchesRegularExpression(
            '/YTD AKUMULASI \(jepit\/param\)\s*:\s*80(\.0)?\b/',
            Artisan::output(),
            'kpi:verify-ytd tidak melaporkan angka yang sama dengan sumbernya.',
        );
    }
}
