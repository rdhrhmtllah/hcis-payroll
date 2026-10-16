<?php

use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'check.jabatan:UpdatePerformancePage'])->group(function () {
    Route::get('/update-performance', [UpdatePerformanceController::class, 'index'])->name('update-performance.index');
    Route::get('/api/v1/update-performance/list-Kpi', [UpdatePerformanceController::class, 'getDataKpiItems']);
    Route::post('/api/v1/update-performance/store/temporary', [
        UpdatePerformanceController::class,
        'storeTemporaryPerformanceStep1',
    ]);
    Route::post('/api/v1/update-performance/finalize', [UpdatePerformanceController::class, 'finalisasiPerformance']);
});
Route::middleware(['auth', 'check.jabatan:UpdatePerformanceTeam'])->group(function () {
    Route::get('/update-performance/team', [UpdatePerformanceController::class, 'indexUpdatePerformanceTeam'])->name(
        'update-performance-team.index',
    );
    Route::get('/update-performance/by-pass', [UpdatePerformanceController::class, 'indexUpdatePerformanceByPass']);
    Route::get('/update-performance/team/{user_id}', [
        UpdatePerformanceController::class,
        'showUpdatePerformanceByTeam',
    ]);
    Route::get('/update-performance/by-pass/{user_id}', [
        UpdatePerformanceController::class,
        'showUpdatePerformanceByPass',
    ]);
    Route::get('/api/v1/update-performance/list-Kpi-team', [UpdatePerformanceController::class, 'getDataKpiItemsTeam']);
    Route::get('/api/v1/update-performance/list-Kpi-team/belum-parameter', [
        UpdatePerformanceController::class,
        'getBelumParameterTeam',
    ]);
    Route::get('/api/v1/update-performance/list-Kpi-bypass', [
        UpdatePerformanceController::class,
        'getDataKpiItemsByPass',
    ]);
    Route::post('/api/v1/update-performance/list-Kpi/by-user', [
        UpdatePerformanceController::class,
        'getDataKpiItemsByIdUser',
    ]);
    Route::post('/api/v1/update-performance/list-Kpi-by-pass/by-user', [
        UpdatePerformanceController::class,
        'getDataKpiItemsByIdUserByPass',
    ]);
    Route::get('/api/v1/update-performance/list-Kpi-team/on-progress', [
        UpdatePerformanceController::class,
        'getDataKpiItemsTeamForApproval',
    ]);
    Route::get('/api/v1/update-performance/list-Kpi-team/rejected', [
        UpdatePerformanceController::class,
        'getDataKpiItemsTeamForApprovalRejected',
    ]);
    Route::get('/api/v1/update-performance/list-Kpi-team/widget-count', [
        UpdatePerformanceController::class,
        'getKpiTeamSummaryCounts',
    ]);
    Route::post('/api/v1/update-performance/temporary-actual-performance/by-team/{id_user}', [
        UpdatePerformanceController::class,
        'getDataTemporaryDataStep1ByTeamUserId',
    ]);
    Route::post('/api/v1/update-performance/temporary-actual-performance/by-pass/{id_user}', [
        UpdatePerformanceController::class,
        'getDataTemporaryDataStep1ByTeamUserIdByPass',
    ]);
    Route::post('/api/v1/update-performance/store/temporary/{id_user}', [
        UpdatePerformanceController::class,
        'storeTemporaryPerformanceStep1ByTeam',
    ]);
    Route::post('/api/v1/update-performance/store/temporary-bypass/{id_user}', [
        UpdatePerformanceController::class,
        'storeTemporaryPerformanceStep1ByTeamByPass',
    ]);
    Route::post('/api/v1/update-performance/finalize/by-team/{id_user}', [
        UpdatePerformanceController::class,
        'finalisasiPerformanceByTeam',
    ]);
    Route::post('/api/v1/update-performance/approval-flow/by-team/{id_user}', [
        UpdatePerformanceController::class,
        'getApprovalFlow',
    ]);
    Route::post('/api/v1/update-performance/finalize/by-pass/{id_user}', [
        UpdatePerformanceController::class,
        'finalisasiPerformanceByPass',
    ]);
    Route::post('/api/v1/update-performance/by-pass/jadwal', [
        UpdatePerformanceController::class,
        'updateGetDateDummy2',
    ]);
    Route::get('/api/v1/update-performance/by-pass/master-penjadwalan', [
        UpdatePerformanceController::class,
        'getMasterPenjadwalanForByPass',
    ]);
});

Route::middleware(['auth', 'check.jabatan:UpdatePerformancePage,UpdatePerformanceTeam'])->group(function () {
    Route::post('/api/v1/update-performance/master-grading/performance', [
        UpdatePerformanceController::class,
        'getMasterGrading',
    ]);
    Route::get('/api/v1/update-performance/kpi-cap-config', [
        UpdatePerformanceController::class,
        'getKpiCapConfig',
    ]);
    Route::get('/api/v1/update-performance/temporary-actual-performance', [
        UpdatePerformanceController::class,
        'getDataTemporaryDataStep1',
    ]);
    Route::get('/api/v1/update-performance/list-performance', [
        UpdatePerformanceController::class,
        'getDataPerformance',
    ]);
    Route::post('/api/v1/update-performance/list-history', [
        UpdatePerformanceController::class,
        'getDataHistoryKpiAndUpdateActive',
    ]);
    Route::post('/api/v1/update-performance/store/steps2', [
        UpdatePerformanceController::class,
        'storeTemporaryPerformanceStep2',
    ]);
    Route::post('/api/v1/update-performance/store/temporary-pica', [
        UpdatePerformanceController::class,
        'storePicaUpdatePerformance',
    ]);
    Route::post('/api/v1/update-performance/store/temporary-pica-bypass', [
        UpdatePerformanceController::class,
        'storePicaUpdatePerformanceByPass',
    ]);
    Route::get('/api/v1/update-performance/approval-flow', [
        UpdatePerformanceController::class,
        'getApprovalFlow',
    ]);
});
