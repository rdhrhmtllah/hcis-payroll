<?php

namespace App\Services\LMS\SOP\Uat;

/**
 * Pembangkit beban baca untuk uji kontensi SOP.
 *
 * SATU PROSES, banyak permintaan serentak lewat curl_multi — bukan puluhan
 * proses PHP. Menyalakan 50 proses beruntun di mesin Windows adalah cara
 * tercepat mengulang STATUS_DLL_INIT_FAILED, lalu salah membacanya sebagai
 * temuan tentang aplikasi.
 *
 * Menembak ENDPOINT API, bukan halaman Inertia: yang dipertanyakan kontensi
 * basis data, bukan pengiriman aset. Halaman Inertia akan menambahkan puluhan
 * permintaan aset yang mengaburkan angkanya.
 */
class UatBebanKlien
{
    /** Porsi admin dalam beban: 3 dari tiap 40 permintaan. */
    private const TIAP = 40;

    private const PORSI_ADMIN = 3;

    /**
     * Halaman admin yang dimuat bersamaan dengan beban karyawan.
     *
     * Bukan pelengkap. Riwayat CPU 100% di HCIS ini datang dari agregasi
     * dashboard, bukan dari halaman karyawan — menguji beban karyawan saja akan
     * melewatkan kombinasi terburuknya.
     */
    private const URL_ADMIN = [
        '/api/v1/sop/admin/monitoring/coverage',
        '/api/v1/sop/admin/monitoring/karyawan',
        '/api/v1/sop/admin/monitoring/analitik/tren',
    ];

    /**
     * @param  list<array{name:string,value:string}>  $sesi
     * @param  list<array{name:string,value:string}>  $sesiAdmin
     */
    public function __construct(
        private string $baseUrl,
        private array $sesi,
        private array $sesiAdmin = [],
    ) {}

    /**
     * Bentuk satu putaran beban: daftar tugas, detail paket, lalu pembaca.
     *
     * SESI DIPUTAR, bukan dipakai satu. Isi SOP Saya berbeda per karyawan dan
     * ikut ter-cache per kunci; memakai satu sesi untuk 50 permintaan membuat 49
     * di antaranya dijawab cache, dan kerja basis data yang sedang diukur tak
     * pernah benar-benar terjadi.
     *
     * @return list<array{url:string,sesi:int,admin:bool}>
     */
    public function rencanaPermintaan(array $dunia, int $serentak): array
    {
        $paket = $dunia['paket_hash'];
        $item = $dunia['item_hash'] ?: ['x'];

        $pola = ['/api/v1/sop/saya', "/api/v1/sop/{$paket}"];
        foreach ($item as $it) {
            $pola[] = "/api/v1/sop/{$paket}/item/{$it}/versi";
        }

        $basis = rtrim($this->baseUrl, '/');
        $out = [];

        for ($i = 0; $i < $serentak; $i++) {
            $giliranAdmin = $this->sesiAdmin !== [] && ($i % self::TIAP) < self::PORSI_ADMIN;

            if ($giliranAdmin) {
                $out[] = [
                    'url' => $basis.self::URL_ADMIN[$i % count(self::URL_ADMIN)],
                    'sesi' => $i % count($this->sesiAdmin),
                    'admin' => true,
                ];

                continue;
            }

            $out[] = [
                'url' => $basis.$pola[$i % count($pola)],
                'sesi' => $i % max(1, count($this->sesi)),
                'admin' => false,
            ];
        }

        return $out;
    }

    /**
     * Tembakkan seluruh permintaan SERENTAK.
     *
     * @param  list<array{url:string,sesi:int,admin:bool}>  $permintaan
     * @return list<array{url:string,http:int,ms:float,galat:string|null,admin:bool}>
     */
    public function tembak(array $permintaan, int $timeoutDetik = 30): array
    {
        if ($permintaan === []) {
            return [];
        }

        $multi = curl_multi_init();
        $pegangan = [];

        foreach ($permintaan as $i => $p) {
            $kolam = ($p['admin'] ?? false) ? $this->sesiAdmin : $this->sesi;
            $s = $kolam[$p['sesi']] ?? null;

            $ch = curl_init($p['url']);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeoutDetik,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_COOKIE => $s ? "{$s['name']}={$s['value']}" : '',
            ]);
            curl_multi_add_handle($multi, $ch);
            $pegangan[$i] = $ch;
        }

        $jalan = null;
        do {
            curl_multi_exec($multi, $jalan);
            curl_multi_select($multi, 0.1);
        } while ($jalan > 0);

        $hasil = [];
        foreach ($pegangan as $i => $ch) {
            $galat = curl_error($ch) ?: null;
            $hasil[] = [
                'url' => $permintaan[$i]['url'],
                'http' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
                'ms' => (float) curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000,
                'galat' => $galat,
                'admin' => (bool) ($permintaan[$i]['admin'] ?? false),
            ];
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }
        curl_multi_close($multi);

        return $hasil;
    }
}
