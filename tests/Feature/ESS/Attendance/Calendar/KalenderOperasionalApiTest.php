<?php

namespace Tests\Feature\ESS\Attendance\Calendar;

use App\Http\Middleware\HrisApiGuard;
use App\Services\ESS\Attendance\Calendar\Operational\KalenderOperasionalService;
use Tests\TestCase;

/**
 * Mengunci kontrak §6 spec
 * docs/superpowers/specs/2026-09-04-api-kalender-operasional-ho-design.md.
 *
 * Guard sengaja dilewati: yang diuji di sini kontraknya, bukan keamanannya
 * (keamanan diuji di HrisApiGuardJalurBertandaTanganTest). Service diganti
 * subkelas anonim supaya uji ini tidak menyentuh database sama sekali.
 */
class KalenderOperasionalApiTest extends TestCase
{
    /** @var list<array{unit:string, dari:string, sampai:string}> */
    public static array $panggilan = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(HrisApiGuard::class);
        self::$panggilan = [];

        $this->instance(KalenderOperasionalService::class, new class extends KalenderOperasionalService
        {
            public function untukRentang(string $unit, string $dari, string $sampai): array
            {
                KalenderOperasionalApiTest::$panggilan[] = compact('unit', 'dari', 'sampai');

                return [
                    'unit' => $unit,
                    'hari_kerja_mingguan' => [1, 2, 3, 4, 5, 6],
                    'digenerate_at' => '2026-09-02T08:00:00+07:00',
                    'tanggal_tidak_operasional' => [],
                ];
            }

            public function unitDikenal(string $unit): bool
            {
                return $unit === 'HEAD_OFFICE';
            }
        });
    }

    private function galat(string $url, string $pesan): void
    {
        $this->getJson($url)
            ->assertStatus(400)
            ->assertExactJson(['sukses' => false, 'message' => $pesan, 'result' => null]);
    }

    /** @test */
    public function dari_kosong_ditolak_400(): void
    {
        $this->galat(
            '/api/v1/hcis/kalender-operasional?sampai=2026-12-31',
            "Parameter 'dari' wajib diisi (format YYYY-MM-DD).",
        );
    }

    /** @test */
    public function sampai_kosong_ditolak_400(): void
    {
        $this->galat(
            '/api/v1/hcis/kalender-operasional?dari=2026-09-01',
            "Parameter 'sampai' wajib diisi (format YYYY-MM-DD).",
        );
    }

    /** @test */
    public function format_tanggal_salah_ditolak_400(): void
    {
        $this->galat(
            '/api/v1/hcis/kalender-operasional?dari=2026-13-45&sampai=2026-12-31',
            "Parameter 'dari' harus berformat YYYY-MM-DD.",
        );

        $this->galat(
            '/api/v1/hcis/kalender-operasional?dari=2026-09-01&sampai=01-12-2026',
            "Parameter 'sampai' harus berformat YYYY-MM-DD.",
        );
    }

    /** @test */
    public function sampai_lebih_awal_dari_dari_ditolak_400(): void
    {
        $this->galat(
            '/api/v1/hcis/kalender-operasional?dari=2026-12-31&sampai=2026-09-01',
            "Parameter 'sampai' tidak boleh lebih awal dari 'dari'.",
        );
    }

    /** @test */
    public function rentang_lebih_dari_400_hari_ditolak_400(): void
    {
        $this->galat(
            '/api/v1/hcis/kalender-operasional?dari=2026-01-01&sampai=2027-02-05',
            'Rentang tanggal maksimal 400 hari.',
        );
    }

    /** @test */
    public function rentang_tepat_400_hari_masih_diterima(): void
    {
        // Batasnya inklusif; 2026-01-01 s/d 2027-02-04 tepat 400 hari.
        $this->getJson('/api/v1/hcis/kalender-operasional?dari=2026-01-01&sampai=2027-02-04')
            ->assertStatus(200);
    }

    /** @test */
    public function unit_tak_dikenal_ditolak_400(): void
    {
        $this->galat(
            '/api/v1/hcis/kalender-operasional?dari=2026-09-01&sampai=2026-12-31&unit=GUDANG',
            "Unit 'GUDANG' tidak dikenal.",
        );
    }

    /** @test */
    public function tahun_di_luar_batas_ditolak_400(): void
    {
        $pesan = "Parameter 'tahun' harus 4 digit antara 2020 dan 2100.";

        $this->galat('/api/v1/hcis/kalender-operasional/tahun/2019', $pesan);
        $this->galat('/api/v1/hcis/kalender-operasional/tahun/2101', $pesan);
    }

    /** @test */
    public function tahun_bukan_empat_digit_ditolak_400(): void
    {
        $this->galat(
            '/api/v1/hcis/kalender-operasional/tahun/abcd',
            "Parameter 'tahun' harus 4 digit antara 2020 dan 2100.",
        );
    }

    /** @test */
    public function rentang_tanpa_libur_balas_200_dengan_array_kosong(): void
    {
        $this->getJson('/api/v1/hcis/kalender-operasional?dari=2026-09-01&sampai=2026-12-31')
            ->assertStatus(200)
            ->assertJson([
                'sukses' => true,
                'message' => 'Kalender operasional 2026-09-01 s/d 2026-12-31',
                'result' => [
                    'unit' => 'HEAD_OFFICE',
                    'dari' => '2026-09-01',
                    'sampai' => '2026-12-31',
                    'hari_kerja_mingguan' => [1, 2, 3, 4, 5, 6],
                    'tanggal_tidak_operasional' => [],
                ],
            ]);
    }

    /** @test */
    public function unit_baku_head_office_bila_tidak_dikirim(): void
    {
        $this->getJson('/api/v1/hcis/kalender-operasional?dari=2026-09-01&sampai=2026-09-30')
            ->assertStatus(200);

        $this->assertSame('HEAD_OFFICE', self::$panggilan[0]['unit']);
    }

    /** @test */
    public function endpoint_tahun_setara_rentang_satu_januari_sampai_tiga_puluh_satu_desember(): void
    {
        $this->getJson('/api/v1/hcis/kalender-operasional/tahun/2026')
            ->assertStatus(200)
            ->assertJson([
                'message' => 'Kalender operasional 2026',
                'result' => ['tahun' => 2026, 'unit' => 'HEAD_OFFICE'],
            ]);

        $this->assertSame(
            [['unit' => 'HEAD_OFFICE', 'dari' => '2026-01-01', 'sampai' => '2026-12-31']],
            self::$panggilan,
        );
    }

    /** @test */
    public function endpoint_tahun_tidak_membocorkan_field_rentang(): void
    {
        $result = $this->getJson('/api/v1/hcis/kalender-operasional/tahun/2026')->json('result');

        $this->assertArrayHasKey('tahun', $result);
        $this->assertArrayNotHasKey('dari', $result);
        $this->assertArrayNotHasKey('sampai', $result);
    }

    /** @test */
    public function bungkus_respons_memakai_kunci_sukses_bukan_success(): void
    {
        $respons = $this->getJson('/api/v1/hcis/kalender-operasional/tahun/2026')->assertStatus(200);

        $respons->assertJsonStructure(['sukses', 'message', 'result']);
        $this->assertArrayNotHasKey('success', $respons->json());
        $this->assertArrayNotHasKey('status', $respons->json());
    }
}
