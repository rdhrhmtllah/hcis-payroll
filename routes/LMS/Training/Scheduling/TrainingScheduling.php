<?php

use App\Http\Controllers\LMS\Training\Scheduling\TrainingSchedulingController;
use Illuminate\Support\Facades\Route;

Route::prefix('lms')
    ->middleware(['auth', 'check.jabatan:LMSPenjadwalanPage'])
    ->group(function () {
        Route::get('/penjadwalan', [TrainingSchedulingController::class, 'index']);
    });

/*
 * `auth` dideklarasikan di sini, bukan diwariskan dari tempat berkas ini
 * di-require. Sebelumnya grup ini tidak mendeklarasikan middleware apa pun,
 * sehingga proteksinya sepenuhnya bergantung pada posisi `require` di
 * routes/web.php. Ketika berkas ini ter-require dua kali — sekali di dalam
 * grup auth dan sekali di luarnya — registrasi terakhir menang dan seluruh
 * endpoint di bawah ini berjalan tanpa autentikasi.
 *
 * Deklarasi eksplisit membuat proteksinya tidak lagi bergantung pada susunan
 * berkas lain.
 */
/*
 * `check.jabatan` disamakan dengan halaman yang dilayani. Penyisiran
 * pemanggil menemukan endpoint di bawah ini HANYA dipanggil dari
 * Pages/LMS/Training/Scheduling/TrainingSchedulingPage.vue, yang rutenya
 * sendiri sudah menuntut LMSPenjadwalanPage. Sebelumnya `auth` saja,
 * sehingga setiap pengguna yang sudah login bisa memanggil store, update,
 * toggle, bulk-toggle, dan reassign-third-party.
 */
Route::prefix('api/v1')
    ->middleware(['auth', 'check.jabatan:LMSPenjadwalanPage'])
    ->group(function () {
        Route::prefix('lms/penjadwalan')->group(function () {
            Route::get('/options/karyawan-current', [TrainingSchedulingController::class, 'getDataKaryawan']);
            Route::get('/options/lokasi-karyawan', [TrainingSchedulingController::class, 'getLokasiKaryawan']);
            Route::get('/options/lokasi-kerja', [TrainingSchedulingController::class, 'getLokasiKerja']);
            Route::get('/options/master-paket/training', [TrainingSchedulingController::class, 'getMasterTraining']);
            Route::get('/options/kategori-ujian', [TrainingSchedulingController::class, 'getDataKategoriUjian']);
            Route::get('/options/formula', [TrainingSchedulingController::class, 'getDataMasterRumus']);
            Route::get('/options/level', [TrainingSchedulingController::class, 'getDataMasterLevel']);
            Route::post('/store', [TrainingSchedulingController::class, 'store']);
            Route::get('/current', [TrainingSchedulingController::class, 'getDataCurrent']);
            Route::get('/all-ids', [TrainingSchedulingController::class, 'getAllIds']);
            Route::post('/update', [TrainingSchedulingController::class, 'update']);
            Route::post('/toggle', [TrainingSchedulingController::class, 'toggleAktif']);
            Route::post('/reassign-third-party', [TrainingSchedulingController::class, 'reassignThirdParty']);
            Route::post('/bulk-toggle', [TrainingSchedulingController::class, 'bulkToggleAktif']);
            Route::get('/progress/{process_id}', [TrainingSchedulingController::class, 'checkProgress']);
        });
    });
