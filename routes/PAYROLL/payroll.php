<?php

use App\Http\Controllers\PAYROLL\Dashboard\PayrollDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [PayrollDashboardController::class, 'index'])->name('payroll.dashboard');
    Route::get('/payroll', [PayrollDashboardController::class, 'index'])->name('payroll.index');
});
