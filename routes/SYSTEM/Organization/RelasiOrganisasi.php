<?php

use App\Http\Controllers\SYSTEM\Organization\OrganizationRelationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SYSTEM — Relasi Organisasi
|--------------------------------------------------------------------------
| Siapa atasan, bawahan, dan rekan kerja siapa. Ini master LINTAS MODUL, bukan
| milik Performance Appraisal — PA hanya salah satu pembacanya, lewat
| `PaAssignmentBuilderService`.
|
| Gerbangnya ATAU: System Admin, atau pemegang akses `systemRelasiOrganisasiPage`.
|
| Awalnya `system.admin` saja, dan itu terlalu sempit: admin PA yang menyiapkan siklus
| membutuhkan struktur ini justru untuk pekerjaannya — penugasan penilai diturunkan
| darinya — sementara satu-satunya pemegang bendera System Admin bukan orang yang
| mengerjakannya sehari-hari.
|
| DUA kode halaman diterima, dan itu bukan kelebihan:
|
|   systemRelasiOrganisasiPage     butir menu di modul SYSTEM (untuk System Admin)
|   PaMasterRelasiOrganisasiPage   butir menu di modul KPI, header Master Data (DML-099)
|
| Keduanya menunjuk halaman yang SAMA. Satu layar, satu sumber kebenaran; yang berbeda hanya
| dari mana orang menemukannya.
|
| Kode kedua itu membawa akibat yang disengaja dan sudah diputuskan: karena akses role
| diturunkan dari tabel pemetaan yang sama dengan menu, memetakannya ke grup KPI memberikan
| halaman ini kepada SETIAP pemegang role KPI — sekarang maupun yang ditambahkan kelak.
| Struktur organisasi menentukan siapa menilai siapa, jadi kalau kelak diputuskan
| dipersempit lagi, yang dicabut adalah baris pemetaan KPI-nya (DIHAPUS, bukan dimatikan:
| Flag_Aktif tidak disaring oleh kueri akses).
|
| CATATAN PEMELIHARAAN
|
| Berkas ini sempat memuat tujuh rute yang terdaftar DUA KALI — sisa dua lajur kerja yang
| sama-sama menambahkannya. Laravel tidak mengeluh: pendaftaran terakhir yang menang, dan
| nama yang kembar menunjuk yang belakangan. Jadi gejalanya nihil sampai suatu saat dua
| pendaftaran itu berbeda isinya, dan yang berlaku bukan yang sedang dibaca orang.
| Satu rute, satu baris.
*/
Route::middleware(['auth', 'system.admin.atau:systemRelasiOrganisasiPage,PaMasterRelasiOrganisasiPage'])
    ->prefix('system/organisasi/relasi')
    ->name('system.organisasi.relasi.')
    ->group(function () {
        Route::get('/', [OrganizationRelationController::class, 'halaman'])->name('page');

        // Pembacaan
        Route::get('/ringkasan', [OrganizationRelationController::class, 'ringkasan'])->name('ringkasan');
        Route::get('/daftar', [OrganizationRelationController::class, 'daftar'])->name('daftar');
        Route::get('/cari-karyawan', [OrganizationRelationController::class, 'cariKaryawan'])->name('cari-karyawan');
        Route::get('/filter-options', [OrganizationRelationController::class, 'opsiFilter'])->name('filter-options');
        // GET untuk tautan/klien lama; POST bila daftar karyawan terpilih terlalu panjang untuk URL.
        Route::match(['get', 'post'], '/ekspor', [OrganizationRelationController::class, 'ekspor'])->name('ekspor');
        Route::get('/cari-karyawan/semua', [OrganizationRelationController::class, 'semuaKaryawanCocok'])->name('cari-karyawan.semua');
        Route::get('/usulan-approval/ekspor', [OrganizationRelationController::class, 'eksporUsulanApproval'])->name('usulan-approval.ekspor');
        // Export di latar belakang (batch + Activity Center); `ekspor` di atas tetap untuk unduhan langsung.
        Route::post('/ekspor/mulai', [OrganizationRelationController::class, 'mulaiEkspor'])->name('ekspor.mulai');
        Route::get('/ekspor/{exportId}/status', [OrganizationRelationController::class, 'statusEkspor'])->name('ekspor.status');
        Route::get('/pohon-departemen', [OrganizationRelationController::class, 'pohonDepartemen'])->name('pohon-departemen');

        /* Penelusuran satu orang: seluruh relasinya sekaligus. Dipakai panel ekosistem,
           yang dibuka berulang kali saat admin menelusuri struktur. */
        Route::get('/ekosistem', [OrganizationRelationController::class, 'ekosistem'])->name('ekosistem');
        Route::get('/daftar-bawahan', [OrganizationRelationController::class, 'daftarBawahan'])->name('daftar-bawahan');

        // Penulisan satuan
        Route::post('/', [OrganizationRelationController::class, 'tambah'])->name('tambah');

        /* Operasi massal. Semuanya menulis banyak baris sekaligus dan masing-masing
           berbatas di layanan — bukan di sini: batas yang hanya ada di rute bisa dilewati
           pemanggil lain, dan operasi ini menulis struktur yang menentukan siapa menilai
           siapa.

           `massal` dan `massal-saringan` sengaja terpisah dan berbatas berbeda. Yang
           pertama menerima daftar NAMA yang ditinjau admin satu per satu (batas 200, batas
           yang masih bisa dibaca); yang kedua menerima SARINGANNYA dan server yang
           menyelesaikan himpunannya (batas 1000, karena yang ditinjau jumlah, bukan nama). */
        Route::post('/massal', [OrganizationRelationController::class, 'tambahMassal'])->name('massal');
        Route::post('/massal-saringan', [OrganizationRelationController::class, 'tambahMassalSaringan'])
            ->name('massal-saringan');
        Route::post('/peer-cluster', [OrganizationRelationController::class, 'peerCluster'])->name('peer-cluster');
        Route::post('/transfer', [OrganizationRelationController::class, 'transfer'])->name('transfer');
        Route::post('/cabut-massal', [OrganizationRelationController::class, 'cabutMassal'])->name('cabut-massal');
        // Impor ATASAN dari Excel usulan approval; jalankan=0 hanya pratinjau.
        Route::post('/usulan-approval/impor', [OrganizationRelationController::class, 'imporUsulanApproval'])->name('usulan-approval.impor');

        /* PALING BAWAH: `{id}` menangkap apa pun. Ditaruh di atas, `cabut-massal` akan
           terbaca sebagai id dan ditolak `whereNumber` — atau lebih buruk, tertangkap rute
           lain yang tidak dimaksudkan. */
        Route::post('/{id}/cabut', [OrganizationRelationController::class, 'cabut'])
            ->whereNumber('id')
            ->name('cabut');
    });
