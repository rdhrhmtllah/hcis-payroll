<?php

namespace App\Services\KPI\PerformanceAppraisal\Export;

use App\Services\SYSTEM\Application\Reports\DatasetRegistry;
use Illuminate\Support\ServiceProvider;

class PaReportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $registry = $this->app->make(DatasetRegistry::class);
        $registry->daftarkan('pa', $this->app->make(PaHasilDataset::class));
        $registry->daftarkan('pa', $this->app->make(PaPenugasanDataset::class));
        $registry->daftarkan('pa', $this->app->make(PaKompetensiDataset::class));
        $registry->daftarkan('pa', $this->app->make(PaPesertaDataset::class));
    }
}
