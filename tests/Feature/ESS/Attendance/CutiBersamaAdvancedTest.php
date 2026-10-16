<?php

namespace Tests\Feature\ESS\Attendance;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\TaskProcessing\CloudTaskService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CutiBersamaAdvancedTest extends TestCase
{
    use WithoutMiddleware;

    protected ?User $actor = null;

    protected string $token;

    protected array $createdCutiBersamaIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! filter_var(env('RUN_REAL_CUTI_BERSAMA_E2E', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Set RUN_REAL_CUTI_BERSAMA_E2E=true untuk menjalankan skenario real DB + GCP.');
        }

        $this->actor = User::query()
            ->whereHas('karyawan', function ($q) {
                $q->where('Aktif', 'Y')->whereNull('Tanggal_Resign');
            })
            ->first();

        if (! $this->actor) {
            $this->markTestSkipped('Tidak ada user aktif yang terhubung ke karyawan.');
        }

        $this->token = (string) config(
            'services.project_config.SCHEDULER_BEARER_TOKEN',
            'bdc43f93e7d057cd1dca14d5a6259eb45c5062cc4cf96641fee05b688b3d85d3',
        );

        Auth::login($this->actor);
    }

    protected function tearDown(): void
    {
        foreach (array_unique($this->createdCutiBersamaIds) as $id) {
            DB::table('N_HRIS_Cuti_Bersama')
                ->where('Id_Cuti_Bersama', $id)
                ->update([
                    'Flag_Aktif' => 'T',
                    'Updated_At' => now(),
                    'Updated_By' => $this->resolveActorAuditId(),
                ]);
        }

        parent::tearDown();
    }

    /** @test */
    public function it_runs_full_real_flow_execute_then_rollback_and_restores_balance(): void
    {
        $target = $this->findKaryawanByBalance('negative_or_zero') ?? $this->findKaryawanByBalance('positive');
        if (! $target) {
            $this->markTestSkipped('Tidak ada kandidat karyawan untuk skenario execute real.');
        }

        $beforePhysical = $this->getSaldoKaryawanFisikFromDb($target->Kode_Karyawan);
        $tanggal = $this->futureDate(20);

        $store = $this->postJson('/api/v1/cuti-bersama-adv/store', [
            'nama' => $this->makeScenarioName('FULL FLOW'),
            'selected_dates' => [$tanggal],
            'filter_kode_karyawan' => [$target->Kode_Karyawan],
            'execution_mode' => 'draft',
            'keterangan' => 'REAL E2E execute lalu rollback',
        ]);

        $store->assertOk()->assertJson(['success' => true]);
        $cbId = (int) data_get($store->json(), 'result.id');
        $this->createdCutiBersamaIds[] = $cbId;

        $execute = $this->postJson("/api/v1/cuti-bersama-adv/execute/{$cbId}");
        $execute->assertOk()->assertJson(['success' => true]);

        $executeLogId = (int) data_get($execute->json(), 'result.cloud_task_log_id');
        $executeLog = $this->waitForTaskTerminalState($executeLogId, 'CUTI_BERSAMA_EXECUTE');

        $this->assertNotNull($executeLog, 'Task execute tidak mencapai status terminal dalam batas waktu.');
        $this->assertSame('COMPLETED', strtoupper((string) $executeLog->Status));
        $this->assertSame(1, (int) ($executeLog->Total_Success ?? 0));
        $this->assertSame(0, (int) ($executeLog->Total_Failed ?? 0));

        $detail = DB::table('N_HRIS_Cuti_Bersama_Detail')
            ->where('Id_Cuti_Bersama', $cbId)
            ->where('Kode_Karyawan', $target->Kode_Karyawan)
            ->where('Status', 'SUCCESS')
            ->first();

        $this->assertNotNull($detail, 'Detail execute tidak ditemukan untuk target real.');
        $this->assertSame((float) $beforePhysical, (float) $detail->Saldo_Sebelum);
        $this->assertSame((float) ($beforePhysical - 1), (float) $detail->Saldo_Sesudah);

        $detailResponse = $this->getJson("/api/v1/cuti-bersama-adv/detail/{$cbId}");
        $detailResponse->assertOk();
        $this->assertSame('EXECUTED', data_get($detailResponse->json(), 'result.cuti_bersama.execution_snapshot.status'));

        $rollback = $this->postJson("/api/v1/cuti-bersama-adv/rollback/{$cbId}", [
            'scope' => 'all',
        ]);
        $rollback->assertOk()->assertJson(['success' => true]);

        $rollbackLogId = (int) data_get($rollback->json(), 'result.cloud_task_log_id');
        $rollbackLog = $this->waitForTaskTerminalState($rollbackLogId, 'CUTI_BERSAMA_ROLLBACK', 300);

        $this->assertNotNull($rollbackLog, 'Task rollback tidak mencapai status terminal dalam batas waktu.');
        $this->assertSame('COMPLETED', strtoupper((string) $rollbackLog->Status));
        $this->assertSame(1, (int) ($rollbackLog->Total_Success ?? 0));
        $this->assertSame(0, (int) ($rollbackLog->Total_Failed ?? 0));

        $afterPhysical = $this->getSaldoKaryawanFisikFromDb($target->Kode_Karyawan);
        $this->assertSame((float) $beforePhysical, (float) $afterPhysical, 'Saldo fisik tidak kembali seperti semula setelah rollback real.');

        $detailAfterRollback = DB::table('N_HRIS_Cuti_Bersama_Detail')
            ->where('Id_Cuti_Bersama', $cbId)
            ->where('Kode_Karyawan', $target->Kode_Karyawan)
            ->first();
        $this->assertSame('ROLLED_BACK', (string) ($detailAfterRollback->Status ?? ''));

        $detailAfterResponse = $this->getJson("/api/v1/cuti-bersama-adv/detail/{$cbId}");
        $detailAfterResponse->assertOk();
        $this->assertSame(
            'ROLLED_BACK',
            data_get($detailAfterResponse->json(), 'result.cuti_bersama.execution_snapshot.status'),
        );
    }

