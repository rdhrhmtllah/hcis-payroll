<?php

namespace Tests\Unit\LMS\SOP\Uat;

use App\Services\LMS\SOP\Uat\UatBebanKlien;
use PHPUnit\Framework\TestCase;

/**
 * Bentuk beban diuji tanpa menyentuh jaringan.
 *
 * Yang diperiksa di sini bukan "apakah curl bekerja", melainkan apakah beban
 * yang disusun benar-benar mewakili kejadian yang hendak diukur. Beban yang
 * salah bentuk tetap menghasilkan angka rapi — dan angka rapi dari beban yang
 * salah adalah cara paling meyakinkan untuk menyimpulkan hal yang keliru.
 */
class UatBebanKlienTest extends TestCase
{
    private function dunia(): array
    {
        return ['paket_hash' => 'ABC123', 'item_hash' => ['IT1', 'IT2']];
    }

    private function klien(array $sesiAdmin = []): UatBebanKlien
    {
        return new UatBebanKlien('http://contoh.test', [
            ['name' => 'c', 'value' => 'v1'],
            ['name' => 'c', 'value' => 'v2'],
        ], $sesiAdmin);
    }

    public function test_rencana_menghasilkan_sebanyak_yang_diminta(): void
    {
        $this->assertCount(50, $this->klien()->rencanaPermintaan($this->dunia(), 50));
    }

    public function test_rencana_memutar_sesi_supaya_bukan_satu_orang_saja(): void
    {
        $dipakai = array_unique(array_column(
            $this->klien()->rencanaPermintaan($this->dunia(), 10), 'sesi'
        ));

        $this->assertCount(2, $dipakai,
            'Seluruh beban memakai satu sesi — isi Functional Document ter-cache per karyawan, '
            .'sehingga kerja basis data yang sedang diukur tak pernah terjadi.');
    }

    public function test_rencana_mencakup_ketiga_langkah_alur_karyawan(): void
    {
        $url = array_column($this->klien()->rencanaPermintaan($this->dunia(), 30), 'url');

        $this->assertNotEmpty(preg_grep('#/api/v1/sop/saya$#', $url),
            'Daftar tugas tak pernah dibuka.');
        $this->assertNotEmpty(preg_grep('#/api/v1/sop/ABC123$#', $url),
            'Detail paket tak pernah dibuka.');
        $this->assertNotEmpty(preg_grep('#/api/v1/sop/ABC123/item/IT[12]/versi$#', $url),
            'Pembaca dokumen tak pernah tersentuh — padahal itu bagian terberatnya.');
    }

    public function test_beban_admin_ikut_disertakan(): void
    {
        $url = array_column(
            $this->klien([['name' => 'c', 'value' => 'admin']])->rencanaPermintaan($this->dunia(), 40),
            'url'
        );

        $this->assertNotEmpty(preg_grep('#/api/v1/sop/admin/monitoring/#', $url),
            'Monitoring admin tak pernah dimuat. Riwayat CPU 100% di HCIS ini datang dari '
            .'agregasi dashboard — melewatkannya berarti melewatkan kombinasi terburuknya.');
    }

    public function test_tanpa_sesi_admin_seluruh_beban_milik_karyawan(): void
    {
        $url = array_column($this->klien()->rencanaPermintaan($this->dunia(), 40), 'url');

        $this->assertEmpty(preg_grep('#/admin/#', $url),
            'Beban admin muncul padahal tak ada sesi admin — permintaannya akan '
            .'ditolak 401 dan terhitung sebagai kegagalan aplikasi.');
    }

    public function test_menembak_daftar_kosong_tidak_melempar(): void
    {
        $this->assertSame([], $this->klien()->tembak([]));
    }
}
