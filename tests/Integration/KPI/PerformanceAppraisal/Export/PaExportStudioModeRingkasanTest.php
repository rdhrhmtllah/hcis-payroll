<?php

namespace Tests\Integration\KPI\PerformanceAppraisal\Export;

use App\Services\SYSTEM\Application\Reports\DatasetRegistry;
use App\Services\SYSTEM\Application\Reports\DefinisiLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Dimensi;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\Kontrak\Saringan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Ukuran;
use App\Services\SYSTEM\Application\Reports\ReportCompiler;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Membuktikan mode RINGKASAN keempat dataset Export Studio PA benar-benar bisa
 * dieksekusi di SQL Server sungguhan.
 *
 * `toSql()` tidak pernah menyentuh basis data, dan SQL Server melarang
 * sub-kueri di `GROUP BY` sementara `ReportCompiler::susun()` menaruh
 * ekspresi `select` dimensi apa adanya ke `groupBy()`. Sebuah dimensi bisa
 * lolos sempurna di mode RINCI lalu gagal total begitu dikelompokkan --
 * satu-satunya bukti yang berarti adalah query sungguhan diterima server.
 *
 * BACA-SAJA: hanya SELECT. Tidak ada pengguna uji yang dibuat -- pratinjau/
 * jalankan lewat HTTP menuntut login admin, dan login itu berarti menulis ke
 * basis data development, di luar kewenangan uji ini (lihat
 * PaExportStudioAksesTest untuk alasan yang sama pada REPORT_DATASET_UNKNOWN).
 */
class PaExportStudioModeRingkasanTest extends TestCase
{
    private const MODULE = 'pa';

    /** Ukuran utama harus mengikuti grain masing-masing dataset, bukan dipukul rata. */
    private const UKURAN_UTAMA = [
        'hasil' => 'jumlah_peserta',
        'penugasan' => 'jumlah_penugasan',
        'kompetensi' => 'jumlah_pasang',
        'peserta' => 'jumlah_orang',
    ];

    private function dataset(string $kunci): DatasetLaporan
    {
        return app(DatasetRegistry::class)->ambil(self::MODULE, $kunci);
    }

    private function lingkupAdmin(): Lingkup
    {
        return new Lingkup('AD1', true, [], true);
    }

    /**
     * Jumlah baris mode RINCI (satu baris per grain, tanpa pengelompokan) --
     * baseline untuk memastikan pengelompokan tidak pernah menghasilkan baris
     * LEBIH BANYAK daripada rinci.
     */
    private function jumlahBarisRinci(DatasetLaporan $ds, Lingkup $lingkup): int
    {
        $definisi = DefinisiLaporan::dariArray([
            'dataset' => $ds->kunci(),
            'mode' => 'rinci',
            'kolom' => [$ds->dimensi()[0]->key],
        ]);

        return app(ReportCompiler::class)->susun($definisi, $ds, $lingkup)->count();
    }

    /** Menjalankan mode ringkasan sungguhan dan menegaskan bentuk barisnya, bukan angkanya. */
    private function jalankanRingkasanDanPeriksa(string $datasetKunci, string $dimensiKey, string $ukuranKey, int $batasBarisRinci): void
    {
        $ds = $this->dataset($datasetKunci);
        $lingkup = $this->lingkupAdmin();

        $definisi = DefinisiLaporan::dariArray([
            'dataset' => $datasetKunci,
            'mode' => 'ringkasan',
            'dimensi' => [$dimensiKey],
            'ukuran' => [$ukuranKey],
        ]);

        $builder = app(ReportCompiler::class)->susun($definisi, $ds, $lingkup);

        try {
            // SELECT ... GROUP BY ... dieksekusi SUNGGUHAN di SQL Server di sini.
            $rows = $builder->get();
        } catch (\Throwable $e) {
            $this->fail("Dataset '{$datasetKunci}': mode ringkasan gagal dikelompokkan pada dimensi '{$dimensiKey}' -- {$e->getMessage()}");
        }

        foreach ($rows as $row) {
            $arr = (array) $row;
            $this->assertArrayHasKey($dimensiKey, $arr, "Kolom dimensi '{$dimensiKey}' tidak muncul di baris ringkasan dataset '{$datasetKunci}'.");
            $this->assertArrayHasKey($ukuranKey, $arr, "Kolom ukuran '{$ukuranKey}' tidak muncul di baris ringkasan dataset '{$datasetKunci}'.");
        }

        $this->assertLessThanOrEqual(
            $batasBarisRinci,
            $rows->count(),
            "Dataset '{$datasetKunci}': pengelompokan dimensi '{$dimensiKey}' menghasilkan baris LEBIH BANYAK daripada mode rinci."
        );
    }

