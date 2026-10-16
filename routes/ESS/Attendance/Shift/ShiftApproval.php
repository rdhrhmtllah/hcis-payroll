<?php

use App\Http\Controllers\ESS\Attendance\Shift\Approval\ShiftApprovalController;
use Illuminate\Support\Facades\Route;

// Shift Approval
Route::middleware(['auth', 'check.jabatan:shiftApproval'])->group(function () {
    Route::get('/shift-approval/jabatan', [ShiftApprovalController::class, 'getJabatan'])->name(
        'shift-approval.jabatan',
    );
    Route::get('/shift-approval', [ShiftApprovalController::class, 'index'])->name('shift-approval.index');
    Route::get('/shift-approval/dashboard-stats', [ShiftApprovalController::class, 'getDashboardStats'])->name(
        'shift-approval.stats',
    );
    Route::get('/shift-approval/inbox', [ShiftApprovalController::class, 'getInbox'])->name('shift-approval.inbox');
    Route::get('/shift-approval/history', [ShiftApprovalController::class, 'getHistory'])->name(
        'shift-approval.history',
    );
    Route::get('/shift-approval/approval-status', [ShiftApprovalController::class, 'getApprovalStatus'])->name(
        'shift-approval.approval-status',
    );
    Route::get('/shift-approval/approval-flow', [ShiftApprovalController::class, 'getApprovalFlow'])->name(
        'shift-approval.approval-flow',
    );
    Route::get('/shift-approval/download-lampiran', [ShiftApprovalController::class, 'downloadLampiran'])->name(
        'shift-approval.download-lampiran',
    );
    Route::post('/shift-approval/process', [ShiftApprovalController::class, 'approve'])->name('shift-approval.process');
});
