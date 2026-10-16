<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use Tests\TestCase;

/**
 * Penegak kontrak K32: aturan per peserta hanya boleh diresolusi lewat
 * PaParticipantRuleResolver.
 *
 * Lapisan ketiga berbentuk JARANG — hanya menyimpan yang menyimpang. Bentuk itu
 * memindahkan risiko dari jalur tulis ke jalur baca, dan pertukarannya cuma
 * menguntungkan kalau jalur bacanya benar-benar satu. Kalau ada kode lain yang
 * membaca tabel salinan siklus langsung, kode itu akan terlihat benar sampai
 * peserta pertama yang punya override muncul — dan saat itu ia diam-diam
 * memakai aturan standar untuk orang yang justru dikecualikan.
 *
 * Uji ini yang membuat kontraknya punya penegak, bukan sekadar komentar.
 *
 * MENAMBAH BERKAS KE DAFTAR IZIN ITU BOLEH, tapi harus disengaja: barisnya
 * muncul di diff dan wajib menyebutkan alasannya. Yang dilarang adalah
 * menyentuh tabel ini tanpa ada yang tahu.
 */
class ResolverSatuPintuTest extends TestCase
{
    /**
     * Tabel yang resolusinya dimiliki resolver.
     *
     * Tiga tabel salinan siklus dan tiga tabel override. Ketiganya berpasangan:
     * membaca salah satu sisi tanpa sisi lainnya selalu menghasilkan jawaban
     * yang salah untuk peserta yang menyimpang.
     */
    private const TABEL_DIJAGA = [
        'N_PA_Cycle_Rater_Policy_Detail_Snapshot',
        'N_PA_Cycle_Pillar_Weight_Snapshot',
        'N_PA_Cycle_Competency_Requirement_Snapshot',
        'N_PA_Participant_Rater_Override',
        'N_PA_Participant_Pillar_Override',
        'N_PA_Participant_Requirement_Override',
    ];

