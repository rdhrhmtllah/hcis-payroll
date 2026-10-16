<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\PerformanceAppraisal\Katalog\PaLevelDikecualikan;
use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use App\Services\KPI\PerformanceAppraisal\PaCyclePreparationService;
use App\Services\KPI\PerformanceAppraisal\PaCycleSetupService;
use App\Services\KPI\PerformanceAppraisal\PaKelayakanMasaKerjaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Minimal masa kerja peserta (DDL-2026-191).
 *
 * Yang dijaga: rumusnya, bahwa penyiapan dan pratinjau memakai salinan SIKLUS (bukan master),
 * bahwa siklus lama tanpa angka tidak terkena aturan, dan bahwa angkanya membeku begitu
 * peserta terbentuk — angka yang berubah sesudahnya tidak akan pernah berlaku bagi mereka.
 */
class KelayakanMasaKerjaTest extends TestCase
{
    use DatabaseTransactions;

    private const LEVEL = 2;

    public function test_rumus_masa_kerja_terhadap_akhir_periode(): void
    {
        $f = fn ($masuk, $min) => PaKelayakanMasaKerjaService::alasanTidakLayak($masuk, '2026-06-30', $min);

        // Genap 3 bulan pada akhir hari 30 Juni.
        $this->assertNull($f('2026-04-01', 3));
        $this->assertStringStartsWith('Masa kerja kurang dari 3 bulan pada akhir periode 2026-06-30', $f('2026-04-02', 3));
        $this->assertNull($f('2020-01-15 00:00:00.000', 3), 'Nilai datetime SQL Server harus terbaca.');

        // Masuk sesudah periode berakhir: selalu tidak layak, juga pada minimal 0.
        $this->assertStringStartsWith('Bergabung setelah periode penilaian berakhir pada 2026-06-30', $f('2026-07-01', 0));
        $this->assertNull($f('2026-06-30', 0));

        // Aturan tidak berlaku tanpa angka siklus, tanpa tanggal masuk, atau tanpa akhir periode.
        $this->assertNull($f('2026-07-01', null));
        $this->assertNull($f(null, 3));
        $this->assertNull(PaKelayakanMasaKerjaService::alasanTidakLayak('2026-07-01', null, 3));

        // Tanpa luapan akhir bulan: 31 Mei + 1 bulan = 30 Juni, genap.
        $this->assertNull($f('2026-05-31', 1));
    }

    public function test_penyiapan_mengecualikan_dengan_alasan_lalu_angkanya_membeku(): void
    {
        [$baru, $susulan] = $this->duaKaryawan();
        DB::table('Karyawan')->where('Kode_Perusahaan', $baru->Kode_Perusahaan)->where('Kode_Karyawan', $baru->Kode_Karyawan)
            ->update(['Tgl_Masuk' => '2026-05-15']);
        DB::table('Karyawan')->where('Kode_Perusahaan', $susulan->Kode_Perusahaan)->where('Kode_Karyawan', $susulan->Kode_Karyawan)
            ->update(['Tgl_Masuk' => '2026-08-01']);

        $cycleId = $this->siklusDraft(3);
        $penyiapan = app(PaCyclePreparationService::class);

        $pratinjau = $penyiapan->pratinjauPenyiapan($cycleId);
        $this->assertArrayHasKey('Masa kerja kurang dari 3 bulan', $pratinjau['sebab_dikecualikan']);
        $this->assertArrayHasKey('Bergabung setelah periode penilaian berakhir', $pratinjau['sebab_dikecualikan']);

        app(PaCycleGate::class)->lupakanMemo();
        $penyiapan->siapkan($cycleId);

        $this->assertStringStartsWith('Masa kerja kurang dari 3 bulan', $this->alasanPeserta($cycleId, $baru));
        $this->assertStringStartsWith('Bergabung setelah periode penilaian berakhir', $this->alasanPeserta($cycleId, $susulan));

        /* Sesudah peserta terbentuk angkanya tidak boleh diganti: penyiapan ulang tidak menilai
           ulang mereka, jadi angka baru hanya akan tampil tanpa pernah berlaku. */
        $kelayakan = app(PaKelayakanMasaKerjaService::class);
        $this->assertFalse($kelayakan->untukSiklus($cycleId)['boleh_ubah']);

        try {
            $kelayakan->simpanSiklus($cycleId, 6, 1);
            $this->fail('Angka siklus yang pesertanya sudah terbentuk tidak boleh berubah.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Peserta sudah dibentuk', $e->getMessage());
        }
        $this->assertSame(3, (int) DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->value('Min_Masa_Kerja_Bulan'));
    }

    public function test_siklus_lama_tanpa_angka_tidak_terkena_aturan(): void
    {
        [$baru] = $this->duaKaryawan();
        DB::table('Karyawan')->where('Kode_Perusahaan', $baru->Kode_Perusahaan)->where('Kode_Karyawan', $baru->Kode_Karyawan)
            ->update(['Tgl_Masuk' => '2026-08-01']);

        $pratinjau = app(PaCyclePreparationService::class)->pratinjauPenyiapan($this->siklusDraft(null));

        $this->assertArrayNotHasKey('Bergabung setelah periode penilaian berakhir', $pratinjau['sebab_dikecualikan']);
        $this->assertArrayNotHasKey('Masa kerja kurang dari 3 bulan', $pratinjau['sebab_dikecualikan']);
    }

