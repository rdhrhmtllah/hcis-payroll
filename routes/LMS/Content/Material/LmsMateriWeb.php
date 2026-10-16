<?php

use App\Http\Controllers\LMS\Content\Material\LmsMateriController;
use Illuminate\Support\Facades\Route;

// NOTE: Materi (Course Materials) adalah bagian dari Training Detail page
// Page: GET /lms/training/{id} - includes TrainingDetailMaterials component
// Route di sini hanya untuk API endpoints

// API Routes untuk fetch material detail, viewer, pdf streaming, dll
Route::prefix('api/v1')
    ->middleware('auth')
    ->group(function () {
        Route::prefix('lms/training')->group(function () {
            // Material viewing endpoints (bagian dari training detail)
            Route::get('/{id}/materials/{materiDetId}/viewer', [LmsMateriController::class, 'viewer'])->name(
                'api.training.material-viewer',
            );
            Route::get('/{id}/konten/{materiDetId}', [LmsMateriController::class, 'getKonten'])->name(
                'api.training.konten',
            );
            Route::get('/{id}/materials/{berkasId}/pdf/stream', [LmsMateriController::class, 'streamPdf'])->name(
                'api.training.pdf-stream',
            );
            Route::get('/{id}/materials/{berkasId}/download', [LmsMateriController::class, 'downloadFile'])->name(
                'api.training.file-download',
            );
            Route::post('/{id}/materials/{berkasId}/pdf/token', [LmsMateriController::class, 'generatePdfToken'])->name(
                'api.training.pdf-token',
            );
            Route::get('/{id}/materials/{berkasId}/pdf/pages/manifest', [
                LmsMateriController::class,
                'securePdfPageManifest',
            ])->name('api.training.pdf-pages.manifest');
            Route::post('/{id}/materials/{berkasId}/pdf/pages/{page}/grant', [
                LmsMateriController::class,
                'securePdfPageGrant',
            ])
                ->whereNumber('page')
                ->name('api.training.pdf-pages.grant');
            Route::post('/{id}/materials/{berkasId}/pdf/pages/{page}', [
                LmsMateriController::class,
                'securePdfPageImage',
            ])
                ->whereNumber('page')
                ->name('api.training.pdf-pages.image');
            Route::post('/{id}/materials/{berkasId}/pdf/stream-secure', [
                LmsMateriController::class,
                'streamPdfSecure',
            ])->name('api.training.pdf-stream-secure');
            Route::get('/{id}/materials/{berkasId}/pdf/stream-secure', [
                LmsMateriController::class,
                'streamPdfSecure',
            ])->name('api.training.pdf-stream-secure.get');
        });

        // Backward-compatible aliases for older frontend calls
        Route::prefix('lms/materi')->group(function () {
            Route::get('/{id}', [LmsMateriController::class, 'show'])->name('api.materi.show.legacy');
            Route::get('/{materiId}/viewer/{materiDetId}', [LmsMateriController::class, 'viewer'])->name(
                'api.materi.viewer.legacy',
            );
            Route::get('/konten/{materiId}/{materiDetId}', [LmsMateriController::class, 'getKonten'])->name(
                'api.materi.konten.legacy',
            );
            Route::get('/stream-pdf/{berkasId}', [LmsMateriController::class, 'streamPdf'])->name(
                'api.materi.stream-pdf.legacy',
            );
            Route::get('/download-file/{berkasId}', [LmsMateriController::class, 'downloadFile'])->name(
                'api.materi.download-file.legacy',
            );
            Route::post('/secure-pdf/token/{berkasId}', [LmsMateriController::class, 'generatePdfToken'])->name(
                'api.materi.pdf-token.legacy',
            );
            Route::get('/secure-pdf/pages/{berkasId}/manifest', [
                LmsMateriController::class,
                'securePdfPageManifest',
            ])->name('api.materi.pdf-pages.manifest.legacy');
            Route::post('/secure-pdf/pages/{berkasId}/{page}/grant', [LmsMateriController::class, 'securePdfPageGrant'])
                ->whereNumber('page')
                ->name('api.materi.pdf-pages.grant.legacy');
            Route::post('/secure-pdf/pages/{berkasId}/{page}', [LmsMateriController::class, 'securePdfPageImage'])
                ->whereNumber('page')
                ->name('api.materi.pdf-pages.image.legacy');
            Route::post('/secure-pdf/stream/{berkasId}', [LmsMateriController::class, 'streamPdfSecure'])->name(
                'api.materi.pdf-stream-secure.legacy',
            );
            Route::get('/secure-pdf/stream/{berkasId}', [LmsMateriController::class, 'streamPdfSecure'])->name(
                'api.materi.pdf-stream-secure.legacy.get',
            );
        });
    });
