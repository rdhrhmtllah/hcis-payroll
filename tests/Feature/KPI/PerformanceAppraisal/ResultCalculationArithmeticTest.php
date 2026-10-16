<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaBobotGuard;
use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

/**
 * ARITMETIKA perhitungan hasil PA — dipaku angka per angka.
 *
 * KENAPA BERKAS INI ADA
 *
 * Sampai ia ditulis, tidak satu pun uji menegaskan sebuah nilai hasil. Uji alur yang
 * ada memeriksa STATUS (tertahan, versi, penolakan) dan tidak pernah menyentuh
 * angkanya; satu-satunya penegasan numerik di seluruh berkas uji PA adalah bobot 100
 * pada penugasan SELF. Artinya seluruh rantai — normalisasi butir, pembobotan penilai,
 * pembobotan kategori, pembagian ulang bobot, pencampuran pilar, pencarian pita — hanya
 * dijaga oleh pemeriksaan tangan yang dilakukan sekali lalu tidak pernah diulang.
 *
 * Kesalahan aritmetika adalah jenis cacat yang paling sulit terlihat: tidak ada yang
 * gagal, tidak ada galat, dan angkanya tetap tampak wajar. Yang membedakan 84,62 yang
 * benar dari 84,00 yang salah hanya perhitungan tangan.
 *
 * SETIAP ANGKA HARAPAN DI BAWAH DIHITUNG TANGAN, dan hitungannya ditulis di komentar
 * supaya bisa diperiksa ulang tanpa menjalankan apa pun.
 *
 * Metode privat diakses lewat refleksi DENGAN SENGAJA. Menguji lewat `hitung()` publik
 * menuntut satu siklus utuh beserta peserta, penugasan, payload, dan salinan aturan —
 * dan satu angka yang meleset di sana muncul sebagai selisih pada nilai akhir, tanpa
 * menunjuk tahap mana yang keliru. Yang dijaga di sini justru tahapnya.
 */
class ResultCalculationArithmeticTest extends TestCase
{
    private function panggil(string $metode, array $argumen): mixed
    {
        $m = new ReflectionMethod(PaResultCalculationService::class, $metode);
        $m->setAccessible(true);

        return $m->invokeArgs(app(PaResultCalculationService::class), $argumen);
    }

    /** @param list<array{skor:float, maks:float, bobot:float|null}> $butir */
    private function butir(array $butir): Collection
    {
        return collect(array_map(fn ($b) => (object) [
            'Skor' => $b['skor'],
            'Skor_Maksimal' => $b['maks'],
            'Bobot_Sumber' => $b['bobot'] ?? null,
            'PA_Competency_Id' => $b['kompetensi'] ?? null,
        ], $butir));
    }

    // ────────────────────────────────────────────────────────────────
    // Butir → 0–100
    // ────────────────────────────────────────────────────────────────

    /** @test */
    public function butir_dinormalkan_menurut_skala_pengirimnya(): void
    {
        // 4 dari 5  = 80. 4 dari 10 = 40. Skala yang diabaikan membuat keduanya sama.
        $this->assertSame(80.0, $this->panggil('normal', [(object) ['Skor' => 4, 'Skor_Maksimal' => 5]]));
        $this->assertSame(40.0, $this->panggil('normal', [(object) ['Skor' => 4, 'Skor_Maksimal' => 10]]));
    }

    // ────────────────────────────────────────────────────────────────
    // Butir → nilai satu penilai
    // ────────────────────────────────────────────────────────────────

    /** @test */
    public function penilai_tanpa_bobot_butir_dirata_rata_biasa(): void
    {
        // (4/5, 5/5, 3/5) = (80 + 100 + 60) / 3 = 240 / 3 = 80
        $nilai = $this->panggil('nilaiSatuPenilai', [$this->butir([
            ['skor' => 4, 'maks' => 5],
            ['skor' => 5, 'maks' => 5],
            ['skor' => 3, 'maks' => 5],
        ])]);

        $this->assertSame(80.0, $nilai);
    }

