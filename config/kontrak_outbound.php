<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Masa berlaku tautan viewer kontrak
    |--------------------------------------------------------------------------
    |
    | Viewer kontrak (/api/contracts/{slug}) tetap bisa dibuka tanpa login,
    | karena tautannya memang dibagikan ke developer vendor. Yang ditutup adalah
    | PENEBAKAN: sejak tautannya ditandatangani, slug yang benar saja tidak cukup.
    |
    | null  = tanda tangan tidak berkedaluwarsa.
    | angka = jumlah HARI tautan berlaku sejak dibuat.
    |
    | Bakunya null, dan itu keputusan sadar. Tujuan tanda tangan di sini adalah
    | anti-penebakan, bukan rotasi rahasia — sebuah spesifikasi integrasi yang
    | mati di tengah pengerjaan vendor menimbulkan kerepotan yang jauh lebih
    | besar daripada risiko yang ditutupnya. Isi angka hanya bila memang ada
    | kebijakan yang menuntutnya.
    |
    | CATATAN PENTING: tanda tangan terikat pada APP_KEY *dan* pada URL utuh —
    | termasuk host-nya. Akibatnya:
    |
    |  - Tautan yang dibuat di dev TIDAK sah di production, dan sebaliknya.
    |    Selalu salin tautannya dari portal di lingkungan yang sama dengan yang
    |    akan dibuka vendor.
    |  - Tautan yang dibuat DI LUAR permintaan HTTP (perintah artisan, job
    |    antrean) memakai APP_URL, bukan host yang sedang dibuka. Pada 4 Sep 2026
    |    APP_URL di lingkungan dev ini masih menunjuk domain lain, jadi jangan
    |    membuat tautan viewer dari jalur CLI sebelum nilainya dibetulkan.
    |
    */
    'tautan_berlaku_hari' => env('KONTRAK_TAUTAN_BERLAKU_HARI'),
];
