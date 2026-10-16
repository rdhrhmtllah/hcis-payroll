<?php

use App\Http\Controllers\KPI\MasterData\Scoring\Formula\FormulaKpiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'check.jabatan:MasterFormula'])->group(function () {
    Route::get('/formula-kpi', [FormulaKpiController::class, 'index'])->name('formula-kpi.index');
    Route::get('/formula-kpi/create', [FormulaKpiController::class, 'create'])->name('formula-kpi.create');
    Route::get('/formula-kpi/edit/{id_master_formula}', [FormulaKpiController::class, 'edit'])->name(
        'formula-kpi.edit',
    );
    Route::get('/api/v1/formula-kpi/current', [FormulaKpiController::class, 'getDataCurrent']);
    Route::get('/api/v1/formula-kpi/by/{id_master_formula}', [FormulaKpiController::class, 'getFormulaById']);
    Route::post('/api/v1/formula-kpi/store', [FormulaKpiController::class, 'store']);
    Route::put('/api/v1/formula-kpi/update/{id_master_formula}', [FormulaKpiController::class, 'update']);
    Route::post('/api/v1/formula-kpi/toggle-switch', [FormulaKpiController::class, 'toggleSwitch']);
});
