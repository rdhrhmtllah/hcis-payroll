<?php

namespace Tests\Feature\ESS\Dashboard;

use App\Services\ESS\Dashboard\Homepage\HomepageKpiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Parameter di widget KPI homepage harus berasal dari pengajuan yang BERLAKU.
 *
 * Ditemukan saat uji manual 2026-09-18: Aisyah hanya punya satu parameter
 * berlaku, tetapi widget menampilkan 8 parameter dari 7 pengajuan (bobot total
 * 700%) — revisi lama lewat update performance yang sudah dinonaktifkan, dan
 * pengajuan menggantung yang tak pernah disetujui — lengkap dengan target
 * Oktober yang tidak ia miliki.
 *
 * Dijaga lewat resolvePengajuanSumberParameter, yang dipakai widget pribadi
 * dan detail anggota di dashboard leader.
 */
class HomepageKpiSumberParameterTest extends TestCase
{
    use DatabaseTransactions;

    private const TAHUN = 2036;

    private string $idUser;

    private int $lama;

    private int $berlaku;

    private int $menggantung;

    protected function setUp(): void
    {
        parent::setUp();

        $idUser = DB::table('Karyawan')->whereNotNull('UserID_Web')->value('UserID_Web');
        if (! $idUser) {
            $this->markTestSkipped('Tak ada karyawan acuan.');
        }
        $this->idUser = (string) $idUser;

        $jadwal = (int) DB::table('KPI_Master_Penjadwalan')->insertGetId([
            'Nama_Kegiatan' => 'Uji sumber parameter',
            'Tahun' => self::TAHUN,
            'Periode' => self::TAHUN.'-01',
            'Flag_Form_KPI' => 'Y',
            'Flag_Aktif' => 'Y',
        ], 'Id_Master_Penjadwalan');

        $pengajuan = fn (string $tanggal, array $flag) => (int) DB::table('KPI_Pengajuan')->insertGetId([
            'No_Transaksi' => 'UJI-SP-'.bin2hex(random_bytes(4)),
            'Id_Penjadwalan' => $jadwal,
            'Id_User' => $idUser,
            'Tanggal' => $tanggal,
            'Jam' => '09:00:00',
        ] + $flag, 'Id_Pengajuan');

        // Versi lama yang sudah digantikan revisi.
        $this->lama = $pengajuan(self::TAHUN.'-01-10', ['Flag_Aktif' => 'T', 'Flag_Approval' => 'Y', 'Status' => 'Y']);
        // Menggantung sejak awal tahun, tak pernah disetujui.
        $this->menggantung = $pengajuan(self::TAHUN.'-02-10', ['Flag_Aktif' => 'Y', 'Flag_Approval' => null, 'Status' => null]);
        // Yang berlaku sekarang.
        $this->berlaku = $pengajuan(self::TAHUN.'-01-20', ['Flag_Aktif' => 'Y', 'Flag_Approval' => 'Y', 'Status' => null]);

        $idTarget = DB::table('KPI_Master_Target')->where('Kode_Target', 'M')->value('Id_Master_Target');
        foreach (['lama' => $this->lama, 'menggantung' => $this->menggantung, 'berlaku' => $this->berlaku] as $nama => $id) {
            $idDetail = (int) DB::table('KPI_Pengajuan_Detail')->insertGetId([
                'Id_Pengajuan' => $id,
                'Nama_Parameter' => 'Parameter '.$nama,
                'Id_Master_Target' => $idTarget,
                'Bobot' => 100,
            ], 'Id_Pengajuan_Detail');

            DB::table('KPI_Pengajuan_Det')->insert([
                'Id_Pengajuan_Detail' => $idDetail,
                'Nama_Target' => 'Target '.$nama,
                'Nilai_Target' => 10,
                'Periode' => self::TAHUN.'-10',
            ]);
        }

        // Agustus: update HIDUP memakai versi lama (sah, menopang skor bulan itu).
        $this->update($this->lama, self::TAHUN.'-08', 'Y');
        // September: update memakai versi lama tetapi sudah DINONAKTIFKAN (digantikan).
        $this->update($this->lama, self::TAHUN.'-09', 'T');
    }

