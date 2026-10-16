<?php

use App\Http\Controllers\LMS\Content\ContentManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'check.jabatan:LMSContentManagementPage'])
    ->prefix('/lms')
    ->name('lms.')
    ->group(function () {
        Route::get('/konten-kategori', [ContentManagementController::class, 'index'])->name('konten-kategori.index');

        Route::post('/konten-kategori/create-kategori', [ContentManagementController::class, 'createKategori'])->name(
            'konten-kategori.create-kategori.store',
        );

        Route::get('/konten-kategori/kategori', [ContentManagementController::class, 'getKategori'])->name(
            'konten-kategori.kategori.index',
        );

        Route::get('/konten-kategori/konten/{id}', [ContentManagementController::class, 'getKontenByKategori'])->name(
            'konten-kategori.konten.show',
        );
        Route::get('/konten-kategori/berkas/{id}/metadata', [
            ContentManagementController::class,
            'getKontenBerkasMetadata',
        ])->name('konten-kategori.berkas.metadata');

        Route::post('/konten-kategori/berkas/{id}/render-ulang', [
            ContentManagementController::class,
            'renderUlangBerkas',
        ])->name('konten-kategori.berkas.render-ulang');

        Route::post('/konten-kategori/berkas/{id}/verifikasi-ulang', [
            ContentManagementController::class,
            'verifikasiUlangBerkas',
        ])->name('konten-kategori.berkas.verifikasi-ulang');

        Route::get('/konten-kategori/berkas/{id}/unduh', [
            ContentManagementController::class,
            'unduhBerkas',
        ])->name('konten-kategori.berkas.unduh');

        Route::get('/konten-kategori/bunny-thumbnail/{videoId}/{fileName}', [
            ContentManagementController::class,
            'getBunnyThumbnail',
        ])
            ->where('fileName', '.+')
            ->name('konten-kategori.thumbnail');

        Route::post('/konten-kategori/upload-konten', [ContentManagementController::class, 'uploadKonten'])->name(
            'konten-kategori.upload-konten.store',
        );

        Route::post('/konten-kategori/get-upload-ticket', [ContentManagementController::class, 'getUploadTicket'])->name(
            'konten-kategori.upload-konten.ticket',
        );

        // Lifecycle task upload async → Pusat Aktivitas (catat & update progres).
        Route::post('/konten-kategori/upload-task/start', [ContentManagementController::class, 'startUploadTask'])->name(
            'konten-kategori.upload-task.start',
        );
        Route::post('/konten-kategori/upload-task/{exportId}/progress', [
            ContentManagementController::class,
            'progressUploadTask',
        ])->name('konten-kategori.upload-task.progress');
        Route::post('/konten-kategori/upload-task/{exportId}/fail', [
            ContentManagementController::class,
            'failUploadTask',
        ])->name('konten-kategori.upload-task.fail');

        Route::delete('/konten-kategori/delete-upload-ticket', [
            ContentManagementController::class,
            'deleteUploadTicket',
        ])->name('konten-kategori.upload-konten.delete-ticket');

        Route::post('/konten-kategori/rename', [ContentManagementController::class, 'rename'])->name(
            'konten-kategori.rename',
        );

        // Get content usage tracking (training and scheduling)
        Route::get('/api/content/{id}/usage-tracking', [ContentManagementController::class, 'getContentUsage'])->name(
            'konten-kategori.content-usage',
        );
        Route::post('/api/content/usage-tracking-batch', [
            ContentManagementController::class,
            'getContentUsageBatch',
        ])->name('konten-kategori.content-usage-batch');

        Route::post('/konten-kategori/delete-konten', [ContentManagementController::class, 'deleteKonten'])->name(
            'konten-kategori.delete-konten',
        );

        Route::post('/konten-kategori/delete-kategori', [ContentManagementController::class, 'deleteKategori'])->name(
            'konten-kategori.delete-kategori',
        );
    });
