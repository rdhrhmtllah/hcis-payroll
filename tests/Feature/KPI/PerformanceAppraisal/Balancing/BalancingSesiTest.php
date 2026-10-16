<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Balancing;

use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingException;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingSesiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanBalancing;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanSiklusKurva;
use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use Tests\TestCase;

class BalancingSesiTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanBalancing;
    use MenyiapkanLembarPenilaian;
    use MenyiapkanSiklusKurva;

    private function layanan(): PaBalancingSesiService
    {
        return app(PaBalancingSesiService::class);
    }

    private function sesiBaru(array $p): array
    {
        $this->palsukanAturan($p['kategori_id']);
        $forum = DB::table('Karyawan')->where('Aktif', 'Y')->orderByDesc('Kode_Karyawan')->first(['Kode_Perusahaan', 'Kode_Karyawan']);

        return $this->layanan()->buat($p['cycle_id'], [
            'nama' => 'Balancing Staff–SPV', 'pic_user_id' => (int) DB::table('KPI_Users')->orderBy('Id_Users')->value('Id_Users'),
            'forum' => [['kode_perusahaan' => $forum->Kode_Perusahaan, 'kode_karyawan' => $forum->Kode_Karyawan, 'peran' => 'Dept Head']],
            'peserta_ids' => [$p['participant_id']],
        ], 1);
    }

    private function galat(callable $f, string $kode): void
    {
        try {
            $f();
            $this->fail("Harus ditolak dengan {$kode}.");
        } catch (PaBalancingException $e) {
            $this->assertSame($kode, $e->errorCode, $e->getMessage());
        }
    }

    public function test_buat_sesi_membekukan_before_dan_bobot(): void
    {
        $p = $this->pesertaDihitung();
        $d = $this->sesiBaru($p);

        $this->assertSame('TERBUKA', $d['status']);
        $this->assertCount(1, $d['forum']);
        $orang = $d['peserta'][0];
        $this->assertSame('BELUM', $orang['status_tinjau']);
        $this->assertSame(77.5, $orang['sebelum']['nilai_kompetensi']);
        $k = collect($orang['kompetensi'])->keyBy('competency_id');
        $this->assertSame(70.0, $k[$p['kompetensi'][0]]['sebelum']);
        $this->assertSame(70.0, $k[$p['kompetensi'][0]]['sesudah']);
        $this->assertEqualsWithDelta(0.5, $k[$p['kompetensi'][0]]['bobot'], 1e-6);
        $this->assertNotEmpty($orang['kualitatif'], 'Satu baris per kompetensi kualitatif aktif.');
    }

    public function test_peserta_yang_sedang_dibahas_ditolak(): void
    {
        $p = $this->pesertaDihitung();
        $this->sesiBaru($p);

        $this->galat(fn () => $this->sesiBaru($p), PaBalancingException::PESERTA_TIDAK_LAYAK);
    }

    public function test_simpan_menolak_luar_rentang_dan_tanpa_alasan(): void
    {
        $p = $this->pesertaDihitung();
        $d = $this->sesiBaru($p);
        $k1 = $p['kompetensi'][0];

        $this->galat(fn () => $this->layanan()->simpanDraft($d['id'], $d['versi_data'], [[
            'participant_id' => $p['participant_id'], 'kompetensi' => [['competency_id' => $k1, 'nilai_sesudah' => 101, 'alasan' => 'x']],
        ]], null, 1), PaBalancingException::NILAI_DI_LUAR_RENTANG);

        $this->galat(fn () => $this->layanan()->simpanDraft($d['id'], $d['versi_data'], [[
            'participant_id' => $p['participant_id'], 'kompetensi' => [['competency_id' => $k1, 'nilai_sesudah' => 80, 'alasan' => '  ']],
        ]], null, 1), PaBalancingException::ALASAN_WAJIB);
    }

    public function test_simpan_menurunkan_status_tinjau_dan_menaikkan_versi(): void
    {
        $p = $this->pesertaDihitung();
        $d = $this->sesiBaru($p);
        $k1 = $p['kompetensi'][0];

        $d2 = $this->layanan()->simpanDraft($d['id'], $d['versi_data'], [[
            'participant_id' => $p['participant_id'], 'alasan' => 'Konsisten memimpin proyek',
            'kompetensi' => [['competency_id' => $k1, 'nilai_sesudah' => 80]],
            'kualitatif' => [['competency_id' => $d['peserta'][0]['kualitatif'][0]['competency_id'], 'isi_sesudah' => '<p onclick="x()">Rapi</p><script>1</script>']],
        ]], '<p>Rapat 1</p>', 1);

        $this->assertSame('DRAFT', $d2['status']);
        $this->assertSame($d['versi_data'] + 1, $d2['versi_data']);
        $this->assertSame('DIUBAH', $d2['peserta'][0]['status_tinjau']);
        $this->assertSame('<p>Rapi</p>', $d2['peserta'][0]['kualitatif'][0]['sesudah'], 'Teks kaya wajib disaring server.');

        // Dikembalikan ke Before → bukan DIUBAH lagi.
        $d3 = $this->layanan()->simpanDraft($d2['id'], $d2['versi_data'], [[
            'participant_id' => $p['participant_id'], 'status_tinjau' => 'TANPA_PERUBAHAN',
            'kompetensi' => [['competency_id' => $k1, 'nilai_sesudah' => 70, 'alasan' => null]],
            'kualitatif' => [['competency_id' => $d['peserta'][0]['kualitatif'][0]['competency_id'], 'isi_sesudah' => $d['peserta'][0]['kualitatif'][0]['sebelum']]],
        ]], null, 1);
        $this->assertSame('TANPA_PERUBAHAN', $d3['peserta'][0]['status_tinjau']);
    }

    public function test_versi_basi_ditolak(): void
    {
        $p = $this->pesertaDihitung();
        $d = $this->sesiBaru($p);
        $this->layanan()->simpanDraft($d['id'], $d['versi_data'], [], '<p>a</p>', 1);

        $this->galat(fn () => $this->layanan()->simpanDraft($d['id'], $d['versi_data'], [], '<p>b</p>', 1), PaBalancingException::VERSI_BERUBAH);
    }

    public function test_finalkan_menuntut_semua_ditinjau_lalu_mengunci(): void
    {
        $p = $this->pesertaDihitung();
        $d = $this->sesiBaru($p);

        $this->galat(fn () => $this->layanan()->finalkan($d['id'], $d['versi_data'], 1), PaBalancingException::PESERTA_BELUM_DITINJAU);

        $d2 = $this->layanan()->simpanDraft($d['id'], $d['versi_data'], [['participant_id' => $p['participant_id'], 'status_tinjau' => 'TANPA_PERUBAHAN']], null, 1);
        $d3 = $this->layanan()->finalkan($d2['id'], $d2['versi_data'], 1);

        $this->assertSame('FINAL', $d3['status']);
        $this->galat(fn () => $this->layanan()->simpanDraft($d3['id'], $d3['versi_data'], [], null, 1), PaBalancingException::SESI_TERKUNCI);
    }

    public function test_batalkan_wajib_alasan_dan_membebaskan_peserta(): void
    {
        $p = $this->pesertaDihitung();
        $d = $this->sesiBaru($p);

        $this->galat(fn () => $this->layanan()->batalkan($d['id'], 'no', 1), PaBalancingException::DATA_TIDAK_SAH);
        $this->assertSame('DIBATALKAN', $this->layanan()->batalkan($d['id'], 'Rapat ditunda', 1)['status']);
        $this->assertSame('TERBUKA', $this->sesiBaru($p)['status'], 'Peserta sesi batal boleh masuk sesi baru.');
    }

    public function test_siklus_belum_ditutup_ditolak(): void
    {
        $p = $this->pesertaDihitung();
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $p['cycle_id'])->update(['Status_Cycle' => 'OPEN']);

        $this->galat(fn () => $this->sesiBaru($p), PaBalancingException::SIKLUS_BELUM_DITUTUP);
    }
    /** Review final, Kritis 1: sesi FINAL yang belum dihitung ulang tidak boleh ditimpa sesi baru. */
    public function test_peserta_sesi_final_belum_dihitung_ditolak_masuk_sesi_baru(): void
    {
        $p = $this->pesertaDihitung();
        $this->sesiLangsung($p['cycle_id'], [$p['participant_id'] => [$p['kompetensi'][0] => 90.0]]);

        $this->galat(fn () => $this->sesiBaru($p), PaBalancingException::PESERTA_TIDAK_LAYAK);
    }

    /** Review final, Kritis 2: siklus KURVA yang sebagian hasilnya sudah terbit tidak boleh membuka sesi. */
    public function test_siklus_kurva_dengan_hasil_terbit_menolak_sesi(): void
    {
        $level = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $s = $this->siklusKurva([$level => [70, 72, 74]]);
        app(PaKurvaService::class)->kunci($s['cycle_id'], null);
        DB::table('N_PA_Result')->where('PA_Participant_Id', $s['peserta'][$level][0])->update(['Status_Hasil' => 'TERBIT']);

        $this->galat(fn () => $this->layanan()->buat($s['cycle_id'], [
            'nama' => 'Sesi kurva', 'pic_user_id' => (int) DB::table('KPI_Users')->orderBy('Id_Users')->value('Id_Users'),
            'forum' => [], 'peserta_ids' => [$s['peserta'][$level][1]],
        ], null), PaBalancingException::SUDAH_ADA_YANG_TERBIT);
    }
    /** Review akhir Bagian 2, Penting 2: kutip di catatan admin tidak boleh membuat peserta "DIUBAH". */
    public function test_kualitatif_dengan_kutip_dikirim_balik_tetap_tanpa_perubahan(): void
    {
        $p = $this->pesertaDihitung();
        $ql = (int) DB::table('N_PA_Competency as k')->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'k.PA_Competency_Type_Id')
            ->where('t.Kode_Type', 'KUALITATIF')->where('k.Flag_Aktif', 'Y')->orderBy('k.Id_PA_Competency')->value('k.Id_PA_Competency');
        DB::table('N_PA_Result_Note')->insert([
            'PA_Result_Id' => $p['result_id'], 'PA_Competency_Id' => $ql, 'PA_Assignment_Id' => null,
            'Isi' => "Karyawan's \"kuat\" & teliti", 'Urutan_Tampil' => 1, 'Flag_Aktif' => 'Y',
        ]);
        $d = $this->sesiBaru($p);
        $q = collect($d['peserta'][0]['kualitatif'])->firstWhere('competency_id', $ql);

        $d2 = $this->layanan()->simpanDraft($d['id'], $d['versi_data'], [[
            'participant_id' => $p['participant_id'], 'status_tinjau' => 'TANPA_PERUBAHAN',
            'kualitatif' => [['competency_id' => $ql, 'isi_sesudah' => $q['sebelum']]],
        ]], null, null);

        $this->assertSame('TANPA_PERUBAHAN', $d2['peserta'][0]['status_tinjau']);
    }

    /** Tim penilai wajib minimal satu orang; peran tidak diisi tangan melainkan dari jabatan. */
    public function test_tim_penilai_wajib_dan_peran_dari_jabatan(): void
    {
        $p = $this->pesertaDihitung();
        $this->palsukanAturan($p['kategori_id']);
        $pic = (int) DB::table('KPI_Users')->orderBy('Id_Users')->value('Id_Users');

        $this->galat(fn () => $this->layanan()->buat($p['cycle_id'], [
            'nama' => 'Tanpa tim', 'pic_user_id' => $pic, 'forum' => [], 'peserta_ids' => [$p['participant_id']],
        ], 1), PaBalancingException::DATA_TIDAK_SAH);

        $k = DB::table('Karyawan as k')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as g', 'g.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('k.Aktif', 'Y')->whereNull('k.Tanggal_Resign')->whereNotNull('g.nama_jabatan')->where('g.nama_jabatan', '<>', 'NOT SET')
            ->orderBy('k.Kode_Karyawan')->first(['k.Kode_Perusahaan', 'k.Kode_Karyawan', 'g.nama_jabatan']);

        $d = $this->layanan()->buat($p['cycle_id'], [
            'nama' => 'Dengan tim', 'pic_user_id' => $pic,
            // Peran yang dikirim layar diabaikan; dua kiriman orang yang sama dicatat sekali.
            'forum' => [
                ['kode_perusahaan' => $k->Kode_Perusahaan, 'kode_karyawan' => $k->Kode_Karyawan, 'peran' => 'karangan'],
                ['kode_perusahaan' => $k->Kode_Perusahaan, 'kode_karyawan' => $k->Kode_Karyawan],
            ],
            'peserta_ids' => [$p['participant_id']],
        ], 1);

        $this->assertCount(1, $d['forum']);
        $this->assertSame(trim($k->nama_jabatan), $d['forum'][0]['peran']);
    }

    /** Alasan dicatat per orang: wajib bila ada nilai yang diubah, tidak per kompetensi. */
    public function test_alasan_per_orang_wajib_bila_ada_perubahan(): void
    {
        $p = $this->pesertaDihitung();
        $d = $this->sesiBaru($p);
        $k1 = $p['kompetensi'][0];

        $this->galat(fn () => $this->layanan()->simpanDraft($d['id'], $d['versi_data'], [[
            'participant_id' => $p['participant_id'], 'alasan' => '',
            'kompetensi' => [['competency_id' => $k1, 'nilai_sesudah' => 80, 'alasan' => 'alasan per kompetensi tidak cukup']],
        ]], null, 1), PaBalancingException::ALASAN_WAJIB);

        $d2 = $this->layanan()->simpanDraft($d['id'], $d['versi_data'], [[
            'participant_id' => $p['participant_id'], 'alasan' => 'Kesepakatan forum',
            'kompetensi' => [['competency_id' => $k1, 'nilai_sesudah' => 80]],
        ]], null, 1);
        $this->assertSame('Kesepakatan forum', $d2['peserta'][0]['alasan']);

        // Tanpa perubahan, alasan boleh dikosongkan lagi.
        $d3 = $this->layanan()->simpanDraft($d2['id'], $d2['versi_data'], [[
            'participant_id' => $p['participant_id'], 'alasan' => '',
            'kompetensi' => [['competency_id' => $k1, 'nilai_sesudah' => $d['peserta'][0]['kompetensi'][0]['sebelum']]],
        ]], null, 1);
        $this->assertNull($d3['peserta'][0]['alasan']);
    }

    /** Kualitatif yang dibahas di Balancing hanya Strength dan Development Area. */
    public function test_kualitatif_hanya_strength_dan_development_area(): void
    {
        $d = $this->sesiBaru($this->pesertaDihitung());
        $kode = array_column($d['peserta'][0]['kualitatif'], 'kode');
        sort($kode);

        $this->assertSame(PaBalancingSesiService::KUALITATIF_BALANCING, $kode);
    }
}
