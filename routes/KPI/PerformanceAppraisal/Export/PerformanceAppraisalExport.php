<?php

use App\Http\Controllers\KPI\PerformanceAppraisal\Export\PaExportStudioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| KPI / Performance Appraisal — Export Studio
|--------------------------------------------------------------------------
| SATU kunci hak, bukan bentuk OR. Gerbang API memakai kunci yang sama dengan
| gerbang halaman. Menggerbangi halaman saja hanya menyembunyikan tombolnya --
| datanya tetap bisa ditarik langsung dari endpoint.
*/
Route::middleware(['auth', 'check.jabatan:PaExportStudioPage'])->group(function () {
    Route::get('performance-appraisal/export-studio', [PaExportStudioController::class, 'halaman'])
        ->name('pa.export-studio');
});

Route::middleware(['auth', 'check.jabatan:PaExportStudioPage'])
    ->prefix('api/v1/performance-appraisal/export-studio/{studioDataset}')
    ->group(function () {
        // {dataset} SENGAJA tidak dibatasi dengan ->where(): nama dataset yang
        // tak dikenal harus kembali sebagai REPORT_DATASET_UNKNOWN dari lapisan
        // katalog -- kode galat itulah kontraknya dengan layar. Kalau dibatasi
        // di route, yang kembali cuma 404 kosong dari router dan layar tidak
        // bisa membedakan "salah nama dataset" dari "halaman tidak ada".
        Route::get('/katalog/{dataset}', [PaExportStudioController::class, 'katalog']);
        Route::get('/template', [PaExportStudioController::class, 'galeri']);
        Route::post('/template', [PaExportStudioController::class, 'simpan']);
        Route::put('/template/{kode}', [PaExportStudioController::class, 'perbarui']);
        Route::post('/template/{kode}/duplikat', [PaExportStudioController::class, 'duplikat']);
        Route::delete('/template/{kode}', [PaExportStudioController::class, 'hapus']);
        Route::post('/pratinjau', [PaExportStudioController::class, 'pratinjau']);
        Route::post('/jalankan', [PaExportStudioController::class, 'jalankan']);
    });
