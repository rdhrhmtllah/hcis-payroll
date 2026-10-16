<?php

namespace Tests\Feature\SYSTEM\Application;

use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Progress bar ekspor tidak boleh menulis sekali per baris.
 *
 * ── Skalanya ─────────────────────────────────────────────────────────────────
 *
 * Subclass memanggil bumpProgress() sekali PER BARIS, dan itu bentuk yang wajar
 * dari sisi pemanggil. Yang tidak wajar: tiap panggilan dulu menerbitkan satu
 * UPDATE ke tabel job. Ekspor 900 karyawan berarti 900 UPDATE untuk
 * menggerakkan bar yang cuma punya 100 langkah — sekitar 800 di antaranya
 * menulis angka yang identik dengan yang sudah tertulis.
 *
 * Karena AbstractExportService adalah induk SEMUA ekspor, angka itu berlaku
 * untuk setiap ekspor di sistem, bukan satu layar saja.
 *
 * ── Yang dijaga ──────────────────────────────────────────────────────────────
 *
 * Bukan "jangan pernah menulis", melainkan "jangan menulis yang sama berulang":
 * tulisannya dibatasi jumlah langkah persen, dan baris TERAKHIR selalu ditulis
 * supaya Processed_Records mencapai angka akhirnya.
 */
class ProgresEksporTidakPerBarisTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 1.000 baris hanya punya 100 langkah persen. Ambang 120 memberi ruang
     * untuk pembulatan dan tulisan penutup tanpa membiarkan bentuk per-baris
     * kembali — bentuk lama menghasilkan 1.000.
     */
    private const AMBANG_TULIS = 120;

    public function test_progres_ditulis_per_langkah_persen_bukan_per_baris(): void
    {
        $exportId = 'UJI-'.Str::upper(Str::random(12));

        DB::table(AbstractExportService::TABLE)->insert([
            'Export_Id' => $exportId,
            'Module' => 'UJI',
            'Task_Type' => 'uji-progres',
            'Status' => AbstractExportService::STATUS_PROCESSING,
            'Total_Records' => 1000,
            'Processed_Records' => 0,
            'Percent' => 0,
            'Requested_By' => 'UJI',
            'Created_At' => now(),
        ]);

        /*
         * Subclass minimal: yang diuji hanya bumpProgress(), dan tujuh metode
         * abstrak lainnya tak pernah dipanggil di jalur ini. Diisi seadanya
         * supaya kelasnya bisa dibentuk, bukan supaya berperilaku.
         */
        $svc = new class extends AbstractExportService
        {
            public function dorong(string $id, int $processed, int $total): void
            {
                $this->bumpProgress($id, $processed, $total);
            }

            protected function moduleKey(): string
            {
                return 'UJI';
            }

            protected function exportIdPrefix(): string
            {
                return 'UJI';
            }

            protected function storageDir(): string
            {
                return 'uji';
            }

            protected function buildDataset(object $job): array
            {
                return [];
            }

            protected function renderFile(object $job, array $dataset): array
            {
                return [];
            }

            protected function listLabel(object $job): string
            {
                return 'uji';
            }

            public function dispatchJob(string $exportId): void {}
        };

        $tulis = 0;
        DB::listen(function ($q) use (&$tulis) {
            if (stripos(ltrim($q->sql), 'update') === 0) {
                $tulis++;
            }
        });

        for ($i = 1; $i <= 1000; $i++) {
            $svc->dorong($exportId, $i, 1000);
        }

        $this->assertLessThanOrEqual(
            self::AMBANG_TULIS,
            $tulis,
            sprintf(
                'bumpProgress menerbitkan %d UPDATE untuk 1.000 baris. Bar-nya hanya '.
                'punya 100 langkah; sisanya menulis angka yang sudah tertulis.',
                $tulis,
            ),
        );

        /* Murah saja tidak cukup — angka akhirnya harus BENAR. Baris terakhir
           wajib ditulis walau persennya tak bergeser dari baris sebelumnya. */
        $baris = DB::table(AbstractExportService::TABLE)->where('Export_Id', $exportId)->first();

        $this->assertSame(1000, (int) $baris->Processed_Records,
            'Baris terakhir tidak ditulis — panel akan berhenti di angka sebelum akhir.');
        $this->assertSame(99, (int) $baris->Percent,
            'Persen berhenti di 99 sampai berkasnya benar-benar siap; itu memang kontraknya.');
    }
}
