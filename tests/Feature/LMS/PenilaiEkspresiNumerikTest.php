<?php

namespace Tests\Feature\LMS;

use App\Services\LMS\Training\Scoring\PenilaiEkspresiNumerik;
use Tests\TestCase;

/**
 * Satu daftar-putih untuk satu eval().
 *
 * ── Kenapa dijaga seketat ini ────────────────────────────────────────────────
 *
 * Penilai ini memanggil eval(). Yang membuatnya aman bukan eval-nya melainkan
 * satu baris di atasnya: daftar-putih yang menolak apa pun selain digit, titik,
 * empat operator, dan tanda kurung.
 *
 * Sebelum dipusatkan, baris itu tersalin TIGA KALI — LmsMateriController,
 * AdminTrainingMonitoringController, dan TrainingExecutionService — dan
 * ketiganya kebetulan masih identik. Yang berbahaya bukan keadaan hari ini
 * melainkan bentuknya: melonggarkan daftar-putih di satu berkas (misalnya
 * menambahkan huruf demi mendukung ROUND) tidak akan meninjau dua salinan
 * lainnya.
 *
 * ── Muatan ujinya sengaja berbahaya-bentuk, bukan berbahaya-efek ─────────────
 *
 * Setiap muatan penolakan di bawah ini dipilih supaya, ANDAI daftar-putihnya
 * jebol, ia menghasilkan angka yang bisa ditegaskan — bukan efek samping. Uji
 * yang menembakkan system() sungguhan akan menjalankannya saat penjaganya
 * rusak, dan itu harga yang tak perlu dibayar untuk mengetahui hal yang sama.
 */
class PenilaiEkspresiNumerikTest extends TestCase
{
    /** @return array<string, array{0: string, 1: float}> */
    public static function ekspresiSah(): array
    {
        return [
            'rumus bobot khas training' => ['(80*0.4)+(90*0.6)', 86.0],
            'urutan operasi dihormati' => ['2+3*4', 14.0],
            'kurung mengubah urutan' => ['(2+3)*4', 20.0],
            'desimal tanpa nol di depan' => ['.5+.5', 1.0],
            'pengurangan negatif' => ['10-20', -20.0 + 10.0],
            'pembagian menghasilkan pecahan' => ['10/4', 2.5],
            'spasi dibuang lebih dulu' => ['  80 * 0.5  +  10  ', 50.0],
            'baris baru juga spasi' => ["80\n*\n0.5", 40.0],
            'nol sah, bukan dianggap kosong' => ['0', 0.0],
            /*
             * Pangkat LOLOS, dan itu memang konsekuensi daftar-putihnya: `*`
             * diizinkan, jadi `**` ikut. Ditulis di sini sebagai perilaku yang
             * disadari — versi pertama uji ini menyangkanya ditolak dan merah.
             * Tidak berbahaya: hasilnya tetap aritmetika, dan pangkat raksasa
             * menjadi INF yang gagal is_numeric-nya di pemanggil.
             */
            'pangkat ikut lolos karena bintang diizinkan' => ['2**3', 8.0],
        ];
    }

    /**
     * @dataProvider ekspresiSah
     */
    public function test_ekspresi_aritmetika_dihitung_benar(string $ekspresi, float $harap): void
    {
        $this->assertSame($harap, PenilaiEkspresiNumerik::nilai($ekspresi));
    }

    /** @return array<string, array{0: string}> */
    public static function ekspresiDitolak(): array
    {
        /*
         * Kolom kedua sengaja tidak ada: yang ditegaskan selalu null. Nama
         * kuncinya menjelaskan APA yang ditolak, dan tiap muatan akan
         * menghasilkan angka (bukan efek samping) seandainya penjaganya jebol.
         */
        return [
            'nama fungsi — huruf tak boleh lolos' => ['strlen(12345)'],
            'fungsi tanpa argumen' => ['time()'],
            'konstanta PHP' => ['PHP_INT_MAX'],
            'notasi ilmiah memakai huruf e' => ['1e3'],
            'heksadesimal memakai huruf x' => ['0x10'],
            'variabel' => ['$x+1'],
            'variabel berkurawal' => ['${x}'],
            'pernyataan kedua lewat titik koma' => ['1;2'],
            'penugasan' => ['1+1=2'],
            'operator penggabungan string' => ['"1"."2"'],
            'petik tunggal' => ["'12'"],
            'backtick' => ['`12`'],
            'koma sebagai pemisah argumen' => ['max(1,2)'],
            'kurung siku' => ['[1][0]'],
            'operator modulo di luar daftar' => ['7%2'],
            'bitwise' => ['6&3'],
            'perbandingan' => ['1<2'],
            'negasi logika' => ['!0'],
            'komentar pagar' => ['1#2'],
            'ternari' => ['1?2:3'],
            'panah objek' => ['1->x'],
            'garis bawah pemisah digit' => ['1_000'],
        ];
    }

    /**
     * @dataProvider ekspresiDitolak
     */
    public function test_karakter_di_luar_daftar_putih_ditolak(string $ekspresi): void
    {
        $this->assertNull(
            PenilaiEkspresiNumerik::nilai($ekspresi),
            "Ekspresi {$ekspresi} lolos daftar-putih dan sampai ke eval(). ".
            'Daftar-putih itu satu-satunya yang berdiri antara rumus training dan '.
            'eksekusi kode sembarang.',
        );
    }

