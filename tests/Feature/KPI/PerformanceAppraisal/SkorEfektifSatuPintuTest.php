<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use Tests\TestCase;

/**
 * Penegak satu pintu skor efektif (spec §5.4).
 *
 * Perhitungan dan layar yang membaca kolom `Skor` dari `N_PA_Assignment_Score` langsung akan
 * tampak benar sampai ada Kalibrasi Penilai pertama — lalu diam-diam memakai skor asli untuk
 * orang yang skornya sudah direvisi penilainya.
 *
 * Yang dipindai: literal kolom `Skor` dalam tanda kutip ('Skor', 's.Skor', 'Skor as x') pada
 * berkas di app/ yang juga menyebut `N_PA_Assignment_Score`. Pola ini TIDAK menangkap
 * `select *` — tidak ada satu pun pembaca skor di modul ini yang memakainya, dan bila kelak
 * ada, pemindai ini perlu diperluas.
 *
 * Pengecualian per baris: baris yang memuat komentar `skor-mentah: <alasan>` dilewati, dan
 * alasannya WAJIB diisi.
 *
 * Sejak DDL-2026-119 r2 jawaban esai ikut bisa dikalibrasi, jadi `Jawaban_Teks` dijaga dengan
 * cara yang sama: setiap penyebutan kolom itu di berkas yang menyebut `N_PA_Assignment_Score`
 * harus lewat PaSkorEfektif, atau ditandai `jawaban-mentah: <alasan>` — termasuk di dalam SQL
 * mentah, di mana penandanya ditulis sebagai komentar SQL. MENAMBAH BERKAS KE DAFTAR IZIN ITU BOLEH, tapi harus disengaja:
 * barisnya muncul di diff dan wajib menyebutkan alasannya.
 */
class SkorEfektifSatuPintuTest extends TestCase
{
    private const TABEL = 'N_PA_Assignment_Score';

    private const POLA_KOLOM = "/['\"](?:[A-Za-z]{1,4}\\.)?Skor(?:\\s+as\\s+\\w+)?['\"]/";

    private const PENANDA = 'skor-mentah:';

    private const KOLOM_JAWABAN = 'Jawaban_Teks';

    private const PENANDA_JAWABAN = 'jawaban-mentah:';

    private const DAFTAR_IZIN_JAWABAN = [
        // Pintunya sendiri.
        'app/Services/KPI/PerformanceAppraisal/PaSkorEfektif.php',
        // Penulis: memetakan kiriman HC Learn menjadi baris skor.
        'app/Services/KPI/PerformanceAppraisal/PaPayloadMapperService.php',
        // Perintah semaian pengembangan: MENULIS jawaban contoh, menolak berjalan di production.
        'app/Console/Commands/KPI/PerformanceAppraisal/PaDemoCycleCommand.php',
        'app/Console/Commands/KPI/PerformanceAppraisal/PaSeedSkenarioCommand.php',
        // Pengisi otomatis UAT: MENYUSUN kiriman tiruan (kunci JSON `Jawaban_Teks`), tidak membaca
        // kolomnya, dan menolak berjalan di production.
        'app/Console/Commands/KPI/PerformanceAppraisal/PaUatIsiOtomatisCommand.php',
    ];

    private const DAFTAR_IZIN = [
        // Pintunya sendiri.
        'app/Services/KPI/PerformanceAppraisal/PaSkorEfektif.php',
        // Penulis: memetakan kiriman HC Learn menjadi baris skor.
        'app/Services/KPI/PerformanceAppraisal/PaPayloadMapperService.php',
        // Pemverifikasi menghitung ulang dengan jalannya sendiri dan menerapkan kalibrasi
        // secara independen; memakai PaSkorEfektif akan memeriksa mesin dengan mesin yang sama.
        'app/Console/Commands/KPI/PerformanceAppraisal/PaVerifyCommand.php',
        // Perintah semaian pengembangan: MENULIS baris skor contoh, menolak berjalan di production.
        'app/Console/Commands/KPI/PerformanceAppraisal/PaDemoCycleCommand.php',
        'app/Console/Commands/KPI/PerformanceAppraisal/PaSeedSkenarioCommand.php',
        // Pengisi otomatis UAT: MENYUSUN kiriman tiruan (kunci JSON `Skor`) yang lalu lewat
        // pemeta sungguhan, tidak membaca kolomnya, dan menolak berjalan di production.
        'app/Console/Commands/KPI/PerformanceAppraisal/PaUatIsiOtomatisCommand.php',
    ];

