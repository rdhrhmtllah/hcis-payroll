<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Jobs\KPI\PerformanceAppraisal\PembentukanPenugasanPaJob;
use App\Models\ESS\Employee\Models\User;
use App\Services\ESS\Activity\Center\ActivityCenterService;
use App\Services\KPI\PerformanceAppraisal\Katalog\PaLevelDikecualikan;
use App\Services\KPI\PerformanceAppraisal\PaAssignmentBuilderService;
use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use App\Services\KPI\PerformanceAppraisal\PaCyclePreparationService;
use App\Services\KPI\PerformanceAppraisal\PaPembentukanTugasService;
use App\Services\KPI\PerformanceAppraisal\PaTugasLatarSiklusService;
use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Pembentukan penugasan sebagai TUGAS LATAR: layar memulai, job berantai mengerjakan.
 *
 * ── Apa yang dijaga ───────────────────────────────────────────────────────────
 *
 *  1. Satu siklus, satu tugas — klik ganda atau tab kedua mendapat tugas yang sama.
 *  2. Rantainya MAJU sampai tak ada peserta yang diambil, lalu berakhir `ready` dengan angka
 *     dari `progresPembentukan()` — bukan berhenti sesudah potongan pertama.
 *  3. Siklus yang sudah dikunci ditolak di muka, tanpa tugas.
 *  4. Dihentikan berarti berhenti sebelum potongan berikutnya.
 *  5. Database penuh terbaca sebagai kalimat yang bisa ditindaklanjuti (UAT 13 Okt).
 */
class PembentukanLatarTest extends TestCase
{
    use DatabaseTransactions;

    private int $cycleId;