    public function test_dataset_hasil_bisa_dikelompokkan_per_divisi_dalam_mode_ringkasan(): void
    {
        $ds = $this->dataset('hasil');
        $rinci = $this->jumlahBarisRinci($ds, $this->lingkupAdmin());
        $this->assertGreaterThan(0, $rinci, "Populasi dataset 'hasil' kosong di Web_HRIS -- uji ini tak membuktikan apa pun.");

        $this->jalankanRingkasanDanPeriksa('hasil', 'divisi', self::UKURAN_UTAMA['hasil'], $rinci);
    }

    public function test_dataset_penugasan_bisa_dikelompokkan_per_divisi_dalam_mode_ringkasan(): void
    {
        $ds = $this->dataset('penugasan');
        $rinci = $this->jumlahBarisRinci($ds, $this->lingkupAdmin());
        $this->assertGreaterThan(0, $rinci, "Populasi dataset 'penugasan' kosong di Web_HRIS -- uji ini tak membuktikan apa pun.");

        $this->jalankanRingkasanDanPeriksa('penugasan', 'divisi', self::UKURAN_UTAMA['penugasan'], $rinci);
    }

    /**
     * Sapuan SETIAP dimensi katalog, bukan cuma 'divisi': akar masalahnya adalah
     * ekspresi `select` apa pun yang berbentuk sub-kueri, dan 'divisi' kebetulan
     * berupa kolom biasa di kedua dataset PA. Dimensi lain (mis. yang menyilang
     * ke N_PA_Hav_Quadrant atau N_PA_Score_Range lewat LEFT JOIN) baru terbukti
     * aman kalau benar-benar dijalankan satu per satu.
     */
    public function test_setiap_dimensi_dataset_hasil_bisa_dikelompokkan_dalam_mode_ringkasan(): void
    {
        $ds = $this->dataset('hasil');
        $lingkup = $this->lingkupAdmin();
        $rinci = $this->jumlahBarisRinci($ds, $lingkup);
        $this->assertGreaterThan(0, $rinci, "Populasi dataset 'hasil' kosong di Web_HRIS -- uji ini tak membuktikan apa pun.");

        $this->assertArrayHasKey('hasil', self::UKURAN_UTAMA);
        $ukuranKeys = array_map(fn ($u) => $u->key, $ds->ukuran());
        $this->assertContains(self::UKURAN_UTAMA['hasil'], $ukuranKeys, "Ukuran 'jumlah_peserta' tidak lagi ada di katalog dataset 'hasil'.");

        foreach ($ds->dimensi() as $dimensi) {
            $this->jalankanRingkasanDanPeriksa('hasil', $dimensi->key, self::UKURAN_UTAMA['hasil'], $rinci);
        }
    }

    /** Sapuan SETIAP dimensi katalog dataset 'penugasan' -- lihat alasan pada uji dataset 'hasil'. */
    public function test_setiap_dimensi_dataset_penugasan_bisa_dikelompokkan_dalam_mode_ringkasan(): void
    {
        $this->periksaSeluruhDimensi('penugasan');
    }

    /** Dataset kompetensi menyentuh dua tabel master tambahan; seluruh dimensinya wajib diuji. */
    public function test_setiap_dimensi_dataset_kompetensi_bisa_dikelompokkan_dalam_mode_ringkasan(): void
    {
        $this->periksaSeluruhDimensi('kompetensi');
    }

    /** Dataset peserta memakai LEFT JOIN subkueri agregat; mode ringkasanlah yang paling rawan. */
    public function test_setiap_dimensi_dataset_peserta_bisa_dikelompokkan_dalam_mode_ringkasan(): void
    {
        $this->periksaSeluruhDimensi('peserta');
    }

    private function periksaSeluruhDimensi(string $kunci): void
    {
        $ds = $this->dataset($kunci);
        $lingkup = $this->lingkupAdmin();
        $rinci = $this->jumlahBarisRinci($ds, $lingkup);
        $this->assertGreaterThan(0, $rinci, "Populasi dataset '{$kunci}' kosong di Web_HRIS -- uji ini tak membuktikan apa pun.");

        $this->assertArrayHasKey($kunci, self::UKURAN_UTAMA);
        $ukuranKeys = array_map(fn ($u) => $u->key, $ds->ukuran());
        $this->assertContains(
            self::UKURAN_UTAMA[$kunci],
            $ukuranKeys,
            "Ukuran utama tidak lagi ada di katalog dataset '{$kunci}'."
        );

        foreach ($ds->dimensi() as $dimensi) {
            $this->jalankanRingkasanDanPeriksa($kunci, $dimensi->key, self::UKURAN_UTAMA[$kunci], $rinci);
        }
    }

