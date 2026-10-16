<?php

use App\Http\Controllers\VISITOR\Permit\Inspection\AgendaInspeksiController;
use Illuminate\Support\Facades\Route;

/*
| Agenda Inspeksi SHE (DDL-2026-123): agenda harian inspeksi kendaraan permit
| karyawan + kedatangan tamu B2B, serta pengaturan pola slot. Kunci halamannya
| diturunkan dari pemegang kunci Verifikator Permit.
*/
Route::middleware(['auth', 'check.jabatan:visitorAgendaInspeksiPage'])->group(function () {
    Route::get('agenda-inspeksi', [AgendaInspeksiController::class, 'index']);
    Route::get('agenda-inspeksi-data', [AgendaInspeksiController::class, 'data']);
    Route::get('agenda-inspeksi-belum-dicatat', [AgendaInspeksiController::class, 'belumDicatat']);
    Route::post('agenda-inspeksi/{noTransaksi}/tidak-hadir', [AgendaInspeksiController::class, 'tidakHadir']);
    Route::post('agenda-inspeksi/{noTransaksi}/pindah', [AgendaInspeksiController::class, 'pindah']);
    // Tamu B2B tidak punya slot: yang dicatat hasil kedatangannya di pos gerbang.
    Route::post('agenda-inspeksi/{noTransaksi}/kedatangan-tamu', [AgendaInspeksiController::class, 'catatKedatanganTamu']);
    Route::get('agenda-inspeksi-slot', [AgendaInspeksiController::class, 'slot']);
    Route::get('agenda-inspeksi-pola', [AgendaInspeksiController::class, 'pola']);
    Route::post('agenda-inspeksi-hari', [AgendaInspeksiController::class, 'ubahStatusHari']);
    Route::post('agenda-inspeksi-shift', [AgendaInspeksiController::class, 'simpanShift']);
    Route::post('agenda-inspeksi-shift/{id}/status', [AgendaInspeksiController::class, 'ubahStatusShift']);
    Route::post('agenda-inspeksi-shift/{id}/hapus', [AgendaInspeksiController::class, 'hapusShift']);
    Route::post('agenda-inspeksi-pos', [AgendaInspeksiController::class, 'simpanPos']);
    Route::post('agenda-inspeksi-pos/{id}/status', [AgendaInspeksiController::class, 'ubahStatusPos']);
    Route::post('agenda-inspeksi-pos/{id}/hapus', [AgendaInspeksiController::class, 'hapusPos']);
    Route::post('agenda-inspeksi-pola', [AgendaInspeksiController::class, 'simpanPola']);
    Route::post('agenda-inspeksi-pengecualian', [AgendaInspeksiController::class, 'simpanPengecualian']);
    Route::post('agenda-inspeksi-pengecualian/{id}/hapus', [AgendaInspeksiController::class, 'hapusPengecualian']);
});
