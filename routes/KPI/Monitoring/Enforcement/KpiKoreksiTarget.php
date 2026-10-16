<?php

use App\Http\Controllers\KPI\Monitoring\Enforcement\KpiTargetKoreksiController;
use Illuminate\Support\Facades\Route;

// Kunci izin SENDIRI, bukan kpiEnforcementMonitoring. Itu peran pemantau, dan
// pemantau tidak boleh ikut mendapat kuasa mengubah angka penilaian.
//
// Kunci ini sengaja TIDAK didaftarkan di N_HRIS_Grup_Fitur_Map: satu role
// memberi seluruh halaman grup fiturnya sekaligus, jadi menaruhnya di grup KPI
// berarti memberikannya kepada setiap pemegang role KPI mana pun. Pemberiannya
// lewat HRIS_Page_Access per orang.
Route::middleware(['auth', 'check.jabatan:kpiKoreksiTarget'])->group(function () {
    Route::prefix('/api/v1/kpi-enforcement/target')->group(function () {
        Route::get('/kelayakan', [KpiTargetKoreksiController::class, 'kelayakan']);
        Route::post('/koreksi', [KpiTargetKoreksiController::class, 'koreksi']);
    });
});
