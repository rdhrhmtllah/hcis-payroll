<?php

use App\Http\Controllers\VISITOR\Guest\Visit\TransaksiTamuApi;
use Illuminate\Support\Facades\Route;

// Route dinonaktifkan: TransaksiTamuApi adalah stub kosong, tapi tetap terdaftar sebagai apiResource
// aktif -> setiap panggilan berujung error. Tidak ada referensi ke /api/transaksitamu di frontend
// (dikonfirmasi grep resources/js). Class controller tetap dipertahankan, hanya route-nya yang ditutup.
// Route::prefix('api')->group(function() {
//     Route::apiResource('transaksitamu', TransaksiTamuApi::class)->names('api.transaksitamu');
// });