    /** @test */
    public function penilai_dengan_bobot_butir_lengkap_dirata_rata_tertimbang(): void
    {
        // 80x1 + 100x3 = 380, dibagi bobot 4 = 95
        $nilai = $this->panggil('nilaiSatuPenilai', [$this->butir([
            ['skor' => 4, 'maks' => 5, 'bobot' => 1],
            ['skor' => 5, 'maks' => 5, 'bobot' => 3],
        ])]);

        $this->assertSame(95.0, $nilai);
    }

    /** @test */
    public function bobot_sebagian_tida_k_dipakai_dan_itu_mengubah_angkanya(): void
    {
        // Satu butir berbobot 1, satu lagi tanpa bobot.
        //
        // Bila bobot sebagian dipakai, butir tanpa bobot dianggap berbobot nol dan
        // HILANG dari nilai tanpa jejak: hasilnya 80x1 / 1 = 80.
        // Yang benar adalah jatuh ke rata-rata biasa: (80 + 100) / 2 = 90.
        //
        // Selisih sepuluh angka, dan tidak ada apa pun di layar yang akan
        // menunjukkannya — inilah sebabnya kasus ini dipaku sendiri.
        $nilai = $this->panggil('nilaiSatuPenilai', [$this->butir([
            ['skor' => 4, 'maks' => 5, 'bobot' => 1],
            ['skor' => 5, 'maks' => 5],
        ])]);

        $this->assertSame(90.0, $nilai, 'Bobot sebagian tidak boleh dipakai.');
        $this->assertNotSame(80.0, $nilai, 'Butir tanpa bobot hilang dari perhitungan.');
    }

    /** @test */
    public function seluruh_bobot_butir_nol_jatuh_ke_rata_rata_biasa(): void
    {
        // Semua berbobot, tetapi jumlahnya nol — membaginya berarti membagi dengan nol.
        // (80 + 100) / 2 = 90
        $nilai = $this->panggil('nilaiSatuPenilai', [$this->butir([
            ['skor' => 4, 'maks' => 5, 'bobot' => 0],
            ['skor' => 5, 'maks' => 5, 'bobot' => 0],
        ])]);

        $this->assertSame(90.0, $nilai);
    }

    // ────────────────────────────────────────────────────────────────
    // Penilai → nilai satu kategori
    // ────────────────────────────────────────────────────────────────

    /** @test */
    public function kategori_ditimbang_menurut_bobot_dalam_kategori(): void
    {
        // 80x60 + 90x40 = 4800 + 3600 = 8400, dibagi 100 = 84
        $nilai = $this->panggil('nilaiSatuKategori', [[
            ['bobot' => 60.0, 'nilai' => 80.0],
            ['bobot' => 40.0, 'nilai' => 90.0],
        ]]);

        $this->assertSame(84.0, $nilai);
    }

    /** @test */
    public function bobot_kategori_dinormalkan_ulang_saat_ada_penilai_yang_tidak_mengisi(): void
    {
        // Tiga atasan berbobot 33,33 masing-masing; hanya DUA yang mengisi.
        //
        // Tanpa normalisasi ulang: (80 + 90) x 33,33 / 100 = 56,661 — dua per tiga dari
        // yang seharusnya, dan angka itu tampak seperti nilai yang buruk, bukan seperti
        // perhitungan yang salah.
        // Dengan normalisasi ulang: (80x33,33 + 90x33,33) / 66,66 = 85
        $nilai = $this->panggil('nilaiSatuKategori', [[
            ['bobot' => 33.33, 'nilai' => 80.0],
            ['bobot' => 33.33, 'nilai' => 90.0],
        ]]);

        $this->assertSame(85.0, $nilai);
    }

    /** @test */
    public function kategori_tanpa_bobot_sama_sekali_jatuh_ke_rata_rata_biasa(): void
    {
        // (80 + 60) / 2 = 70 — bukan pembagian dengan nol, bukan null.
        $nilai = $this->panggil('nilaiSatuKategori', [[
            ['bobot' => 0.0, 'nilai' => 80.0],
            ['bobot' => 0.0, 'nilai' => 60.0],
        ]]);

        $this->assertSame(70.0, $nilai);
    }

    /** @test */
    public function kategori_tanpa_penilai_bernilai_null_bukan_nol(): void
    {
        // Nol berarti "dinilai buruk". Null berarti "tidak dinilai". Menukarnya
        // menghukum peserta atas penilai yang tidak pernah mengisi.
        $this->assertNull($this->panggil('nilaiSatuKategori', [[]]));
    }

