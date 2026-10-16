<?php

use App\Http\Controllers\LMS\Training\Catalog\TrainingCatalogController;
use Illuminate\Support\Facades\Route;

Route::prefix('lms/training')
    ->name('lms.')
    ->middleware(['auth', 'check.jabatan:LMSTrainingCatalogPage'])
    ->group(function () {
        Route::get('/catalog', [TrainingCatalogController::class, 'index'])->name('training.index');
        Route::get('/filter-options', [TrainingCatalogController::class, 'getFilterOptions'])->name(
            'training.filterOptions',
        );
        Route::get('/schedule/{penjadwalanId}', [TrainingCatalogController::class, 'showBySchedule'])->name(
            'training.schedule.show',
        );
        Route::get('/{id}', [TrainingCatalogController::class, 'show'])->name('training.show');
    });

/*
 * `auth` dideklarasikan di sini, bukan diwariskan dari posisi `require` berkas
 * ini di routes/web.php. Ketergantungan itu pernah gagal: satu berkas route
 * yang ter-require dua kali membuat registrasi terakhir menang dan seluruh
 * endpoint di bawahnya berjalan tanpa autentikasi.
 */
Route::prefix('api/v1')->middleware(['auth'])->group(function () {
    Route::prefix('lms/training')->group(function () {
        Route::get('/{id}', [TrainingCatalogController::class, 'show'])->name('api.training.showData');
    });
});
