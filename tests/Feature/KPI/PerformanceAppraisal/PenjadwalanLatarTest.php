<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Jobs\KPI\PerformanceAppraisal\PenjadwalanHcLearnPaJob;
use App\Models\ESS\Employee\Models\User;
use App\Services\ESS\Activity\Center\ActivityCenterService;
use App\Services\KPI\PerformanceAppraisal\PaHcLearnScheduleService;
use App\Services\KPI\PerformanceAppraisal\PaPenjadwalanTugasService;
use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Penjadwalan rater sebagai TUGAS LATAR: layar memulai, job berantai mengerjakan.
 *
 * ── Apa yang dijaga ───────────────────────────────────────────────────────────
 *
 *  1. Satu siklus, satu tugas. Menekan tombol dua kali (atau dari dua tab) tidak melahirkan
 *     rantai job kedua yang memperebutkan penugasan yang sama.
 *  2. Fase berpindah dengan benar: kirim → tunggu HC Learn → selesai, dan tugas baru dinyatakan
 *     selesai saat SEMUA penugasan bertautan — bukan saat kiriman terakhir berangkat.
 *  3. Kiriman yang gagal menghentikan kiriman (tanpa mengulang tembok yang sama tiap batch),
 *     dan penantian yang melewati batas waktu berakhir "gagal" dengan sebab yang terbaca.
 *  4. Tugasnya terlihat di panel Tugas & Aktivitas, dengan tautan ke langkah 5.
 *
 * HC Learn dipalsukan di tingkat HTTP, seperti `PenjadwalanHcLearnTest`.
 */
class PenjadwalanLatarTest extends TestCase
{
    use DatabaseTransactions;

    private int $cycleId;

    private int $levelId;

    private int $kategoriAtasan;

    /** @var list<object> */
    private array $stok = [];

    /** @var list<int> */
    private array $assignmentId = [];

    /** Jawaban `status` HC Learn saat ini — diubah di tengah uji tanpa mendaftar fake baru. */
    private array $barisStatus = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->stok = DB::table('Karyawan')->where('Aktif', 'Y')
            ->orderBy('Kode_Karyawan')
            ->limit(3)
            ->get(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan'])
            ->all();
        $this->assertCount(3, $this->stok, 'Uji ini butuh tiga karyawan aktif.');

        $pengguna = User::whereNotNull('User_Key')->first();
        $this->assertNotNull($pengguna, 'Tidak ada pengguna ber-User_Key.');
        $this->actingAs($pengguna);

        $this->levelId = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $this->kategoriAtasan = (int) DB::table('N_PA_Rater_Category')->where('Kode_Kategori', 'ATASAN')->value('Id_PA_Rater_Category');

        $this->cycleId = $this->buatSiklus();
        $this->buatSel('100160');

        $peserta = $this->buatPeserta($this->stok[0]);
        $this->assignmentId[] = $this->buatPenugasan($peserta, $this->stok[1]);
        $this->assignmentId[] = $this->buatPenugasan($peserta, $this->stok[2]);

        /* Antrean bukan `sync` — supaya penantian HC Learn diantrekan ulang, bukan diakhiri. */
        config(['queue.default' => 'cloudtasks']);
        Queue::fake();
    }

    public function test_mulai_mencatat_satu_tugas_per_siklus_dan_mengantrekan_job(): void
    {
        $url = "/api/v1/performance-appraisal/cycles/{$this->cycleId}/penjadwalan/latar";

        $pertama = $this->postJson($url)->assertOk()->json('result');
        $this->assertTrue($pertama['baru']);
        $this->assertSame('queued', $pertama['tugas']['status']);
        $this->assertSame(2, $pertama['ringkasan']['belum']);

        /* Tekanan kedua — tab lain, klik ganda — mendapat tugas yang SAMA. */
        $kedua = $this->postJson($url)->assertOk()->json('result');
        $this->assertFalse($kedua['baru']);
        $this->assertSame($pertama['tugas']['id'], $kedua['tugas']['id']);

        Queue::assertPushed(PenjadwalanHcLearnPaJob::class, 1);

        $baris = app(PaPenjadwalanTugasService::class)->baris($pertama['tugas']['id']);
        $this->assertSame($this->cycleId, (int) $baris->Reference_Id);

        $status = $this->getJson($url)->assertOk()->json('result');
        $this->assertSame($pertama['tugas']['id'], $status['tugas']['id']);
    }

