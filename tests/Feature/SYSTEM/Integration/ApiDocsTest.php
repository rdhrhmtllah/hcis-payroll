<?php

namespace Tests\Feature\SYSTEM\Integration;

use App\Services\SYSTEM\Integration\OpenApiRegistryFilter;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dokumentasi OpenAPI yang disaring per aplikasi konsumen.
 *
 * Butuh DB dev: yang diuji justru relasi registry-nya. Digerbangi env seperti
 * uji dev lain di repo ini.
 *   RUN_API_DOCS_TEST=true php artisan test --filter ApiDocs
 */
class ApiDocsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! filter_var(env('RUN_API_DOCS_TEST', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Set RUN_API_DOCS_TEST=true untuk menjalankan (butuh registry di DB dev).');
        }
    }

    private function publicKey(string $namaAplikasi): string
    {
        $app = DB::table('N_HRIS_Api_Aplikasi')
            ->where('Nama_Aplikasi', $namaAplikasi)
            ->where('Flag_Aktif', 'Y')
            ->first(['Api_Public']);

        if (! $app) {
            $this->markTestSkipped("Aplikasi '{$namaAplikasi}' belum terdaftar di registry.");
        }

        return $app->Api_Public;
    }

    /** @test */
    public function tanpa_header_public_ditolak_401(): void
    {
        $this->getJson('/api/docs/openapi.json')->assertStatus(401);
    }

    /** @test */
    public function kunci_publik_tak_dikenal_ditolak_401(): void
    {
        $this->getJson('/api/docs/openapi.json', ['X-HC-Public' => 'TIDAK_ADA_KUNCI_INI'])
            ->assertStatus(401);
    }

    /** @test */
    public function perintah_curl_tidak_boleh_memakai_body_karangan(): void
    {
        // Penjaga sempit, dan sengaja begitu: yang sebenarnya membuat perintah
        // cURL ditolak 401 adalah body yang DI-HASH berbeda byte dari body yang
        // DIKIRIM. Dulu itu terjadi karena ada DUA literal — '{\n  "key":
        // "value"\n}' untuk di-hash dan '{"key": "value"}' untuk dikirim.
        //
        // Kebenaran hasilnya tidak bisa dibuktikan dari PHP; ia diperiksa di
        // peramban dengan membandingkan sha256 body hasil penguraian shell dan
        // tanda tangan terhadap hitungan server. Yang ditahan di sini hanya
        // kembalinya literal itu, yaitu bentuk regresi yang paling mungkin.
        $html = $this->get('/api/docs')->assertStatus(200)->getContent();

        $this->assertStringNotContainsString(
            '"key": "value"',
            $html,
            'Body contoh karangan kembali muncul. Body harus diambil dari editor "Try it out" '
            .'(atau contoh di dokumen), dan string yang di-hash harus SAMA PERSIS dengan yang dikirim.',
        );

        $this->assertStringContainsString(
            '--data-raw',
            $html,
            'Body dikirim dengan --data-raw supaya Postman mengimpornya apa adanya.',
        );
    }

    /** @test */
    public function dokumen_tak_boleh_disimpan_cache_mana_pun(): void
    {
        // Isi respons ini berbeda per konsumen, dan yang membedakannya adalah
        // sebuah HEADER — bukan URL. Cache yang berkunci URL saja akan
        // menyajikan dokumen milik satu konsumen kepada konsumen lain: persis
        // kebocoran yang seluruh penyaring ini ada untuk mencegahnya.
        //
        // Diuji pada jalur 401 supaya penjaganya tidak bergantung pada adanya
        // kunci sungguhan di DB.
        $respons = $this->getJson('/api/docs/openapi.json');

        $this->assertStringContainsString('X-HC-Public', (string) $respons->headers->get('Vary'));
        $this->assertStringContainsString('no-store', (string) $respons->headers->get('Cache-Control'));
    }

    /** @test */
    public function halaman_dokumentasi_tidak_menarik_aset_dari_luar(): void
    {
        // Halaman ini dibuka developer pihak ketiga dari jaringan kantor
        // mereka. CDN yang terblokir di sana menjatuhkan huruf atau skripnya
        // tanpa pesan apa pun, dan kita tak akan pernah melihat gejalanya.
        $html = $this->get('/api/docs')->assertStatus(200)->getContent();

        foreach (['fonts.googleapis.com', 'fonts.gstatic.com', 'cdn.tailwindcss.com', 'unpkg.com', 'cdn.jsdelivr.net'] as $host) {
            $this->assertStringNotContainsString($host, $html, "Aset wajib dari origin sendiri, bukan {$host}.");
        }
    }

    /** @test */
    public function web_careers_hanya_melihat_endpoint_kalender(): void
    {
        $paths = $this->getJson('/api/docs/openapi.json', [
            'X-HC-Public' => $this->publicKey('Web Careers'),
        ])->assertStatus(200)->json('paths');

        $this->assertEqualsCanonicalizing([
            '/api/v1/hcis/kalender-operasional',
            '/api/v1/hcis/kalender-operasional/tahun/{tahun}',
        ], array_keys($paths));
    }

    /** @test */
    public function hc_learn_melihat_himpunan_yang_saling_lepas_dari_web_careers(): void
    {
        // Dua aplikasi nyata dengan hak berbeda. Kalau penyaringnya rusak dan
        // meloloskan semuanya, uji ini yang menangkapnya — bukan uji di atas.
        $paths = array_keys($this->getJson('/api/docs/openapi.json', [
            'X-HC-Public' => $this->publicKey('HC Learn'),
        ])->assertStatus(200)->json('paths'));

        $this->assertNotContains('/api/v1/hcis/kalender-operasional', $paths);
        $this->assertNotContains('/api/v1/hcis/kalender-operasional/tahun/{tahun}', $paths);
        $this->assertContains('/api/v1/hris/third-party/sop/exam/complete', $paths);
    }

    /** @test */
    public function web_careers_tidak_melihat_tag_milik_konsumen_lain(): void
    {
        // Ditemukan saat memeriksa halaman sungguhan: path-nya sudah tersaring,
        // tapi Swagger UI masih menampilkan judul dan keterangan tag "SOP".
        $tags = array_column($this->getJson('/api/docs/openapi.json', [
            'X-HC-Public' => $this->publicKey('Web Careers'),
        ])->json('tags') ?? [], 'name');

        $this->assertSame(['Kalender Operasional'], $tags);
    }

    /** @test */
    public function path_memakai_pola_rute_bukan_nilai_konkret(): void
    {
        $paths = array_keys($this->getJson('/api/docs/openapi.json', [
            'X-HC-Public' => $this->publicKey('Web Careers'),
        ])->json('paths'));

        $this->assertContains('/api/v1/hcis/kalender-operasional/tahun/{tahun}', $paths);
        $this->assertEmpty(
            preg_grep('#/tahun/\d{4}$#', $paths),
            'Dokumen tidak boleh memuat tahun konkret; whitelist guard mencocokkan pola.',
        );
    }

    /** @test */
    public function server_diisi_dari_host_yang_sedang_dibuka(): void
    {
        $servers = $this->getJson('/api/docs/openapi.json', [
            'X-HC-Public' => $this->publicKey('Web Careers'),
        ])->json('servers');

        $this->assertNotEmpty($servers, 'servers wajib diisi saat penyajian, bukan ditulis di YAML.');
        $this->assertStringStartsWith('http', $servers[0]['url']);
    }

    /** @test */
    public function halaman_dokumentasi_tidak_membocorkan_daftar_endpoint(): void
    {
        $html = $this->get('/api/docs')->assertStatus(200)->getContent();

        $this->assertStringNotContainsString('kalender-operasional', $html);
        $this->assertStringNotContainsString('third-party', $html);
    }

    /**
     * @test
     *
     * Penjaga sinkron: menutup kelemahan utama pendekatan berkas-YAML, yaitu
     * menambah endpoint di registry lalu lupa mendokumentasikannya. Endpoint
     * yang sengaja belum didokumentasikan harus disebut satu per satu di sini,
     * dengan alasannya — bukan dibiarkan lolos diam-diam.
     */
    public function setiap_endpoint_aktif_di_registry_terdokumentasi(): void
    {
        $penyaring = app(OpenApiRegistryFilter::class);
        $terdokumentasi = $penyaring->jalurTerdaftar($penyaring->dokumenPenuh());

        /** @var array<string,string> alasan per "url|METHOD" */
        $dikecualikan = [
            // (kosong — setiap endpoint aktif saat ini sudah terdokumentasi)
        ];

        $belum = [];
        foreach (
            DB::table('N_HRIS_Api_Endpoint')->where('Flag_Aktif', 'Y')->get(['Url_Route', 'Method']) as $baris
        ) {
            $kunci = trim((string) $baris->Url_Route).'|'.strtoupper(trim((string) $baris->Method));
            if (! in_array($kunci, $terdokumentasi, true) && ! isset($dikecualikan[$kunci])) {
                $belum[] = $kunci;
            }
        }

        $this->assertSame([], $belum, "Endpoint aktif di registry tapi tidak ada di resources/openapi/hcis-third-party.yaml:\n  ".implode("\n  ", $belum));
    }

    /**
     * @test
     *
     * Arah sebaliknya: dokumen tidak boleh menjanjikan endpoint yang tidak ada
     * di registry, karena permintaan ke sana pasti dijawab 403.
     */
    public function dokumen_tidak_menjanjikan_endpoint_di_luar_registry(): void
    {
        $penyaring = app(OpenApiRegistryFilter::class);
        $terdokumentasi = $penyaring->jalurTerdaftar($penyaring->dokumenPenuh());

        $diRegistry = DB::table('N_HRIS_Api_Endpoint')
            ->where('Flag_Aktif', 'Y')
            ->get(['Url_Route', 'Method'])
            ->map(fn ($b) => trim((string) $b->Url_Route).'|'.strtoupper(trim((string) $b->Method)))
            ->all();

        $this->assertSame([], array_values(array_diff($terdokumentasi, $diRegistry)));
    }
}
