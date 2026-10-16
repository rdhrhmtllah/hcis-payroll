<?php

namespace Tests\Feature\LMS;

use Tests\TestCase;

/**
 * Tiga mesin nilai akhir training, dan kontrak yang WAJIB mereka bagi.
 *
 * ── Keadaannya ───────────────────────────────────────────────────────────────
 *
 * Nilai akhir training dihitung di TIGA tempat:
 *
 *   LmsMateriController::buildFinalScoreSummary()            188 baris — MENAMPILKAN
 *   AdminTrainingMonitoringController::buildFinalScoreSummary() 170 baris — MENAMPILKAN
 *   TrainingExecutionService::computeFinalScoreUpdates()      176 baris — MENYIMPAN
 *
 * Dua yang pertama berbagi 136 dari ~160 baris: perbedaannya hanya perakitan
 * muatan (kunci tambahan, teks tooltip, dan `final_score_display` yang bernilai
 * '-' di satu sisi dan null di sisi lain — keduanya BENAR untuk layarnya
 * masing-masing; monitoring menyembunyikan lencananya lewat v-if).
 *
 * Yang ketiga hanya berbagi 68 baris dan memang berbeda: ia menerapkan
 * $requireCompleted, menuntut SEMUA skor terisi sebelum sebuah komponen
 * dianggap siap, lalu meneruskan hasilnya ke Total_Score, Score_Learning_Gain,
 * dan penentuan kelulusan.
 *
 * ── Kenapa ketiganya TIDAK disatukan ─────────────────────────────────────────
 *
 * Sudah dicoba diadu pada data nyata: dari 60 pasangan (penjadwalan, materi)
 * yang punya rumus, hanya SATU yang menghasilkan angka — dan di situ ketiganya
 * sepakat (100). Lima puluh sembilan sisanya null di semua mesin.
 *
 * Satu sampel bukan bukti kesetaraan. Menyatukan mesin penilaian yang
 * divergensinya tidak bisa dijalankan berarti menukar duplikasi yang terlihat
 * dengan perubahan perilaku yang tidak terlihat. Jadi ketiganya dibiarkan, dan
 * yang dipatok di sini adalah kontrak yang PASTI harus mereka bagi.
 *
 * ── Yang dipatok ─────────────────────────────────────────────────────────────
 *
 * Sumber rumus, cara token diurai, dan evaluator yang menghitungnya. Selama
 * ketiganya sama, ketiga mesin membaca rumus yang sama persis dan
 * menghitungnya dengan penilai yang sama; yang boleh berbeda tinggal cara
 * komponen dinilai. Begitu salah satunya menyimpang di tiga hal itu, mereka
 * dijamin berbeda — dan uji ini merah.
 */
class TigaMesinNilaiTrainingTest extends TestCase
{
    private const MESIN = [
        'app/Http/Controllers/LMS/Content/Material/LmsMateriController.php' => 'buildFinalScoreSummary',
        'app/Http/Controllers/LMS/Training/Monitoring/AdminTrainingMonitoring/AdminTrainingMonitoringController.php' => 'buildFinalScoreSummary',
        'app/Services/LMS/TrainingExecutionService.php' => 'computeFinalScoreUpdates',
    ];

    private function isi(string $berkas): string
    {
        return (string) file_get_contents(base_path($berkas));
    }

