<?php

use App\Http\Controllers\ESS\Attendance\Shift\Admin\PengajuanShiftAdminController;
use App\Http\Controllers\ESS\Attendance\Shift\Schedule\JejakJadwalHariController;
use Illuminate\Support\Facades\Route;

/*
| Admin HC: pantau & batalkan pengajuan perubahan jadwal — Ganti Shift, Tukar
| Hari, Status Hari — dari karyawan, leader, maupun admin (DML-2026-180).
| Satu baris = satu karyawan dalam satu transaksi, jadi rute membawa
| {noTransaksi}/{idDetail}. Nomor berbentuk SH10-26-0008 — tanpa garis miring.
*/
Route::middleware(['auth', 'check.jabatan:pengajuanShiftAdmin'])
    ->prefix('admin/pengajuan-shift')
    ->name('pengajuan-shift.admin.')
    ->group(function () {
        Route::get('/', [PengajuanShiftAdminController::class, 'index'])->name('index');
        Route::get('/data', [PengajuanShiftAdminController::class, 'data'])->name('data');
        Route::get('/detail/{noTransaksi}/{idDetail}', [PengajuanShiftAdminController::class, 'detail'])
            ->whereNumber('idDetail')->name('detail');
        Route::post('/batal/{noTransaksi}/{idDetail}', [PengajuanShiftAdminController::class, 'batal'])
            ->whereNumber('idDetail')->name('batal');
        Route::get('/jejak-hari', [JejakJadwalHariController::class, 'admin'])->name('jejak-hari');
    });
