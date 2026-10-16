<?php

namespace Tests\Feature\LMS\SOP\Export;

use App\Services\LMS\SOP\Export\SopTemplateSistem;
use App\Services\LMS\SOP\SopComplianceExportService;
use App\Services\SYSTEM\Application\Reports\DatasetRegistry;
use App\Services\SYSTEM\Application\Reports\DefinisiLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\ReportRunner;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Mesin baru dan jalur lama harus menghasilkan BARIS YANG SAMA.
 *
 * Yang dibandingkan datanya, bukan berkasnya: XLSX menyimpan timestamp di dalam
 * zip dan dompdf menulis CreationDate, jadi perbandingan byte akan gagal karena
 * alasan yang tak ada hubungannya dengan kebenaran isi.
 *
 * Harness ini TIDAK dibuang setelah migrasi. Selama layar monitoring masih
 * membaca service lama dan Studio membaca dataset baru, dua implementasi
 * menghitung angka yang sama -- dan hanya test ini yang membuat
 * ketidaksepakatannya terlihat.
 */
class RegresiRinciTest extends TestCase
{
    private function datasetLegacy(array $filters): array
    {
        $job = (object) [
            'Export_Id' => 'regresi-rinci',
            'Format' => 'xlsx',
            'Payload_Json' => json_encode(['jenis' => 'rinci', 'filters' => $filters]),
        ];

        $m = new \ReflectionMethod(SopComplianceExportService::class, 'buildDataset');
        $m->setAccessible(true);

        return $m->invoke(app(SopComplianceExportService::class), $job);
    }

    private function datasetBaru(array $saringan): array
    {
        $definisi = SopTemplateSistem::semua()['sop-sistem-rinci']['definisi'];
        $definisi['saringan'] = $saringan;

        return app(ReportRunner::class)->jalankan(
            DefinisiLaporan::dariArray($definisi),
            app(DatasetRegistry::class)->ambil('sop', 'kepatuhan'),
            new Lingkup('REG001', true, [], true),
            'RINCIAN KEPATUHAN SOP'
        );
    }

    /**
     * Skenario sengaja disempitkan ke satu divisi kecil, bukan seluruh dataset.
     *
     * Jalur lama menarik baris lewat SopMonitoringPeopleService secara berhalaman
     * dan memformatnya di PHP; menjalankannya tanpa saringan menembus batas waktu
     * test. Mesin baru menyelesaikan dataset yang sama dalam sepersekian detik --
     * perbedaan itu sendiri salah satu alasan migrasi ini dilakukan.
     *
     * Skenario luas dijalankan di suite lambat pada fase 3, bersama skenario
     * MAKS_BARIS.
     */
    public static function skenario(): array
    {
        return [
            'satu divisi' => [
                ['divisi' => ['IT']],
                [['field' => 'divisi', 'op' => 'salah_satu', 'nilai' => ['IT']]],
            ],
            /*
             * Skenario 'satu divisi' TIDAK bisa melihat dua ketidaksepakatan
             * terpenting: penugasan paket publik yang belum disentuh, dan item
             * ber-Status NULL. Keduanya nol di divisi IT, sehingga harness ini
             * lulus tanpa pernah menyentuh perkaranya. Divisi di bawah dipilih
             * karena memuat baris-baris itu DAN tetap kecil (19 baris) --
             * jalur lama memformat baris di PHP secara berhalaman, jadi
             * skenario lebar menembus batas waktu test.
             */
            'divisi bertunggakan publik' => [
                ['divisi' => ['BUSINESS STRATEGY & MARKETING']],
                [['field' => 'divisi', 'op' => 'salah_satu', 'nilai' => ['BUSINESS STRATEGY & MARKETING']]],
            ],
            'nol baris' => [
                ['divisi' => ['TIDAK-ADA-DIVISI-INI']],
                [['field' => 'divisi', 'op' => 'salah_satu', 'nilai' => ['TIDAK-ADA-DIVISI-INI']]],
            ],
        ];
    }

