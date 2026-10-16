<?php

use App\Http\Controllers\ESS\Attendance\Leave\Personal\CutiController;
use Illuminate\Support\Facades\Route;

// Cuti Karyawan Phase 2
Route::middleware(['auth', 'check.jabatan:cutiPage'])->group(function () {
    Route::get('cuti', [CutiController::class, 'index']);

    Route::prefix('/api/v1/cuti')->group(function () {
        Route::get('dataCurrent', [CutiController::class, 'current']);
        Route::get('/riwayat', [CutiController::class, 'getRiwayat']);
        Route::get('/riwayat/{no_transaksi}', [CutiController::class, 'getDetailRiwayat']);
        Route::get('/current', [CutiController::class, 'getRiwayat']);
        Route::get('/jenis-cuti', [CutiController::class, 'getJenisCuti']);
        Route::get('/jenis-cuti/{id}/approval-flow', [CutiController::class, 'getJenisCutiApprovalFlow']);
        Route::get('/jenis-cuti/{id}/hutang-usage', [CutiController::class, 'getHutangUsage']);
        Route::get('/check-cuti-bersama', [CutiController::class, 'checkOverlapCutiBersama']);
        Route::get('/check-saldo', [CutiController::class, 'getSaldoCuti']);
        Route::post('/store', [CutiController::class, 'store']);
        Route::post('/destroy', [CutiController::class, 'destroy']);
    });
});
