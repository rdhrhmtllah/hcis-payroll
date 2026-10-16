<?php

namespace Tests\Feature\LMS;

use Tests\TestCase;

/**
 * Kunci anti double-dispatch harus ATOMIK, dan harus terpasang SEBELUM efeknya.
 *
 * ── Cacat yang dijaga ────────────────────────────────────────────────────────
 *
 * Bentuk lama di setiap pintu bukan kunci sama sekali, melainkan cek-lalu-tindak:
 *
 *     if (Cache::store('database')->has($lockKey)) { return 429; }
 *     try {
 *         $cloudTask->dispatch(...);                    // efek samping DULU
 *         Cache::store('database')->put($lockKey, ...); // kunci SESUDAHNYA
 *     }
 *
 * Dua lubang, dan yang kedua memperlebar yang pertama:
 *
 *   1. Dua permintaan bersamaan sama-sama melihat "belum terkunci", lalu
 *      KEDUANYA men-dispatch Cloud Task untuk berkas yang sama.
 *   2. Kunci baru dipasang SESUDAH dispatch selesai — jadi jendela balapannya
 *      selebar satu panggilan jaringan ke Google, ratusan milidetik.
 *
 * Terbukti bukan teori: dijalankan pada basis data ini, dua pemeriksaan `has()`
 * berurutan pada kunci yang sama SAMA-SAMA menjawab "belum terkunci", sementara
 * `add()` kedua menjawab false.
 *
 * ── Kenapa add(), bukan Cache::lock() ────────────────────────────────────────
 *
 * `add()` sudah atomik pada tumpukan ini dan diverifikasi di vendor: untuk SQL
 * Server, DatabaseStore::add() menjalankan INSERT ke tabel cache lalu menangkap
 * pelanggaran primary key. Yang kalah balapan mendapat false. Ia juga tidak
 * menambah primitif baru bagi pembaca kode ini.
 */
class KunciDispatchAtomikTest extends TestCase
{
    /**
     * Semua pintu yang memakai kunci anti double-dispatch.
     *
     * DUA BERKAS, dan itu bukan kelengkapan yang dicari-cari:
     * `LmsPdfMonitoringController::retryDocument` memakai AWALAN KUNCI YANG SAMA
     * (`lms_pdf_converting_`) dengan `renderUlangBerkas`. Keduanya menjaga sumber
     * daya yang sama dari dua endpoint berbeda, jadi memperbaiki salah satunya
     * saja hanya memberi perlindungan separuh — dua permintaan yang masuk lewat
     * pintu berbeda tetap bisa sama-sama men-dispatch.
     *
     * Pintu ketiga itu ditemukan oleh pemindaian pola cek-lalu-tindak, bukan
     * secara kebetulan seperti dua yang pertama.
     *
     * @var array<string, list<string>>
     */
    private const PINTU = [
        'app/Http/Controllers/LMS/Content/ContentManagementController.php' => [
            'verifikasiUlangBerkas',
            'renderUlangBerkas',
        ],
        'app/Http/Controllers/LMS/Monitoring/Pdf/LmsPdfMonitoringController.php' => [
            'retryDocument',
        ],
        'app/Http/Controllers/LMS/Content/Material/LmsMateriController.php' => [
            'maybeDispatchPdfConversionJob',
        ],
    ];

    /** Awalan kunci yang dipakai bersama oleh lebih dari satu pintu. */
    private const AWALAN_BERSAMA = "'lms_pdf_converting_'";

    private function badanMetode(string $berkas, string $nama): string
    {
        $isi = (string) file_get_contents(base_path($berkas));

        $cocok = preg_match(
            '/^[ \t]*(?:public|protected|private) function '.preg_quote($nama, '/').'\s*\(/m',
            $isi,
            $m,
            PREG_OFFSET_CAPTURE,
        );
        $this->assertSame(1, $cocok, "{$nama}() tidak ditemukan di {$berkas} — namanya berubah?");

        $awal = (int) $m[0][1];
        $buka = strpos($isi, '{', $awal);
        $dalam = 0;

        for ($i = $buka; $i < strlen($isi); $i++) {
            if ($isi[$i] === '{') {
                $dalam++;
            } elseif ($isi[$i] === '}') {
                $dalam--;
                if ($dalam === 0) {
                    return substr($isi, $awal, $i - $awal + 1);
                }
            }
        }

        $this->fail("Kurung kurawal {$nama}() tidak seimbang.");
    }

    /** @return list<array{0:string,1:string}> */
    private function semuaPintu(): array
    {
        $hasil = [];
        foreach (self::PINTU as $berkas => $daftar) {
            foreach ($daftar as $nama) {
                $hasil[] = [$berkas, $nama];
            }
        }

        return $hasil;
    }

