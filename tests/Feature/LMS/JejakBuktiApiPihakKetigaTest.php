<?php

namespace Tests\Feature\LMS;

use App\Services\SYSTEM\Integration\ApiEvidenceSanitizer;
use Tests\TestCase;

/**
 * Jejak bukti API pihak ketiga harus dicatat SESUDAH respons diketahui.
 *
 * Bug aslinya: LmsApiGuard menulis `logAttempt(..., 200, 'Y')` tepat sebelum
 * `$next($request)`. Angka 200 itu berarti "lolos gerbang keamanan", bukan
 * "HCIS membalas 200" — kegagalan di dalam controller tak pernah bisa muncul di
 * log. Terbukti di production: 1.639 baris dari HCLEARN, nol di antaranya 5xx.
 *
 * Enam penolakan paling awal (HTTPS, header tak lengkap, timestamp kedaluwarsa,
 * aplikasi tak dikenal, nonce berulang, body hash tak cocok) bahkan tidak
 * menulis baris sama sekali, sehingga "ditolak" dan "tidak pernah sampai" tak
 * bisa dibedakan dari luar.
 *
 * Penjaga ini memeriksa bentuk berkasnya, bukan efeknya, dan itu disengaja:
 * yang dijaga adalah URUTAN, dan urutan yang salah tidak melempar galat apa pun
 * — ia hanya diam-diam mencatat angka yang keliru.
 */
class JejakBuktiApiPihakKetigaTest extends TestCase
{
    private const GUARD = 'app/Http/Middleware/LmsApiGuard.php';

    private function isi(): string
    {
        return file_get_contents(base_path(self::GUARD));
    }

    /** @test */
    public function tidak_ada_lagi_status_dipaku_sebelum_permintaan_diteruskan(): void
    {
        $isi = $this->isi();

        $this->assertStringNotContainsString(
            'logAttempt(',
            $isi,
            'logAttempt() adalah pencatatan lama yang menulis status sebelum $next($request). '.
            'Statusnya tidak pernah bisa mencerminkan respons sebenarnya.',
        );

        // Pencatatan harus menerima objek Response, bukan angka literal.
        $this->assertMatchesRegularExpression(
            '/private function catat\(\s*Request \$request,\s*Response \$response,/',
            $isi,
            'catat() harus menerima Response supaya statusnya diambil dari respons nyata.',
        );

        $this->assertStringContainsString(
            '$status = $response->getStatusCode();',
            $isi,
            'Status yang dicatat harus dibaca dari respons, bukan ditulis sebagai angka tetap.',
        );
    }

    /** @test */
    public function setiap_jalur_keluar_melewati_pencatatan(): void
    {
        $isi = $this->isi();

        // handle() hanya boleh punya jalur keluar lewat catat(): penolakan,
        // sukses, dan lemparan. Tiga-tiganya.
        //
        // Potongannya diambil dengan mencocokkan badan metode sampai kurung
        // tutupnya sendiri, bukan sampai nama metode berikutnya. Jangkar "sampai
        // metode X" pernah membuat penjaga ini mengukur STRING KOSONG begitu ada
        // metode baru disisipkan di antaranya — 0 return dan 0 catat lolos
        // assertSame() tanpa satu pun jalur benar-benar diperiksa.
        preg_match('/\n    public function handle\(.*?\n    \}\n/s', $isi, $cocok);
        $handle = $cocok[0] ?? '';

        $this->assertNotSame(
            '',
            $handle,
            'Badan handle() tidak ditemukan — penjaga ini akan mengukur string kosong dan hijau palsu.',
        );

        $jumlahReturn = preg_match_all('/^\s*return /m', $handle);
        $jumlahCatat = substr_count($handle, '$this->catat(');

        $this->assertSame(
            $jumlahReturn,
            $jumlahCatat,
            "handle() punya {$jumlahReturn} jalur keluar tetapi hanya {$jumlahCatat} yang melewati catat(). ".
            'Jalur yang lolos berarti permintaan tanpa baris bukti.',
        );
        $this->assertGreaterThanOrEqual(3, $jumlahCatat, 'Penolakan, sukses, dan lemparan harus tercatat.');
    }

