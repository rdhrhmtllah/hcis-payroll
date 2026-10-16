<?php

namespace App\Services\LMS\SOP\Export;

use App\Jobs\LMS\SOP\Export\GenerateSopReportExportJob;
use App\Services\SYSTEM\Application\Reports\ReportExportService;

/**
 * Sambungan modul SOP ke mesin export laporan.
 *
 * Modul: LMS · Submodul: SOP · Fitur: Export · Lapisan: Service.
 *
 * Hanya DUA hal yang khas SOP di sepanjang jalur export: nama modul yang
 * dicatat di N_HRIS_Async_Tasks, dan kelas Job yang mengantre. Keduanya ada di
 * sini, sehingga ReportExportService di SYSTEM tetap bersih dari nama modul
 * bisnis dan modul berikutnya cukup menambah kelas setipis ini.
 *
 * Nama modul 'sop-studio', bukan 'sop-compliance' — supaya Activity Center
 * bisa membedakan export Studio dari export lama selama masa dua jalur.
 */
class SopReportExportService extends ReportExportService
{
    protected function moduleKey(): string
    {
        return 'sop-studio';
    }

    public function dispatchJob(string $exportId): void
    {
        GenerateSopReportExportJob::dispatch($exportId);
    }
}
