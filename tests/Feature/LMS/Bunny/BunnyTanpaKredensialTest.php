<?php

namespace Tests\Feature\LMS\Bunny;

use App\Services\LMS\Bunny\BunnyStreamService;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Penjaga: kredensial Bunny yang belum diisi harus MENGATAKAN dirinya.
 *
 * Lahir dari galat unggah video di Library Konten SOP, 20 Agustus 2026:
 * pengguna menerima "Gagal membuat objek video di Bunny" — pesan yang menuduh
 * penyedia, padahal `BUNNY_API_KEY` di .env environment ini memang kosong.
 *
 * Kode sudah punya penjaga yang benar (`blank($apiKey)` di createTusSignature,
 * lengkap dengan pesan "Konfigurasi Bunny tidak lengkap"). Penjaga itu TIDAK
 * PERNAH BISA BERJALAN: `protected string $apiKey` diisi config yang null,
 * sehingga konstruktor melempar TypeError lebih dulu — persis pada satu-satunya
 * keadaan yang penjaganya dirancang untuk tangani.
 *
 * Pelajaran yang dijaga di sini bukan "cast ke string", melainkan: penjaga yang
 * berada di HILIR titik ledak sama saja dengan tidak ada penjaga.
 */
class BunnyTanpaKredensialTest extends TestCase
{
    private function tanpaApiKey(): void
    {
        config([
            'services.lms.bunny.api_key' => null,
            'services.lms.bunny.library_id' => '603117',
        ]);
    }

    public function test_service_bisa_dibangun_walau_kredensial_kosong(): void
    {
        $this->tanpaApiKey();

        // Sebelum perbaikan, baris ini melempar TypeError dan seluruh penjaga
        // di dalam kelas menjadi kode mati.
        $service = new BunnyStreamService;

        $this->assertInstanceOf(BunnyStreamService::class, $service);
    }

    public function test_kredensial_kosong_dijawab_pesan_konfigurasi_bukan_kegagalan_penyedia(): void
    {
        $this->tanpaApiKey();

        $respons = (new BunnyStreamService)->createTusSignature(
            Request::create('/', 'POST', ['title' => 'Uji', 'trace_id' => 'uji-tanpa-kredensial'])
        );

        $this->assertSame(500, $respons->getStatusCode());

        $isi = (string) $respons->getContent();

        // Pesannya harus menunjuk KONFIGURASI KITA, bukan menuduh Bunny.
        $this->assertStringContainsString('Konfigurasi Bunny tidak lengkap', $isi);

        // Dan tidak boleh ada permintaan yang benar-benar dikirim ke penyedia:
        // memanggil API berbayar dengan header "AccessKey: null" hanya
        // menghasilkan 401 yang menyesatkan pembacanya.
        $this->assertStringNotContainsString('Authentication has been denied', $isi);
    }
}
