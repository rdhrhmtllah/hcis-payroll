<?php

use App\Http\Controllers\ESS\Attendance\Leave\Admin\KetentuanCutiController;
use App\Http\Controllers\ESS\Attendance\Leave\Admin\KuotaManagementController;
use Illuminate\Support\Facades\Route;

// Kuota Management - Halaman Terpisah (untuk kontrol akses independen)
Route::middleware(['auth', 'check.jabatan:adminHCKuotaManagementPage'])->group(function () {
    Route::get('/admin/kuota-management', [KuotaManagementController::class, 'index']);

    Route::prefix('/api/v1/kuota-management')->group(function () {
        // Statistics
        Route::get('/stats', [KuotaManagementController::class, 'getStats']);

        // Options for dropdowns
        Route::get('/options/jenis-cuti', [KuotaManagementController::class, 'getJenisCutiOptions']);
        Route::get('/options/divisi', [KuotaManagementController::class, 'getDivisiOptions']);
        Route::get('/options/karyawan', [KuotaManagementController::class, 'getKaryawanOptions']);

        // Kuota Karyawan
        Route::get('/karyawan', [KuotaManagementController::class, 'getAllKaryawan']);
        Route::get('/karyawan/detail', [KuotaManagementController::class, 'getKaryawanDetail']);
        Route::get('/karyawan/riwayat', [KuotaManagementController::class, 'getRiwayatCuti']);
        Route::get('/karyawan/logs', [KuotaManagementController::class, 'getLogMutasi']);
        Route::post('/karyawan/adjust', [KuotaManagementController::class, 'adjustKuota']);
        Route::get('/karyawan/wallets', [KuotaManagementController::class, 'getAvailableWallets']);

        // Process Batch Monitoring & Recovery
        Route::get('/process/dashboard', [KuotaManagementController::class, 'getProcessDashboard']);
        Route::get('/process/batches', [KuotaManagementController::class, 'getProcessBatches']);
        Route::get('/process/batches/{id}/details', [KuotaManagementController::class, 'getProcessBatchDetails']);
        Route::get('/process/dashboard/cache/flush', [KuotaManagementController::class, 'flushProcessDashboardCache']);
        Route::post('/process/recovery/preview', [KuotaManagementController::class, 'previewRecovery']);
        Route::post('/process/recovery/execute', [KuotaManagementController::class, 'executeRecovery']);

        // Dashboard Widget endpoints. Main widgets cached; detail endpoints are fresh drill-down data.
        Route::get('/dashboard/widget/heatmap-akrual', [KuotaManagementController::class, 'widgetHeatmapAkrual']);
        Route::get('/dashboard/widget/tren-akrual-bulanan', [
            KuotaManagementController::class,
            'widgetTrenAkrualBulanan',
        ]);
        Route::get('/dashboard/widget/tren-akrual-loyalty', [
            KuotaManagementController::class,
            'widgetTrenAkrualLoyalty',
        ]);
        Route::get('/dashboard/widget/anniversary-bulan-ini', [
            KuotaManagementController::class,
            'widgetAnniversaryBulanIni',
        ]);
        Route::get('/dashboard/widget/monthly-anniversary-cuti', [
            KuotaManagementController::class,
            'widgetMonthlyAnniversaryCuti',
        ]);
        Route::get('/dashboard/widget/saldo-akan-aktif', [KuotaManagementController::class, 'widgetSaldoAkanAktif']);
        Route::get('/dashboard/widget/karyawan-berpotensi-konflik', [
            KuotaManagementController::class,
            'widgetKaryawanBerpotensiKonflik',
        ]);
        Route::get('/dashboard/widget/heatmap-akrual/detail', [
            KuotaManagementController::class,
            'widgetHeatmapAkrualDetail',
        ]);
        Route::get('/dashboard/widget/tren-akrual/detail', [
            KuotaManagementController::class,
            'widgetTrenAkrualDetail',
        ]);
        Route::get('/dashboard/widget/anniversary/detail', [
            KuotaManagementController::class,
            'widgetAnniversaryDetail',
        ]);

        // Export Routes
        Route::post('/karyawan/export-kuota', [KuotaManagementController::class, 'exportKuotaKaryawan']);
        Route::get('/karyawan/export-riwayat', [KuotaManagementController::class, 'exportRiwayatCuti']);
        Route::get('/karyawan/export-log', [KuotaManagementController::class, 'exportLogMutasi']);

        // Cuti Bersama
        Route::get('/cuti-bersama', [KuotaManagementController::class, 'getCutiBersama']);
        Route::post('/cuti-bersama', [KuotaManagementController::class, 'storeCutiBersama']);
        Route::put('/cuti-bersama/{id}', [KuotaManagementController::class, 'updateCutiBersama']);
        Route::delete('/cuti-bersama/{id}', [KuotaManagementController::class, 'deleteCutiBersama']);
        Route::get('/cuti-bersama/{id}/preview', [KuotaManagementController::class, 'previewApply']);
        Route::post('/cuti-bersama/{id}/execute', [KuotaManagementController::class, 'executeApply']);

        // Ketentuan Cuti
        Route::prefix('/ketentuan')->group(function () {
            Route::get('/golongan', [KetentuanCutiController::class, 'getGolonganOptions']);

            Route::get('/kelayakan', [KetentuanCutiController::class, 'getAturanKelayakan']);
            Route::post('/kelayakan', [KetentuanCutiController::class, 'storeAturanKelayakan']);
            Route::put('/kelayakan/{id}', [KetentuanCutiController::class, 'updateAturanKelayakan']);
            Route::delete('/kelayakan/{id}', [KetentuanCutiController::class, 'deleteAturanKelayakan']);

            Route::get('/hak-tambahan', [KetentuanCutiController::class, 'getHakCutiTambahan']);
            Route::post('/hak-tambahan', [KetentuanCutiController::class, 'storeHakCutiTambahan']);
            Route::put('/hak-tambahan/{id}', [KetentuanCutiController::class, 'updateHakCutiTambahan']);
            Route::delete('/hak-tambahan/{id}', [KetentuanCutiController::class, 'deleteHakCutiTambahan']);
        });
    });
});
