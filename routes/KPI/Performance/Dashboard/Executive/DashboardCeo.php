<?php

use App\Http\Controllers\KPI\Performance\Dashboard\Executive\DashboardCeoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'check.jabatan:dashboardCeo'])->group(function () {
    Route::get('/dashboard-performance-all', [DashboardCeoController::class, 'index']);
    Route::get('/api/v1/dashboard-ceo/detail/{id_pengajuan}/{id_update_performance}/{isyear}', [
        DashboardCeoController::class,
        'recentKpiUserDetail',
    ]);
    // Ganti periode di dalam modal detail; karyawan diturunkan dari ref_update.
    Route::get('/api/v1/dashboard-ceo/detail-periode', [DashboardCeoController::class, 'detailPerPeriode']);
    Route::get('/api/v1/dashboard-ceo/all', [DashboardCeoController::class, 'getDataInformasi']);
    Route::get('/api/v2/dashboard-ceo/all', [DashboardCeoController::class, 'getDataInformasiV2']);
    Route::get('/dashboard-ceo/option/list-divisi', [DashboardCeoController::class, 'getDataListDivisi']);
    Route::get('/dashboard-ceo/option/list-departemen/{id_divisi}', [
        DashboardCeoController::class,
        'getDataListDepartemen',
    ]);
    Route::get('/dashboard-ceo/option/list-level', [DashboardCeoController::class, 'getDataListLevelOption']);
});