    /** @test */
    public function galat_diteruskan_ke_penangan_laravel_bukan_dijawab_sendiri(): void
    {
        $isi = $this->isi();

        // Menjawab exception dengan respons karangan sendiri berarti membuang
        // jenis galat yang sebenarnya (422 validasi berubah jadi 500 generik)
        // sekaligus laporannya (report() tak pernah dipanggil, jadi tak ada
        // stack trace di mana pun). Efeknya dijaga di
        // Tests\Feature\SYSTEM\Integration\GerbangApiGalatAsliTest; di sini
        // yang dijaga adalah keberadaan jalurnya.
        $this->assertStringContainsString(
            '$penangan->report($e);',
            $isi,
            'Exception dari controller harus tetap dilaporkan lewat penangan galat Laravel.',
        );
        $this->assertStringContainsString(
            '$penangan->render($request, $e);',
            $isi,
            'Respons galat harus dirender penangan Laravel supaya statusnya yang sebenarnya sampai ke pemanggil.',
        );
    }

    /** @test */
    public function kegagalan_mencatat_tidak_boleh_menggagalkan_permintaan(): void
    {
        $isi = $this->isi();
        $catat = substr($isi, strpos($isi, 'private function catat('));

        $this->assertMatchesRegularExpression(
            '/DB::table\(\x27N_LMS_Api_Logs\x27\)->insert\(\[.*?\]\);\s*\}\s*catch \(\\\\Throwable/s',
            $catat,
            'INSERT jejak wajib dibungkus try/catch. Kalau kolomnya belum ada di suatu lingkungan, '.
            'callback HCLEARN harus tetap berhasil — bukan ikut mati gara-gara pencatatan.',
        );
    }

    /** @test */
    public function nilai_rahasia_diredaksi_rekursif_apa_pun_tipe_nilainya(): void
    {
        $ringkas = app(ApiEvidenceSanitizer::class)->ringkasBody(json_encode([
            'Kode_Karyawan' => 'A4',
            'Id_Ujian_Token' => 'token-string',
            'nested' => [
                'password' => 123456,
                'api_key' => ['nilai-array'],
                'signature_meta' => ['x' => true],
                'aman' => 'tetap-terlihat',
            ],
        ]), 1000);

        $this->assertNotNull($ringkas);
        $this->assertStringNotContainsString('token-string', $ringkas);
        $this->assertStringNotContainsString('123456', $ringkas);
        $this->assertStringNotContainsString('nilai-array', $ringkas);
        $this->assertStringContainsString('tetap-terlihat', $ringkas);
        $this->assertSame(4, substr_count($ringkas, '[REDACTED]'));
    }

    /** @test */
    public function body_non_json_tidak_pernah_disalin_mentah_ke_log(): void
    {
        $ringkas = app(ApiEvidenceSanitizer::class)->ringkasBody(
            'password=rahasia&token=rahasia-juga',
            1000,
        );

        $this->assertSame('[NON_JSON_REDACTED]', $ringkas);
    }

    /** @test */
    public function identitas_aplikasi_dan_status_signature_baru_diisi_setelah_terbukti(): void
    {
        $isi = $this->isi();

        $this->assertStringContainsString(
            "'signature_valid' => null",
            $isi,
            'Signature belum boleh dianggap Y ketika header/public key bahkan belum bisa diverifikasi.',
        );
        $this->assertStringContainsString(
            "\$jejak['app'] = (int) \$app->Id_Api_Aplikasi;",
            $isi,
            'Begitu public key dikenal, setiap penolakan berikutnya harus diatribusikan ke konsumen itu.',
        );
        $this->assertStringContainsString(
            "\$jejak['signature_valid'] = 'T';",
            $isi,
            'Signature yang gagal hash_equals() harus tercatat T, bukan nilai baku Y.',
        );
        $this->assertStringContainsString(
            "\$jejak['signature_valid'] = 'Y';",
            $isi,
            'Signature baru boleh tercatat Y sesudah hash_equals() lolos.',
        );

        $gagal = strpos($isi, "\$jejak['signature_valid'] = 'T';");
        $lolos = strpos($isi, "\$jejak['signature_valid'] = 'Y';");
        $hashEquals = strpos($isi, 'hash_equals($serverSignature, $signature)');

        $this->assertGreaterThan($hashEquals, $gagal, 'Penanda T harus berada setelah evaluasi hash_equals().');
        $this->assertGreaterThan($gagal, $lolos, 'Penanda Y harus berada setelah cabang signature gagal.');
    }

