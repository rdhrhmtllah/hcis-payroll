<?php

use App\Http\Controllers\VISITOR\Guest\Schedule\JadwalKedatanganTamuController;
use Illuminate\Support\Facades\Route;

/*
| Jadwal Kedatangan Tamu (DDL-2026-122) DILEBUR ke Monitoring Tamu.
|
| Dua halaman menjawab pertanyaan yang sama dengan dua mekanisme izin yang
| berbeda - Jadwal lewat matriks departemen, Monitoring lewat kunci halaman
| per orang - dan pemakai tidak punya cara menebak mana yang harus dibuka.
| Monitoring yang dipertahankan, dan matriks yang dipakai untuk menyaring
| isinya; kunci halaman berhenti menjadi jalan pintas.
|
| Halamannya dialihkan, BUKAN dihapus: pranala lama hidup di bookmark, email,
| dan tangkapan layar SOP. URL yang mati berakhir sebagai 404 yang dilaporkan
| sebagai "modulnya hilang".
|
| Endpoint datanya sengaja TETAP HIDUP. Ia masih dipakai layar lain (mis.
| pemilih jadwal), dan mematikannya bersamaan dengan pemindahan halaman
| membuat dua kegagalan yang berbeda tampak seperti satu.
*/
Route::middleware(['auth'])->group(function () {
    Route::redirect('jadwal-kedatangan-tamu', '/monitoring-tamu', 301);
});

Route::middleware(['auth', 'check.jabatan:visitorJadwalTamuPage,visitorMonitoringTamuPage'])->group(function () {
    Route::get('jadwal-kedatangan-tamu-data', [JadwalKedatanganTamuController::class, 'data']);
    Route::get('jadwal-kedatangan-tamu-list', [JadwalKedatanganTamuController::class, 'getList']);
    Route::get('jadwal-kedatangan-tamu-stats', [JadwalKedatanganTamuController::class, 'getStats']);
    Route::get('jadwal-kedatangan-tamu/{id}', [JadwalKedatanganTamuController::class, 'show']);
});
