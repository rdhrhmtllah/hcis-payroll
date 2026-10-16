<?php

use App\Http\Controllers\LMS\Training\Scheduling\LmsPenjadwalanTrainingApi;
use Illuminate\Support\Facades\Route;

Route::prefix('lmspenjadwalantraining')->group(function () {
    Route::apiResource('lmspenjadwalantraining', LmsPenjadwalanTrainingApi::class);
});
