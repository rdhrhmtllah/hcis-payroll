<?php

use App\Http\Controllers\ESS\Attendance\Calendar\Operational\KalenderOperasionalApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ESS — Kalender operasional (API pihak ke-3)
|--------------------------------------------------------------------------
|
| Dikonsumsi Web Careers untuk menghitung SLA rekrutmen dalam hari kerja.
| Dijaga hris.api.guard (HMAC-SHA512 + nonce + whitelist endpoint).
|
| Pendaftaran aplikasi & endpoint ada di DML-2026-075-registrasi-api-kalender-ho.
| Url_Route di N_HRIS_Api_Endpoint WAJIB berisi POLA rute — termasuk "{tahun}",
| bukan tahun konkret — karena whitelist dicocokkan ke route()->uri().
|
| Spec: docs/superpowers/specs/2026-09-04-api-kalender-operasional-ho-design.md
|
*/

Route::middleware('hris.api.guard')->prefix('v1/hcis/kalender-operasional')->group(function () {
    Route::get('/tahun/{tahun}', [KalenderOperasionalApiController::class, 'tahun']);
    Route::get('/', [KalenderOperasionalApiController::class, 'rentang']);
});
