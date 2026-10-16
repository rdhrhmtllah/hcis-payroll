<?php

namespace Tests\Feature\KPI\Performance;

use Tests\TestCase;

/**
 * Tombol rollback mesin approval KPI (CENTRAL <-> LEGACY).
 *
 * Dulu dibaca lewat env() langsung di controller. Begitu
 * `php artisan config:cache` dijalankan, berkas .env tidak lagi dimuat dan env()
 * mengembalikan nilai default — jadi menyetel KPI_PARAM_ENGINE=LEGACY tidak
 * berefek apa pun, tanpa pesan kesalahan. Test ini menjaga agar polanya tidak
 * kembali.
 */
class KpiEngineSwitchTest extends TestCase
{
    public function test_kunci_config_ada_dan_default_central(): void
    {
        $this->assertSame('CENTRAL', config('services.kpi.param_engine'));
        $this->assertSame('CENTRAL', config('services.kpi.perf_engine'));
    }

    public function test_nilai_config_dinormalkan_huruf_besar(): void
    {
        // Nilai di .env boleh ditulis apa saja; yang dibandingkan controller
        // selalu huruf besar.
        $this->assertSame(
            strtoupper((string) config('services.kpi.param_engine')),
            config('services.kpi.param_engine'),
        );
    }

    public function test_config_bisa_ditimpa_saat_berjalan(): void
    {
        config(['services.kpi.param_engine' => 'LEGACY']);
        $this->assertSame('LEGACY', config('services.kpi.param_engine'));

        config(['services.kpi.perf_engine' => 'LEGACY']);
        $this->assertSame('LEGACY', config('services.kpi.perf_engine'));
    }

    /**
     * Penjaga statis: tidak boleh ada lagi env('KPI_*_ENGINE') di kode aplikasi.
     * Ini yang sebenarnya mencegah regresi — bukan assertion config di atas.
     */
    public function test_tidak_ada_lagi_env_engine_di_kode_aplikasi(): void
    {
        $pelanggaran = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $berkas) {
            if ($berkas->getExtension() !== 'php') {
                continue;
            }

            $isi = file_get_contents($berkas->getPathname());

            if (preg_match("/env\(\s*['\"]KPI_(PARAM|PERF)_ENGINE['\"]/", $isi)) {
                $pelanggaran[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $berkas->getPathname());
            }
        }

        $this->assertSame(
            [],
            $pelanggaran,
            "Pakai config('services.kpi.*_engine') — env() mengembalikan default setelah config:cache. Berkas: ".implode(', ', $pelanggaran),
        );
    }
}
