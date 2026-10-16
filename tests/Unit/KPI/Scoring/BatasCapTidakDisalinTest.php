<?php

namespace Tests\Unit\KPI\Scoring;

use App\Services\KPI\KpiScoringService;
use Tests\TestCase;

/**
 * Batas cap tidak boleh punya baku tandingan di luar sumber kanoniknya.
 *
 * Sebelum penjaga ini ada, empat tempat menyalin ulang pembacaan config-nya
 * dengan baku 150/0 — menyimpang dari 125/50 yang sebenarnya berlaku, dan
 * masing-masing membawa komentar yang justru menyatakan ingin "konsisten
 * dengan skor bulanan resmi". Selama config terisi, ketidaksesuaian itu tak
 * terlihat sama sekali; ia baru hidup begitu kunci config-nya dikosongkan atau
 * di-null-kan, dan saat itu dashboard memangkas di 150/0 sementara service
 * kanonik memangkas di 125/50.
 *
 * Yang dijaga BUKAN keberadaan pembacaan config-nya (beberapa pemanggil memang
 * sah membacanya untuk ditampilkan), melainkan BAKU yang dipakai: baku apa pun
 * selain 125/50 berarti ada aturan kedua yang menunggu giliran.
 */
class BatasCapTidakDisalinTest extends TestCase
{
    private const BAKU = [
        'max_achievement_score' => '125',
        'min_achievement_score' => '50',
    ];

    /** Seluruh berkas PHP di app/, kecuali sumber kanoniknya sendiri. */
    private function berkasApp(): array
    {
        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));
        $hasil = [];

        foreach ($iter as $f) {
            if ($f->isFile()
                && $f->getExtension() === 'php'
                && $f->getFilename() !== 'KpiScoringService.php') {
                $hasil[] = $f->getPathname();
            }
        }

        sort($hasil);

        return $hasil;
    }

    public function test_tidak_ada_baku_tandingan_untuk_batas_cap(): void
    {
        $pelanggaran = [];

        foreach ($this->berkasApp() as $path) {
            $isi = file_get_contents($path);

            foreach (self::BAKU as $kunci => $bakuSah) {
                // config('services.kpi.<kunci>', <baku>) — hanya yang MEMBAWA baku.
                $pola = "/config\(\s*['\"]services\.kpi\.".preg_quote($kunci, '/')."['\"]\s*,\s*([^)]+?)\s*\)/";

                if (! preg_match_all($pola, $isi, $cocok)) {
                    continue;
                }

                foreach ($cocok[1] as $baku) {
                    if (trim($baku) !== $bakuSah) {
                        $pelanggaran[] = sprintf(
                            '%s: %s berbaku %s, seharusnya %s',
                            str_replace(app_path().DIRECTORY_SEPARATOR, '', $path),
                            $kunci,
                            trim($baku),
                            $bakuSah,
                        );
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $pelanggaran,
            "Baku cap tandingan ditemukan:\n  ".implode("\n  ", $pelanggaran).
            "\n\nPakai KpiScoringService::capBounds()/capValue(), atau samakan bakunya dengan 125/50.",
        );
    }

    /**
     * Penjaga di atas hanya memeriksa TEKS. Ini memeriksa EFEKNYA, supaya
     * angkanya benar-benar berlaku dan bukan sekadar tertulis benar.
     */
    public function test_baku_kanonik_benar_benar_125_dan_50(): void
    {
        [$max, $min] = app(KpiScoringService::class)->capBounds();

        $this->assertSame(125.0, $max);
        $this->assertSame(50.0, $min);
        $this->assertSame(125.0, app(KpiScoringService::class)->capValue(999));
        $this->assertSame(50.0, app(KpiScoringService::class)->capValue(-999));
    }
}