    public function test_kunci_dipasang_secara_atomik_bukan_cek_lalu_tindak(): void
    {
        foreach ($this->semuaPintu() as [$berkas, $nama]) {
            $badan = $this->badanMetode($berkas, $nama);

            /* TTL boleh literal atau variabel: maybeDispatchPdfConversionJob
               memakai $lockTtl yang berbeda untuk retry dan non-retry. Yang
               dijaga bentuk pemasangannya, bukan angkanya. */
            $this->assertMatchesRegularExpression(
                '/->add\(\$lockKey,\s*true,\s*(?:\d+|\$lockTtl)\)/',
                $badan,
                "{$nama}() tidak lagi memasang kunci lewat add(). Hanya add() yang ".
                'atomik; has()+put() meloloskan dua permintaan bersamaan.',
            );

            $this->assertStringNotContainsString(
                '->has($lockKey)',
                $badan,
                "{$nama}() kembali memakai has(\$lockKey) — itu cek-lalu-tindak, ".
                'bukan kunci. Dua permintaan bersamaan akan sama-sama lolos.',
            );

            $this->assertStringNotContainsString(
                '->put($lockKey',
                $badan,
                "{$nama}() memasang kunci dengan put() setelah efeknya. Kunci harus ".
                'sudah terpasang SEBELUM dispatch, lewat add().',
            );
        }
    }

    public function test_kunci_dilepas_saat_dispatch_gagal(): void
    {
        foreach ($this->semuaPintu() as [$berkas, $nama]) {
            $badan = $this->badanMetode($berkas, $nama);

            $posisiCatch = strpos($badan, 'catch (');
            $this->assertNotFalse($posisiCatch, "{$nama}() tidak punya blok catch.");

            $this->assertStringContainsString(
                '->forget($lockKey)',
                substr($badan, $posisiCatch),
                "{$nama}() tidak melepas kunci saat dispatch gagal. Tanpa itu, satu ".
                'kegagalan mengunci berkasnya selama 300 detik penuh — padahal bentuk '.
                'lama justru tidak pernah mengunci kalau dispatch melempar.',
            );
        }
    }

    public function test_kunci_dipasang_sebelum_dispatch_bukan_sesudah(): void
    {
        foreach ($this->semuaPintu() as [$berkas, $nama]) {
            $badan = $this->badanMetode($berkas, $nama);

            $posisiAdd = strpos($badan, '->add($lockKey');
            $posisiDispatch = strpos($badan, '->dispatch(');

            $this->assertNotFalse($posisiAdd, "{$nama}(): add() tidak ditemukan.");
            $this->assertNotFalse($posisiDispatch, "{$nama}(): dispatch() tidak ditemukan.");

            /*
             * Urutannya YANG menutup jendela balapan, bukan sekadar pemakaian
             * add(). add() yang diletakkan setelah dispatch akan lulus dua uji
             * di atas sambil membiarkan cacat aslinya utuh.
             */
            $this->assertLessThan(
                $posisiDispatch,
                $posisiAdd,
                "{$nama}(): kunci dipasang SESUDAH dispatch. Jendela balapannya ".
                'kembali selebar satu panggilan jaringan ke Cloud Tasks.',
            );
        }
    }

    public function test_setiap_pemakai_awalan_kunci_bersama_ikut_terdaftar(): void
    {
        /*
         * Penjaga atas KELENGKAPAN, dan ia ada karena pintu ketiga sempat luput:
         * dua pintu pertama diperbaiki lebih dulu, dan yang ketiga — memakai
         * awalan kunci yang sama persis — baru ketahuan saat pola cek-lalu-tindak
         * dipindai khusus. Selama masih ada pemakai awalan itu di luar daftar
         * PINTU, perlindungannya tidak utuh dan tak ada uji yang menyadarinya.
         */
        $pemakai = [];
        $akar = str_replace('\\', '/', base_path()).'/';

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('app')),
        );
        foreach ($iterator as $berkas) {
            if (! $berkas->isFile() || $berkas->getExtension() !== 'php') {
                continue;
            }
            if (str_contains((string) file_get_contents($berkas->getPathname()), self::AWALAN_BERSAMA)) {
                $pemakai[] = str_replace($akar, '', str_replace('\\', '/', $berkas->getPathname()));
            }
        }

        $luput = array_values(array_diff($pemakai, array_keys(self::PINTU)));

        $this->assertSame(
            [],
            $luput,
            'Berkas berikut memakai awalan kunci '.self::AWALAN_BERSAMA.' tetapi tidak '.
            "terdaftar di PINTU, jadi bentuk kuncinya tidak dijaga sama sekali:\n  ".
            implode("\n  ", $luput),
        );
    }
}
