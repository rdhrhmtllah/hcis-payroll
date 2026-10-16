<?php

use App\Http\Controllers\KPI\Approval\Verification\KpiVerifikasiController;
use Illuminate\Support\Facades\Route;

// KPI Verifikasi (step ROLE_KPI_VERIF) — parameter & performance dalam satu page
Route::middleware(['auth'])->group(function () {
    Route::get('/kpi-verifikasi', [KpiVerifikasiController::class, 'index']);
    Route::prefix('/api/v1/kpi-verifikasi/approvals')->group(function () {
        Route::get('/', [KpiVerifikasiController::class, 'getApprovals']);
        Route::get('/filter-options/{type}', [KpiVerifikasiController::class, 'getFilterOptions']);
        Route::get('/{noTransaksi}/getApprovalDetail', [KpiVerifikasiController::class, 'getApprovalDetail']);
        Route::post('/{id}/approve', [KpiVerifikasiController::class, 'approve']);
        Route::post('/{id}/edit-approve', [KpiVerifikasiController::class, 'editAndApprovePerformance']);
        Route::post('/{id}/reject', [KpiVerifikasiController::class, 'reject']);
        Route::post('/{id}/reject-with-detail', [KpiVerifikasiController::class, 'reject']);
    });
    Route::prefix('/api/v1/kpi-verifikasi/parameter/approvals')->group(function () {
        Route::get('/', [KpiVerifikasiController::class, 'getParameterApprovals']);
        Route::get('/filter-options/{type}', [KpiVerifikasiController::class, 'getFilterOptions']);
        Route::get('/{noTransaksi}/getApprovalDetail', [KpiVerifikasiController::class, 'getApprovalDetail']);
        Route::post('/{id}/approve', [KpiVerifikasiController::class, 'approve']);
        Route::post('/{id}/reject', [KpiVerifikasiController::class, 'reject']);
        Route::post('/{id}/reject-with-detail', [KpiVerifikasiController::class, 'reject']);
    });
    Route::prefix('/api/v1/kpi-verifikasi/performance/approvals')->group(function () {
        Route::get('/', [KpiVerifikasiController::class, 'getPerformanceApprovals']);
        Route::get('/filter-options/{type}', [KpiVerifikasiController::class, 'getFilterOptions']);
        Route::get('/detail/{id}/{periode}', [KpiVerifikasiController::class, 'getPerformanceDetail']);
        Route::post('/{id}/approve', [KpiVerifikasiController::class, 'approve']);
        Route::post('/{id}/edit-approve', [KpiVerifikasiController::class, 'editAndApprovePerformance']);
        Route::post('/{id}/reject', [KpiVerifikasiController::class, 'reject']);
        Route::post('/{id}/reject-with-detail', [KpiVerifikasiController::class, 'reject']);
        Route::post('/getAttachmentUrl', [KpiVerifikasiController::class, 'getAttachmentUrl']);
    });
});
