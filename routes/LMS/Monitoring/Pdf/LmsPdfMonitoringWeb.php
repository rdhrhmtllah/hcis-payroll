<?php

use App\Http\Controllers\LMS\Monitoring\Pdf\LmsPdfMonitoringController;
use Illuminate\Support\Facades\Route;

Route::prefix('lms/admin/pdf-monitoring')
    ->name('lms.admin.pdf-monitoring.')
    ->middleware(['auth', 'check.jabatan:LMSAdminMonitoringPage'])
    ->group(function () {
        // === PAGE ===
        Route::get('/', [LmsPdfMonitoringController::class, 'index'])->name('index');

        // === API ===
        Route::get('/summary', [LmsPdfMonitoringController::class, 'getSummary'])->name('api.summary');
        Route::get('/list', [LmsPdfMonitoringController::class, 'getList'])->name('api.list');
        Route::get('/detail/{berkasId}', [LmsPdfMonitoringController::class, 'getDetail'])->name('api.detail');
        Route::post('/migrate', [LmsPdfMonitoringController::class, 'startMigration'])->name('api.migrate');
        Route::post('/retry/{berkasId}', [LmsPdfMonitoringController::class, 'retryDocument'])->name('api.retry');
    });
