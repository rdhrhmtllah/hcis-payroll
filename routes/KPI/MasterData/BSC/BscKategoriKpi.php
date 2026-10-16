<?php

use App\Http\Controllers\KPI\MasterData\BSC\BscKategoriKpiController;
use Illuminate\Support\Facades\Route;

// BSC Katergori
Route::middleware(['auth', 'check.jabatan:MasterBscKategori'])->group(function () {
    Route::get('/bsc-kategori', [BscKategoriKpiController::class, 'index']);
    Route::get('/api/v1/bsc-kategori/current', [BscKategoriKpiController::class, 'getDataCurrent']);
    Route::post('/api/v1/bsc-kategori/store', [BscKategoriKpiController::class, 'store']);
    Route::put('/api/v1/bsc-kategori/update/{Id_Master_Bsc_Kategori}', [BscKategoriKpiController::class, 'update']);
    Route::post('/api/v1/bsc-kategori/destroy', [BscKategoriKpiController::class, 'destroy']);
});
