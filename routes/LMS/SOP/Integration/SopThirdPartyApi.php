<?php

use App\Http\Controllers\LMS\SOP\Integration\SopKuisCallbackController;
use Illuminate\Support\Facades\Route;

/*
| SOP — Callback pihak ke-3 (HC Learn). Dijaga hris.api.guard (HMAC + nonce +
| whitelist N_HRIS_Api_*). Full path: /api/v1/hris/third-party/sop/exam/complete
*/
Route::middleware('hris.api.guard')->prefix('v1/hris/third-party/sop')->group(function () {
    Route::post('/exam/complete', [SopKuisCallbackController::class, 'completeExam']);
});