    /** @return array<string, array{0: string}> */
    public static function ekspresiTakTerhitung(): array
    {
        return [
            'kosong' => [''],
            'hanya spasi' => ['   '],
            'kurung tidak seimbang' => ['(1+2'],
            'kurung tutup menggantung' => ['1+2)'],
            'operator menggantung' => ['1+'],
            'titik ganda' => ['1..2'],
            'pembagian nol' => ['1/0'],
            'pembagian nol berdesimal' => ['5/0.0'],
            'kurung kosong' => ['()'],
        ];
    }

    /**
     * @dataProvider ekspresiTakTerhitung
     */
    public function test_ekspresi_yang_tak_bisa_dihitung_menghasilkan_null_bukan_lemparan(string $ekspresi): void
    {
        /*
         * Kegagalan di sini WAJIB jadi null. Pemanggilnya memakai nilai itu
         * untuk menampilkan "nilai akhir belum tersedia"; sebuah lemparan yang
         * lolos akan menjatuhkan seluruh halaman monitoring hanya karena satu
         * rumus salah ketik.
         */
        $this->assertNull(PenilaiEkspresiNumerik::nilai($ekspresi));
    }

    public function test_hasilnya_selalu_float_atau_null(): void
    {
        $hasil = PenilaiEkspresiNumerik::nilai('7');
        $this->assertIsFloat($hasil, 'Bilangan bulat pun harus keluar sebagai float; '.
            'pemanggilnya membulatkan dan memformat tanpa memeriksa tipe.');
        $this->assertSame(7.0, $hasil);
    }

    public function test_tidak_ada_lagi_salinan_evaluator(): void
    {
        /*
         * Ketiga berkas ini dulu memegang salinannya sendiri. Salinan keempat
         * berarti ada daftar-putih yang tidak ikut ditinjau saat yang ini
         * diperketat.
         */
        foreach ([
            'app/Http/Controllers/LMS/Content/Material/LmsMateriController.php',
            'app/Http/Controllers/LMS/Training/Monitoring/AdminTrainingMonitoring/AdminTrainingMonitoringController.php',
            'app/Services/LMS/TrainingExecutionService.php',
        ] as $berkas) {
            $isi = (string) file_get_contents(base_path($berkas));

            $this->assertStringNotContainsString(
                'function evaluateNumericExpression',
                $isi,
                "{$berkas} memegang salinan evaluatornya sendiri lagi.",
            );

            $this->assertStringNotContainsString(
                'eval(',
                $isi,
                "{$berkas} memanggil eval() sendiri. Hanya boleh ada satu tempat, dan "
                .'tempat itu PenilaiEkspresiNumerik.',
            );

            $this->assertStringContainsString(
                'PenilaiEkspresiNumerik::nilai(',
                $isi,
                "{$berkas} tidak lagi memakai penilai bersamanya.",
            );
        }
    }

    /**
     * Kedua situs eval() yang sah di aplikasi ini, beserta penanda
     * daftar-putih yang WAJIB ada di masing-masing.
     *
     * Keduanya tidak digabung karena tata bahasanya memang berbeda: LMS murni
     * aritmetika, KPI mengizinkan sembilan nama fungsi dan operator
     * perbandingan untuk IF(). Menyatukannya berarti memberi salah satu sisi
     * keleluasaan yang tidak ia butuhkan.
     */
    private const SITUS_EVAL = [
        'app/Services/LMS/Training/Scoring/PenilaiEkspresiNumerik.php' => 'self::IZIN',
        'app/Helpers/KpiFormulaHelper.php' => 'self::KARAKTER_DIIZINKAN',
    ];

    public function test_setiap_eval_di_aplikasi_terdaftar_dan_berdaftar_putih(): void
    {
        /*
         * Penjaga terluas di berkas ini: ia menyapu SELURUH app/, bukan hanya
         * bekas pemilik salinan. eval() baru di mana pun akan merah di sini,
         * dan situs yang sudah terdaftar pun merah kalau daftar-putihnya
         * dicabut.
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
            if (preg_match('/\beval\s*\(/', $isi)) {
                $rel = str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], $berkas->getPathname());
                $ditemukan[$rel] = $isi;
            }
        }

        $diharap = array_keys(self::SITUS_EVAL);
        $nyata = array_keys($ditemukan);
        sort($diharap);
        sort($nyata);   // urutan penelusuran direktori bukan bagian dari kontrak

        $this->assertSame(
            $diharap,
            $nyata,
            "Daftar situs eval() berubah.\nDitemukan: ".implode(', ', array_keys($ditemukan))."\n".
            'Setiap eval() baru harus dinilai tersendiri dan dicatat di sini bersama '.
            'daftar-putihnya; eval() tanpa daftar-putih mengubah izin menyunting data '.
            'menjadi izin menjalankan kode.',
        );

        foreach (self::SITUS_EVAL as $rel => $penandaDaftarPutih) {
            $isi = $ditemukan[$rel];

            $posDaftar = strpos($isi, $penandaDaftarPutih);

            /*
             * Pemanggilan sungguhannya, bukan kata "eval()" di docblock —
             * versi pertama penjaga ini tersandung penjelasannya sendiri.
             */
            $posEval = strpos($isi, "eval('return '");
            $this->assertNotFalse($posEval, "{$rel}: bentuk pemanggilan eval() berubah.");

            $this->assertNotFalse(
                $posDaftar,
                "{$rel} memanggil eval() tanpa daftar-putih ({$penandaDaftarPutih} hilang).",
            );

            $this->assertLessThan(
                $posEval,
                $posDaftar,
                "{$rel}: daftar-putihnya harus dideklarasikan sebelum eval() dipanggil.",
            );
        }
    }

    public function test_berkas_penjaga_tidak_kosong(): void
    {
        $this->assertGreaterThan(5000, (int) filesize(__FILE__), 'Berkas penjaga menyusut drastis.');
    }
}
