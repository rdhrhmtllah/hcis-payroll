<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Export;

use App\Services\KPI\PerformanceAppraisal\Export\PaReportExportService;
use App\Services\SYSTEM\Application\Reports\DatasetRegistry;
use Tests\TestCase;

class PaExportPendaftaranTest extends TestCase
{
    public function test_empat_dataset_terdaftar_pada_modul_pa(): void
    {
        $registry = app(DatasetRegistry::class);

        $this->assertSame('hasil', $registry->ambil('pa', 'hasil')->kunci());
        $this->assertSame('penugasan', $registry->ambil('pa', 'penugasan')->kunci());
        $this->assertSame('kompetensi', $registry->ambil('pa', 'kompetensi')->kunci());
        $this->assertSame('peserta', $registry->ambil('pa', 'peserta')->kunci());
    }

    public function test_module_key_terdaftar_di_config_exports(): void
    {
        // Lupa mendaftarkan di sini tidak menggagalkan apa pun sampai klik unduh:
        // berkasnya tetap dibuat dan ditandai siap, lalu unduhannya gagal.
        $peta = config('exports.modules');

        $this->assertArrayHasKey('pa-studio', $peta);
        $this->assertSame(PaReportExportService::class, $peta['pa-studio']);
    }

    public function test_plafon_baris_pa_terdefinisi_untuk_tiap_format(): void
    {
        $plafon = config('exports.pa_report_max_rows');

        $this->assertIsArray($plafon);
        foreach (['csv', 'xlsx', 'pdf'] as $format) {
            $this->assertArrayHasKey($format, $plafon);
            $this->assertGreaterThan(0, $plafon[$format]);
        }
    }
}
