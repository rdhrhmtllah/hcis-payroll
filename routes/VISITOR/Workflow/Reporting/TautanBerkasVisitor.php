<?php

use App\Http\Controllers\VISITOR\Workflow\Reporting\Export\TautanBerkasController;
use App\Services\VISITOR\Workflow\Reporting\Export\TautanBerkasVisitor;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| VISITOR — Tautan berkas di hasil Export Studio
|--------------------------------------------------------------------------
| SENGAJA di luar grup `auth` (keputusan user 30 Sep 2026, opsi 2): file
| export boleh dikirim ke pihak luar. Penjaganya tanda tangan bertenggat
| 1 hari yang diperiksa controller sendiri — bukan middleware `signed` —
| supaya tautan kedaluwarsa dijawab "silakan export ulang", bukan 403 polos.
| Dibatasi lajunya agar tidak bisa dipakai menebak.
*/

Route::get('berkas-visitor/{jenis}/{id}', [TautanBerkasController::class, 'tampil'])
    ->whereIn('jenis', TautanBerkasVisitor::JENIS)
    ->whereNumber('id')
    ->middleware('throttle:60,1')
    ->name(TautanBerkasVisitor::RUTE);
