<?php

namespace Tests\Feature\LMS;

use Tests\TestCase;

/**
 * Penyalinan struktur silabus -> penjadwalan hanya boleh punya SATU rumah.
 *
 * Metode `ensurePenjadwalanStructure()` pernah hidup sebagai salinan IDENTIK
 * BYTE-PER-BYTE di dua controller: LmsMateriController dan
 * TrainingCatalogController, 76 baris masing-masing. Keduanya menulis ke
 * N_LMS_Penjadwalan_Detail dan N_LMS_Penjadwalan_Det dengan aturan snapshot
 * yang sama persis.
 *
 * KENAPA ITU BERBAHAYA DI MODUL INI, bukan sekadar tidak rapi. Aturan
 * snapshot-nya halus: kalau detail penjadwalan sudah pernah tersimpan, struktur
 * TIDAK boleh disinkronkan ulang ke silabus terbaru — kalau tidak, modul yang
 * baru ditambahkan di silabus akan muncul pada jadwal lama, dan peserta yang
 * sudah selesai tiba-tiba punya pekerjaan yang belum selesai. Memperbaiki atau
 * menyetel aturan sehalus itu di satu salinan sambil melewatkan salinan
 * satunya adalah pola yang sudah berulang di modul ini.
 *
 * Yang dijaga penjaga ini: badan logikanya tinggal di service, dan kedua
 * controller hanya meneruskan.
 */
class StrukturPenjadwalanSatuTempatTest extends TestCase
{
    private const SERVICE = 'app/Services/LMS/Training/Scheduling/PenjadwalanStrukturService.php';

    private const PEMANGGIL = [
        'app/Http/Controllers/LMS/Content/Material/LmsMateriController.php',
        'app/Http/Controllers/LMS/Training/Catalog/TrainingCatalogController.php',
    ];

    /**
     * Penanda ISI, bukan nama metode: nama boleh saja sama di beberapa tempat
     * (itu wajar), yang tak boleh adalah badan penulisannya tersalin.
     */
    private const PENANDA_BADAN = [
        "DB::table('N_LMS_Penjadwalan_Detail')->insertGetId(",
        "DB::table('N_LMS_Penjadwalan_Det')->insert(",
    ];

    public function test_logikanya_tinggal_di_service(): void
    {
        $isi = $this->baca(self::SERVICE);

        foreach (self::PENANDA_BADAN as $penanda) {
            $this->assertStringContainsString(
                $penanda,
                $isi,
                'Service kehilangan penulisan strukturnya — logikanya pindah ke tempat lain?',
            );
        }
    }

    public function test_tak_ada_controller_yang_menulis_strukturnya_sendiri(): void
    {
        foreach (self::PEMANGGIL as $berkas) {
            $isi = $this->baca($berkas);

            foreach (self::PENANDA_BADAN as $penanda) {
                $this->assertStringNotContainsString(
                    $penanda,
                    $isi,
                    "{$berkas} menulis struktur penjadwalannya sendiri. "
                    .'Badan itu milik PenjadwalanStrukturService; salinan kedua berarti '
                    .'perbaikan berikutnya harus mendarat dua kali, dan yang kedua akan terlewat.',
                );
            }
        }
    }

    public function test_kedua_controller_meneruskan_ke_service_yang_sama(): void
    {
        foreach (self::PEMANGGIL as $berkas) {
            $this->assertStringContainsString(
                'PenjadwalanStrukturService::class',
                $this->baca($berkas),
                "{$berkas} tak lagi meneruskan ke PenjadwalanStrukturService.",
            );
        }
    }

    private function baca(string $relatif): string
    {
        $penuh = base_path($relatif);

        $this->assertFileExists($penuh, "Berkas {$relatif} tidak ada.");

        return (string) file_get_contents($penuh);
    }
}
