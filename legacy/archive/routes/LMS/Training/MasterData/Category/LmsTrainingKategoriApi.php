<?php

use App\Http\Controllers\LMS\Training\MasterData\Category\LmsTrainingKategoriApi;
use Illuminate\Support\Facades\Route;

Route::prefix('lmstrainingkategori')->group(function () {
    Route::apiResource('lmstrainingkategori', LmsTrainingKategoriApi::class);
});
