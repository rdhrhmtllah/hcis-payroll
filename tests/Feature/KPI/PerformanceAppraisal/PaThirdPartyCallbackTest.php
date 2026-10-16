<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Http\Controllers\KPI\PerformanceAppraisal\Integration\PaThirdPartyCallbackController;
use App\Services\KPI\PerformanceAppraisal\PaAssignmentBuilderService;
use App\Services\KPI\PerformanceAppraisal\PaPayloadMapperService;
use App\Services\KPI\PerformanceAppraisal\PaStatusPenugasan;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

/**
 * Pintu callback Performance Appraisal — terpisah, dan harus TETAP terpisah.
 *
 * Berkas ini menjaga satu keputusan: hasil penilaian 360 masuk lewat alamatnya sendiri,
 * bukan menumpang callback Training/SOP. Yang dijaga bukan cuma "rutenya ada", melainkan
 * bahwa pintu lamanya benar-benar melepasnya — karena dua jalan ke satu tempat adalah
 * persis keadaan yang sedang dibubarkan, dan ia bisa kembali tanpa satu pun uji memerah.
 */
class PaThirdPartyCallbackTest extends TestCase
{
    private const RUTE = 'api/v1/pa/third-party/penilaian/complete';

    /** @test */
    public function rute_callback_pa_terdaftar_dan_dijaga_gerbangnya_sendiri(): void
    {
        $rute = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === self::RUTE);

        $this->assertNotNull($rute, 'Rute callback PA tidak terdaftar.');
        $this->assertContains('POST', $rute->methods());

