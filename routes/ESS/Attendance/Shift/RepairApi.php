<?php

use App\Http\Controllers\ESS\Attendance\Shift\Repair\StatusHariTanggalSelesaiBackfillController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/internal/ess/attendance/shift/status-hari/backfill-tanggal-selesai', [
    StatusHariTanggalSelesaiBackfillController::class,
    'run',
])
    ->middleware('status-hari.backfill.auth')
    ->name('internal.ess.attendance.shift.status-hari.backfill-tanggal-selesai');
