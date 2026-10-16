<?php

namespace Tests\Integration\VISITOR\Operations;

use App\Services\VISITOR\Permit\VehiclePermit\PermitCoklatTamuService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Satu cabang penyapu yang gagal tidak menghentikan ringkasan dan tetap terlihat
 * sebagai FAILURE bagi penjadwal (audit VMS 27 Sep, Low).
 */
class PenyapuCabangTerisolasiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cabang_gagal_dilaporkan_tanpa_menghentikan_sapuan(): void
    {
        $this->mock(PermitCoklatTamuService::class)
            ->shouldReceive('tutupKunjunganBerlalu')
            ->andThrow(new \RuntimeException('uji gagal'));

        $this->artisan('visitor:expire-efektif')
            ->expectsOutputToContain("Cabang 'coklat kunjungan berlalu' gagal: uji gagal")
            ->expectsOutputToContain('coklat kunjungan berlalu: 0')
            ->assertExitCode(1);
    }
}
