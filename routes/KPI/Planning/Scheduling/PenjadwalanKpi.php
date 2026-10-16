<?php

use App\Http\Controllers\KPI\Planning\Scheduling\PenjadwalanKpiController;
use Illuminate\Support\Facades\Route;

// ====================
// CONTROLLER KPI BY FRANS
// ====================
Route::middleware(['auth', 'check.jabatan:MasterPenjadwalan'])->group(function () {
    Route::get('/penjadwalan-kpi', [PenjadwalanKpiController::class, 'index'])->name('penjadwalan-kpi.index');
    Route::get('/api/v1/penjadwalan-kpi/current', [PenjadwalanKpiController::class, 'getDataCurrent']);
    Route::get('/api/v1/penjadwalan-kpi/current/karyawan', [PenjadwalanKpiController::class, 'getDataKaryawan']);
    Route::get('/api/v1/penjadwalan-kpi/current/karyawan-not-kpi', [
        PenjadwalanKpiController::class,
        'getDataKaryawanBelumAdaKpi',
    ]);
    Route::post('/api/v1/penjadwalan-kpi/store', [PenjadwalanKpiController::class, 'store']);
    Route::post('/api/v1/penjadwalan-kpi/toggle-switch', [PenjadwalanKpiController::class, 'toggleSwitch']);
    Route::post('/api/v1/penjadwalan-kpi/toggle-switch-peserta', [
        PenjadwalanKpiController::class,
        'toggleSwitchPeserta',
    ]);
    Route::post('/api/v1/penjadwalan-kpi/arsipkan', [PenjadwalanKpiController::class, 'arsipkan']);
    Route::post('/api/v1/penjadwalan-kpi/pulihkan', [PenjadwalanKpiController::class, 'pulihkan']);
    Route::put('/api/v1/penjadwalan-kpi/update/{id_master_penjadwalan}', [PenjadwalanKpiController::class, 'update']);
    // Audit baca-saja: target yang tersimpan di bulan sebelum bulan mulai karyawan.
    // Pembersihannya lewat artisan kpi:rapikan-target-sebelum-mulai, bukan lewat HTTP.
    Route::get('/api/v1/penjadwalan-kpi/audit/target-sebelum-mulai', [
        PenjadwalanKpiController::class,
        'auditTargetSebelumMulai',
    ]);
});
