<?php

namespace Tests\Feature\SYSTEM\Integration;

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureSystemAdmin;
use App\Services\SYSTEM\Integration\OutboundContractService;
use Tests\TestCase;

/**
 * Outbound Integration Contracts / Feature Requests.
 *
 * Fitur ini punya DUA permukaan dengan aturan berlawanan, dan hampir seluruh
 * berkas uji ini ada untuk menjaga garis di antaranya:
 *
 * - Portal manajemen INTERNAL — halaman Inertia di dalam shell, gerbang
 *   `system.admin`, di /system/integrasi/kontrak-outbound.
 * - Viewer kontrak PUBLIK — dokumen Blade mandiri di /api/contracts/{slug},
 *   sengaja bebas auth dan bebas Api_Public karena tautannya memang dibagikan
 *   ke developer vendor.
 *
 * Dua kegagalan sudah pernah terjadi di sini dan keduanya punya penjaganya:
 *
 * 1. Versi pertama menaruh portal manajemen di routes/api.php tanpa penjaga.
 *    Grup middleware 'api' tidak memuat autentikasi sama sekali, sehingga ia
 *    menjawab 200 kepada siapa saja di internet — diam-diam, bukan lewat galat.
 * 2. Setelah dijaga, halaman itu masih berupa Blade. Halaman non-Inertia di
 *    balik `auth` DI DALAM aplikasi Inertia tampil di dalam modal galat:
 *    klien Inertia mengikuti redirect()->intended() sebagai XHR ber-header
 *    X-Inertia lalu menerima HTML polos. Karena itu ada uji yang menuntut
 *    respons Inertia, bukan sekadar 200.
 */
class OutboundContractsTest extends TestCase
{
    private const PORTAL = '/system/integrasi/kontrak-outbound';

    /** Melewati dua gerbang sekaligus; isi halamannya yang sedang diuji, bukan gerbangnya. */
    private function sebagaiAdmin(): self
    {
        return $this->withoutMiddleware([Authenticate::class, EnsureSystemAdmin::class]);
    }

    // ────────────────────────────────────────────────────────────────
    // Portal manajemen: internal
    // ────────────────────────────────────────────────────────────────

    /** @test */
    public function tamu_ditolak_dari_portal_manajemen(): void
    {
        $this->get(self::PORTAL)->assertRedirect(route('login'));
    }

    /** @test */
    public function tamu_yang_meminta_json_ditolak_401(): void
    {
        // Jalur JSON tidak dialihkan ke halaman login, jadi ia perlu
        // pernyataannya sendiri: pengalihan yang hilang di sini akan terbaca
        // sebagai "tidak 200" oleh uji di atas, padahal 200-nya yang berbahaya.
        $this->getJson(self::PORTAL)->assertStatus(401);
        $this->getJson(self::PORTAL.'?format=json')->assertStatus(401);
    }

    /** @test */
    public function pengguna_biasa_ditolak_403(): void
    {
        // Lolos `auth` belum berarti boleh: gerbang keduanya adalah flag
        // KPI_Users.Is_System_Admin. Grup fitur SYSTEM tidak dipegang role mana
        // pun, jadi check.jabatan akan jadi gerbang yang tak seorang pun lewati.
        $this->withoutMiddleware(Authenticate::class)->get(self::PORTAL)->assertStatus(403);
    }

    /** @test */
    public function portal_manajemen_adalah_halaman_inertia_bukan_blade(): void
    {
        // Penjaga bentuk, bukan penjaga isi. Halaman Blade juga akan menjawab
        // 200 di sini — dan justru 200-lah yang dulu berakhir di dalam modal
        // galat Inertia setelah login. Yang membedakannya adalah header
        // X-Inertia pada respons terhadap permintaan Inertia.
        $respons = $this->sebagaiAdmin()->get(self::PORTAL, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        ]);

