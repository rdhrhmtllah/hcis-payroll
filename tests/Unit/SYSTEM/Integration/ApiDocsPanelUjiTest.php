<?php

namespace Tests\Unit\SYSTEM\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Panel "Try It Out" di /api/docs.
 *
 * Dua cacat nyata pernah lolos ke sini sekaligus, dan keduanya tidak menyalakan
 * galat apa pun di layar:
 *
 *  1. Formulir merakit payload sebagai string murni, sehingga properti bertipe
 *     array atau object selalu ditolak server dengan "must be an array" —
 *     terlihat seperti salah payload, padahal formulirnya yang kehilangan tipe.
 *  2. Tombol Execute memanggil fungsi async tanpa menangkap promise-nya, jadi
 *     satu galat menjadi unhandled rejection: spinner berputar selamanya dan
 *     pengguna menyimpulkan servernya menggantung.
 *
 * Berkasnya Blade berisi JS sebaris, jadi penjaga ini membaca sumbernya. Kasar,
 * tetapi ia menagih tepat dua hal yang dulu rusak.
 */
class ApiDocsPanelUjiTest extends TestCase
{
    private function sumber(): string
    {
        $path = dirname(__DIR__, 4).'/resources/views/SYSTEM/Integration/api-docs.blade.php';

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_isian_body_membawa_tipe_deklarasinya(): void
    {
        $s = $this->sumber();

        $this->assertStringContainsString('data-field-type="', $s, 'Isian body tidak lagi menyimpan tipe skemanya.');
        $this->assertStringContainsString('function bacaNilaiField(', $s);
        $this->assertStringContainsString('function kumpulkanBodyForm(', $s);
    }

    public function test_tidak_ada_lagi_perakit_body_yang_membuang_tipe(): void
    {
        // Pola lama: nilai isian dikirim apa adanya sebagai string. Ia sempat
        // tersalin ke LIMA tempat (pratinjau HMAC, salin cURL, dua tombol mode,
        // dan eksekusi), dan memperbaiki satu saja menyisakan empat lainnya.
        $this->assertStringNotContainsString(
            "payload[field] = val === '' ? null : val;",
            $this->sumber(),
            'Perakit body lama yang membuang tipe muncul kembali.',
        );
    }

    public function test_array_dan_objek_dipulihkan_ke_tipe_aslinya(): void
    {
        $s = $this->sumber();

        $this->assertMatchesRegularExpression(
            "/tipe === 'array' \|\| tipe === 'object'/",
            $s,
            'Tidak ada pemulihan tipe untuk properti array/object.',
        );
        $this->assertStringContainsString('return JSON.parse(teks);', $s);
    }

    public function test_tombol_execute_menangkap_kegagalannya(): void
    {
        $s = $this->sumber();

        $this->assertStringContainsString(
            'jalankanPermintaanLive(ep).catch(',
            $s,
            'Promise tombol Execute tidak ditangkap; galat akan kembali menjadi spinner abadi.',
        );
        $this->assertStringContainsString('function bebaskanTombolExecute(', $s);
    }

    public function test_permintaan_live_punya_batas_waktu(): void
    {
        $s = $this->sumber();

        $this->assertStringContainsString('AbortController', $s, 'Permintaan live tidak berbatas waktu.');
        $this->assertStringContainsString("e.name === 'AbortError'", $s);
    }
}
