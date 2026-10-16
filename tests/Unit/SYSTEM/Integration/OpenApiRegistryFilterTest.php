<?php

namespace Tests\Unit\SYSTEM\Integration;

use App\Services\SYSTEM\Integration\OpenApiRegistryFilter;
use PHPUnit\Framework\TestCase;

/**
 * Penyaring dokumen OpenAPI terhadap hak akses aplikasi.
 *
 * Bagian yang diuji di sini MURNI: menerima dokumen dan daftar izin, tidak
 * menyentuh database. Pencocokan memakai POLA rute (".../tahun/{tahun}"),
 * sama seperti HrisApiGuard mencocokkan whitelist — kalau keduanya berbeda,
 * dokumen akan menjanjikan endpoint yang ternyata 403.
 */
class OpenApiRegistryFilterTest extends TestCase
{
    private OpenApiRegistryFilter $penyaring;

    protected function setUp(): void
    {
        parent::setUp();
        $this->penyaring = new OpenApiRegistryFilter;
    }

    private function dokumen(): array
    {
        return [
            'openapi' => '3.1.0',
            'info' => ['title' => 'HCIS', 'version' => '1.0.0'],
            'paths' => [
                '/api/v1/hcis/kalender-operasional' => [
                    'get' => [
                        'summary' => 'Rentang',
                        'tags' => ['Kalender Operasional'],
                        'parameters' => [['$ref' => '#/components/parameters/Unit']],
                        'responses' => [
                            '200' => ['content' => ['application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/Kalender'],
                            ]]],
                            '400' => ['$ref' => '#/components/responses/Galat400'],
                        ],
                    ],
                ],
                '/api/v1/hcis/kalender-operasional/tahun/{tahun}' => [
                    'get' => ['summary' => 'Tahun', 'tags' => ['Kalender Operasional']],
                ],
                '/api/v1/hris/third-party/sop/exam/complete' => [
                    'post' => [
                        'summary' => 'Callback SOP',
                        'tags' => ['SOP'],
                        'requestBody' => ['content' => ['application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/SopKuisCallback'],
                        ]]],
                        'responses' => ['200' => ['$ref' => '#/components/responses/HanyaSop']],
                    ],
                ],
            ],
            'tags' => [
                ['name' => 'Kalender Operasional', 'description' => 'Tanggal HO tidak beroperasi.'],
                ['name' => 'SOP', 'description' => 'Callback hasil kuis dari HC Learn.'],
            ],
            'components' => [
                'schemas' => [
                    'Galat' => ['type' => 'object'],
                    'Kalender' => [
                        'type' => 'object',
                        'properties' => ['isi' => ['$ref' => '#/components/schemas/Tanggal']],
                    ],
                    'Tanggal' => ['type' => 'object'],
                    'SopKuisCallback' => [
                        'type' => 'object',
                        'properties' => ['Kode_Karyawan' => ['type' => 'string']],
                    ],
                ],
                'responses' => [
                    'Galat400' => [
                        'description' => 'Galat',
                        'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Galat']]],
                    ],
                    'HanyaSop' => ['description' => 'Khusus SOP'],
                ],
                'parameters' => [
                    'Unit' => ['name' => 'unit', 'in' => 'query'],
                ],
                'securitySchemes' => [
                    'HcPublic' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-HC-Public'],
                ],
            ],
        ];
    }

    /** @test */
    public function skema_milik_endpoint_yang_tersaring_ikut_dibuang(): void
    {
        // Ditemukan saat memeriksa halaman sungguhan: bagian "Schemas" di Swagger
        // UI masih memperlihatkan SopKuisCallback beserta field-nya kepada Web
        // Careers, padahal endpoint SOP-nya sudah tersaring. Sama keluarganya
        // dengan kebocoran tag, tapi lebih serius karena membuka bentuk data.
        $hasil = $this->penyaring->saring($this->dokumen(), [
            ['url' => '/api/v1/hcis/kalender-operasional', 'method' => 'GET'],
        ]);

        $this->assertArrayNotHasKey('SopKuisCallback', $hasil['components']['schemas']);
        $this->assertArrayNotHasKey('HanyaSop', $hasil['components']['responses']);
    }

    /** @test */
    public function skema_yang_dirujuk_secara_berantai_tetap_dipertahankan(): void
    {
        // Kalender -> Tanggal, dan Galat400 -> Galat. Memangkas terlalu rajin
        // menghasilkan dokumen dengan $ref menggantung, yang di Swagger UI
        // tampil sebagai skema kosong tanpa penjelasan apa pun.
        $hasil = $this->penyaring->saring($this->dokumen(), [
            ['url' => '/api/v1/hcis/kalender-operasional', 'method' => 'GET'],
        ]);

        $this->assertEqualsCanonicalizing(
            ['Kalender', 'Tanggal', 'Galat'],
            array_keys($hasil['components']['schemas']),
        );
        $this->assertArrayHasKey('Galat400', $hasil['components']['responses']);
        $this->assertArrayHasKey('Unit', $hasil['components']['parameters']);
    }

    /** @test */
    public function security_schemes_tidak_pernah_dibuang(): void
    {
        // Tidak dirujuk lewat $ref, melainkan lewat blok `security` di akar.
        // Memangkasnya membuat dokumen kehilangan cara autentikasinya.
        $hasil = $this->penyaring->saring($this->dokumen(), []);

        $this->assertArrayHasKey('HcPublic', $hasil['components']['securitySchemes']);
        $this->assertSame([], $hasil['components']['schemas']);
    }

    /** @test */
    public function tag_yang_tak_lagi_punya_operasi_ikut_dibuang(): void
    {
        // Ditemukan saat memeriksa halaman sungguhan: Swagger UI tetap
        // menampilkan judul dan keterangan tag meski seluruh endpoint-nya
        // tersaring. Bagi Web Careers itu berarti melihat "SOP — Callback hasil
        // kuis dari HC Learn", yaitu justru kebocoran antar-konsumen yang
        // penyaring ini ada untuk mencegahnya.
        $hasil = $this->penyaring->saring($this->dokumen(), [
            ['url' => '/api/v1/hcis/kalender-operasional', 'method' => 'GET'],
        ]);

        $this->assertSame(['Kalender Operasional'], array_column($hasil['tags'], 'name'));
    }

    /** @test */
    public function tanpa_izin_seluruh_tag_dibuang(): void
    {
        $this->assertSame([], $this->penyaring->saring($this->dokumen(), [])['tags']);
    }

    /** @test */
    public function tag_yang_tidak_dideklarasikan_operasi_tidak_dikarang(): void
    {
        $dokumen = $this->dokumen();
        $dokumen['paths']['/api/v1/hcis/kalender-operasional']['get']['tags'] = ['Tag Tak Terdaftar'];

        $hasil = $this->penyaring->saring($dokumen, [
            ['url' => '/api/v1/hcis/kalender-operasional', 'method' => 'GET'],
        ]);

        $this->assertSame([], $hasil['tags'], 'Hanya tag yang memang dideklarasikan di dokumen yang boleh tersisa.');
    }

    /** @test */
    public function hanya_endpoint_yang_diizinkan_yang_tersisa(): void
    {
        $hasil = $this->penyaring->saring($this->dokumen(), [
            ['url' => '/api/v1/hcis/kalender-operasional', 'method' => 'GET'],
            ['url' => '/api/v1/hcis/kalender-operasional/tahun/{tahun}', 'method' => 'GET'],
        ]);

        $this->assertSame(
            ['/api/v1/hcis/kalender-operasional', '/api/v1/hcis/kalender-operasional/tahun/{tahun}'],
            array_keys($hasil['paths']),
        );
    }

    /** @test */
    public function aplikasi_lain_melihat_himpunan_yang_berbeda(): void
    {
        // Saling lepas: yang membuktikan penyaring benar-benar menyaring, bukan
        // kebetulan meloloskan semuanya.
        $hasil = $this->penyaring->saring($this->dokumen(), [
            ['url' => '/api/v1/hris/third-party/sop/exam/complete', 'method' => 'POST'],
        ]);

        $this->assertSame(['/api/v1/hris/third-party/sop/exam/complete'], array_keys($hasil['paths']));
    }

    /** @test */
    public function tanpa_izin_paths_jadi_kosong_bukan_hilang(): void
    {
        $hasil = $this->penyaring->saring($this->dokumen(), []);

        $this->assertSame([], $hasil['paths']);
        $this->assertArrayHasKey('paths', $hasil, 'paths wajib tetap ada; dokumen tanpa paths bukan OpenAPI yang sah.');
    }

    /** @test */
    public function operasi_lain_pada_path_yang_sama_ikut_dibuang(): void
    {
        $dokumen = $this->dokumen();
        $dokumen['paths']['/api/v1/hcis/kalender-operasional']['delete'] = ['summary' => 'Rahasia'];

        $hasil = $this->penyaring->saring($dokumen, [
            ['url' => '/api/v1/hcis/kalender-operasional', 'method' => 'GET'],
        ]);

        $this->assertSame(['get'], array_keys($hasil['paths']['/api/v1/hcis/kalender-operasional']));
    }

    /** @test */
    public function path_yang_seluruh_operasinya_dibuang_ikut_hilang(): void
    {
        $hasil = $this->penyaring->saring($this->dokumen(), [
            ['url' => '/api/v1/hcis/kalender-operasional', 'method' => 'POST'],
        ]);

        $this->assertSame([], $hasil['paths'], 'Method tidak cocok berarti path tidak boleh muncul.');
    }

    /** @test */
    public function method_dicocokkan_tanpa_peduli_besar_kecil_huruf(): void
    {
        $hasil = $this->penyaring->saring($this->dokumen(), [
            ['url' => '/api/v1/hcis/kalender-operasional', 'method' => 'get'],
        ]);

        $this->assertSame(['/api/v1/hcis/kalender-operasional'], array_keys($hasil['paths']));
    }

    /** @test */
    public function spasi_ekor_pada_url_registry_tidak_membuat_endpoint_hilang(): void
    {
        // Url_Route bertipe varchar dan nilainya kerap berspasi ekor.
        $hasil = $this->penyaring->saring($this->dokumen(), [
            ['url' => ' /api/v1/hcis/kalender-operasional ', 'method' => 'GET '],
        ]);

        $this->assertSame(['/api/v1/hcis/kalender-operasional'], array_keys($hasil['paths']));
    }

    /** @test */
    public function bagian_selain_paths_dipertahankan_utuh(): void
    {
        $hasil = $this->penyaring->saring($this->dokumen(), [
            ['url' => '/api/v1/hcis/kalender-operasional', 'method' => 'GET'],
        ]);

        $this->assertSame('3.1.0', $hasil['openapi']);
        $this->assertSame(['title' => 'HCIS', 'version' => '1.0.0'], $hasil['info']);
        $this->assertArrayHasKey('Galat', $hasil['components']['schemas']);
    }

    /** @test */
    public function jalur_terdaftar_mendaftar_semua_pasangan_url_dan_method(): void
    {
        $terdaftar = $this->penyaring->jalurTerdaftar($this->dokumen());

        $this->assertEqualsCanonicalizing([
            '/api/v1/hcis/kalender-operasional|GET',
            '/api/v1/hcis/kalender-operasional/tahun/{tahun}|GET',
            '/api/v1/hris/third-party/sop/exam/complete|POST',
        ], $terdaftar);
    }
}
