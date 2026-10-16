<?php

use App\Http\Controllers\ESS\Organization\Team\KaryawanTimController;
use Illuminate\Support\Facades\Route;

// Karyawan Team

// Route::middleware(['auth', 'check.jabatan:adminKPIKaryawanTeamPage'])->group(function() {
// Route::get('/kpi-karyawan-team', [KpiKaryawanTeamController::class, 'index']);
// Route::prefix('/api/v1/kpi-karyawan-team')->group(function () {
//         Route::get('/current', [KpiKaryawanTeamController::class, 'getDataCurrent']);
//         Route::get('/getAllKaryawan', [KpiKaryawanTeamController::class, 'getAllKaryawan']);
// });
// });
// Karyawan Team Management
Route::middleware(['auth', 'check.jabatan:adminKPIKaryawanTeamPage'])->group(function () {
    Route::get('/manajemen-karyawan-tim-kpi', [KaryawanTimController::class, 'index']);
    Route::prefix('/api/v1/karyawan-tim')->group(function () {
        Route::get('/members', [KaryawanTimController::class, 'getTeamMembers']);
        Route::get('/leaders', [KaryawanTimController::class, 'getTeamLeaders']);
        Route::get('/available-members', [KaryawanTimController::class, 'getAvailableMembers']);
        Route::get('/team-details/{leaderId}', [KaryawanTimController::class, 'getTeamDetails']);
        Route::get('/stats', [KaryawanTimController::class, 'getTeamStats']);
        Route::get('/search-employees', [KaryawanTimController::class, 'searchEmployees']);

        Route::post('/assign-member', [KaryawanTimController::class, 'assignMemberToTeam']);
        Route::post('/bulk-transfer', [KaryawanTimController::class, 'bulkTransferMembers']);
        Route::post('/remove-member', [KaryawanTimController::class, 'removeMemberFromTeam']);
        Route::post('/bulk-remove-members', [KaryawanTimController::class, 'bulkRemoveMembersFromTeam']);
    });
});
