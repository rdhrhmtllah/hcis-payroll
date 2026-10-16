<?php

use App\Http\Controllers\ESS\Attendance\Leave\Admin\AdminCutiController;
use App\Http\Controllers\ESS\Attendance\Leave\Admin\CutiBersamaController;
use App\Http\Controllers\ESS\Attendance\Leave\Admin\JenisCutiController;
use Illuminate\Support\Facades\Route;

// Cuti Admin Phase 2
Route::middleware(['auth', 'check.jabatan:adminHCCutiManagementPage'])->group(function () {
    Route::get('admin/cuti', [AdminCutiController::class, 'index']);

    Route::prefix('/api/v1/cuti/admin/pengajuan')->group(function () {
        Route::get('/', [AdminCutiController::class, 'getPengajuan']);
        Route::get('/riwayat', [AdminCutiController::class, 'getRiwayatPengajuan']);
        Route::get('/detail/{id}', [AdminCutiController::class, 'getDetailPengajuan']);
        Route::post('/approve', [AdminCutiController::class, 'approvePengajuan']);
        Route::post('/reject', [AdminCutiController::class, 'rejectPengajuan']);
        // Route::get('/filter-options', [AdminCutiController::class, 'getFilterOptions']);
        Route::get('/karyawan', [AdminCutiController::class, 'getKaryawan']);
    });

    // Deteksi (read-only) transaksi cuti dengan kelebihan hari (day_rows > submitted_days).
    Route::get('/api/v1/cuti/admin/audit/extra-days', [AdminCutiController::class, 'getExtraDaysAudit']);

    Route::prefix('/api/v1/cuti/admin/jenis-cuti')->group(function () {
        Route::get('/', [JenisCutiController::class, 'index']);
        Route::get('/role-options', [JenisCutiController::class, 'roleOptions']);
        Route::post('/store', [JenisCutiController::class, 'store']);
        Route::post('/update/{id}', [JenisCutiController::class, 'update']);
        Route::delete('/destroy/{id}', [JenisCutiController::class, 'destroy']);
    });

    Route::prefix('/api/v1/cuti/admin/cuti-bersama')->group(function () {
        Route::get('/', [CutiBersamaController::class, 'index']);
        Route::post('/store', [CutiBersamaController::class, 'store']);
        Route::post('/{id}/apply', [CutiBersamaController::class, 'applyToAll']);
        Route::post('/update/{id}', [CutiBersamaController::class, 'update']);
        Route::delete('/destroy/{id}', [CutiBersamaController::class, 'destroy']);
    });
});
