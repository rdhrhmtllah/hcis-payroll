<?php

use App\Http\Controllers\SYSTEM\MasterData\KontakBantuan\KontakBantuanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SYSTEM — Kontak bantuan untuk Pusat Bantuan (DDL-2026-144)
|--------------------------------------------------------------------------
|
| Pengelolaan kontak ada di halaman master modul masing-masing (mis. Master
| Data Permit). Rute ini hanya membaca, untuk semua pengguna yang login.
|
*/

Route::middleware(['auth'])
    ->get('/bantuan/kontak', [KontakBantuanController::class, 'pusatBantuan'])
    ->name('bantuan.kontak');
