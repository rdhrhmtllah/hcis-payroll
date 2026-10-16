<?php

namespace App\Services\VISITOR\Workflow\Reporting\Export;

use App\Services\SYSTEM\Application\Reports\DatasetRegistry;
use App\Services\SYSTEM\Application\Reports\TautanBerkasRegistry;
use Illuminate\Support\ServiceProvider;

class VisitorReportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singletonIf(TautanBerkasRegistry::class, fn () => new TautanBerkasRegistry);
    }

    public function boot(): void
    {
        $registry = $this->app->make(DatasetRegistry::class);
        $registry->daftarkan('visitor', $this->app->make(VisitorGuestDataset::class));
        $registry->daftarkan('visitor', $this->app->make(VisitorPermitDataset::class));

        // Kolom `tautan:visitor_*` di dataset VMS: ID berkas → tautan 1 hari.
        $tautan = $this->app->make(TautanBerkasRegistry::class);
        foreach ([TautanBerkasVisitor::DOKUMEN_PERMIT, TautanBerkasVisitor::FOTO_TAMU] as $jenis) {
            $tautan->daftarkan(
                'visitor_'.str_replace('-', '_', $jenis),
                fn (string $id) => $this->app->make(TautanBerkasVisitor::class)->buat($jenis, $id),
            );
        }
    }
}
