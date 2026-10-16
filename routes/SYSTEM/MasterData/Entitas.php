<?php

use App\Http\Controllers\SYSTEM\MasterData\Entitas\EntitasController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SYSTEM — Master Entitas Perusahaan
|--------------------------------------------------------------------------
| Gerbangnya `system.admin`, sama seperti routes/SYSTEM/DatabaseChanges.php,
| dan BUKAN check.jabatan. Grup fitur SYSTEM tidak dipegang satu pun role di
| N_HRIS_Role_Functional, jadi check.jabatan di sini akan menjadi gerbang yang
| tak seorang pun bisa lewati. Di repo ini "System Admin" adalah flag
| KPI_Users.Is_System_Admin, bukan role.
|
| Master ini lintas modul. Modul yang membutuhkannya membaca lewat endpoint
| bergerbang sendiri (mis. /api/v1/sop/admin/master-data/entitas), bukan lewat
| route ini.
*/
Route::middleware(['auth', 'system.admin'])
    ->prefix('system/master-data/entitas')
    ->name('system.master-data.entitas.')
    ->group(function () {
        Route::get('/', [EntitasController::class, 'page'])->name('page');
        Route::get('/data', [EntitasController::class, 'index'])->name('index');
        Route::get('/options', [EntitasController::class, 'options'])->name('options');
        Route::get('/pohon', [EntitasController::class, 'pohon'])->name('pohon');
        Route::post('/store', [EntitasController::class, 'store'])->name('store');
        Route::put('/update/{id}', [EntitasController::class, 'update'])->name('update');
        Route::delete('/delete/{id}', [EntitasController::class, 'destroy'])->name('destroy');
    });