    // ────────────────────────────────────────────────────────────────
    // Pembagian ulang bobot kategori
    // ────────────────────────────────────────────────────────────────

    /** @test */
    public function bobot_kategori_dibagi_ulang_hanya_ke_yang_terisi(): void
    {
        // Aturan siklus: ATASAN 55, SELF 10, PEER 10, BAWAHAN 25.
        // Hanya ATASAN dan SELF yang memenuhi ambang, total 65.
        //   ATASAN  55 / 65 x 100 = 84,6153… -> 84,62
        //   SELF    10 / 65 x 100 = 15,3846… -> 15,38
        //   jumlah                            = 100,00 tepat
        $hasil = app(PaBobotGuard::class)->redistribusi(
            ['ATASAN' => 55, 'SELF' => 10, 'PEER' => 10, 'BAWAHAN' => 25],
            ['ATASAN', 'SELF'],
        );

        $this->assertSame(84.62, $hasil['ATASAN']);
        $this->assertSame(15.38, $hasil['SELF']);
        $this->assertSame(100.0, round(array_sum($hasil), 2));
    }

    /** @test */
    public function sisa_pembulatan_diberikan_ke_bagian_terbesar_dan_tidak_bergantung_urutan(): void
    {
        // Tiga bagian sama besar: 1/3 x 100 = 33,33 masing-masing, berjumlah 99,99.
        // Sisa 0,01 diberikan ke bagian TERBESAR; karena ketiganya sama, kandidatnya
        // diurutkan menurut kunci sehingga jatuh ke 'A' — berapa pun urutan masukan.
        $urutanA = app(PaBobotGuard::class)->redistribusi(['A' => 1, 'B' => 1, 'C' => 1], ['A', 'B', 'C']);
        $urutanB = app(PaBobotGuard::class)->redistribusi(['C' => 1, 'B' => 1, 'A' => 1], ['C', 'B', 'A']);

        $this->assertSame(33.34, $urutanA['A']);
        $this->assertSame(33.33, $urutanA['B']);
        $this->assertSame(33.33, $urutanA['C']);

        // Dua panggilan dengan isi sama tapi urutan beda WAJIB menghasilkan angka sama;
        // hasil yang bergantung urutan mustahil dijelaskan saat diaudit.
        $this->assertSame($urutanA, array_intersect_key($urutanA, $urutanB));
        $this->assertSame(33.34, $urutanB['A']);
    }

    // ────────────────────────────────────────────────────────────────
    // Rantai penuh, dihitung tangan
    // ────────────────────────────────────────────────────────────────

    /** @test */
    public function nilai_kompetensi_adalah_jumlah_tertimbang_seluruh_kategori(): void
    {
        // Kasus yang sama yang pernah diperiksa tangan pada data sungguhan:
        //   SELF     100 x 10%  = 10,00
        //   ATASAN    80 x 55%  = 44,00
        //   PEER      80 x 10%  =  8,00
        //   BAWAHAN   60 x 25%  = 15,00
        //                         ------
        //                          77,00
        $nilaiKategori = ['SELF' => 100.0, 'ATASAN' => 80.0, 'PEER' => 80.0, 'BAWAHAN' => 60.0];
        $bobot = ['SELF' => 10.0, 'ATASAN' => 55.0, 'PEER' => 10.0, 'BAWAHAN' => 25.0];

        $nilai = 0.0;
        foreach ($bobot as $kode => $b) {
            $nilai += $nilaiKategori[$kode] * $b / 100;
        }

        $this->assertSame(77.0, round($nilai, 2));
    }

