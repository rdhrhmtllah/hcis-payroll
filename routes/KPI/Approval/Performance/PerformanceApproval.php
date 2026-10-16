<?php

use App\Http\Controllers\KPI\Approval\Performance\PerformanceApprovalController;
use Illuminate\Support\Facades\Route;

// Performance Approval
Route::middleware(['auth', 'check.jabatan:ApprovalUpdatePerformance'])->group(function () {
    Route::get('performance-approval', [PerformanceApprovalController::class, 'index']);
    Route::prefix('/api/v1/performance-approval/approvals')->group(function () {
        Route::get('/', [PerformanceApprovalController::class, 'getApprovals']);
        Route::get('/filter-options/{type}', [PerformanceApprovalController::class, 'getFilterOptions']);
        Route::post('/{id}/approve', [PerformanceApprovalController::class, 'approvePerformance']);
        Route::get('/detail/{id}/{periode}', [PerformanceApprovalController::class, 'getApprovalDetail']);
        Route::post('/{id}/reject', [PerformanceApprovalController::class, 'rejectPerformance']);
        Route::post('/{id}/edit-approve', [PerformanceApprovalController::class, 'editAndApprovePerformance']);
        Route::post('/{id}/reject-with-detail', [PerformanceApprovalController::class, 'rejectPerformance']);
        Route::post('/getAttachmentUrl', [PerformanceApprovalController::class, 'getAttachmentUrl']);
    });
});
