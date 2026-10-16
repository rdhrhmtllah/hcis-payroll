<?php

namespace Tests\Unit\ESS\Employee;

use App\Services\ESS\Employee\DataCollection\StatusPengumpulanService;
use Tests\TestCase;

/**
 * Aturan "modal perlu tampil atau tidak" diuji sebagai fungsi murni.
 *
 * Sejak penanda dipindah dari browser ke tabel, seluruh keputusan ini milik
 * server — dan justru karena itu ia harus bisa diuji tanpa database maupun
 * browser.
 */
class GerbangTampilModalTest extends TestCase
{
    private function service(): StatusPengumpulanService
    {
        return app(StatusPengumpulanService::class);
    }

    private function keadaan(array $ganti = []): array
    {
        return array_merge([
            'status' => 'BELUM',
            'terkunci' => false,
            'terakhir_tampil_tanggal' => null,
            'terakhir_tampil_sesi' => null,
        ], $ganti);
    }

    public function test_mode_halaman_selalu_tampil_walau_baru_saja_tampil(): void
    {
        $s = $this->keadaan(['terakhir_tampil_tanggal' => '2026-08-14', 'terakhir_tampil_sesi' => 'sesi-a']);

        $this->assertTrue($this->service()->perluTampil($s, 'HALAMAN', 'sesi-a', '2026-08-14'));
    }

    public function test_mode_sesi_tampil_sekali_lalu_diam_di_sesi_yang_sama(): void
    {
        $belumPernah = $this->keadaan();
        $this->assertTrue($this->service()->perluTampil($belumPernah, 'SESI', 'sesi-a', '2026-08-14'));

        $sudah = $this->keadaan(['terakhir_tampil_sesi' => 'sesi-a']);
        $this->assertFalse($this->service()->perluTampil($sudah, 'SESI', 'sesi-a', '2026-08-14'));
    }

    public function test_mode_sesi_tampil_lagi_di_sesi_baru(): void
    {
        $s = $this->keadaan(['terakhir_tampil_sesi' => 'sesi-lama']);

        $this->assertTrue($this->service()->perluTampil($s, 'SESI', 'sesi-baru', '2026-08-14'));
    }

    public function test_mode_harian_diam_pada_hari_yang_sama(): void
    {
        $s = $this->keadaan(['terakhir_tampil_tanggal' => '2026-08-14']);

        $this->assertFalse($this->service()->perluTampil($s, 'HARIAN', 'sesi-a', '2026-08-14'));
    }

    public function test_mode_harian_tampil_lagi_esok_hari_walau_sesinya_sama(): void
    {
        $s = $this->keadaan(['terakhir_tampil_tanggal' => '2026-08-14', 'terakhir_tampil_sesi' => 'sesi-a']);

        $this->assertTrue($this->service()->perluTampil($s, 'HARIAN', 'sesi-a', '2026-08-15'));
    }

    /**
     * Dulu status terkunci MEMAKSA modal tampil, apa pun penandanya. Aturan itu
     * dicabut 19 Agu 2026: yang terkunci sekarang dialihkan server ke formulir
     * (ArahkanKeFormulirPengumpulanData), jadi ia tidak pernah membuka halaman
     * lain. Memaksa modal untuknya hanya menghidupkan kembali layar mengurung
     * yang justru sedang kita hapus.
     */
    public function test_status_terkunci_tidak_lagi_memanggil_modal(): void
    {
        $s = $this->keadaan([
            'status' => 'TERKUNCI',
            'terkunci' => true,
            'terakhir_tampil_tanggal' => '2026-08-14',
            'terakhir_tampil_sesi' => 'sesi-a',
        ]);

        foreach (['SESI', 'HARIAN', 'HALAMAN'] as $frekuensi) {
            $this->assertFalse(
                $this->service()->perluTampil($s, $frekuensi, 'sesi-b', '2026-08-19'),
                "Frekuensi {$frekuensi} tidak boleh memunculkan modal bagi yang terkunci.",
            );
        }
    }

    public function test_frekuensi_tak_dikenal_diperlakukan_seperti_sesi(): void
    {
        $s = $this->keadaan(['terakhir_tampil_sesi' => 'sesi-a']);

        $this->assertFalse($this->service()->perluTampil($s, 'ENTAH-APA', 'sesi-a', '2026-08-14'));
    }

    public function test_sesi_kosong_tidak_membuat_modal_hilang(): void
    {
        // Bila id sesi tak terbaca, modal harus tetap muncul. Gagal ke arah
        // "tampilkan" — kebalikannya berarti orang diam-diam tak pernah
        // ditagih sama sekali.
        $s = $this->keadaan(['terakhir_tampil_sesi' => 'sesi-a']);

        $this->assertTrue($this->service()->perluTampil($s, 'SESI', null, '2026-08-14'));
    }
}