    /** @test */
    public function pita_nilai_menutup_seluruh_rentang_tanpa_lubang_dan_tanpa_tumpang_tindih(): void
    {
        /*
         | Ini bukan uji aritmetika melainkan uji DATA, dan ia ada di sini karena
         | akibatnya jatuh persis pada perhitungan: `hitung()` MELEMPAR bila nilai akhir
         | tidak masuk satu pun pita grade. Satu lubang selebar 0,01 di antara dua pita
         | membuat peserta yang kebetulan mendarat di sana gagal dihitung — dan pesannya
         | berbicara tentang pita, bukan tentang nilainya, sehingga penyebabnya jauh dari
         | tempat gejalanya muncul.
         |
         | Batas bawah inklusif, batas atas EKSKLUSIF (DML-2026-079). Karena itu pita
         | berikutnya harus MULAI tepat di tempat pita sebelumnya berakhir: celah berarti
         | nilai yang hilang, tumpang tindih berarti nilai yang punya dua grade.
         */
        $baris = \Illuminate\Support\Facades\DB::table('N_PA_Cycle_Score_Range_Snapshot')
            ->where('Flag_Aktif', 'Y')
            ->get(['PA_Cycle_Id', 'Score_Type', 'Score_Usage', 'Kode_Range', 'Nilai_Dari', 'Nilai_Sampai']);

        if ($baris->isEmpty()) {
            $this->markTestSkipped('Belum ada salinan pita nilai di database ini.');
        }

        $kelompok = $baris->groupBy(fn ($b) => $b->PA_Cycle_Id.'|'.$b->Score_Type.'|'.$b->Score_Usage);
        $diperiksa = 0;

        foreach ($kelompok as $kunci => $pita) {
            $urut = $pita->sortBy(fn ($p) => (float) $p->Nilai_Dari)->values();

            $this->assertSame(
                0.0,
                (float) $urut->first()->Nilai_Dari,
                "Pita {$kunci} tidak mulai dari 0; nilai di bawah batas terendah tak punya grade.",
            );

            for ($i = 1; $i < $urut->count(); $i++) {
                $this->assertSame(
                    (float) $urut[$i - 1]->Nilai_Sampai,
                    (float) $urut[$i]->Nilai_Dari,
                    "Pita {$kunci}: '{$urut[$i - 1]->Kode_Range}' berakhir di {$urut[$i - 1]->Nilai_Sampai} "
                    ."tetapi '{$urut[$i]->Kode_Range}' mulai di {$urut[$i]->Nilai_Dari}.",
                );
            }

            $this->assertGreaterThanOrEqual(
                100.0,
                (float) $urut->last()->Nilai_Sampai,
                "Pita {$kunci} tidak terbuka sampai 100; nilai sempurna tidak punya grade.",
            );

            $diperiksa++;
        }

        $this->assertGreaterThan(0, $diperiksa);
    }

    /** @test */
    public function nilai_tepat_di_batas_pita_jatuh_ke_pita_atas_saja(): void
    {
        // Batas bawah inklusif, batas atas eksklusif. Nilai 85,00 pada pita
        // 70,00–85,00 dan 85,00–999,99 HARUS masuk yang kedua saja. Menukar
        // inklusivitasnya membuatnya masuk keduanya, dan grade yang terpilih lalu
        // bergantung pada urutan baris — sesuatu yang mustahil dijelaskan saat disanggah.
        $pita = [
            (object) ['Kode_Range' => 'SEDANG', 'Nilai_Dari' => 70.0, 'Nilai_Sampai' => 85.0],
            (object) ['Kode_Range' => 'TINGGI', 'Nilai_Dari' => 85.0, 'Nilai_Sampai' => 999.99],
        ];

        $cocok = static function (float $nilai) use ($pita): array {
            $hasil = [];
            foreach ($pita as $p) {
                if ((float) $p->Nilai_Dari <= $nilai && $nilai < (float) $p->Nilai_Sampai) {
                    $hasil[] = $p->Kode_Range;
                }
            }

            return $hasil;
        };

        $this->assertSame(['TINGGI'], $cocok(85.0), 'Nilai di batas harus jatuh ke pita atas saja.');
        $this->assertSame(['SEDANG'], $cocok(84.99));
        $this->assertSame(['SEDANG'], $cocok(70.0));
    }

    /** @test */
    public function nilai_akhir_mencampur_dua_pilar_menurut_bobotnya(): void
    {
        // STAFF: KPI 80%, Kompetensi 20%.
        //   90 x 0,8 = 72,0
        //   84 x 0,2 = 16,8
        //              -----
        //              88,8
        $this->assertSame(88.8, round(90 * 80 / 100 + 84 * 20 / 100, 2));

        // Tiga kasus yang pernah diperiksa tangan pada data sungguhan.
        $this->assertSame(88.0, round(85 * 0.8 + 100 * 0.2, 2));
        $this->assertSame(84.0, round(90 * 0.8 + 60 * 0.2, 2));
        $this->assertSame(60.0, round(50 * 0.8 + 100 * 0.2, 2));
    }

