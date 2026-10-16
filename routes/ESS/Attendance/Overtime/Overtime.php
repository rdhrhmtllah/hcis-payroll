<?php

use App\Http\Controllers\ESS\Attendance\Overtime\Management\OvertimeController;
use Illuminate\Support\Facades\Route;

/*
 * `auth` dideklarasikan di sini, bukan diwariskan dari posisi `require` berkas
 * ini di routes/web.php. Ketergantungan itu pernah gagal: satu berkas route
 * yang ter-require dua kali membuat registrasi terakhir menang dan seluruh
 * endpoint di bawahnya berjalan tanpa autentikasi.
 */
Route::get('/overtime2', [OvertimeController::class, 'index2'])
    ->middleware(['auth'])
    ->name('overtime.index2');
