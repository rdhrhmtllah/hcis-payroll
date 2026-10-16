<?php

use App\Http\Controllers\KPI\Monitoring\Enforcement\KpiEnforcementController;
use Illuminate\Support\Facades\Route;

// KPI Enforcement & Monitoring Dashboard
Route::middleware(['auth', 'check.jabatan:kpiEnforcementMonitoring'])->group(function () {
    Route::get('/kpi-enforcement', [KpiEnforcementController::class, 'index']);
    Route::prefix('/api/v1/kpi-enforcement')->group(function () {
        // Daftar penjadwalan per jenis (submission/penilaian) untuk dropdown enforcement.
        Route::get('/schedules', [KpiEnforcementController::class, 'getSchedules']);
        // Penjadwalan dalam scope (tahun/periode) untuk filter dalam monitoring.
        Route::get('/schedules-in-scope', [KpiEnforcementController::class, 'getSchedulesInScope']);
        // Monitoring (TAB 1)
        Route::get('/submission-status', [KpiEnforcementController::class, 'getSubmissionStatus']);
        Route::get('/performance-tracking', [KpiEnforcementController::class, 'getPerformanceTracking']);
        // Opsi filter isi monitoring (status di-derive di FE; divisi/dll dari sini)
        Route::get('/monitor-filter-options', [KpiEnforcementController::class, 'getMonitorFilterOptions']);
        // Detail modal (parameter/performance) + alur approval
        Route::get('/submission-detail', [KpiEnforcementController::class, 'getSubmissionDetail']);
        Route::get('/performance-detail', [KpiEnforcementController::class, 'getPerformanceDetail']);
        // Rincian perhitungan baris YTD (agregat, bukan satu transaksi).
        Route::get('/performance-detail-ytd', [KpiEnforcementController::class, 'getPerformanceDetailYtd']);
        Route::get('/approval-flow', [KpiEnforcementController::class, 'getApprovalFlow']);
        // Pembatalan transaksi (single & bulk, dengan cascade parameter -> performance)
        Route::post('/cancel/preview', [KpiEnforcementController::class, 'previewCancellation']);
        Route::post('/cancel/execute', [KpiEnforcementController::class, 'executeCancellation']);
        Route::get('/cancel/history', [KpiEnforcementController::class, 'getCancellationHistory']);
        // Enforcement (TAB 2)
        Route::get('/filter-options', [KpiEnforcementController::class, 'getFilterOptions']);
        Route::get('/unfilled', [KpiEnforcementController::class, 'getUnfilledEmployees']);
        Route::post('/preview', [KpiEnforcementController::class, 'previewEnforcement']);
        Route::post('/preview-detail', [KpiEnforcementController::class, 'previewEmployeeDetail']);
        Route::post('/enforce', [KpiEnforcementController::class, 'enforceCap']);
        // Polling progress (persistent)
        Route::get('/batch/{batchId}', [KpiEnforcementController::class, 'getBatchStatus']);
        // Export (Excel/PDF) async via queue
        Route::get('/export/universe', [KpiEnforcementController::class, 'getExportUniverse']);
        Route::get('/export/latest', [KpiEnforcementController::class, 'getLatestExport']);
        Route::get('/export/list', [KpiEnforcementController::class, 'getExportList']);
        Route::post('/export/request', [KpiEnforcementController::class, 'requestExport']);
        Route::get('/export/status/{exportId}', [KpiEnforcementController::class, 'getExportStatus']);
        Route::post('/export/retry/{exportId}', [KpiEnforcementController::class, 'retryExport']);
        Route::get('/export/download/{exportId}', [KpiEnforcementController::class, 'downloadExport']);
        Route::post('/export/dismiss/{exportId}', [KpiEnforcementController::class, 'dismissExport']);
    });
});
