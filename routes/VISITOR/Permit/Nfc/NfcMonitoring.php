<?php

use App\Http\Controllers\VISITOR\Permit\Nfc\NfcMonitoringController;
use Illuminate\Support\Facades\Route;

/*
| Monitoring Master & Pairing ID Card NFC (N_EMI_Master_NFC & N_EMI_Binding_NFC).
| Halaman administrasi kartu untuk admin Visitor dan IT.
*/
Route::middleware(['auth', 'check.jabatan:visitorNfcMonitoringPage'])->group(function () {
    Route::get('/admin/visitor/nfc', [NfcMonitoringController::class, 'index'])->name('visitor.nfc.page');
    Route::get('/admin/visitor/nfc-data', [NfcMonitoringController::class, 'data'])->name('visitor.nfc.data');
    Route::get('/admin/visitor/nfc-suggest-karyawan', [NfcMonitoringController::class, 'suggestKaryawan'])->name('visitor.nfc.suggest');
    Route::post('/admin/visitor/nfc-import-preview', [NfcMonitoringController::class, 'pratinjauImport'])->name('visitor.nfc.import-preview');
    Route::post('/admin/visitor/nfc-import-apply', [NfcMonitoringController::class, 'eksekusiImport'])->name('visitor.nfc.import-apply');
    Route::post('/admin/visitor/nfc-manual', [NfcMonitoringController::class, 'simpanManual'])->name('visitor.nfc.manual');
    Route::post('/admin/visitor/nfc-toggle-binding/{id}', [NfcMonitoringController::class, 'toggleBinding'])->name('visitor.nfc.toggle-binding');
    Route::post('/admin/visitor/nfc-toggle-validasi/{id}', [NfcMonitoringController::class, 'toggleValidasi'])->name('visitor.nfc.toggle-validasi');
});
