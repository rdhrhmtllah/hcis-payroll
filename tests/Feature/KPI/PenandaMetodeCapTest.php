<?php

namespace Tests\Feature\KPI;

use Tests\TestCase;

/**
 * Penanda metode cap harus ditulis di SETIAP jalur finalisasi header, dan
 * backfill cap total harus menolak record bermetode PARAMETER.
 *
 * Latar: sejak 28 Agustus 2026 penjepitan pindah ke achievement tiap parameter,
 * sehingga `KPI_Update_Performance.Flag_Capped = 'Y'` bermakna ganda — pada
 * record lama berarti totalnya dipangkas, pada record baru berarti ada
 * parameternya yang dipangkas. `Metode_Cap` yang membedakannya.
 *
 * Penjaga ini memeriksa berkas, bukan efek, dengan sengaja. Yang dilindungi
 * adalah hal yang gagalnya SENYAP: satu jalur insert yang lupa menulis
 * penandanya tidak melempar galat apa pun — ia hanya menghasilkan record baru
 * yang menyamar sebagai record lama, dan baru ketahuan saat backfill terlanjur
 * memangkas skor yang sebenarnya sudah benar.
 */
class PenandaMetodeCapTest extends TestCase
{
    private const PENULIS_HEADER = [
        'app/Http/Controllers/KPI/Performance/Update/UpdatePerformanceController.php',
        'app/Http/Controllers/KPI/Approval/Performance/PerformanceApprovalController.php',
        // Auto-cap enforcement: jalur ketiga yang sempat terlewat (12 Okt 2026 —
        // 588 record auto-cap tersimpan dengan Metode_Cap NULL).
        'app/Services/KPI/KpiEnforcementService.php',
        // Backfill cap per parameter (12 Okt 2026) menulis header lewat kalkulator.
        'app/Services/KPI/KpiCapParameterKalkulator.php',
    ];

    private const BACKFILL = 'app/Services/KPI/KpiCapParameterBackfillService.php';

    /** @test */
    public function setiap_penulisan_flag_capped_header_disertai_metode_cap(): void
    {
        foreach (self::PENULIS_HEADER as $berkas) {
            $baris = explode("\n", file_get_contents(base_path($berkas)));

            foreach ($baris as $i => $isi) {
                // Hanya penulisan Flag_Capped milik HEADER yang diperiksa. Baris
                // detail memakai $calc, bukan $scores, dan jejaknya sudah ada di
                // Score_Raw sehingga tidak butuh penanda ini.
                if (! preg_match("/'Flag_Capped'\s*=>.*\\\$scores\[/", $isi)) {
                    continue;
                }

                $jendela = implode("\n", array_slice($baris, $i, 8));

                $this->assertStringContainsString(
                    "'Metode_Cap'",
                    $jendela,
                    "{$berkas} baris ".($i + 1).": menulis Flag_Capped header tanpa Metode_Cap.\n".
                    "Record hasil jalur ini akan menyamar sebagai record metode lama, dan backfill\n".
                    'cap total berhak memangkas skornya yang sebenarnya sudah dijepit per parameter.',
                );
            }
        }
    }

    /** @test */
    public function backfill_hanya_menyasar_header_yang_belum_parameter(): void
    {
        $isi = file_get_contents(base_path(self::BACKFILL));

        $this->assertStringContainsString("->orWhere('Metode_Cap', '<>', 'PARAMETER')", $isi,
            'Backfill tidak lagi menyaring Metode_Cap — record yang sudah benar ikut dihitung ulang.');
        $this->assertStringContainsString("Metode_Cap !== 'PARAMETER'", $isi,
            'Potongan tidak memeriksa ulang sasaran — header yang berubah sesudah pencacahan ikut ditimpa.');
    }
}
