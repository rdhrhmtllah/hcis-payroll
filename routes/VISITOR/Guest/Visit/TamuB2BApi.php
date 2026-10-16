<?php

use App\Http\Controllers\VISITOR\Guest\Visit\TamuB2BApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| VISITOR — Registrasi tamu B2B untuk aplikasi mitra (API pihak ke-3)
|--------------------------------------------------------------------------
|
| Dikonsumsi aplikasi mitra yang ingin MENDAFTARKAN tamunya ke HCIS, bukan
| sekadar meminta izin gerbang. Sengaja terpisah dari v1/hcis/visitor/permit-tamu:
| yang itu menerbitkan permit tanpa baris kunjungan, yang ini membuat kunjungan
| tamu B2B lengkap beserta alur approval dan permit COKLAT-nya.
|
| Dijaga hris.api.guard (HMAC-SHA512 + nonce sekali pakai + whitelist endpoint
| per aplikasi). Rute TULIS yang lupa dijaga berarti siapa pun di internet dapat
| mendaftarkan tamu dan menerbitkan izin gerbang.
|
| Pendaftaran endpoint ada di DML-nya. Url_Route di N_HRIS_Api_Endpoint WAJIB
| berisi POLA rute — termasuk "{no_transaksi}", bukan nomor konkret — karena
| whitelist dicocokkan ke route()->uri().
|
*/

Route::middleware('hris.api.guard')->prefix('v1/hcis/visitor/tamu-b2b')->group(function () {
    Route::post('/', [TamuB2BApiController::class, 'ajukan']);
    Route::get('/{no_transaksi}', [TamuB2BApiController::class, 'status']);
});
