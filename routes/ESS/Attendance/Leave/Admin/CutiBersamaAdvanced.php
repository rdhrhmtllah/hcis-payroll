<?php

use App\Http\Controllers\ESS\Attendance\Leave\Admin\CutiBersamaAdvancedController;
use Illuminate\Support\Facades\Route;

// =========================================================================
// CUTI BERSAMA ADVANCED
// =========================================================================
Route::middleware(['auth', 'check.jabatan:adminHCCutiBersamaPage'])->group(function () {
    // Page render
    Route::get('/admin/cuti-bersama', [CutiBersamaAdvancedController::class, 'index'])->name('cuti-bersama-adv.index');

    // API routes
    Route::prefix('api/v1/cuti-bersama-adv')
        ->name('cuti-bersama-adv.api.')
        ->group(function () {
            // CRUD
            Route::get('/list', [CutiBersamaAdvancedController::class, 'list'])->name('list');
            Route::post('/store', [CutiBersamaAdvancedController::class, 'store'])->name('store');
            Route::put('/update/{id}', [CutiBersamaAdvancedController::class, 'update'])->name('update');
            Route::delete('/delete/{id}', [CutiBersamaAdvancedController::class, 'delete'])->name('delete');

            // Detail & Filter
            Route::get('/detail/{id}', [CutiBersamaAdvancedController::class, 'detail'])->name('detail');
            Route::get('/filter-options', [CutiBersamaAdvancedController::class, 'filterOptions'])->name(
                'filter-options',
            );

            // Exclusion
            Route::post('/exclusion/{id}', [CutiBersamaAdvancedController::class, 'addExclusion'])->name(
                'exclusion.add',
            );
            Route::delete('/exclusion/{id}/{kode}', [CutiBersamaAdvancedController::class, 'removeExclusion'])->name(
                'exclusion.remove',
            );
            Route::post('/exclusion/bulk-add/{id}', [CutiBersamaAdvancedController::class, 'bulkAddExclusions'])->name(
                'exclusion.bulk-add',
            );
            Route::post('/exclusion/bulk-remove/{id}', [
                CutiBersamaAdvancedController::class,
                'bulkRemoveExclusions',
            ])->name('exclusion.bulk-remove');

            // Search Karyawan
            Route::get('/search-karyawan', [CutiBersamaAdvancedController::class, 'searchKaryawan'])->name(
                'search-karyawan',
            );

            // Preview & Execute
            Route::post('/preview', [CutiBersamaAdvancedController::class, 'preview'])->name('preview');
            Route::post('/execute/{id}', [CutiBersamaAdvancedController::class, 'execute'])->name('execute');
            Route::post('/cancel-execute/{id}', [CutiBersamaAdvancedController::class, 'cancelExecute'])->name(
                'cancel-execute',
            );

            // Rollback
            Route::get('/rollback-options/{id}', [CutiBersamaAdvancedController::class, 'rollbackOptions'])->name(
                'rollback-options',
            );
            Route::post('/rollback/{id}', [CutiBersamaAdvancedController::class, 'rollback'])->name('rollback');

            // Cloud Task Logs
            Route::get('/cloud-task-logs/{id}', [CutiBersamaAdvancedController::class, 'cloudTaskLogs'])->name(
                'cloud-task-logs',
            );
        });
});
