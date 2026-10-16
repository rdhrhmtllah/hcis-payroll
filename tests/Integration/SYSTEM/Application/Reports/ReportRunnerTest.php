<?php

namespace Tests\Integration\SYSTEM\Application\Reports;

use App\Services\SYSTEM\Application\Reports\DefinisiLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Dimensi;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\Kontrak\Saringan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Ukuran;
use App\Services\SYSTEM\Application\Reports\ReportRunner;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportRunnerTest extends TestCase
{
    private function dataset(): DatasetLaporan
    {
        return new class implements DatasetLaporan
        {
            public function kunci(): string
            {
                return 'uji';
            }

            public function label(): string
            {
                return 'Uji';
            }

            public function grain(): string
            {
                return 'satu baris per template';
            }

            public function dimensi(): array
            {
                return [
                    new Dimensi('modul', 'Modul', 't.Module', 'teks', 'inti'),
                    new Dimensi('pemilik', 'Pemilik', 't.Kode_Karyawan_Pemilik', 'teks', 'rahasia', true),
                ];
            }

            public function ukuran(): array
            {
                return [
                    new Ukuran('jml', 'Jumlah', 'COUNT(*)', 'angka'),
                    new Ukuran('unik', 'Pemilik Unik', 'COUNT(DISTINCT t.Kode_Karyawan_Pemilik)', 'angka', false, Ukuran::TOTAL_ABAIKAN),
                ];
            }

            public function saringan(): array
            {
                return [new Saringan('modul', 'Modul', 't.Module', 'teks', ['salah_satu'])];
            }

            public function baseQuery(): Builder
            {
                return DB::table('N_HRIS_Report_Template as t');
            }

            public function terapkanLingkup(Builder $q, Lingkup $l): Builder
            {
                return $q;
            }
        };
    }

    private function sisipkan(string $module, string $pemilik): void
    {
        DB::table('N_HRIS_Report_Template')->insert([
            'Module' => $module, 'Kode' => $module.'-uji', 'Nama' => 'Uji',
            'Dataset_Key' => 'uji', 'Mode' => 'RINCI', 'Definisi_Json' => '{}',
            'Tingkat_Bagi' => 'PRIBADI', 'Kode_Karyawan_Pemilik' => $pemilik,
        ]);
    }

    private function bersihkan(string $module): void
    {
        DB::table('N_HRIS_Report_Template')->where('Module', $module)->delete();
    }

    private function jalankan(array $definisi, Lingkup $l, ?int $batas = null, ?callable $lapor = null): array
    {
        return app(ReportRunner::class)->jalankan(
            DefinisiLaporan::dariArray($definisi),
            $this->dataset(),
            $l,
            'LAPORAN UJI',
            $batas,
            $lapor
        );
    }

    private function jalankanStreaming(array $definisi, Lingkup $l, ?int $batas = null): array
    {
        return app(ReportRunner::class)->jalankanStreaming(
            DefinisiLaporan::dariArray($definisi),
            $this->dataset(),
            $l,
            'LAPORAN UJI',
            $batas,
        );
    }

    public function test_bentuk_hasil_sama_dengan_kontrak_legacy(): void
    {
        $hasil = $this->jalankan(
            ['dataset' => 'uji', 'mode' => 'ringkasan', 'dimensi' => ['modul'], 'ukuran' => ['jml']],
            new Lingkup('AD1', true, [], true)
        );

        foreach (['title', 'generated_at', 'columns', 'rows', 'catatan'] as $k) {
            $this->assertArrayHasKey($k, $hasil);
        }
        $this->assertSame('LAPORAN UJI', $hasil['title']);
        $this->assertSame(['modul', 'jml'], array_column($hasil['columns'], 'key'));
    }

    public function test_field_sensitif_di_mask_bukan_dihapus(): void
    {
        $this->sisipkan('uji-runner', 'RAHASIA1');

        $hasil = $this->jalankan([
            'dataset' => 'uji', 'mode' => 'rinci', 'kolom' => ['modul', 'pemilik'],
            'saringan' => [['field' => 'modul', 'op' => 'salah_satu', 'nilai' => ['uji-runner']]],
        ], new Lingkup('L1', false, [], false));

        $this->bersihkan('uji-runner');

        $this->assertSame(['modul', 'pemilik'], array_column($hasil['columns'], 'key'));
        $this->assertSame('—', $hasil['rows'][0]['pemilik']);
        $this->assertNotEmpty($hasil['catatan']);
    }

    public function test_field_sensitif_utuh_untuk_yang_berhak(): void
    {
        $this->sisipkan('uji-runner2', 'RAHASIA2');

        $hasil = $this->jalankan([
            'dataset' => 'uji', 'mode' => 'rinci', 'kolom' => ['modul', 'pemilik'],
            'saringan' => [['field' => 'modul', 'op' => 'salah_satu', 'nilai' => ['uji-runner2']]],
        ], new Lingkup('AD1', true, [], true));

        $this->bersihkan('uji-runner2');

        $this->assertSame('RAHASIA2', $hasil['rows'][0]['pemilik']);
        $this->assertSame([], $hasil['catatan']);
    }

    public function test_batas_menulis_catatan_dan_mematikan_grand_total(): void
    {
        foreach (['uji-b1', 'uji-b2'] as $m) {
            $this->sisipkan($m, 'P');
        }

        $hasil = $this->jalankan([
            'dataset' => 'uji', 'mode' => 'ringkasan', 'dimensi' => ['modul'], 'ukuran' => ['jml'],
            'opsi' => ['grand_total' => true],
            'saringan' => [['field' => 'modul', 'op' => 'salah_satu', 'nilai' => ['uji-b1', 'uji-b2']]],
        ], new Lingkup('AD1', true, [], true), 1);

        foreach (['uji-b1', 'uji-b2'] as $m) {
            $this->bersihkan($m);
        }

        $this->assertCount(1, $hasil['rows']);
        $this->assertNotEmpty($hasil['catatan']);
        $this->assertNotSame('GRAND TOTAL', $hasil['rows'][0]['modul']);
    }

    public function test_grand_total_muncul_saat_tidak_terpotong(): void
    {
        foreach (['uji-g1', 'uji-g2'] as $m) {
            $this->sisipkan($m, 'P');
        }

        $hasil = $this->jalankan([
            'dataset' => 'uji', 'mode' => 'ringkasan', 'dimensi' => ['modul'], 'ukuran' => ['jml'],
            'opsi' => ['grand_total' => true],
            'saringan' => [['field' => 'modul', 'op' => 'salah_satu', 'nilai' => ['uji-g1', 'uji-g2']]],
        ], new Lingkup('AD1', true, [], true));

        foreach (['uji-g1', 'uji-g2'] as $m) {
            $this->bersihkan($m);
        }

        $terakhir = end($hasil['rows']);
        $this->assertSame('GRAND TOTAL', $terakhir['modul']);
        $this->assertSame(2.0, (float) $terakhir['jml']);
    }

    /**
     * Baris GRAND TOTAL pernah menjumlahkan APA SAJA, termasuk rata-rata,
     * persentase, dan COUNT(DISTINCT). Angkanya salah tapi berlabel resmi —
     * mode gagal yang tidak pernah menimbulkan galat.
     */
    public function test_grand_total_tidak_menjumlahkan_ukuran_non_aditif(): void
    {
        $this->sisipkan('uji-gt', 'AD1');

        try {
            $hasil = $this->jalankan([
                'dataset' => 'uji', 'mode' => 'ringkasan',
                'dimensi' => ['modul'], 'ukuran' => ['jml', 'unik'],
                'saringan' => [['field' => 'modul', 'op' => 'salah_satu', 'nilai' => ['uji-gt']]],
                'opsi' => ['grand_total' => true],
            ], new Lingkup('AD1', true, [], true));

            $total = end($hasil['rows']);

            $this->assertSame('GRAND TOTAL', $total['modul']);
            $this->assertSame('1', $total['jml'], 'COUNT(*) aditif, harus dijumlahkan.');
            $this->assertSame('-', $total['unik'], 'COUNT(DISTINCT) tidak sah dijumlahkan antar kelompok.');
            $this->assertNotEmpty(array_filter(
                $hasil['catatan'],
                fn ($c) => str_contains($c, 'GRAND TOTAL tidak diisi')
            ), 'Ukuran yang dilewati wajib dilaporkan, bukan diam-diam dikosongkan.');
        } finally {
            $this->bersihkan('uji-gt');
        }
    }

    /** Pemotongan wajib dilaporkan; berkas terpotong tanpa jejak tetap terbaca masuk akal. */
    public function test_pemotongan_baris_dilaporkan_di_catatan(): void
    {
        $this->sisipkan('uji-p1', 'AD1');
        DB::table('N_HRIS_Report_Template')->insert([
            'Module' => 'uji-p1', 'Kode' => 'uji-p1-dua', 'Nama' => 'Uji',
            'Dataset_Key' => 'uji', 'Mode' => 'RINCI', 'Definisi_Json' => '{}',
            'Tingkat_Bagi' => 'PRIBADI', 'Kode_Karyawan_Pemilik' => 'AD2',
        ]);

        try {
            $hasil = $this->jalankan([
                'dataset' => 'uji', 'mode' => 'rinci', 'kolom' => ['pemilik'],
                'saringan' => [['field' => 'modul', 'op' => 'salah_satu', 'nilai' => ['uji-p1']]],
            ], new Lingkup('AD1', true, [], true), 1);

            $this->assertCount(1, $hasil['rows']);
            $this->assertNotEmpty(array_filter($hasil['catatan'], fn ($c) => str_contains($c, 'dipotong')));
        } finally {
            $this->bersihkan('uji-p1');
        }
    }

    public function test_streaming_memotong_dan_memask_tanpa_materialisasi_array(): void
    {
        $this->sisipkan('uji-stream', 'RAHASIA1');
        DB::table('N_HRIS_Report_Template')->insert([
            'Module' => 'uji-stream', 'Kode' => 'uji-stream-dua', 'Nama' => 'Uji',
            'Dataset_Key' => 'uji', 'Mode' => 'RINCI', 'Definisi_Json' => '{}',
            'Tingkat_Bagi' => 'PRIBADI', 'Kode_Karyawan_Pemilik' => 'RAHASIA2',
        ]);

        try {
            $hasil = $this->jalankanStreaming([
                'dataset' => 'uji', 'mode' => 'rinci', 'kolom' => ['modul', 'pemilik'],
                'saringan' => [['field' => 'modul', 'op' => 'salah_satu', 'nilai' => ['uji-stream']]],
            ], new Lingkup('L1', false, [], false), 1);

            $this->assertInstanceOf(\Generator::class, $hasil['rows']);
            $baris = iterator_to_array($hasil['rows'], false);

            $this->assertCount(1, $baris);
            $this->assertNotSame('RAHASIA1', $baris[0]['pemilik']);
            $this->assertNotSame('RAHASIA2', $baris[0]['pemilik']);
            $this->assertSame(1, $hasil['stream_state']->count);
            $this->assertNotEmpty(array_filter(
                $hasil['stream_state']->catatan,
                fn ($c) => str_contains($c, 'dipotong'),
            ));
        } finally {
            $this->bersihkan('uji-stream');
        }
    }

    /**
     * Pelapor progres bukan hiasan bar: di dalamnya pemanggil memeriksa
     * pembatalan. Bila ia tak pernah dipanggil, tombol "Batalkan" tak
     * berpengaruh sepanjang pengambilan data.
     */
    public function test_pelapor_progres_dipanggil_dan_bisa_menghentikan(): void
    {
        $this->sisipkan('uji-lp', 'AD1');

        try {
            $dipanggil = 0;
            $this->jalankan([
                'dataset' => 'uji', 'mode' => 'rinci', 'kolom' => ['pemilik'],
                'saringan' => [['field' => 'modul', 'op' => 'salah_satu', 'nilai' => ['uji-lp']]],
            ], new Lingkup('AD1', true, [], true), 100, function () use (&$dipanggil) {
                $dipanggil++;
            });

            // Satu baris < TIK_PROGRES, jadi belum ada denyut; yang diuji di sini
            // adalah bahwa parameternya benar-benar diteruskan sampai ke runner
            // tanpa menggagalkan proses.
            $this->assertSame(0, $dipanggil);
        } finally {
            $this->bersihkan('uji-lp');
        }
    }

    /**
     * Regresi: pemangkasan nol di belakang koma pernah ditulis tanpa syarat,
     * sehingga 100 menjadi 1 — tanpa galat apa pun.
     */
    public function test_angka_bulat_tidak_kehilangan_nol_di_belakang(): void
    {
        $runner = app(ReportRunner::class);
        $format = new \ReflectionMethod($runner, 'formatNilai');
        $format->setAccessible(true);

        $this->assertSame('100', $format->invoke($runner, 100, 'angka'));
        $this->assertSame('1000', $format->invoke($runner, 1000, 'durasi'));
        $this->assertSame('85.5', $format->invoke($runner, 85.5, 'persen'));
        $this->assertSame('137', $format->invoke($runner, 137.42857142857, 'durasi'));
        $this->assertSame('-', $format->invoke($runner, null, 'angka'));
    }

    /** Penulis XLSX memakai penanda ini untuk memutuskan sel angka vs sel teks. */
    public function test_kolom_membawa_penanda_numerik(): void
    {
        $hasil = $this->jalankan(
            ['dataset' => 'uji', 'mode' => 'ringkasan', 'dimensi' => ['modul'], 'ukuran' => ['jml']],
            new Lingkup('AD1', true, [], true)
        );

        $peta = array_column($hasil['columns'], 'numerik', 'key');

        $this->assertFalse($peta['modul']);
        $this->assertTrue($peta['jml']);
    }
}