    public function test_ketiga_mesin_masih_ada_dan_tidak_bertambah(): void
    {
        /*
         * Bertambahnya mesin keempat jauh lebih berbahaya daripada tiga yang
         * ada, karena tiga yang ada sudah terdokumentasi. Yang disapu SELURUH
         * app/, bukan daftar berkas — pelajaran dari PR #357 dan #359, yang
         * dua-duanya melewatkan satu salinan karena bekerja dari daftar.
         */
        $ditemukan = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $berkas) {
            if ($berkas->getExtension() !== 'php') {
                continue;
            }

            $isi = (string) file_get_contents($berkas->getPathname());
            if (! str_contains($isi, "DB::table('N_LMS_Master_Rumus')->where('Id_Master_Rumus'")) {
                continue;
            }

            $ditemukan[] = str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], $berkas->getPathname());
        }

        sort($ditemukan);
        $diharap = array_keys(self::MESIN);
        sort($diharap);

        $this->assertSame(
            $diharap,
            $ditemukan,
            "Jumlah mesin nilai akhir berubah.\nDitemukan: ".implode(', ', $ditemukan)."\n".
            'Mesin keempat berarti satu lagi tempat yang bisa menyimpang dari nilai yang '.
            'BENAR-BENAR disimpan, dan yang ini tidak terdokumentasi.',
        );
    }

    public function test_ketiganya_mengurai_token_rumus_dengan_cara_yang_sama(): void
    {
        /*
         * Kalau salah satu mengurai token secara berbeda, ia membaca RUMUS yang
         * berbeda — dan sesudah itu tidak ada gunanya membandingkan hasilnya.
         * Ini kontrak paling dasar di antara ketiganya.
         */
        foreach (self::MESIN as $berkas => $metode) {
            $this->assertStringContainsString(
                "preg_match_all('/\\[(.*?)\\]/', (string) \$formula->Rumus, \$matches)",
                $this->isi($berkas),
                "{$berkas} mengurai token rumus dengan cara yang berbeda dari dua mesin lainnya.",
            );
        }
    }

    public function test_ketiganya_memakai_penilai_ekspresi_yang_sama(): void
    {
        /*
         * Dipusatkan di PR #354. Kalau salah satunya kembali memakai evaluator
         * sendiri, daftar-putih eval() pun tidak lagi satu — lihat
         * PenilaiEkspresiNumerikTest.
         */
        foreach (self::MESIN as $berkas => $metode) {
            $isi = $this->isi($berkas);

            $this->assertStringContainsString(
                'PenilaiEkspresiNumerik::nilai(',
                $isi,
                "{$berkas} tidak lagi memakai penilai ekspresi bersama.",
            );

            $this->assertStringNotContainsString(
                'function evaluateNumericExpression',
                $isi,
                "{$berkas} kembali memegang evaluatornya sendiri.",
            );
        }
    }

    public function test_hanya_mesin_penyimpan_yang_menuntut_semua_komponen_terisi(): void
    {
        /*
         * $requireCompleted adalah perbedaan yang DISENGAJA, dan ia hanya milik
         * jalur yang menyimpan: yang menampilkan boleh memperlihatkan angka
         * sementara, yang menyimpan tidak boleh.
         *
         * Dipatok supaya perbedaan itu terbaca sebagai keputusan. Kalau ia
         * muncul di jalur tampilan, seseorang sedang menyalin mesin penyimpan
         * ke tempat yang salah.
         */
        $penyimpan = $this->isi('app/Services/LMS/TrainingExecutionService.php');
        $this->assertStringContainsString('$requireCompleted', $penyimpan);

        foreach ([
            'app/Http/Controllers/LMS/Content/Material/LmsMateriController.php',
            'app/Http/Controllers/LMS/Training/Monitoring/AdminTrainingMonitoring/AdminTrainingMonitoringController.php',
        ] as $penampil) {
            $this->assertStringNotContainsString(
                '$requireCompleted',
                $this->isi($penampil),
                "{$penampil} mengambil aturan milik jalur penyimpan.",
            );
        }
    }

    public function test_perbedaan_penyajian_kedua_penampil_tetap_seperti_adanya(): void
    {
        /*
         * final_score_display berbeda dengan SENGAJA:
         *
         *   izinPage/materi  '-'   ditampilkan apa adanya di teks
         *   monitoring       null  lencananya disembunyikan v-if; '-' yang
         *                          truthy justru memunculkan lencana "Skor -"
         *
         * Diperiksa di MonitoringPage.vue baris 547. Menyamakannya "supaya
         * rapi" mengubah apa yang terlihat di layar monitoring.
         */
        $this->assertStringContainsString(
            "number_format((float) \$finalScore, 2) : '-'",
            $this->isi('app/Http/Controllers/LMS/Content/Material/LmsMateriController.php'),
            'Jalur materi berhenti memakai "-" sebagai nilai tampilan kosong.',
        );

        $this->assertStringContainsString(
            'number_format((float) $finalScore, 2) : null',
            $this->isi('app/Http/Controllers/LMS/Training/Monitoring/AdminTrainingMonitoring/AdminTrainingMonitoringController.php'),
            'Jalur monitoring berhenti memakai null; "-" yang truthy akan memunculkan '.
            'lencana "Skor -" di layar yang seharusnya menyembunyikannya.',
        );
    }

    public function test_berkas_penjaga_tidak_kosong(): void
    {
        $this->assertGreaterThan(5000, (int) filesize(__FILE__), 'Berkas penjaga menyusut drastis.');
    }
}
