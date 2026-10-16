<?php

use App\Http\Controllers\LMS\Training\Catalog\TrainingCatalogApi;
use Illuminate\Support\Facades\Route;

/*
 * Berkas ini dimuat dari routes/api.php, sehingga route di bawahnya hanya
 * memperoleh middleware `api` — tanpa autentikasi. Endpoint `show` bukan stub:
 * ia membaca N_LMS_Materi beserta rincian penjadwalannya, jadi sebelumnya isi
 * materi training dapat diambil tanpa login.
 *
 * Terbuka untuk ditinjau: endpoint ini tidak dipanggil dari mana pun, URL-nya
 * berprefix ganda (`api/api/v1/...`) karena routes/api.php sudah menambahkan
 * prefix `api`, dan padanan terproteksinya sudah ada di
 * `api/v1/lms/training/{id}` lewat TrainingCatalogController. Kemungkinan besar
 * berkas ini sisa registrasi yang tidak disengaja dan layak dihapus.
 */
Route::prefix('api/v1')->middleware(['auth'])->group(function () {
    Route::prefix('lms/training')->group(function () {
        Route::get('/{id}', [TrainingCatalogApi::class, 'show'])->name('api.training.show');
        // Tambahkan endpoint lain jika perlu, misal: index, store, update, destroy
    });
});
