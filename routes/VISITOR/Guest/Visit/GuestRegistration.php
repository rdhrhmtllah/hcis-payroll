<?php

use App\Http\Controllers\VISITOR\Guest\Visit\GuestRegistrationController;
use Illuminate\Support\Facades\Route;

// visitorTamuPage: self-service, di-set Is_Global_Menu='Y' (lihat database/visitor seeder DDL)
// supaya semua karyawan tetap bisa registrasi tamu sendiri.
// Tipe tamu DITETAPKAN RUTE (visitor.tipe-tamu), bukan dikirim klien. URL lama
// menjadi halaman Tamu Regular; Tamu B2B punya salinan rute dengan kunci
// halamannya sendiri (DDL-2026-121). Controller-nya sama, penyaringnya membaca
// tipe dari rute — lihat TipeTamu.
Route::middleware(['auth', 'check.jabatan:visitorTamuPage', 'visitor.tipe-tamu:REGULAR'])->group(function () {
    // only(): controller cuma implement index/store/show/update/destroy - create/edit tidak ada.
    Route::resource('transaksitamu', GuestRegistrationController::class)->only(['index', 'store', 'show']);
    Route::post('transaksitamu/cancel/{id}', [GuestRegistrationController::class, 'cancel'])->whereNumber('id');
    Route::get('transaksitamu-stats', [GuestRegistrationController::class, 'getStats']);
    Route::get('transaksitamu-list', [GuestRegistrationController::class, 'getList']);
    Route::get('transaksitamu-master', [GuestRegistrationController::class, 'getMasterData']);
});

Route::middleware(['auth', 'check.jabatan:visitorTamuB2BPage', 'visitor.tipe-tamu:B2B'])->group(function () {
    Route::get('transaksi-tamu-b2b', [GuestRegistrationController::class, 'index']);
    Route::post('transaksi-tamu-b2b', [GuestRegistrationController::class, 'store']);
    Route::post('transaksi-tamu-b2b/cancel/{id}', [GuestRegistrationController::class, 'cancel'])->whereNumber('id');
    Route::get('transaksi-tamu-b2b-stats', [GuestRegistrationController::class, 'getStats']);
    Route::get('transaksi-tamu-b2b-list', [GuestRegistrationController::class, 'getList']);
    Route::get('transaksi-tamu-b2b-master', [GuestRegistrationController::class, 'getMasterData']);
    Route::get('transaksi-tamu-b2b/{id}', [GuestRegistrationController::class, 'show']);
});

/*
| TIDAK ADA route check-in/check-out di sini, dan itu DISENGAJA.
|
| Kehadiran dicatat aplikasi palang di gerbang, yang menulis
| Waktu_Aktual_Masuk/Keluar langsung ke N_VISITOR_Tamu_Efektif. HCIS tidak boleh
| punya jalur tulis tandingan: dua penulis untuk satu fakta melahirkan drift
| tanpa wasit.
|
| Route-nya pernah ada dan dicabut sengaja pada commit d641d350 "Harden Visitor
| lifecycle and approval flow", lengkap dengan penjaganya di
| tests/Feature/VISITOR/VisitorRouteContractTest.php. Jangan mendaftarkannya
| kembali tanpa mengubah keputusan itu lebih dulu.
*/