    /** @dataProvider skenario */
    public function test_jumlah_baris_identik(array $filtersLegacy, array $saringanBaru): void
    {
        Carbon::setTestNow('2026-08-18 10:00:00');

        $lama = $this->datasetLegacy($filtersLegacy);
        $baru = $this->datasetBaru($saringanBaru);

        Carbon::setTestNow();

        $this->assertSame(
            count($lama['rows']),
            count($baru['rows']),
            'Jumlah baris mesin baru berbeda dengan jalur lama.'
        );
    }

    /** @dataProvider skenario */
    public function test_baris_identik(array $filtersLegacy, array $saringanBaru): void
    {
        Carbon::setTestNow('2026-08-18 10:00:00');

        $lama = $this->datasetLegacy($filtersLegacy);
        $baru = $this->datasetBaru($saringanBaru);

        Carbon::setTestNow();

        $kunci = array_diff(
            array_intersect(
                array_column($baru['columns'], 'key'),
                array_column($lama['columns'], 'key')
            ),
            self::PERBEDAAN_DISENGAJA
        );

        $this->assertNotEmpty($kunci, 'Tidak ada kolom yang bisa dibandingkan.');

        $this->assertSame(
            $this->normalkan($lama['rows'], $kunci),
            $this->normalkan($baru['rows'], $kunci),
            'Baris mesin baru berbeda dengan jalur lama.'
        );
    }

    /** @dataProvider skenario */
    public function test_label_kolom_identik(array $filtersLegacy, array $saringanBaru): void
    {
        $lama = collect($this->datasetLegacy($filtersLegacy)['columns'])->keyBy('key');

        foreach ($this->datasetBaru($saringanBaru)['columns'] as $kol) {
            if (isset($lama[$kol['key']])) {
                $this->assertSame(
                    $lama[$kol['key']]['label'],
                    $kol['label'],
                    "Label kolom '{$kol['key']}' berbeda."
                );
            }
        }

        $this->assertTrue(true);
    }

    /**
     * Kolom yang mesin barunya SENGAJA berbeda dari jalur lama.
     *
     * Paritas adalah alat migrasi, bukan tujuan: begitu mesin baru memperbaiki
     * sesuatu, menuntutnya tetap sama berarti mengunci cacat lama. Yang tidak
     * boleh adalah perbedaan yang tak tercatat — karena itu daftar ini pendek,
     * beralasan, dan perbaikannya dijaga uji tersendiri di bawah.
     *
     * percobaan — jalur lama memaksa NULL menjadi '0', sehingga dua keadaan yang
     *   berbeda maknanya jadi terlihat sama:
     *
     *     tak ada baris N_SOP_Progress  -> NULL = TIDAK DIKETAHUI
     *     ada barisnya, belum dibuka    -> 0    = benar-benar nol kali
     *
     *   Mesin baru mempertahankan perbedaan itu; jalur lama meratakannya. Yang
     *   hilang bukan sekadar tampilan: pada item DOKUMEN yang tak berkuis, '0'
     *   membaca seolah ada yang mencoba.
     *
     *   TIDAK DIJAGA UJI TERSENDIRI, dan itu disebut apa adanya. Kedua keadaan
     *   tampil identik di seluruh kolom lain, sehingga perbedaannya tak bisa
     *   dibuktikan dari keluaran berkas saja — penjaga yang mencobanya akan
     *   menuduh baris yang benar (sudah dicoba, dan memang salah menuduh).
     *   Menjaganya butuh fikstur yang menyiapkan kedua keadaan dengan sengaja.
     */
    private const PERBEDAAN_DISENGAJA = ['percobaan'];

    /** Hanya kolom yang ada di kedua sisi yang dibandingkan, urut stabil. */
    private function normalkan(array $rows, array $kunci): array
    {
        $out = [];
        foreach ($rows as $r) {
            $r = (array) $r;
            $baris = [];
            foreach ($kunci as $k) {
                $nilai = $r[$k] ?? '';
                $baris[$k] = $nilai === null ? '' : trim((string) $nilai);
            }
            $out[] = $baris;
        }

        usort($out, fn ($a, $b) => strcmp(implode('|', $a), implode('|', $b)));

        return $out;
    }
}
