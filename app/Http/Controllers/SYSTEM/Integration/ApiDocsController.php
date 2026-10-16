<?php

namespace App\Http\Controllers\SYSTEM\Integration;

use App\Http\Controllers\Controller;
use App\Services\SYSTEM\Integration\OpenApiRegistryFilter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use stdClass;

/**
 * Dokumentasi OpenAPI untuk konsumen pihak ketiga.
 *
 * Dua rute, dan pemisahannya disengaja:
 *
 * - GET /api/docs             halaman Swagger UI. Publik, tetapi TIDAK memuat
 *                             satu pun nama endpoint — hanya kerangka.
 * - GET /api/docs/openapi.json dokumen tersaring. Wajib header X-HC-Public.
 *
 * Kunci tidak pernah diletakkan di URL, sehingga tidak tercatat di riwayat
 * browser, log akses, maupun header Referer.
 *
 * Catatan keamanan: Api_Public BUKAN rahasia — ia terkirim polos di setiap
 * permintaan. Menyaring dokumen dengannya mencegah satu konsumen melihat
 * permukaan API konsumen lain; ia bukan kendali kerahasiaan. Karena itu dokumen
 * tidak boleh memuat data sungguhan atau catatan internal.
 */
class ApiDocsController extends Controller
{
    public function __construct(private readonly OpenApiRegistryFilter $penyaring) {}

    public function halaman(): View
    {
        // Huruf besar-kecilnya WAJIB persis: Windows tidak peduli, Cloud Run
        // peduli. Konvensinya mengikuti SYSTEM.Application.Maintenance.pulihkan.
        return view('SYSTEM.Integration.api-docs');
    }

    public function spesifikasi(Request $request): JsonResponse
    {
        $apiPublic = (string) $request->header('X-HC-Public', '');
        $izin = $this->penyaring->izinUntuk($apiPublic);

        // null berarti kunci tidak dikenal atau aplikasinya nonaktif. Itu berbeda
        // dari daftar kosong, yang berarti aplikasinya sah tetapi belum diberi
        // endpoint apa pun — kasus itu dijawab 200 dengan paths kosong.
        if ($izin === null) {
            return $this->tanpaCache(response()->json([
                'sukses' => false,
                'message' => 'Kunci publik tidak dikenal. Masukkan Api_Public yang diberikan tim HCIS.',
                'result' => null,
            ], 401));
        }

        $dokumen = $this->penyaring->saring($this->penyaring->dokumenPenuh(), $izin);

        // servers sengaja tidak ditulis di YAML: diisi dari host yang sedang
        // dibuka, supaya dokumen yang sama benar di dev, staging, dan production.
        $dokumen['servers'] = [[
            'url' => $request->getSchemeAndHttpHost(),
            'description' => 'Server yang sedang dibuka',
        ]];

        // paths kosong harus tetap objek JSON ({}), bukan array ([]) — array
        // kosong membuat dokumen tidak sah menurut skema OpenAPI.
        if (($dokumen['paths'] ?? []) === []) {
            $dokumen['paths'] = new stdClass;
        }

        return $this->tanpaCache(response()->json($dokumen));
    }

    /**
     * Isi respons ini BERBEDA per konsumen, dan yang membedakannya adalah sebuah
     * header — bukan URL. Cache mana pun yang berkunci URL saja akan menyajikan
     * dokumen milik satu konsumen kepada konsumen lain, yaitu persis kebocoran
     * yang penyaring ini ada untuk mencegahnya.
     *
     * `Vary` memberi tahu cache apa yang membedakannya; `no-store` memastikan
     * tidak ada yang menyimpannya sama sekali. Keduanya dipasang: Laravel
     * memang sudah memberi `private` secara baku, tetapi mengandalkan nilai
     * baku untuk sifat keamanan berarti sifat itu bisa hilang tanpa suara kalau
     * suatu saat ada middleware yang menimpanya.
     */
    private function tanpaCache(JsonResponse $respons): JsonResponse
    {
        return $respons
            ->header('Vary', 'X-HC-Public')
            ->header('Cache-Control', 'no-store, no-cache, private, max-age=0');
    }
}
