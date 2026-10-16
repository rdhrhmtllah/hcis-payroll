<?php

use App\Http\Controllers\SYSTEM\Integration\OutboundContractController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SYSTEM — Outbound Integration Contracts & Feature Requests
|--------------------------------------------------------------------------
|
| Berkas ini kini memuat SATU jenis rute saja: viewer publik untuk developer
| vendor. Sengaja bebas auth dan bebas Api_Public — tautannya memang dibagikan
| ke luar, dan mensyaratkan login akan membatalkan gunanya.
|
| - GET /api/contracts/{slug}      Viewer mandiri untuk developer klien/vendor
| - GET /api/contracts/{slug}/json Spesifikasi kontrak dalam JSON
| - GET /api/docs/requests/{slug}  Alias ramah untuk viewer yang sama
|
| Portal manajemennya PINDAH ke routes/SYSTEM/Integration/OutboundContractsPage
| .php sebagai halaman Inertia di dalam shell. Ia sempat tinggal di sini sebagai
| Blade mandiri, dan itu salah dua kali:
|
| 1. Grup middleware 'api' TIDAK memuat autentikasi sama sekali — isinya hanya
|    diagnostik, lock-timeout, throttle, dan activity tracker. Rute internal
|    yang lupa menyebut penjaganya sendiri di sini tidak ditolak oleh apa pun;
|    ia menjawab 200 kepada siapa saja di internet, tanpa galat, tanpa jejak.
| 2. Setelah penjaganya dipasang, halaman non-Inertia di balik `auth` di dalam
|    aplikasi Inertia tampil di dalam modal galat: klien Inertia mengikuti
|    redirect()->intended() sebagai XHR ber-header X-Inertia dan menerima HTML
|    polos. Halaman internal di repo ini berbentuk Inertia; itu bukan selera.
|
| /api/contracts dipertahankan sebagai PENGALIHAN supaya tautan dan penanda
| lama tidak mati. Ia tidak menyajikan apa pun sendiri.
|
*/

// Kedua alamat lama portal dialihkan, bukan dimatikan. Alias 'docs/requests'
// ikut, dengan alasan yang sama seperti ia dulu ikut dijaga: dua pintu ke satu
// halaman harus diperlakukan sama, termasuk saat halamannya pindah.
//
// 302, bukan 301: pengalihan permanen disimpan keras oleh peramban, sehingga
// keputusan hari ini akan sulit dicabut besok. Tanpa nama rute — pemanggil
// harus menuju halaman yang sebenarnya, bukan ke pengalihannya.
Route::redirect('contracts', '/system/integrasi/kontrak-outbound');
Route::redirect('docs/requests', '/system/integrasi/kontrak-outbound');

/*
 * Batas bentuk slug dipasang di lapis routing: masukan yang tak berbentuk slug
 * ditolak 404 sebelum menyentuh controller, sehingga tak ada teks bebas dari
 * luar yang ikut terpantul ke pesan galat.
 *
 * Sengaja per-rute (->where), bukan Route::pattern(): pattern bersifat global
 * dan akan diam-diam mengikat setiap rute ber-{slug} di seluruh aplikasi.
 */
$bentukSlug = '[a-z0-9][a-z0-9-]*';

/*
 * Tetap tanpa login — tautannya memang dibagikan ke developer vendor — tetapi
 * WAJIB bertanda tangan. Yang ditutup adalah PENEBAKAN: sebelum ini, siapa pun
 * yang menebak "web-careers-candidate-status" membaca peta integrasi outbound
 * HCIS. Sejak sekarang slug yang benar saja tidak cukup.
 *
 * Tautannya dibuatkan portal internal saat System Admin menekan "Salin Tautan",
 * jadi tidak ada jalur bagi orang luar untuk membuat tanda tangan sendiri.
 *
 * Tanda tangan dihitung dari APP_KEY: tautan yang dibuat di dev TIDAK sah di
 * production. Salin tautannya dari lingkungan yang sama dengan yang akan
 * dibuka vendor.
 *
 * Alias 'docs/requests/{slug}' DIHAPUS, bukan ikut ditandatangani. Tanda tangan
 * terikat pada URL utuh, sehingga tautan untuk satu bentuk alamat tidak pernah
 * sah pada bentuk lainnya — alias kedua hanya akan jadi pintu yang selalu
 * menolak. Sekarang tautan hanya lahir dari satu tempat, dan itu memang yang
 * diinginkan.
 */
Route::middleware('signed')->group(function () use ($bentukSlug) {
    Route::get('contracts/{slug}', [OutboundContractController::class, 'show'])
        ->where('slug', $bentukSlug)
        ->name('system.integration.contracts.show');

    Route::get('contracts/{slug}/json', [OutboundContractController::class, 'json'])
        ->where('slug', $bentukSlug)
        ->name('system.integration.contracts.json');
});