    /**
     * Berkas yang boleh menyebut tabel di atas, beserta alasannya.
     *
     * Jalur relatif terhadap akar proyek, memakai garis miring maju.
     */
    private const DAFTAR_IZIN = [
        // Pemilik resolusi. Inilah satu pintunya.
        'app/Services/KPI/PerformanceAppraisal/PaParticipantRuleResolver.php',
        // Pemilik pembentukan salinan. Ia MENULIS tiga tabel snapshot saat siklus
        // disiapkan, bukan membacanya untuk menentukan aturan efektif peserta.
        'app/Services/KPI/PerformanceAppraisal/PaCyclePreparationService.php',
        // Jalur TULIS penyimpangan per peserta. Ia MENULIS ketiga tabel override, tetapi
        // tidak pernah membacanya untuk menentukan aturan efektif — untuk itu ia memanggil
        // PaParticipantRuleResolver, termasuk saat memeriksa bahwa bobot gabungan tetap
        // berjumlah 100 sesudah penyimpangan ditulis. Satu pintu resolusi tetap satu pintu.
        'app/Services/KPI/PerformanceAppraisal/PaParticipantOverrideService.php',
        // Tanda "kategori sengaja tidak diikutkan" (13 Okt 2026), jalur TULIS di atas override
        // service. Ia membaca baris override hanya untuk mengenali baris yang ia tulis sendiri
        // (penanda di Alasan_Perubahan), dan salinan level hanya untuk MENAMPILKAN bobot rencana
        // di modal hasil. Aturan efektif selalu dibaca lewat PaParticipantRuleResolver.
        'app/Services/KPI/PerformanceAppraisal/PaKategoriTidakDiikutkanService.php',
        // Perintah siklus contoh untuk pengembangan frontend. Ia hanya MENGHAPUS
        // baris salinan saat membuang siklus ber-kode PA-DEMO-FE (opsi --hapus),
        // tidak pernah membacanya untuk menentukan aturan efektif peserta —
        // seluruh resolusi aturannya tetap lewat PaParticipantRuleResolver.
        // Perintahnya juga menolak berjalan di production.
        'app/Console/Commands/KPI/PerformanceAppraisal/PaDemoCycleCommand.php',
        // Perintah semaian skenario, sekategori dengan PaDemoCycleCommand di atas. Ketiga
        // tabel salinan hanya muncul di jalur PENGHAPUSAN — saat membuang siklus semaian
        // yang lama — dan tidak pernah dibaca untuk menyimpulkan aturan efektif seseorang.
        // Perintahnya juga menolak berjalan di production.
        //
        // Kalau suatu saat ia mulai MEMBACA salah satunya untuk menentukan aturan efektif,
        // barisnya harus dicabut dari daftar ini, bukan dibiarkan karena sudah terlanjur ada.
        'app/Console/Commands/KPI/PerformanceAppraisal/PaSeedSkenarioCommand.php',
        // Jalur TULIS bobot pilar per siklus (K23). Ia MENULIS dan MEMBACA
        // N_PA_Cycle_Pillar_Weight_Snapshot pada tingkat LEVEL - untuk menampilkan dan
        // menyunting bobot standar level, bukan untuk menentukan bobot efektif satu
        // orang. Bobot efektif per peserta tetap hanya lewat PaParticipantRuleResolver,
        // karena hanya resolver yang tahu apakah orang itu punya penyimpangan.
        'app/Services/KPI/PerformanceAppraisal/PaCyclePillarWeightService.php',
        // Lini masa audit (A12). Ia membaca ketiga tabel override sebagai RIWAYAT —
        // "penyimpangan ini pernah ditulis, oleh siapa, dengan alasan apa" — dan sengaja
        // ikut menampilkan baris yang sudah dicabut, yang justru TIDAK BOLEH ikut dalam
        // resolusi. Ia tidak pernah menyimpulkan aturan efektif siapa pun; kalau suatu
        // saat ia mulai melakukannya, barisnya harus dicabut dari daftar ini, bukan
        // dibiarkan karena sudah terlanjur ada.
        'app/Services/KPI/PerformanceAppraisal/PaAuditService.php',
        // Setup siklus (A09). Satu-satunya sentuhannya adalah MENCACAH baris per siklus
        // untuk ringkasan kesiapan ("salinan bobot pilar: 4"), bukan membaca isinya.
        // Cacah tingkat siklus tidak bisa keliru terhadap peserta yang menyimpang,
        // karena ia memang tidak berbicara tentang peserta mana pun.
        'app/Services/KPI/PerformanceAppraisal/PaCycleSetupService.php',
        /* Pemverifikasi perhitungan (`pa:verify`). Ia membaca ketiga tabel salinan
           LANGSUNG, dan justru KEHARUSAN itu yang membuatnya berguna: tugasnya menghitung
           ulang hasil dari data mentah lalu membandingkannya dengan yang tersimpan.
           Memakai resolver di sana berarti memeriksa mesin dengan mesin yang sama —
           kesalahan rumus akan salah dengan cara yang sama di kedua sisi lalu cocok
           sempurna, dan pemeriksanya melaporkan nol selisih pada hasil yang keliru.
           Perintahnya read-only dan tidak pernah menentukan aturan efektif siapa pun;
           ia hanya menirukan perhitungan untuk membandingkannya. */
        'app/Console/Commands/KPI/PerformanceAppraisal/PaVerifyCommand.php',

        /* ------------------------------------------------------------------ *
         | EMPAT BERKAS DI BAWAH SUDAH MELANGGAR SEJAK MODULNYA MASUK (7f3901d4),
         | dan uji ini merah sejak hari itu. Diperiksa satu per satu pada 6 September
         | 2026: seluruhnya membaca pada lingkup SIKLUS atau LEVEL — bukan menentukan
         | aturan efektif seorang peserta, yang memang hanya boleh lewat resolver.
         |
         | Dicatat, bukan disembunyikan: aturannya tetap berlaku, dan yang berubah
         | cuma pengakuan bahwa keempat pemakaian ini memang di luar jangkauannya.
         * ------------------------------------------------------------------ */

        /* Laporan kesehatan penugasan se-siklus. Membaca minimum penilai per
           (level, kategori) untuk membandingkannya dengan penugasan yang benar-benar
           terbentuk. Tidak pernah bertanya "berapa aturan efektif ORANG ini" —
           pertanyaannya "berapa yang seharusnya ada pada level ini". */
        'app/Services/KPI/PerformanceAppraisal/PaAssignmentHealthService.php',

        /* Jalur TULIS dan layar aturan penilai per siklus. Ia menyunting salinan pada
           tingkat level, alasan yang sama persis dengan PaCyclePillarWeightService yang
           sudah ada di daftar ini. */
        'app/Services/KPI/PerformanceAppraisal/PaCycleRaterPolicyService.php',

        /* Penanda kemajuan langkah penyiapan. Ia MENGHITUNG — berapa level punya bobot,
           apakah semuanya genap 100 — untuk menandai satu langkah selesai. Angka itu
           tentang siklusnya, bukan tentang siapa pun. */
        'app/Services/KPI/PerformanceAppraisal/PaCycleSetupStepService.php',

        /* Pengikatan paket soal. Membaca pasangan (level, kategori) yang berlaku di
           siklus untuk menentukan sel mana yang perlu paket. Sekali lagi: peta level,
           bukan aturan seseorang. */
        'app/Services/KPI/PerformanceAppraisal/PaQuestionBindingService.php',
    ];

