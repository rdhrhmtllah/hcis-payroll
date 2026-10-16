<?php

use App\Http\Controllers\SYSTEM\Application\Panduan\PanduanController;
use App\Http\Controllers\SYSTEM\Application\Panduan\PanduanPenggunaController;
use App\Http\Controllers\SYSTEM\Application\Panduan\PanduanVersiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SYSTEM — Manajemen Panduan (manual guide) lintas modul (DDL-2026-146)
|--------------------------------------------------------------------------
|
| Pengelolaan: `system.admin`, seperti halaman SYSTEM lain.
| Pemakaian: cukup `auth` — hak lihat diturunkan dari hak membuka halaman yang
| dituju, diperiksa PanduanService per permintaan (bukan kunci halaman di rute).
|
*/

Route::middleware(['auth', 'system.admin'])
    ->prefix('system/panduan')
    ->name('system.panduan.')
    ->group(function () {
        Route::get('/', [PanduanController::class, 'page'])->name('page');
        Route::get('/data', [PanduanController::class, 'index'])->name('index');
        Route::get('/opsi', [PanduanController::class, 'opsi'])->name('opsi');
        Route::post('/', [PanduanController::class, 'store'])->name('store');
        Route::post('/{id}', [PanduanController::class, 'update'])->whereNumber('id')->name('update');
        Route::patch('/{id}/status', [PanduanController::class, 'status'])->whereNumber('id')->name('status');
        Route::get('/{id}/berkas', [PanduanController::class, 'berkas'])->whereNumber('id')->name('berkas');
        Route::get('/{id}/sasaran', [PanduanController::class, 'sasaran'])->whereNumber('id')->name('sasaran');

        // Versi berkas (DDL-2026-149)
        Route::get('/{id}/versi', [PanduanVersiController::class, 'index'])->whereNumber('id')->name('versi.index');
        Route::post('/{id}/versi', [PanduanVersiController::class, 'store'])->whereNumber('id')->name('versi.store');
        Route::post('/{id}/versi/{idVersi}/aktifkan', [PanduanVersiController::class, 'aktifkan'])->whereNumber(['id', 'idVersi'])->name('versi.aktifkan');
        Route::delete('/{id}/versi/{idVersi}', [PanduanVersiController::class, 'destroy'])->whereNumber(['id', 'idVersi'])->name('versi.destroy');
        Route::get('/{id}/versi/{idVersi}/berkas', [PanduanVersiController::class, 'berkas'])->whereNumber(['id', 'idVersi'])->name('versi.berkas');
    });

Route::middleware(['auth'])
    ->prefix('panduan')
    ->name('panduan.')
    ->group(function () {
        Route::get('/halaman/{jenisPage}', [PanduanPenggunaController::class, 'untukHalaman'])
            ->where('jenisPage', '[A-Za-z0-9_]{1,100}')
            ->name('halaman');
        Route::get('/{id}/berkas', [PanduanPenggunaController::class, 'berkas'])->whereNumber('id')->name('berkas');
    });
