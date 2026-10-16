<?php

use App\Http\Controllers\VISITOR\Permit\VehiclePermit\TransaksiPermitApi;
use Illuminate\Support\Facades\Route;

// Route dinonaktifkan: TransaksiPermitApi adalah stub kosong (tidak ada method sama sekali),
// tapi tetap terdaftar sebagai apiResource aktif -> setiap panggilan berujung 500 (BadMethodCallException).
// Tidak ada referensi ke /api/transaksipermit di frontend (dikonfirmasi grep resources/js). Class controller
// tetap dipertahankan (permintaan eksplisit sebelumnya: "jangan hapus transaksi permit api"), hanya route-nya
// yang ditutup untuk menutup attack-surface 500 publik yang tidak dipakai siapapun.
// Route::prefix('api')->group(function() {
//     Route::apiResource('transaksipermit', TransaksiPermitApi::class)->names('api.transaksipermit');
// });
