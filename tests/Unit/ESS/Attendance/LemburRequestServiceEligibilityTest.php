<?php

namespace Tests\Unit\ESS\Attendance;

use App\Helpers\WhatsappLogHelper;
use App\Services\ESS\Attendance\Overtime\Request\LemburRequestService;
use App\Services\ESS\Attendance\Processing\AttendanceDirtyResolverService;
use App\Services\ESS\Attendance\Processing\HrisAttendanceSnapshotService;
use App\Services\SYSTEM\Governance\Authorization\Shared\AccessContext;
use App\Services\SYSTEM\Governance\Authorization\Shared\FunctionalRole;
use App\Services\SYSTEM\Notification\WhatsappMessageBuilder;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class LemburRequestServiceEligibilityTest extends TestCase
{
    public function test_blocks_when_same_type_overtime_already_exists_on_same_effective_date(): void
    {
        $service = new TestableLemburRequestService;

        $result = $service->evaluateUserEligibilityPublic(
            'LEMBUR_AWAL',
            '05:00:00',
            '06:00:00',
            '2026-03-19',
            'WORK',
            '07:00:00',
            '16:00:00',
            'SHIFT-1',
            'FALSE',
            '001',
            'EMP-1',
            [
                'existing_type_dates' => [
                    'EMP-1' => [
                        'LEMBUR_AWAL' => ['2026-03-19'],
                    ],
                ],
                'overlap_intervals' => [],
                'next_shift_start' => [],
            ],
            true,
        );

        $this->assertFalse($result['is_active']);
        $this->assertSame(
            'Sudah ditugaskan lembur awal pada tanggal kerja ini.',
            $result['reason'],
        );
    }

    public function test_allows_workday_overtime_when_only_other_type_exists_without_overlap(): void
    {
        $service = new TestableLemburRequestService;

        $result = $service->evaluateUserEligibilityPublic(
            'LEMBUR',
            '17:00:00',
            '19:00:00',
            '2026-03-19',
            'WORK',
            '08:00:00',
            '16:00:00',
            'SHIFT-1',
            'TRUE',
            '001',
            'EMP-1',
            [
                'existing_type_dates' => [
                    'EMP-1' => [
                        'LEMBUR_AWAL' => ['2026-03-19'],
                    ],
                ],
                'overlap_intervals' => [
                    'EMP-1' => [
                        [
                            'start' => '2026-03-19 05:00:00',
                            'end' => '2026-03-19 08:00:00',
                        ],
                    ],
                ],
                'next_shift_start' => [
                    'EMP-1' => '2026-03-20 18:00:00',
                ],
            ],
            true,
        );

        $this->assertTrue($result['is_active']);
        $this->assertNull($result['reason']);
    }
}

class TestableLemburRequestService extends LemburRequestService
{
    public function __construct()
    {
        parent::__construct(
            Mockery::mock(FunctionalRole::class),
            Mockery::mock(AccessContext::class),
            Mockery::mock(WhatsappLogHelper::class),
            Mockery::mock(WhatsappMessageBuilder::class),
            Mockery::mock(HrisAttendanceSnapshotService::class),
            Mockery::mock(AttendanceDirtyResolverService::class),
        );
    }

    public function evaluateUserEligibilityPublic(
        string $requestedType,
        string $requestedStart,
        string $requestedEnd,
        string $requestedDate,
        ?string $statusHari,
        ?string $jamMasuk,
        ?string $jamKeluar,
        $idShift,
        string $lemburPernah,
        ?string $kodePerusahaan = null,
        ?string $kodeKaryawan = null,
        ?array $eligibilityCache = null,
        bool $isAdminContext = false,
    ): array {
        return $this->evaluateUserEligibility(
            $requestedType,
            $requestedStart,
            $requestedEnd,
            $requestedDate,
            $statusHari,
            $jamMasuk,
            $jamKeluar,
            $idShift,
            $lemburPernah,
            $kodePerusahaan,
            $kodeKaryawan,
            $eligibilityCache,
            $isAdminContext,
        );
    }

    protected function currentNow(): Carbon
    {
        return Carbon::parse('2026-03-19 18:00:00');
    }
}
