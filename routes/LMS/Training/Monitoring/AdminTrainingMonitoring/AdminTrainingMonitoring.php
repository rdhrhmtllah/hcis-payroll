<?php

use App\Http\Controllers\LMS\Training\Monitoring\AdminTrainingMonitoring\AdminTrainingMonitoringController;
use Illuminate\Support\Facades\Route;

Route::prefix('lms/admin/monitoring')
    ->name('lms.admin.monitoring.')
    ->middleware(['auth', 'check.jabatan:LMSAdminMonitoringPage'])
    ->group(function () {
        // === PAGES ===
        Route::get('/', [AdminTrainingMonitoringController::class, 'index'])->name('index');
        Route::get('/training/schedule/{penjadwalanId}/{kodeKaryawan}', [
            AdminTrainingMonitoringController::class,
            'showTrainingDetailBySchedule',
        ])->name('training-detail-schedule');
        Route::get('/training/{materiId}/{kodeKaryawan}', [AdminTrainingMonitoringController::class, 'showTrainingDetail'])->name(
            'training-detail',
        );
        Route::get('/test-detail/{penjadwalanDetId}', [AdminTrainingMonitoringController::class, 'showTestDetail'])->name(
            'test-detail',
        );

        // === API ===
        Route::get('/filter-options', [AdminTrainingMonitoringController::class, 'getFilterOptions'])->name('api.filter-options');
        Route::get('/employees', [AdminTrainingMonitoringController::class, 'getEmployeeList'])->name('api.employees');
        Route::get('/employees/{kodeKaryawan}/trainings', [
            AdminTrainingMonitoringController::class,
            'getEmployeeTrainings',
        ])->name('api.employee-trainings');
        Route::get('/training/schedule/{penjadwalanId}/{kodeKaryawan}/progress', [
            AdminTrainingMonitoringController::class,
            'getTrainingProgressBySchedule',
        ])->name('api.training-progress-schedule');
        Route::get('/training/{materiId}/{kodeKaryawan}/progress', [
            AdminTrainingMonitoringController::class,
            'getTrainingProgress',
        ])->name('api.training-progress');
        Route::get('/test-detail/{penjadwalanDetId}/answer-detail', [
            AdminTrainingMonitoringController::class,
            'getTestAnswerDetail',
        ])->name('api.test-answer-detail');

        // === EXPORT DATA TRAINING ===
        Route::get('/export/employees', [AdminTrainingMonitoringController::class, 'getExportEmployees'])->name(
            'api.export-employees',
        );
        Route::get('/export/selection-ids', [AdminTrainingMonitoringController::class, 'getExportSelectionIds'])->name(
            'api.export-selection-ids',
        );
        Route::get('/export/employees/{kodeKaryawan}/trainings', [
            AdminTrainingMonitoringController::class,
            'getExportEmployeeTrainings',
        ])->name('api.export-employee-trainings');
        Route::get('/export/trainings/search', [AdminTrainingMonitoringController::class, 'searchTrainings'])->name(
            'api.export-trainings-search',
        );
        Route::post('/export/request', [AdminTrainingMonitoringController::class, 'requestExport'])->name('api.export-request');
        Route::get('/export/list', [AdminTrainingMonitoringController::class, 'getExportList'])->name('api.export-list');
        Route::get('/export/status/{exportId}', [AdminTrainingMonitoringController::class, 'getExportStatus'])->name(
            'api.export-status',
        );
        Route::post('/export/retry/{exportId}', [AdminTrainingMonitoringController::class, 'retryExport'])->name(
            'api.export-retry',
        );
        Route::get('/export/download/{exportId}', [AdminTrainingMonitoringController::class, 'downloadExport'])->name(
            'api.export-download',
        );
        Route::post('/export/dismiss/{exportId}', [AdminTrainingMonitoringController::class, 'dismissExport'])->name(
            'api.export-dismiss',
        );
    });