        $respons->assertStatus(200);
        $respons->assertHeader('X-Inertia', 'true');
        $this->assertSame(
            'SYSTEM/Integration/OutboundContracts/OutboundContractsPage',
            $respons->json('component'),
        );
    }

    /** @test */
    public function portal_manajemen_dapat_mengembalikan_json(): void
    {
        $respons = $this->sebagaiAdmin()->getJson(self::PORTAL);

        $respons->assertStatus(200);
        $respons->assertJsonPath('success', true);

        /* Dicocokkan dengan JUMLAH YANG DIDEFINISIKAN, bukan dengan angka tetap.
           Versi sebelumnya mengunci angka 3, sehingga setiap kontrak baru mematahkan uji ini
           tanpa satu pun cacat — dan uji yang merah karena pekerjaan yang benar mengajari
           orang mengabaikannya. Yang sesungguhnya dijaga: portal mengembalikan SELURUH
           kontrak, bukan sebagian. */
        $respons->assertJsonCount(
            count(app(OutboundContractService::class)->all()),
            'data',
        );

        /* Dan setidaknya satu slug yang dikenal benar-benar ada — pencacahan saja akan tetap
           hijau bila isinya berubah menjadi hal lain yang kebetulan sama banyaknya. */
        $respons->assertJsonFragment(['slug' => 'hc-learn-enrollment-sync']);
    }

    /** @test */
    public function alamat_lama_dialihkan_bukan_dimatikan(): void
    {
        // /api/contracts dipakai sebelum halaman ini pindah bentuk. Mematikannya
        // begitu saja membuat penanda dan tautan lama berakhir di 404 tanpa
        // petunjuk ke mana halamannya pergi.
        $this->get('/api/contracts')->assertRedirect(self::PORTAL);

        // Alias ikut, dengan alasan yang sama seperti ia dulu ikut dijaga:
        // dua pintu ke satu halaman diperlakukan sama, termasuk saat pindah.
        $this->get('/api/docs/requests')->assertRedirect(self::PORTAL);
    }

    // ────────────────────────────────────────────────────────────────
    // Viewer kontrak: publik, dan memang harus tetap begitu
    // ────────────────────────────────────────────────────────────────

    /** Tautan viewer yang sah — dibuat cara yang sama seperti portal membuatnya. */
    private function tautanViewer(string $slug = 'web-careers-candidate-status'): string
    {
        return app(\App\Services\SYSTEM\Integration\OutboundContractService::class)->tautanViewer($slug);
    }

    private function tautanJson(string $slug = 'web-careers-candidate-status'): string
    {
        return app(\App\Services\SYSTEM\Integration\OutboundContractService::class)->tautanJson($slug);
    }

    /** @test */
    public function tautan_tanpa_tanda_tangan_ditolak(): void
    {
        // Inti gerbang ini: sebelumnya siapa pun yang menebak slug membaca peta
        // integrasi outbound HCIS. Slug yang benar saja kini tidak cukup.
        $this->get('/api/contracts/web-careers-candidate-status')->assertStatus(403);
        $this->get('/api/contracts/web-careers-candidate-status/json')->assertStatus(403);
    }

    /** @test */
    public function tanda_tangan_yang_diubah_ditolak(): void
    {
        // Tanda tangan terikat pada URL utuh. Memakai tanda tangan milik satu
        // kontrak untuk kontrak lain adalah percobaan paling wajar, dan harus
        // gagal — kalau lolos, gerbangnya hanya hiasan.
        $sah = $this->tautanViewer();

        $pindahKontrak = str_replace('web-careers-candidate-status', 'assessment-vendor-invite', $sah);
        $this->get($pindahKontrak)->assertStatus(403);

        $rusak = preg_replace('/signature=[a-f0-9]+/', 'signature='.str_repeat('a', 64), $sah);
        $this->get($rusak)->assertStatus(403);
    }

    /** @test */
    public function tautan_tidak_sah_menjelaskan_dirinya_kepada_vendor(): void
    {
        // Pembacanya di luar organisasi; halaman 403 umum tidak memberi tahu
        // apa pun yang bisa mereka lakukan.
        // Dicocokkan pada bagian kalimat yang sama-sama dipakai halaman HTML dan
        // balasan JSON, supaya penjaga ini tidak patah hanya karena susunan
        // kalimatnya dirapikan.
        $this->get('/api/contracts/web-careers-candidate-status')
            ->assertStatus(403)
            ->assertSee('tautan baru kepada tim HCIS', false);

        $this->getJson('/api/contracts/web-careers-candidate-status')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function viewer_spesifikasi_kontrak_tetap_bebas_auth(): void
    {
        // Bertanda tangan, tetapi tetap TANPA login dan tanpa Api_Public —
        // itu memang gunanya.
        $response = $this->get($this->tautanViewer());

        $response->assertStatus(200);
        $response->assertSee('Web Careers — Integrasi Sinkronisasi Rekrutmen Pelamar');
        $response->assertSee('/api/webhooks/candidate-status');
        $response->assertSee('candidate.status_updated');
        $response->assertSee('X-HC-Signature');
        $response->assertSee('X-HC-Timestamp');
    }

    /** @test */
    public function endpoint_json_kontrak_tetap_bebas_auth(): void
    {
        $response = $this->getJson($this->tautanJson('assessment-vendor-invite'));

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.slug', 'assessment-vendor-invite');
        $response->assertJsonPath('data.endpoints.0.path', '/api/v1/assessments/invitations');
        $response->assertJsonPath('data.endpoints.0.method', 'POST');
    }

    /** @test */
    public function satu_kontrak_boleh_memuat_banyak_endpoint(): void
    {
        // Satu kontrak = satu permintaan integrasi ke satu sistem, dan boleh
        // memuat berapa pun endpoint. Web Careers memuat dua; kalau angka ini
        // pernah terbaca 1, ada lapisan yang menyempitkannya di tengah jalan.
        $data = $this->getJson($this->tautanJson())
            ->assertStatus(200)
            ->json('data');

        $this->assertCount(2, $data['endpoints']);
        $this->assertEqualsCanonicalizing(
            ['/api/webhooks/candidate-status', '/api/webhooks/interview-schedule'],
            array_column($data['endpoints'], 'path'),
        );
    }

    /** @test */
    public function seluruh_endpoint_tampil_di_viewer_bukan_hanya_yang_pertama(): void
    {
        // Yang dibaca vendor adalah halaman ini. Endpoint yang tak tercetak di
        // sini tidak akan pernah mereka kerjakan.
        $html = $this->get($this->tautanViewer())->getContent();

        $this->assertStringContainsString('/api/webhooks/candidate-status', $html);
        $this->assertStringContainsString('/api/webhooks/interview-schedule', $html);
    }

    /** @test */
    public function kontrak_tidak_boleh_punya_ringkasan_endpoint_di_tingkat_atas(): void
    {
        // Field-field ini dulu ada di tingkat kontrak dan nilainya selalu
        // menyalin endpoint PERTAMA. Untuk kontrak berisi dua endpoint, itu
        // berarti dokumen yang dikirim ke klien punya dua sumber kebenaran dan
        // yang satu diam-diam hanya menggambarkan separuhnya — undangan untuk
        // mengimplementasikan setengah kontrak lalu mengira sudah selesai.
        //
        // Yang tinggal di tingkat kontrak hanyalah yang memang berlaku untuk
        // seluruh endpoint, mis. `security`.
        $terlarang = ['method', 'endpoint_path', 'trigger_event', 'headers', 'sample_payload', 'expected_response', 'retry_policy'];

        foreach ($this->daftarKontrak() as $kontrak) {
            foreach ($terlarang as $kunci) {
                $this->assertArrayNotHasKey(
                    $kunci,
                    $kontrak,
                    "Kontrak '{$kontrak['slug']}' memuat '{$kunci}' di tingkat atas. Itu milik endpoint; letakkan di dalam endpoints[].",
                );
            }

            $this->assertNotEmpty($kontrak['endpoints'] ?? [], "Kontrak '{$kontrak['slug']}' wajib punya minimal satu endpoint.");
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function daftarKontrak(): array
    {
        return app(\App\Services\SYSTEM\Integration\OutboundContractService::class)->all();
    }

    // ────────────────────────────────────────────────────────────────
    // Arah masuk: callback milik HCIS yang dipanggil pihak ketiga
    // ────────────────────────────────────────────────────────────────

    /** @test */
    public function callback_tidak_ikut_dihitung_sebagai_pekerjaan_pihak_ketiga(): void
    {
        // Lencana di kepala halaman menjawab satu pertanyaan yang paling dulu
        // ditanyakan pembacanya: "berapa yang harus saya bangun?" Callback bukan
        // jawabannya — ia sudah berdiri di sisi kami dan hanya perlu dipanggil.
        // Menjumlahkan keduanya melebih-lebihkan permintaan kami, dan angka yang
        // melebih-lebihkan adalah alasan yang bagus untuk menunda.
        $kontrak = app(OutboundContractService::class)->findBySlug('hc-learn-performance-appraisal');
        $html = $this->get($this->tautanViewer('hc-learn-performance-appraisal'))->getContent();

        $this->assertNotEmpty($kontrak['callbacks'] ?? [], 'Kontrak PA kehilangan daftar callback-nya.');

        $this->assertStringContainsString(
            count($kontrak['endpoints']).' Endpoint Harapan Wajib Disediakan',
            $html,
            'Hitungan endpoint di kepala halaman harus tetap menghitung endpoints[] saja.',
        );
        $this->assertStringContainsString(
            count($kontrak['callbacks']).' Callback ke HCIS',
            $html,
            'Callback harus disebut terpisah, bukan dilebur ke hitungan endpoint.',
        );
    }

    /** @test */
    public function setiap_callback_tampil_utuh_beserta_bentuk_kirimannya(): void
    {
        // Bentuk kiriman balik inilah yang menentukan nilai bisa dihitung atau
        // tidak: satu butir yang salah nama membuat penilaian tersimpan utuh
        // tetapi tidak pernah menjadi angka, tanpa satu pun galat terlihat.
        // Karena itu ia harus ADA DI HALAMAN, bukan ditunjuk ke dokumen lain.
        $kontrak = app(OutboundContractService::class)->findBySlug('hc-learn-performance-appraisal');
        $html = $this->get($this->tautanViewer('hc-learn-performance-appraisal'))->getContent();

        foreach ($kontrak['callbacks'] as $cb) {
            $this->assertStringContainsString('id="'.$cb['id'].'"', $html, "Callback {$cb['id']} tidak dirender.");
            $this->assertStringContainsString(e($cb['path']), $html);

            foreach ($cb['payload_fields'] as $field) {
                $this->assertStringContainsString(
                    e($field['field']),
                    $html,
                    "Field '{$field['field']}' pada {$cb['id']} tidak sampai ke halaman.",
                );
            }

            foreach ($cb['headers'] as $header) {
                $this->assertStringContainsString(e($header['name']), $html);
            }
        }

        // Label arah wajib ikut terbalik. Tanpa ini, bagian callback berbunyi
        // "data yang dikirimkan HCIS" untuk kiriman yang justru datang dari
        // pihak ketiga — salah arah, dan terbaca meyakinkan.
        $this->assertStringContainsString('A. Data yang Anda Kirimkan ke HCIS', $html);
        $this->assertStringContainsString('B. Respon yang HCIS Kembalikan kepada Anda', $html);

        // Sekaligus membuktikan label arah keluar TIDAK ikut terbalik.
        $this->assertStringContainsString('A. Data yang Dikirimkan oleh HCIS', $html);
    }

    /** @test */
    public function penjadwalan_pa_memakai_nama_field_yang_sungguh_dipakai_hari_ini(): void
    {
        // Kontrak ini menjual dirinya sebagai "salinan training/store dengan satu
        // perubahan". Selama janji itu benar, HC Learn bisa membangunnya dengan
        // menyalin. Begitu satu nama field menyimpang, janjinya berubah jadi jebakan:
        // mereka menyalin kode yang sudah ada, lalu kiriman kita ditolak karena
        // menyebut field yang tak pernah mereka kenal.
        //
        // Versi pertama kontrak ini benar-benar menyimpang — `Started_At`/`Finished_At`
        // dan `Id_Penugasan[]` yang tidak ada di mana pun, plus `Flag_Camera` dan
        // `User_Agent` yang justru WAJIB (tanpa keduanya training/store menjawab 500)
        // tetapi tak disebut sama sekali. Penjaga ini lahir dari kejadian itu.
        $referensi = '';

        foreach ([
            'app/Services/LMS/SOP/SopKuisDispatchService.php',           // training/store + training/update
            'app/Jobs/LMS/Training/Scheduling/ProcessBulkPenjadwalan.php', // training/store massal
            'app/Services/SYSTEM/TaskProcessing/LmsReassignThirdPartyHandler.php', // training/reassign
        ] as $jalur) {
            $referensi .= file_get_contents(base_path($jalur));
        }

        // Satu-satunya field yang memang BELUM ada di sisi mana pun. Daftar ini sengaja
        // pendek dan eksplisit: menambahkannya harus terasa seperti keputusan, bukan
        // seperti menghilangkan lampu merah.
        $memangBaru = ['Label_Subjek'];

        $kontrak = app(OutboundContractService::class)->findBySlug('hc-learn-performance-appraisal');

        /* Ketiganya menjual diri sebagai salinan endpoint yang sudah ada — penjadwalan dari
           training/store, perpanjangan dari training/update, penerbitan ulang dari
           training/reassign. Selama janji itu benar, HC Learn membangunnya dengan menyalin. */
        foreach (['ep-pa-penjadwalan', 'ep-pa-perpanjang', 'ep-pa-terbitkan-ulang'] as $id) {
            $ep = collect($kontrak['endpoints'])->firstWhere('id', $id);
            $this->assertNotNull($ep, "Endpoint {$id} hilang dari kontrak.");

            $diperiksa = array_merge(
                array_column($ep['payload_fields'], 'field'),
                array_column($ep['expected_response']['success']['fields'], 'field'),
            );

            foreach ($diperiksa as $field) {
                $bagian = explode('.', str_replace('[]', '', $field));
                $nama = end($bagian);

                // Parameter di alamat ditulis {Begini}; ia bukan field body.
                if (str_starts_with($nama, '{') || in_array($nama, $memangBaru, true)) {
                    continue;
                }

                $this->assertStringContainsString(
                    "'".$nama."'",
                    $referensi,
                    "Kontrak menyebut field '{$nama}' pada {$id}, tetapi nama itu tidak dipakai "
                    .'satu pun jalur HC Learn yang berjalan. Pakai nama yang sudah ada, '
                    ."atau daftarkan '{$nama}' sebagai field yang memang baru.",
                );
            }
        }
    }

    /** @test */
    public function tanda_tangan_di_kontrak_sama_dengan_yang_benar_benar_dikirim(): void
    {
        // Salah menulis algoritmanya bukan salah ketik yang bisa dimaafkan: vendor yang
        // menuruti tulisan kita akan gagal di SETIAP permintaan, dan pesannya cuma
        // "Invalid signature" — tanpa petunjuk bahwa yang salah adalah dokumen kita.
        // Kontrak PA sempat menulis HMAC-SHA256 atas raw body; SignatureHelper memakai
        // HMAC-SHA512 atas string kanonik tujuh bagian.
        $helper = file_get_contents(base_path('app/Helpers/SignatureHelper.php'));
        $this->assertStringContainsString("hash_hmac('sha512'", $helper, 'Referensinya berubah — perbarui kontraknya.');

        // Sengaja HANYA kontrak PA. `hc-learn-enrollment-sync` juga bertujuan ke HC Learn
        // dan juga menulis HMAC-SHA256, tetapi ia menggambarkan endpoint yang BELUM ADA
        // (/api/v1/lms/sync/enrollments — bukan salah satu jalur yang kita panggil hari
        // ini), berstatus ACTIVE, dan boleh jadi sudah dikirim ke seberang. Menyeretnya
        // ke dalam penjaga ini berarti mengubah isi kontrak orang lain diam-diam demi
        // menghijaukan uji — persis kebalikan dari gunanya penjaga. Selisihnya dilaporkan
        // ke pemiliknya, bukan ditutup di sini.
        $pa = app(OutboundContractService::class)->findBySlug('hc-learn-performance-appraisal');

        $this->assertSame(
            'HMAC-SHA512',
            $pa['security']['algorithm'] ?? null,
            'Kontrak PA menyebut algoritma yang bukan dipakai SignatureHelper.',
        );

        // Toleransinya pun harus jujur: LmsApiGuard menolak selisih di atas 60 detik,
        // dan angka 300 membuat vendor mengira punya lima menit.
        $this->assertSame(60, $pa['security']['tolerance_seconds'] ?? null);
    }

    /** @test */
    public function rincian_endpoint_lahir_tertutup_tetapi_tidak_terkunci(): void
    {
        // Dokumen ini panjang; terbentang seluruhnya sejak muat membuat pembacanya
        // menggulir jauh sebelum tahu ada apa saja. Tertutupnya ditulis di markup,
        // bukan dikatupkan skrip sesudah tampil — versi kedua membuat halaman
        // terbentang sekejap lalu melipat dirinya di depan mata pembaca.
        $html = $this->get($this->tautanViewer('hc-learn-performance-appraisal'))->getContent();

        $terbuka = substr_count($html, 'class="accordion-content p-6');
        $this->assertSame(0, $terbuka, 'Masih ada rincian yang lahir terbentang.');

        $tertutup = substr_count($html, 'class="accordion-content hidden p-6');
        $this->assertGreaterThan(0, $tertutup, 'Rincian endpoint tidak dirender sama sekali.');

        // Tertutup hanya benar selama skripnya jalan. Tanpa penjaga ini, satu skrip
        // yang gagal dimuat di jaringan vendor mengubah dokumen setebal ini menjadi
        // deretan judul tanpa isi — tanpa galat, dan tanpa cara membukanya.
        $this->assertMatchesRegularExpression(
            '/<noscript>.*\.accordion-content\s*\{\s*display:\s*block\s*!important;\s*\}.*<\/noscript>/s',
            $html,
            'Halaman tanpa JavaScript harus membentangkan kembali seluruh rinciannya.',
        );

        // Dan harus tetap ada jalan membuka semuanya sekaligus.
        $this->assertStringContainsString('toggleAllAccordions(true)', $html);
    }

    /** @test */
    public function endpoint_terurut_menurut_pemakaiannya_di_alur(): void
    {
        // Daftar endpoint dibaca dari atas ke bawah oleh orang yang belum tahu apa-apa,
        // jadi urutannya adalah penjelasan pertama yang ia terima. Endpoint baru paling
        // mudah ditambahkan di ujung daftar — dan di situlah urutannya diam-diam rusak,
        // tanpa satu pun yang gagal.
        $kontrak = app(OutboundContractService::class)->findBySlug('hc-learn-performance-appraisal');
        $urutan = array_column($kontrak['endpoints'], 'id');

        $terlihat = [];

        foreach (($kontrak['flow'] ?? []) as $langkah) {
            $id = $langkah['endpoint'] ?? null;

            // Callback bernomor di ruang lain — halaman melabelinya "Callback A", bukan
            // meneruskan hitungan endpoint. Mencampurnya membuat urutan yang benar
            // terbaca melompat.
            if ($id === null || ! str_starts_with($id, 'ep-')) {
                continue;
            }

            $terlihat[] = array_search($id, $urutan, true);
        }

        $menaik = $terlihat;
        sort($menaik);

        $this->assertSame(
            $menaik,
            $terlihat,
            'Alur merujuk endpoint dengan melompat mundur: '
            .implode(' → ', array_map(static fn ($n) => '#'.($n + 1), $terlihat))
            .'. Urutkan endpoints[] mengikuti urutan pemakaiannya.',
        );
    }

    /** @test */
    public function sla_yang_tidak_ada_tidak_dikarang(): void
    {
        // Lencana SLA dulu punya cadangan 'Maks 3.0s', sehingga endpoint TANPA angka SLA
        // tetap memampangkannya. Bagi pembaca di seberang itu tidak terbaca sebagai bawaan
        // template melainkan sebagai komitmen kami — dan komitmen karangan lebih buruk
        // daripada tidak ada komitmen sama sekali.
        $pa = app(OutboundContractService::class)->findBySlug('hc-learn-performance-appraisal');

        foreach (array_merge($pa['endpoints'], $pa['callbacks'] ?? []) as $spec) {
            $this->assertArrayNotHasKey('sla_timeout', $spec, "SLA kembali muncul di {$spec['id']}.");
        }

        $htmlPa = $this->get($this->tautanViewer('hc-learn-performance-appraisal'))->getContent();
        $this->assertStringNotContainsString('SLA:', $htmlPa, 'Halaman PA masih memampangkan SLA.');

        // DUA ARAH. Tanpa bagian ini, menghapus lencananya dari template akan membuat uji
        // di atas tetap hijau — dan SLA milik kontrak lain hilang tanpa seorang pun tahu.
        $lain = app(OutboundContractService::class)->findBySlug('web-careers-candidate-status');
        $htmlLain = $this->get($this->tautanViewer('web-careers-candidate-status'))->getContent();

        $this->assertSame(
            count($lain['endpoints']),
            substr_count($htmlLain, 'SLA:'),
            'SLA kontrak lain ikut hilang; yang diminta hanya kontrak PA.',
        );
    }

    /** @test */
    public function rujukan_endpoint_di_alur_selalu_menemukan_sasarannya(): void
    {
        // Langkah alur menunjuk endpoint lewat id. Id yang salah ketik tidak
        // memunculkan galat apa pun: tombolnya sekadar hilang, dan pembaca
        // berhenti di langkah itu tanpa tahu ada rinciannya di bawah.
        foreach ($this->daftarKontrak() as $kontrak) {
            $dikenal = array_merge(
                array_column($kontrak['endpoints'], 'id'),
                array_column($kontrak['callbacks'] ?? [], 'id'),
            );

            foreach (($kontrak['flow'] ?? []) as $i => $langkah) {
                if (($langkah['endpoint'] ?? null) === null) {
                    continue;
                }

                $this->assertContains(
                    $langkah['endpoint'],
                    $dikenal,
                    "Kontrak '{$kontrak['slug']}' langkah ke-".($i + 1)
                    ." menunjuk '{$langkah['endpoint']}' yang tidak ada di endpoints[] maupun callbacks[].",
                );
            }
        }
    }

    /** @test */
    public function kontrak_tidak_menyatakan_status_kesiapan_sisi_kami(): void
    {
        // Keputusan sadar: halaman ini menggambarkan BENTUK integrasi, bukan sejauh mana
        // pekerjaan kami sudah jalan. Sempat ada lencana BELUM AKTIF / SIAP MENERIMA di
        // tabel callback, dan ia punya dua cacat sekaligus.
        //
        // Pertama, umurnya pendek: begitu rutenya terpasang, lencana itu bukan lagi kabar
        // melainkan sisa. Kedua — dan ini yang menentukan — tidak ada apa pun yang gagal
        // bila seseorang lupa memperbaruinya, sehingga yang tertinggal justru pernyataan
        // yang salah dan terlihat resmi. Dokumen yang berbohong tentang dirinya sendiri
        // lebih buruk daripada dokumen yang diam soal itu.
        //
        // Urutan pemasangan tetap penting dan tetap ditulis — tempatnya di catatan rilis
        // (docs/changes/DML-2026-102), tempat orang membacanya tepat sebelum bertindak,
        // bukan menempel di tiap baris tabel selamanya.
        //
        // Yang TIDAK dilarang: menyebut apa yang sudah ada di sisi PIHAK KETIGA
        // ("SUDAH ADA DAN SUDAH KAMI PAKAI", "SALINAN training/store YANG SUDAH ADA").
        // Itu bukan status kami — itu keterangan yang justru mengurangi pekerjaan mereka.
        $terlarang = ['BELUM AKTIF', 'SIAP MENERIMA'];

        foreach ($this->daftarKontrak() as $kontrak) {
            foreach (array_merge($kontrak['endpoints'], $kontrak['callbacks'] ?? []) as $spec) {
                foreach ($terlarang as $frasa) {
                    $this->assertStringNotContainsString(
                        $frasa,
                        $spec['purpose'] ?? '',
                        "Kontrak '{$kontrak['slug']}' endpoint '{$spec['id']}' menyatakan status "
                        ."kesiapan kami ('{$frasa}'). Halaman ini menggambarkan bentuk integrasi; "
                        .'urutan pemasangan ditulis di catatan rilis.',
                    );
                }
            }
        }

        // Sampai ke halamannya juga — lencananya dulu dirender template, bukan data.
        foreach (['hc-learn-performance-appraisal'] as $slug) {
            $html = $this->get($this->tautanViewer($slug))->getContent();

            foreach ($terlarang as $frasa) {
                $this->assertStringNotContainsString($frasa, $html);
            }
        }
    }

    /** @test */
    public function setiap_kelas_yang_dipakai_halaman_benar_benar_bergaya(): void
    {
        // Tailwind halaman ini DIBANGUN SEKALI lalu di-commit — tidak ada kompiler yang
        // berjalan di peramban pembaca. Kelas baru yang lupa dibangun ulang tidak
        // menghasilkan galat apa pun: bagian halaman itu sekadar kehilangan gayanya, dan
        // yang melihatnya adalah developer vendor, bukan kita.
        //
        // Penjaga ini lahir dari kesalahan yang nyaris terkirim: pemeriksaan manual saya
        // membaca berkas CSS DI POHON KERJA yang kebetulan sudah dibangun ulang tetapi
        // belum di-commit, lalu melaporkan "nol kelas tanpa gaya". Terhadap berkas yang
        // benar-benar ter-commit, 28 kelas tak bergaya. Uji ini membaca berkas di disk
        // apa adanya, jadi di CI ia membaca yang ter-commit.
        $css = file_get_contents(public_path('vendor/tailwind/outbound-contracts.css'));

        foreach ($this->daftarKontrak() as $kontrak) {
            $html = $this->get($this->tautanViewer($kontrak['slug']))->getContent();

            preg_match_all('/class="([^"]+)"/', $html, $cocok);
            $kelas = array_unique(array_merge(
                ...array_map(static fn ($c) => preg_split('/\s+/', trim($c)), $cocok[1]),
            ));

            $hilang = [];

            foreach ($kelas as $satu) {
                // `group`/`peer` memang tak punya aturan sendiri — keduanya penanda
                // jangkar bagi variannya, dan halaman ini tidak memakai satu pun varian.
                if ($satu === '' || str_contains($satu, '{') || in_array($satu, ['group', 'peer'], true)) {
                    continue;
                }

                $selektor = '.'.str_replace(
                    [':', '[', ']', '/', '.'],
                    ['\\:', '\\[', '\\]', '\\/', '\\.'],
                    $satu,
                );

                // Halaman punya DUA sumber gaya: berkas Tailwind, dan blok <style>-nya
                // sendiri (flow-step-card, custom-scrollbar, endpoint-accordion).
                if (! str_contains($css, $selektor) && ! str_contains($html, '.'.$satu)) {
                    $hilang[] = $satu;
                }
            }

            $this->assertSame(
                [],
                $hilang,
                "Halaman '{$kontrak['slug']}' memakai kelas tanpa gaya: ".implode(', ', $hilang)
                .'. Bangun ulang berkasnya — lihat perintahnya di tailwind.outbound-contracts.config.js '
                .'— lalu COMMIT hasilnya.',
            );
        }
    }

    /** @test */
    public function viewer_kontrak_tidak_menarik_aset_dari_luar(): void
    {
        // Halaman ini dibuka developer vendor dari jaringan kantor mereka.
        // Satu CDN yang terblokir di sana = HTML telanjang tanpa gaya, dan
        // kita tidak akan pernah melihat gejalanya dari sini.
        //
        // cdn.tailwindcss.com bukan berkas CSS melainkan kompiler yang berjalan
        // di peramban pengunjung; dokumentasi Tailwind sendiri menyatakan ia
        // tidak untuk production.
        $html = $this->get($this->tautanViewer())->getContent();

        foreach (['cdn.tailwindcss.com', 'fonts.bunny.net', 'fonts.googleapis.com', 'fonts.gstatic.com'] as $host) {
            $this->assertStringNotContainsString(
                $host,
                $html,
                "Halaman ini tidak boleh menarik aset dari {$host}: ia dibuka dari jaringan yang tak kita kendalikan.",
            );
        }
    }

    // ────────────────────────────────────────────────────────────────
    // Bentuk masukan
    // ────────────────────────────────────────────────────────────────

    /** @test */
    public function kontrak_tidak_dikenal_menghasilkan_404(): void
    {
        $this->get($this->tautanViewer('kontrak-palsu-tidak-ada'))->assertStatus(404);
    }

    /** @test */
    public function slug_di_luar_bentuk_ditolak_di_lapis_routing(): void
    {
        // Ditolak sebelum menyentuh controller, sehingga tak ada teks bebas
        // dari luar yang ikut terpantul ke pesan galat.
        $this->get('/api/contracts/KONTRAK_PALSU')->assertStatus(404);
        $this->get('/api/contracts/-diawali-tanda-hubung')->assertStatus(404);
        $this->get('/api/contracts/ada spasi')->assertStatus(404);
    }
}
