<?php

namespace App\Services\SYSTEM\Integration;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Yaml;

/**
 * Menyaring dokumen OpenAPI terhadap hak akses satu aplikasi konsumen.
 *
 * Dokumen (resources/openapi/hcis-third-party.yaml) memuat SELURUH endpoint
 * pihak ketiga dan tidak tahu siapa boleh apa. Yang menentukan izin adalah
 * registry N_HRIS_Api_Akses_Endpoint x N_HRIS_Api_Endpoint — relasi yang sama
 * persis dengan yang dipakai HrisApiGuard.
 *
 * Kesamaan itu disengaja: dokumentasi dan gerbang izin harus membaca satu sumber,
 * supaya dokumen tidak pernah menjanjikan endpoint yang ternyata dijawab 403.
 *
 * Pencocokan memakai POLA rute (".../tahun/{tahun}"), bukan jalur konkret —
 * lihat HrisApiGuard::jalurPermintaan().
 */
class OpenApiRegistryFilter
{
    public const CACHE_KUNCI_DOKUMEN = 'openapi:dokumen-pihak-ketiga';

    /** @var list<string> Method HTTP yang dianggap operasi di objek Path Item OpenAPI. */
    private const OPERASI = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

    public function __construct(private readonly ?string $berkas = null) {}

    /**
     * Dokumen lengkap apa adanya, belum disaring.
     *
     * @return array<string,mixed>
     */
    public function dokumenPenuh(): array
    {
        $berkas = $this->berkas ?? resource_path('openapi/hcis-third-party.yaml');

        return Cache::remember(
            self::CACHE_KUNCI_DOKUMEN.':'.md5($berkas),
            300,
            fn () => Yaml::parseFile($berkas) ?? [],
        );
    }

    /**
     * Endpoint yang boleh diakses aplikasi pemilik $apiPublic.
     *
     * Mengembalikan null bila kunci tidak dikenal atau aplikasinya nonaktif —
     * itu dibedakan dari daftar kosong, yang berarti aplikasinya sah tetapi
     * memang belum diberi endpoint apa pun.
     *
     * @return list<array{url:string, method:string}>|null
     */
    public function izinUntuk(string $apiPublic): ?array
    {
        $apiPublic = trim($apiPublic);
        if ($apiPublic === '') {
            return null;
        }

        $app = DB::table('N_HRIS_Api_Aplikasi')
            ->where('Api_Public', $apiPublic)
            ->where('Flag_Aktif', 'Y')
            ->first(['Id_Api_Aplikasi']);

        if (! $app) {
            return null;
        }

        return DB::table('N_HRIS_Api_Akses_Endpoint as ae')
            ->join('N_HRIS_Api_Endpoint as ep', 'ep.Id_Api_Endpoint', '=', 'ae.Id_Api_Endpoint')
            ->where('ae.Id_Api_Aplikasi', $app->Id_Api_Aplikasi)
            ->where('ae.Flag_Aktif', 'Y')
            ->where('ep.Flag_Aktif', 'Y')
            ->get(['ep.Url_Route', 'ep.Method'])
            ->map(fn ($baris) => ['url' => (string) $baris->Url_Route, 'method' => (string) $baris->Method])
            ->all();
    }

    /**
     * Murni: buang setiap operasi yang tidak ada di daftar izin, lalu buang path
     * yang jadi kosong. Bagian selain `paths` dipertahankan utuh.
     *
     * @param  array<string,mixed>  $dokumen
     * @param  list<array{url:string, method:string}>  $izin
     * @return array<string,mixed>
     */
    public function saring(array $dokumen, array $izin): array
    {
        $diizinkan = [];
        foreach ($izin as $satu) {
            $diizinkan[$this->kunci($satu['url'] ?? '', $satu['method'] ?? '')] = true;
        }

        $paths = [];
        foreach (($dokumen['paths'] ?? []) as $url => $pathItem) {
            if (! is_array($pathItem)) {
                continue;
            }

            $sisa = [];
            foreach ($pathItem as $bagian => $isi) {
                $adalahOperasi = in_array(strtolower((string) $bagian), self::OPERASI, true);

                // Bagian non-operasi (parameters, summary, servers) hanya ikut
                // bila masih ada operasi yang tersisa — diputuskan di bawah.
                if (! $adalahOperasi) {
                    continue;
                }

                if (isset($diizinkan[$this->kunci((string) $url, (string) $bagian)])) {
                    $sisa[$bagian] = $isi;
                }
            }

            if ($sisa === []) {
                continue;
            }

            foreach ($pathItem as $bagian => $isi) {
                if (! in_array(strtolower((string) $bagian), self::OPERASI, true)) {
                    $sisa[$bagian] = $isi;
                }
            }

            $paths[$url] = $sisa;
        }

        $dokumen['paths'] = $paths;

        if (array_key_exists('tags', $dokumen)) {
            $dokumen['tags'] = $this->tagYangMasihTerpakai($dokumen['tags'] ?? [], $paths);
        }

        if (array_key_exists('components', $dokumen)) {
            $dokumen['components'] = $this->komponenYangMasihTerpakai($dokumen['components'] ?? [], $paths);
        }

        return $dokumen;
    }

