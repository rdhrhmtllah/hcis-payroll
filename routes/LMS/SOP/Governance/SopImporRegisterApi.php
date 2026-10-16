<?php

use App\Http\Controllers\LMS\SOP\Governance\SopAdminDokumenImporController;
use Illuminate\Support\Facades\Route;

/*
| Impor register dokumen SOP (Excel) lewat API — Authorization: Bearer.
| form-data: berkas (.xlsx), mode=dry_run|jalankan, mulai, jumlah (maks 50).
| Pelaku dicatat sebagai HCIS_SOP_IMPOR_REGISTER_USER_ID.
*/
Route::post('/v1/sop/dokumen/impor-register', [SopAdminDokumenImporController::class, 'imporRegisterApi'])
    ->middleware('sop.impor-register.auth')
    ->name('api.sop.dokumen.impor-register');
