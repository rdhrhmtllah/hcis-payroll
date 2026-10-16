<?php

use App\Http\Controllers\ESS\Attendance\Shift\Request\ShiftRequestController;
use App\Http\Controllers\ESS\Attendance\Shift\Schedule\JejakJadwalHariController;
use Illuminate\Support\Facades\Route;

//

// Self Shift Request Routes
Route::middleware(['auth', 'check.jabatan:selfShift'])
    ->prefix('self-shift')
    ->name('self-shift.')
    ->group(function () {
        Route::get('/shift-for-day', [ShiftRequestController::class, 'shiftForDay'])->name('shift-for-day');
        Route::get('/', [ShiftRequestController::class, 'index'])->name('index');
        Route::get('/schedule', [ShiftRequestController::class, 'getWeekSchedule'])->name('schedule');
        Route::get('/get-hari-swap', [ShiftRequestController::class, 'getHariSwap'])->name('get-hari-swap');
        Route::post('/submit-swap-hari', [ShiftRequestController::class, 'submitSwapHari'])->name('submit-swap-hari');
        Route::get('/shifts', [ShiftRequestController::class, 'getShifts'])->name('shifts');
        Route::get('/locked-dates', [ShiftRequestController::class, 'getLockedDates'])->name('locked-dates');
        Route::get('/off-days', [ShiftRequestController::class, 'getOffDatesRange'])->name('off-days');
        Route::get('/permanent-conflicts', [ShiftRequestController::class, 'getPermanentConflicts'])->name(
            'permanent-conflicts',
        );
        Route::post('/submit', [ShiftRequestController::class, 'submit'])->name('submit');
        Route::get('/stats', [ShiftRequestController::class, 'stats'])->name('stats');
        Route::get('/history', [ShiftRequestController::class, 'history'])->name('history');
        Route::get('/detail/{noTransaksi}', [ShiftRequestController::class, 'detail'])->name('detail');
        Route::post('/cancel/{noTransaksi}', [ShiftRequestController::class, 'cancel'])->name('cancel');
        // "Kenapa jadwal saya begini?" — hanya untuk diri sendiri.
        Route::get('/jejak-hari', [JejakJadwalHariController::class, 'pribadi'])->name('jejak-hari');
    });
