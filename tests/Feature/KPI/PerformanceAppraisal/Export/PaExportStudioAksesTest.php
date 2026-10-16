<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Export;

use App\Http\Controllers\KPI\PerformanceAppraisal\Export\PaExportStudioController;
use App\Http\Requests\KPI\PerformanceAppraisal\Export\SavePaReportTemplateRequest;
use App\Services\SYSTEM\Application\Reports\DatasetRegistry;
use App\Services\SYSTEM\Application\Reports\DefinisiValidator;
use Illuminate\Support\Facades\Route as RouteFacade;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Tests\TestCase;

/**
 * Uji akses Export Studio PA tanpa login dan tanpa basis data.
 *
 * Uji `REPORT_DATASET_UNKNOWN` sempat ditulis untuk menuntut login (helper
 * `actingAsAdminPa()`) dan dipindahkan ke sini di tugas 9 -- lihat brief
 * tugas 5. Tugas 9 lalu MEMBATALKAN rencana login itu: `DatasetRegistry::ambil()`
 * (lapisan katalog) melempar `RuntimeException` POLOS tanpa kode galat sama
 * sekali (lihat `DatasetRegistryTest`); `REPORT_DATASET_UNKNOWN` sungguhan
 * lahir di `PaExportStudioController::datasetUnknown()`, dipanggil dari
 * `katalog()`/`galeri()` SEBELUM `Auth::user()` disentuh. Jadi kontraknya
 * teruji langsung di controller, tanpa login dan tanpa basis data sama sekali.
 */
class PaExportStudioAksesTest extends TestCase
{
    private const API_PREFIX = 'api/v1/performance-appraisal/export-studio';

    public function test_rute_halaman_terdaftar_dengan_nama_pa_export_studio(): void
    {
        $this->assertTrue(RouteFacade::has('pa.export-studio'));
    }

    public function test_tamu_dialihkan_ke_login(): void
    {
        $this->get('/performance-appraisal/export-studio')->assertRedirect();
    }

    public function test_api_juga_digerbangi_bukan_hanya_halamannya(): void
    {
        // Menggerbangi halaman saja hanya menyembunyikan tombolnya; datanya tetap
        // bisa ditarik langsung dari endpoint.
        $this->getJson(self::API_PREFIX.'/pa/katalog/hasil')
            ->assertStatus(401);
    }

