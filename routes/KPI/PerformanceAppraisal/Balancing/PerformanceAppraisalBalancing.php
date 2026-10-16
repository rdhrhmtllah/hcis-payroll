<?php

use App\Http\Controllers\KPI\PerformanceAppraisal\Balancing\PaBalancingController;
use Illuminate\Support\Facades\Route;

/*
 * Performance Appraisal — Balancing berulang sesudah Calculate
 * (docs/superpowers/specs/2026-09-30-pa-balancing-design.md).
 *
 * Kunci halaman PaHasilPage, sama dengan Hitung dan Terbitkan: Balancing mengubah angka yang
 * sampai ke karyawan, jadi ia milik fase Hasil.
 */
Route::middleware(['auth', 'check.jabatan:PaHasilPage'])->group(function () {
    Route::get('/performance-appraisal/balancing', [PaBalancingController::class, 'halamanDaftar'])
        ->name('performance-appraisal.balancing');
    Route::get('/performance-appraisal/balancing/sesi/{sesiId}', [PaBalancingController::class, 'halamanSesi'])
        ->whereNumber('sesiId')->name('performance-appraisal.balancing.sesi');
});

Route::middleware(['auth', 'check.jabatan:PaHasilPage'])
    ->prefix('api/v1/performance-appraisal')
    ->group(function () {
        Route::get('/cycles/{cycleId}/balancing/sesi', [PaBalancingController::class, 'daftarSesi'])->whereNumber('cycleId');
        Route::get('/balancing/tim-penilai', [PaBalancingController::class, 'timPenilai']);
        Route::get('/cycles/{cycleId}/balancing/kandidat', [PaBalancingController::class, 'kandidat'])->whereNumber('cycleId');
        Route::post('/cycles/{cycleId}/balancing/sesi', [PaBalancingController::class, 'buatSesi'])->whereNumber('cycleId');
        Route::post('/cycles/{cycleId}/balancing/hitung-ulang', [PaBalancingController::class, 'hitungUlang'])->whereNumber('cycleId');
        Route::get('/balancing/sesi/{sesiId}', [PaBalancingController::class, 'detailSesi'])->whereNumber('sesiId');
        Route::put('/balancing/sesi/{sesiId}', [PaBalancingController::class, 'simpanSesi'])->whereNumber('sesiId');
        Route::post('/balancing/sesi/{sesiId}/final', [PaBalancingController::class, 'finalkanSesi'])->whereNumber('sesiId');
        Route::post('/balancing/sesi/{sesiId}/batal', [PaBalancingController::class, 'batalkanSesi'])->whereNumber('sesiId');
        Route::post('/balancing/sesi/{sesiId}/simulasi', [PaBalancingController::class, 'simulasikan'])->whereNumber('sesiId');
        Route::get('/participants/{participantId}/balancing/kualitatif', [PaBalancingController::class, 'kualitatifPeserta'])->whereNumber('participantId');
        Route::get('/participants/{participantId}/balancing', [PaBalancingController::class, 'riwayatPeserta'])->whereNumber('participantId');
    });
