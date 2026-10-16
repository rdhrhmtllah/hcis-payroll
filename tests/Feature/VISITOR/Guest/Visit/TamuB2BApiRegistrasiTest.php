<?php

namespace Tests\Feature\VISITOR\Guest\Visit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Pendaftaran endpoint tamu B2B dan dokumentasinya.
 *
 * `Url_Route` WAJIB berisi POLA rute, bukan nomor konkret: whitelist guard
 * dicocokkan ke `route()->uri()`. Pola yang salah membuat rute berparameter
 * ditolak `401` tanpa petunjuk penyebabnya — dan satu dari dua endpoint di sini
 * berparameter.
 *
 * Uji dokumentasi bukan formalitas: penyaring OpenAPI menyajikan hanya endpoint
 * yang benar-benar ada di registry, jadi endpoint yang lupa didokumentasikan
 * berarti mitra tidak punya acuan untuk menandatangani permintaannya.
 */
class TamuB2BApiRegistrasiTest extends TestCase
{
    use DatabaseTransactions;

    private const PREFIKS = '/api/v1/hcis/visitor/tamu-b2b';

    /** @var array<string,string> */
    private const POLA = [
        'ajukan' => '/api/v1/hcis/visitor/tamu-b2b',
        'status' => '/api/v1/hcis/visitor/tamu-b2b/{no_transaksi}',
    ];

    public function test_kedua_endpoint_terdaftar_dengan_pola_rute(): void
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
        // Inilah pemeriksaan yang menutup jebakan 401 bisu: pola di registry
        // harus persis sama dengan route()->uri(), bukan sekadar mirip.
        foreach (self::POLA as $peran => $pola) {
            $this->assertContains(
                $pola,
                $this->ruteTamuB2B(),
                "Pola '{$peran}' tidak cocok dengan rute Laravel mana pun.",
            );
        }
    }

    public function test_kedua_rute_dijaga_hris_api_guard(): void
    {
        // Rute TULIS yang lupa dijaga berarti siapa pun di internet dapat
        // mendaftarkan tamu dan menerbitkan izin gerbang atas namanya.
        $rute = collect(app('router')->getRoutes())
            ->filter(fn ($r) => str_starts_with('/'.ltrim($r->uri(), '/'), self::PREFIKS));

        $this->assertCount(2, $rute, 'Jumlah rute tamu B2B berubah.');

        foreach ($rute as $r) {
            $this->assertContains(
                'hris.api.guard',
                $r->gatherMiddleware(),
                'Rute '.$r->uri().' tidak dijaga hris.api.guard.',
            );
        }
    }

    public function test_kedua_endpoint_ada_di_openapi(): void
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

    public function test_skema_tamu_b2b_terdefinisi(): void
    {
        // Path yang merujuk skema yang tidak ada menghasilkan dokumen rusak di
        // Swagger UI, dan mitra membaca halaman kosong alih-alih kontraknya.
        $spec = Yaml::parseFile(base_path('resources/openapi/hcis-third-party.yaml'));

        $this->assertArrayHasKey('TamuB2BRequest', $spec['components']['schemas']);
        $this->assertArrayHasKey('TamuB2BStatus', $spec['components']['schemas']);
    }

    public function test_skema_mendokumentasikan_estimasi_dan_peringatan_transisi(): void
    {
        // DDL-2026-121: mitra mengirim estimasi_masuk/estimasi_keluar;
        // tanggal_kunjungan tinggal bentuk lama masa transisi (tidak lagi
        // wajib), dan balasannya membawa `peringatan`.
        $skema = Yaml::parseFile(base_path('resources/openapi/hcis-third-party.yaml'))['components']['schemas'];

        $this->assertArrayHasKey('estimasi_masuk', $skema['TamuB2BRequest']['properties']);
        $this->assertArrayHasKey('estimasi_keluar', $skema['TamuB2BRequest']['properties']);
        $this->assertNotContains('tanggal_kunjungan', $skema['TamuB2BRequest']['required'] ?? []);
        $this->assertTrue(
            $this->punyaKunci($skema['TamuB2BStatus'], 'peringatan'),
            'Balasan tamu B2B belum mendokumentasikan `peringatan` masa transisi.',
        );
    }

    public function test_dokumentasi_membedakan_pintu_ini_dari_permit_tamu(): void
    {
        // Dua endpoint mitra yang mirip namanya adalah sumber salah pilih yang
        // paling mungkin. Perbedaannya harus tertulis, bukan disimpulkan sendiri.
        $isi = file_get_contents(base_path('resources/openapi/hcis-third-party.yaml'));

        $this->assertStringContainsString('Kapan memakai ini, bukan `/permit-tamu`', $isi);
        $this->assertStringContainsString('Permit COKLAT diajukan otomatis setelah disetujui', $isi);
    }

    private function punyaKunci(mixed $simpul, string $kunci): bool
    {
        if (! is_array($simpul)) {
            return false;
        }

        if (array_key_exists($kunci, $simpul)) {
            return true;
        }

        foreach ($simpul as $anak) {
            if ($this->punyaKunci($anak, $kunci)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int,string> */
    private function ruteTamuB2B(): array
    {
        return collect(app('router')->getRoutes())
            ->map(fn ($r) => '/'.ltrim($r->uri(), '/'))
            ->filter(fn ($u) => str_starts_with($u, self::PREFIKS))
            ->unique()
            ->values()
            ->all();
    }
}
