<?php

use App\Http\Controllers\LMS\Training\MasterData\TrainingCategory\TrainingCategoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('lms')
    ->middleware(['auth', 'check.jabatan:LMSTrainingKategoriPage'])
    ->group(function () {
        Route::get('/training-kategori', [TrainingCategoryController::class, 'index']);
    });

/*
 * `auth` dideklarasikan di sini, bukan diwariskan dari tempat berkas ini
 * di-require. Lihat catatan yang sama di
 * routes/LMS/Training/Scheduling/TrainingScheduling.php.
 */
/*
 * Grup ini DIPECAH karena pembacaan dan penulisannya punya pemanggil berbeda.
 *
 * Penyisiran pemanggil menemukan:
 *   - /current  dipanggil DUA halaman: TrainingCategoryPage.vue (izin
 *     LMSTrainingKategoriPage) dan AdminTrainingListPage.vue (izin
 *     LMSAdminTrainingPage).
 *   - /store dan /update hanya dipanggil TrainingCategoryPage.vue.
 *
 * Karena itu satu `check.jabatan:LMSTrainingKategoriPage` untuk seluruh grup
 * akan mematikan daftar kategori di layar Admin Training bagi orang yang punya
 * akses admin training tetapi bukan master kategori. `check.jabatan` menerima
 * beberapa halaman dengan semantik ATAU, jadi pembacaannya menyebut keduanya.
 *
 * Sebelumnya seluruh grup hanya `auth`: setiap pengguna yang sudah login bisa
 * memanggil store dan update master kategori training.
 */
Route::prefix('api/v1/training/kategori')->group(function () {
    Route::middleware(['auth', 'check.jabatan:LMSTrainingKategoriPage,LMSAdminTrainingPage'])
        ->get('/current', [TrainingCategoryController::class, 'getDataCurrent']);

    Route::middleware(['auth', 'check.jabatan:LMSTrainingKategoriPage'])->group(function () {
        Route::post('/store', [TrainingCategoryController::class, 'store']);
        Route::put('/update/{id_training_kategori}', [TrainingCategoryController::class, 'update']);
    });
});
