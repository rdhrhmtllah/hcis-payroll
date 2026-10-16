<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Periode tanpa target jatuh tempo tidak boleh bisa di-update.
 *
 * ── Kenapa ──────────────────────────────────────────────────────────────────
 *
 * Keluhan 2026-09-18: karyawan yang tidak punya target bulan itu (mis. tak ada
 * target monthly) tetap mendapat formulir Update KPI. Seluruh parameternya
 * nonaktif, tetapi ia tetap bisa lanjut dan menyimpan draft. Halaman Update KPI
 * membuka periode hanya berdasarkan JADWAL yang berjalan, sementara monitoring
 * sudah lama melabeli orang yang sama "Tidak Ada Target".
 *
 * Aturannya satu: KpiEnforcementService::classifyParameterTargetForPeriod —
 * punya target = minimal satu parameter bertarget TEPAT di bulan itu.
 *
 * ── Yang dijaga ─────────────────────────────────────────────────────────────
 *
 * Semua pintu, karena aturan yang hanya dipasang di satu pintu akan menyimpang
 * di pintu lainnya: daftar To Update tim, halaman pribadi, simpan draft (pribadi
 * dan tim), dan penjaga submit akhir di ketiga jalur finalisasi.
 */
class UpdatePerformanceTanpaTargetTest extends TestCase
{
    use DatabaseTransactions;

    /** Tahun jauh ke depan supaya pengajuan nyata tidak ikut terbaca. */
    private const TAHUN = 2036;

    private const PERIODE = '2036-05';

    private const PERIODE_LAIN = '2036-06';

    private int $leader;

    private int $bertarget;

    private int $tanpaTarget;

    private int $jadwalBerjalan;

    /** @var array<int, array{pengajuan:int, detail:int, det:int}> */
    private array $kpi = [];

