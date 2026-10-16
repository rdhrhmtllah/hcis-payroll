<?php

namespace Tests\Feature\ESS\Dashboard;

use App\Models\ESS\Employee\Models\Karyawan;
use App\Models\ESS\Employee\Models\User;
use App\Services\ESS\Attendance\Leave\Admin\cutiBersamaService;
use App\Services\ESS\Attendance\Processing\HrisAttendanceSnapshotService;
use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use App\Services\ESS\Dashboard\Homepage\HomepageHcisService;
use App\Services\ESS\Dashboard\Homepage\HomepageKpiService;
use App\Services\ESS\Dashboard\Homepage\HomepageLeaderDashboardService;
use App\Services\ESS\Dashboard\Homepage\HomepageLmsService;
use App\Services\ESS\Dashboard\Homepage\HomepagePersonalDashboardService;
use App\Services\ESS\Dashboard\Homepage\HomepageRoleService;
use App\Services\ESS\Dashboard\Homepage\HomepageVisitorService;
use App\Services\ESS\Dashboard\Personal\UDashReadModelService;
use App\Services\SYSTEM\Application\Shell\InertiaShellService;
use App\Services\SYSTEM\Notification\NotificationRouteRegistry;
use App\Services\SYSTEM\Notification\NotificationService;
use App\Services\SYSTEM\Notification\NotificationSyncStateService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class HomepageControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        Schema::create('HRIS_Karyawan_Team', function (Blueprint $table) {
            $table->string('Kode_Karyawan_Team')->nullable();
            $table->string('Kode_Karyawan')->nullable();
        });

        $this->withoutMiddleware();
        $this->bindDefaultMocks();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('HRIS_Karyawan_Team');
        Mockery::close();

        parent::tearDown();
    }

    public function test_homepage_index_renders_dashboard_payload_from_personal_service(): void
    {
        $roleService = Mockery::mock(HomepageRoleService::class);
        $roleService
            ->shouldReceive('getRoleDetails')
            ->once()
            ->andReturn([
                'is_leader' => true,
                'role' => 'team_leader',
                'team_count' => 4,
            ]);
        $this->instance(HomepageRoleService::class, $roleService);

        $notificationService = Mockery::mock(NotificationService::class);
        $notificationService->shouldReceive('listUnread')->once()->with('EMP001')->andReturn(collect());
        $this->instance(NotificationService::class, $notificationService);

        $approvalService = Mockery::mock(UDashReadModelService::class);
        $approvalService->shouldReceive('countPendingApprovalsV2')->once()->with('EMP001')->andReturn(2);
        $this->instance(UDashReadModelService::class, $approvalService);

        $personalService = Mockery::mock(HomepagePersonalDashboardService::class);
        $personalService->shouldNotReceive('buildInitialData');
        $this->instance(HomepagePersonalDashboardService::class, $personalService);

        $response = $this->actingAs($this->makeUser())->get('/homepage');

        $response->assertOk();
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('ESS/Dashboard/Homepage/homepage')
                ->where('role', 'team_leader')
                ->where('greeting.is_leader', true)
                ->where('user.name', 'Test User')
                ->where('initialDashboard', null),
        );
    }

    public function test_homepage_api_returns_dashboard_payload(): void
    {
        $roleService = Mockery::mock(HomepageRoleService::class);
        $roleService
            ->shouldReceive('getRoleDetails')
            ->once()
            ->andReturn([
                'is_leader' => true,
                'role' => 'team_leader',
                'team_count' => 4,
            ]);
        $this->instance(HomepageRoleService::class, $roleService);

        $approvalService = Mockery::mock(UDashReadModelService::class);
        $approvalService->shouldReceive('countPendingApprovalsV2')->once()->andReturn(3);
        $this->instance(UDashReadModelService::class, $approvalService);

        $personalService = Mockery::mock(HomepagePersonalDashboardService::class);
        $personalService
            ->shouldReceive('buildInitialData')
            ->once()
            ->andReturn([
                'cutoffRange' => ['start' => '2026-04-16', 'end' => '2026-05-15', 'label' => 'Cutoff April'],
                'attendanceRecords' => [['date' => '2026-04-29', 'status' => 'present']],
                'upcomingSchedule' => [['date' => '29 Apr', 'label' => 'Shift Pagi']],
                'kpi' => ['score' => 88, 'parameter_submitted' => true, 'performance_submitted' => true],
                'lms' => ['enrolled_courses' => 2, 'completed_courses' => 1],
                'overtime' => ['total_hours' => 4],
                'leave' => ['quota' => 12, 'remaining' => 8, 'used' => 4],
                'submissions' => [['id' => 'sub-1', 'title' => 'IZIN']],
                'pendingApprovals' => 3,
            ]);
        $this->instance(HomepagePersonalDashboardService::class, $personalService);

        $response = $this->actingAs($this->makeUser())->getJson('/api/homepage');

        $response
            ->assertOk()
            ->assertJsonPath('data.cutoffRange.start', '2026-04-16')
            ->assertJsonPath('data.attendanceRecords.0.date', '2026-04-29')
            ->assertJsonPath('data.kpi.score', 88)
            ->assertJsonPath('data.pendingApprovals', 3);
    }

    public function test_homepage_personal_attendance_summary_returns_domain_payload(): void
    {
        $roleService = Mockery::mock(HomepageRoleService::class);
        $roleService
            ->shouldReceive('getRoleDetails')
            ->once()
            ->with('EMP001')
            ->andReturn([
                'is_leader' => true,
                'role' => 'team_leader',
                'team_count' => 4,
            ]);
        $this->instance(HomepageRoleService::class, $roleService);

        $approvalService = Mockery::mock(UDashReadModelService::class);
        $approvalService->shouldReceive('countPendingApprovalsV2')->once()->with('EMP001')->andReturn(5);
        $this->instance(UDashReadModelService::class, $approvalService);

        $personalService = Mockery::mock(HomepagePersonalDashboardService::class);
        $personalService
            ->shouldReceive('getAttendanceSummary')
            ->once()
            ->with('EMP001', 'ABS001', null)
            ->andReturn([
                'cutoffRange' => ['start' => '2026-04-16', 'end' => '2026-05-15', 'label' => 'Cutoff April'],
                'attendanceRecords' => [['date' => '2026-04-29', 'status' => 'present']],
                'overtime' => ['total_hours' => 7.5],
            ]);
        $this->instance(HomepagePersonalDashboardService::class, $personalService);

        $response = $this->actingAs($this->makeUser())->getJson('/homepage/personal/attendance-summary');

        $response
            ->assertOk()
            ->assertJsonPath('data.cutoffRange.start', '2026-04-16')
            ->assertJsonPath('data.attendanceRecords.0.date', '2026-04-29')
            ->assertJsonPath('data.overtime.total_hours', 7.5)
            ->assertJsonPath('data.pendingApprovals', 5);
    }

    public function test_homepage_personal_kpi_summary_returns_domain_payload(): void
    {
        $personalService = Mockery::mock(HomepagePersonalDashboardService::class);
        $personalService
            ->shouldReceive('getKpiSummary')
            ->once()
            ->with('EMP001', null)
            ->andReturn([
                'score' => 90,
                'grade_label' => 'A',
            ]);
        $this->instance(HomepagePersonalDashboardService::class, $personalService);

        $response = $this->actingAs($this->makeUser())->getJson('/homepage/personal/kpi-summary');

        $response->assertOk()->assertJsonPath('data.score', 90)->assertJsonPath('data.grade_label', 'A');
    }

    public function test_leader_dashboard_renders_real_payload(): void
    {
        $roleService = Mockery::mock(HomepageRoleService::class);
        $roleService
            ->shouldReceive('getRoleDetails')
            ->once()
            ->andReturn([
                'is_leader' => true,
                'role' => 'team_leader',
                'team_count' => 2,
            ]);
        $this->instance(HomepageRoleService::class, $roleService);

        $leaderService = Mockery::mock(HomepageLeaderDashboardService::class);
        $leaderService
            ->shouldReceive('buildOverviewData')
            ->once()
            ->andReturn([
                'period' => ['label' => 'Cutoff April', 'kpiMonth' => 'April 2026'],
                'todayLabel' => '29 Apr 2026',
                'teamCount' => 4,
                'approvalQueue' => 2,
                'summary' => [
                    'totalMembers' => 4,
                    'approvalQueue' => 2,
                    'presentToday' => 3,
                ],
                'heroCards' => [['key' => 'members', 'section' => 'members', 'label' => 'Total Anggota', 'value' => 4]],
                'sectionCards' => [['key' => 'members', 'title' => 'Daftar Anggota Tim']],
                'useDemoData' => false,
            ]);
        $this->instance(HomepageLeaderDashboardService::class, $leaderService);

        $response = $this->actingAs($this->makeUser())->get('/homepage/leader-dashboard');

        $response->assertOk();
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('ESS/Dashboard/Homepage/LeaderDashboard')
                ->where('todayLabel', '29 Apr 2026')
                ->where('useDemoData', false)
                ->where('overview.teamCount', 4)
                ->where('overview.summary.totalMembers', 4)
                ->where('overview.approvalQueue', 2)
                ->where('overview.heroCards.0.key', 'members'),
        );
    }

    public function test_attendance_day_detail_returns_json_from_service(): void
    {
        $personalService = Mockery::mock(HomepagePersonalDashboardService::class);
        $personalService
            ->shouldReceive('getAttendanceDayDetail')
            ->once()
            ->andReturn([
                'date' => '2026-04-29',
                'note' => 'Check out belum tercatat',
                'flags' => [['key' => 'no-checkout']],
                'timeline' => [['label' => 'Check In', 'time' => '07:02']],
            ]);
        $this->instance(HomepagePersonalDashboardService::class, $personalService);

        $response = $this->actingAs($this->makeUser())->getJson('/homepage/attendance/day-detail?date=2026-04-29');

        $response
            ->assertOk()
            ->assertJsonPath('data.date', '2026-04-29')
            ->assertJsonPath('data.note', 'Check out belum tercatat')
            ->assertJsonPath('data.flags.0.key', 'no-checkout');
    }

    public function test_homepage_personal_hcis_summary_returns_personal_payload(): void
    {
        $personalService = Mockery::mock(HomepagePersonalDashboardService::class);
        $personalService
            ->shouldReceive('getHcisSummary')
            ->once()
            ->with('EMP001', 'ABS001')
            ->andReturn([
                'stats' => [
                    'attendanceIssues' => 2,
                    'hcisCritical' => 1,
                    'lateCount' => 1,
                ],
                'today' => [
                    'statusLabel' => 'Hadir',
                    'checkIn' => '07:01',
                    'checkOut' => '17:05',
                ],
                'trend' => [['label' => 'Rab', 'value' => 1]],
            ]);
        $this->instance(HomepagePersonalDashboardService::class, $personalService);

        $response = $this->actingAs($this->makeUser())->getJson('/homepage/personal/hcis-summary');

        $response
            ->assertOk()
            ->assertJsonPath('data.stats.attendanceIssues', 2)
            ->assertJsonPath('data.today.statusLabel', 'Hadir')
            ->assertJsonPath('data.trend.0.label', 'Rab')
            ->assertJsonPath('meta.source_scope', 'personal');
    }

    public function test_homepage_personal_needs_action_returns_personal_items(): void
    {
        $personalService = Mockery::mock(HomepagePersonalDashboardService::class);
        $personalService
            ->shouldReceive('getNeedsAction')
            ->once()
            ->with('EMP001', 'ABS001', 2026, 1)
            ->andReturn([
                'data' => [
                    [
                        'id' => 'hcis-2026-04-29',
                        'category' => 'hcis',
                        'module' => 'ESS',
                        'title' => 'Check-out belum tercatat',
                    ],
                ],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 5,
                    'total' => 1,
                    'has_prev' => false,
                    'has_next' => false,
                ],
                'summary' => [
                    'total_active_items' => 1,
                    'unread_active_items' => 1,
                ],
            ]);
        $this->instance(HomepagePersonalDashboardService::class, $personalService);

        $response = $this->actingAs($this->makeUser())->getJson('/homepage/personal/needs-action?kpi_year=2026');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', 'hcis-2026-04-29')
            ->assertJsonPath('data.0.module', 'ESS')
            ->assertJsonPath('summary.total_active_items', 1);
    }

    public function test_homepage_personal_calendar_day_detail_returns_raw_checktime(): void
    {
        $personalService = Mockery::mock(HomepagePersonalDashboardService::class);
        $personalService
            ->shouldReceive('getCalendarDayDetail')
            ->once()
            ->with('EMP001', 'ABS001', '2026-04-29')
            ->andReturn([
                'date' => '2026-04-29',
                'note' => 'Checktime lengkap',
                'raw_logs' => [
                    [
                        'id' => 'raw-0',
                        'time' => '07:01:00',
                        'is_selected_checkin' => true,
                        'is_selected_checkout' => false,
                    ],
                ],
                'selected_checkin' => '07:01',
                'selected_checkout' => '17:05',
            ]);
        $this->instance(HomepagePersonalDashboardService::class, $personalService);

        $response = $this->actingAs($this->makeUser())->getJson(
            '/homepage/personal/calendar/day-detail?date=2026-04-29',
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.date', '2026-04-29')
            ->assertJsonPath('data.raw_logs.0.time', '07:01:00')
            ->assertJsonPath('data.raw_logs.0.is_selected_checkin', true)
            ->assertJsonPath('data.selected_checkout', '17:05');
    }

    public function test_homepage_personal_kpi_summary_accepts_year(): void
    {
        $personalService = Mockery::mock(HomepagePersonalDashboardService::class);
        $personalService
            ->shouldReceive('getKpiSummary')
            ->once()
            ->with('EMP001', 2026)
            ->andReturn([
                'score' => 90,
                'periodLabel' => '2026',
            ]);
        $this->instance(HomepagePersonalDashboardService::class, $personalService);

        $response = $this->actingAs($this->makeUser())->getJson('/homepage/personal/kpi-summary?year=2026');

        $response->assertOk()->assertJsonPath('data.score', 90)->assertJsonPath('data.periodLabel', '2026');
    }

    public function test_personal_attendance_state_respects_working_time_boundaries(): void
    {
        try {
            Carbon::setTestNow(Carbon::parse('2026-05-13 06:30:00', 'Asia/Jakarta'));
            $beforeShift = $this->resolvePersonalAttendanceState(
                [
                    'status_hari' => 'WORK',
                    'shift_name' => 'SHIFT PAGI',
                    'jam_masuk' => '07:00',
                    'jam_keluar' => '17:00',
                    'check_in' => null,
                    'check_out' => null,
                    'late_minutes' => 0,
                ],
                '2026-05-13',
            );

            $this->assertTrue($beforeShift['waiting_shift']);
            $this->assertFalse($beforeShift['alpha']);
            $this->assertFalse($beforeShift['no_checkin']);

            Carbon::setTestNow(Carbon::parse('2026-05-13 12:00:00', 'Asia/Jakarta'));
            $duringShift = $this->resolvePersonalAttendanceState(
                [
                    'status_hari' => 'WORK',
                    'shift_name' => 'SHIFT PAGI',
                    'jam_masuk' => '07:00',
                    'jam_keluar' => '17:00',
                    'check_in' => '07:01',
                    'check_out' => null,
                    'late_minutes' => 0,
                ],
                '2026-05-13',
            );

            $this->assertTrue($duringShift['working']);
            $this->assertFalse($duringShift['no_checkout']);

            Carbon::setTestNow(Carbon::parse('2026-05-13 18:00:00', 'Asia/Jakarta'));
            $afterShift = $this->resolvePersonalAttendanceState(
                [
                    'status_hari' => 'WORK',
                    'shift_name' => 'SHIFT PAGI',
                    'jam_masuk' => '07:00',
                    'jam_keluar' => '17:00',
                    'check_in' => '07:01',
                    'check_out' => null,
                    'late_minutes' => 0,
                ],
                '2026-05-13',
            );

            $this->assertFalse($afterShift['working']);
            $this->assertTrue($afterShift['no_checkout']);

            Carbon::setTestNow(Carbon::parse('2026-05-13 10:00:00', 'Asia/Jakarta'));
            $futureDate = $this->resolvePersonalAttendanceState(
                [
                    'status_hari' => 'WORK',
                    'shift_name' => 'SHIFT PAGI',
                    'jam_masuk' => '07:00',
                    'jam_keluar' => '17:00',
                    'check_in' => null,
                    'check_out' => null,
                    'late_minutes' => 0,
                ],
                '2026-05-14',
            );

            $this->assertTrue($futureDate['future']);
            $this->assertFalse($futureDate['alpha']);
            $this->assertFalse($futureDate['no_checkin']);
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Kadaluarsa pengajuan dibaca dari BASIS DATA, bukan dihitung ulang di PHP.
     *
     * Uji ini semula menyetel services.hcis.batas_approve_izin lalu menuntut
     * layanan menghitung sendiri selisih hari dari Tanggal_Request. Cara itu
     * SENGAJA DITINGGALKAN: sumber kebenarannya kini kolom dari view approval
     * (Has_Expired_Pending_Approval / Approval_Expired_At), sebagaimana
     * dinyatakan pada docblock resolveSubmissionExpiryText. Dua perhitungan
     * untuk satu kenyataan berarti beranda dan halaman approval bisa berbeda
     * pendapat tentang pengajuan yang sama.
     *
     * Yang paling penting dijaga di sini bukan "kapan expired", melainkan
     * pengecualiannya: transaksi yang SUDAH FINAL tidak boleh ditandai expired
     * meski sempat ada langkah yang melewati batas — alurnya sudah selesai, dan
     * menandainya expired membuat karyawan mengira pengajuannya gagal.
     */
    public function test_kadaluarsa_pengajuan_mengikuti_penanda_dari_basis_data(): void
    {
        $pendingLewatBatas = (object) [
            'Dashboard_Status' => 'PENDING',
            'Jenis' => 'IZIN',
            'Has_Expired_Pending_Approval' => 1,
        ];
        $pendingMasihBerjalan = (object) [
            'Dashboard_Status' => 'PENDING',
            'Jenis' => 'CUTI',
            'Has_Expired_Pending_Approval' => 0,
        ];
        $ditandaiExpired = (object) [
            'Dashboard_Status' => 'EXPIRED',
            'Jenis' => 'IZIN',
        ];
        $sudahDisetujui = (object) [
            'Dashboard_Status' => 'APPROVED',
            'Jenis' => 'IZIN',
            'Has_Expired_Pending_Approval' => 1,
        ];
        $sudahDitolak = (object) [
            'Dashboard_Status' => 'REJECTED',
            'Jenis' => 'IZIN',
            'Has_Expired_Pending_Approval' => 1,
        ];

        $this->assertTrue($this->isPersonalSubmissionExpired($pendingLewatBatas));
        $this->assertFalse($this->isPersonalSubmissionExpired($pendingMasihBerjalan));
        $this->assertTrue($this->isPersonalSubmissionExpired($ditandaiExpired));

        $this->assertFalse($this->isPersonalSubmissionExpired($sudahDisetujui),
            'Pengajuan yang sudah DISETUJUI ditandai expired. Karyawan akan mengira '
            .'pengajuannya gagal padahal sudah disetujui.');
        $this->assertFalse($this->isPersonalSubmissionExpired($sudahDitolak),
            'Pengajuan yang sudah DITOLAK ditandai expired, menutupi alasan penolakannya.');
    }

    public function test_leader_member_detail_only_allows_team_member(): void
    {
        Schema::table('HRIS_Karyawan_Team', function (Blueprint $table) {
            // no-op to ensure connection is active
        });

        \DB::table('HRIS_Karyawan_Team')->insert([
            'Kode_Karyawan_Team' => 'EMP001',
            'Kode_Karyawan' => 'EMP002',
        ]);

        $leaderService = Mockery::mock(HomepageLeaderDashboardService::class);
        $leaderService
            ->shouldReceive('getMemberDetail')
            ->once()
            ->andReturn([
                'id' => 'EMP002',
                'name' => 'Anggota Satu',
            ]);
        $this->instance(HomepageLeaderDashboardService::class, $leaderService);

        $allowed = $this->actingAs($this->makeUser())->getJson('/homepage/leader-dashboard/members/EMP002/detail');

        $allowed->assertOk()->assertJsonPath('data.id', 'EMP002');

        $forbidden = $this->actingAs($this->makeUser())->getJson('/homepage/leader-dashboard/members/EMP999/detail');

        $forbidden->assertStatus(403);
    }

    public function test_leader_dashboard_paged_approvals_returns_paginated_payload(): void
    {
        $roleService = Mockery::mock(HomepageRoleService::class);
        $roleService
            ->shouldReceive('getRoleDetails')
            ->once()
            ->andReturn([
                'is_leader' => true,
                'role' => 'team_leader',
                'team_count' => 2,
            ]);
        $this->instance(HomepageRoleService::class, $roleService);

        $leaderService = Mockery::mock(HomepageLeaderDashboardService::class);
        $leaderService
            ->shouldReceive('getPaginatedApprovalItems')
            ->once()
            ->with('EMP001', 1, 6)
            ->andReturn([
                'data' => [
                    [
                        'id' => 'approval-1',
                        'memberName' => 'Anggota Satu',
                        'title' => 'CUTI',
                        'statusLabel' => 'Pending',
                    ],
                ],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 2,
                    'per_page' => 6,
                    'total' => 7,
                    'has_prev' => false,
                    'has_next' => true,
                ],
            ]);
        $this->instance(HomepageLeaderDashboardService::class, $leaderService);

        $response = $this->actingAs($this->makeUser())->getJson(
            '/homepage/leader-dashboard/approvals?page=1&per_page=6',
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', 'approval-1')
            ->assertJsonPath('pagination.current_page', 1)
            ->assertJsonPath('pagination.last_page', 2)
            ->assertJsonPath('pagination.has_next', true);
    }

    public function test_leader_dashboard_paged_members_accepts_filter_params(): void
    {
        $roleService = Mockery::mock(HomepageRoleService::class);
        $roleService
            ->shouldReceive('getRoleDetails')
            ->once()
            ->andReturn([
                'is_leader' => true,
                'role' => 'team_leader',
                'team_count' => 2,
            ]);
        $this->instance(HomepageRoleService::class, $roleService);

        $leaderService = Mockery::mock(HomepageLeaderDashboardService::class);
        $leaderService
            ->shouldReceive('getPaginatedTeamMembers')
            ->once()
            ->with('EMP001', 1, 10, [
                'search' => 'ani',
                'attendance' => 'all',
                'kpi' => 'kpi-alert',
                'lms' => 'pending',
                'kpi_year' => null,
            ])
            ->andReturn([
                'data' => [
                    [
                        'id' => 'EMP002',
                        'name' => 'Anita',
                        'approvalCount' => 2,
                        'attendance' => [
                            'todayStatus' => 'present',
                            'todayLabel' => 'Hadir',
                            'issueCount' => 1,
                        ],
                    ],
                ],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 10,
                    'total' => 1,
                    'has_prev' => false,
                    'has_next' => false,
                ],
            ]);
        $this->instance(HomepageLeaderDashboardService::class, $leaderService);

        $response = $this->actingAs($this->makeUser())->getJson(
            '/homepage/leader-dashboard/members?search=ani&kpi=kpi-alert&lms=pending',
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', 'EMP002')
            ->assertJsonPath('data.0.approvalCount', 2)
            ->assertJsonPath('data.0.attendance.issueCount', 1)
            ->assertJsonMissingPath('data.0.leave')
            ->assertJsonMissingPath('data.0.approvals')
            ->assertJsonPath('pagination.total', 1);
    }

    public function test_leader_dashboard_attendance_issues_endpoint_returns_paginated_payload(): void
    {
        $roleService = Mockery::mock(HomepageRoleService::class);
        $roleService
            ->shouldReceive('getRoleDetails')
            ->once()
            ->andReturn([
                'is_leader' => true,
                'role' => 'team_leader',
                'team_count' => 2,
            ]);
        $this->instance(HomepageRoleService::class, $roleService);

        $leaderService = Mockery::mock(HomepageLeaderDashboardService::class);
        $leaderService
            ->shouldReceive('getPaginatedAttendanceIssues')
            ->once()
            ->with('EMP001', '2026-05-04', 1, 10)
            ->andReturn([
                'data' => [
                    [
                        'id' => 'EMP002',
                        'name' => 'Anita',
                        'position' => 'Staff',
                        'shift' => [
                            'name' => 'EMI-LS1',
                            'time' => '07:00 - 19:00',
                        ],
                        'checkIn' => '07:18',
                        'checkOut' => null,
                        'issues' => [
                            [
                                'key' => 'late',
                                'label' => 'Terlambat',
                                'tone' => 'warning',
                                'meta' => '18 menit',
                            ],
                        ],
                    ],
                ],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 10,
                    'total' => 1,
                    'has_prev' => false,
                    'has_next' => false,
                ],
                'meta' => [
                    'date' => '2026-05-04',
                    'day_label' => 'Sen, 04 Mei 2026',
                    'issue_count' => 1,
                ],
            ]);
        $this->instance(HomepageLeaderDashboardService::class, $leaderService);

        $response = $this->actingAs($this->makeUser())->getJson(
            '/homepage/leader-dashboard/attendance-issues?date=2026-05-04&page=1&per_page=10',
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', 'EMP002')
            ->assertJsonPath('data.0.issues.0.key', 'late')
            ->assertJsonPath('pagination.current_page', 1)
            ->assertJsonPath('meta.date', '2026-05-04')
            ->assertJsonPath('meta.issue_count', 1);
    }

    public function test_leader_dashboard_attendance_issues_endpoint_rejects_non_leader(): void
    {
        $roleService = Mockery::mock(HomepageRoleService::class);
        $roleService
            ->shouldReceive('getRoleDetails')
            ->once()
            ->andReturn([
                'is_leader' => false,
                'role' => 'staff',
                'team_count' => 0,
            ]);
        $this->instance(HomepageRoleService::class, $roleService);

        $response = $this->actingAs($this->makeUser())->getJson(
            '/homepage/leader-dashboard/attendance-issues?date=2026-05-04',
        );

        $response->assertStatus(403);
    }

    public function test_all_leader_dashboard_data_endpoints_reject_non_leader(): void
    {
        $urls = [
            '/homepage/leader-dashboard/members/EMP002/detail',
            '/homepage/leader-dashboard/approvals',
            '/homepage/leader-dashboard/needs-action',
            '/homepage/leader-dashboard/summary',
            '/homepage/leader-dashboard/summary/hcis',
            '/homepage/leader-dashboard/summary/kpi',
            '/homepage/leader-dashboard/kpi-details',
            '/homepage/leader-dashboard/kpi-details/EMP002',
            '/homepage/leader-dashboard/summary/lms',
            '/homepage/leader-dashboard/lms-team-members',
            '/homepage/leader-dashboard/members',
            '/homepage/leader-dashboard/heatmap',
            '/homepage/leader-dashboard/attendance-issues',
            '/homepage/leader-dashboard/attendance-statuses',
            '/homepage/leader-dashboard/calendar/month',
            '/homepage/leader-dashboard/calendar/day-detail',
            '/homepage/leader-dashboard/training-pending',
            '/homepage/leader-dashboard/members/EMP002/weekly-calendar',
            '/homepage/leader-dashboard/members/EMP002/day-checktimes',
            '/homepage/leader-dashboard/shifts',
        ];

        $roleService = Mockery::mock(HomepageRoleService::class);
        $roleService
            ->shouldReceive('getRoleDetails')
            ->atLeast()
            ->once()
            ->andReturn([
                'is_leader' => false,
                'role' => 'staff',
                'team_count' => 0,
            ]);
        $this->instance(HomepageRoleService::class, $roleService);

        foreach ($urls as $url) {
            $this->actingAs($this->makeUser())->getJson($url)->assertStatus(403);
        }
    }

    public function test_leader_dashboard_attendance_issues_endpoint_validates_date_format(): void
    {
        $roleService = Mockery::mock(HomepageRoleService::class);
        $roleService
            ->shouldReceive('getRoleDetails')
            ->once()
            ->andReturn([
                'is_leader' => true,
                'role' => 'team_leader',
                'team_count' => 2,
            ]);
        $this->instance(HomepageRoleService::class, $roleService);

        $response = $this->actingAs($this->makeUser())->getJson(
            '/homepage/leader-dashboard/attendance-issues?date=04-05-2026',
        );

        $response->assertStatus(422);
    }

    public function test_leader_dashboard_attendance_statuses_endpoint_returns_paginated_payload(): void
    {
        $roleService = Mockery::mock(HomepageRoleService::class);
        $roleService
            ->shouldReceive('getRoleDetails')
            ->once()
            ->andReturn([
                'is_leader' => true,
                'role' => 'team_leader',
                'team_count' => 2,
            ]);
        $this->instance(HomepageRoleService::class, $roleService);

        $leaderService = Mockery::mock(HomepageLeaderDashboardService::class);
        $leaderService
            ->shouldReceive('getPaginatedAttendanceStatusRows')
            ->once()
            ->with('EMP001', 1, 10, [
                'filter' => 'no-checkout',
                'search' => '',
            ])
            ->andReturn([
                'data' => [
                    [
                        'id' => 'EMP002',
                        'name' => 'Anita',
                        'attendance' => [
                            'todayStatus' => 'no-checkout',
                            'todayLabel' => 'Belum Checkout',
                        ],
                    ],
                ],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 10,
                    'total' => 1,
                    'has_prev' => false,
                    'has_next' => false,
                ],
                'meta' => [
                    'date' => '2026-05-06',
                    'filter' => 'no-checkout',
                    'filters' => ['all' => 2, 'no-checkout' => 1],
                ],
            ]);
        $this->instance(HomepageLeaderDashboardService::class, $leaderService);

        $response = $this->actingAs($this->makeUser())->getJson(
            '/homepage/leader-dashboard/attendance-statuses?filter=no-checkout&page=1&per_page=10',
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', 'EMP002')
            ->assertJsonPath('data.0.attendance.todayStatus', 'no-checkout')
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('meta.filter', 'no-checkout');
    }

    public function test_leader_dashboard_attendance_statuses_endpoint_accepts_search_query(): void
    {
        $roleService = Mockery::mock(HomepageRoleService::class);
        $roleService
            ->shouldReceive('getRoleDetails')
            ->once()
            ->andReturn([
                'is_leader' => true,
                'role' => 'team_leader',
                'team_count' => 2,
            ]);
        $this->instance(HomepageRoleService::class, $roleService);

        $leaderService = Mockery::mock(HomepageLeaderDashboardService::class);
        $leaderService
            ->shouldReceive('getPaginatedAttendanceStatusRows')
            ->once()
            ->with('EMP001', 1, 10, [
                'filter' => 'all',
                'search' => 'anita',
            ])
            ->andReturn([
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 10,
                    'total' => 0,
                    'has_prev' => false,
                    'has_next' => false,
                ],
                'meta' => [
                    'date' => '2026-05-06',
                    'filter' => 'all',
                    'filters' => ['all' => 2],
                ],
            ]);
        $this->instance(HomepageLeaderDashboardService::class, $leaderService);

        $response = $this->actingAs($this->makeUser())->getJson(
            '/homepage/leader-dashboard/attendance-statuses?filter=all&search=anita&page=1&per_page=10',
        );

        $response->assertOk()->assertJsonPath('meta.filter', 'all');
    }

    public function test_leader_dashboard_attendance_statuses_endpoint_rejects_non_leader(): void
    {
        $roleService = Mockery::mock(HomepageRoleService::class);
        $roleService
            ->shouldReceive('getRoleDetails')
            ->once()
            ->andReturn([
                'is_leader' => false,
                'role' => 'staff',
                'team_count' => 0,
            ]);
        $this->instance(HomepageRoleService::class, $roleService);

        $response = $this->actingAs($this->makeUser())->getJson(
            '/homepage/leader-dashboard/attendance-statuses?filter=all',
        );

        $response->assertStatus(403);
    }

    public function test_leader_dashboard_attendance_statuses_endpoint_validates_filter(): void
    {
        $roleService = Mockery::mock(HomepageRoleService::class);
        $roleService
            ->shouldReceive('getRoleDetails')
            ->once()
            ->andReturn([
                'is_leader' => true,
                'role' => 'team_leader',
                'team_count' => 2,
            ]);
        $this->instance(HomepageRoleService::class, $roleService);

        $response = $this->actingAs($this->makeUser())->getJson(
            '/homepage/leader-dashboard/attendance-statuses?filter=not-valid',
        );

        $response->assertStatus(422);
    }

    public function test_leader_dashboard_summary_endpoint_returns_global_aggregate(): void
    {
        $roleService = Mockery::mock(HomepageRoleService::class);
        $roleService
            ->shouldReceive('getRoleDetails')
            ->once()
            ->andReturn([
                'is_leader' => true,
                'role' => 'team_leader',
                'team_count' => 66,
            ]);
        $this->instance(HomepageRoleService::class, $roleService);

        $leaderService = Mockery::mock(HomepageLeaderDashboardService::class);
        $leaderService
            ->shouldReceive('getGlobalSummary')
            ->once()
            ->with('EMP001')
            ->andReturn([
                'totalMembers' => 66,
                'presentToday' => 58,
                'absentToday' => 4,
                'leaveToday' => 4,
                'noCheckoutToday' => 3,
                'attendanceIssues' => 12,
                'hcisCritical' => 7,
                'approvalQueue' => 6,
            ]);
        $this->instance(HomepageLeaderDashboardService::class, $leaderService);

        $response = $this->actingAs($this->makeUser())->getJson('/homepage/leader-dashboard/summary');

        $response
            ->assertOk()
            ->assertJsonPath('data.summary.totalMembers', 66)
            ->assertJsonPath('data.summary.presentToday', 58)
            ->assertJsonPath('data.summary.attendanceIssues', 12)
            ->assertJsonPath('data.summary.approvalQueue', 6)
            ->assertJsonPath('meta.generated_at', fn ($value) => filled($value))
            ->assertJsonPath('meta.source_scope', 'full_team');
    }

    public function test_leader_dashboard_summary_hcis_endpoint_returns_full_team_payload(): void
    {
        $roleService = Mockery::mock(HomepageRoleService::class);
        $roleService
            ->shouldReceive('getRoleDetails')
            ->once()
            ->andReturn([
                'is_leader' => true,
                'role' => 'team_leader',
                'team_count' => 66,
            ]);
        $this->instance(HomepageRoleService::class, $roleService);

        $leaderService = Mockery::mock(HomepageLeaderDashboardService::class);
        $leaderService
            ->shouldReceive('getHcisSummary')
            ->once()
            ->with('EMP001')
            ->andReturn([
                'stats' => [
                    'attendanceIssues' => 16,
                    'hcisCritical' => 8,
                ],
                'trend' => [['label' => 'Sen', 'value' => 3]],
            ]);
        $this->instance(HomepageLeaderDashboardService::class, $leaderService);

        $response = $this->actingAs($this->makeUser())->getJson('/homepage/leader-dashboard/summary/hcis');

        $response
            ->assertOk()
            ->assertJsonPath('data.stats.attendanceIssues', 16)
            ->assertJsonPath('data.trend.0.label', 'Sen')
            ->assertJsonPath('meta.generated_at', fn ($value) => filled($value))
            ->assertJsonPath('meta.source_scope', 'full_team');
    }

    public function test_leader_dashboard_summary_kpi_endpoint_returns_full_team_payload(): void
    {
        $roleService = Mockery::mock(HomepageRoleService::class);
        $roleService
            ->shouldReceive('getRoleDetails')
            ->once()
            ->andReturn([
                'is_leader' => true,
                'role' => 'team_leader',
                'team_count' => 66,
            ]);
        $this->instance(HomepageRoleService::class, $roleService);

        $leaderService = Mockery::mock(HomepageLeaderDashboardService::class);
        $leaderService
            ->shouldReceive('getKpiSummary')
            ->once()
            ->with('EMP001', null)
            ->andReturn([
                'stats' => [
                    'kpiPending' => 9,
                    'kpiCompletion' => 86,
                ],
                'trend' => [['month' => 'Apr', 'value' => 88]],
            ]);
        $this->instance(HomepageLeaderDashboardService::class, $leaderService);

        $response = $this->actingAs($this->makeUser())->getJson('/homepage/leader-dashboard/summary/kpi');

        $response
            ->assertOk()
            ->assertJsonPath('data.stats.kpiPending', 9)
            ->assertJsonPath('data.trend.0.month', 'Apr')
            ->assertJsonPath('meta.generated_at', fn ($value) => filled($value))
            ->assertJsonPath('meta.source_scope', 'full_team');
    }

    public function test_leader_dashboard_summary_lms_endpoint_returns_full_team_payload(): void
    {
        $roleService = Mockery::mock(HomepageRoleService::class);
        $roleService
            ->shouldReceive('getRoleDetails')
            ->once()
            ->andReturn([
                'is_leader' => true,
                'role' => 'team_leader',
                'team_count' => 66,
            ]);
        $this->instance(HomepageRoleService::class, $roleService);

        $leaderService = Mockery::mock(HomepageLeaderDashboardService::class);
        $leaderService
            ->shouldReceive('getLmsSummary')
            ->once()
            ->with('EMP001', null)
            ->andReturn([
                'stats' => [
                    'avgLmsProgress' => 72,
                    'lmsAtRisk' => 11,
                ],
                'trend' => [['month' => 'Apr', 'value' => 65]],
            ]);
        $this->instance(HomepageLeaderDashboardService::class, $leaderService);

        $response = $this->actingAs($this->makeUser())->getJson('/homepage/leader-dashboard/summary/lms');

        $response
            ->assertOk()
            ->assertJsonPath('data.stats.avgLmsProgress', 72)
            ->assertJsonPath('data.trend.0.month', 'Apr')
            ->assertJsonPath('meta.generated_at', fn ($value) => filled($value))
            ->assertJsonPath('meta.source_scope', 'full_team');
    }

    public function test_leader_dashboard_shift_schedules_lazy_endpoint_returns_data(): void
    {
        $roleService = Mockery::mock(HomepageRoleService::class);
        $roleService
            ->shouldReceive('getRoleDetails')
            ->once()
            ->andReturn([
                'is_leader' => true,
                'role' => 'team_leader',
                'team_count' => 2,
            ]);
        $this->instance(HomepageRoleService::class, $roleService);

        $leaderService = Mockery::mock(HomepageLeaderDashboardService::class);
        $leaderService
            ->shouldReceive('getLeaderShiftSchedules')
            ->once()
            ->with('EMP001', '2026-05-04')
            ->andReturn([
                [
                    'date' => '2026-05-04',
                    'label' => '4 Mei 2026',
                    'name' => 'EMI-LS1',
                    'key' => '2026-05-04-EMI-LS1',
                    'time' => '07:00 - 19:00',
                    'memberCount' => 3,
                    'gapCount' => 1,
                    'coverageCount' => 2,
                    'minNeed' => 3,
                    'scheduledCount' => 3,
                    'availableCount' => 2,
                    'unavailableCount' => 1,
                    'availabilityRate' => 66.67,
                    'hasGap' => true,
                    'memberIds' => ['EMP002'],
                ],
            ]);
        $this->instance(HomepageLeaderDashboardService::class, $leaderService);

        $response = $this->actingAs($this->makeUser())->getJson('/homepage/leader-dashboard/shifts?date=2026-05-04');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.name', 'EMI-LS1')
            ->assertJsonPath('data.0.memberCount', 3)
            ->assertJsonPath('data.0.gapCount', 1)
            ->assertJsonPath('data.0.scheduledCount', 3)
            ->assertJsonPath('data.0.availableCount', 2)
            ->assertJsonPath('data.0.unavailableCount', 1)
            ->assertJsonPath('data.0.hasGap', true)
            ->assertJsonMissingPath('data.0.members')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.date', '2026-05-04')
            ->assertJsonPath('meta.source_scope', 'full_team');
    }

    public function test_leader_dashboard_shift_detail_endpoint_returns_paginated_members(): void
    {
        $roleService = Mockery::mock(HomepageRoleService::class);
        $roleService
            ->shouldReceive('getRoleDetails')
            ->once()
            ->andReturn([
                'is_leader' => true,
                'role' => 'team_leader',
                'team_count' => 2,
            ]);
        $this->instance(HomepageRoleService::class, $roleService);

        $leaderService = Mockery::mock(HomepageLeaderDashboardService::class);
        $leaderService
            ->shouldReceive('getLeaderShiftDetail')
            ->once()
            ->with('EMP001', '2026-05-04', 'EMI-LS1', 1, 8)
            ->andReturn([
                'shift' => [
                    'date' => '2026-05-04',
                    'name' => 'EMI-LS1',
                ],
                'data' => [
                    [
                        'id' => 'EMP002',
                        'name' => 'Anita',
                        'availability' => [
                            'is_available' => false,
                            'status' => 'sick',
                            'label' => 'Sakit',
                            'reason' => 'Sedang sakit',
                        ],
                    ],
                ],
                'pagination' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 8,
                    'total' => 1,
                    'has_prev' => false,
                    'has_next' => false,
                ],
            ]);
        $this->instance(HomepageLeaderDashboardService::class, $leaderService);

        $response = $this->actingAs($this->makeUser())->getJson(
            '/homepage/leader-dashboard/shifts?date=2026-05-04&shift_name=EMI-LS1',
        );

        $response
            ->assertOk()
            ->assertJsonPath('shift.name', 'EMI-LS1')
            ->assertJsonPath('data.0.id', 'EMP002')
            ->assertJsonPath('data.0.availability.status', 'sick')
            ->assertJsonPath('data.0.availability.reason', 'Sedang sakit')
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('meta.source_scope', 'full_team');
    }

    public function test_personal_hcis_cutoff_trend_point_contains_attendance_percentage_metadata(): void
    {
        $service = $this->makePersonalDashboardServiceForUnit();
        $method = new \ReflectionMethod(HomepagePersonalDashboardService::class, 'summarizeCutoffAttendanceTrendPoint');
        $method->setAccessible(true);

        $point = $method->invoke(
            $service,
            [
                'start_date' => Carbon::parse('2026-04-16'),
                'end_date' => Carbon::parse('2026-05-15'),
                'label' => 'Cutoff April',
            ],
            collect([
                ['status' => 'present', 'check_in' => '07:00', 'no_shift' => false],
                ['status' => 'terlambat', 'check_in' => '07:20', 'late_minutes' => 15, 'no_shift' => false],
                ['status' => 'izin', 'sakit' => true, 'no_shift' => false],
                ['status' => 'cuti', 'cuti' => true, 'no_shift' => false],
                ['status' => 'alpha', 'alpha' => true, 'no_shift' => false],
                ['status' => 'off', 'no_shift' => true],
                ['status' => 'future', 'future' => true, 'no_shift' => false],
                ['status' => 'waiting shift', 'waiting_shift' => true, 'no_shift' => false],
            ]),
            Carbon::parse('2026-05-10'),
        );

        $this->assertSame('2026-04', $point['period']);
        $this->assertSame('Cutoff April', $point['label']);
        $this->assertSame('2026-04-16', $point['cutoff_start']);
        $this->assertSame('2026-05-15', $point['cutoff_end']);
        $this->assertSame(80.0, $point['value']);
        $this->assertSame(80.0, $point['percentage']);
        $this->assertSame(2, $point['present_days']);
        $this->assertSame(4, $point['fulfilled_days']);
        $this->assertSame(5, $point['total_days']);
        $this->assertSame(1, $point['sick_days']);
        $this->assertSame(1, $point['leave_days']);
        $this->assertSame(15, $point['late_minutes']);
    }

    protected function bindDefaultMocks(): void
    {
        $this->instance(cutiBersamaService::class, Mockery::mock(cutiBersamaService::class));
        $this->instance(HomepageHcisService::class, Mockery::mock(HomepageHcisService::class));
        $this->instance(HomepageVisitorService::class, Mockery::mock(HomepageVisitorService::class));
        $this->instance(HomepageLmsService::class, Mockery::mock(HomepageLmsService::class));
        $this->instance(HomepageKpiService::class, Mockery::mock(HomepageKpiService::class));
        $this->instance(HomepageRoleService::class, Mockery::mock(HomepageRoleService::class));
        $this->instance(NotificationService::class, Mockery::mock(NotificationService::class));
        $this->instance(NotificationSyncStateService::class, Mockery::mock(NotificationSyncStateService::class));
        $this->instance(UDashReadModelService::class, Mockery::mock(UDashReadModelService::class));
        $this->instance(
            HomepagePersonalDashboardService::class,
            Mockery::mock(HomepagePersonalDashboardService::class),
        );
        $this->instance(HomepageLeaderDashboardService::class, Mockery::mock(HomepageLeaderDashboardService::class));
        $shellService = Mockery::mock(InertiaShellService::class);
        $shellService->shouldReceive('build')->andReturn([
            'brand' => [],
            'navigation' => ['home' => ['url' => '/homepage'], 'modules' => []],
            'shell' => ['activeModule' => 'HOME', 'activeModuleUrl' => '/homepage'],
            'notifications' => [],
            'messages' => [],
            'help' => [],
            'unreadNotificationsCount' => 0,
        ]);
        $this->instance(InertiaShellService::class, $shellService);
    }

    protected function resolvePersonalAttendanceState(array $row, string $date): array
    {
        $service = $this->makePersonalDashboardServiceForUnit();
        $method = new \ReflectionMethod(HomepagePersonalDashboardService::class, 'buildAttendanceRecordState');
        $method->setAccessible(true);

        return $method->invoke($service, $row, null, $date);
    }

    protected function isPersonalSubmissionExpired(object $item): bool
    {
        $service = $this->makePersonalDashboardServiceForUnit();
        $method = new \ReflectionMethod(HomepagePersonalDashboardService::class, 'isPersonalSubmissionExpiredRecord');
        $method->setAccessible(true);

        return $method->invoke($service, $item);
    }

    protected function makePersonalDashboardServiceForUnit(): HomepagePersonalDashboardService
    {
        return new HomepagePersonalDashboardService(
            Mockery::mock(UDashReadModelService::class),
            Mockery::mock(ShiftRequestService::class),
            Mockery::mock(HomepageKpiService::class),
            Mockery::mock(HomepageLmsService::class),
            Mockery::mock(NotificationService::class),
            Mockery::mock(NotificationSyncStateService::class),
            new NotificationRouteRegistry,
            Mockery::mock(HrisAttendanceSnapshotService::class),
        );
    }

    protected function makeUser(): User
    {
        $user = new User;
        $user->forceFill([
            'Id_Users' => 77,
            'Username' => 'tester',
        ]);
        $user->exists = true;

        $karyawan = new Karyawan;
        $karyawan->forceFill([
            'Kode_Karyawan' => 'EMP001',
            'Nama' => 'Test User',
            'UserID_Absen' => 'ABS001',
            'No_Induk_Karyawan' => 'NIK001',
        ]);
        $karyawan->exists = true;
        $karyawan->setRelation('level', (object) ['nama_jabatan' => 'Leader']);
        $karyawan->setRelation('division', (object) ['Nama_Divisi' => 'Produksi']);

        $user->setRelation('karyawan', $karyawan);

        return $user;
    }
}
