<?php

namespace Tests\Unit\ESS\Attendance;

use App\Services\ESS\Attendance\Processing\AttendanceDirtyMarkService;
use App\Services\ESS\Attendance\Processing\AttendanceDirtyResolverService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class AttendanceDirtyResolverServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_mark_many_resolves_user_absen_and_dedupes_targets(): void
    {
        $dirtyMarkService = Mockery::mock(AttendanceDirtyMarkService::class);
        $dirtyMarkService
            ->shouldReceive('markDirty')
            ->once()
            ->withArgs(function (
                string $kodePerusahaan,
                int $userId,
                string $tglAwal,
                string $tglAkhir,
                string $dirtyReason,
                string $requestSource,
                int $priority,
                ?string $requestedBy,
                bool $createQueue
            ) {
                return $kodePerusahaan === '001'
                    && $userId === 12345
                    && $tglAwal === '2026-05-01'
                    && $tglAkhir === '2026-05-01'
                    && $dirtyReason === 'UNIT_REASON'
                    && $requestSource === 'UNIT_SOURCE'
                    && $priority === 1
                    && $requestedBy === 'system'
                    && $createQueue === true;
            })
            ->andReturn(['success' => true]);

        $query = Mockery::mock();
        $query->shouldReceive('whereIn')->once()->with('Kode_Karyawan', ['K001'])->andReturnSelf();
        $query->shouldReceive('whereNotNull')->once()->with('UserID_Absen')->andReturnSelf();
        $query->shouldReceive('pluck')->once()->with('UserID_Absen', 'Kode_Karyawan')->andReturn(collect([
            'K001' => 12345,
        ]));

        DB::shouldReceive('table')->once()->with('Karyawan')->andReturn($query);

        $resolver = new AttendanceDirtyResolverService($dirtyMarkService);
        $result = $resolver->markMany(
            [
                ['kode_perusahaan' => '001', 'kode_karyawan' => 'K001', 'tanggal' => '2026-05-01'],
                ['kode_perusahaan' => '001', 'kode_karyawan' => 'K001', 'tgl_awal' => '2026-05-01', 'tgl_akhir' => '2026-05-01'],
            ],
            'UNIT_REASON',
            'UNIT_SOURCE',
            1,
        );

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['marked']);
    }

    public function test_permanent_effect_end_date_covers_current_cutoff_end(): void
    {
        Carbon::setTestNow('2026-05-16 08:00:00');
        config()->set('services.hcis.cutoff_payroll_date.start_day', 16);
        config()->set('services.hcis.cutoff_payroll_date.end_day', 15);
        config()->set('services.hcis.shift_request.future_max_days', 1);
        config()->set('services.hcis.leader_shift_request.future_max_days', 1);
        config()->set('services.hcis.swap_shift_request.future_max_days', 1);
        config()->set('services.hcis.attendance_dirty.rolling_horizon_days', 1);
        config()->set('services.hcis.attendance_dirty.permanent_horizon_days', 90);

        $resolver = new AttendanceDirtyResolverService(Mockery::mock(AttendanceDirtyMarkService::class));

        $this->assertSame('2026-06-15', $resolver->permanentEffectEndDate('2026-05-20'));
    }

    public function test_mark_permanent_effect_marks_bounded_dirty_range(): void
    {
        Carbon::setTestNow('2026-05-16 08:00:00');
        config()->set('services.hcis.cutoff_payroll_date.start_day', 16);
        config()->set('services.hcis.cutoff_payroll_date.end_day', 15);
        config()->set('services.hcis.shift_request.future_max_days', 1);
        config()->set('services.hcis.leader_shift_request.future_max_days', 1);
        config()->set('services.hcis.swap_shift_request.future_max_days', 1);
        config()->set('services.hcis.attendance_dirty.rolling_horizon_days', 1);
        config()->set('services.hcis.attendance_dirty.permanent_horizon_days', 90);

        $dirtyMarkService = Mockery::mock(AttendanceDirtyMarkService::class);
        $dirtyMarkService
            ->shouldReceive('markDirty')
            ->once()
            ->withArgs(function (
                string $kodePerusahaan,
                int $userId,
                string $tglAwal,
                string $tglAkhir,
                string $dirtyReason,
                string $requestSource,
                int $priority,
                ?string $requestedBy,
                bool $createQueue
            ) {
                return $kodePerusahaan === '001'
                    && $userId === 12345
                    && $tglAwal === '2026-05-20'
                    && $tglAkhir === '2026-06-15'
                    && $dirtyReason === 'PERMANENT_UNIT'
                    && $requestSource === 'PERMANENT_TEST'
                    && $priority === 1
                    && $requestedBy === 'system'
                    && $createQueue === true;
            })
            ->andReturn(['success' => true]);

        $query = Mockery::mock();
        $query->shouldReceive('whereIn')->once()->with('Kode_Karyawan', ['K001'])->andReturnSelf();
        $query->shouldReceive('whereNotNull')->once()->with('UserID_Absen')->andReturnSelf();
        $query->shouldReceive('pluck')->once()->with('UserID_Absen', 'Kode_Karyawan')->andReturn(collect([
            'K001' => 12345,
        ]));

        DB::shouldReceive('table')->once()->with('Karyawan')->andReturn($query);

        $resolver = new AttendanceDirtyResolverService($dirtyMarkService);
        $result = $resolver->markPermanentEffectByKodeKaryawan(
            'K001',
            '2026-05-20',
            'PERMANENT_UNIT',
            'PERMANENT_TEST',
        );

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['marked']);
    }

    public function test_permanent_effect_end_date_is_capped_by_configured_horizon(): void
    {
        Carbon::setTestNow('2026-05-16 08:00:00');
        config()->set('services.hcis.cutoff_payroll_date.start_day', 16);
        config()->set('services.hcis.cutoff_payroll_date.end_day', 15);
        config()->set('services.hcis.shift_request.future_max_days', 120);
        config()->set('services.hcis.leader_shift_request.future_max_days', 120);
        config()->set('services.hcis.swap_shift_request.future_max_days', 120);
        config()->set('services.hcis.attendance_dirty.rolling_horizon_days', 7);
        config()->set('services.hcis.attendance_dirty.permanent_horizon_days', 30);

        $resolver = new AttendanceDirtyResolverService(Mockery::mock(AttendanceDirtyMarkService::class));

        $this->assertSame('2026-07-01', $resolver->permanentEffectEndDate('2026-06-01'));
    }

    public function test_permanent_effect_end_date_never_before_start_date(): void
    {
        Carbon::setTestNow('2026-05-01 08:00:00');
        config()->set('services.hcis.cutoff_payroll_date.start_day', 16);
        config()->set('services.hcis.cutoff_payroll_date.end_day', 15);
        config()->set('services.hcis.shift_request.future_max_days', 0);
        config()->set('services.hcis.leader_shift_request.future_max_days', 0);
        config()->set('services.hcis.swap_shift_request.future_max_days', 0);
        config()->set('services.hcis.attendance_dirty.rolling_horizon_days', 0);
        config()->set('services.hcis.attendance_dirty.permanent_horizon_days', 1);

        $resolver = new AttendanceDirtyResolverService(Mockery::mock(AttendanceDirtyMarkService::class));

        $this->assertSame('2026-12-01', $resolver->permanentEffectEndDate('2026-12-01'));
    }
}
