<?php

use App\Http\Controllers\LMS\Attendance\LmsModulAbsensiController;
use Illuminate\Support\Facades\Route;

// NOTE: Absensi (Check-in) adalah bagian dari Training Detail page
// Page: GET /lms/training/{id} - includes CheckIn component
// Route di sini hanya untuk API endpoints

/*
 * `auth` dideklarasikan di sini, bukan diwariskan dari posisi `require` berkas
 * ini di routes/web.php. Ketergantungan itu pernah gagal: satu berkas route
 * yang ter-require dua kali membuat registrasi terakhir menang dan seluruh
 * endpoint di bawahnya berjalan tanpa autentikasi.
 */
Route::middleware(['auth'])->group(function () {});

Route::prefix('api/v1')->middleware(['auth'])->group(function () {
    Route::prefix('lms/training')->group(function () {
        // Training attendance/check-in endpoints
        Route::post('/{id}/checkin', [LmsModulAbsensiController::class, 'store'])->name('api.training.checkin');
        Route::get('/{id}/checkin-status', [LmsModulAbsensiController::class, 'getStatus'])->name(
            'api.training.checkin-status',
        );
        Route::post('/{id}/activity/record', [LmsModulAbsensiController::class, 'recordActivity'])->name(
            'api.training.activity-record',
        );
        Route::post('/{id}/activity/complete', [LmsModulAbsensiController::class, 'completeActivity'])->name(
            'api.training.activity-complete',
        );
    });

    // Backward-compatible aliases for older frontend calls
    Route::prefix('lms/absensi')->group(function () {
        Route::post('', [LmsModulAbsensiController::class, 'store'])->name('api.absensi.checkin.legacy');
        Route::post('/training/activity/record', [LmsModulAbsensiController::class, 'recordActivity'])->name(
            'api.absensi.activity-record.legacy',
        );
        Route::post('/training/activity/complete', [LmsModulAbsensiController::class, 'completeActivity'])->name(
            'api.absensi.activity-complete.legacy',
        );
    });
});