    /**
     * Dataset SINTETIS -- bukan `PaHasilDataset` atau `PaPenugasanDataset` --
     * dengan satu dimensi berbentuk sub-kueri berkorelasi. Ini persis bentuk
     * yang dilarang SQL Server di `GROUP BY`, dan ditaruh apa adanya ke sana
     * oleh `ReportCompiler::susun()` (lihat baris `groupBy(DB::raw($dimensi[$k]->select))`).
     *
     * Dataset ini SENGAJA dibuat cacat: penjaga atas kedua uji lolos di atas
     * hanya berarti sesuatu kalau mekanisme yang sama TERBUKTI menangkap
     * kegagalan saat kegagalan itu sungguhan ada. Base query-nya memakai
     * tabel PA sungguhan (`N_PA_Cycle`) supaya eksekusinya nyata, bukan
     * `toSql()` yang tak pernah menyentuh basis data.
     */
    private function datasetSintetisDenganDimensiSubkueri(): DatasetLaporan
    {
        return new class implements DatasetLaporan
        {
            public function kunci(): string
            {
                return 'sintetis-subkueri-groupby';
            }

            public function label(): string
            {
                return 'Sintetis -- dimensi subkueri (sengaja cacat)';
            }

            public function grain(): string
            {
                return 'satu baris per siklus PA';
            }

            public function dimensi(): array
            {
                return [
                    // Berkorelasi ke c.Id_PA_Cycle milik query luar -- bentuk
                    // yang SQL Server tolak begitu ekspresi ini muncul di
                    // GROUP BY, walau sempurna valid sebagai kolom SELECT biasa.
                    new Dimensi(
                        'nama_peserta_pertama',
                        'Nama Peserta Pertama (subkueri)',
                        '(SELECT TOP 1 p.Nama_Karyawan_Saat_Cycle FROM N_PA_Participant p WHERE p.PA_Cycle_Id = c.Id_PA_Cycle)',
                        'teks',
                        'sintetis'
                    ),
                ];
            }

            public function ukuran(): array
            {
                return [new Ukuran('jumlah', 'Jumlah', 'COUNT(*)', 'angka')];
            }

            public function saringan(): array
            {
                return [new Saringan('kode_cycle', 'Kode Siklus', 'c.Kode_Cycle', 'teks', ['salah_satu'])];
            }

            public function baseQuery(): Builder
            {
                return DB::table('N_PA_Cycle as c');
            }

            public function terapkanLingkup(Builder $q, Lingkup $l): Builder
            {
                return $q;
            }
        };
    }

    /**
     * Kebalikan pasangan dari keempat uji di atas: dataset yang SENGAJA cacat
     * harus GAGAL saat dikelompokkan, dengan mekanisme uji yang PERSIS sama
     * (`ReportCompiler::susun()` lalu `->get()` sungguhan). Kalau ternyata
     * berhasil, uji-uji dataset PA yang lulus di atas tidak membuktikan
     * apa-apa -- mekanismenya sendiri tidak sanggup menangkap kegagalan.
     */
    public function test_dataset_sintetis_dengan_dimensi_subkueri_gagal_dikelompokkan_di_sql_server(): void
    {
        $ds = $this->datasetSintetisDenganDimensiSubkueri();
        $lingkup = $this->lingkupAdmin();

        $definisi = DefinisiLaporan::dariArray([
            'dataset' => $ds->kunci(),
            'mode' => 'ringkasan',
            'dimensi' => ['nama_peserta_pertama'],
            'ukuran' => ['jumlah'],
        ]);

        $builder = app(ReportCompiler::class)->susun($definisi, $ds, $lingkup);

        $gagal = false;
        $pesan = '';
        try {
            $builder->get();
        } catch (\Throwable $e) {
            $gagal = true;
            $pesan = $e->getMessage();
        }

        $this->assertTrue(
            $gagal,
            'Dataset sintetis dengan dimensi subkueri seharusnya GAGAL dikelompokkan di SQL Server, tapi malah berhasil -- '
            .'berarti mekanisme yang menjaga dataset PA sungguhan TIDAK benar-benar menjaga apa pun.'
        );
        // Bukan cuma "gagal" -- galatnya harus PERSIS akar masalah yang
        // dijelaskan brief (sub-kueri di GROUP BY), bukan kegagalan lain yang
        // kebetulan lolos try/catch (mis. tabel salah nama).
        $this->assertStringContainsStringIgnoringCase(
            'group by',
            $pesan,
            "Galatnya bukan soal sub-kueri di GROUP BY seperti yang diharapkan -- pesan sungguhannya: {$pesan}"
        );
    }
}