    public function test_sembilan_rute_api_terdaftar_dengan_prefix_dan_metode_benar(): void
    {
        $routes = collect(RouteFacade::getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), self::API_PREFIX.'/{studioDataset}'))
            ->map(fn ($route) => [
                'methods' => collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->sort()->values()->all(),
                'uri' => $route->uri(),
                'middleware' => $route->gatherMiddleware(),
            ])
            ->values();

        $expected = [
            ['methods' => ['GET'], 'uri' => self::API_PREFIX.'/{studioDataset}/katalog/{dataset}'],
            ['methods' => ['GET'], 'uri' => self::API_PREFIX.'/{studioDataset}/template'],
            ['methods' => ['POST'], 'uri' => self::API_PREFIX.'/{studioDataset}/template'],
            ['methods' => ['PUT'], 'uri' => self::API_PREFIX.'/{studioDataset}/template/{kode}'],
            ['methods' => ['POST'], 'uri' => self::API_PREFIX.'/{studioDataset}/template/{kode}/duplikat'],
            ['methods' => ['DELETE'], 'uri' => self::API_PREFIX.'/{studioDataset}/template/{kode}'],
            ['methods' => ['POST'], 'uri' => self::API_PREFIX.'/{studioDataset}/pratinjau'],
            ['methods' => ['POST'], 'uri' => self::API_PREFIX.'/{studioDataset}/jalankan'],
        ];

        // 8 rute API + 1 rute halaman (diperiksa terpisah di uji lain) = 9 rute
        // Export Studio secara keseluruhan, sesuai sembilan metode controller.
        $this->assertCount(8, $routes);

        foreach ($expected as $expectation) {
            $match = $routes->first(fn ($r) => $r['uri'] === $expectation['uri'] && $r['methods'] === $expectation['methods']);
            $this->assertNotNull($match, "Rute {$expectation['methods'][0]} {$expectation['uri']} tidak ditemukan.");
        }

        // Uji `test_api_juga_digerbangi_bukan_hanya_halamannya` hanya menuntut 401
        // -- dan 401 itu sudah dihasilkan middleware `auth` sendirian, TANPA
        // `check.jabatan` sama sekali. Mencabut `check.jabatan` dari grup API,
        // atau salah ketik kuncinya jadi kunci halaman lain yang lebih longgar,
        // membuat seluruh suite tetap hijau sementara data penilaian seluruh
        // perusahaan terbaca oleh setiap karyawan yang login. Rute dokblok file
        // rute ini menjanjikan SATU kunci hak yang sama dengan gerbang halaman
        // -- ditegaskan langsung di sini, per rute, bukan hanya di komentar.
        foreach ($routes as $route) {
            $this->assertContains(
                'check.jabatan:PaExportStudioPage',
                $route['middleware'],
                "Rute {$route['uri']} tidak memuat middleware check.jabatan:PaExportStudioPage."
            );
        }
    }

    /**
     * Menguji sambungan galeri ke template sistem tanpa login dan tanpa basis
     * data: kalau method privat `templates()` di controller lupa menyalurkan
     * PaSystemTemplates::untuk($dataset) ke ReportTemplateService, galeri
     * "resmi" kosong secara diam-diam -- tidak ada satu pun error yang muncul,
     * cuma tab galeri yang tidak pernah terisi. Diuji lewat reflection karena
     * memanggil controller sungguhan (galeri()) menuntut Auth::user() yang
     * berarti login sungguhan.
     */
    public function test_studio_merakit_template_sistem_untuk_setiap_dataset(): void
    {
        $controller = (new ReflectionClass(PaExportStudioController::class))->newInstanceWithoutConstructor();

        // templates() hanya menyentuh $registry & $validator untuk merakit
        // ReportTemplateService -- keduanya aman diisi nyata (tanpa basis data)
        // lewat reflection, tanpa perlu menyusun dependensi controller penuh
        // (ReportRunner, PaReportExportService) yang menuntut Auth/DB.
        $reflection = new ReflectionClass(PaExportStudioController::class);
        $registryProp = $reflection->getProperty('registry');
        $registryProp->setAccessible(true);
        $registryProp->setValue($controller, new DatasetRegistry);
        $validatorProp = $reflection->getProperty('validator');
        $validatorProp->setAccessible(true);
        $validatorProp->setValue($controller, new DefinisiValidator);

        $templates = new ReflectionMethod(PaExportStudioController::class, 'templates');
        $templates->setAccessible(true);

        foreach (['hasil', 'penugasan', 'kompetensi', 'peserta'] as $dataset) {
            $service = $templates->invoke($controller, $dataset);

            $templateSistem = new ReflectionProperty($service, 'templateSistem');
            $templateSistem->setAccessible(true);

            $this->assertNotEmpty(
                $templateSistem->getValue($service),
                "ReportTemplateService untuk dataset {$dataset} tidak menerima template sistem apa pun."
            );
        }
    }

    /**
     * Kontrak katalog: `DatasetRegistry::ambil()` untuk kunci dataset asing
     * melempar `RuntimeException` POLOS -- tidak ada `error_code` di sini sama
     * sekali. Ditulis dulu supaya asumsi "kode galatnya lahir di lapisan
     * katalog" tidak diam-diam dianggap benar tanpa pernah diperiksa.
     */
    public function test_dataset_registry_ambil_melempar_runtime_exception_polos_tanpa_kode_galat(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Dataset tidak dikenal: pa/dataset-asing');

        app(DatasetRegistry::class)->ambil('pa', 'dataset-asing');
    }

    /**
     * `REPORT_DATASET_UNKNOWN` sungguhan lahir di controller, bukan di
     * `DatasetRegistry`. `galeri()` memeriksa `datasetValid()` SEBELUM
     * menyentuh `$this->registry`/`Auth::user()`, jadi method controller
     * sungguhan bisa dipanggil langsung lewat instans tanpa konstruktor --
     * tanpa login, tanpa HTTP, tanpa basis data.
     */
    public function test_dataset_bernama_asing_menjawab_kode_report_dataset_unknown(): void
    {
        $controller = (new ReflectionClass(PaExportStudioController::class))->newInstanceWithoutConstructor();

        $response = $controller->galeri('dataset-asing');
        $payload = json_decode($response->getContent(), true);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertFalse($payload['success']);
        $this->assertSame('REPORT_DATASET_UNKNOWN', $payload['error_code']);
    }

    /** Jalur `katalog()` melempar kode yang sama, lewat pemeriksaan dua bagian (studioDataset vs dataset). */
    public function test_katalog_dataset_bernama_asing_juga_menjawab_kode_report_dataset_unknown(): void
    {
        $controller = (new ReflectionClass(PaExportStudioController::class))->newInstanceWithoutConstructor();

        $response = $controller->katalog('dataset-asing', 'dataset-asing');
        $payload = json_decode($response->getContent(), true);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('REPORT_DATASET_UNKNOWN', $payload['error_code']);
    }

    /**
     * `PaExportStudioController::DATASETS` adalah konstanta TERPISAH dari
     * `DatasetRegistry` -- tidak dibaca dari sana, cuma ditulis ulang secara
     * manual. Kalau dataset baru terdaftar di registry (mis. tahap 2 nanti:
     * `kompetensi`, `peserta`) tapi konstanta ini lupa diperbarui, controller
     * akan menolaknya sebagai `REPORT_DATASET_UNKNOWN` padahal registry
     * mengenalnya -- gejala yang menyesatkan karena kelihatannya seperti
     * dataset memang belum ada, padahal cuma dua daftar yang tidak sinkron.
     *
     * `DatasetRegistry::semua('pa')` tidak menyentuh basis data (dataset PA
     * tidak butuh dependensi apa pun di konstruktornya), jadi ini aman
     * tinggal di suite tanpa DB.
     */
    public function test_konstanta_datasets_controller_sama_dengan_kunci_terdaftar_di_registry_modul_pa(): void
    {
        $konstanta = (new ReflectionClass(PaExportStudioController::class))->getConstant('DATASETS');
        $kunciRegistry = array_keys(app(DatasetRegistry::class)->semua('pa'));

        sort($konstanta);
        sort($kunciRegistry);

        $this->assertSame(
            $kunciRegistry,
            $konstanta,
            'PaExportStudioController::DATASETS menyimpang dari kunci dataset yang terdaftar di DatasetRegistry '
            .'untuk modul pa -- dataset yang sudah terdaftar bisa ditolak REPORT_DATASET_UNKNOWN.'
        );
    }

    /**
     * `PaExportStudioController::DATASET_LABELS` (nama laporan bawaan yang
     * mengalir ke judul job di Pusat Aktivitas & nama berkas ekspor) adalah
     * peta TERPISAH lagi, ditulis ulang secara manual seperti DATASETS.
     * Dataset baru yang lupa ditambahkan ke sini tidak akan ditolak seperti
     * DATASETS/REPORT_DATASET_UNKNOWN -- gejalanya justru diam-diam memakai
     * label fallback "Laporan Performance Appraisal", jauh lebih sulit
     * dicurigai karena tidak ada satu pun galat.
     */
    public function test_peta_dataset_labels_controller_sama_dengan_kunci_terdaftar_di_registry_modul_pa(): void
    {
        $peta = (new ReflectionClass(PaExportStudioController::class))->getConstant('DATASET_LABELS');
        $kunciRegistry = array_keys(app(DatasetRegistry::class)->semua('pa'));

        $kunciPeta = array_keys($peta);
        sort($kunciPeta);
        sort($kunciRegistry);

        $this->assertSame(
            $kunciRegistry,
            $kunciPeta,
            'PaExportStudioController::DATASET_LABELS menyimpang dari kunci dataset yang terdaftar di DatasetRegistry '
            .'untuk modul pa -- dataset yang sudah terdaftar bisa jatuh ke label fallback tanpa satu pun galat.'
        );
    }

    /**
     * `SavePaReportTemplateRequest::rules()` menulis ulang daftar dataset lagi
     * lewat aturan `in:hasil,penugasan` -- daftar KETIGA yang tidak dijaga.
     * Skenario nyata yang membingungkan: dataset baru didaftarkan di registry,
     * DATASETS diperbarui, seluruh uji katalog/pratinjau/jalankan hijau --
     * tapi MENYIMPAN TEMPLATE untuk dataset itu menjawab 422 karena aturan
     * `in:` di sini lupa diperbarui. Katalog, pratinjau, dan jalankan tidak
     * lewat FormRequest ini sama sekali, jadi mereka tidak akan
     * memperlihatkan gejalanya.
     */
    public function test_aturan_in_dataset_pada_save_template_request_sama_dengan_registry_modul_pa(): void
    {
        $rules = (new SavePaReportTemplateRequest)->rules();
        $aturanDataset = (array) ($rules['definisi.dataset'] ?? []);

        $aturanIn = null;
        foreach ($aturanDataset as $aturan) {
            if (is_string($aturan) && str_starts_with($aturan, 'in:')) {
                $aturanIn = substr($aturan, 3);
                break;
            }
        }

        $this->assertNotNull($aturanIn, "Aturan 'definisi.dataset' pada SavePaReportTemplateRequest tidak memuat aturan in:.");

        $kunciAturan = explode(',', $aturanIn);
        $kunciRegistry = array_keys(app(DatasetRegistry::class)->semua('pa'));

        sort($kunciAturan);
        sort($kunciRegistry);

        $this->assertSame(
            $kunciRegistry,
            $kunciAturan,
            "Aturan in: pada SavePaReportTemplateRequest::rules()['definisi.dataset'] menyimpang dari kunci dataset "
            .'yang terdaftar di DatasetRegistry untuk modul pa -- dataset yang sudah terdaftar bisa ditolak 422 '
            .'saat disimpan sebagai template, walau katalog/pratinjau/jalankan semuanya bekerja.'
        );
    }

    /**
     * `PaExportStudioPage.test.js` (`menyebut label dataset yang benar (bukan
     * karangan) di lencana hero`) hanya membandingkan teks yang dirender
     * dengan konstanta `LABEL_DATASET` di `PaExportStudioPage.vue` itu sendiri
     * -- nol hubungan ke `PaHasilDataset::label()` / `PaPenugasanDataset::label()`
     * yang katanya dicerminkan. Mengubah label() di PHP membuat lencana basi
     * TANPA satu pun uji merah.
     *
     * Uji ini menutup celah itu dari sisi backend: baca literal `LABEL_DATASET`
     * langsung dari berkas .vue (lewat regex, bukan menjalankan Vue) dan
     * bandingkan dengan label() milik dataset yang sama di DatasetRegistry.
     * Dipilih ketimbang menyambungkan label ke respons katalog (opsi yang
     * lebih "benar" tapi menuntut ReportStudio.vue -- komponen bersama modul
     * lain -- mengeskpos `katalog` dan halaman ini membaca label secara
     * reaktif dari state async; risikonya flicker/regresi lintas modul jauh
     * di luar cakupan perbaikan ini) karena tujuannya sama: label yang
     * dirender tidak boleh menyimpang dari sumber kanonis tanpa ketahuan.
     */
    public function test_label_dataset_di_vue_sinkron_dengan_label_dataset_class(): void
    {
        $path = resource_path('js/Pages/KPI/PerformanceAppraisal/Export/PaExportStudioPage.vue');
        $isi = file_get_contents($path);
        $this->assertNotFalse($isi, "Tidak bisa membaca {$path}.");

        $ada = preg_match('/const LABEL_DATASET = \{(.*?)\};/s', $isi, $blok);
        $this->assertSame(1, $ada, 'Konstanta LABEL_DATASET tidak ditemukan di PaExportStudioPage.vue.');

        preg_match_all("/(\\w+):\\s*'([^']*)'/", $blok[1], $entri, PREG_SET_ORDER);
        $this->assertNotEmpty($entri, 'LABEL_DATASET ditemukan tapi tidak berisi satu pun entri kunci/label.');

        $petaVue = [];
        foreach ($entri as $e) {
            $petaVue[$e[1]] = $e[2];
        }

        $registry = app(DatasetRegistry::class);
        $kunciRegistry = array_keys($registry->semua('pa'));

        $kunciVue = array_keys($petaVue);
        sort($kunciVue);
        $kunciRegistrySorted = $kunciRegistry;
        sort($kunciRegistrySorted);
        $this->assertSame(
            $kunciRegistrySorted,
            $kunciVue,
            'LABEL_DATASET di PaExportStudioPage.vue tidak mencakup persis dataset yang terdaftar di DatasetRegistry untuk modul pa.'
        );

        foreach ($kunciRegistry as $kunci) {
            $labelBackend = $registry->ambil('pa', $kunci)->label();
            $this->assertSame(
                $labelBackend,
                $petaVue[$kunci] ?? null,
                "LABEL_DATASET['{$kunci}'] di PaExportStudioPage.vue (\"{$petaVue[$kunci]}\") menyimpang dari "
                ."label() dataset '{$kunci}' di DatasetRegistry (\"{$labelBackend}\")."
            );
        }
    }
}
