<?php

use App\Http\Controllers\VISITOR\Permit\VehiclePermit\PermitTamuApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| VISITOR — Permit COKLAT untuk aplikasi mitra B2B (API pihak ke-3)
|--------------------------------------------------------------------------
|
| Dikonsumsi aplikasi mitra yang sudah mendaftarkan tamunya sendiri dan hanya
| membutuhkan izin akses gerbang dari HCIS.
|
| Dijaga hris.api.guard (HMAC-SHA512 + nonce sekali pakai + whitelist endpoint
| per aplikasi). Ini rute TULIS pertama di lapisan itu; rute tulis yang lupa
| dijaga berarti siapa pun di internet dapat menerbitkan izin gerbang.
|
| Pendaftaran endpoint ada di DML-nya. Url_Route di N_HRIS_Api_Endpoint WAJIB
| berisi POLA rute — termasuk "{no_transaksi}", bukan nomor konkret — karena
| whitelist dicocokkan ke route()->uri().
|
| Spec: docs/superpowers/specs/2026-09-08-api-b2b-permit-coklat-design.md
|
*/

Route::middleware('hris.api.guard')->prefix('v1/hcis/visitor/permit-tamu')->group(function () {
    Route::post('/', [PermitTamuApiController::class, 'ajukan']);
    Route::get('/{no_transaksi}', [PermitTamuApiController::class, 'status']);
    Route::post('/{no_transaksi}/batal', [PermitTamuApiController::class, 'batal']);
});
