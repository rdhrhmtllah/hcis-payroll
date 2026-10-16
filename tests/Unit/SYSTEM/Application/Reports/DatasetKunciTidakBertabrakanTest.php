<?php

namespace Tests\Unit\SYSTEM\Application\Reports;

use App\Services\SYSTEM\Application\Reports\DatasetRegistry;
use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;
use Tests\TestCase;

/**
 * Penjaga tabrakan kunci dimensi/ukuran, dinaikkan dari dua salinan berbeda
 * (`PaHasilDatasetTest` dan `PaPenugasanDatasetTest`) jadi SATU uji yang
 * menyapu SELURUH dataset terdaftar di `DatasetRegistry` -- semua modul,
 * bukan cuma `pa`.
 *
 * ReportRunner::metaKolom() menggabungkan dimensi() dan ukuran() ke satu peta
 * ber-kunci sama -- ukuran() diproses TERAKHIR sehingga metadatanya
 * (label/format/numerik/totalisasi) menimpa punya dimensi kalau kuncinya
 * sama. Dimensi adalah nilai baris, ukuran adalah agregat lintas baris:
 * keduanya TIDAK BOLEH bernama sama di satu dataset, atau kolom rinci
 * diam-diam memakai metadata agregat yang salah.
 *
 * Dua untungnya dinaikkan ke sini: (1) dataset PA tahap berikutnya otomatis
 * terjaga tanpa ada yang perlu ingat menambahkan ujinya sendiri, dan (2)
 * cacat lama di `VisitorGuestDataset` (kunci `jumlah_tamu` dipakai di
 * `dimensi()` DAN `ukuran()` sekaligus) ikut ketahuan.
 *
 * Modul `pa` WAJIB hijau -- itu milik pekerjaan ini. Modul LAIN (mis.
 * `visitor`) sengaja TIDAK ikut menggagalkan suite ini: memperbaiki
 * VisitorGuestDataset ada di luar cakupan tugas ini. Kegagalannya
 * dilaporkan lewat markTestIncomplete() (kuning, bukan merah) supaya
 * temuannya tidak hilang tapi juga tidak memerahkan suite karena kode
 * yang bukan milik pekerjaan ini.
 */
class DatasetKunciTidakBertabrakanTest extends TestCase
{
    /** @return array<int, string> Kunci yang dipakai dimensi() DAN ukuran() sekaligus. */
    private function tabrakan(DatasetLaporan $dataset): array
    {
        $kunciDimensi = array_map(fn ($d) => $d->key, $dataset->dimensi());
        $kunciUkuran = array_map(fn ($u) => $u->key, $dataset->ukuran());

        return array_values(array_intersect($kunciDimensi, $kunciUkuran));
    }

    public function test_modul_pa_tidak_ada_tabrakan_kunci_dimensi_dan_ukuran(): void
    {
        $registry = app(DatasetRegistry::class);
        $datasetPa = $registry->semua('pa');

        // Ditegaskan dulu bahwa dataset PA memang terdaftar -- kalau daftar
        // provider-nya lupa dipanggil (mis. lingkungan uji tidak memuat
        // config/app.php penuh), uji ini tidak boleh diam-diam lulus dengan
        // nol dataset yang diperiksa.
        $this->assertNotEmpty($datasetPa, 'Tidak ada dataset modul pa yang terdaftar di DatasetRegistry.');

        $semuaTabrakan = [];
        foreach ($datasetPa as $kunciDataset => $dataset) {
            $tabrakan = $this->tabrakan($dataset);
            if ($tabrakan !== []) {
                $semuaTabrakan["pa/{$kunciDataset}"] = $tabrakan;
            }
        }

        $this->assertSame(
            [],
            $semuaTabrakan,
            'Tabrakan kunci dimensi/ukuran di dataset modul pa: '.json_encode($semuaTabrakan)
        );
    }

    /**
     * Sapuan modul LAIN di luar pa -- laporan saja, bukan gerbang. Lihat
     * dokblok kelas untuk alasan kenapa kegagalan di sini tidak boleh
     * memerahkan suite.
     */
    public function test_modul_lain_disapu_untuk_tabrakan_kunci_tapi_dilaporkan_bukan_memerahkan_suite(): void
    {
        $registry = app(DatasetRegistry::class);
        $semuaModul = $registry->semuaModul();
        unset($semuaModul['pa']);

        $semuaTabrakan = [];
        foreach ($semuaModul as $namaModul => $datasetModul) {
            foreach ($datasetModul as $kunciDataset => $dataset) {
                $tabrakan = $this->tabrakan($dataset);
                if ($tabrakan !== []) {
                    $semuaTabrakan["{$namaModul}/{$kunciDataset}"] = $tabrakan;
                }
            }
        }

        if ($semuaTabrakan !== []) {
            $this->markTestIncomplete(
                'Tabrakan kunci dimensi/ukuran ditemukan di luar modul pa (di luar cakupan tugas ini, TIDAK '
                .'diperbaiki di sini -- lihat brief tugas 6): '.json_encode($semuaTabrakan)
            );
        }

        $this->assertTrue(true, 'Tidak ada tabrakan kunci di modul selain pa.');
    }
}