    /** @test */
    public function dua_pintu_callback_tidak_mengubah_validasi_422_menjadi_type_error_500(): void
    {
        foreach ([
            'app/Http/Controllers/LMS/Integration/LmsThirdPartyCallbackController.php',
            'app/Http/Controllers/LMS/SOP/Integration/SopKuisCallbackController.php',
        ] as $berkas) {
            $isi = file_get_contents(base_path($berkas));

            $this->assertStringNotContainsString(
                'ResponseHelper::error($e->errors(), 422)',
                $isi,
                "$berkas mengirim array ke ResponseHelper::error(string) — validasi 422 berubah TypeError/500.",
            );
            $this->assertStringContainsString(
                '->flatten()->filter()->map(fn ($x) => (string) $x)->values()',
                $isi,
                "$berkas harus meratakan pesan validasi menjadi string sebelum memanggil ResponseHelper.",
            );
        }
    }

    /** @test */
    public function asal_modul_dan_hash_payload_ikut_tercatat(): void
    {
        $isi = $this->isi();

        $this->assertStringContainsString("\$jejak['modul'] = 'SOP';", $isi);
        $this->assertStringContainsString("\$jejak['modul'] = 'LMS';", $isi);
        $this->assertStringContainsString("'Modul' => \$jejak['modul']", $isi);
        $this->assertStringContainsString(
            "'Payload_Hash' => hash('sha256', (string) \$request->getContent())",
            $isi,
            'Hash body wajib tersimpan agar kedua pihak bisa membuktikan payload yang dibahas sama.',
        );
    }

    /** @test */
    public function respons_dan_request_sama_sama_diredaksi(): void
    {
        $isi = $this->isi();

        $this->assertStringContainsString(
            "'Request_Ringkas' => app(ApiEvidenceSanitizer::class)",
            $isi,
        );
        $this->assertStringContainsString(
            "'Response_Ringkas' => app(ApiEvidenceSanitizer::class)",
            $isi,
        );
    }

    /** @test */
    public function token_dan_payload_mentah_tidak_bocor_ke_log_berkas(): void
    {
        foreach ([
            'app/Http/Middleware/LmsApiGuard.php',
            'app/Http/Middleware/HrisApiGuard.php',
            'app/Http/Controllers/LMS/Integration/LmsThirdPartyCallbackController.php',
            'app/Services/LMS/SOP/SopKuisCallbackService.php',
        ] as $berkas) {
            $isi = file_get_contents(base_path($berkas));

            $this->assertStringNotContainsString(
                "'token' => \$idUjianToken",
                $isi,
                "$berkas masih menulis token mentah ke log berkas.",
            );
            $this->assertStringNotContainsString(
                "'Id_Ujian_Token' => \$token",
                $isi,
                "$berkas masih menulis token mentah ke log berkas.",
            );
            $this->assertStringNotContainsString(
                "'payload' => \$request->all()",
                $isi,
                "$berkas masih membuang seluruh body request ke log berkas.",
            );
            $this->assertStringNotContainsString(
                "'message' => \$e->getMessage()",
                $isi,
                "$berkas masih menulis pesan exception penuh (bisa berisi SQL + binding) ke log.",
            );
        }
    }

    /** @test */
    public function id_korelasi_dikembalikan_ke_pemanggil(): void
    {
        $this->assertStringContainsString(
            "\$response->headers->set('X-HC-Correlation-Id', \$correlationId);",
            $this->isi(),
            'Tanpa id yang dikembalikan, pihak luar tak punya cara menyebut baris log yang mana '.
            'saat melapor — dan kembali ke saling tebak.',
        );
    }
}