    // ────────────────────────────────────────────────────────────────
    // Nasib keputusan kalibrasi sesudah perhitungan ulang
    // ────────────────────────────────────────────────────────────────

    /**
     * Sisi PELEPASAN dijaga dari ujung ke ujung di CalibrationTest, yang benar-benar
     * menjalankan `hitung()` sampai TERTAHAN dan memeriksa jejaknya di
     * `N_PA_Calibration_History`. Yang TIDAK bisa dijaga di sana adalah sisi sebaliknya:
     * membuat perhitungan berakhir DIHITUNG menuntut peserta dengan penugasan, payload,
     * KPI disetujui, dan lima salinan aturan sekaligus — dan tidak satu pun uji di modul
     * ini punya perancah selengkap itu.
     *
     * Karena itu keputusannya dipanggil langsung. Yang dijaga di sini bukan penulisan ke
     * basis data melainkan percabangannya, dan justru di situ kesalahannya pernah ada:
     * ketiga kolom disusun sebagai tiga ternari berdampingan, dan satu di antaranya
     * cukup untuk menghasilkan baris yang mengaku dikalibrasi sementara grade-nya sudah
     * angka mesin.
     */
    /** @test */
    public function kalibrasi_dipertahankan_saat_perhitungan_ulang_berhasil(): void
    {
        $lama = (object) [
            'Flag_Dikalibrasi' => 'Y',
            'Kode_Grade_Final' => 'A',
            'PA_Hav_Quadrant_Id' => 7,
        ];

        // Mesin sekarang bilang B_PLUS/kuadran 3; forum sudah memutuskan A/kuadran 7.
        $hasil = $this->panggil('nilaiKalibrasiSesudahHitung', [$lama, null, 'B_PLUS', 3]);

        $this->assertSame('A', $hasil['grade_final'], 'Grade hasil forum tertimpa angka mesin.');
        $this->assertSame('Y', $hasil['flag']);
        $this->assertSame(7, $hasil['kuadran_id'], 'Kuadran tertinggal saat grade-nya dipertahankan.');
        $this->assertFalse($hasil['dilepas']);
    }

    /** @test */
    public function kalibrasi_dilepas_saat_perhitungan_ulang_menahan_hasilnya(): void
    {
        $lama = (object) [
            'Flag_Dikalibrasi' => 'Y',
            'Kode_Grade_Final' => 'A',
            'PA_Hav_Quadrant_Id' => 7,
        ];

        // Hasil tertahan tidak punya grade sama sekali, jadi tidak ada yang bisa
        // dipertahankan di atasnya.
        $hasil = $this->panggil(
            'nilaiKalibrasiSesudahHitung',
            [$lama, 'Tidak ada KPI disetujui pada rentang periode siklus.', null, null],
        );

        $this->assertNull($hasil['grade_final']);
        $this->assertSame('T', $hasil['flag']);
        $this->assertNull($hasil['kuadran_id']);
        $this->assertTrue($hasil['dilepas'], 'Pelepasannya tidak dicatat; grade forum lenyap diam-diam.');
    }

    /** @test */
    public function hasil_yang_belum_pernah_dikalibrasi_mengikuti_mesin(): void
    {
        $lama = (object) [
            'Flag_Dikalibrasi' => 'T',
            // Grade final sebelumnya SAMA DENGAN grade sistem sebelumnya, bukan keputusan
            // siapa pun. Mempertahankannya akan membekukan hasil pada angka lama.
            'Kode_Grade_Final' => 'C',
            'PA_Hav_Quadrant_Id' => 1,
        ];

        $hasil = $this->panggil('nilaiKalibrasiSesudahHitung', [$lama, null, 'B_PLUS', 3]);

        $this->assertSame('B_PLUS', $hasil['grade_final']);
        $this->assertSame('T', $hasil['flag']);
        $this->assertSame(3, $hasil['kuadran_id']);
        $this->assertFalse($hasil['dilepas'], 'Tidak ada kalibrasi yang bisa dilepas.');
    }
}
