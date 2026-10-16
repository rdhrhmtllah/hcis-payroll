<?php

use App\Http\Controllers\SYSTEM\MasterData\Departemen\DepartemenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SYSTEM — Master Kode Departemen
|--------------------------------------------------------------------------
| Gerbangnya `system.admin`, sama seperti Master Entitas dan Database Change
| Dashboard. Master ini lintas modul; modul yang membutuhkannya membaca lewat
| endpoint bergerbang sendiri, bukan lewat route ini.
*/
Route::middleware(['auth', 'system.admin'])
    ->prefix('system/master-data/departemen')
    ->name('system.master-data.departemen.')
    ->group(function () {
        Route::get('/', [DepartemenController::class, 'page'])->name('page');
        Route::get('/data', [DepartemenController::class, 'index'])->name('index');
        Route::get('/sub-divisi', [DepartemenController::class, 'opsiSubDivisi'])->name('sub-divisi');
        Route::post('/store', [DepartemenController::class, 'store'])->name('store');
        Route::put('/update/{id}', [DepartemenController::class, 'update'])->name('update');
        Route::delete('/delete/{id}', [DepartemenController::class, 'destroy'])->name('destroy');
    });
