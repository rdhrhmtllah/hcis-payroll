<?php

use App\Http\Controllers\KPI\Access\Division\UserAksesDivisiController;
use App\Http\Controllers\KPI\Access\Level\UserAksesLevelController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'check.jabatan:ManajemenAksesDivisi'])->group(function () {
    Route::get('/akses-user/divisi', [UserAksesDivisiController::class, 'index']);
    Route::post('/akses-user/store', [UserAksesDivisiController::class, 'store']);
    Route::get('/akses-user/current', [UserAksesDivisiController::class, 'getDataCurrent']);
    Route::get('/akses-user/divisi/list-divisi', [UserAksesDivisiController::class, 'getDataOptionDivisi']);
});
Route::middleware(['auth', 'check.jabatan:ManajemenAksesLevel'])->group(function () {
    Route::get('/akses-user/level', [UserAksesLevelController::class, 'index']);
    Route::post('/akses-user/level/store', [UserAksesLevelController::class, 'store']);
    Route::get('/akses-user/level/current', [UserAksesLevelController::class, 'getDataCurrent']);
    Route::get('/akses-user/level/list-level', [UserAksesLevelController::class, 'getDataOptionLevel']);
});

Route::middleware(['auth', 'check.jabatan:ManajemenAksesLevel,ManajemenAksesDivisi'])->group(function () {
    Route::get('/dashboard-ceo/option/list-user', [UserAksesDivisiController::class, 'getDataOptionUser']);
});
