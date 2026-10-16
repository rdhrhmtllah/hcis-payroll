<?php

use App\Http\Controllers\SYSTEM\TaskProcessing\TaskOutboxDispatcherController;
use App\Http\Controllers\SYSTEM\TaskProcessing\TaskOutboxHandlerController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/internal/task-outbox')
    ->middleware(['outbox.dispatcher.auth'])
    ->name('internal.task-outbox.')
    ->group(function () {
        Route::post('/dispatch', [TaskOutboxDispatcherController::class, 'dispatch'])
            ->name('dispatch');
    });

Route::post('/v1/internal/task-outbox/execute', [TaskOutboxHandlerController::class, 'execute'])
    ->middleware(['outbox.handler.auth'])
    ->name('internal.task-outbox.execute');