    public function test_hanya_pintu_skor_efektif_yang_membaca_kolom_skor(): void
    {
        $pelanggar = [];

        foreach ($this->berkasTerpindai() as $relatif => $isi) {
            if (in_array($relatif, self::DAFTAR_IZIN, true) || ! str_contains($isi, self::TABEL)) {
                continue;
            }

            foreach (explode("\n", $isi) as $i => $baris) {
                if (preg_match(self::POLA_KOLOM, $baris) && ! str_contains($baris, self::PENANDA)) {
                    $pelanggar[] = $relatif.':'.($i + 1);
                }
            }
        }

        $this->assertSame([], $pelanggar,
            "Kolom Skor hanya boleh dibaca lewat PaSkorEfektif.\n"
            ."Kalau bacaan mentah ini memang sah, tandai barisnya dengan `// skor-mentah: <alasan>`.\n"
            .'Pelanggaran: '.implode('; ', $pelanggar));
    }

    public function test_hanya_pintu_jawaban_efektif_yang_membaca_teks_esai(): void
    {
        $pelanggar = [];
        $ditandai = 0;

        foreach ($this->berkasTerpindai() as $relatif => $isi) {
            if (in_array($relatif, self::DAFTAR_IZIN_JAWABAN, true) || ! str_contains($isi, self::TABEL)) {
                continue;
            }

            foreach (explode("\n", $isi) as $i => $baris) {
                if (! str_contains($baris, self::KOLOM_JAWABAN)) {
                    continue;
                }

                if (str_contains($baris, self::PENANDA_JAWABAN)) {
                    $ditandai++;
                } else {
                    $pelanggar[] = $relatif.':'.($i + 1);
                }
            }
        }

        $this->assertSame([], $pelanggar,
            "Jawaban esai hanya boleh dibaca lewat PaSkorEfektif (jawabanNaratif/timpaJawaban).\n"
            ."Kalau bacaan mentah ini memang sah, tandai barisnya dengan `jawaban-mentah: <alasan>`.\n"
            .'Pelanggaran: '.implode('; ', $pelanggar));

        /* Penjaga atas penjaga: pembaca bertanda yang sudah dikenal harus benar-benar terpindai.
           Turun dari 8 ke 4 pada 26 Sep 2026: empat pemeriksaan ADA-TIDAKNYA jawaban (cakupan
           jenis kualitatif dan dua di dasbor admin, plus detail admin) pindah ke
           PaSkorEfektif::sqlAdaJawaban(), karena jawaban format per soal tinggal di anak esai
           dan memeriksa kolom bagian saja akan melewatkannya. */
        $this->assertGreaterThanOrEqual(4, $ditandai, 'Pemindaian jawaban tidak menyentuh pembaca yang dikenal.');
    }

    public function test_setiap_penanda_skor_mentah_menyebut_alasan(): void
    {
        $kosong = [];

        foreach ($this->berkasTerpindai() as $relatif => $isi) {
            foreach (explode("\n", $isi) as $i => $baris) {
                foreach ([self::PENANDA, self::PENANDA_JAWABAN] as $penanda) {
                    $posisi = strpos($baris, $penanda);

                    if ($posisi !== false && trim(substr($baris, $posisi + strlen($penanda))) === '') {
                        $kosong[] = $relatif.':'.($i + 1);
                    }
                }
            }
        }

        $this->assertSame([], $kosong, 'Penanda skor-mentah/jawaban-mentah tanpa alasan: '.implode('; ', $kosong));
    }

    /** Penjaga atas penjaga: polanya harus benar-benar menangkap bentuk bacaan yang nyata. */
    public function test_pola_menangkap_bacaan_nyata_dan_pemindaian_menyentuh_berkas(): void
    {
        foreach (["->get(['Skor'])", "->whereNotNull('s.Skor')", '->select("s.Skor as nilai")'] as $contoh) {
            $this->assertSame(1, preg_match(self::POLA_KOLOM, $contoh), "Pola gagal menangkap {$contoh}");
        }

        foreach (["'Skor_Maksimal'", "'Skor_Asli'", '`Skor`'] as $bukan) {
            $this->assertSame(0, preg_match(self::POLA_KOLOM, $bukan), "Pola salah menangkap {$bukan}");
        }

        $berkas = $this->berkasTerpindai();
        $this->assertGreaterThan(200, count($berkas), 'Pemindaian hampir tidak menemukan berkas.');
        $this->assertArrayHasKey('app/Services/KPI/PerformanceAppraisal/PaSkorEfektif.php', $berkas);
    }

    /** @return array<string, string> */
    private function berkasTerpindai(): array
    {
        $hasil = [];
        $akar = str_replace('\\', '/', base_path()).'/';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('app')));

        foreach ($iterator as $berkas) {
            if ($berkas->isFile() && $berkas->getExtension() === 'php') {
                $jalur = str_replace('\\', '/', $berkas->getPathname());
                $hasil[str_replace($akar, '', $jalur)] = (string) file_get_contents($berkas->getPathname());
            }
        }

        return $hasil;
    }
}