    public function test_jendela_tidak_sah_ditolak_di_muka_tanpa_tugas(): void
    {
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)->update(['Pengisian_Dari' => null]);

        $this->postJson("/api/v1/performance-appraisal/cycles/{$this->cycleId}/penjadwalan/latar")
            ->assertStatus(422);

        $this->assertNull(app(PaPenjadwalanTugasService::class)->terakhirUntukSiklus($this->cycleId));
        Queue::assertNothingPushed();
    }

    public function test_job_kirim_lalu_tunggu_lalu_selesai_hanya_saat_semua_bertautan(): void
    {
        $id = $this->mulaiTugas();
        $this->palsukanHcLearn(['proses-A']);

        $this->jalankanJob($id);

        $layar = $this->layar($id);
        $this->assertSame('processing', $layar['status']);
        $this->assertSame('tunggu', $layar['fase'], 'Semua terkirim → fase menunggu HC Learn.');
        $this->assertSame(2, $layar['ringkasan']['antre']);
        $this->assertSame(50, $layar['persen'], 'Separuh pertama = terkirim; tautan belum ada.');
        Queue::assertPushed(PenjadwalanHcLearnPaJob::class, fn ($j) => $j->delay !== null);

        /* HC Learn baru menjawab SATU dari dua: tugas tetap menunggu, bukan selesai. */
        $this->palsukanStatus('proses-A', [$this->barisBerhasil(0)]);
        $this->jalankanJob($id);
        $this->assertSame('processing', $this->layar($id)['status']);
        $this->assertSame(1, $this->layar($id)['ringkasan']['antre']);

        $this->palsukanStatus('proses-A', [$this->barisBerhasil(0), $this->barisBerhasil(1)]);
        $this->jalankanJob($id);

        $layar = $this->layar($id);
        $this->assertSame('ready', $layar['status']);
        $this->assertSame(100, $layar['persen']);
    }

    /* Penugasan baru (revisi, ganti penilai) selagi tugas menunggu HC Learn: tugas kembali ke
       fase kirim, bukan menunggu selamanya tanpa pernah mengirimnya. */
    public function test_penugasan_baru_saat_menunggu_membuat_tugas_kembali_mengirim(): void
    {
        $id = $this->mulaiTugas();
        $this->palsukanHcLearn(['proses-A', 'proses-B']);
        $this->jalankanJob($id);
        $this->assertSame('tunggu', $this->layar($id)['fase']);

        $peserta = (int) DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $this->assignmentId[0])->value('PA_Participant_Id');
        $baru = DB::table('Karyawan')->where('Aktif', 'Y')->whereNotIn('Kode_Karyawan', collect($this->stok)->pluck('Kode_Karyawan'))->orderBy('Kode_Karyawan')->first();
        $this->stok[] = $baru;
        $this->assignmentId[] = $this->buatPenugasan($peserta, $baru);

        $this->jalankanJob($id);

        $this->assertSame('kirim', $this->layar($id)['fase']);
        $this->assertSame(1, $this->layar($id)['ringkasan']['belum']);
    }

    public function test_kiriman_gagal_menghentikan_kiriman_dan_tugas_gagal_dengan_sebab(): void
    {
        $id = $this->mulaiTugas();
        $this->palsukanHcLearn(['proses-A'], siapSemua: false);

        $this->jalankanJob($id);

        $layar = $this->layar($id);
        $this->assertSame('failed', $layar['status']);
        $this->assertStringContainsString('2 belum terkirim', (string) $layar['galat']);
        Queue::assertNotPushed(PenjadwalanHcLearnPaJob::class);

        /* Diulang: kembali ke fase kirim, baris tugas yang SAMA. */
        $this->assertTrue(app(PaPenjadwalanTugasService::class)->siapkanUlang($id));
        $this->assertSame('queued', $this->layar($id)['status']);
        $this->assertSame('kirim', $this->layar($id)['fase']);
    }

    public function test_penantian_yang_melewati_batas_waktu_berakhir_gagal(): void
    {
        $id = $this->mulaiTugas();
        $this->palsukanHcLearn(['proses-A']);
        $this->jalankanJob($id);

        app(PaPenjadwalanTugasService::class)->catat($id, [
            'mulai_tunggu' => now()->subMinutes(PenjadwalanHcLearnPaJob::BATAS_TUNGGU_MENIT + 1)->toDateTimeString(),
        ]);
        $this->palsukanStatus('proses-A', []);

        $this->jalankanJob($id);

        $layar = $this->layar($id);
        $this->assertSame('failed', $layar['status']);
        $this->assertStringContainsString('2 masih antre di HC Learn', (string) $layar['galat']);
    }

    public function test_tugas_tampil_di_panel_dan_bisa_dihentikan(): void
    {
        $id = $this->mulaiTugas();

        $item = collect(app(ActivityCenterService::class)->forUser('')->list(200))
            ->firstWhere('id', $id);

        $this->assertNotNull($item, 'Tugas penjadwalan tidak tampil di Tugas & Aktivitas.');
        $this->assertSame(PaPenjadwalanTugasService::TASK_TYPE, $item['type']);
        $this->assertStringContainsString('Penjadwalan Rater PA', $item['label']);
        $this->assertStringContainsString("/siapkan/{$this->cycleId}?langkah=PENUGASAN", $item['open_url']);
        $this->assertNotNull($item['abort_url']);
        $this->assertNull($item['dismiss_url'], 'Tugas berjalan tidak boleh disembunyikan.');

        $this->postJson($item['abort_url'])->assertOk();
        $this->assertSame('failed', $this->layar($id)['status']);

        /* Job yang tiba sesudah dihentikan tidak mengirim apa pun. */
        Http::fake();
        $this->jalankanJob($id);
        Http::assertNothingSent();
    }

    /* ------------------------------------------------------------------ *
     * Bangunan uji                                                        *
     * ------------------------------------------------------------------ */

    private function mulaiTugas(): string
    {
        $jadwal = app(PaHcLearnScheduleService::class);

        return app(PaPenjadwalanTugasService::class)->mulai(
            $this->cycleId,
            $jadwal->ringkasan($this->cycleId),
            null,
            optional(auth()->user()->karyawan)->Kode_Karyawan,
        )['id'];
    }

    private function jalankanJob(string $id): void
    {
        (new PenjadwalanHcLearnPaJob($id))->handle(
            app(PaPenjadwalanTugasService::class),
            app(PaHcLearnScheduleService::class),
        );
    }

    private function layar(string $id): array
    {
        $tugas = app(PaPenjadwalanTugasService::class);

        return $tugas->bentukLayar(DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)->first());
    }

    private function barisBerhasil(int $i): array
    {
        return [
            'Id_Integrasi' => (int) DB::table('N_PA_Assignment_Integration')
                ->where('PA_Assignment_Id', $this->assignmentId[$i])->where('Flag_Aktif', 'Y')
                ->value('Id_PA_Assignment_Integration'),
            'Kode_Karyawan' => $this->stok[$i + 1]->Kode_Karyawan,
            'status' => 'BERHASIL',
            'api_ref' => str_repeat((string) $i, 32),
            'short_token' => 'EVOG31G'.$i,
            'akses_otp' => 'XCBADFB'.$i,
            'waktu_mulai' => '2026-09-01 08:00:00',
            'waktu_akhir' => '2026-09-30 17:00:00',
            'link_ujian' => 'https://hclearn.example.com/evo/rekrutmen?wo_aut=EVOG31G'.$i,
        ];
    }

    /** @param list<string> $prosesId */
    private function palsukanHcLearn(array $prosesId, bool $siapSemua = true): void
    {
        $antrean = array_map(fn ($id) => Http::response([
            'success' => true,
            'status' => 202,
            'result' => [
                'process_id' => $id,
                'total_subjek' => 2,
                'status' => 'ANTRE',
                'url_status' => 'https://hclearn.example.com/api/v1/pa/penjadwalan/status/'.$id,
                'id_master_ujian' => 100160,
                'nama_ujian' => 'Employee Assessment 360 - STAFF',
            ],
        ], 202), $prosesId);

        Http::fake([
            '*/api/v1/pa/checking/karyawan*' => Http::response([
                'success' => true,
                'result' => [
                    'data' => array_map(fn ($k) => [
                        'dicari' => $k->Kode_Karyawan,
                        'ada' => $siapSemua,
                        'siap_dipakai' => $siapSemua,
                        'alasan' => $siapSemua ? 'SIAP' : 'BELUM_TERDAFTAR',
                        'keterangan' => $siapSemua ? 'Karyawan terdaftar.' : 'Karyawan belum ada di HCLearn.',
                    ], $this->stok),
                ],
            ]),
            '*/api/v1/pa/penjadwalan/store' => Http::sequence($antrean),
            /* Satu stub untuk seluruh uji: Http::fake MENAMBAH stub dan yang pertama cocok
               yang menang, jadi stub baru per tahap tidak pernah terpakai. */
            '*/api/v1/pa/penjadwalan/status/*' => fn () => Http::response([
                'success' => true,
                'status' => 200,
                'result' => [
                    'process_id' => 'proses-A',
                    'status' => $this->barisStatus === [] ? 'ANTRE' : 'SELESAI',
                    'ringkasan' => ['total' => count($this->barisStatus), 'antre' => 0],
                    'result' => $this->barisStatus,
                ],
            ]),
        ]);
    }

    /** @param list<array<string, mixed>> $baris */
    private function palsukanStatus(string $processId, array $baris): void
    {
        $this->barisStatus = $baris;
    }

    private function buatSiklus(): int
    {
        $kode = 'UJI-LTR-'.substr(bin2hex(random_bytes(4)), 0, 8);

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => $kode,
            'Nama_Cycle' => 'Siklus uji penjadwalan latar',
            'Tahun' => (int) date('Y'),
            'Status_Cycle' => 'OPEN',
            'Pengisian_Dari' => now()->subDay()->format('Y-m-d H:i:s'),
            'Pengisian_Sampai' => now()->addDays(20)->format('Y-m-d H:i:s'),
        ]);

        return (int) DB::table('N_PA_Cycle')->where('Kode_Cycle', $kode)->value('Id_PA_Cycle');
    }

    private function buatSel(string $idMasterUjian): void
    {
        DB::table('N_PA_Cycle_Question_Package')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'ID_Level' => $this->levelId,
            'PA_Rater_Category_Id' => $this->kategoriAtasan,
            'Id_Master_Ujian' => $idMasterUjian,
            'Nama_Ujian_Sumber' => 'Ujian uji '.$idMasterUjian,
            'Sumber_Kontrak' => 'HCLEARN',
            'Flag_Aktif' => 'Y',
        ]);
    }

    private function buatPeserta(object $k): int
    {
        DB::table('N_PA_Participant')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'Kode_Perusahaan' => $k->Kode_Perusahaan,
            'Kode_Karyawan' => $k->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $k->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $k->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $this->levelId,
            'Status_Peserta' => 'DIIKUTKAN',
        ]);

        return (int) DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('Kode_Karyawan', $k->Kode_Karyawan)
            ->value('Id_PA_Participant');
    }

    private function buatPenugasan(int $participantId, object $penilai): int
    {
        DB::table('N_PA_Assignment')->insert([
            'PA_Participant_Id' => $participantId,
            'Kode_Perusahaan_Penilai' => $penilai->Kode_Perusahaan,
            'Kode_Karyawan_Penilai' => $penilai->Kode_Karyawan,
            'Nama_Penilai_Saat_Cycle' => $penilai->Nama,
            'PA_Rater_Category_Id' => $this->kategoriAtasan,
            'Bobot_Dalam_Kategori' => 100,
            'Status_Penugasan' => 'BELUM_MULAI',
        ]);

        return (int) DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $participantId)
            ->where('Kode_Karyawan_Penilai', $penilai->Kode_Karyawan)
            ->value('Id_PA_Assignment');
    }
}
