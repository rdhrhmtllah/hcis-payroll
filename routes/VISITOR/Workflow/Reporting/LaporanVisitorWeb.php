<?php

use App\Http\Controllers\VISITOR\Permit\VehiclePermit\VehiclePermitController;
use App\Http\Controllers\VISITOR\Permit\VehiclePermit\VerifikasiKartuPermitController;
use App\Http\Controllers\VISITOR\Workflow\Reporting\LaporanVisitorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| VISITOR — Monitoring Tamu & Monitoring Permit
|--------------------------------------------------------------------------
|
| Halaman gabungan /laporanvisitor dipecah menjadi dua (DML-2026-105), dan
| pemisahannya BUKAN sekadar tampilan: tiap endpoint dijaga kunci halaman yang
| benar-benar memakainya. Admin yang hanya diberi Monitoring Permit tidak bisa
| menarik data tamu, bahkan dengan memanggil endpointnya langsung.
|
| `check.jabatan` menerima banyak kunci dengan makna ATAU — itulah yang dipakai
| tiga endpoint yang memang dibaca KEDUA dashboard. Menyalin endpoint itu ke
| dua grup rute tidak mungkin: URL yang sama terdaftar dua kali, dan yang
| terakhir menang secara diam-diam.
|
| Kunci halamannya sengaja ditulis sebagai string, BUKAN const: berkas rute
| dapat dimuat lebih dari sekali dalam satu proses, dan const di lingkup global
| membuat pemuatan kedua mati dengan "Constant already defined".
*/

Route::middleware(['auth'])->group(function () {
    // URL lama. Tidak dijaga kunci apa pun selain auth: yang menegakkan hak
    // akses adalah halaman tujuannya. Menjaganya dengan kunci lama justru
    // mengunci orang yang sudah dipindahkan ke kunci baru.
    Route::get('laporanvisitor', [LaporanVisitorController::class, 'index']);
});

Route::middleware(['auth', 'check.jabatan:visitorMonitoringTamuPage', 'visitor.tipe-tamu:REGULAR'])->group(function () {
    Route::get('monitoring-tamu', [LaporanVisitorController::class, 'indexTamu']);
    Route::get('laporanvisitor-tamu', [LaporanVisitorController::class, 'getLaporanTamu']);

    // Detail BACA SAJA. Ditaruh SESUDAH route daftar supaya
    // '/laporanvisitor-tamu' tidak tertangkap sebagai {id}.
    Route::get('laporanvisitor-detail-tamu/{id}', [LaporanVisitorController::class, 'getDetailTamu']);
});

Route::middleware(['auth', 'check.jabatan:visitorMonitoringPermitPage'])->group(function () {
    Route::get('monitoring-permit', [LaporanVisitorController::class, 'indexPermit']);
    Route::get('laporanvisitor-permit', [LaporanVisitorController::class, 'getLaporanPermit']);
    Route::get('laporanvisitor-detail-permit/{id}', [LaporanVisitorController::class, 'getDetailPermit']);
});

// Kunjungan asal sebuah permit (selalu tamu B2B), dibaca meja & monitoring
// PERMIT. Dijaga kunci permit, bukan kunci tamu: admin permit tidak
// memegang kunci halaman tamu.
Route::middleware([
    'auth',
    'check.jabatan:visitorMonitoringPermitPage,visitorVerifikatorPermitPage',
])->group(function () {
    Route::get('laporanvisitor-permit-tamu/{id}', [LaporanVisitorController::class, 'getDetailTamuPermit']);

    // Admin mencetak kartu dashboard milik karyawan mana pun. Berkasnya sama
    // persis dengan yang dicetak pemegangnya sendiri; yang berbeda hanya
    // pintunya — kunci halaman monitoring/verifikator, bukan kepemilikan.
    Route::get('laporanvisitor-permit/{id}/kartu-pdf', [VehiclePermitController::class, 'kartuPdfAdmin']);

    // Cetak massal: POST karena daftar Id bisa panjang dan tidak layak jadi
    // query string; balasannya tetap berkas PDF, bukan JSON.
    Route::post('laporanvisitor-permit/kartu-pdf-batch', [VehiclePermitController::class, 'kartuPdfBatchAdmin']);

    // Cek keaslian kartu dari QR — pintu ADMIN: boleh memeriksa kartu siapa
    // pun. Pintu karyawan (transaksipermit-verifikasi-kartu) hanya kartunya
    // sendiri; yang membedakan keduanya kunci halaman pada rute ini.
    Route::post('laporanvisitor-verifikasi-kartu', [VerifikasiKartuPermitController::class, 'admin']);
});

// Monitoring Tamu B2B (DDL-2026-121): salinan rute monitoring tamu dengan
// kunci halamannya sendiri. Tipe ditetapkan rute; daftar, detail, papan, dan
// antrean hanya memuat baris B2B.
Route::middleware(['auth', 'check.jabatan:visitorMonitoringTamuB2BPage', 'visitor.tipe-tamu:B2B'])->group(function () {
    Route::get('monitoring-b2b', [LaporanVisitorController::class, 'indexTamu']);
    Route::get('laporanvisitor-b2b-tamu', [LaporanVisitorController::class, 'getLaporanTamu']);
    Route::get('laporanvisitor-b2b-detail-tamu/{id}', [LaporanVisitorController::class, 'getDetailTamu']);
    Route::get('laporanvisitor-b2b-papan-harian', [LaporanVisitorController::class, 'getPapanHarian']);
    Route::get('laporanvisitor-b2b-papan-tamu', [LaporanVisitorController::class, 'getPapanTamu']);
    Route::get('laporanvisitor-b2b-antrian', [LaporanVisitorController::class, 'getAntrianApproval']);
});

// Dibaca KEDUA dashboard, jadi dijaga kedua kuncinya (ATAU).
Route::middleware([
    'auth',
    'check.jabatan:visitorMonitoringTamuPage,visitorMonitoringPermitPage',
])->group(function () {
    Route::get('laporanvisitor-harian', [LaporanVisitorController::class, 'getLaporanHarian']);
    Route::get('laporanvisitor-papan-harian', [LaporanVisitorController::class, 'getPapanHarian']);
    Route::get('laporanvisitor-papan-tamu', [LaporanVisitorController::class, 'getPapanTamu']);
    Route::get('laporanvisitor-antrian', [LaporanVisitorController::class, 'getAntrianApproval']);
});
