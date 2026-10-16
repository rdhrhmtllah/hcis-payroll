<?php

use App\Http\Controllers\KPI\MasterData\Unit\SatuanKpiController;
use Illuminate\Support\Facades\Route;

// Satuan
Route::middleware(['auth', 'check.jabatan:MasterSatuan'])->group(function () {
    Route::get('/satuan', [SatuanKpiController::class, 'index']);
    Route::get('/api/v1/satuan/current', [SatuanKpiController::class, 'getDataCurrent']);
    Route::post('/api/v1/satuan/store', [SatuanKpiController::class, 'store']);
    Route::put('/api/v1/satuan/update/{Id_Master_Satuan}', [SatuanKpiController::class, 'update']);
    Route::post('/api/v1/satuan/destroy', [SatuanKpiController::class, 'destroy']);
});
