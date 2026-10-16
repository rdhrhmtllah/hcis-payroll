<?php

use App\Http\Controllers\VISITOR\Governance\MasterData\VisitorMasterDataController;
use App\Services\VISITOR\Governance\MasterData\VisitorMasterDataCatalog;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| VISITOR — Master Data Permit & Master Data Tamu
|--------------------------------------------------------------------------
| Dipecah per domain (DML-2026-107) mengikuti Monitoring, Verifikator, dan
| Export Studio.
|
| Tiap master didaftarkan sebagai ruas LITERAL, bukan satu pola `{master}` yang
| dibatasi `where()` per grup. Koleksi rute berkunci metode + POLA URI, jadi dua
| grup berparameter sama akan terdaftar dengan pola identik dan yang belakangan
| MENIMPA yang pertama — seluruh jalur domain pertama lenyap tanpa galat, hanya
| 404 saat dipanggil. Itu benar-benar terjadi saat Export Studio dipecah.
|
| Master yang dipakai kedua domain ('bersama') mendapat SATU rute dengan kedua
| kunci (makna ATAU) — bukan dua rute, karena URI-nya sama dan yang kedua akan
| menimpa yang pertama.
|
| Gerbang API memakai kunci yang sama dengan gerbang halamannya: menggerbangi
| halaman saja hanya menyembunyikan tabnya, sedangkan datanya tetap bisa
| ditarik langsung dari endpoint.
*/

Route::middleware(['auth'])->group(function () {
    // URL lama halaman gabungan; controllernya mengalihkan ke domain permit.
    Route::get('/admin/visitor/master-data', [VisitorMasterDataController::class, 'page']);
});

Route::middleware(['auth', 'check.jabatan:visitorMasterDataPermitPage'])->group(function () {
    Route::get('/admin/visitor/master-data/permit', [VisitorMasterDataController::class, 'pagePermit']);

    /*
     * Export satu berkas Excel berisi seluruh master permit.
     *
     * Ruas 'export' aman berdampingan dengan rute per master di bawah karena
     * masing-masing terdaftar LITERAL: 'export' bukan kunci master, jadi tidak
     * ada pola yang saling menimpa.
     *
     * Pratinjau adalah dry run — dipisah dari unduhan supaya admin melihat
     * lembar dan jumlah barisnya sebelum ada berkas yang dirakit.
     */
    Route::get('/api/v1/visitor/master-data/export/permit/pratinjau', [VisitorMasterDataController::class, 'exportPratinjau'])
        ->defaults('domain', 'permit');
    Route::get('/api/v1/visitor/master-data/export/permit', [VisitorMasterDataController::class, 'exportUnduh'])
        ->defaults('domain', 'permit');

    /*
     * Import berkas berbentuk templat export.
     *
     * Dua pintu dengan gerbang yang sama: pratinjau menilai tanpa menulis,
     * eksekusi menulis berkas yang sidik jarinya sama dengan yang dinilai.
     * Menggerbangi salah satunya saja berarti berkas asing tetap bisa masuk
     * lewat pintu yang lain.
     */
    Route::post('/api/v1/visitor/master-data/import/permit/pratinjau', [VisitorMasterDataController::class, 'importPratinjau'])
        ->defaults('domain', 'permit');
    Route::post('/api/v1/visitor/master-data/import/permit', [VisitorMasterDataController::class, 'importJalankan'])
        ->defaults('domain', 'permit');
});

Route::middleware(['auth', 'check.jabatan:visitorMasterDataTamuPage'])->group(function () {
    Route::get('/admin/visitor/master-data/tamu', [VisitorMasterDataController::class, 'pageTamu']);
});

/*
 * Satu rute per master, dengan kunci yang menyusul domainnya. Dipetakan dari
 * VisitorMasterDataCatalog::DOMAIN supaya master baru cukup didaftarkan di
 * katalog — daftar yang disalin ke sini akan menua sendiri dan menghasilkan
 * halaman yang menampilkan master yang endpointnya justru menolaknya.
 */
foreach (VisitorMasterDataCatalog::DOMAIN as $master => $domain) {
    $kunci = match ($domain) {
        'tamu' => 'visitorMasterDataTamuPage',
        'permit' => 'visitorMasterDataPermitPage',
        default => 'visitorMasterDataPermitPage,visitorMasterDataTamuPage',
    };

    Route::middleware(['auth', 'check.jabatan:'.$kunci])
        ->prefix('/api/v1/visitor/master-data/'.$master)
        ->group(function () use ($master) {
            Route::get('/', [VisitorMasterDataController::class, 'index'])->defaults('master', $master);
            Route::post('/', [VisitorMasterDataController::class, 'store'])->defaults('master', $master);
            Route::put('/{id}', [VisitorMasterDataController::class, 'update'])
                ->whereNumber('id')
                ->defaults('master', $master);
            Route::patch('/{id}/status', [VisitorMasterDataController::class, 'changeStatus'])
                ->whereNumber('id')
                ->defaults('master', $master);
        });
}
