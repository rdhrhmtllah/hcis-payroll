<?php

use App\Http\Controllers\ESS\Attendance\Leave\Approval\IzinAtasanApprovalController;
use Illuminate\Support\Facades\Route;

// Izin Atasan Approval

Route::middleware(['auth', 'check.jabatan:IzinPageApprover'])->group(function () {
    Route::get('/izin-atasan-approval', [IzinAtasanApprovalController::class, 'index']);
    Route::prefix('/api/v1/izin-atasan-approval')->group(function () {
        Route::get('/', [IzinAtasanApprovalController::class, 'getData']);
        Route::get('/getDataDH', [IzinAtasanApprovalController::class, 'getData2']);
        Route::post('/approve', [IzinAtasanApprovalController::class, 'confirm']);
        Route::post('/approveDH', [IzinAtasanApprovalController::class, 'confirm']);
        Route::get('/history', [IzinAtasanApprovalController::class, 'getApprovalHistory']);
    });
});
