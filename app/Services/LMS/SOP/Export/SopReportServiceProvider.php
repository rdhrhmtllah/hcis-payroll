<?php

namespace App\Services\LMS\SOP\Export;

use App\Services\SYSTEM\Application\Reports\DatasetRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Mendaftarkan katalog dataset SOP ke registry lintas modul.
 *
 * Inilah satu-satunya sambungan antara modul bisnis dan mesin laporan. Modul
 * lain bergabung dengan menambah provider serupa — tanpa menyentuh mesin.
 */
class SopReportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DatasetRegistry::class, fn () => new DatasetRegistry);
    }

    public function boot(): void
    {
        /*
         * DUA DATASET, DUA BUTIRAN YANG BERBEDA — dan itu justru alasannya
         * dipisah. 'kepatuhan' menjawab "siapa sudah membaca apa"; 'dokumen'
         * menjawab "apa yang kita punya dan bagaimana keadaannya". Dokumen
         * yang belum masuk paket mana pun tidak punya baris sama sekali di
         * dataset pertama, sehingga tak ada saringan atau ukuran apa pun di
         * sana yang bisa memunculkannya.
         */
        $registry = $this->app->make(DatasetRegistry::class);
        $registry->daftarkan('sop', $this->app->make(SopDatasetKepatuhan::class));
        $registry->daftarkan('sop', $this->app->make(SopDatasetDokumen::class));
    }
}
