<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\PerformanceAppraisal\PaKoreksiLevelPaService;
use App\Services\KPI\PerformanceAppraisal\PaLevelMasterService;
use App\Services\KPI\PerformanceAppraisal\PaLevelPaResolver;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * MASTER LEVEL PA (DDL-2026-183/185) — level khusus PA (mis. Officer Specialist) yang berlaku di
 * semua matriks seperti level biasa tetapi tidak pernah masuk HRIS.
 *
 *  1. Level HRIS tersalin dengan ID sama; sinkron idempoten.
 *  2. Level khusus PA ber-ID >= 1.000.000, matriks level dasarnya tersalin sebagai titik awal.
 *  3. FK matriks menunjuk N_PA_Level: level yang tidak dikenal ditolak SQL Server.
 *  4. Koreksi Level PA bisa memindahkan orang ke level khusus PA, dan resolver memakainya.
 *  5. Level HRIS tidak disunting dari PA; level PA yang masih dipakai tidak bisa dinonaktifkan.
 */
class MasterLevelPaTest extends TestCase
{
    use DatabaseTransactions;

    private PaLevelMasterService $master;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::whereNotNull('User_Key')->firstOrFail());
        PaLevelMasterService::lupakan();
        $this->master = app(PaLevelMasterService::class);
    }

    public function test_level_hris_tersalin_dengan_id_sama_dan_sinkron_idempoten(): void
    {
        PaLevelMasterService::sinkronHris(true);
        $this->assertSame(['ditambah' => 0, 'diperbarui' => 0], PaLevelMasterService::sinkronHris(true));

        $hris = DB::table('HRIS_Level')->pluck('Keterangan', 'ID_Level')->map(fn ($v) => trim($v))->all();
        $pa = DB::table('N_PA_Level')->where('Sumber', 'HRIS')->pluck('Nama_Level', 'ID_Level')->all();
        $this->assertEquals($hris, $pa);
    }

    public function test_tambah_level_khusus_pa_menyalin_matriks_level_dasar(): void
    {
        $officer = (int) DB::table('HRIS_Level')->where('Keterangan', 'OFFICER')->value('ID_Level');
        $hasil = $this->master->tambah('Officer Specialist Uji', $officer, 4.5, true, 4);

        $this->assertGreaterThanOrEqual(PaLevelMasterService::ID_PA_PERTAMA, $hasil['id']);
        $this->assertSame('OFFICER SPECIALIST UJI', DB::table('N_PA_Level')->where('ID_Level', $hasil['id'])->value('Nama_Level'));
        $this->assertSame(
            DB::table('N_PA_Rater_Policy')->where('ID_Level', $officer)->where('Flag_Aktif', 'Y')->where('Status_Policy', 'ACTIVE')->count(),
            $hasil['disalin']['komposisi_penilai'],
        );
        $this->assertSame(DB::table('N_PA_Pillar_Weight')->where('ID_Level', $officer)->count(), $hasil['disalin']['bobot_pilar']);

        /* Ikatan soal tersalin BESERTA label kompetensi per bagiannya — tanpa label, usulan
           master gugur di Langkah 2 siklus ("belum dipetakan ke kompetensi"; UAT 13 Okt). */
        $ikatan = app(\App\Services\KPI\PerformanceAppraisal\PaQuestionBindingMasterService::class);
        foreach (DB::table('N_PA_Question_Binding_Master')->where('ID_Level', $officer)->where('Flag_Aktif', 'Y')->get() as $b) {
            $this->assertEquals(
                $ikatan->labelSel((int) $b->Id_PA_Question_Binding_Master),
                $ikatan->labelSelUntuk($hasil['id'], $b->PA_Rater_Category_Id === null ? null : (int) $b->PA_Rater_Category_Id, (string) $b->Id_Master_Ujian),
                "Label ikatan kategori {$b->PA_Rater_Category_Id} tidak ikut tersalin.",
            );
        }

        /* Sel salinan ditandai "perlu ditinjau" di Matriks Soal sampai admin menyimpannya. */
        $baris = collect(app(\App\Services\KPI\PerformanceAppraisal\PaQuestionBindingMasterService::class)->matriks()['level'] ?? [])
            ->firstWhere('id_level', $hasil['id']);
        $this->assertNotNull($baris, 'Level baru tidak muncul di Matriks Soal.');
        $terisi = collect($baris['sel'])->filter(fn ($sel) => $sel['terisi'] ?? false);
        $this->assertNotEmpty($terisi);
        $this->assertTrue($terisi->every(fn ($sel) => $sel['salinan_dari'] === 'OFFICER' && $sel['sahih']), json_encode($terisi));

        /* Muncul di daftar matriks, di antara OFFICER dan SENIOR OFFICER. */
        $nama = collect($this->master->daftar())->pluck('nama')->all();
        $i = array_search('OFFICER SPECIALIST UJI', $nama, true);
        $this->assertNotFalse($i);
        $this->assertLessThan($i, array_search('OFFICER', $nama, true));
        $this->assertGreaterThan($i, array_search('SENIOR OFFICER', $nama, true));
    }

    public function test_fk_matriks_menolak_level_yang_tidak_ada_di_master(): void
    {
        $this->expectException(QueryException::class);
        DB::table('N_PA_Pillar_Weight')->insert(['ID_Level' => 999999, 'Bobot_KPI' => 50, 'Bobot_Kompetensi' => 50]);
    }

    public function test_koreksi_ke_level_khusus_pa_dipakai_resolver(): void
    {
        $officer = (int) DB::table('HRIS_Level')->where('Keterangan', 'OFFICER')->value('ID_Level');
        $id = $this->master->tambah('Officer Specialist Uji', $officer, 4.5, false, 4)['id'];
        $k = DB::table('Karyawan as k')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('k.Aktif', 'Y')->where('v.ID_Level', $officer)
            ->whereNotExists(fn ($q) => $q->from('N_PA_Karyawan_Level_PA as lp')->whereColumn('lp.Kode_Karyawan', 'k.Kode_Karyawan')->where('lp.Flag_Aktif', 'Y'))
            ->first(['k.Kode_Perusahaan', 'k.Kode_Karyawan', 'v.ID_Level', 'v.nama_level']);
        $this->assertNotNull($k, 'Tidak ada OFFICER tanpa koreksi di data uji.');

        $this->assertContains($id, array_column(app(PaKoreksiLevelPaService::class)->opsiLevel(), 'id'));
        app(PaKoreksiLevelPaService::class)->simpan([
            'kode_perusahaan' => $k->Kode_Perusahaan, 'kode_karyawan' => $k->Kode_Karyawan, 'id_level_pa' => $id,
            'alasan' => 'Officer Specialist (uji).', 'berlaku_dari' => now()->subDay()->format('Y-m-d'),
        ], 4);

        $resolver = app(PaLevelPaResolver::class);
        $r = $resolver->resolusi((object) ['Kode_Perusahaan' => $k->Kode_Perusahaan, 'Kode_Karyawan' => $k->Kode_Karyawan,
            'ID_Level' => $officer, 'nama_level' => $k->nama_level], $resolver->koreksiBerlaku());
        $this->assertSame($id, $r['id_level']);
        $this->assertSame('OFFICER SPECIALIST UJI', $r['nama_level']);

        /* Masih dipakai koreksi aktif → tidak bisa dinonaktifkan. */
        $this->expectException(RuntimeException::class);
        $this->master->aturAktif($id, false, 4);
    }

    public function test_level_hris_tidak_disunting_dari_pa(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('berasal dari HRIS');
        $this->master->ubah((int) DB::table('HRIS_Level')->where('Keterangan', 'OFFICER')->value('ID_Level'), 'OFFICER BARU', 4, 4);
    }

    /* Di produksi level HRIS bisa berbeda dari dev: periksa menyebutnya tanpa mengubah apa pun,
       sinkron menyamakannya; level yang hilang dari HRIS hanya disebut. */
    public function test_periksa_dan_sinkron_dengan_hris(): void
    {
        PaLevelMasterService::sinkronHris(true);
        $officer = (int) DB::table('HRIS_Level')->where('Keterangan', 'OFFICER')->value('ID_Level');
        DB::table('N_PA_Level')->where('ID_Level', $officer)->update(['Nama_Level' => 'OFFICER LAMA']);
        DB::table('N_PA_Level')->insert(['ID_Level' => 999998, 'Nama_Level' => 'LEVEL HILANG UJI', 'Sumber' => 'HRIS',
            'Urutan' => 99, 'Flag_Aktif' => 'Y']);

        $p = $this->master->periksaHris();
        $this->assertFalse($p['sinkron']);
        $this->assertSame([$officer], array_column($p['beda'], 'id'));
        $this->assertSame([999998], array_column($p['hilang'], 'id'));
        $this->assertSame('OFFICER LAMA', DB::table('N_PA_Level')->where('ID_Level', $officer)->value('Nama_Level'), 'Periksa tidak boleh mengubah apa pun.');

        $hasil = PaLevelMasterService::sinkronHris(true);
        $this->assertSame(1, $hasil['diperbarui']);
        $sesudah = $this->master->periksaHris();
        $this->assertTrue($sesudah['sinkron']);
        $this->assertSame([999998], array_column($sesudah['hilang'], 'id'), 'Level yang hilang dari HRIS tidak dihapus otomatis.');
    }
}