    protected function setUp(): void
    {
        parent::setUp();

        $idTarget = DB::table('KPI_Master_Target')->where('Kode_Target', 'M')->value('Id_Master_Target');

        // Tiga karyawan nyata yang lengkap divisi & jabatannya (daftar tim
        // mem-JOIN kedua view itu) dan punya akun KPI_Users (untuk actingAs).
        $orang = DB::table('karyawan as k')
            ->join('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'k.ID_Level_Jabatan', '=', 'vg.ID_Level_Jabatan')
            ->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')
            ->where('k.Aktif', 'Y')
            ->whereNotNull('k.Kode_Karyawan')
            ->select('k.UserID_Web', 'k.Kode_Karyawan')
            ->distinct()
            ->limit(3)
            ->get();

        if (! $idTarget || $orang->count() < 3) {
            $this->markTestSkipped('Butuh target monthly dan tiga karyawan lengkap sebagai acuan.');
        }

        [$leader, $a, $b] = $orang->all();
        $this->leader = (int) $leader->UserID_Web;
        $this->bertarget = (int) $a->UserID_Web;
        $this->tanpaTarget = (int) $b->UserID_Web;

        $jadwalForm = (int) DB::table('KPI_Master_Penjadwalan')->insertGetId([
            'Nama_Kegiatan' => 'Uji tanpa target — form',
            'Tahun' => self::TAHUN,
            'Periode' => self::TAHUN.'-01',
            'Flag_Form_KPI' => 'Y',
            'Flag_Perorangan' => 'T',
            'Flag_Aktif' => 'Y',
            'Jadwal_Mulai' => self::TAHUN.'-01-01 00:00:00',
            'Jadwal_Akhir' => self::TAHUN.'-01-31 23:59:59',
        ], 'Id_Master_Penjadwalan');

        $this->jadwalBerjalan = (int) DB::table('KPI_Master_Penjadwalan')->insertGetId([
            'Nama_Kegiatan' => 'Uji tanpa target — penilaian berjalan',
            'Tahun' => self::TAHUN,
            'Periode' => self::PERIODE,
            'Flag_Penilaian_KPI' => 'Y',
            'Flag_Perorangan' => 'T',
            'Flag_Aktif' => 'Y',
            'Jadwal_Mulai' => now()->subDays(3),
            'Jadwal_Akhir' => now()->addDays(10),
        ], 'Id_Master_Penjadwalan');

        // A punya target TEPAT di periode jadwal; B hanya punya target bulan berikutnya.
        foreach ([$a->UserID_Web => [$a->Kode_Karyawan, self::PERIODE], $b->UserID_Web => [$b->Kode_Karyawan, self::PERIODE_LAIN]] as $idUser => [$kode, $periodeTarget]) {
            DB::table('KPI_Karyawan_Team')->insert([
                'Kode_Karyawan_Leader' => $leader->Kode_Karyawan,
                'Kode_Karyawan_Team' => $kode,
                'Flag_Aktif' => 'Y',
                'create_at' => now(),
            ]);

            $idPengajuan = (int) DB::table('KPI_Pengajuan')->insertGetId([
                'No_Transaksi' => 'UJI-TT-'.bin2hex(random_bytes(4)),
                'Id_Penjadwalan' => $jadwalForm,
                'Id_User' => $idUser,
                'Id_User_Sender' => $this->leader,
                'Flag_Team' => 'Y',
                'Tanggal' => self::TAHUN.'-01-10 00:00:00',
                'Jam' => '09:00:00',
                'Flag_Aktif' => 'Y',
                'Flag_Approval' => 'Y',
                'Status' => null,
            ], 'Id_Pengajuan');

            $idDetail = (int) DB::table('KPI_Pengajuan_Detail')->insertGetId([
                'Id_Pengajuan' => $idPengajuan,
                'Nama_Parameter' => 'Parameter uji tanpa target',
                'Id_Master_Target' => $idTarget,
                'Bobot' => 100,
                'Kode_Karyawan' => $kode,
            ], 'Id_Pengajuan_Detail');

            $idDet = (int) DB::table('KPI_Pengajuan_Det')->insertGetId([
                'Id_Pengajuan_Detail' => $idDetail,
                'Nama_Target' => 'Target '.$periodeTarget,
                'Nilai_Target' => 100,
                'Periode' => $periodeTarget,
            ], 'Id_Pengajuan_Det');

            $this->kpi[(int) $idUser] = ['pengajuan' => $idPengajuan, 'detail' => $idDetail, 'det' => $idDet];
        }
    }

    public function test_daftar_tim_tidak_memuat_anggota_tanpa_target(): void
    {
        $pasangan = $this->panggil('buildToUpdatePairs', $this->leader, null)
            ->where('periode_raw', self::PERIODE);

        $this->assertTrue(
            $pasangan->contains('id_user_raw', $this->bertarget),
            'Anggota yang PUNYA target hilang dari To Update — penjaganya terlalu keras.',
        );
        $this->assertFalse(
            $pasangan->contains('id_user_raw', $this->tanpaTarget),
            'Anggota tanpa target periode ini masih muncul di To Update.',
        );
    }

    public function test_halaman_pribadi_tanpa_target_tidak_membuka_formulir(): void
    {
        $this->actingAs(User::findOrFail($this->tanpaTarget));

        $halaman = $this->halamanInertia(app(UpdatePerformanceController::class)->index());

        $this->assertSame('KPI/components/ui/statusPage', $halaman['component']);
        $this->assertSame('tanpa_target', $halaman['props']['status'] ?? null);
    }

    public function test_halaman_pribadi_bertarget_tetap_membuka_formulir(): void
    {
        $this->actingAs(User::findOrFail($this->bertarget));

        $halaman = $this->halamanInertia(app(UpdatePerformanceController::class)->index());

        $this->assertSame('KPI/Performance/Update/updatePerformance', $halaman['component']);
    }

    public function test_simpan_draft_pribadi_ditolak_tanpa_target(): void
    {
        $this->actingAs(User::findOrFail($this->tanpaTarget));

        $respons = app(UpdatePerformanceController::class)->storeTemporaryPerformanceStep1(
            $this->permintaanDraft($this->tanpaTarget),
        );

        $this->assertSame(422, $respons->getStatusCode(), (string) $respons->getContent());
        $this->assertFalse($this->adaDraft($this->tanpaTarget), 'Draft tetap terbentuk untuk periode tanpa target.');
    }

    public function test_simpan_draft_tim_ditolak_tanpa_target(): void
    {
        $this->actingAs(User::findOrFail($this->leader));

        $respons = app(UpdatePerformanceController::class)->storeTemporaryPerformanceStep1ByTeam(
            $this->permintaanDraft($this->tanpaTarget),
            Hashids::connection('custom')->encode($this->tanpaTarget),
        );

        $this->assertSame(422, $respons->getStatusCode(), (string) $respons->getContent());
        $this->assertFalse($this->adaDraft($this->tanpaTarget), 'Draft tim tetap terbentuk untuk periode tanpa target.');
    }

    /**
     * By-pass boleh di luar jendela jadwal, tetapi bukan pintu belakang untuk
     * periode tanpa target. Ditemukan saat uji manual: halaman
     * /update-performance/by-pass/{id}?periode=... masih membuka formulir.
     */
    public function test_halaman_bypass_tanpa_target_tidak_membuka_formulir(): void
    {
        $this->actingAs(User::findOrFail($this->leader));
        $this->app->instance('request', Request::create('/', 'GET', [
            'schedule' => Hashids::connection('custom')->encode($this->jadwalBerjalan),
        ]));

        $pengendali = app(UpdatePerformanceController::class);

        $tanpa = $this->halamanInertia($pengendali->showUpdatePerformanceByPass(
            Hashids::connection('custom')->encode($this->tanpaTarget),
        ));
        $this->assertSame('KPI/components/ui/statusPage', $tanpa['component']);
        $this->assertSame('tanpa_target', $tanpa['props']['status'] ?? null);

        $dengan = $this->halamanInertia($pengendali->showUpdatePerformanceByPass(
            Hashids::connection('custom')->encode($this->bertarget),
        ));
        $this->assertSame('KPI/Performance/Update/team/updatePerformanceByPass', $dengan['component']);
    }

    public function test_daftar_bypass_tidak_memuat_karyawan_tanpa_target(): void
    {
        $respons = app(UpdatePerformanceController::class)->getDataKpiItemsByPass(
            Request::create('/api/v1/update-performance/list-Kpi-bypass', 'GET', [
                'bulan' => (int) substr(self::PERIODE, 5, 2),
                'tahun' => (int) substr(self::PERIODE, 0, 4),
                'limit' => 1000,
                'page' => 1,
            ]),
        );

        $kode = collect(json_decode($respons->getContent(), true)['result'] ?? [])->pluck('id_user');
        $hash = fn (int $id) => Hashids::connection('custom')->encode($id);

        $this->assertTrue($kode->contains($hash($this->bertarget)), 'Karyawan bertarget hilang dari daftar bypass.');
        $this->assertFalse($kode->contains($hash($this->tanpaTarget)), 'Karyawan tanpa target masih ada di daftar bypass.');
    }

    public function test_penjaga_submit_akhir_menolak_tanpa_target_dan_meloloskan_yang_bertarget(): void
    {
        $this->panggil('assertPunyaTargetPeriode', $this->bertarget, self::PERIODE);

        $this->expectExceptionMessage('Tidak ada target KPI yang jatuh tempo');
        $this->panggil('assertPunyaTargetPeriode', $this->tanpaTarget, self::PERIODE);
    }

    /**
     * Ketiga jalur finalisasi harus memanggil penjaganya. Penjaga yang hanya ada
     * di satu jalur membiarkan dua jalur lain meloloskan periode tanpa target.
     */
    public function test_ketiga_jalur_finalisasi_memanggil_penjaga_target(): void
    {
        foreach (['processPerformanceTransaction', 'processPerformanceTransactionByTeam', 'processPerformanceTransactionByPass'] as $metode) {
            $refleksi = new \ReflectionMethod(UpdatePerformanceController::class, $metode);
            $isi = implode('', array_slice(
                file($refleksi->getFileName()),
                $refleksi->getStartLine() - 1,
                $refleksi->getEndLine() - $refleksi->getStartLine() + 1,
            ));

            $this->assertStringContainsString(
                'assertPunyaTargetPeriode(',
                $isi,
                "{$metode} tidak memanggil assertPunyaTargetPeriode.",
            );
        }
    }

    private function panggil(string $metode, ...$argumen)
    {
        $refleksi = new \ReflectionMethod(UpdatePerformanceController::class, $metode);
        $refleksi->setAccessible(true);

        return $refleksi->invoke(app(UpdatePerformanceController::class), ...$argumen);
    }

    /** @return array{component:string, props:array} */
    private function halamanInertia($respons): array
    {
        $http = $respons->toResponse(Request::create('/', 'GET', [], [], [], ['HTTP_X_INERTIA' => 'true']));

        return json_decode($http->getContent(), true);
    }

    private function permintaanDraft(int $idUser): Request
    {
        $hash = fn (int $id) => Hashids::connection('custom')->encode($id);
        $kpi = $this->kpi[$idUser];

        return Request::create('/api/v1/update-performance/store/temporary', 'POST', [
            'kpi_data' => [[
                'Id_Pengajuan' => $hash($kpi['pengajuan']),
                'Id_Pengajuan_Detail' => $hash($kpi['detail']),
                'Id_Pengajuan_Det' => $hash($kpi['det']),
                'is_input_period' => 'false',
            ]],
            'current_step' => 1,
            'Id_Master_Penjadwalan' => $hash($this->jadwalBerjalan),
        ]);
    }

    private function adaDraft(int $idUser): bool
    {
        return DB::table('KPI_Update_Performance_Sementara')
            ->where('Id_User', $idUser)
            ->where('Id_Penjadwalan', $this->jadwalBerjalan)
            ->exists();
    }
}
