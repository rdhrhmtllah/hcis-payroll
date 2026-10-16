<?php

namespace Tests\Unit\VISITOR\Permit\Inspection;

use App\Http\Controllers\VISITOR\Permit\VehiclePermit\VehiclePermitController;
use App\Services\VISITOR\Permit\Inspection\JadwalInspeksiService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Uji logika jadwal inspeksi terlewat hari dan konsistensi status SQL.
 * Uji unit murni: tidak membutuhkan koneksi database.
 */
class JadwalInspeksiLewatTanggalTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_lewat_hari_inspeksi_mengenali_jadwal_kemarin_tanpa_hasil(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-12 10:00:00'));

        $jadwal = (new \ReflectionClass(JadwalInspeksiService::class))->newInstanceWithoutConstructor();

        // 1. Booking kemarin masih TERJADWAL -> lewat hari
        $bookingKemarin = (object) [
            'Status' => JadwalInspeksiService::TERJADWAL,
            'Waktu_Mulai' => '2026-10-11 08:00:00',
        ];
        $this->assertTrue($jadwal->lewatHariInspeksi($bookingKemarin));

        // 2. Booking hari ini jam 08:00 (hari H belum selesai) -> TIDAK lewat hari
        $bookingHariIni = (object) [
            'Status' => JadwalInspeksiService::TERJADWAL,
            'Waktu_Mulai' => '2026-10-12 08:00:00',
        ];
        $this->assertFalse($jadwal->lewatHariInspeksi($bookingHariIni));

        // 3. Booking besok -> TIDAK lewat hari
        $bookingBesok = (object) [
            'Status' => JadwalInspeksiService::TERJADWAL,
            'Waktu_Mulai' => '2026-10-13 08:00:00',
        ];
        $this->assertFalse($jadwal->lewatHariInspeksi($bookingBesok));

        // 4. Booking kemarin tapi sudah SELESAI -> TIDAK lewat hari
        $bookingSelesai = (object) [
            'Status' => JadwalInspeksiService::SELESAI,
            'Waktu_Mulai' => '2026-10-11 08:00:00',
        ];
        $this->assertFalse($jadwal->lewatHariInspeksi($bookingSelesai));
    }

    public function test_status_case_sql_memakai_waktu_bisnis_dan_memeriksa_kedaluwarsa_detail(): void
    {
        $controllerRef = new \ReflectionClass(VehiclePermitController::class);
        $method = $controllerRef->getMethod('statusCaseSql');
        $method->setAccessible(true);

        $permitQuery = new VisitorPermitQueryService();
        $controller = $controllerRef->newInstanceWithoutConstructor();

        // Inject permitQuery property jika diperlukan
        $prop = $controllerRef->getProperty('permitQuery');
        $prop->setAccessible(true);
        $prop->setValue($controller, $permitQuery);

        $sql = (string) $method->invoke($controller);

        // Harus memeriksa kedaluwarsa waktu/tanggal detail
        $this->assertStringContainsString('Tanggal_Kadaluarsa', $sql);
        $this->assertStringContainsString('Waktu_Berakhir', $sql);
        // Harus menggunakan waktu bisnis (bukan GETDATE mentah yang terpaku ke zona server DB)
        $this->assertStringNotContainsString('GETDATE()', $sql);
    }
}
