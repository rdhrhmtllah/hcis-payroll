<?php

use App\Http\Controllers\VISITOR\Permit\VehiclePermit\VehiclePermitController;
use App\Http\Controllers\VISITOR\Permit\VehiclePermit\VerifikasiKartuPermitController;
use Illuminate\Support\Facades\Route;

// visitorPermitPage: self-service, di-set Is_Global_Menu='Y' (lihat database/visitor seeder DDL)
// supaya semua karyawan tetap bisa ajukan permit sendiri.
Route::middleware(['auth', 'check.jabatan:visitorPermitPage'])->group(function () {
    // only(): controller cuma implement index/store/show/destroy - create/edit/update tidak ada,
    // resource() penuh akan generate route ke method yang tidak eksis (fatal error jika dipanggil).
    Route::resource('transaksipermit', VehiclePermitController::class)->only(['index', 'store', 'show']);
    Route::post('transaksipermit/{id}/cancel', [VehiclePermitController::class, 'cancel'])->whereNumber('id');
    Route::get('transaksipermit-list', [VehiclePermitController::class, 'getList']);
    Route::get('transaksipermit-my', [VehiclePermitController::class, 'getMyPermits']);
    Route::get('transaksipermit-stats', [VehiclePermitController::class, 'getStats']);
    Route::get('transaksipermit-master', [VehiclePermitController::class, 'getMasterData']);
    // Jadwal inspeksi kendaraan (DDL-2026-123).
    Route::get('transaksipermit-slot-inspeksi', [VehiclePermitController::class, 'slotInspeksi']);
    Route::post('transaksipermit/{id}/pindah-inspeksi', [VehiclePermitController::class, 'pindahInspeksi'])->whereNumber('id');
    Route::get('transaksipermit/{id}/kartu-pdf', [VehiclePermitController::class, 'kartuPdf'])->whereNumber('id');
    Route::post('transaksipermit-kartu-pdf-batch', [VehiclePermitController::class, 'kartuPdfBatch']);
});

/*
| Cek keaslian kartu dari QR yang dipindai di dalam HCIS.
|
| Sengaja HANYA 'auth', tanpa kunci halaman: tombolnya dipakai dari empat layar
| berbeda (Pass Digital & pengajuan milik karyawan, Verifikator Permit, Agenda
| Inspeksi SHE, Monitoring Permit) yang kuncinya berbeda-beda. Yang membatasi
| bukan kunci halaman melainkan tokennya sendiri — jawaban hanya keluar untuk
| token bertanda tangan yang memang tercetak pada sebuah kartu.
*/
Route::middleware(['auth'])->group(function () {
    Route::post('transaksipermit-verifikasi-kartu', VerifikasiKartuPermitController::class);
});