    /**
     * Buang komponen yang tak lagi dirujuk endpoint mana pun.
     *
     * Tanpa ini bagian "Schemas" di Swagger UI tetap memperlihatkan skema milik
     * endpoint yang sudah tersaring — lengkap dengan nama field-nya. Itu lebih
     * serius daripada kebocoran tag, karena membuka bentuk data konsumen lain.
     *
     * Penelusuran $ref dilakukan sampai titik tetap: memangkas hanya satu lapis
     * menyisakan $ref menggantung, yang di Swagger UI tampil sebagai skema
     * kosong tanpa penjelasan apa pun.
     *
     * @param  array<string,mixed>  $components
     * @param  array<string,mixed>  $paths
     * @return array<string,mixed>
     */
    private function komponenYangMasihTerpakai(array $components, array $paths): array
    {
        // securitySchemes tidak pernah dirujuk lewat $ref, melainkan lewat blok
        // `security` di akar dokumen. Memangkasnya membuat dokumen kehilangan
        // keterangan cara autentikasinya.
        $selaluDisimpan = ['securitySchemes'];

        $dipakai = $this->kumpulkanRujukan($paths);

        // Titik tetap: komponen yang disimpan bisa merujuk komponen lain.
        do {
            $sebelum = count($dipakai, COUNT_RECURSIVE);

            foreach ($dipakai as $tipe => $nama) {
                foreach (array_keys($nama) as $satu) {
                    $isi = $components[$tipe][$satu] ?? null;
                    if (is_array($isi)) {
                        $dipakai = $this->kumpulkanRujukan($isi, $dipakai);
                    }
                }
            }
        } while (count($dipakai, COUNT_RECURSIVE) > $sebelum);

        foreach ($components as $tipe => $isi) {
            if (in_array($tipe, $selaluDisimpan, true) || ! is_array($isi)) {
                continue;
            }

            $components[$tipe] = array_intersect_key($isi, $dipakai[$tipe] ?? []);
        }

        return $components;
    }

    /**
     * Kumpulkan seluruh "#/components/<tipe>/<nama>" di dalam struktur.
     *
     * @param  array<mixed>  $struktur
     * @param  array<string, array<string, true>>  $terkumpul
     * @return array<string, array<string, true>>
     */
    private function kumpulkanRujukan(array $struktur, array $terkumpul = []): array
    {
        foreach ($struktur as $kunci => $nilai) {
            if ($kunci === '$ref' && is_string($nilai)) {
                if (preg_match('#^\#/components/([^/]+)/(.+)$#', $nilai, $cocok) === 1) {
                    $terkumpul[$cocok[1]][$cocok[2]] = true;
                }

                continue;
            }

            if (is_array($nilai)) {
                $terkumpul = $this->kumpulkanRujukan($nilai, $terkumpul);
            }
        }

        return $terkumpul;
    }

    /**
     * Buang tag yang tak lagi punya operasi.
     *
     * Tanpa ini Swagger UI tetap menampilkan judul dan keterangan tag walau
     * seluruh endpoint-nya sudah tersaring — sehingga satu konsumen membaca
     * "SOP — Callback hasil kuis dari HC Learn", yaitu persis kebocoran
     * antar-konsumen yang penyaring ini ada untuk mencegahnya.
     *
     * @param  list<array<string,mixed>>  $tags
     * @param  array<string,mixed>  $paths
     * @return list<array<string,mixed>>
     */
    private function tagYangMasihTerpakai(array $tags, array $paths): array
    {
        $terpakai = [];
        foreach ($paths as $pathItem) {
            foreach ($pathItem as $bagian => $operasi) {
                if (! in_array(strtolower((string) $bagian), self::OPERASI, true) || ! is_array($operasi)) {
                    continue;
                }
                foreach (($operasi['tags'] ?? []) as $nama) {
                    $terpakai[(string) $nama] = true;
                }
            }
        }

        return array_values(array_filter(
            $tags,
            fn ($tag) => is_array($tag) && isset($terpakai[(string) ($tag['name'] ?? '')]),
        ));
    }

    /**
     * Setiap pasangan URL+method yang didokumentasikan, dalam bentuk "url|METHOD".
     * Dipakai penjaga sinkron antara registry dan dokumen.
     *
     * @param  array<string,mixed>  $dokumen
     * @return list<string>
     */
    public function jalurTerdaftar(array $dokumen): array
    {
        $terdaftar = [];
        foreach (($dokumen['paths'] ?? []) as $url => $pathItem) {
            if (! is_array($pathItem)) {
                continue;
            }
            foreach ($pathItem as $bagian => $isi) {
                if (in_array(strtolower((string) $bagian), self::OPERASI, true)) {
                    $terdaftar[] = $this->kunci((string) $url, (string) $bagian);
                }
            }
        }

        return $terdaftar;
    }

    /** Url_Route bertipe varchar dan nilainya kerap berspasi ekor. */
    private function kunci(string $url, string $method): string
    {
        return trim($url).'|'.strtoupper(trim($method));
    }
}
