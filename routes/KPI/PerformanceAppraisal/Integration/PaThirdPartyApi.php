<?php

use App\Http\Controllers\KPI\PerformanceAppraisal\Integration\PaThirdPartyCallbackController;
use Illuminate\Support\Facades\Route;

/*
 | Callback pihak ketiga untuk Performance Appraisal.
 |
 | Alamatnya sengaja TIDAK menumpang /v1/lms/third-party. Satu alamat untuk tiga modul
 | memaksa gerbangnya menebak asal kiriman dari token — tiga kueri setiap kali, plus satu
 | cabang untuk token yang cocok di lebih dari satu modul. Prefix sendiri membuat asalnya
 | terbaca dari alamatnya, dan tebakan itu hilang seluruhnya.
 |
 | `pa.api.guard` menjaga rute ini. Ia bukan gerbang baru dari nol: separuh keamanannya
 | disalin apa adanya dari `lms.api.guard`, sehingga bagi HC Learn ini URL berbeda dengan
 | mekanisme tanda tangan yang sama persis.
 |
 | Rute ini berada di routes/api.php yang TIDAK memuat autentikasi sama sekali; seluruh
 | penjagaannya ada di middleware. Menghapus middleware-nya berarti membuka penulisan hasil
 | penilaian ke internet — bukan menonaktifkan sebuah pemeriksaan.
 */
Route::middleware('pa.api.guard')
    ->prefix('v1/pa/third-party')
    ->group(function () {
        Route::post('/penilaian/complete', [PaThirdPartyCallbackController::class, 'complete']);
    });