    /** @test */
    public function it_runs_real_scheduled_flow_and_can_cancel_before_execution(): void
    {
        $target = $this->findKaryawanByBalance('positive');
        if (! $target) {
            $this->markTestSkipped('Tidak ada kandidat saldo positif untuk skenario scheduled.');
        }

        $store = $this->postJson('/api/v1/cuti-bersama-adv/store', [
            'nama' => $this->makeScenarioName('SCHEDULED CANCEL'),
            'selected_dates' => [$this->futureDate(25)],
            'filter_kode_karyawan' => [$target->Kode_Karyawan],
            'execution_mode' => 'draft',
        ]);
        $store->assertOk();

        $cbId = (int) data_get($store->json(), 'result.id');
        $this->createdCutiBersamaIds[] = $cbId;

        $scheduledAt = now()->addMinutes(10)->format('Y-m-d H:i:s');
        $execute = $this->postJson("/api/v1/cuti-bersama-adv/execute/{$cbId}", [
            'scheduled_at' => $scheduledAt,
        ]);

        $execute->assertOk()->assertJson(['success' => true]);
        $executeLogId = (int) data_get($execute->json(), 'result.cloud_task_log_id');

        $dispatchLog = DB::table(CloudTaskService::LOG_TABLE)->where('Id_Log', $executeLogId)->first();
        $this->assertNotNull($dispatchLog);
        $this->assertSame('DISPATCHED', strtoupper((string) $dispatchLog->Status));
        $this->assertNotNull($dispatchLog->Scheduled_At);

        $cancel = $this->postJson("/api/v1/cuti-bersama-adv/cancel-execute/{$cbId}");
        $cancel->assertOk()->assertJson(['success' => true]);

        $cancelledLog = $this->waitForSpecificLogStatus($executeLogId, 'CANCELLED', 60);
        $this->assertNotNull($cancelledLog, 'Task scheduled tidak berubah menjadi CANCELLED.');

        $detailResponse = $this->getJson("/api/v1/cuti-bersama-adv/detail/{$cbId}");
        $detailResponse->assertOk();
        $this->assertSame(
            'CANCELLED',
            data_get($detailResponse->json(), 'result.cuti_bersama.execution_snapshot.status'),
        );
    }