    protected function setUp(): void
    {
        parent::setUp();

        $pengguna = User::whereNotNull('User_Key')->first();
        $this->assertNotNull($pengguna, 'Tidak ada pengguna ber-User_Key.');
        $this->actingAs($pengguna);

        /* Sama dengan PratinjauPenyiapanTest: pengecualian level adalah data hidup, dan satu
           level yang dikecualikan membuat siklus uji tanpa peserta. */
        DB::table('N_PA_Level_Exclusion')->where('Flag_Aktif', 'Y')->whereIn('ID_Level', [2])->update(['Flag_Aktif' => 'T']);
        PaLevelDikecualikan::lupakan();

        $kode = 'UJI-BTK-'.substr(bin2hex(random_bytes(4)), 0, 8);
        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => $kode,
            'Nama_Cycle' => 'Siklus uji pembentukan latar',
            'Tahun' => 2026,
            'Status_Cycle' => 'DRAFT',
            'Periode_Dinilai_Dari' => '2026-01-01',
            'Periode_Dinilai_Sampai' => '2026-06-30',
        ]);
        $this->cycleId = (int) DB::table('N_PA_Cycle')->where('Kode_Cycle', $kode)->value('Id_PA_Cycle');

        app(PaCycleGate::class)->lupakanMemo();
        app(PaCyclePreparationService::class)->siapkan($this->cycleId, 4);
        app(PaCycleGate::class)->lupakanMemo();

        Queue::fake();
    }

    public function test_mulai_mencatat_satu_tugas_per_siklus_dan_mengantrekan_job(): void
    {
        $url = "/api/v1/performance-appraisal/cycles/{$this->cycleId}/penugasan-massal/latar";

        $pertama = $this->postJson($url)->assertOk()->json('result');
        $this->assertTrue($pertama['baru']);
        $this->assertSame('queued', $pertama['tugas']['status']);

        $kedua = $this->postJson($url)->assertOk()->json('result');
        $this->assertFalse($kedua['baru']);
        $this->assertSame($pertama['tugas']['id'], $kedua['tugas']['id']);

        Queue::assertPushed(PembentukanPenugasanPaJob::class, 1);
        $this->assertSame($pertama['tugas']['id'], $this->getJson($url)->assertOk()->json('result.tugas.id'));
    }

    public function test_rantai_maju_sampai_habis_lalu_ready_dengan_angka_progres(): void
    {
        $tugas = app(PaPembentukanTugasService::class);
        $id = $this->mulai();
        $total = (int) app(PaAssignmentBuilderService::class)->progresPembentukan($this->cycleId)['total'];
        $this->assertGreaterThan(0, $total, 'Siklus uji tanpa peserta; uji ini tidak menguji apa pun.');

        /* Job dijalankan tangan, satu per satu, selama rantainya mengantrekan lanjutan. */
        $putaran = 0;
        do {
            $sebelum = Queue::pushed(PembentukanPenugasanPaJob::class)->count();
            $this->jalankanJob($id);
            $putaran++;
        } while (Queue::pushed(PembentukanPenugasanPaJob::class)->count() > $sebelum && $putaran < 50);

        $layar = $tugas->bentukLayar($tugas->baris($id));
        $this->assertSame('ready', $layar['status']);
        $this->assertSame(100, $layar['persen']);
        $this->assertGreaterThan(0, $layar['dibuat']);
        $this->assertSame(
            app(PaAssignmentBuilderService::class)->progresPembentukan($this->cycleId)['sudah'],
            $layar['ringkasan']['sudah'],
        );
        $this->assertGreaterThanOrEqual((int) ceil($total / PembentukanPenugasanPaJob::BATAS_PER_JOB), $putaran);

        $item = collect(app(ActivityCenterService::class)->forUser('')->list(200))->firstWhere('id', $id);
        $this->assertStringContainsString('Pembentukan Penugasan PA', $item['label']);
        $this->assertStringContainsString('peserta berpenugasan', $item['label']);
        $this->assertStringContainsString("/siapkan/{$this->cycleId}?langkah=PENUGASAN", $item['open_url']);
    }

    public function test_siklus_terkunci_ditolak_di_muka_tanpa_tugas(): void
    {
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)->update(['Status_Cycle' => 'LOCKED']);
        app(PaCycleGate::class)->lupakanMemo();

        $this->postJson("/api/v1/performance-appraisal/cycles/{$this->cycleId}/penugasan-massal/latar")->assertStatus(422);

        $this->assertNull(app(PaPembentukanTugasService::class)->terakhirUntukSiklus($this->cycleId));
        Queue::assertNothingPushed();
    }

    public function test_dihentikan_tidak_membentuk_lagi_dan_bisa_diulang(): void
    {
        $tugas = app(PaPembentukanTugasService::class);
        $id = $this->mulai();

        $this->assertTrue($tugas->hentikan($id));
        $this->jalankanJob($id);

        $this->assertSame(0, (int) app(PaAssignmentBuilderService::class)->progresPembentukan($this->cycleId)['sudah']);
        $this->assertSame('failed', $tugas->baris($id)->Status);

        $this->assertTrue($tugas->siapkanUlang($id));
        $this->assertSame('queued', $tugas->baris($id)->Status);
        $this->assertSame('cepat', $tugas->bentukLayar($tugas->baris($id))['jalur']);
    }

    public function test_database_penuh_terbaca_sebagai_kalimat_yang_bisa_ditindaklanjuti(): void
    {
        $pesan = PaTugasLatarSiklusService::pesanTerbaca(
            "SQLSTATE[42000]: Could not allocate space for object 'dbo.N_PA_Assignment' because the 'PRIMARY' filegroup is full."
        );

        $this->assertStringStartsWith('Database penuh', $pesan);
        $this->assertStringContainsString('admin server', $pesan);

        $id = $this->mulai();
        app(PaPembentukanTugasService::class)->gagal($id, 'x filegroup is full y');
        $this->assertStringStartsWith(
            'Database penuh',
            (string) DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)->value('Error_Message'),
        );
    }

    private function mulai(): string
    {
        return app(PaPembentukanTugasService::class)->mulai(
            $this->cycleId,
            app(PaAssignmentBuilderService::class)->progresPembentukan($this->cycleId),
            4,
            optional(auth()->user()->karyawan)->Kode_Karyawan,
        )['id'];
    }

    private function jalankanJob(string $id): void
    {
        (new PembentukanPenugasanPaJob($id))->handle(
            app(PaPembentukanTugasService::class),
            app(PaAssignmentBuilderService::class),
        );
    }
}
