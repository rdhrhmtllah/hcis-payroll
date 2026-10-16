<?php

use App\Http\Controllers\LMS\SOP\Employee\SopAbsensiController;
use App\Http\Controllers\LMS\SOP\Employee\SopCheckpointController;
use App\Http\Controllers\LMS\SOP\Employee\SopProgressController;
use App\Http\Controllers\LMS\SOP\Employee\SopRepositoryController;
use App\Http\Controllers\LMS\SOP\Employee\SopSayaController;
use App\Http\Controllers\LMS\SOP\Employee\SopSecurePdfController;
use App\Http\Controllers\LMS\SOP\Employee\SopTeamController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SOP — Sisi Karyawan (Dev A)
|--------------------------------------------------------------------------
| Halaman: middleware 'auth' biasa (bukan admin).
| CATATAN URUTAN: route statik (/sop/saya, /sop/repository) HARUS didaftar
| SEBELUM route dinamis (/sop/{id}) agar tidak "ditelan" parameter.
*/

Route::prefix('sop')->middleware(['auth'])->group(function () {
    Route::get('/saya', [SopSayaController::class, 'index'])->name('sop.saya');
    // Repository di URL sendiri (/sop/repository) — BUKAN bare /sop — supaya
    // active-match sidebar tidak "menang" atas sub-halaman /sop/admin/* dsb.
    Route::get('/repository', [SopRepositoryController::class, 'index'])->name('sop.repository');
    Route::get('/tim', [SopTeamController::class, 'index'])->name('sop.tim'); // kepatuhan tim (leader)
    Route::get('/', fn () => redirect('/sop/repository')); // bare /sop → repository
    Route::get('/{id}', [SopRepositoryController::class, 'show'])->name('sop.detail');

    // --- SEAM: halaman milik LEAD (placeholder supaya tidak 404 saat Dev A tautkan) ---
    // Dev A cukup arahkan tombol ke URL ini; isinya digarap LEAD.
    Route::get('/{id}/baca/{item}', fn ($id, $item) => inertia('LMS/SOP/Shared/placeholder/ComingSoon', ['fitur' => 'Reader (secure PDF)'])
    )->name('sop.reader');
    // Checkpoint kuis: lazy-mint token HC Learn saat diklik → redirect ke Link_Ujian.
    Route::get('/{id}/kuis/{item}', [SopCheckpointController::class, 'launch'])->name('sop.checkpoint');
});

Route::prefix('api/v1/sop')->middleware(['auth'])->group(function () {
    // Perpustakaan: statik sebelum dinamis, dan SEBELUM '/{id}' di bawah.
    Route::get('/repository/beranda', [SopRepositoryController::class, 'apiBeranda']);
    /* Perpustakaan diproyeksikan per dokumen. Statik, jadi mendahului
       '/repository' polos di bawah — alasan yang sama seperti seluruh blok ini. */
    Route::get('/repository/dokumen', [SopRepositoryController::class, 'apiKatalogDokumen']);
    Route::get('/repository/cari', [SopRepositoryController::class, 'apiCari']);
    Route::get('/repository/intip/{id}', [SopRepositoryController::class, 'apiIntip']);
    Route::get('/repository/paket', [SopRepositoryController::class, 'apiDaftarPaket']);
    /* Rekap kelompok rail atas SELURUH himpunan tersaring — terpisah dari baris
       halaman karena keduanya menjawab pertanyaan berbeda, dan menyatukannya
       memaksa memuat semua baris hanya untuk memperoleh angkanya. */
    Route::get('/repository/rekap', [SopRepositoryController::class, 'apiRekapSumbu']);
    Route::post('/repository/favorit/{id}', [SopRepositoryController::class, 'apiFavorit']);
    Route::get('/repository', [SopRepositoryController::class, 'apiList']);
    Route::get('/saya', [SopSayaController::class, 'apiSaya']);
    // Riwayat kepatuhan versi karyawan DIHAPUS: analitiknya milik admin/leader,
    // yang sudah punya /karyawan/{kode}/riwayat + /karyawan/{kode}/atestasi
    // (lebih lengkap, sampai IP & perangkat). Yang tersisa cuma unduhan kartu
    // kepatuhan miliknya sendiri, kini dipicu dari halaman SOP Saya.
    Route::post('/saya/kartu-kepatuhan', [SopSayaController::class, 'apiKartuKepatuhan']);
    // kepatuhan tim (leader) — statik, sebelum route dinamis /{id}
    Route::get('/tim/ringkasan', [SopTeamController::class, 'ringkasan']);
    Route::get('/tim/anggota/{kode}', [SopTeamController::class, 'anggota']);
    // aksi progress karyawan (completion flow)
    Route::post('/progress/buka', [SopProgressController::class, 'bukaItem']);
    Route::post('/progress/konfirmasi', [SopProgressController::class, 'konfirmasiItem']);
    Route::post('/progress/halaman', [SopProgressController::class, 'simpanHalaman']);
    // secure-PDF reader: token handshake (reuse endpoint halaman LMS)
    Route::post('/secure-pdf/token/{berkasId}', [SopSecurePdfController::class, 'token']);
    // presensi kehadiran — statik dulu, sebelum pola dinamis /{id} di bawah
    Route::get('/{id}/absensi', [SopAbsensiController::class, 'status']);
    Route::post('/{id}/absensi/checkin', [SopAbsensiController::class, 'checkin']);
    // reader v2: riwayat versi (on-demand) + lihat konten versi
    Route::get('/{idPaket}/item/{idItem}/versi', [SopRepositoryController::class, 'apiItemVersi']);
    Route::get('/{idPaket}/versi/{idVersi}/konten', [SopRepositoryController::class, 'apiVersiKonten']);
    Route::get('/{idPaket}/versi/{idVersi}/lampiran/{idLampiran}', [SopRepositoryController::class, 'apiVersiLampiran']);
    Route::get('/{idPaket}/versi/{idVersi}/lampiran-zip', [SopRepositoryController::class, 'apiVersiLampiranZip']);
    // dinamis paling bawah
    Route::get('/{id}', [SopRepositoryController::class, 'apiDetail']);
});
