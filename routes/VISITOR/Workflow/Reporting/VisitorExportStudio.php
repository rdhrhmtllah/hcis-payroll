<?php

use App\Http\Controllers\VISITOR\Workflow\Reporting\Export\VisitorExportStudioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| VISITOR — Export Studio Tamu & Export Studio Permit
|--------------------------------------------------------------------------
| Dipisah dari /laporanvisitor menjadi halaman sendiri (DML-2026-026), lalu
| dipecah lagi per dataset (DML-2026-106) mengikuti pemisahan Monitoring dan
| Verifikator.
|
| SATU kunci per grup, bukan `check.jabatan:A,B`: bentuk OR justru membatalkan
| pemisahan yang jadi tujuan pekerjaan ini. Gerbang API memakai kunci yang sama
| dengan gerbang halamannya — menggerbangi halaman saja hanya menyembunyikan
| tombolnya, sedangkan datanya tetap bisa ditarik langsung dari endpoint.
|
| Datasetnya ditulis sebagai ruas LITERAL pada prefiks, bukan sebagai parameter
| `{studioDataset}` yang dibatasi `where()`. Koleksi rute berkunci metode+POLA
| URI, jadi dua grup berparameter sama akan terdaftar dengan pola yang identik
| dan yang BELAKANGAN MENIMPA yang pertama — seluruh jalur tamu lenyap tanpa
| galat, dan yang tersisa hanya 404 saat dipanggil. Nilainya diteruskan ke
| controller lewat defaults() supaya tanda tangan metodenya tidak berubah.
*/

Route::middleware(['auth'])->group(function () {
    // URL lama studio gabungan; controllernya mengalihkan ke dataset tamu.
    Route::get('visitor/export-studio', [VisitorExportStudioController::class, 'halaman'])
        ->name('visitor.export-studio');
});

/**
 * Daftar endpoint studio untuk satu dataset.
 *
 * Ditulis sekali dan dipanggil dua kali: dua salinan yang disunting terpisah
 * akan menua sendiri-sendiri, dan yang tertinggal justru domain yang jarang
 * dibuka.
 */
$endpointStudio = function (string $dataset) {
    return function () use ($dataset) {
        Route::get('/katalog/{dataset}', [VisitorExportStudioController::class, 'katalog'])
            ->where('dataset', 'tamu|permit')
            ->defaults('studioDataset', $dataset);

        foreach ([
            ['get', '/template', 'galeri'],
            ['post', '/template', 'simpan'],
            ['put', '/template/{kode}', 'perbarui'],
            ['post', '/template/{kode}/duplikat', 'duplikat'],
            ['delete', '/template/{kode}', 'hapus'],
            ['post', '/pratinjau', 'pratinjau'],
            ['post', '/jalankan', 'jalankan'],
        ] as [$metode, $jalur, $aksi]) {
            Route::{$metode}($jalur, [VisitorExportStudioController::class, $aksi])
                ->defaults('studioDataset', $dataset);
        }
    };
};

// ─── Tamu ───────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'check.jabatan:visitorExportStudioTamuPage'])->group(function () {
    Route::get('visitor/export-studio/tamu', [VisitorExportStudioController::class, 'halamanTamu'])
        ->name('visitor.export-studio.tamu');
});

Route::middleware(['auth', 'check.jabatan:visitorExportStudioTamuPage'])
    ->prefix('api/v1/visitor/export-studio/tamu')
    ->group($endpointStudio('tamu'));

// ─── Permit ─────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'check.jabatan:visitorExportStudioPermitPage'])->group(function () {
    Route::get('visitor/export-studio/permit', [VisitorExportStudioController::class, 'halamanPermit'])
        ->name('visitor.export-studio.permit');
});

Route::middleware(['auth', 'check.jabatan:visitorExportStudioPermitPage'])
    ->prefix('api/v1/visitor/export-studio/permit')
    ->group($endpointStudio('permit'));
