<?php

use App\Http\Controllers\SYSTEM\Integration\OutboundContractController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SYSTEM — Portal Kontrak Integrasi Outbound (halaman internal)
|--------------------------------------------------------------------------
|
| Halaman Inertia biasa di dalam shell aplikasi. Pasangannya, viewer publik
| untuk vendor, tetap dokumen Blade mandiri di routes/SYSTEM/Integration/
| OutboundContracts.php — dua permukaan, dua bentuk, dan itu memang disengaja.
|
| Gerbangnya `system.admin`, sama seperti routes/SYSTEM/MasterData/Entitas.php
| dan routes/SYSTEM/DatabaseChanges.php, dan BUKAN check.jabatan: grup fitur
| SYSTEM tidak dipegang satu pun role di N_HRIS_Role_Functional, sehingga
| check.jabatan di sini akan jadi gerbang yang tak seorang pun bisa lewati.
| Di repo ini "System Admin" adalah flag KPI_Users.Is_System_Admin, bukan role.
| Konsekuensinya: halaman ini TIDAK muncul di menu dan tidak butuh baris
| HRIS_Page_Access — ia dibuka lewat alamatnya.
|
| Halaman ini sempat berupa Blade mandiri di /api/contracts. Menaruh halaman
| non-Inertia di balik `auth` DI DALAM aplikasi Inertia membuka satu jalur yang
| tidak kelihatan sampai dicoba: setelah login, redirect()->intended() diikuti
| oleh KLIEN Inertia sebagai XHR yang masih membawa header X-Inertia. Respons
| HTML polos bukan respons Inertia, dan klien menampilkannya di dalam modal
| galat. Bentuk halaman ini — Inertia, bukan Blade — itulah perbaikannya.
|
*/

Route::middleware(['auth', 'system.admin'])
    ->prefix('system/integrasi/kontrak-outbound')
    ->name('system.integrasi.kontrak-outbound.')
    ->group(function () {
        Route::get('/', [OutboundContractController::class, 'halaman'])->name('page');
    });
