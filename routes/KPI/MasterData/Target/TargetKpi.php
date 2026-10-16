<?php

use App\Http\Controllers\KPI\MasterData\Target\TargetKpiController;
use App\Http\Controllers\KPI\Performance\Management\PerformanceKpiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'check.jabatan:MasterTarget'])->group(function () {
    // Target KPI
    Route::get('/target-kpi', [TargetKpiController::class, 'index'])->name('target-kpi.index');
    Route::get('/api/v1/target-kpi/current', [TargetKpiController::class, 'getDataCurrent']);
    Route::post('/api/v1/target-kpi/store', [TargetKpiController::class, 'store']);
    Route::put('/api/v1/target-kpi/update/{id_master_target}', [TargetKpiController::class, 'update']);
});

Route::middleware(['auth', 'check.jabatan:MasterTarget'])->group(function () {
    // Performance KPI
    Route::get('/performance-kpi', [PerformanceKpiController::class, 'index'])->name('performance-kpi.index');
    Route::get('/api/v1/performance-kpi/current', [PerformanceKpiController::class, 'getDataCurrent']);
    Route::post('/api/v1/performance-kpi/store', [PerformanceKpiController::class, 'store']);
    Route::put('/api/v1/performance-kpi/update/{id_master_target}', [PerformanceKpiController::class, 'update']);
});
