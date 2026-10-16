<?php

use App\Http\Controllers\ESS\Attendance\Shift\Admin\AdminShiftController;
use App\Http\Controllers\ESS\Attendance\Shift\Leadership\LeaderShiftController;
use App\Http\Controllers\ESS\Attendance\Shift\Rules\ShiftRuleAdminController;
use App\Http\Controllers\ESS\Attendance\Shift\Schedule\JejakJadwalHariController;
use Illuminate\Support\Facades\Route;

// Leader Shift Management (SPV to Member Shift Assignment)
// Route::middleware(['auth'
// // , 'check.jabatan:ShiftManagement, ShiftManagementAdmin'
// ])->prefix('leader-shift')->name('leader-shift.')->group(function () {
//     Route::get('/team', [LeaderShiftController::class, 'getTeam'])->name('team');
//     Route::get('/week-schedule', [LeaderShiftController::class, 'getWeekSchedule'])->name('week-schedule');
//     Route::get('/shifts', [LeaderShiftController::class, 'getShifts'])->name('shifts');
//     Route::get('/shift-for-day', [LeaderShiftController::class, 'shiftForDay'])->name('shift-for-day');
//     Route::post('/offday', [LeaderShiftController::class, 'submitOffday'])->name('offday');
//     Route::get('/history', [LeaderShiftController::class, 'getHistory'])->name('history');
//     Route::get('/detail/{noTransaksi}', [LeaderShiftController::class, 'getDetail'])->name('detail');
//     Route::post('/cancel', [LeaderShiftController::class, 'cancelHistory'])->name('cancel');
//     Route::post('/check-conflicts', [LeaderShiftController::class, 'checkConflicts'])->name('check-conflicts');
//     Route::post('/permanent-conflicts', [LeaderShiftController::class, 'checkPermanentConflicts'])->name('permanent-conflicts');
//     Route::post('/submit', [LeaderShiftController::class, 'submit'])->name('submit');

//     Route::middleware(['check.jabatan:ShiftManagement'])->group(function(){
//         Route::get('/', [LeaderShiftController::class, 'index'])->name('index');
//     });

//     Route::middleware(['check.jabatan:ShiftManagementAdmin'])->group(function(){
//         Route::get('/admin', [AdminShiftController::class, 'index'])->name('index-admin');
//     });
// });

Route::middleware(['auth', 'check.jabatan:ShiftManagement, ShiftManagementAdmin'])
    ->prefix('leader-shift')
    ->name('leader-shift.')
    ->group(function () {
        Route::get('/shifts', [LeaderShiftController::class, 'getShifts'])->name('shifts');
        Route::get('/shift-for-day', [LeaderShiftController::class, 'shiftForDay'])->name('shift-for-day');
        Route::get('/detail/{noTransaksi}', [LeaderShiftController::class, 'getDetail'])->name('detail');
        Route::post('/check-conflicts', [LeaderShiftController::class, 'checkConflicts'])->name('check-conflicts');
        Route::post('/permanent-conflicts', [LeaderShiftController::class, 'checkPermanentConflicts'])->name(
            'permanent-conflicts',
        );
        Route::post('/cancel', [LeaderShiftController::class, 'cancelHistory'])->name('cancel');

        /*
        |--------------------------------------------------------------------------
        | USER MODE (default behaviour)
        |--------------------------------------------------------------------------
        */
        Route::middleware(['check.jabatan:ShiftManagement', 'access.context:user'])->group(function () {
            Route::get('/', [LeaderShiftController::class, 'index'])->name('index');
            Route::post('/offday', [LeaderShiftController::class, 'submitOffday'])->name('offday');
            Route::get('/history', [LeaderShiftController::class, 'getHistory'])->name('history');
            Route::get('/stats', [LeaderShiftController::class, 'stats'])->name('stats');
            Route::get('/team', [LeaderShiftController::class, 'getTeam'])->name('team');
            Route::get('/week-schedule', [LeaderShiftController::class, 'getWeekSchedule'])->name('week-schedule');
            Route::get('/jejak-hari', [JejakJadwalHariController::class, 'tim'])->name('jejak-hari');
            Route::post('/submit', [LeaderShiftController::class, 'submit'])->name('submit');
            // Override status hari endpoints (quick modal)
            Route::get('/override-for-date', [LeaderShiftController::class, 'getOverrideForDate'])->name(
                'override-for-date',
            );
            Route::post('/stop-override', [LeaderShiftController::class, 'stopOverride'])->name('stop-override');
        });

        /*
            |--------------------------------------------------------------------------
            | ADMIN MODE (privileged behaviour)
            |--------------------------------------------------------------------------
            */
        Route::prefix('admin')
            ->name('admin.')
            ->middleware(['check.jabatan:ShiftManagementAdmin', 'access.context:admin'])
            ->group(function () {
                Route::get('/', [AdminShiftController::class, 'index'])->name('index');

                // Admin tetap boleh pakai method yang sama
                Route::post('/offday', [LeaderShiftController::class, 'submitOffday'])->name('offday');
                Route::get('/history', [LeaderShiftController::class, 'getHistory'])->name('history');
                Route::get('/stats', [LeaderShiftController::class, 'stats'])->name('stats');
                Route::get('/team', [LeaderShiftController::class, 'getTeam'])->name('team');
                Route::get('/week-schedule', [LeaderShiftController::class, 'getWeekSchedule'])->name('week-schedule');
                Route::get('/jejak-hari', [JejakJadwalHariController::class, 'tim'])->name('jejak-hari');
                Route::post('/submit', [LeaderShiftController::class, 'submit'])->name('submit');
                // Override status hari endpoints (quick modal)
                Route::get('/override-for-date', [LeaderShiftController::class, 'getOverrideForDate'])->name(
                    'override-for-date',
                );
                Route::post('/stop-override', [LeaderShiftController::class, 'stopOverride'])->name('stop-override');

                // Shift Rule Management (Admin only)
                Route::get('/shift-rule', [ShiftRuleAdminController::class, 'index'])->name('shift-rule.index');
                Route::get('/shift-rule/stats', [ShiftRuleAdminController::class, 'stats'])->name('shift-rule.stats');
                Route::get('/shift-rule/list', [ShiftRuleAdminController::class, 'list'])->name('shift-rule.list');
                Route::get('/shift-rule/groups', [ShiftRuleAdminController::class, 'groups'])->name(
                    'shift-rule.groups',
                );
                Route::get('/shift-rule/group-detail', [ShiftRuleAdminController::class, 'groupDetail'])->name(
                    'shift-rule.group-detail',
                );
                Route::post('/shift-rule', [ShiftRuleAdminController::class, 'store'])->name('shift-rule.store');
                Route::post('/shift-rule/deactivate-group-shift', [
                    ShiftRuleAdminController::class,
                    'deactivateGroupShift',
                ])->name('shift-rule.deactivate-group-shift');
                Route::post('/shift-rule/deactivate-source', [
                    ShiftRuleAdminController::class,
                    'deactivateRuleSource',
                ])->name('shift-rule.deactivate-source');
            });
    });