    public function test_angka_siklus_bisa_diubah_sebelum_peserta_dibentuk(): void
    {
        $cycleId = $this->siklusDraft(null);
        $kelayakan = app(PaKelayakanMasaKerjaService::class);

        $hasil = $kelayakan->simpanSiklus($cycleId, 6, 1);

        $this->assertSame(6, $hasil['min_masa_kerja_bulan']);
        $this->assertTrue($hasil['boleh_ubah']);

        $this->expectException(RuntimeException::class);
        $kelayakan->simpanSiklus($cycleId, PaKelayakanMasaKerjaService::MAKS_BULAN + 1, 1);
    }

    public function test_master_disimpan_dan_menjadi_nilai_awal_siklus_baru_saja(): void
    {
        $lama = $this->siklusDraft(3);
        $kelayakan = app(PaKelayakanMasaKerjaService::class);

        $kelayakan->simpanMaster(5, 1);
        $this->assertSame(5, $kelayakan->master()['min_masa_kerja_bulan']);
        // Disimpan dua kali: baris tunggalnya diperbarui, bukan disisipkan lagi.
        $kelayakan->simpanMaster(4, 1);
        $this->assertSame(1, DB::table('N_PA_Eligibility_Setting')->count());

        $baru = app(PaCycleSetupService::class)->buat([
            'nama_cycle' => 'Siklus Uji Masa Kerja',
            'tahun' => 2026,
            'periode_dinilai_dari' => '2026-01-01',
            'periode_dinilai_sampai' => '2026-06-30',
        ], 1);

        $this->assertSame(4, (int) DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $baru['cycle_id'])->value('Min_Masa_Kerja_Bulan'));
        $this->assertSame(3, (int) DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $lama)->value('Min_Masa_Kerja_Bulan'),
            'Mengubah master tidak boleh menyentuh siklus yang sudah ada.');
    }

    public function test_alamat_siklus_membaca_menyimpan_dan_menolak_angka_di_luar_rentang(): void
    {
        $userId = DB::table('HRIS_Page_Access')->where('Jenis_page', 'PerformanceAppraisalPage')
            ->where('Flag_Aktif', 'Y')->whereNotNull('UserID_Web')->value('UserID_Web');
        $this->assertNotNull($userId, 'Tidak ada user berakses PerformanceAppraisalPage.');
        $this->actingAs(User::find($userId));

        $cycleId = $this->siklusDraft(3);
        $alamat = "/api/v1/performance-appraisal/cycles/{$cycleId}/kelayakan-masa-kerja";

        $this->getJson($alamat)->assertOk()
            ->assertJsonPath('result.min_masa_kerja_bulan', 3)
            ->assertJsonPath('result.boleh_ubah', true);

        $this->putJson($alamat, ['min_masa_kerja_bulan' => 6])->assertOk()
            ->assertJsonPath('result.min_masa_kerja_bulan', 6);

        $this->putJson($alamat, ['min_masa_kerja_bulan' => 99])->assertStatus(422);
        $this->assertSame(6, (int) DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->value('Min_Masa_Kerja_Bulan'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('N_PA_Level_Exclusion')->where('Flag_Aktif', 'Y')->where('ID_Level', self::LEVEL)
            ->update(['Flag_Aktif' => 'T']);
        PaLevelDikecualikan::lupakan();
    }

    /** Dua karyawan aktif ber-level STAFF yang tidak resign. */
    private function duaKaryawan(): array
    {
        $orang = DB::table('Karyawan as k')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')
            ->where('v.ID_Level', self::LEVEL)
            ->orderBy('k.Kode_Karyawan')
            ->limit(2)
            ->get(['k.Kode_Perusahaan', 'k.Kode_Karyawan'])
            ->all();

        $this->assertCount(2, $orang, 'Butuh dua karyawan STAFF aktif di basis data pengembangan.');

        return $orang;
    }

    private function siklusDraft(?int $minBulan): int
    {
        $kode = 'UJI-MSKJ-'.substr(bin2hex(random_bytes(4)), 0, 8);

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => $kode,
            'Nama_Cycle' => 'Siklus Uji Masa Kerja',
            'Tahun' => 2026,
            'Status_Cycle' => 'DRAFT',
            'Periode_Dinilai_Dari' => '2026-01-01',
            'Periode_Dinilai_Sampai' => '2026-06-30',
            'Min_Masa_Kerja_Bulan' => $minBulan,
        ]);

        app(PaCycleGate::class)->lupakanMemo();

        return (int) DB::table('N_PA_Cycle')->where('Kode_Cycle', $kode)->value('Id_PA_Cycle');
    }

    private function alasanPeserta(int $cycleId, object $k): ?string
    {
        $p = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Kode_Perusahaan', $k->Kode_Perusahaan)
            ->where('Kode_Karyawan', $k->Kode_Karyawan)
            ->where('Flag_Aktif', 'Y')
            ->first(['Status_Peserta', 'Alasan_Dikecualikan']);

        $this->assertNotNull($p, "{$k->Kode_Karyawan} tidak terbentuk sebagai peserta.");
        $this->assertSame('DIKECUALIKAN', $p->Status_Peserta);

        return $p->Alasan_Dikecualikan;
    }
}
