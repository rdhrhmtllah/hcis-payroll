<?php

use App\Http\Controllers\SYSTEM\Governance\Authorization\AksesPeran\AksesPeranController;
use Illuminate\Support\Facades\Route;

/*
| RBAC Fase 2b — layar "Akses & Peran" (baca-saja). Gerbang `akses.peran`:
| System Admin atau pemegang role ADMIN; lingkup modul ditentukan server.
*/
// SYSTEM → Akses & Peran: lintas modul, khusus System Admin.
Route::middleware(['auth', 'system.admin'])->get('/system/akses-peran', [AksesPeranController::class, 'page'])->name('system.akses-peran.page');

Route::middleware(['auth', 'akses.peran'])->group(function () {
    // Per modul (DML-2026-183): Super Admin & Admin modul masuk dari menu modulnya.
    Route::get('/admin/{modul}/akses-peran', [AksesPeranController::class, 'pageModul'])
        ->whereIn('modul', ['ess', 'kpi', 'lms', 'visitor', 'dms'])->name('modul.akses-peran.page');

    Route::prefix('/api/v1/akses-peran')->name('system.akses-peran.')->group(function () {
        Route::get('/ringkasan', [AksesPeranController::class, 'ringkasan'])->name('ringkasan');
        Route::get('/peran', [AksesPeranController::class, 'peran'])->name('peran');
        Route::get('/peran/isi', [AksesPeranController::class, 'isiPeran'])->name('peran.isi');
        Route::get('/halaman', [AksesPeranController::class, 'petaHalaman'])->name('halaman');
        Route::get('/halaman/orang', [AksesPeranController::class, 'orangHalaman'])->name('halaman.orang');
        Route::get('/peran/{id}', [AksesPeranController::class, 'detailPeran'])->whereNumber('id')->name('peran.detail');
        Route::get('/pengguna', [AksesPeranController::class, 'pengguna'])->name('pengguna');
        Route::get('/pengguna/pilih', [AksesPeranController::class, 'pilihKaryawan'])->name('pengguna.pilih');
        Route::get('/pengguna/opsi-saring', [AksesPeranController::class, 'opsiSaringKaryawan'])->name('pengguna.opsi-saring');
        Route::get('/pengguna/{id}', [AksesPeranController::class, 'detailPengguna'])->whereNumber('id')->name('pengguna.detail');
        Route::get('/pengguna/{id}/telusuri', [AksesPeranController::class, 'telusuri'])->whereNumber('id')->name('pengguna.telusuri');
        Route::get('/admin', [AksesPeranController::class, 'admin'])->name('admin');
        Route::get('/audit', [AksesPeranController::class, 'audit'])->name('audit');

        // Fase 3a — Katalog (System Admin; dicek di controller).
        Route::get('/katalog', [AksesPeranController::class, 'katalog'])->name('katalog');
        Route::get('/kesiapan', [AksesPeranController::class, 'kesiapan'])->name('kesiapan');
        Route::get('/tingkat', [AksesPeranController::class, 'tingkat'])->name('tingkat');
        Route::put('/tingkat/{kode}', [AksesPeranController::class, 'ubahKemampuanTingkat'])->where('kode', '[A-Za-z_]+')->name('tingkat.ubah');
        Route::put('/peran/{id}/tingkat', [AksesPeranController::class, 'ubahTingkatPeran'])->whereNumber('id')->name('peran.tingkat');
        Route::put('/katalog/{grup}/mode', [AksesPeranController::class, 'ubahMode'])->whereNumber('grup')->name('katalog.mode');
        Route::get('/selisih', [AksesPeranController::class, 'selisih'])->name('selisih');
        Route::post('/selisih/bersihkan', [AksesPeranController::class, 'bersihkanSelisih'])->name('selisih.bersihkan');
        Route::post('/sinkron', [AksesPeranController::class, 'sinkron'])->name('sinkron');

        // Fase 3b — tombol ubah (aturan anti-eskalasi di AturanDelegasi).
        Route::post('/penugasan', [AksesPeranController::class, 'tugaskan'])->name('penugasan.tugaskan');
        Route::post('/penugasan/massal', [AksesPeranController::class, 'tugaskanMassal'])->name('penugasan.massal');
        Route::put('/penugasan/{id}/batas', [AksesPeranController::class, 'ubahBatas'])->whereNumber('id')->name('penugasan.batas');
        Route::post('/penugasan/{id}/cabut', [AksesPeranController::class, 'cabutPenugasan'])->whereNumber('id')->name('penugasan.cabut');
        Route::post('/grant', [AksesPeranController::class, 'beriGrant'])->name('grant.beri');
        Route::post('/grant/cabut', [AksesPeranController::class, 'cabutGrant'])->name('grant.cabut');
        Route::put('/peran/{id}/hak', [AksesPeranController::class, 'ubahHak'])->whereNumber('id')->name('peran.hak');
        Route::post('/pratinjau', [AksesPeranController::class, 'pratinjau'])->name('pratinjau');

        // Template cakupan bernama (DDL-2026-184).
        Route::get('/cakupan-template', [AksesPeranController::class, 'templateCakupan'])->name('cakupan-template');
        Route::post('/cakupan-template', [AksesPeranController::class, 'simpanTemplateCakupan'])->name('cakupan-template.buat');
        Route::put('/cakupan-template/{id}', [AksesPeranController::class, 'simpanTemplateCakupan'])->whereNumber('id')->name('cakupan-template.ubah');
        Route::post('/cakupan-template/{id}/nonaktif', [AksesPeranController::class, 'nonaktifkanTemplateCakupan'])->whereNumber('id')->name('cakupan-template.nonaktif');
    });
});
