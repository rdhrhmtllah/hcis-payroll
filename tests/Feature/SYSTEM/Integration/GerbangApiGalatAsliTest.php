<?php

namespace Tests\Feature\SYSTEM\Integration;

use App\Http\Middleware\HrisApiGuard;
use App\Http\Middleware\LmsApiGuard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Gerbang API pihak ketiga tidak boleh menelan galat yang sebenarnya.
 *
 * ── Kenapa ──────────────────────────────────────────────────────────────────
 *
 * Kedua gerbang membungkus `$next($request)` dengan `catch (\Throwable)` supaya
 * setiap permintaan tetap meninggalkan satu baris bukti. Efek sampingnya:
 * exception dari controller tidak pernah sampai ke penangan galat Laravel.
 * Dua hal hilang sekaligus, dan keduanya justru yang membuat perbatasan ini
 * dibangun:
 *
 *   1. JENIS RESPONSNYA. ValidationException seharusnya 422 berisi field mana
 *      yang salah. Ditelan begitu, HC Learn menerima 500 "Security System
 *      Failure" — tak ada satu pun petunjuk bahwa yang kurang cuma satu field.
 *      Ini persis jenis kebutaan yang bikin kedua pihak saling tebak.
 *
 *   2. LAPORANNYA. report() tak pernah dipanggil, jadi bug controller hanya
 *      menyisakan satu baris tanpa stack trace di seluruh sistem.
 *
 * Perbaikannya melewatkan exception ke penangan galat Laravel (report + render)
 * lalu mencatat status HASIL RENDER itu sebagai bukti.
 *
 * ── Yang dijaga ─────────────────────────────────────────────────────────────
 *
 * Untuk KEDUA gerbang: galat validasi tetap 422, galat tak terduga tetap 500
 * dengan Sumber_Error='SERVER', dan baris bukti membawa status yang benar-benar
 * diterima pemanggil. Diuji berpasangan karena di proyek ini sudah terbukti
 * aturan yang hanya dipasang di satu pintu akan menyimpang di pintu lainnya.
 */
class GerbangApiGalatAsliTest extends TestCase
{
    use DatabaseTransactions;

    public function test_lms_meneruskan_galat_validasi_sebagai_422(): void
    {
        $respons = $this->lewatiGerbang(
            new LmsApiGuardTanpaKripto,
            fn () => throw ValidationException::withMessages(['Score' => 'Score wajib diisi.']),
        );

        $this->assertSame(422, $respons->getStatusCode(), 'Galat validasi ditelan jadi 500 generik.');
        $this->assertStringContainsString('Score', (string) $respons->getContent());
        $this->assertNotEmpty($respons->headers->get('X-HC-Correlation-Id'));

        $baris = $this->bukti('N_LMS_Api_Logs', $respons);
        $this->assertSame(422, (int) $baris->Status_Code, 'Baris bukti menyimpan status yang bukan diterima pemanggil.');
        $this->assertSame('APLIKASI', $baris->Sumber_Error);
    }

    public function test_lms_membalas_500_untuk_galat_tak_terduga(): void
    {
        $respons = $this->lewatiGerbang(
            new LmsApiGuardTanpaKripto,
            fn () => throw new \RuntimeException('kerusakan tak terduga'),
        );

        $this->assertSame(500, $respons->getStatusCode());
        $this->assertStringNotContainsString(
            'kerusakan tak terduga',
            (string) $respons->getContent(),
            'Pesan exception mentah bocor ke pemanggil.',
        );

        $this->assertSame(500, (int) $this->bukti('N_LMS_Api_Logs', $respons)->Status_Code);
    }

    public function test_hris_meneruskan_galat_validasi_sebagai_422(): void
    {
        $respons = $this->lewatiGerbang(
            new HrisApiGuardTanpaKripto,
            fn () => throw ValidationException::withMessages(['Nilai' => 'Nilai wajib diisi.']),
        );

        $this->assertSame(422, $respons->getStatusCode(), 'Galat validasi ditelan jadi 500 generik.');

        $baris = $this->bukti('N_HRIS_Api_Log', $respons);
        $this->assertSame(422, (int) $baris->Status_Code);
        $this->assertSame('CLIENT', $baris->Sumber_Error, '4xx setelah lolos gerbang adalah kesalahan pemanggil.');
    }

    public function test_hris_menandai_galat_tak_terduga_sebagai_server(): void
    {
        $respons = $this->lewatiGerbang(
            new HrisApiGuardTanpaKripto,
            fn () => throw new \RuntimeException('kerusakan tak terduga'),
        );

        $this->assertSame(500, $respons->getStatusCode());

        $baris = $this->bukti('N_HRIS_Api_Log', $respons);
        $this->assertSame(500, (int) $baris->Status_Code);
        $this->assertSame(
            'SERVER',
            $baris->Sumber_Error,
            '5xx tidak boleh tercatat sebagai kesalahan pemanggil.',
        );
    }

    public function test_hris_menandai_ledakan_di_dalam_gerbang_sebagai_server(): void
    {
        // Galat yang meletus SEBELUM gerbang dilewati tetap kegagalan kami.
        // Tanpa aturan "5xx selalu SERVER", baris bukti menyimpan kategori
        // langkah terakhir yang sempat dijalani (mis. 'AUTH') dan kegagalan
        // sendiri terbaca seolah kesalahan pemanggil.
        $respons = $this->lewatiGerbang(
            new HrisApiGuardGerbangMeledak,
            fn () => $this->fail('Controller tidak boleh dijalankan ketika gerbang meledak.'),
        );

        $this->assertSame(500, $respons->getStatusCode());
        $this->assertSame('SERVER', $this->bukti('N_HRIS_Api_Log', $respons)->Sumber_Error);
    }

    private function lewatiGerbang(object $gerbang, \Closure $next): Response
    {
        $request = Request::create('/api/v1/uji-gerbang', 'POST', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['Kode_Karyawan' => 'X1']));

        return $gerbang->handle($request, $next);
    }

    private function bukti(string $tabel, Response $respons): object
    {
        $id = $respons->headers->get('X-HC-Correlation-Id');
        $baris = DB::table($tabel)->where('Correlation_Id', $id)->first();

        $this->assertNotNull($baris, "Tidak ada baris bukti untuk correlation id {$id} di {$tabel}.");

        return $baris;
    }
}

/**
 * Gerbang tanpa kriptografi.
 *
 * Yang diuji berkas ini adalah apa yang terjadi SESUDAH gerbang dilewati, jadi
 * pemeriksaan HMAC/nonce/whitelist-nya dilewati sengaja — bukan dilemahkan di
 * kode produksi. Kalau kelak `periksa()` dijadikan private lagi, kelas ini
 * gagal dimuat dan penjaga ini berteriak alih-alih diam-diam berhenti menguji.
 */
class LmsApiGuardTanpaKripto extends LmsApiGuard
{
    protected function periksa(Request $request, array &$jejak): ?Response
    {
        $jejak['signature_valid'] = 'Y';
        $jejak['modul'] = 'LMS';

        return null;
    }
}

class HrisApiGuardTanpaKripto extends HrisApiGuard
{
    protected function periksa(Request $request, array &$jejak): ?Response
    {
        $jejak['signature_valid'] = 'Y';
        $jejak['lolos_gerbang'] = true;

        return null;
    }
}

class HrisApiGuardGerbangMeledak extends HrisApiGuard
{
    protected function periksa(Request $request, array &$jejak): ?Response
    {
        throw new \RuntimeException('gerbang meledak');
    }
}