    /** @test */
    public function it_blocks_real_overlap_execution_against_existing_executed_transaction(): void
    {
        $target = $this->findKaryawanByBalance('positive');
        if (! $target) {
            $this->markTestSkipped('Tidak ada kandidat saldo positif untuk skenario overlap.');
        }

        $tanggal = $this->futureDate(35);

        $storeA = $this->postJson('/api/v1/cuti-bersama-adv/store', [
            'nama' => $this->makeScenarioName('OVERLAP A'),
            'selected_dates' => [$tanggal],
            'filter_kode_karyawan' => [$target->Kode_Karyawan],
            'execution_mode' => 'draft',
        ]);
        $storeA->assertOk();

        $cbA = (int) data_get($storeA->json(), 'result.id');
        $this->createdCutiBersamaIds[] = $cbA;

        $executeA = $this->postJson("/api/v1/cuti-bersama-adv/execute/{$cbA}");
        $executeA->assertOk();

        $executeALog = $this->waitForTaskTerminalState(
            (int) data_get($executeA->json(), 'result.cloud_task_log_id'),
            'CUTI_BERSAMA_EXECUTE',
        );
        $this->assertNotNull($executeALog);
        $this->assertSame('COMPLETED', strtoupper((string) $executeALog->Status));

        $storeB = $this->postJson('/api/v1/cuti-bersama-adv/store', [
            'nama' => $this->makeScenarioName('OVERLAP B'),
            'selected_dates' => [$tanggal],
            'filter_kode_karyawan' => [$target->Kode_Karyawan],
            'execution_mode' => 'draft',
        ]);
        $storeB->assertOk();

        $cbB = (int) data_get($storeB->json(), 'result.id');
        $this->createdCutiBersamaIds[] = $cbB;

        $executeB = $this->postJson("/api/v1/cuti-bersama-adv/execute/{$cbB}");
        $executeB->assertStatus(400);
        $this->assertStringContainsString('overlap', strtolower((string) $executeB->json('message')));

        $rollbackA = $this->postJson("/api/v1/cuti-bersama-adv/rollback/{$cbA}", ['scope' => 'all']);
        $rollbackA->assertOk();

        $rollbackALog = $this->waitForTaskTerminalState(
            (int) data_get($rollbackA->json(), 'result.cloud_task_log_id'),
            'CUTI_BERSAMA_ROLLBACK',
            300,
        );
        $this->assertNotNull($rollbackALog);
        $this->assertSame('COMPLETED', strtoupper((string) $rollbackALog->Status));
    }

    /** @test */
    public function it_runs_real_partial_rollback_by_division_through_gcp(): void
    {
        $candidates = $this->findTwoRollbackCandidatesAcrossDivision();
        if (! $candidates) {
            $this->markTestSkipped('Tidak ditemukan 2 kandidat lintas divisi untuk skenario rollback parsial.');
        }

        $tanggal = $this->futureDate(45);
        $store = $this->postJson('/api/v1/cuti-bersama-adv/store', [
            'nama' => $this->makeScenarioName('PARTIAL DIVISI'),
            'selected_dates' => [$tanggal],
            'filter_kode_karyawan' => [
                $candidates['first']->Kode_Karyawan,
                $candidates['second']->Kode_Karyawan,
            ],
            'execution_mode' => 'draft',
        ]);
        $store->assertOk();

        $cbId = (int) data_get($store->json(), 'result.id');
        $this->createdCutiBersamaIds[] = $cbId;

        $execute = $this->postJson("/api/v1/cuti-bersama-adv/execute/{$cbId}");
        $execute->assertOk();
        $executeLog = $this->waitForTaskTerminalState(
            (int) data_get($execute->json(), 'result.cloud_task_log_id'),
            'CUTI_BERSAMA_EXECUTE',
        );
        $this->assertNotNull($executeLog);
        $this->assertSame('COMPLETED', strtoupper((string) $executeLog->Status));

        $rollback = $this->postJson("/api/v1/cuti-bersama-adv/rollback/{$cbId}", [
            'scope' => 'divisi',
            'divisi' => [$candidates['first']->nama_divisi],
        ]);
        $rollback->assertOk();

        $rollbackLog = $this->waitForTaskTerminalState(
            (int) data_get($rollback->json(), 'result.cloud_task_log_id'),
            'CUTI_BERSAMA_ROLLBACK',
            300,
        );
        $this->assertNotNull($rollbackLog);
        $this->assertSame('COMPLETED', strtoupper((string) $rollbackLog->Status));

        $firstStatus = DB::table('N_HRIS_Cuti_Bersama_Detail')
            ->where('Id_Cuti_Bersama', $cbId)
            ->where('Kode_Karyawan', $candidates['first']->Kode_Karyawan)
            ->value('Status');
        $secondStatus = DB::table('N_HRIS_Cuti_Bersama_Detail')
            ->where('Id_Cuti_Bersama', $cbId)
            ->where('Kode_Karyawan', $candidates['second']->Kode_Karyawan)
            ->value('Status');

        $this->assertSame('ROLLED_BACK', (string) $firstStatus);
        $this->assertSame('SUCCESS', (string) $secondStatus);

        $rollbackRest = $this->postJson("/api/v1/cuti-bersama-adv/rollback/{$cbId}", [
            'scope' => 'all',
        ]);
        $rollbackRest->assertOk();
        $this->waitForTaskTerminalState(
            (int) data_get($rollbackRest->json(), 'result.cloud_task_log_id'),
            'CUTI_BERSAMA_ROLLBACK',
            300,
        );
    }

