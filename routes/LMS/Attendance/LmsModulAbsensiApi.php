<?php

use App\Http\Controllers\LMS\Attendance\LmsModulAbsensiApi;
use Illuminate\Support\Facades\Route;

/*
 * `auth` dideklarasikan di sini, bukan diwariskan dari posisi `require` berkas
 * ini di routes/web.php. Ketergantungan itu pernah gagal: satu berkas route
 * yang ter-require dua kali membuat registrasi terakhir menang dan seluruh
 * endpoint di bawahnya berjalan tanpa autentikasi.
 */
Route::prefix('api')->middleware(['auth'])->group(function () {
    Route::apiResource('lmsmodulabsensi', LmsModulAbsensiApi::class)->names('api.lmsmodulabsensi');
});
