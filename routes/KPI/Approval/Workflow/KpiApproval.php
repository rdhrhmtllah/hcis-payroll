<?php

use App\Http\Controllers\KPI\Approval\Workflow\KpiApprovalController;
use Illuminate\Support\Facades\Route;

// KPI Approval
Route::middleware(['auth', 'check.jabatan:ApprovalParameterKPI'])->group(function () {
    Route::get('/kpi-approval', [KpiApprovalController::class, 'index']);
    Route::prefix('/api/v1/kpi-approval/approvals')->group(function () {
        Route::get('/', [KpiApprovalController::class, 'getApprovals']);
        Route::get('/stats', [KpiApprovalController::class, 'getStats']);
        Route::get('/{id}/getApprovalDetail', [KpiApprovalController::class, 'getApprovalDetail']);
        Route::post('/{id}/approve', [KpiApprovalController::class, 'approve']);
        Route::post('/{id}/reject-with-detail', [KpiApprovalController::class, 'rejectWithDetail']);
        Route::get('/filter-options/{type}', [KpiApprovalController::class, 'getFilterOptions']);
    });
});
