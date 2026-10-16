<?php

namespace Tests\Feature\VISITOR\Permit\VehiclePermit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Pendaftaran endpoint dan dokumentasinya.
 *
 * `Url_Route` WAJIB berisi POLA rute, bukan nomor konkret: whitelist guard
 * dicocokkan ke `route()->uri()`. Pola yang salah membuat rute berparameter
 * ditolak 401 tanpa petunjuk penyebabnya — dan dua dari tiga endpoint di sini
 * berparameter.
 *
 * Uji dokumentasi bukan formalitas: penyaring OpenAPI menyajikan hanya endpoint
 * yang benar-benar ada di registry, jadi endpoint yang lupa didokumentasikan
 * berarti mitra tidak punya acuan untuk menandatangani permintaannya.
 */
class PermitTamuApiRegistrasiTest extends TestCase
{
    use DatabaseTransactions;

    private const PREFIKS = '/api/v1/hcis/visitor/permit-tamu';

    /** @var array<string,string> */
    private const POLA = [
        'ajukan' => '/api/v1/hcis/visitor/permit-tamu',
        'status' => '/api/v1/hcis/visitor/permit-tamu/{no_transaksi}',
        'batal' => '/api/v1/hcis/visitor/permit-tamu/{no_transaksi}/batal',
    ];

    public function test_ketiga_endpoint_terdaftar_dengan_pola_rute(): void
    {
        foreach (self::POLA as $peran => $pola) {
            $this->assertTrue(
                DB::table('N_HRIS_Api_Endpoint')
                    ->where('Url_Route', $pola)
                    ->where('Flag_Aktif', 'Y')
                    ->exists(),
                "Endpoint '{$peran}' ({$pola}) belum terdaftar di N_HRIS_Api_Endpoint.",
            );
        }
    }

    public function test_tidak_ada_url_route_berisi_nomor_konkret(): void
    {
        $salah = DB::table('N_HRIS_Api_Endpoint')
            ->where('Url_Route', 'LIKE', self::PREFIKS.'/%')
            ->where('Url_Route', 'NOT LIKE', '%{no_transaksi}%')
            ->pluck('Url_Route');

        $this->assertCount(
            0,
            $salah,
            'Url_Route berisi nomor konkret, bukan pola: '.$salah->implode(', '),
        );
    }

    public function test_pola_rute_cocok_dengan_rute_laravel(): void
    {
        // Inilah pemeriksaan yang benar-benar menutup jebakan 401 bisu: pola di
        // registry harus persis sama dengan route()->uri(), bukan sekadar mirip.
        foreach (self::POLA as $peran => $pola) {
            $this->assertContains(
                $pola,
                $this->rutePermitTamu(),
                "Pola '{$peran}' tidak cocok dengan rute Laravel mana pun.",
            );
        }
    }

    public function test_ketiga_rute_dijaga_hris_api_guard(): void
    {
        // Guard-nya sendiri sudah diuji terpisah; yang ditagih di sini adalah
        // bahwa rute BARU benar-benar memakainya. Rute TULIS yang lupa dijaga
        // berarti siapa pun di internet dapat menerbitkan izin gerbang.
        $rute = collect(app('router')->getRoutes())
            ->filter(fn ($r) => str_starts_with('/'.ltrim($r->uri(), '/'), self::PREFIKS));

        $this->assertCount(3, $rute, 'Jumlah rute permit mitra berubah.');

        foreach ($rute as $r) {
            $this->assertContains(
                'hris.api.guard',
                $r->gatherMiddleware(),
                'Rute '.$r->uri().' tidak dijaga hris.api.guard.',
            );
        }
    }

    public function test_ketiga_endpoint_ada_di_openapi(): void
    {
        $spec = Yaml::parseFile(base_path('resources/openapi/hcis-third-party.yaml'));

        foreach (self::POLA as $peran => $pola) {
            $this->assertArrayHasKey(
                $pola,
                $spec['paths'],
                "Path '{$peran}' ({$pola}) belum ada di hcis-third-party.yaml.",
            );
        }
    }

    public function test_skema_permit_tamu_terdefinisi(): void
    {
        // Path yang merujuk skema yang tidak ada menghasilkan dokumen rusak di
        // Swagger UI, dan mitra membaca halaman kosong alih-alih kontraknya.
        $spec = Yaml::parseFile(base_path('resources/openapi/hcis-third-party.yaml'));

        $this->assertArrayHasKey('PermitTamuRequest', $spec['components']['schemas']);
        $this->assertArrayHasKey('PermitTamuStatus', $spec['components']['schemas']);
    }

    public function test_header_idempotency_opsional_di_dokumentasi(): void
    {
        $isi = file_get_contents(base_path('resources/openapi/hcis-third-party.yaml'));

        $this->assertStringContainsString('Idempotency-Key', $isi);
        $this->assertStringContainsString('referensi_mitra', $isi);
        $this->assertStringNotContainsString('waktu_mulai', $isi);
        $this->assertStringNotContainsString('waktu_selesai', $isi);
    }

    /** @return array<int,string> */
    private function rutePermitTamu(): array
    {
        return collect(app('router')->getRoutes())
            ->map(fn ($r) => '/'.ltrim($r->uri(), '/'))
            ->filter(fn ($u) => str_starts_with($u, self::PREFIKS))
            ->unique()
            ->values()
            ->all();
    }
}
