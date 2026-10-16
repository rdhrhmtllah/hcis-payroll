<?php

use App\Http\Controllers\KPI\PerformanceAppraisal\Leader\PaLeaderController;
use Illuminate\Support\Facades\Route;

/*
 * Sisi leader Performance Appraisal (L01 dan L02).
 *
 * Kunci halamannya `PerformanceAppraisalLeaderPage`.
 *
 * KENAPA ADA `{participantId}` DI SINI PADAHAL DI SISI PESERTA DILARANG
 *
 * Karena pertanyaannya memang berbeda. Peserta hanya boleh melihat dirinya, jadi
 * menerima id sama saja membuka pintu tanpa guna. Leader justru harus menyebut anggota
 * mana yang sedang dilihat. Yang menjaganya adalah pemeriksaan keanggotaan di
 * `PaLeaderService::detailAnggota()` — bukan rute ini, dan bukan controllernya.
 *
 * Dua batas yang berlaku di seluruh alamat berkas ini, dan keduanya dinyatakan dalam
 * balasan (`batas`) supaya layar tidak perlu menebaknya:
 *   1. angka hanya tampak untuk hasil TERBIT;
 *   2. tidak pernah ada nilai per penilai, dan rincian per kategori disembunyikan bila
 *      pengisinya di bawah `PaLeaderService::MIN_PENILAI_UNTUK_TAMPIL`.
 */
Route::middleware(['auth', 'check.jabatan:PerformanceAppraisalLeaderPage'])->group(function () {
    Route::get('/performance-appraisal/tim', [PaLeaderController::class, 'index'])
        ->name('performance-appraisal.tim');

    Route::prefix('api/v1/performance-appraisal/leader')->group(function () {
        Route::get('/tim', [PaLeaderController::class, 'tim']);
        Route::get('/anggota/{participantId}', [PaLeaderController::class, 'detailAnggota'])
            ->whereNumber('participantId');
    });
});
