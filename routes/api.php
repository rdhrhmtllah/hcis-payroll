<?php

use App\Http\Controllers\SYSTEM\Application\Maintenance\RuntimeRecoveryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — HCIS Payroll
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

if (file_exists(base_path('routes/SYSTEM/TaskProcessing.php'))) {
    require __DIR__.'/SYSTEM/TaskProcessing.php';
}

Route::prefix('ops/runtime')->group(function () {
    if (class_exists(RuntimeRecoveryController::class)) {
        Route::get('/db-health', [RuntimeRecoveryController::class, 'dbHealth']);
        Route::post('/db-reconnect', [RuntimeRecoveryController::class, 'dbReconnect']);
        Route::post('/laravel-refresh', [RuntimeRecoveryController::class, 'laravelRefresh']);
    }
});

Route::middleware(['web', 'auth'])
    ->prefix('v1/activity')
    ->group(function () {
        if (class_exists(\App\Http\Controllers\SYSTEM\Audit\Activity\ActivityHandshakeController::class)) {
            Route::post('/handshake', [
                \App\Http\Controllers\SYSTEM\Audit\Activity\ActivityHandshakeController::class,
                'handshake',
            ])->name('activity.handshake');
        }
    });