    private function update(int $idPengajuan, string $periode, string $flagAktif): void
    {
        DB::table('KPI_Update_Performance')->insert([
            'No_Transaksi' => 'UJI-SPU-'.bin2hex(random_bytes(4)),
            'Id_Pengajuan' => $idPengajuan,
            'Id_User' => $this->idUser,
            'Periode' => $periode,
            'Status_Pengajuan' => 'disetujui',
            'Flag_Approval' => 'Y',
            'Flag_Verifikasi' => 'Y',
            'Flag_Aktif' => $flagAktif,
            'Status' => null,
            'Tanggal' => now(),
        ]);
    }

    private function sumber(string $mode, string $periode): array
    {
        $refleksi = new \ReflectionMethod(HomepageKpiService::class, 'resolvePengajuanSumberParameter');
        $refleksi->setAccessible(true);

        return $refleksi->invoke(app(HomepageKpiService::class), $this->idUser, self::TAHUN, $mode, $periode);
    }

    /** Diuji lewat efek: parameter yang benar-benar dipulangkan ke widget. */
    public function test_widget_bulan_tanpa_update_hanya_menampilkan_parameter_berlaku(): void
    {
        $member = (object) ['UserID_Web' => $this->idUser, 'Kode_Karyawan' => 'UJI-SP', 'Nama' => 'Uji'];

        $detail = app(HomepageKpiService::class)->getLeaderKpiMemberDetail($member, 'month', self::TAHUN.'-10', self::TAHUN);

        $this->assertSame(
            ['Parameter berlaku'],
            collect($detail['parameter_rows'] ?? [])->pluck('parameter')->values()->all(),
            'Widget menumpuk parameter dari revisi lama / pengajuan yang belum disetujui.',
        );
    }

    public function test_bulan_dengan_update_hidup_memakai_pengajuan_update_itu(): void
    {
        $this->assertSame([$this->lama], $this->sumber('month', self::TAHUN.'-08'));
    }

    public function test_update_yang_sudah_dinonaktifkan_tidak_membawa_versi_lama(): void
    {
        $this->assertSame(
            [$this->berlaku],
            $this->sumber('month', self::TAHUN.'-09'),
            'Revisi lama masih ikut lewat update performance yang sudah digantikan.',
        );
    }

    public function test_bulan_tanpa_update_memakai_pengajuan_berlaku_yang_disetujui(): void
    {
        $this->assertSame(
            [$this->berlaku],
            $this->sumber('month', self::TAHUN.'-10'),
            'Pengajuan yang belum disetujui ikut dianggap berlaku.',
        );
    }

    public function test_setahun_hanya_pengajuan_yang_menopang_update_hidup_dan_yang_berlaku(): void
    {
        $sumber = $this->sumber('full_year', self::TAHUN.'-10');
        sort($sumber);

        $harapan = [$this->lama, $this->berlaku];
        sort($harapan);

        $this->assertSame($harapan, $sumber);
    }

    /**
     * Label status butuh "punya target jatuh tempo di periode ini", bukan
     * "punya target di tahun ini". Tanpa pembeda, orang tanpa target bulan itu
     * dilabeli "Belum Mengisi" (uji manual Aisyah, Oktober 2026).
     */
    public function test_status_performance_membawa_target_jatuh_tempo_periode(): void
    {
        $refleksi = new \ReflectionMethod(HomepageKpiService::class, 'resolvePerformanceApprovalBatch');
        $refleksi->setAccessible(true);
        $status = fn ($periode) => $refleksi->invoke(app(HomepageKpiService::class), [$this->idUser], self::TAHUN, $periode)[$this->idUser];

        $this->assertTrue($status(self::TAHUN.'-10')['hasDueTarget']);
        $this->assertFalse(
            $status(self::TAHUN.'-11')['hasDueTarget'],
            'Periode tanpa target jatuh tempo masih dianggap punya target — label akan "Belum Mengisi".',
        );
        $this->assertTrue($status(self::TAHUN.'-11')['hasParameters'], 'hasParameters (tahunan) tidak boleh ikut berubah.');
    }

    public function test_pengajuan_menunggu_tetap_tampil_bila_belum_ada_yang_disetujui(): void
    {
        DB::table('KPI_Pengajuan')->where('Id_Pengajuan', $this->berlaku)->update(['Flag_Aktif' => 'T', 'Status' => 'Y']);

        $this->assertSame([$this->menggantung], $this->sumber('month', self::TAHUN.'-10'));
    }
}
