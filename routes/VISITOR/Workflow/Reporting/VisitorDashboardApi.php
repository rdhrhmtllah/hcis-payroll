<?php

use App\Http\Controllers\VISITOR\Workflow\Reporting\VisitorDashboardController;
use Illuminate\Support\Facades\Route;

// Dashboard admin Visitor. Ini TAB pada halaman monitoring yang sudah
// terdaftar, bukan halaman baru — jadi tidak ada baris menu dan tidak ada izin
// baru; pintunya ikut kunci halaman yang menampungnya.
//
// Kuncinya dua, dan itu wajib. Halaman Laporan gabungan sudah dipecah menjadi
// Monitoring Tamu dan Monitoring Permit, lalu hibah menyeluruh grup Visitor
// dicabut (DDL-2026-110) sehingga ADMIN_PERMIT dan ADMIN_TAMU hanya memegang
// kunci domainnya sendiri. Menyisakan 'visitorLaporanPage' sendirian membuat
// keduanya bisa MEMBUKA halaman monitoringnya tetapi dibalas 403 oleh kedua
// endpoint ini — dashboardnya kosong tanpa satu pun pesan yang menjelaskan
// sebabnya. CheckJabatan meluluskan bila SALAH SATU kunci dipegang.
Route::middleware([
    'auth',
    // Kunci gabungan lama TIDAK disertakan sebagai cadangan: seluruh
    // pemegangnya sudah memegang kunci domainnya juga, dan menyisakannya
    // berarti satu rute yang masih bergantung pada kunci yang sedang
    // dipensiunkan — persis yang dilarang PemisahanHalamanVisitorTest.
    // Kunci B2B ikut: halaman Monitoring Tamu B2B memuat dashboard yang sama
    // (tipe=B2B). Ranah & tipe per permintaan dijaga ulang di controller.
    'check.jabatan:visitorMonitoringTamuPage,visitorMonitoringPermitPage,visitorMonitoringTamuB2BPage',
])
    ->prefix('api/v1/visitor/dashboard')
    ->group(function () {
        Route::get('/sekarang', [VisitorDashboardController::class, 'sekarang']);
        Route::get('/analitik', [VisitorDashboardController::class, 'analitik']);
    });
