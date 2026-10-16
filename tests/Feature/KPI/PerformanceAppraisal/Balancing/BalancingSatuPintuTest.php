<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Balancing;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Skor After Balancing hanya boleh DIBACA lewat PaBalancingNilaiBerlaku (spec §5).
 *
 * Pembaca kedua yang menerapkan After sendiri — radar, ekspor, laporan — akan diam-diam
 * berbeda dari mesin hitung begitu aturan "sesi FINAL terbaru" berubah. Penulis isian
 * (sesi) dan tampilan riwayat sesi boleh menyentuhnya; semuanya disebut di daftar izin.
 */
class BalancingSatuPintuTest extends TestCase
{
    private const KOLOM = ['Nilai_Sesudah', 'Isi_Sesudah'];

    private const DAFTAR_IZIN = [
        // Pintunya sendiri.
        'app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingNilaiBerlaku.php',
        // Penulis isian sesi dan pembaca detail sesi (menampilkan isian, bukan menghitung).
        'app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingSesiService.php',
        // Simulasi membaca isian sesi yang SEDANG dibahas sebagai titik awal isian layar.
        'app/Services/KPI/PerformanceAppraisal/Balancing/PaBalancingSimulasiService.php',
    ];

    public function test_hanya_pintu_nilai_berlaku_yang_membaca_after(): void
    {
        $akar = base_path('app');
        $pelanggar = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($akar)) as $berkas) {
            if (! $berkas->isFile() || $berkas->getExtension() !== 'php') {
                continue;
            }

            $relatif = 'app/'.ltrim(str_replace($akar, '', $berkas->getPathname()), '/');

            if (in_array($relatif, self::DAFTAR_IZIN, true)) {
                continue;
            }

            $isi = file_get_contents($berkas->getPathname());

            // Kolom bernama sama ada di modul lain (mis. ESS); yang dijaga hanya bacaan tabel Balancing.
            if (! str_contains($isi, 'N_PA_Balancing_Kompetensi') && ! str_contains($isi, 'N_PA_Balancing_Kualitatif')) {
                continue;
            }

            foreach (self::KOLOM as $kolom) {
                if (str_contains($isi, $kolom)) {
                    $pelanggar[] = "{$relatif} ({$kolom})";
                }
            }
        }

        $this->assertSame([], $pelanggar,
            "After Balancing hanya boleh dibaca lewat PaBalancingNilaiBerlaku.\n"
            .'Pelanggaran: '.implode('; ', $pelanggar));
    }
}
