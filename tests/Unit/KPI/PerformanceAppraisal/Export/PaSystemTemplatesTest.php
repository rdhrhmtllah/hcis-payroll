<?php

namespace Tests\Unit\KPI\PerformanceAppraisal\Export;

use App\Services\KPI\PerformanceAppraisal\Export\PaHasilDataset;
use App\Services\KPI\PerformanceAppraisal\Export\PaKompetensiDataset;
use App\Services\KPI\PerformanceAppraisal\Export\PaPenugasanDataset;
use App\Services\KPI\PerformanceAppraisal\Export\PaPesertaDataset;
use App\Services\KPI\PerformanceAppraisal\Export\PaSystemTemplates;
use Tests\TestCase;

class PaSystemTemplatesTest extends TestCase
{
    public function test_setiap_template_hanya_memakai_kunci_yang_benar_benar_ada(): void
    {
        // Template sistem yang menyebut kunci yang tidak ada tidak menggagalkan
        // apa pun saat dimuat -- kolomnya hanya digugurkan diam-diam, dan yang
        // membuka berkasnya menyangka datanya memang kosong.
        $dataset = ['hasil' => new PaHasilDataset, 'penugasan' => new PaPenugasanDataset, 'kompetensi' => new PaKompetensiDataset, 'peserta' => new PaPesertaDataset];

        foreach (PaSystemTemplates::semua() as $t) {
            $d = $dataset[$t['dataset']];
            $dimensiSah = array_map(fn ($x) => $x->key, $d->dimensi());
            $ukuranSah = array_map(fn ($x) => $x->key, $d->ukuran());

            foreach ($t['definisi']['dimensi'] as $k) {
                $this->assertContains($k, $dimensiSah, "Template {$t['kode']} menyebut dimensi {$k} yang tidak ada.");
            }
            foreach ($t['definisi']['ukuran'] as $k) {
                $this->assertContains($k, $ukuranSah, "Template {$t['kode']} menyebut ukuran {$k} yang tidak ada.");
            }
        }
    }

    public function test_dua_template_tahap_satu_tersedia(): void
    {
        $kode = array_column(PaSystemTemplates::semua(), 'kode');

        $this->assertContains('pa-rekap-nilai-akhir', $kode);
        $this->assertContains('pa-papan-kemajuan', $kode);
    }

    /**
     * Penjaga khusus mode 'rinci'.
     *
     * `test_setiap_template_hanya_memakai_kunci_yang_benar_benar_ada` di atas
     * memeriksa $t['definisi']['dimensi'] dan $t['definisi']['ukuran'] -- tapi
     * template mode 'rinci' (seperti pa-rekap-nilai-akhir) SELALU mengosongkan
     * kedua larik itu (lihat PaSystemTemplates::rinci()) dan menaruh kolomnya
     * di $t['definisi']['kolom'] sebagai gantinya. DefinisiValidator::periksa()
     * memvalidasi 'kolom' HANYA terhadap dimensi() dataset, tidak pernah
     * terhadap ukuran() -- jadi menyebut kunci ukuran (mis. kunci agregat
     * seperti 'final_score') di 'kolom' template rinci lolos dari uji di atas
     * tanpa terdeteksi, padahal akan gagal validasi nyata setiap kali
     * dipratinjau/dijalankan. Uji ini memeriksa jalur yang sesungguhnya dipakai
     * mesin laporan.
     */
    public function test_kolom_template_rinci_hanya_memakai_dimensi_yang_benar_benar_ada(): void
    {
        $dataset = ['hasil' => new PaHasilDataset, 'penugasan' => new PaPenugasanDataset, 'kompetensi' => new PaKompetensiDataset, 'peserta' => new PaPesertaDataset];

        foreach (PaSystemTemplates::semua() as $t) {
            if ($t['mode'] !== 'rinci') {
                continue;
            }

            $d = $dataset[$t['dataset']];
            $dimensiSah = array_map(fn ($x) => $x->key, $d->dimensi());

            foreach ($t['definisi']['kolom'] as $k) {
                $this->assertContains($k, $dimensiSah, "Template {$t['kode']} menyebut kolom {$k} yang bukan dimensi.");
            }
        }
    }
}
