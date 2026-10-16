<?php

use App\Http\Controllers\SYSTEM\Integration\ApiDocsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SYSTEM — Dokumentasi API pihak ketiga (OpenAPI / Swagger UI)
|--------------------------------------------------------------------------
|
| Halaman dan dokumennya SENGAJA tidak dijaga hris.api.guard: browser tidak
| bisa menandatangani permintaan HMAC, dan halaman itu sendiri tidak memuat
| informasi apa pun. Yang dijaga adalah dokumennya, lewat header X-HC-Public.
|
| Kunci tidak pernah diletakkan di URL, supaya tidak masuk riwayat browser
| maupun log akses.
|
*/

Route::prefix('docs')->group(function () {
    Route::get('/', [ApiDocsController::class, 'halaman']);
    Route::get('/openapi.json', [ApiDocsController::class, 'spesifikasi']);
});
