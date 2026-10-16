<?php

use App\Http\Controllers\SYSTEM\Application\Maintenance\ClientStateRecoveryController;
use Illuminate\Support\Facades\Route;

// Jaring pengaman: SENGAJA di luar grup 'auth'. Halaman ini dipakai justru saat
// orang tidak bisa masuk atau tersangkut di lapisan logout, jadi mensyaratkan
// login akan membatalkan gunanya. Tidak ada data server yang disentuh — hanya
// penyimpanan browser milik orang itu sendiri.
Route::get('/pulihkan', [ClientStateRecoveryController::class, 'index'])->name('sistem.pulihkan');
