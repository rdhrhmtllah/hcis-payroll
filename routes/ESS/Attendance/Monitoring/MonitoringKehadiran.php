<?php

use App\Http\Controllers\ESS\Attendance\Monitoring\MonitoringKehadiranController;
use Illuminate\Support\Facades\Route;

/*
| Admin HC Personel: pantau kehadiran hari ini & rekap periode (DML-2026-193).
| Murni baca; data dibatasi cakupan admin (FunctionalRole).
*/
Route::middleware(['auth'])
    ->prefix('admin/monitoring-kehadiran')
    ->name('monitoring-kehadiran.')
    ->group(function () {
        Route::get('/', [MonitoringKehadiranController::class, 'index'])->name('index');
        Route::get('/data/hari-ini', [MonitoringKehadiranController::class, 'hariIni'])->name('hari-ini');
        Route::get('/data/rekap', [MonitoringKehadiranController::class, 'rekap'])->name('rekap');
        Route::get('/data/rekap-kelompok', [MonitoringKehadiranController::class, 'rekapKelompok'])->name('rekap-kelompok');
        Route::get('/data/grafik', [MonitoringKehadiranController::class, 'grafik'])->name('grafik');
        Route::get('/data/rincian/{kode}', [MonitoringKehadiranController::class, 'rincian'])->name('rincian');
        Route::post('/ekspor', [MonitoringKehadiranController::class, 'mintaEkspor'])->name('ekspor');
        Route::get('/ekspor', [MonitoringKehadiranController::class, 'daftarEkspor'])->name('ekspor.daftar');
        Route::get('/ekspor/{exportId}', [MonitoringKehadiranController::class, 'statusEkspor'])->name('ekspor.status');
        Route::get('/ekspor/{exportId}/unduh', [MonitoringKehadiranController::class, 'unduhEkspor'])->name('ekspor.unduh');
    });
