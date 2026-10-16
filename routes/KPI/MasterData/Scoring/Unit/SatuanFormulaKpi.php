<?php

use App\Http\Controllers\KPI\MasterData\Scoring\Unit\SatuanFormulaKpiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'check.jabatan:MasterSatuanFormula'])->group(function () {
    // Satuan Formula
    Route::get('/satuan-formula', [SatuanFormulaKpiController::class, 'index'])->name('satuan-formula.index');
    Route::get('/api/v1/satuan-formula/current', [SatuanFormulaKpiController::class, 'getDataCurrent']);
    Route::get('/api/v1/satuan-formula/option/list-satuan', [SatuanFormulaKpiController::class, 'getDataOptionSatuan']);
    Route::get('/api/v1/satuan-formula/option/list-formula', [
        SatuanFormulaKpiController::class,
        'getDataOptionFormula',
    ]);
    Route::post('/api/v1/satuan-formula/store', [SatuanFormulaKpiController::class, 'store']);
    Route::put('/api/v1/satuan-formula/update/{id_master_satuan_formula}', [
        SatuanFormulaKpiController::class,
        'update',
    ]);
    Route::post('/api/v1/satuan-formula/toggle-switch', [SatuanFormulaKpiController::class, 'toggleSwitch']);
});
