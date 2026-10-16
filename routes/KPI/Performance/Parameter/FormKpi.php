<?php

use App\Http\Controllers\KPI\Performance\Parameter\FormKpiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'check.jabatan:FormKPIPage, FormKpiTeam'])->group(function () {
    // Form KPI
    Route::middleware(['check.jabatan:FormKPIPage'])->group(function () {
        Route::get('/form-kpi', [FormKpiController::class, 'index']);
        Route::prefix('/api/v1/form-kpi')->group(function () {
            Route::post('/store', [FormKpiController::class, 'store']);
            Route::get('/current', [FormKpiController::class, 'getDataCurrent']);
        });
    });

    // Form KPI Team
    Route::middleware(['auth', 'check.jabatan:FormKpiTeam'])->group(function () {
        Route::get('/form-kpi/team', [FormKpiController::class, 'indexTeam']);
        Route::prefix('/api/v1/form-kpi/team')->group(function () {
            Route::post('/store', [FormKpiController::class, 'storeTeam']);
            Route::get('/current/{Id_Master_Penjadwalan}', [FormKpiController::class, 'getDataCurrentTeam']);
            // Bulan mulai yang berlaku per anggota (jadwal perorangan menimpa global),
            // supaya leader melihat pemangkasan sebelum mengirim — bukan sesudahnya.
            Route::post('/start-periode', [FormKpiController::class, 'getStartPeriodeTeam']);
        });
    });

    // Form KPI + Team
    Route::prefix('/api/v1/form-kpi')->group(function () {
        Route::get('/by/{id_master_formula}', [FormKpiController::class, 'getFormulaById']);
        Route::get('/team/member/{memberId}/detail', [FormKpiController::class, 'getMemberKpiDetail']);
        Route::get('/getTargetBreakdown', [FormKpiController::class, 'getTargetBreakdown']);
        Route::get('/status/{Id_Master_Penjadwalan}', [FormKpiController::class, 'getStatus']);
    });
});