    protected function waitForTaskTerminalState(
        int $logId,
        string $taskType,
        int $timeoutSeconds = 240,
        int $sleepSeconds = 5
    ): ?object {
        $deadline = now()->addSeconds($timeoutSeconds);

        do {
            $log = DB::table(CloudTaskService::LOG_TABLE)
                ->where('Id_Log', $logId)
                ->where('Task_Type', $taskType)
                ->first();

            if ($log && in_array(strtoupper((string) $log->Status), ['COMPLETED', 'FAILED', 'CANCELLED'], true)) {
                return $log;
            }

            sleep($sleepSeconds);
        } while (now()->lt($deadline));

        return DB::table(CloudTaskService::LOG_TABLE)->where('Id_Log', $logId)->first();
    }

    protected function waitForSpecificLogStatus(
        int $logId,
        string $expectedStatus,
        int $timeoutSeconds = 60,
        int $sleepSeconds = 3
    ): ?object {
        $deadline = now()->addSeconds($timeoutSeconds);
        $expected = strtoupper($expectedStatus);

        do {
            $log = DB::table(CloudTaskService::LOG_TABLE)->where('Id_Log', $logId)->first();
            if ($log && strtoupper((string) $log->Status) === $expected) {
                return $log;
            }

            sleep($sleepSeconds);
        } while (now()->lt($deadline));

        return null;
    }

    protected function findKaryawanByBalance(string $type = 'positive'): ?object
    {
        $query = DB::table('N_HRIS_VW_Saldo_Cuti_Karyawan as v')
            ->join('Karyawan as k', 'v.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as g', 'k.ID_Level_Jabatan', '=', 'g.ID_Level_Jabatan')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')
            ->where(function ($q) {
                $q->whereNull('g.ID_Level')->orWhere('g.ID_Level', '!=', 21);
            });

        if ($type === 'positive') {
            $query->where('v.Sisa_Cuti_Tampil', '>', 5);
        } elseif ($type === 'negative_or_zero') {
            $query->where(function ($q) {
                $q->where('v.Sisa_Cuti_Tampil', '<=', 0)->orWhereNull('v.Sisa_Cuti_Tampil');
            });
        }

        return $query->select('k.*')->first();
    }

    protected function findTwoRollbackCandidatesAcrossDivision(): ?array
    {
        $rows = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')
            ->whereNotNull('vd.nama_divisi')
            ->select('k.Kode_Karyawan', 'k.Nama', 'vd.nama_divisi', 'vd.nama_sub_divisi')
            ->limit(30)
            ->get();

        if ($rows->count() < 2) {
            return null;
        }

        $first = $rows->first();
        $second = $rows->first(fn ($row) => (string) $row->nama_divisi !== (string) $first->nama_divisi);

        if (! $first || ! $second) {
            return null;
        }

        return ['first' => $first, 'second' => $second];
    }

    protected function getSaldoKaryawanFisikFromDb(string $kodeKaryawan): float
    {
        $row = DB::table('HRIS_Buku_Cuti')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where('Flag_Aktif', 'Y')
            ->whereIn('Id_Jenis_Cuti', [1, 2])
            ->where(function ($q) {
                $q->where(function ($qDate) {
                    $today = now()->toDateString();
                    $qDate->whereDate('tanggal_expired', '>=', $today)->whereDate('Activated_At', '<=', $today);
                })->orWhere('sisa_cuti', '<', 0);
            })
            ->selectRaw('COALESCE(SUM(sisa_cuti), 0) as total_saldo')
            ->first();

        return (float) ($row->total_saldo ?? 0);
    }

    protected function resolveActorAuditId(): string
    {
        $userIdWeb = $this->actor?->karyawan?->UserID_Web;
        if (! empty($userIdWeb)) {
            return (string) $userIdWeb;
        }

        return (string) ($this->actor?->Id_Users ?? 'TEST');
    }

    protected function futureDate(int $daysAhead): string
    {
        return Carbon::now()->addDays($daysAhead)->toDateString();
    }

    protected function makeScenarioName(string $label): string
    {
        return 'E2E REAL '.$label.' '.now()->format('YmdHisv');
    }
}