        // Rute ini duduk di routes/api.php, yang TIDAK memuat autentikasi sama sekali.
        // Seluruh penjagaannya ada di middleware — tanpa itu, penulisan hasil penilaian
        // terbuka ke internet. Bukan "sebuah pemeriksaan hilang", melainkan semuanya.
        $this->assertContains(
            'pa.api.guard',
            $rute->gatherMiddleware(),
            'Rute callback PA berjalan tanpa gerbangnya.',
        );
    }

    /** @test */
    public function callback_pa_menolak_permintaan_tanpa_header_keamanan(): void
    {
        // Ditolak SEBELUM menyentuh database sama sekali — yang juga berarti alamat ini
        // tidak bisa dipakai memancing keberadaan token dengan menebak-nebak.
        $respons = $this->postJson('/'.self::RUTE, [
            'Kode_Karyawan' => 'DEMO-001',
            'Id_Integrasi' => 1,
            'Id_Ujian_Token' => 'token-karangan',
            'Detail' => [['Kode_Soal' => 'Q-01', 'Skor' => 4, 'Skor_Maksimal' => 5]],
        ]);

        $respons->assertStatus(401);
        $respons->assertJsonPath('message', 'Security headers incomplete');
    }

    /** @test */
    public function pintu_lms_tidak_lagi_mengenal_apa_pun_tentang_pa(): void
    {
        // Penjaga struktural, dan itu disengaja. Menguji lewat permintaan sungguhan
        // menuntut token PA yang hidup di pintu LMS — persis keadaan yang sedang
        // dihapus, sehingga ujinya hanya bisa hijau bila cacatnya kembali.
        //
        // Yang dijaga: PA sempat menumpang, dan menumpangnya memaksa LmsApiGuard menembak
        // satu tabel lagi hanya untuk MENEBAK asal kiriman, plus satu cabang untuk token
        // yang cocok di lebih dari satu modul. Menambahkannya kembali "sementara" adalah
        // perbaikan yang paling wajar dilakukan orang saat callback PA gagal — dan
        // sesudahnya tidak ada yang terlihat rusak.
        $berkas = [
            'app/Http/Middleware/LmsApiGuard.php',
            'app/Http/Controllers/LMS/Integration/LmsThirdPartyCallbackController.php',
        ];

        foreach ($berkas as $jalur) {
            $isi = file_get_contents(base_path($jalur));

            foreach (['N_PA_Assignment_Integration', 'pa_integrasi', 'PaPayloadMapperService'] as $jejak) {
                $this->assertStringNotContainsString(
                    $jejak,
                    $isi,
                    "{$jalur} masih menyebut '{$jejak}'. Callback PA punya pintunya sendiri; "
                    .'jalur bersama tidak boleh mengenalnya lagi.',
                );
            }
        }
    }

    /** @test */
    public function status_skor_wajib_dan_hanya_menerima_tiga_nilai(): void
    {
        // Sengaja tanpa nilai bawaan. Field yang boleh dikosongkan akan suatu hari terlupa,
        // dan yang terjadi kemudian adalah nilai yang masih menunggu verifikasi
        // diperlakukan sebagai FINAL lalu ikut terhitung — diam-diam, karena semuanya
        // berhasil dan tidak ada yang terlihat rusak.
        $this->assertSame(
            ['FINAL', 'MENUNGGU_VERIFIKASI', 'DITOLAK'],
            PaPayloadMapperService::SKOR_DIKENAL,
        );

        // Hanya MENUNGGU_VERIFIKASI dan DITOLAK yang lewat jalur pencatatan status;
        // FINAL punya jalurnya sendiri yang memetakan dan menghitung.
        $this->expectException(RuntimeException::class);
        app(PaPayloadMapperService::class)->catatStatusSkor('token-karangan', 'FINAL');
    }

    /** @test */
    public function pengingat_melewati_penugasan_yang_menunggu_verifikasi(): void
    {
        // Inti keadaan ketiga ini. Penilai yang sudah mengirim tetapi angkanya belum
        // disahkan admin HC Learn TIDAK boleh ditagih lagi — menagih pekerjaan yang sudah
        // dilakukan membuat penerimanya berhenti membaca pengingat berikutnya.
        //
        /* DIPERIKSA LEWAT NILAINYA, BUKAN EJAAN SUMBERNYA.
           |
           | Versi pertama penjaga ini memindai teks `PaNotificationService.php` mencari
           | larik harfiah `whereNotIn('a.Status_Penugasan', [... 'MENUNGGU_VERIFIKASI' ...])`.
           | Ia putus begitu lariknya diangkat menjadi konstanta bersama
           | `PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT` — perilakunya TIDAK berubah
           | sedikit pun, tetapi penjaganya merah dan terbaca seolah pengingatnya rusak.
           |
           | Penjaga yang menguji ejaan akan selalu kalah oleh perapian kode. Yang diuji
           | sekarang isi daftarnya dan pemakaiannya — dua hal yang benar-benar menentukan
           | siapa ditagih. */
        $this->assertContains(
            PaAssignmentBuilderService::STATUS_MENUNGGU_VERIFIKASI,
            PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT,
            'Pengingat masih menagih penilai yang kirimannya menunggu verifikasi.',
        );

        // Yang selesai maupun dibatalkan tetap dikecualikan — daftar ini tidak boleh
        // menyusut diam-diam saat ada yang menambahkan status baru.
        foreach ([PaStatusPenugasan::SELESAI, PaStatusPenugasan::DIBATALKAN] as $tetap) {
            $this->assertContains($tetap, PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT);
        }

        /* Dan penyaring pengingatnya BENAR-BENAR memakai daftar itu. Tanpa penegasan ini,
           konstanta boleh berisi apa saja sementara pengingatnya menyaring dengan daftar
           lain — dan uji di atas hijau tanpa menyentuh perilaku sama sekali. */
        $sumber = file_get_contents(base_path('app/Services/KPI/PerformanceAppraisal/PaNotificationService.php'));

        $this->assertMatchesRegularExpression(
            "/whereNotIn\(\s*'a\.Status_Penugasan',\s*PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT\s*\)/s",
            $sumber,
            'Penyaring pengingat tidak lagi memakai PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT.',
        );
    }

    /** @test */
    public function menunggu_verifikasi_bukan_selesai_sehingga_tidak_ikut_dihitung(): void
    {
        // Status barunya sengaja BUKAN SELESAI: perhitungan hanya membaca penugasan
        // berstatus SELESAI, jadi nilai yang belum sah tidak bisa menyelinap ke angka
        // akhir hanya karena pengingatnya sudah berhenti.
        $this->assertNotSame(
            PaPayloadMapperService::STATUS_SELESAI,
            PaAssignmentBuilderService::STATUS_MENUNGGU_VERIFIKASI,
        );

        $kalkulator = file_get_contents(base_path('app/Services/KPI/PerformanceAppraisal/PaResultCalculationService.php'));

        $this->assertStringContainsString(
            "\$t->Status_Penugasan !== 'SELESAI'",
            $kalkulator,
            'Perhitungan tidak lagi menyaring SELESAI; status menunggu bisa ikut terhitung.',
        );
    }

    /** @test */
    public function balasan_callback_pa_rata_satu_tingkat(): void
    {
        // Jalur lama membungkus hasil sebagai ['result' => $hasil] ke dalam envelope yang
        // SUDAH punya 'result', sehingga pembacanya harus menulis
        // result.result.pemetaan.butir_hilang — sarang yang tak seorang pun memutuskan,
        // hanya terjadi. Kontrak yang dikirim ke HC Learn menjanjikan bentuk yang rata.
        $ratakan = new ReflectionMethod(PaThirdPartyCallbackController::class, 'ratakan');
        $ratakan->setAccessible(true);

        $controller = app(PaThirdPartyCallbackController::class);

        $rata = $ratakan->invoke($controller, [
            'payload_id' => 5521,
            'assignment_id' => 9001,
            'baru' => true,
            'perhitungan_diantrekan' => false,
            'pemetaan' => [
                'pemeta_versi' => 1,
                'butir' => 3,
                'berskor' => 2,
                'tak_terpetakan' => [],
                'skala_berbeda' => [],
                'butir_hilang' => ['PA-STAFF-2027-Q04'],
                'kontrak_lengkap' => false,
            ],
        ]);

        $this->assertArrayNotHasKey('pemetaan', $rata, 'Bentuknya masih bersarang.');
        $this->assertSame(3, $rata['butir']);
        $this->assertSame(['PA-STAFF-2027-Q04'], $rata['butir_hilang']);
        $this->assertFalse($rata['kontrak_lengkap']);

        // Kiriman ulang yang identik tidak dipetakan lagi, jadi field pemetaannya memang
        // tidak ada. Mengarang angka supaya bentuknya "lengkap" jauh lebih buruk daripada
        // bidang yang jujur absen: pengirimnya sudah menerima jawaban penuh pada kiriman
        // pertama, dan angka karangan akan dibaca sebagai kenyataan.
        $ulang = $ratakan->invoke($controller, [
            'payload_id' => 5521,
            'assignment_id' => 9001,
            'baru' => false,
            'perhitungan_diantrekan' => false,
            'pemetaan' => null,
        ]);

        $this->assertFalse($ulang['baru']);
        $this->assertArrayNotHasKey('butir_hilang', $ulang);
        $this->assertArrayNotHasKey('kontrak_lengkap', $ulang);
    }
}
