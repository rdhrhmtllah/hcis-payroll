<?php

use App\Http\Controllers\VISITOR\Workflow\Verification\VerifikatorVisitorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| VISITOR — Meja Verifikator Permit & Tamu
|--------------------------------------------------------------------------
|
| Meja gabungan /verifikator-visitor dipecah menjadi dua halaman (DML-2026-105).
| Pemisahannya menegakkan SIAPA BOLEH MENYETUJUI APA, bukan sekadar kerapian
| navigasi: setiap aksi keputusan dijaga kunci domainnya sendiri, sehingga
| pemegang kunci tamu tidak bisa menyetujui permit kendaraan — termasuk dengan
| memanggil endpointnya langsung.
|
| Statistik dibaca kedua halaman, jadi ia dijaga kedua kunci (makna ATAU).
|
| Kunci halamannya ditulis sebagai string, BUKAN const: berkas rute dapat
| dimuat lebih dari sekali dalam satu proses, dan const di lingkup global
| membuat pemuatan kedua mati dengan "Constant already defined".
*/

Route::middleware(['auth'])->group(function () {
    // URL lama. Notifikasi yang sudah terkirim menautkan ke sini; controllernya
    // mengalihkan ke domain yang benar berdasarkan `jenis` pada query string.
    // Tidak dijaga kunci lama supaya orang yang sudah dipindahkan ke kunci baru
    // tidak justru terkunci di pintu depannya.
    Route::get('verifikator-visitor', [VerifikatorVisitorController::class, 'index']);
});

Route::middleware([
    'auth',
    'check.jabatan:visitorVerifikatorPermitPage,visitorVerifikatorTamuPage',
])->group(function () {
    Route::get('verifikator-visitor-stats', [VerifikatorVisitorController::class, 'getStats']);
});

// ─── Permit ─────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'check.jabatan:visitorVerifikatorPermitPage'])->group(function () {
    Route::get('verifikator-visitor/permit', [VerifikatorVisitorController::class, 'indexPermit']);

    Route::get('verifikator-permit-list', [VerifikatorVisitorController::class, 'getPermitList']);
    Route::get('verifikator-permit/{id}', [VerifikatorVisitorController::class, 'showPermit']);
    Route::post('verifikator-permit/{noTransaksi}/approve', [VerifikatorVisitorController::class, 'approvePermit']);
    Route::post('verifikator-permit/{noTransaksi}/reject', [VerifikatorVisitorController::class, 'rejectPermit']);
    Route::post('verifikator-permit/{noTransaksi}/revoke', [VerifikatorVisitorController::class, 'revokePermit']);
    Route::post('verifikator-permit-bulk-revoke', [VerifikatorVisitorController::class, 'bulkRevokePermit']);
    Route::post('verifikator-permit/{noTransaksi}/validity', [VerifikatorVisitorController::class, 'setPermitValidity']);
    Route::post('verifikator-permit-checklist/{noTransaksi}', [VerifikatorVisitorController::class, 'updatePermitChecklist']);
    Route::post('verifikator-permit/{noTransaksi}/koreksi', [VerifikatorVisitorController::class, 'koreksiPermit']);
    Route::get('verifikator-permit/{noTransaksi}/koreksi/pratinjau-checklist', [VerifikatorVisitorController::class, 'pratinjauKoreksiChecklist']);
    Route::get('verifikator-permit-koreksi-master', [VerifikatorVisitorController::class, 'masterKoreksiPermit']);
    Route::post('verifikator-permit-upload-dokumen', [VerifikatorVisitorController::class, 'uploadPermitDokumen']);
});

// ─── Tamu ───────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'check.jabatan:visitorVerifikatorTamuPage', 'visitor.tipe-tamu:REGULAR'])->group(function () {
    Route::get('verifikator-visitor/tamu', [VerifikatorVisitorController::class, 'indexTamu']);

    Route::get('verifikator-tamu-list', [VerifikatorVisitorController::class, 'getTamuList']);
    Route::get('verifikator-tamu/{id}', [VerifikatorVisitorController::class, 'showTamu']);
    Route::post('verifikator-tamu/{noTransaksi}/approve', [VerifikatorVisitorController::class, 'approveTamu']);
    Route::post('verifikator-tamu/{noTransaksi}/reject', [VerifikatorVisitorController::class, 'rejectTamu']);
    Route::post('verifikator-tamu/{noTransaksi}/revoke', [VerifikatorVisitorController::class, 'revokeTamu']);
});

/*
| Meja verifikator Tamu B2B (DDL-2026-121). Method-nya sama dengan meja tamu
| Regular; tipe ditetapkan rute, dan setiap aksi menolak (404) nomor transaksi
| bertipe lain — pemegang kunci Regular tidak bisa menyetujui kunjungan B2B.
*/
Route::middleware(['auth', 'check.jabatan:visitorVerifikatorTamuB2BPage', 'visitor.tipe-tamu:B2B'])->group(function () {
    Route::get('verifikator-visitor/b2b', [VerifikatorVisitorController::class, 'indexTamu']);
    Route::get('verifikator-b2b-stats', [VerifikatorVisitorController::class, 'getStats']);
    Route::get('verifikator-b2b-list', [VerifikatorVisitorController::class, 'getTamuList']);
    Route::get('verifikator-b2b/{id}', [VerifikatorVisitorController::class, 'showTamu']);
    Route::post('verifikator-b2b/{noTransaksi}/approve', [VerifikatorVisitorController::class, 'approveTamu']);
    Route::post('verifikator-b2b/{noTransaksi}/reject', [VerifikatorVisitorController::class, 'rejectTamu']);
    Route::post('verifikator-b2b/{noTransaksi}/revoke', [VerifikatorVisitorController::class, 'revokeTamu']);
});
