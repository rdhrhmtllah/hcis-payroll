<?php

use App\Http\Controllers\LMS\Integration\LmsThirdPartyCallbackController;
use Illuminate\Support\Facades\Route;

Route::middleware('lms.api.guard')->prefix('v1/lms/third-party')->group(function () {
    Route::post('/exam/complete', [LmsThirdPartyCallbackController::class, 'completeExam']);
});
