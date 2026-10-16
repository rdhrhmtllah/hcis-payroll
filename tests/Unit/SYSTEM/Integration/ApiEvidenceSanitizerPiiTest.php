<?php

namespace Tests\Unit\SYSTEM\Integration;

use App\Services\SYSTEM\Integration\ApiEvidenceSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * Log bukti API tidak menyimpan data pribadi tamu (nomor identitas/KTP, nomor
 * HP) — hanya rahasia yang dulu diredaksi (audit VMS 27 Sep, Low).
 */
class ApiEvidenceSanitizerPiiTest extends TestCase
{
    public function test_nomor_identitas_dan_hp_diredaksi_termasuk_bersarang(): void
    {
        $body = json_encode([
            'nama_tamu' => 'Budi',
            'no_identitas' => '3201010101010001',
            'no_hp' => '081234567890',
            'anggota' => [['nama' => 'Ani', 'nomor_handphone' => '0812', 'nik' => '3201']],
            'api_key' => 'rahasia',
        ]);

        $hasil = json_decode((new ApiEvidenceSanitizer)->ringkasBody($body, 2000), true);

        $this->assertSame('Budi', $hasil['nama_tamu']);
        $this->assertSame('[PII_REDACTED]', $hasil['no_identitas']);
        $this->assertSame('[PII_REDACTED]', $hasil['no_hp']);
        $this->assertSame('[PII_REDACTED]', $hasil['anggota'][0]['nomor_handphone']);
        $this->assertSame('[PII_REDACTED]', $hasil['anggota'][0]['nik']);
        $this->assertSame('[REDACTED]', $hasil['api_key']);
        $this->assertSame('Ani', $hasil['anggota'][0]['nama']);
    }
}
