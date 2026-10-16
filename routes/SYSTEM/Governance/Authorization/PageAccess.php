<?php

use App\Http\Controllers\SYSTEM\Governance\Authorization\PageAccess\PageAccessController;
use Illuminate\Support\Facades\Route;

// Page Access
Route::middleware(['auth', 'check.jabatan:adminAllPagesAccess'])->group(function () {
    Route::get('/page-access', [PageAccessController::class, 'index']);
    Route::prefix('/api/v1/page-access')->group(function () {
        Route::get('/initial-data', [PageAccessController::class, 'getInitialData']);
        Route::get('/search-employees', [PageAccessController::class, 'searchEmployees']);
        Route::post('/update-access', [PageAccessController::class, 'updateAccess']);
    });
});
