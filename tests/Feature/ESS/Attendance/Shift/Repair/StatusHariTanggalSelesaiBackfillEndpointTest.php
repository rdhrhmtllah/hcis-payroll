<?php

namespace Tests\Feature\ESS\Attendance\Shift\Repair;

use App\Services\ESS\Attendance\Processing\AttendanceDirtyResolverService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class StatusHariTanggalSelesaiBackfillEndpointTest extends TestCase
{
    use DatabaseTransactions;

    private const ENDPOINT = '/api/v1/internal/ess/attendance/shift/status-hari/backfill-tanggal-selesai';

    private string $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = (string) DB::selectOne('SELECT DB_NAME() AS Nama')->Nama;
        config([
            'services.hcis.status_hari_backfill.token' => 'token-uji-status-hari',
            'services.hcis.status_hari_backfill.apply_enabled' => false,
            'services.hcis.status_hari_backfill.environment' => '',
            'services.hcis.status_hari_backfill.database' => '',
        ]);
    }

    public function test_endpoint_gagal_tertutup_tanpa_bearer_token(): void
    {
        $this->postJson(self::ENDPOINT, [
            'target_database' => $this->database,
            'target_environment' => 'development',
        ])
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'STATUS_HARI_BACKFILL_UNAUTHORIZED');
    }

    public function test_dry_run_mengklasifikasi_bukti_dan_tidak_menulis(): void
    {
        $fixture = $this->buatFixture();

        $response = $this->withToken('token-uji-status-hari')->postJson(self::ENDPOINT, [
            'target_database' => $this->database,
            'target_environment' => 'development',
            'dry_run' => true,
            'kode_perusahaan' => $fixture['company'],
            'batch_size' => 20,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('result.mode', 'DRY_RUN')
            ->assertJsonPath('result.mutation_plan.strategy', 'DEACTIVATE_AND_INSERT')
            ->assertJsonPath('result.mutation_plan.ordering_policy', 'PRESERVE_ORIGINAL_CREATED_AT')
            ->assertJsonPath('result.mutation_plan.deactivate_rows', 5)
            ->assertJsonPath('result.mutation_plan.insert_rows', 5)
            ->assertJsonPath('result.summary.invalid_total', 6)
            ->assertJsonPath('result.summary.auto_fixable_total', 5)
            ->assertJsonPath('result.summary.manual_review_total', 1)
            ->assertJsonPath('result.summary.classification.WORK_AUTO_ONE_DAY', 1)
            ->assertJsonPath('result.summary.classification.STATUS_SEMENTARA_ONE_DAY', 1)
            ->assertJsonPath('result.summary.classification.STATUS_PERMANEN_OFF_CLOSE_AT_CAUSAL_SHIFT', 1)
            ->assertJsonPath('result.summary.classification.STATUS_PERMANEN_OFF_RESTORE_OPEN', 1)
            ->assertJsonPath('result.summary.classification.STATUS_PERMANEN_WORK_RESTORE_OPEN', 1)
            ->assertJsonPath('result.summary.classification.MANUAL_REVIEW', 1);

        foreach ($fixture['old_end_by_id'] as $id => $oldEnd) {
            $this->assertSame($oldEnd, $this->tanggalSelesai((int) $id));
        }
    }

    public function test_dirty_plan_tidak_menggabungkan_tanggal_yang_berjauhan(): void
    {
        $company = 'U'.str_pad((string) random_int(1, 99), 2, '0', STR_PAD_LEFT);
        $employee = 'E'.str_pad((string) random_int(1, 999999), 8, '0', STR_PAD_LEFT);

        foreach (['2099-01-10', '2099-05-20'] as $index => $date) {
            $this->buatStatus([
                'Kode_Perusahaan' => $company,
                'Kode_Karyawan' => $employee,
                'Status_Hari' => 'WORK',
                'Hari' => null,
                'Tgl_Mulai' => $date,
                'Tgl_Selesai' => '2099-01-01',
                'No_Transaksi_Reference' => 'SH99-99-UJI-DIRTY-'.$index,
                'Keterangan' => 'Auto override: shift applied on OFF day',
                'Updated_At' => '2099-06-01 08:00:00.000',
                'Updated_By' => '9001',
            ]);
        }

        $result = $this->withToken('token-uji-status-hari')->postJson(self::ENDPOINT, [
            'target_database' => $this->database,
            'target_environment' => 'development',
            'dry_run' => true,
            'kode_perusahaan' => $company,
            'batch_size' => 20,
        ])
            ->assertOk()
            ->assertJsonPath('result.summary.batch_auto_fixable', 2)
            ->assertJsonPath('result.dirty_plan.range_count', 2)
            ->assertJsonPath('result.dirty_plan.user_date_count', 2)
            ->json('result');

        $this->assertSame(
            ['2099-01-10', '2099-05-20'],
            collect($result['dirty_plan']['ranges'])->pluck('tgl_awal')->sort()->values()->all(),
        );
    }

    public function test_apply_memerlukan_plan_yang_sama_dan_hanya_mengubah_kandidat_otomatis(): void
    {
        $fixture = $this->buatFixture();
        $actorId = DB::table('KPI_Users')
            ->where('Flag_Active', 'Y')
            ->where('Is_System_Admin', 'Y')
            ->value('Id_Users');

        if (! $actorId) {
            $this->markTestSkipped('System Admin aktif untuk audit backfill tidak tersedia.');
        }

        config([
            'services.hcis.status_hari_backfill.apply_enabled' => true,
            'services.hcis.status_hari_backfill.environment' => 'development',
            'services.hcis.status_hari_backfill.database' => $this->database,
        ]);

        $dirty = Mockery::mock(AttendanceDirtyResolverService::class);
        $dirty->shouldReceive('permanentEffectEndDate')
            ->zeroOrMoreTimes()
            ->andReturnUsing(fn (string $start) => Carbon::parse($start)->addDays(90)->toDateString());
        $dirty->shouldReceive('markMany')
            ->once()
            ->withArgs(function ($targets, $reason, $source, $priority, $requestedBy) use ($actorId) {
                return $targets->isNotEmpty()
                    && $reason === 'STATUS_HARI_TANGGAL_SELESAI_BACKFILLED'
                    && $source === 'STATUS_HARI_BACKFILL_ENDPOINT'
                    && $priority === 1
                    && $requestedBy === (string) $actorId;
            })
            ->andReturn(['success' => true, 'marked' => 2, 'results' => []]);
        $this->app->instance(AttendanceDirtyResolverService::class, $dirty);

        $scope = [
            'target_database' => $this->database,
            'target_environment' => 'development',
            'dry_run' => true,
            'kode_perusahaan' => $fixture['company'],
            'batch_size' => 20,
        ];
        $dryRun = $this->withToken('token-uji-status-hari')->postJson(self::ENDPOINT, $scope)
            ->assertOk()
            ->json('result');

        $apply = array_merge($scope, [
            'dry_run' => false,
            'plan_hash' => $dryRun['plan_hash'],
            'expected_auto_fixable_count' => $dryRun['summary']['batch_auto_fixable'],
            'actor_id' => (int) $actorId,
            'confirmation' => 'APPLY_STATUS_HARI_BACKFILL',
        ]);

        $staleApply = $apply;
        $staleApply['plan_hash'] = str_repeat('0', 64);
        $this->withToken('token-uji-status-hari')->postJson(self::ENDPOINT, $staleApply)
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'STATUS_HARI_BACKFILL_PLAN_MISMATCH');
        $this->assertSame('2099-01-01', $this->tanggalSelesai($fixture['auto_work']));

        // Bahkan perubahan audit actor tanpa perubahan timestamp harus
        // membatalkan hash dry-run lama.
        DB::table('N_HRIS_Status_Hari_Karyawan')
            ->where('Id', $fixture['auto_work'])
            ->update(['Updated_By' => '9002']);
        $this->withToken('token-uji-status-hari')->postJson(self::ENDPOINT, $apply)
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'STATUS_HARI_BACKFILL_PLAN_MISMATCH');
        DB::table('N_HRIS_Status_Hari_Karyawan')
            ->where('Id', $fixture['auto_work'])
            ->update(['Updated_By' => '9001']);

        $applyResponse = $this->withToken('token-uji-status-hari')->postJson(self::ENDPOINT, $apply);
        $applyResponse
            ->assertOk()
            ->assertJsonPath('result.applied.deactivated_rows', 5)
            ->assertJsonPath('result.applied.inserted_rows', 5)
            ->assertJsonPath('result.applied.remaining_invalid_in_scope', 1)
            ->assertJsonPath('result.applied.status', 'APPLIED')
            ->assertJsonCount(5, 'result.applied.replacements');

        $this->assertSame('2099-01-01', $this->tanggalSelesai($fixture['auto_work']));
        $this->assertSame('2099-02-01', $this->tanggalSelesai($fixture['temporary_off']));
        $this->assertSame('2098-12-31', $this->tanggalSelesai($fixture['permanent_off_close']));
        $this->assertSame('2099-01-01', $this->tanggalSelesai($fixture['permanent_off_future']));
        $this->assertSame('2099-02-01', $this->tanggalSelesai($fixture['permanent_work']));
        $this->assertSame('2098-12-01', $this->tanggalSelesai($fixture['manual']));

        $expectedEndByOldId = [
            $fixture['auto_work'] => '2099-01-10',
            $fixture['temporary_off'] => '2099-02-10',
            $fixture['permanent_off_close'] => '2099-01-09',
            $fixture['permanent_off_future'] => null,
            $fixture['permanent_work'] => null,
        ];
        $replacements = collect($applyResponse->json('result.applied.replacements'))->keyBy('old_id');

        // Retry dengan plan lama harus ditolak dan tidak boleh membuat versi ketiga.
        $rowCountBeforeRetry = DB::table('N_HRIS_Status_Hari_Karyawan')
            ->where('Kode_Perusahaan', $fixture['company'])
            ->count();
        $this->withToken('token-uji-status-hari')->postJson(self::ENDPOINT, $apply)
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'STATUS_HARI_BACKFILL_PLAN_MISMATCH');
        $this->assertSame(
            $rowCountBeforeRetry,
            DB::table('N_HRIS_Status_Hari_Karyawan')->where('Kode_Perusahaan', $fixture['company'])->count(),
        );

        $replacementRows = DB::table('N_HRIS_Status_Hari_Karyawan')
            ->where('Kode_Perusahaan', $fixture['company'])
            ->where('Flag_Aktif', 'Y')
            ->whereIn('No_Transaksi_Reference', [
                'SH99-99-UJI-AUTO',
                'TSH99-99-UJI-TEMP',
                'TSH99-99-UJI-PERM-CLOSE',
                'TSH99-99-UJI-PERM-FUTURE',
                'TSH99-99-UJI-PERM-WORK',
            ])
            ->get()
            ->keyBy('No_Transaksi_Reference');

        $this->assertCount(5, $replacementRows);

        foreach ($expectedEndByOldId as $oldId => $expectedEnd) {
            $old = DB::table('N_HRIS_Status_Hari_Karyawan')->where('Id', $oldId)->first();
            $new = $replacementRows->get($old->No_Transaksi_Reference);

            $this->assertSame('T', $old->Flag_Aktif);
            $this->assertSame((string) $actorId, (string) $old->Updated_By);
            $this->assertNotNull($new);
            $this->assertNotSame((int) $old->Id, (int) $new->Id);
            $this->assertSame((int) $new->Id, (int) $replacements->get($oldId)['new_id']);
            $this->assertSame('Y', $new->Flag_Aktif);
            $this->assertSame($expectedEnd, $new->Tgl_Selesai === null ? null : Carbon::parse($new->Tgl_Selesai)->toDateString());
            $this->assertSame(
                Carbon::parse($old->Created_At)->format('Y-m-d H:i:s.v'),
                Carbon::parse($new->Created_At)->format('Y-m-d H:i:s.v'),
            );
            $this->assertSame((string) $old->Created_By, (string) $new->Created_By);
            $this->assertNotNull($new->Updated_At);
            $this->assertSame((string) $actorId, (string) $new->Updated_By);
            $this->assertSame($old->Status_Hari, $new->Status_Hari);
            $this->assertSame($old->Hari, $new->Hari);
            $this->assertSame($old->Keterangan, $new->Keterangan);
        }

        $this->assertSame('Y', DB::table('N_HRIS_Status_Hari_Karyawan')->where('Id', $fixture['manual'])->value('Flag_Aktif'));
    }

    public function test_apply_production_ditolak_walaupun_payload_lengkap(): void
    {
        config([
            'services.hcis.status_hari_backfill.apply_enabled' => true,
            'services.hcis.status_hari_backfill.environment' => 'production',
            'services.hcis.status_hari_backfill.database' => $this->database,
        ]);

        $this->withToken('token-uji-status-hari')->postJson(self::ENDPOINT, [
            'target_database' => $this->database,
            'target_environment' => 'production',
            'dry_run' => false,
            'batch_size' => 1,
            'plan_hash' => str_repeat('0', 64),
            'expected_auto_fixable_count' => 0,
            'actor_id' => 1,
            'confirmation' => 'APPLY_STATUS_HARI_BACKFILL',
        ])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'STATUS_HARI_BACKFILL_APPLY_ENVIRONMENT_FORBIDDEN');
    }

    public function test_apply_mempertahankan_urutan_created_at_agar_work_tetap_menang_pada_tanggal_yang_sama(): void
    {
        $company = 'U'.str_pad((string) random_int(1, 99), 2, '0', STR_PAD_LEFT);
        $employee = 'E'.str_pad((string) random_int(1, 999999), 8, '0', STR_PAD_LEFT);
        $tanggal = '2099-06-10';
        $updatedAt = '2020-06-09 08:00:02.000';

        $workId = $this->buatStatus([
            'Kode_Perusahaan' => $company,
            'Kode_Karyawan' => $employee,
            'Status_Hari' => 'WORK',
            'Hari' => null,
            'Tgl_Mulai' => $tanggal,
            'Tgl_Selesai' => $tanggal,
            'No_Transaksi_Reference' => 'SH99-99-UJI-ORDER',
            'Keterangan' => 'Auto override: shift applied on OFF day',
            'Created_At' => '2020-06-09 08:00:01.000',
            'Updated_At' => $updatedAt,
            'Updated_By' => '9001',
        ]);
        $offId = $this->buatStatus([
            'Kode_Perusahaan' => $company,
            'Kode_Karyawan' => $employee,
            'Status_Hari' => 'OFF',
            'Hari' => 5,
            'Tgl_Mulai' => $tanggal,
            'Tgl_Selesai' => '2099-06-01',
            'No_Transaksi_Reference' => 'TSH99-99-UJI-ORDER',
            'Keterangan' => 'Fixture urutan resolver',
            'Created_At' => '2020-06-09 07:00:00.000',
            'Updated_At' => $updatedAt,
            'Updated_By' => '9001',
        ]);
        $actorId = DB::table('KPI_Users')
            ->where('Flag_Active', 'Y')
            ->where('Is_System_Admin', 'Y')
            ->value('Id_Users');

        if (! $actorId) {
            $this->markTestSkipped('System Admin aktif untuk audit backfill tidak tersedia.');
        }

        config([
            'services.hcis.status_hari_backfill.apply_enabled' => true,
            'services.hcis.status_hari_backfill.environment' => 'development',
            'services.hcis.status_hari_backfill.database' => $this->database,
        ]);

        $scope = [
            'target_database' => $this->database,
            'target_environment' => 'development',
            'dry_run' => true,
            'kode_perusahaan' => $company,
            'batch_size' => 20,
        ];
        $dryRun = $this->withToken('token-uji-status-hari')->postJson(self::ENDPOINT, $scope)
            ->assertOk()
            ->assertJsonPath('result.summary.auto_fixable_total', 1)
            ->assertJsonPath('result.summary.classification.OFF_CLOSE_AT_CAUSAL_SHIFT', 1)
            ->json('result');

        $response = $this->withToken('token-uji-status-hari')->postJson(self::ENDPOINT, array_merge($scope, [
            'dry_run' => false,
            'plan_hash' => $dryRun['plan_hash'],
            'expected_auto_fixable_count' => 1,
            'actor_id' => (int) $actorId,
            'confirmation' => 'APPLY_STATUS_HARI_BACKFILL',
        ]))->assertOk();

        $replacementId = (int) $response->json('result.applied.replacements.0.new_id');
        $replacement = DB::table('N_HRIS_Status_Hari_Karyawan')->where('Id', $replacementId)->first();

        $this->assertSame('T', DB::table('N_HRIS_Status_Hari_Karyawan')->where('Id', $offId)->value('Flag_Aktif'));
        $this->assertSame('Y', DB::table('N_HRIS_Status_Hari_Karyawan')->where('Id', $workId)->value('Flag_Aktif'));
        $this->assertSame('2020-06-09 07:00:00.000', Carbon::parse($replacement->Created_At)->format('Y-m-d H:i:s.v'));
        $this->assertSame('WORK', DB::selectOne(
            'SELECT dbo.N_HRIS_FN_GetStatusHari(?, ?, ?) AS Status_Hari',
            [$company, $employee, $tanggal],
        )->Status_Hari ?? null);
    }

    private function buatFixture(): array
    {
        $company = 'U'.str_pad((string) random_int(1, 99), 2, '0', STR_PAD_LEFT);
        $employee = 'E'.str_pad((string) random_int(1, 999999), 8, '0', STR_PAD_LEFT);
        $updatedAt = '2099-01-11 08:00:00.000';

        $autoWork = $this->buatStatus([
            'Kode_Perusahaan' => $company,
            'Kode_Karyawan' => $employee,
            'Status_Hari' => 'WORK',
            'Hari' => null,
            'Tgl_Mulai' => '2099-01-10',
            'Tgl_Selesai' => '2099-01-01',
            'No_Transaksi_Reference' => 'SH99-99-UJI-AUTO',
            'Keterangan' => 'Auto override: shift applied on OFF day',
            'Created_At' => '2099-01-11 07:59:59.900',
            'Updated_At' => $updatedAt,
            'Updated_By' => '9001',
        ]);

        $temporaryOff = $this->buatStatusDenganSumber(
            $company,
            $employee,
            'TSH99-99-UJI-TEMP',
            'SEMENTARA',
            'OFF',
            '2099-02-10',
            '2099-02-01',
            '2099-02-11 08:00:00.000',
        );
        $permanentOffClose = $this->buatStatusDenganSumber(
            $company,
            $employee,
            'TSH99-99-UJI-PERM-CLOSE',
            'PERMANEN',
            'OFF',
            '2099-01-01',
            '2098-12-31',
            $updatedAt,
        );
        $permanentOffFuture = $this->buatStatusDenganSumber(
            $company,
            $employee,
            'TSH99-99-UJI-PERM-FUTURE',
            'PERMANEN',
            'OFF',
            '2099-01-20',
            '2099-01-01',
            $updatedAt,
        );
        $permanentWork = $this->buatStatusDenganSumber(
            $company,
            $employee,
            'TSH99-99-UJI-PERM-WORK',
            'PERMANEN',
            'WORK',
            '2099-03-01',
            '2099-02-01',
            $updatedAt,
        );
        $manual = $this->buatStatus([
            'Kode_Perusahaan' => $company,
            'Kode_Karyawan' => $employee.'M',
            'Status_Hari' => 'OFF',
            'Hari' => 2,
            'Tgl_Mulai' => '2099-04-01',
            'Tgl_Selesai' => '2098-12-01',
            'No_Transaksi_Reference' => 'TSH99-99-UJI-MANUAL',
            'Keterangan' => 'Tidak punya bukti sumber',
            'Updated_At' => '2099-04-02 08:00:00.000',
            'Updated_By' => '9001',
        ]);

        $ids = [$autoWork, $temporaryOff, $permanentOffClose, $permanentOffFuture, $permanentWork, $manual];

        return [
            'company' => $company,
            'auto_work' => $autoWork,
            'temporary_off' => $temporaryOff,
            'permanent_off_close' => $permanentOffClose,
            'permanent_off_future' => $permanentOffFuture,
            'permanent_work' => $permanentWork,
            'manual' => $manual,
            'old_end_by_id' => collect($ids)->mapWithKeys(fn (int $id) => [$id => $this->tanggalSelesai($id)])->all(),
        ];
    }

    private function buatStatusDenganSumber(
        string $company,
        string $employee,
        string $reference,
        string $target,
        string $status,
        string $start,
        string $end,
        string $updatedAt,
    ): int {
        $detailId = DB::table('N_HRIS_Transaksi_Status_Hari_Detail')->insertGetId([
            'No_Transaksi' => $reference,
            'Kode_Perusahaan' => $company,
            'Kode_Karyawan' => $employee,
            'Target_Table' => $target,
        ]);
        DB::table('N_HRIS_Transaksi_Status_Hari_Det')->insert([
            'Id_Detail' => $detailId,
            'No_Transaksi' => $reference,
            'Tanggal' => $start,
            'Status_Hari' => $status,
            'Jenis_Pengajuan' => 'FUTURE',
        ]);

        return $this->buatStatus([
            'Kode_Perusahaan' => $company,
            'Kode_Karyawan' => $employee,
            'Status_Hari' => $status,
            'Hari' => $status === 'OFF' ? 2 : 1,
            'Tgl_Mulai' => $start,
            'Tgl_Selesai' => $end,
            'No_Transaksi_Reference' => $reference,
            'Keterangan' => 'Fixture '.$target,
            'Updated_At' => $updatedAt,
            'Updated_By' => '9001',
        ]);
    }

    private function buatStatus(array $values): int
    {
        return DB::table('N_HRIS_Status_Hari_Karyawan')->insertGetId(array_merge([
            'Flag_Aktif' => 'Y',
            'Created_At' => '2098-12-01 08:00:00.000',
            'Created_By' => '9001',
        ], $values));
    }

    private function tanggalSelesai(int $id): ?string
    {
        $value = DB::table('N_HRIS_Status_Hari_Karyawan')->where('Id', $id)->value('Tgl_Selesai');

        return $value === null ? null : Carbon::parse($value)->toDateString();
    }
}
