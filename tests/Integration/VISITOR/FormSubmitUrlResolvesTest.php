<?php

namespace Tests\Integration\VISITOR;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Alamat yang dikirimi formulir harus benar-benar punya route.
 *
 * Celah yang ditutup uji ini nyata dan sudah menggigit: seluruh uji pengajuan
 * permit memanggil `VehiclePermitController::store()` LANGSUNG, sehingga
 * routing tak pernah ikut diuji. Ketika alamat di formulir berubah menjadi
 * `/transaksipermit-simpan` yang tak punya route, 95 uji tetap hijau sementara
 * tombol Simpan gagal di browser dengan "route could not be found".
 */
class FormSubmitUrlResolvesTest extends TestCase
{
    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function formulir(): array
    {
        return [
            'permit' => ['resources/js/Pages/VISITOR/Permit/VehiclePermit/components/PermitForm.vue', 'POST'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('formulir')]
    public function test_alamat_kirim_formulir_punya_route(string $berkas, string $metode): void
    {
        $sumber = file_get_contents(base_path($berkas));
        $this->assertNotFalse($sumber, "Berkas formulir tidak terbaca: {$berkas}");

        preg_match_all("~axios\.post\(\s*'(/[^']+)'~", $sumber, $cocok);
        $alamat = array_values(array_unique($cocok[1]));

        $this->assertNotEmpty(
            $alamat,
            "Tidak ada axios.post yang terbaca di {$berkas}; polanya berubah dan uji ini berhenti menjaga apa pun.",
        );

        foreach ($alamat as $path) {
            // Lewatkan alamat berparameter template literal; uji ini menjaga
            // alamat harfiah, yang justru paling mudah salah ketik.
            if (str_contains($path, '${')) {
                continue;
            }

            try {
                Route::getRoutes()->match(Request::create($path, $metode));
            } catch (NotFoundHttpException) {
                $this->fail("{$berkas} mengirim ke {$metode} {$path}, tetapi route itu tidak ada.");
            }
        }

        $this->addToAssertionCount(count($alamat));
    }

    /**
     * Form tamu mengirim ke `/${apiPrefix}` (DDL-2026-121): prefiksnya dari
     * props halaman Regular / B2B. Yang dijaga: form memang memakai prefiks
     * itu, dan KEDUA prefiks yang dikirim controller punya route POST.
     */
    public function test_alamat_kirim_formulir_tamu_per_tipe_punya_route(): void
    {
        $sumber = (string) file_get_contents(base_path('resources/js/Pages/VISITOR/Guest/Visit/components/TamuForm.vue'));

        $this->assertStringContainsString('axios.post(`/${this.apiPrefix}`', $sumber);

        foreach (['REGULAR', 'B2B'] as $tipe) {
            $path = '/'.\App\Http\Controllers\VISITOR\Guest\Visit\GuestRegistrationController::prefiksApi($tipe);

            try {
                Route::getRoutes()->match(Request::create($path, 'POST'));
            } catch (NotFoundHttpException) {
                $this->fail("Form tamu {$tipe} mengirim ke POST {$path}, tetapi route itu tidak ada.");
            }
        }
    }
}
