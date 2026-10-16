<?php

use App\Http\Controllers\KPI\Performance\Dashboard\StaffPerformanceDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'check.jabatan:dashboardStaff'])->group(function () {
    Route::get('/dashboard-my-performance', [StaffPerformanceDashboardController::class, 'index']);
    Route::get('/api/v1/dashboard-my-performance/detail/{id_pengajuan}/{id_update_performance}/{isyear}', [
        StaffPerformanceDashboardController::class,
        'recentKpiUserDetail',
    ]);
    // Ganti periode di dalam modal detail (selalu milik user yang login).
    Route::get('/api/v1/dashboard-my-performance/detail-periode', [
        StaffPerformanceDashboardController::class,
        'detailPerPeriode',
    ]);
    // Route::get("/api/v1/dashboard-ceo/detail-my-performance/{id_pengajuan}/{id_update_performance}", [StaffPerformanceDashboardController::class, 'recentKpiUserDetailMyPerfomance']);
    Route::get('/api/v1/dashboard-ceo/my-performance', [StaffPerformanceDashboardController::class, 'getDataInformasiByUserId']);
});
