<?php

use App\Http\Controllers\ESS\Employee\DataCollection\AdminPengumpulanDataController;
use App\Http\Controllers\ESS\Employee\DataCollection\PengumpulanDataController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Pengumpulan Data Karyawan — sisi karyawan
|--------------------------------------------------------------------------
| Fase 1-2: halaman hanya dicapai lewat URL langsung. Modal dan bar dipasang
| di fase 3, jadi berkas ini aman naik ke produksi lebih dulu.
|
| Seluruh pintu dijaga sakelar kampanye di controller — bukan hanya modal yang
| disembunyikan.
*/

Route::prefix('pengumpulan-data')->middleware(['auth'])->name('pengumpulanData.')->group(function () {
    Route::get('/', [PengumpulanDataController::class, 'index'])->name('index');
    Route::get('/ringkasan', [PengumpulanDataController::class, 'ringkasan'])->name('ringkasan');
    Route::post('/draft', [PengumpulanDataController::class, 'simpanDraft'])->name('draft');
    Route::post('/tampil', [PengumpulanDataController::class, 'tampil'])->name('tampil');
    Route::post('/tunda', [PengumpulanDataController::class, 'tunda'])->name('tunda');
    Route::post('/kirim', [PengumpulanDataController::class, 'kirim'])->name('kirim');
    Route::get('/cari-karyawan', [PengumpulanDataController::class, 'cariKaryawan'])->name('cariKaryawan');
});

/*
|--------------------------------------------------------------------------
| Sisi admin HR
|--------------------------------------------------------------------------
| Halaman ini menampilkan NIK KTP SELURUH karyawan. Aksesnya dijaga
| check.jabatan:adminPengumpulanDataPage -- diberikan sadar lewat halaman
| /page-access, bukan terbuka untuk siapa saja yang sudah login.
|
| Sakelar kampanye SENGAJA tidak menjaga rute admin: justru saat kampanye
| dimatikan HR tetap perlu membaca dan mengekspor data yang sudah masuk.
*/
Route::prefix('admin/pengumpulan-data')
    ->middleware(['auth', 'check.jabatan:adminPengumpulanDataPage'])
    ->name('adminPengumpulanData.')
    ->group(function () {
        $c = AdminPengumpulanDataController::class;

        Route::get('/', [$c, 'index'])->name('index');
        Route::get('/ringkasan', [$c, 'ringkasan'])->name('ringkasan');
        Route::get('/daftar', [$c, 'daftar'])->name('daftar');
        Route::get('/kualitas', [$c, 'kualitas'])->name('kualitas');
        Route::get('/audit', [$c, 'audit'])->name('audit');
        Route::post('/pengecualian', [$c, 'pengecualian'])->name('pengecualian');
        Route::post('/buka-kunci', [$c, 'bukaKunci'])->name('bukaKunci');
        Route::post('/sakelar', [$c, 'sakelar'])->name('sakelar');
        Route::post('/cakupan', [$c, 'cakupan'])->name('cakupan');
        Route::post('/pengumuman', [$c, 'pengumuman'])->name('pengumuman');
        Route::post('/pengingat', [$c, 'pengingat'])->name('pengingat');

        Route::get('/ekspor/daftar', [$c, 'daftarEkspor'])->name('ekspor.daftar');
        Route::post('/ekspor', [$c, 'mintaEkspor'])->name('ekspor.minta');
        Route::get('/ekspor/{exportId}/status', [$c, 'statusEkspor'])->name('ekspor.status');
        Route::get('/ekspor/{exportId}/unduh', [$c, 'unduhEkspor'])->name('ekspor.unduh');

        // Rute dinamis DIDAFTARKAN TERAKHIR supaya tidak menelan /ringkasan,
        // /daftar, /kualitas, dan /audit di atasnya.
        Route::get('/{kodeKaryawan}', [$c, 'detail'])->name('detail');
    });
