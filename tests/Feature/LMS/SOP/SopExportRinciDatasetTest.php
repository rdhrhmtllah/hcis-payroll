<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopComplianceExportService;
use App\Services\LMS\SOP\SopExportRinciService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Sambungan antara service rincian dan penulis berkas.
 *
 * Yang diuji di sini bukan lagi query-nya (itu urusan SopExportRinciTest),
 * melainkan bahwa jenis `rinci` benar-benar tersambung ke datanya, memakai
 * definisi kolom yang sama, dan MENGUMUMKAN pemotongan alih-alih memotong
 * diam-diam. Berkas yang terpotong tanpa jejak adalah mode gagal terburuk:
 * isinya tetap terbaca masuk akal.
 */
class SopExportRinciDatasetTest extends TestCase
{
    /** Job palsu — tak menyentuh tabel tugas, jadi tak ada sampah yang tertinggal. */
    private function job(array $filters = [], ?string $exportId = null): object
    {
        return (object) [
            'Export_Id' => $exportId ?? 'uji-tanpa-baris',
            'Format' => 'xlsx',
            'Payload_Json' => json_encode(['jenis' => 'rinci', 'filters' => $filters]),
        ];
    }

    private function dataset(object $job, ?SopComplianceExportService $svc = null): array
    {
        $svc = $svc ?: app(SopComplianceExportService::class);
        $m = new \ReflectionMethod(SopComplianceExportService::class, 'buildDataset');
        $m->setAccessible(true);

        return $m->invoke($svc, $job);
    }

    /** Salinan service dengan plafon kecil, supaya jalur pemotongan bisa dijalankan. */
    private function serviceBerplafon(int $batas): SopComplianceExportService
    {
        return new class($batas) extends SopComplianceExportService
        {
            public function __construct(private int $batas) {}

            protected function batasBaris(): int
            {
                return $this->batas;
            }
        };
    }

    private function kodeUji(): string
    {
        $kode = DB::table('N_SOP_Penjadwalan_Detail')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Flag_Cabut_Akses', SopStatus::INAKTIF)
            ->groupBy('Kode_Karyawan')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(1)
            ->value('Kode_Karyawan');

        $this->assertNotNull($kode, 'Butuh minimal satu karyawan dengan penugasan dokumen aktif.');

        return $kode;
    }

    public function test_rinci_is_wired_to_its_own_dataset(): void
    {
        $d = $this->dataset($this->job(['kodes' => [$this->kodeUji()]]));

        $this->assertStringContainsString('RINCIAN', $d['title']);
        $this->assertNotEmpty($d['rows']);
    }

    public function test_dataset_uses_the_same_column_definition_as_the_preview(): void
    {
        $d = $this->dataset($this->job(['kodes' => [$this->kodeUji()]]));

        // Satu sumber kolom. Kalau berkas dan pratinjau punya daftarnya sendiri,
        // keduanya bebas menyimpang dan admin melihat bentuk yang salah.
        $this->assertSame(app(SopExportRinciService::class)->columns(), $d['columns']);
    }

    public function test_every_row_fills_every_column(): void
    {
        $d = $this->dataset($this->job(['kodes' => [$this->kodeUji()]]));

        foreach (array_column($d['columns'], 'key') as $key) {
            $this->assertArrayHasKey($key, $d['rows'][0], "Kolom {$key} kosong di baris hasil.");
        }
    }

    public function test_row_count_matches_what_the_filter_promised(): void
    {
        $f = ['kodes' => [$this->kodeUji()]];

        $d = $this->dataset($this->job($f));

        $this->assertSame(app(SopExportRinciService::class)->count($f), count($d['rows']));
    }

    public function test_it_keeps_fetching_past_the_first_chunk(): void
    {
        $svc = app(SopExportRinciService::class);
        $tersedia = $svc->count([]);
        if ($tersedia <= 500) {
            $this->markTestSkipped('Basis data belum melewati satu potongan (500 baris).');
        }

        // Tes lain memakai satu karyawan saja dan tak pernah melewati potongan
        // pertama — persis kondisi di mana gelung pengambilan bisa berhenti
        // terlalu cepat tanpa ada yang tahu.
        $d = $this->dataset($this->job());

        $this->assertSame($tersedia, count($d['rows']));
        $this->assertSame($tersedia, $d['rows'][$tersedia - 1]['no']);
    }

    public function test_title_stays_quiet_when_nothing_was_cut(): void
    {
        $d = $this->dataset($this->job(['kodes' => [$this->kodeUji()]]));

        $this->assertStringNotContainsString('DIPOTONG', $d['title']);
    }

    public function test_truncation_is_written_into_the_title_not_hidden(): void
    {
        $f = ['kodes' => [$this->kodeUji()]];
        $tersedia = app(SopExportRinciService::class)->count($f);
        if ($tersedia < 3) {
            $this->markTestSkipped('Butuh minimal 3 baris untuk menguji pemotongan.');
        }

        $d = $this->dataset($this->job($f), $this->serviceBerplafon(2));

        $this->assertCount(2, $d['rows']);
        $this->assertStringContainsString('DIPOTONG PADA 2 DARI '.number_format($tersedia), $d['title']);
    }

    /**
     * Menulis berkas Excel sungguhan.
     *
     * Gaya PhpSpreadsheet (conditional formatting, freezePane, autoFilter) hanya
     * meledak SAAT dieksekusi. Tanpa tes ini, kekeliruan API-nya baru ketahuan di
     * worker — dalam bentuk job yang gagal tanpa penjelasan bagi admin.
     */
    public function test_the_excel_file_actually_renders_with_its_styling(): void
    {
        // Disk export sungguhan adalah GCS. Tanpa fake, tes ini mengunggah berkas
        // ke bucket produksi setiap kali dijalankan — dan sampahnya menumpuk.
        Storage::fake(SopComplianceExportService::DISK);

        $job = $this->job(['kodes' => [$this->kodeUji()]], 'uji-render');
        $d = $this->dataset($job);

        $svc = app(SopComplianceExportService::class);
        $m = new \ReflectionMethod(SopComplianceExportService::class, 'renderFile');
        $m->setAccessible(true);

        [$path, $nama, $ukuran] = $m->invoke($svc, $job, $d);

        $this->assertGreaterThan(0, $ukuran, 'Berkas Excel kosong.');
        $this->assertStringEndsWith('.xlsx', $nama);
        Storage::disk(SopComplianceExportService::DISK)->assertExists($path);
    }

    public function test_the_reading_aids_travel_with_the_dataset(): void
    {
        $d = $this->dataset($this->job(['kodes' => [$this->kodeUji()]]));

        // Penulis Excel tak boleh tahu apa pun soal "paket" atau "karyawan";
        // dataset yang memberitahunya di mana batas kelompok berada.
        $this->assertSame(['kode', 'paket'], $d['pita']);
        $this->assertSame('kode', $d['pemisah']);
    }

    public function test_an_impossible_filter_produces_an_empty_file_not_an_error(): void
    {
        $d = $this->dataset($this->job(['kodes' => ['ZZ-TIDAK-ADA']]));

        $this->assertSame([], $d['rows']);
        $this->assertNotEmpty($d['columns']);
    }
}
