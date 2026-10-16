<?php

use App\Http\Controllers\VISITOR\Permit\VehiclePermit\KartuPermitPublikApiController;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| VISITOR — Cek kartu permit untuk layanan cek kartu publik (API pihak ke-3)
|--------------------------------------------------------------------------
|
| Hanya dipanggil layanan cek kartu publik (Cloud Run, repo terpisah) secara
| server-ke-server. Dijaga hris.api.guard; aplikasinya hanya di-whitelist ke
| endpoint ini (DML-2026-143).
|
| Batas laju dihitung PER APLIKASI, bukan per IP: seluruh pemindaian publik
| datang dari segelintir IP keluar Cloud Run, sehingga batas bawaan grup api
| (60/menit per IP) akan menolak pemindaian sah pada jam sibuk. Throttle
| dipasang SESUDAH guard supaya identitas aplikasinya sudah diketahui.
|
*/

Route::middleware(['hris.api.guard', 'throttle:kartu-permit-publik'])
    ->withoutMiddleware(ThrottleRequests::class.':api')
    ->get('v1/hcis/visitor/kartu-permit/{kode}', [KartuPermitPublikApiController::class, 'show'])
    ->where('kode', '[0-9A-Fa-f]{32}');
