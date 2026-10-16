<?php

use App\Http\Controllers\KPI\Approval\Performance\PerformanceApprovalController;
use App\Http\Controllers\KPI\Approval\Workflow\ApprovalKPIController;
use Illuminate\Support\Facades\Route;

// // KPI Approval
// Route::prefix('/api/v1/performance-approval')->group(function () {
//     Route::prefix('approvals')->group(function () {
//         Route::get('/', [PerformanceApprovalController::class, 'getApprovals']);
//     });
// });

// Route::get('/team-kpi/dashboard', [\App\Http\Controllers\KPI\Performance\Parameter\TeamKpiController::class, 'dashboard'])->name('team-kpi.dashboard');
// Route::get('/team-kpi/management', [\App\Http\Controllers\KPI\Performance\Parameter\TeamKpiController::class, 'management'])->name('team-kpi.management');
// Route::get('/team-kpi/multi-form', [\App\Http\Controllers\KPI\Performance\Parameter\TeamKpiController::class, 'multiForm'])->name('team-kpi.multi-form');
// Route::get('/team-kpi/approval', [\App\Http\Controllers\KPI\Performance\Parameter\TeamKpiController::class, 'approval'])->name('team-kpi.approval');

// Approval KPI
Route::middleware(['auth', 'check.jabatan:adminHCApprovalKPIPage'])->group(function () {
    Route::get('/approval-kpi-admin', [ApprovalKPIController::class, 'index']);
    Route::prefix('/api/v1/approval-kpi-admin')->group(function () {
        Route::get('/search-employees', [ApprovalKPIController::class, 'searchEmployees']);
        Route::get('/flow/{hashedEmployeeId}', [ApprovalKPIController::class, 'getApprovalFlow']);
        Route::post('/save', [ApprovalKPIController::class, 'saveApprovalFlow']);
    });
});
