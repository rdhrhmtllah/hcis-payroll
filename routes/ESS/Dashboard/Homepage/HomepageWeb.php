<?php

use App\Http\Controllers\ESS\Dashboard\Homepage\HomepageController;
use Illuminate\Support\Facades\Route;

/*
 * `auth` dideklarasikan di sini, bukan diwariskan dari posisi `require` berkas
 * ini di routes/web.php. Seluruh 55 route homepage sebelumnya tidak memiliki
 * middleware sama sekali dan bergantung penuh pada tempat berkas ini
 * di-require. Ketergantungan itu pernah gagal: satu berkas route yang
 * ter-require dua kali membuat registrasi terakhir menang dan seluruh endpoint
 * di bawahnya berjalan tanpa autentikasi.
 */
Route::middleware(['auth'])->group(function () {
    // Calendar data route MUST be before resource route to avoid {id} parameter catch
    Route::get('homepage/calendar-data', [HomepageController::class, 'getCalendarData'])->name('homepage.calendar-data');
    Route::get('homepage/attendance/day-detail', [HomepageController::class, 'getAttendanceDayDetail'])->name(
        'homepage.attendance.day-detail',
    );
    // Endpoint agregat above-the-fold: attendance + composition + hcis + weekly
    // dalam 1 request (1 pass SP absen). Konsolidasi fan-out homepage.
    Route::get('homepage/personal/bootstrap', [HomepageController::class, 'getPersonalBootstrap'])->name(
        'homepage.personal.bootstrap',
    );
    Route::get('homepage/personal/attendance-summary', [HomepageController::class, 'getPersonalAttendanceSummary'])->name(
        'homepage.personal.attendance-summary',
    );
    Route::get('homepage/personal/attendance-composition', [
        HomepageController::class,
        'getPersonalAttendanceComposition',
    ])->name('homepage.personal.attendance-composition');
    Route::get('homepage/personal/overtime-detail', [
        HomepageController::class,
        'getPersonalOvertimeDetail',
    ])->name('homepage.personal.overtime-detail');
    Route::get('homepage/personal/weekly-calendar', [HomepageController::class, 'getPersonalWeeklyCalendar'])->name(
        'homepage.personal.weekly-calendar',
    );
    Route::get('homepage/personal/hcis-summary', [HomepageController::class, 'getPersonalHcisSummary'])->name(
        'homepage.personal.hcis-summary',
    );
    Route::get('homepage/personal/needs-action', [HomepageController::class, 'getPersonalNeedsAction'])->name(
        'homepage.personal.needs-action',
    );
    Route::get('homepage/personal/needs-action-badge', [HomepageController::class, 'getLeaderNeedsActionBadge'])->name(
        'homepage.personal.needs-action-badge',
    );
    Route::get('homepage/personal/kpi-summary', [HomepageController::class, 'getPersonalKpiSummary'])->name(
        'homepage.personal.kpi-summary',
    );
    Route::get('homepage/personal/kpi-detail', [HomepageController::class, 'getPersonalKpiDetail'])->name(
        'homepage.personal.kpi-detail',
    );
    Route::get('homepage/personal/lms-summary', [HomepageController::class, 'getPersonalLmsSummary'])->name(
        'homepage.personal.lms-summary',
    );
    Route::get('homepage/personal/leave-summary', [HomepageController::class, 'getPersonalLeaveSummary'])->name(
        'homepage.personal.leave-summary',
    );
    Route::get('homepage/personal/submissions-summary', [HomepageController::class, 'getPersonalSubmissionsSummary'])->name(
        'homepage.personal.submissions-summary',
    );
    Route::get('homepage/personal/upcoming-schedule', [HomepageController::class, 'getPersonalUpcomingSchedule'])->name(
        'homepage.personal.upcoming-schedule',
    );
    Route::get('homepage/personal/calendar/day-detail', [HomepageController::class, 'getPersonalCalendarDayDetail'])->name(
        'homepage.personal.calendar-day-detail',
    );

    // Notification routes
    Route::get('homepage/notifications/unread', [HomepageController::class, 'getUnreadNotifications'])->name(
        'homepage.notifications.unread',
    );
    Route::get('homepage/notifications/all', [HomepageController::class, 'getAllNotifications'])->name(
        'homepage.notifications.all',
    );
    Route::get('homepage/notifications/paginate', [HomepageController::class, 'paginateNotifications'])->name(
        'homepage.notifications.paginate',
    );
    Route::post('homepage/notifications/{id}/read', [HomepageController::class, 'markNotificationRead'])->name(
        'homepage.notifications.read',
    );
    Route::post('homepage/notifications/{id}/dismiss', [HomepageController::class, 'dismissNotification'])->name(
        'homepage.notifications.dismiss',
    );
    Route::post('homepage/notifications/read-all', [HomepageController::class, 'markAllNotificationsRead'])->name(
        'homepage.notifications.read-all',
    );

    // === Activity Center (global async-task panel di topbar) ===
    Route::get('api/v1/activities', [\App\Http\Controllers\ESS\Activity\Center\ActivityCenterController::class, 'index'])->name(
        'activities.index',
    );
    Route::get('api/v1/activities/summary', [\App\Http\Controllers\ESS\Activity\Center\ActivityCenterController::class, 'summary'])->name(
        'activities.summary',
    );
    Route::post('api/v1/activities/dismiss-all', [
        \App\Http\Controllers\ESS\Activity\Center\ActivityCenterController::class,
        'dismissAll',
    ])->name('activities.dismiss-all');
    Route::get('api/v1/activities/{type}/{id}/download', [
        \App\Http\Controllers\ESS\Activity\Center\ActivityCenterController::class,
        'download',
    ])->name('activities.download');
    Route::post('api/v1/activities/{type}/{id}/retry', [
        \App\Http\Controllers\ESS\Activity\Center\ActivityCenterController::class,
        'retry',
    ])->name('activities.retry');
    Route::post('api/v1/activities/{type}/{id}/dismiss', [
        \App\Http\Controllers\ESS\Activity\Center\ActivityCenterController::class,
        'dismiss',
    ])->name('activities.dismiss');
    Route::post('api/v1/activities/{type}/{id}/abort', [
        \App\Http\Controllers\ESS\Activity\Center\ActivityCenterController::class,
        'abort',
    ])->name('activities.abort');

    Route::get('homepage/leader-dashboard', [HomepageController::class, 'leaderDashboard'])->name(
        'homepage.leader-dashboard',
    );
    Route::get('homepage/leader-dashboard/members/{kodeKaryawan}/detail', [
        HomepageController::class,
        'getLeaderMemberDetail',
    ])->name('homepage.leader-dashboard.member-detail');
    Route::get('homepage/leader-dashboard/approvals', [HomepageController::class, 'getLeaderApprovalItems'])->name(
        'homepage.leader-dashboard.approvals',
    );
    Route::get('homepage/leader-dashboard/needs-action', [HomepageController::class, 'getLeaderNeedsAction'])->name(
        'homepage.leader-dashboard.needs-action',
    );
    Route::get('homepage/leader-dashboard/summary', [HomepageController::class, 'getLeaderSummary'])->name(
        'homepage.leader-dashboard.summary',
    );
    Route::get('homepage/leader-dashboard/summary/hcis', [HomepageController::class, 'getLeaderSummaryHcis'])->name(
        'homepage.leader-dashboard.summary.hcis',
    );
    Route::get('homepage/leader-dashboard/summary/kpi', [HomepageController::class, 'getLeaderSummaryKpi'])->name(
        'homepage.leader-dashboard.summary.kpi',
    );
    Route::get('homepage/leader-dashboard/kpi-details', [HomepageController::class, 'getLeaderKpiDetails'])->name(
        'homepage.leader-dashboard.kpi-details',
    );
    Route::get('homepage/leader-dashboard/kpi-details/{kodeKaryawan}', [
        HomepageController::class,
        'getLeaderKpiMemberDetail',
    ])->name('homepage.leader-dashboard.kpi-member-detail');
    Route::get('homepage/leader-dashboard/summary/lms', [HomepageController::class, 'getLeaderSummaryLms'])->name(
        'homepage.leader-dashboard.summary.lms',
    );
    Route::get('homepage/leader-dashboard/lms-team-members', [HomepageController::class, 'getLeaderLmsTeamMembers'])->name(
        'homepage.leader-dashboard.lms-team-members',
    );
    Route::get('homepage/leader-dashboard/members', [HomepageController::class, 'getLeaderMembers'])->name(
        'homepage.leader-dashboard.members',
    );
    Route::get('homepage/leader-dashboard/heatmap', [HomepageController::class, 'getLeaderHeatmap'])->name(
        'homepage.leader-dashboard.heatmap',
    );
    Route::get('homepage/leader-dashboard/attendance-issues', [
        HomepageController::class,
        'getLeaderAttendanceIssues',
    ])->name('homepage.leader-dashboard.attendance-issues');
    Route::get('homepage/leader-dashboard/attendance-statuses', [
        HomepageController::class,
        'getLeaderAttendanceStatuses',
    ])->name('homepage.leader-dashboard.attendance-statuses');
    Route::get('homepage/leader-dashboard/calendar/month', [
        HomepageController::class,
        'getLeaderCalendarMonthSummary',
    ])->name('homepage.leader-dashboard.calendar-month');
    Route::get('homepage/leader-dashboard/calendar/day-detail', [
        HomepageController::class,
        'getLeaderCalendarDayDetail',
    ])->name('homepage.leader-dashboard.calendar-day-detail');
    Route::get('homepage/leader-dashboard/hcis/period-breakdown', [
        HomepageController::class,
        'getLeaderHcisPeriodBreakdown',
    ])->name('homepage.leader-dashboard.hcis-period-breakdown');
    Route::get('homepage/leader-dashboard/hcis/today-breakdown', [
        HomepageController::class,
        'getLeaderHcisTodayBreakdown',
    ])->name('homepage.leader-dashboard.hcis-today-breakdown');
    Route::get('homepage/leader-dashboard/hcis/today-members', [
        HomepageController::class,
        'getLeaderHcisTodayMembers',
    ])->name('homepage.leader-dashboard.hcis-today-members');
    Route::get('homepage/leader-dashboard/hcis/member-category-dates', [
        HomepageController::class,
        'getLeaderHcisMemberCategoryDates',
    ])->name('homepage.leader-dashboard.hcis-member-category-dates');
    Route::get('homepage/leader-dashboard/training-pending', [HomepageController::class, 'getLeaderTrainingPending'])->name(
        'homepage.leader-dashboard.training-pending',
    );
    Route::get('homepage/leader-dashboard/members/{kodeKaryawan}/weekly-calendar', [
        HomepageController::class,
        'getLeaderMemberWeeklyCalendar',
    ])->name('homepage.leader-dashboard.member-weekly-calendar');
    Route::get('homepage/leader-dashboard/members/{kodeKaryawan}/day-checktimes', [
        HomepageController::class,
        'getLeaderMemberDayCheckTimes',
    ])->name('homepage.leader-dashboard.member-day-checktimes');
    Route::get('homepage/leader-dashboard/shifts', [HomepageController::class, 'getLeaderShiftSchedules'])->name(
        'homepage.leader-dashboard.shifts',
    );
    Route::resource('homepage', HomepageController::class);
});
