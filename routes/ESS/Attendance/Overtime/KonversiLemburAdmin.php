<?php

use App\Http\Controllers\ESS\Attendance\Overtime\Conversion\KonversiLemburAdminController;
use Illuminate\Support\Facades\Route;

/*
| Admin HC: pantau & batalkan pengajuan Konversi Lembur (DML-2026-179).
*/
Route::middleware(['auth', 'check.jabatan:konversiLemburAdmin'])
    ->prefix('admin/konversi-lembur')
    ->name('konversi-lembur.admin.')
    ->group(function () {
        Route::get('/', [KonversiLemburAdminController::class, 'index'])->name('index');
        Route::get('/data', [KonversiLemburAdminController::class, 'data'])->name('data');
        Route::get('/detail/{noTransaksi}', [KonversiLemburAdminController::class, 'detail'])->name('detail');
        Route::post('/batal/{noTransaksi}', [KonversiLemburAdminController::class, 'batal'])->name('batal');
    });
