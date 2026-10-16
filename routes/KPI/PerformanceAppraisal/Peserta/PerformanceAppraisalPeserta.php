<?php

use App\Http\Controllers\KPI\PerformanceAppraisal\Peserta\PaPesertaController;
use App\Http\Controllers\KPI\PerformanceAppraisal\Peserta\PaSimulasiPengisianController;
use App\Http\Controllers\KPI\PerformanceAppraisal\Peserta\PaWizardPenilaianController;
use Illuminate\Support\Facades\Route;

/*
 * Sisi peserta Performance Appraisal.
 *
 * Kunci halamannya `PerformanceAppraisalSayaPage`, dan barisnya di N_HRIS_Menus dipasang
 * Is_Global_Menu='Y'. Itu keputusan yang disengaja: memberikannya lewat HRIS_Page_Access
 * berarti 958 baris yang harus ditambah setiap ada karyawan baru — dan karyawan yang
 * terlewat tidak akan mengeluh, ia hanya tidak akan pernah mengisi penilaiannya, lalu
 * muncul sebagai penilai yang tidak merespons pada laporan admin.
 *
 * Tidak ada `{participantId}` di alamat mana pun di berkas ini, dan jangan ditambahkan.
 * Seluruh lingkupnya berasal dari user yang login; lihat catatan di PaPesertaController.
 */
Route::middleware(['auth', 'check.jabatan:PerformanceAppraisalSayaPage,PaDashboardIndividuPage'])->group(function () {
    Route::get('/performance-appraisal/dashboard-individu', [PaPesertaController::class, 'dashboardIndividu'])
        ->name('performance-appraisal.dashboard-individu');

    Route::get('/performance-appraisal/saya', [PaPesertaController::class, 'index'])
        ->name('performance-appraisal.saya');

    Route::prefix('api/v1/performance-appraisal/saya')->group(function () {
        Route::get('/ringkasan', [PaPesertaController::class, 'ringkasan']);

        /* Agregat per kategori penilai. GET dan tanpa paginasi: yang dijawabnya
           pertanyaan tentang KESELURUHAN, dan agregat yang ikut terpotong halaman
           akan melaporkan sebagian sebagai seluruhnya. */
        Route::get('/progres-kategori', [PaPesertaController::class, 'progresKategori']);
        Route::get('/assignment', [PaPesertaController::class, 'daftarAssignment']);

        /* PENGIRIMAN SATU PINTU. Lembar yang masuk dari HC Learn berhenti di TERKUMPUL;
           penilai melepasnya sendiri sesudah seluruh tugasnya di siklus itu selesai. Lihat
           PaRaterSubmitService. */
        Route::get('/kemajuan-pengiriman', [PaPesertaController::class, 'kemajuanPengiriman']);
        Route::post('/kirim-semua', [PaPesertaController::class, 'kirimSemua']);
        Route::get('/hasil', [PaPesertaController::class, 'hasilSaya']);
        Route::get('/riwayat', [PaPesertaController::class, 'riwayatSaya']);

        /* WIZARD TUGAS PENILAIAN. `{kode}` hanya memilih kategori di antara tugas milik yang
           login; lingkupnya tetap dari Auth::id(). Lihat PaWizardPenilaianController. */
        Route::get('/wizard', [PaWizardPenilaianController::class, 'wizard']);
        Route::get('/wizard/langkah/{kode}/lembar', [PaWizardPenilaianController::class, 'lembar'])
            ->where('kode', '[A-Z_]{1,40}');
        Route::get('/wizard/langkah/{kode}/pratinjau', [PaWizardPenilaianController::class, 'pratinjau'])
            ->where('kode', '[A-Z_]{1,40}');
        Route::post('/kalibrasi', [PaWizardPenilaianController::class, 'kalibrasi']);
    });

    /*
     | LEMBAR PENILAIAN SIMULASI — pengganti sementara HC Learn.
     |
     | Berada di grup yang SAMA dengan halaman "PA Saya", bukan di grup sendiri: yang boleh
     | mengisinya persis orang yang boleh melihat tugasnya. Kunci halaman yang berbeda akan
     | melahirkan dua daftar hak yang harus dijaga sama, dan yang menyimpang di antaranya
     | menentukan siapa boleh menilai siapa.
     |
     | Tokennya ada di alamat, dan itu BUKAN berarti tokennya menjadi izin. Kepemilikan
     | diperiksa di `PaSimulasiPengisianService::tugasMilikSaya()` terhadap penilai yang
     | tercatat pada penugasannya — token yang dipercaya begitu saja berarti siapa pun yang
     | melihat satu token bisa menilai atas nama orang lain.
     |
     | Seluruh jalannya juga bergerbang pada `sedangDisimulasikan()` di service: config
     | menyala DAN bukan produksi. Rutenya sengaja tetap terdaftar di produksi supaya
     | alamat yang terlanjur tersimpan menjawab dengan sebab yang jelas, bukan 404 yang
     | menyesatkan menjadi "halamannya hilang".
     */
    Route::get('/performance-appraisal/nilai/{token}', [PaSimulasiPengisianController::class, 'index'])
        ->where('token', '[A-Za-z0-9\-]+')
        ->name('performance-appraisal.nilai');

    Route::prefix('api/v1/performance-appraisal/nilai')->group(function () {
        Route::get('/{token}', [PaSimulasiPengisianController::class, 'lembar'])
            ->where('token', '[A-Za-z0-9\-]+');
        Route::post('/{token}', [PaSimulasiPengisianController::class, 'kirim'])
            ->where('token', '[A-Za-z0-9\-]+');
    });
});
