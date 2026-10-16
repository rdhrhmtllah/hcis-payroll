<?php

use App\Http\Controllers\LMS\Training\Content\AdminTraining\AdminTrainingController;
use Illuminate\Support\Facades\Route;

Route::prefix('lms/admin/training')
    ->middleware(['auth', 'access.context:admin', 'check.jabatan:LMSAdminTrainingPage'])
    ->group(function () {
        // Pages
        Route::get('/', [AdminTrainingController::class, 'index']);
        Route::get('/create', [AdminTrainingController::class, 'create']);
        Route::get('/edit/{id}', [AdminTrainingController::class, 'edit']);

        // API
        Route::get('/list', [AdminTrainingController::class, 'getList']);
        Route::get('/master-data', [AdminTrainingController::class, 'getMasterData']);
        Route::get('/show/{id}', [AdminTrainingController::class, 'show']);
        Route::post('/store', [AdminTrainingController::class, 'store']);
        Route::put('/update/{id}', [AdminTrainingController::class, 'update']);
        Route::post('/destroy', [AdminTrainingController::class, 'destroy']);
        Route::get('/preview-konten/{kontenId}', [AdminTrainingController::class, 'previewKonten']);
    });
