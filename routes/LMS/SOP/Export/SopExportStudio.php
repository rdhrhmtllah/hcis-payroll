<?php

use App\Http\Controllers\LMS\SOP\Export\SopExportStudioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SOP — Export Studio (API)
|--------------------------------------------------------------------------
| Digate dengan hak halaman monitoring SOP yang sudah ada. Menu tersendiri
| beserta Jenis_Page-nya menyusul lewat DML fase 3; sampai saat itu Studio
| dipakai oleh orang yang memang sudah berhak melihat data kepatuhan.
|
| Module TIDAK PERNAH datang dari badan permintaan — ia dipatri di controller.
*/
Route::middleware(['auth', 'check.jabatan:menuSopExportStudioPage,menuSopMonitoringPage'])
    ->group(function () {
        Route::get('sop/admin/export-studio', [SopExportStudioController::class, 'halaman'])
            ->name('sop.admin.export-studio');
    });

/*
 * {studioDataset} DI PREFIX, bukan di badan permintaan.
 *
 * Studio SOP memuat dua dataset sejak lapis dokumen ditambahkan: 'kepatuhan'
 * (butiran karyawan × paket × item) dan 'dokumen' (butiran satu dokumen).
 * Menaruhnya di alamat membuat galeri template, katalog, dan seluruh aksi
 * template otomatis terscope ke Studio yang sedang dibuka — tanpa satu pun
 * pemanggil yang bisa lupa mengirimkannya. `where` di bawah yang menjaga
 * daftar nilainya, sehingga alamat karangan berhenti di lapisan route dan tak
 * pernah sampai ke controller.
 */
Route::middleware(['auth', 'check.jabatan:menuSopExportStudioPage,menuSopMonitoringPage'])
    ->prefix('api/v1/sop/export-studio/{studioDataset}')
    ->where(['studioDataset' => 'kepatuhan|dokumen'])
    ->group(function () {
        /*
         * {dataset} SENGAJA TIDAK DIBATASI di route, berbeda dari
         * {studioDataset} di atas. Nama dataset yang tak dikenal harus kembali
         * sebagai REPORT_DATASET_UNKNOWN dari controller — kode galat yang
         * sudah jadi kontrak dengan layar. Dibatasi di sini, permintaannya
         * berhenti sebagai 404 kosong tanpa kode, dan layar kehilangan satu-
         * satunya cara membedakannya dari alamat yang salah ketik.
         */
        Route::get('/katalog/{dataset}', [SopExportStudioController::class, 'katalog']);
        Route::get('/template', [SopExportStudioController::class, 'galeri']);
        Route::post('/template', [SopExportStudioController::class, 'simpan']);
        Route::put('/template/{kode}', [SopExportStudioController::class, 'perbarui']);
        Route::post('/template/{kode}/duplikat', [SopExportStudioController::class, 'duplikat']);
        Route::delete('/template/{kode}', [SopExportStudioController::class, 'hapus']);
        // rbac_aksi=VIEW: POST yang hanya membaca (RBAC Fase 5 — bukan aksi tulis).
        Route::post('/pratinjau', [SopExportStudioController::class, 'pratinjau'])->defaults('rbac_aksi', 'VIEW');
        Route::post('/jalankan', [SopExportStudioController::class, 'jalankan'])->defaults('rbac_aksi', 'VIEW');
    });
