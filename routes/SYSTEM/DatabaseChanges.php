<?php

use App\Http\Controllers\SYSTEM\DatabaseChanges\DatabaseChangeDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'system.admin'])->prefix('system/database-changes')->name('system.database-changes.')->group(function () {
    Route::get('/', [DatabaseChangeDashboardController::class, 'page'])->name('page');
    Route::get('/data', [DatabaseChangeDashboardController::class, 'index'])->name('index');
    Route::post('/batch', [DatabaseChangeDashboardController::class, 'batch'])->name('batch');
    // Ditaruh sebelum route ber-parameter supaya 'bulk-sql' tidak tertangkap
    // sebagai {databaseChange}.
    Route::post('/bulk-sql', [DatabaseChangeDashboardController::class, 'bulkSql'])->name('bulk-sql');
    Route::get('/{databaseChange}/sql', [DatabaseChangeDashboardController::class, 'sql'])->name('sql');
    Route::patch('/{databaseChange}/status', [DatabaseChangeDashboardController::class, 'update'])->name('status');
});