    /**
     * Direktori yang dipindai. Sengaja tidak menyertakan database/ karena
     * artefak DDL memang harus menyebut nama tabelnya, dan docs/ karena
     * dokumentasi justru wajib membicarakannya.
     */
    private const DIPINDAI = [
        'app',
        'routes',
        'resources/js',
    ];

    public function test_hanya_resolver_yang_menyentuh_tabel_aturan_per_peserta(): void
    {
        $pelanggar = [];

        foreach ($this->berkasTerpindai() as $relatif => $isi) {
            if (in_array($relatif, self::DAFTAR_IZIN, true)) {
                continue;
            }

            foreach (self::TABEL_DIJAGA as $tabel) {
                if (str_contains($isi, $tabel)) {
                    $pelanggar[] = "{$relatif} menyebut {$tabel}";
                }
            }
        }

        $this->assertSame(
            [],
            $pelanggar,
            "Tabel aturan per peserta hanya boleh dibaca lewat PaParticipantRuleResolver.\n"
            ."Kalau berkas ini memang berhak, tambahkan ke DAFTAR_IZIN beserta alasannya.\n"
            .'Pelanggaran: '.implode('; ', $pelanggar)
        );
    }

    /**
     * Penjaga atas penjaganya sendiri.
     *
     * Penjaga yang memindai berkas gampang mati diam-diam: salah jalur, filter
     * ekstensi yang keliru, atau direktori yang pindah membuat pemindaian tidak
     * menyentuh apa pun — dan operasi yang tidak menyentuh apa pun selalu
     * menghasilkan angka sempurna. Uji ini menuntut pemindaiannya benar-benar
     * bekerja: berkas resolvernya sendiri harus ketemu, dan isinya harus memuat
     * salah satu nama tabel yang dijaga.
     */
    public function test_pemindaian_benar_benar_menyentuh_berkas(): void
    {
        $berkas = $this->berkasTerpindai();

        $this->assertGreaterThan(
            200,
            count($berkas),
            'Pemindaian hampir tidak menemukan berkas apa pun — jalurnya kemungkinan salah.'
        );

        $resolver = self::DAFTAR_IZIN[0];

        $this->assertArrayHasKey(
            $resolver,
            $berkas,
            "Berkas resolver tidak ikut terpindai ({$resolver}); penjaganya tidak menjaga apa-apa."
        );

        $ketemu = false;

        foreach (self::TABEL_DIJAGA as $tabel) {
            if (str_contains($berkas[$resolver], $tabel)) {
                $ketemu = true;

                break;
            }
        }

        $this->assertTrue(
            $ketemu,
            'Resolver tidak menyebut satu pun tabel yang dijaga — '
            .'entah nama tabelnya berubah, entah pencocokannya rusak.'
        );
    }

    /**
     * @return array<string, string> jalur relatif => isi berkas
     */
    private function berkasTerpindai(): array
    {
        $akar = str_replace('\\', '/', rtrim(base_path(), '/\\'));

        $hasil = [];

        foreach (self::DIPINDAI as $direktori) {
            $penuh = "{$akar}/{$direktori}";

            if (! is_dir($penuh)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($penuh, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $berkas) {
                if (! $berkas->isFile()) {
                    continue;
                }

                $ext = strtolower($berkas->getExtension());

                if (! in_array($ext, ['php', 'js', 'vue', 'ts'], true)) {
                    continue;
                }

                $jalur = str_replace('\\', '/', $berkas->getPathname());
                $relatif = ltrim(str_replace($akar, '', $jalur), '/');

                $hasil[$relatif] = (string) file_get_contents($jalur);
            }
        }

        return $hasil;
    }
}
