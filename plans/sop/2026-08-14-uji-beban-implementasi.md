# Harness Uji Beban & Kunci SOP — Rencana Implementasi

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: pakai superpowers:subagent-driven-development (disarankan) atau superpowers:executing-plans untuk mengerjakan rencana ini tugas demi tugas. Langkah memakai checkbox (`- [ ]`).

**Goal:** Membuktikan dengan angka apakah operasi massal SOP (assign / cabut akses puluhan orang) memblokir orang yang sedang membaca, pada basis data yang RCSI-nya mati.

**Architecture:** Satu jalan uji tiga fase. Beban baca dibangkitkan satu proses PHP memakai `curl_multi` ke endpoint API SOP; di tengah fase 2 dan 3, operasi massal ditembakkan dari proses yang sama. Alat ukur sisi server sudah ada seluruhnya (`N_HRIS_Request_Diagnostics`, `db:health-snapshot`, `sys.dm_os_wait_stats`) dan hanya dibaca, bukan dibangun.

**Tech Stack:** Laravel 10, PHP 8.2, SQL Server (sqlsrv), `curl_multi_*`, Pest/PHPUnit.

**Spec:** `plans/sop/2026-08-14-uji-beban-dan-kunci-design.md`

## Global Constraints

- Basis data `Web_HRIS` di `team311` dipakai orang bekerja. **DELETE hanya lewat jurnal `sop:uat-bersihkan`**; dilarang truncate, migrasi, atau delete satu tabel penuh.
- Seluruh baris yang dibuat harus bernama berawalan `UAT-AUTO-` dan tercatat di jurnal.
- Gerbang `UAT_AUTO_ALLOW=1` wajib; tanpa itu semua perintah menolak jalan.
- Populasi nyata: **58 karyawan aktif**, **35 punya akun login**. Sasaran assign baku **50** (melewati ambang async 30). Sesi berbeda maksimal 35.
- Plafon serentak efektif **50** (`FcgidMaxProcesses`).
- Prefiks cache dev **wajib** `devlokal_cache_`, terpisah dari staging.
- Beban ditembakkan ke endpoint **API** (`/api/v1/sop/...`), bukan halaman Inertia.
- Angka dari dev adalah **batas bawah**; laporan wajib menyebutnya begitu.

---

### Task 1: Dunia uji berskala — `sop:beban-dunia`

**Files:**
- Modify: `app/Services/SOP/Uat/UatPembangunDunia.php`
- Create: `app/Console/Commands/SopBebanDunia.php`
- Test: `tests/Feature/LMS/SOP/Uat/BebanDuniaTest.php`

**Interfaces:**
- Consumes: `UatPembangunDunia::bangun()`, `UatJurnal`, `UatGerbang`
- Produces: `UatPembangunDunia::pilihKaryawanAktif(int $jumlah): array` (list `Kode_Karyawan`), dan `bangun()` menerima `array|string $kodeKaryawan`. Perintah mencetak JSON `{jalan, tanda, paket, paket_hash, item[], item_hash[], penjadwalan, karyawan[]}`.

> **`item_hash` WAJIB, bukan tambahan.** Endpoint `/api/v1/sop/{idPaket}/item/{idItem}/versi`
> menerima hashid, sedangkan `item[]` berisi id mentah. Tanpa `item_hash`, Task 3
> akan menyusun URL yang selalu 404 — dan 404 yang cepat akan terbaca sebagai
> "pembaca dokumen ternyata ringan", kesimpulan yang terbalik dari kenyataan.
> Dihasilkan dengan `Hashids::connection('custom')->encode($id)`, sama seperti `paket_hash`.

- [ ] **Step 1: Tulis uji yang gagal**

```php
// tests/Feature/LMS/SOP/Uat/BebanDuniaTest.php
public function test_memilih_karyawan_aktif_sebanyak_yang_diminta(): void
{
    $kode = app(UatPembangunDunia::class)->pilihKaryawanAktif(10);

    $this->assertCount(10, $kode);
    $this->assertSame(array_unique($kode), $kode, 'Ada kode karyawan kembar.');

    $nonaktif = DB::table('Karyawan')->whereIn('Kode_Karyawan', $kode)
        ->where('Aktif', '<>', 'Y')->count();
    $this->assertSame(0, $nonaktif, 'Karyawan nonaktif ikut terpilih.');
}

public function test_meminta_lebih_banyak_dari_yang_ada_memberi_sebanyak_yang_ada(): void
{
    $tersedia = DB::table('Karyawan')->where('Aktif', 'Y')->count();
    $kode = app(UatPembangunDunia::class)->pilihKaryawanAktif($tersedia + 50);

    $this->assertCount($tersedia, $kode,
        'Permintaan berlebih harus mengembalikan sebanyak yang ada, bukan melempar '
        . 'atau diam-diam memotong ke angka lain.');
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `UAT_AUTO_ALLOW=1 php artisan test tests/Feature/LMS/SOP/Uat/BebanDuniaTest.php`
Expected: FAIL — `Call to undefined method ... pilihKaryawanAktif()`

- [ ] **Step 3: Implementasi minimal**

```php
// app/Services/SOP/Uat/UatPembangunDunia.php
/**
 * Karyawan aktif untuk disasar penugasan massal.
 *
 * Diurutkan Kode_Karyawan supaya jalan-uji berulang menyasar orang yang sama —
 * pengukuran yang sasarannya berganti tiap kali tak bisa dibandingkan antar-jalan.
 */
public function pilihKaryawanAktif(int $jumlah): array
{
    return DB::table('Karyawan')
        ->where('Aktif', 'Y')
        ->whereNotNull('Kode_Karyawan')
        ->orderBy('Kode_Karyawan')
        ->limit(max(1, $jumlah))
        ->pluck('Kode_Karyawan')
        ->map(fn ($k) => trim((string) $k))
        ->all();
}
```

Lalu ubah tanda tangan `bangun()` supaya menerima banyak kode:

```php
public function bangun(
    int $userId,
    string|array $kodeKaryawan,          // <- berubah
    array $judulItem = ['Dokumen Pertama', 'Dokumen Kedua'],
    string $modeUrutan = 'BEBAS',
    bool $terbitkan = true,
    bool $revisiTertunda = false,
): array {
```

dan di dalam blok `if ($terbitkan)`:

```php
    $sasaran = array_values(array_unique(array_map(
        'trim', (array) $kodeKaryawan
    )));

    $hasil = app(SopPenjadwalanService::class)->assign($paket, $sasaran, [
        'nama_batch' => "{$tanda} Batch",
        'sumber'     => 'MANUAL',
    ], $userId);
```

Tambahkan `'karyawan' => $sasaran` ke array yang dikembalikan (dan `[]` saat `$terbitkan` false).

- [ ] **Step 4: Jalankan, pastikan lulus**

Run: `UAT_AUTO_ALLOW=1 php artisan test tests/Feature/LMS/SOP/Uat/BebanDuniaTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Perintahnya**

```php
// app/Console/Commands/SopBebanDunia.php
protected $signature = 'sop:beban-dunia
    {--karyawan=50 : Jumlah karyawan aktif yang disasar penugasan}
    {--item=2 : Jumlah item dokumen di dalam paket}
    {--admin= : NIK admin pembangun; bawaannya UAT_NIK_ADMIN}';
```

`handle()` memanggil `UatGerbang::pastikanTerbuka()`, mencari admin lewat `Karyawan`→`User` seperti `SopUatDunia`, memanggil `pilihKaryawanAktif()`, lalu `bangun()`, lalu mencetak JSON satu baris.

- [ ] **Step 6: Uji perintahnya sungguhan, lalu bersihkan**

```bash
UAT_AUTO_ALLOW=1 php artisan --env=uat sop:beban-dunia --karyawan=50
# catat "jalan" dan "penjadwalan" dari keluarannya, lalu:
UAT_AUTO_ALLOW=1 php artisan --env=uat sop:uat-dunia <nik> --lengkapi <jalan> --penjadwalan <id> --paket <id>
UAT_AUTO_ALLOW=1 php artisan --env=uat sop:uat-bersihkan <jalan>
```
Expected: 50 kode karyawan di keluaran; `sop:uat-bersihkan --semua --dry-run` melaporkan nol sisa.

- [ ] **Step 7: Commit**

```bash
git add app/Services/SOP/Uat/UatPembangunDunia.php app/Console/Commands/SopBebanDunia.php tests/Feature/LMS/SOP/Uat/BebanDuniaTest.php
git commit -m "feat(sop): dunia uji berskala untuk uji beban — assign ke 50 karyawan aktif"
```

---

### Task 2: Statistik latensi — `UatBebanStatistik`

**Files:**
- Create: `app/Services/SOP/Uat/UatBebanStatistik.php`
- Test: `tests/Unit/LMS/SOP/Uat/UatBebanStatistikTest.php`

**Interfaces:**
- Produces: `UatBebanStatistik::ringkas(array $ms): array` mengembalikan `['n'=>int,'p50'=>float,'p95'=>float,'maks'=>float]`; `UatBebanStatistik::persentil(array $ms, float $p): float`.

Dipisah jadi kelas sendiri karena murni fungsi — bisa diuji tanpa jaringan, tanpa basis data, dan salah hitung persentil di sini akan merusak seluruh kesimpulan tanpa terlihat.

- [ ] **Step 1: Tulis uji yang gagal**

```php
// tests/Unit/LMS/SOP/Uat/UatBebanStatistikTest.php
public function test_persentil_memakai_interpolasi_linier(): void
{
    $ms = [10, 20, 30, 40];

    $this->assertSame(25.0, UatBebanStatistik::persentil($ms, 0.5));
    $this->assertSame(10.0, UatBebanStatistik::persentil($ms, 0.0));
    $this->assertSame(40.0, UatBebanStatistik::persentil($ms, 1.0));
}

public function test_urutan_masukan_tidak_mempengaruhi_hasil(): void
{
    $acak = [40, 10, 30, 20];

    $this->assertSame(25.0, UatBebanStatistik::persentil($acak, 0.5),
        'Persentil bergantung pada urutan masukan — berarti datanya tidak diurutkan dulu.');
}

public function test_daftar_kosong_tidak_melempar(): void
{
    $r = UatBebanStatistik::ringkas([]);

    $this->assertSame(0, $r['n']);
    $this->assertSame(0.0, $r['p95'],
        'Fase tanpa satu pun permintaan harus terbaca 0, bukan menghentikan laporan.');
}

public function test_ringkas_melaporkan_maks_yang_benar(): void
{
    $r = UatBebanStatistik::ringkas([5, 100, 20]);

    $this->assertSame(3, $r['n']);
    $this->assertSame(100.0, $r['maks']);
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test tests/Unit/LMS/SOP/Uat/UatBebanStatistikTest.php`
Expected: FAIL — kelas tidak ditemukan

- [ ] **Step 3: Implementasi minimal**

```php
// app/Services/SOP/Uat/UatBebanStatistik.php
namespace App\Services\SOP\Uat;

class UatBebanStatistik
{
    /** Persentil dengan interpolasi linier; daftar kosong = 0.0. */
    public static function persentil(array $ms, float $p): float
    {
        $nilai = array_values(array_map('floatval', $ms));
        if ($nilai === []) {
            return 0.0;
        }

        sort($nilai);
        $posisi = $p * (count($nilai) - 1);
        $bawah = (int) floor($posisi);
        $atas = (int) ceil($posisi);

        if ($bawah === $atas) {
            return $nilai[$bawah];
        }

        return $nilai[$bawah] + ($nilai[$atas] - $nilai[$bawah]) * ($posisi - $bawah);
    }

    /** @return array{n:int,p50:float,p95:float,maks:float} */
    public static function ringkas(array $ms): array
    {
        return [
            'n'    => count($ms),
            'p50'  => self::persentil($ms, 0.50),
            'p95'  => self::persentil($ms, 0.95),
            'maks' => $ms === [] ? 0.0 : (float) max($ms),
        ];
    }
}
```

- [ ] **Step 4: Jalankan, pastikan lulus**

Run: `php artisan test tests/Unit/LMS/SOP/Uat/UatBebanStatistikTest.php`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/SOP/Uat/UatBebanStatistik.php tests/Unit/LMS/SOP/Uat/UatBebanStatistikTest.php
git commit -m "feat(sop): statistik latensi uji beban, terpisah supaya bisa diuji tanpa jaringan"
```

---

### Task 3: Pembangkit beban — `UatBebanKlien`

**Files:**
- Create: `app/Services/SOP/Uat/UatBebanKlien.php`
- Test: `tests/Unit/LMS/SOP/Uat/UatBebanKlienTest.php`

**Interfaces:**
- Consumes: `UatBebanStatistik`
- Produces:
  - `UatBebanKlien::__construct(string $baseUrl, array $sesi, array $sesiAdmin = [])` — masing-masing list `['name'=>string,'value'=>string]` dari `sop:uat-sesi`. Admin dipisah karena bebannya berbeda jenis (agregasi, bukan pembacaan per-orang) dan porsinya kecil: **3 dari tiap 40 permintaan**, meniru 2–3 admin yang memantau sementara puluhan karyawan membaca.
  - `rencanaPermintaan(array $dunia, int $serentak): array` — daftar `['url'=>string,'sesi'=>int]` sepanjang `$serentak`
  - `tembak(array $permintaan, int $timeoutDetik = 30): array` — list `['url','http','ms','galat']`

`rencanaPermintaan()` dipisah dari `tembak()` supaya bentuk beban bisa diuji tanpa menyentuh jaringan.

- [ ] **Step 1: Tulis uji yang gagal**

```php
// tests/Unit/LMS/SOP/Uat/UatBebanKlienTest.php
private function dunia(): array
{
    return [
        'paket_hash' => 'ABC123',
        'item_hash'  => ['IT1', 'IT2'],
    ];
}

public function test_rencana_menghasilkan_sebanyak_yang_diminta(): void
{
    $klien = new UatBebanKlien('http://contoh.test', [
        ['name' => 'c', 'value' => 'v1'],
        ['name' => 'c', 'value' => 'v2'],
    ]);

    $this->assertCount(50, $klien->rencanaPermintaan($this->dunia(), 50));
}

public function test_rencana_memutar_sesi_supaya_tidak_satu_orang_saja(): void
{
    $klien = new UatBebanKlien('http://contoh.test', [
        ['name' => 'c', 'value' => 'v1'],
        ['name' => 'c', 'value' => 'v2'],
    ]);

    $dipakai = array_unique(array_column($klien->rencanaPermintaan($this->dunia(), 10), 'sesi'));

    $this->assertCount(2, $dipakai,
        'Seluruh beban memakai satu sesi — cache per-pengguna akan menyembunyikan '
        . 'kerja basis data yang sebenarnya.');
}

public function test_rencana_mencakup_ketiga_langkah_alur_karyawan(): void
{
    $klien = new UatBebanKlien('http://contoh.test', [['name' => 'c', 'value' => 'v']]);
    $url = array_column($klien->rencanaPermintaan($this->dunia(), 30), 'url');

    $this->assertNotEmpty(preg_grep('#/api/v1/sop/saya$#', $url), 'Daftar tugas tak pernah dibuka.');
    $this->assertNotEmpty(preg_grep('#/api/v1/sop/ABC123$#', $url), 'Detail paket tak pernah dibuka.');
    $this->assertNotEmpty(preg_grep('#/api/v1/sop/ABC123/item/IT[12]/versi$#', $url),
        'Pembaca dokumen tak pernah tersentuh — padahal itu bagian terberatnya.');
}

public function test_beban_admin_ikut_disertakan(): void
{
    $klien = new UatBebanKlien('http://contoh.test', [['name' => 'c', 'value' => 'v']], [
        ['name' => 'c', 'value' => 'admin'],
    ]);

    $url = array_column($klien->rencanaPermintaan($this->dunia(), 40), 'url');

    $this->assertNotEmpty(preg_grep('#/api/v1/sop/admin/monitoring/#', $url),
        'Monitoring admin tak pernah dimuat. Riwayat CPU 100% di HCIS ini datang dari '
        . 'agregasi dashboard — melewatkannya berarti melewatkan kombinasi terburuknya.');
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test tests/Unit/LMS/SOP/Uat/UatBebanKlienTest.php`
Expected: FAIL — kelas tidak ditemukan

- [ ] **Step 3: Implementasi**

```php
// app/Services/SOP/Uat/UatBebanKlien.php
namespace App\Services\SOP\Uat;

class UatBebanKlien
{
    /** Porsi admin dalam beban: 3 dari tiap 40 permintaan. */
    private const TIAP = 40;

    private const PORSI_ADMIN = 3;

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
     * Bentuk beban satu putaran: daftar tugas, detail paket, lalu pembaca.
     *
     * Sesi DIPUTAR, bukan dipakai satu. SOP Saya berbeda isinya per karyawan dan
     * ikut ter-cache per kunci; memakai satu sesi untuk 50 permintaan membuat 49
     * di antaranya dijawab cache dan kerja basis data yang sebenarnya tak pernah
     * terjadi.
     *
     * @return list<array{url:string,sesi:int}>
     */
    public function rencanaPermintaan(array $dunia, int $serentak): array
    {
        $paket = $dunia['paket_hash'];
        $item = $dunia['item_hash'] ?: ['x'];

        $pola = ["/api/v1/sop/saya", "/api/v1/sop/{$paket}"];
        foreach ($item as $it) {
            $pola[] = "/api/v1/sop/{$paket}/item/{$it}/versi";
        }

        $out = [];
        for ($i = 0; $i < $serentak; $i++) {
            $adminGiliran = $this->sesiAdmin !== []
                && ($i % self::TIAP) < self::PORSI_ADMIN;

            if ($adminGiliran) {
                $out[] = [
                    'url'   => rtrim($this->baseUrl, '/')
                        . self::URL_ADMIN[$i % count(self::URL_ADMIN)],
                    'sesi'  => $i % count($this->sesiAdmin),
                    'admin' => true,
                ];

                continue;
            }

            $out[] = [
                'url'   => rtrim($this->baseUrl, '/') . $pola[$i % count($pola)],
                'sesi'  => $i % max(1, count($this->sesi)),
                'admin' => false,
            ];
        }

        return $out;
    }

    /**
     * Tembakkan seluruh permintaan SERENTAK dan kembalikan hasil per permintaan.
     *
     * @param  list<array{url:string,sesi:int}>  $permintaan
     * @return list<array{url:string,http:int,ms:float,galat:string|null}>
     */
    public function tembak(array $permintaan, int $timeoutDetik = 30): array
    {
        $multi = curl_multi_init();
        $pegangan = [];

        foreach ($permintaan as $i => $p) {
            $kolam = ($p['admin'] ?? false) ? $this->sesiAdmin : $this->sesi;
            $s = $kolam[$p['sesi']] ?? null;
            $ch = curl_init($p['url']);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $timeoutDetik,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
                CURLOPT_COOKIE         => $s ? "{$s['name']}={$s['value']}" : '',
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
                'url'   => $permintaan[$i]['url'],
                'http'  => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
                'ms'    => (float) curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000,
                'galat' => $galat,
            ];
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }
        curl_multi_close($multi);

        return $hasil;
    }
}
```

- [ ] **Step 4: Jalankan, pastikan lulus**

Run: `php artisan test tests/Unit/LMS/SOP/Uat/UatBebanKlienTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/SOP/Uat/UatBebanKlien.php tests/Unit/LMS/SOP/Uat/UatBebanKlienTest.php
git commit -m "feat(sop): pembangkit beban curl_multi — bentuk beban terpisah dari penembakannya"
```

---

### Task 4: Orkestrator tiga fase — `sop:beban-jalan`

**Files:**
- Create: `app/Console/Commands/SopBebanJalan.php`
- Test: `tests/Unit/LMS/SOP/Uat/UatBebanFaseTest.php`
- Create: `app/Services/SOP/Uat/UatBebanFase.php`

**Interfaces:**
- Consumes: `UatBebanKlien`, `UatBebanStatistik`, `SopPenjadwalanService::assign()`, `SopPenjadwalanService::bulkCabut()`
- Produces: `UatBebanFase::deltaWait(array $sebelum, array $sesudah): array` (peta `wait_type => selisih ms`); berkas hasil `storage/app/uat-auto/beban-<jalan>.json`

- [ ] **Step 1: Tulis uji yang gagal**

```php
// tests/Unit/LMS/SOP/Uat/UatBebanFaseTest.php
public function test_delta_wait_menghitung_selisih_per_jenis(): void
{
    $d = UatBebanFase::deltaWait(
        ['LCK_M_S' => 1000.0, 'LCK_M_X' => 50.0],
        ['LCK_M_S' => 1750.0, 'LCK_M_X' => 50.0],
    );

    $this->assertSame(750.0, $d['LCK_M_S']);
    $this->assertSame(0.0, $d['LCK_M_X']);
}

public function test_jenis_wait_yang_baru_muncul_dihitung_penuh(): void
{
    $d = UatBebanFase::deltaWait([], ['LCK_M_U' => 300.0]);

    $this->assertSame(300.0, $d['LCK_M_U'],
        'Jenis wait yang belum ada di potret awal harus terhitung penuh, bukan hilang.');
}

public function test_penurunan_dijepit_ke_nol(): void
{
    $d = UatBebanFase::deltaWait(['LCK_M_S' => 500.0], ['LCK_M_S' => 100.0]);

    $this->assertSame(0.0, $d['LCK_M_S'],
        'dm_os_wait_stats bisa ter-reset di tengah; nilai negatif akan terbaca '
        . 'sebagai "beban menurunkan penantian", yang mustahil.');
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test tests/Unit/LMS/SOP/Uat/UatBebanFaseTest.php`
Expected: FAIL — kelas tidak ditemukan

- [ ] **Step 3: Implementasi `UatBebanFase`**

```php
// app/Services/SOP/Uat/UatBebanFase.php
namespace App\Services\SOP\Uat;

use Illuminate\Support\Facades\DB;

class UatBebanFase
{
    /** Penantian kunci kumulatif per jenis, dalam milidetik. */
    public static function potretWait(): array
    {
        $rows = DB::select(
            "SELECT wait_type, wait_time_ms FROM sys.dm_os_wait_stats WHERE wait_type LIKE 'LCK%'"
        );

        $out = [];
        foreach ($rows as $r) {
            $out[$r->wait_type] = (float) $r->wait_time_ms;
        }

        return $out;
    }

    /**
     * Selisih dua potret. Penurunan dijepit ke nol: dm_os_wait_stats bisa
     * ter-reset (DBCC SQLPERF) di tengah jalan, dan angka negatif akan terbaca
     * sebagai "beban justru menurunkan penantian" — mustahil, dan menyesatkan.
     */
    public static function deltaWait(array $sebelum, array $sesudah): array
    {
        $out = [];
        foreach ($sesudah as $jenis => $nilai) {
            $out[$jenis] = max(0.0, $nilai - ($sebelum[$jenis] ?? 0.0));
        }

        return $out;
    }
}
```

- [ ] **Step 4: Jalankan, pastikan lulus**

Run: `php artisan test tests/Unit/LMS/SOP/Uat/UatBebanFaseTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Perintah orkestrator**

```php
// app/Console/Commands/SopBebanJalan.php
protected $signature = 'sop:beban-jalan
    {--serentak=50 : Permintaan yang berjalan bersamaan}
    {--detik=60 : Lama tiap fase}
    {--karyawan=50 : Jumlah karyawan yang di-assign / dicabut}
    {--url= : Basis URL; bawaannya UAT_BASE_URL}';
```

`handle()` mengerjakan, berurutan:

1. `UatGerbang::pastikanTerbuka()`.
2. Bangun dunia lewat `sop:beban-dunia` (panggil `UatPembangunDunia` langsung, bukan `Artisan::call`, supaya id-nya langsung di tangan).
3. Cetak sesi untuk **seluruh karyawan yang punya akun login** lewat `SopUatSesi` (maksimal 35).
4. **Fase 1** — putaran `tembak()` berulang selama `--detik`, tanpa operasi massal.
5. **Fase 2** — sama, tapi pada detik ke-20 jalankan `assign()` ke `--karyawan` orang, catat lamanya.
6. **Fase 3** — sama, tapi pada detik ke-20 jalankan `bulkCabut()`, catat lamanya.
7. Tiap detik pada ketiga fase: `Artisan::call('db:health-snapshot')`.
8. Sebelum & sesudah tiap fase: `UatBebanFase::potretWait()`.
9. Tulis seluruhnya ke `storage/app/uat-auto/beban-<jalan>.json`.
10. Lengkapi jurnal (`lengkapiJurnal` dengan `--paket`) — **selalu**, di blok `finally`, supaya kegagalan di tengah tetap meninggalkan jurnal yang bisa dibersihkan.

Naikkan bertahap: fase 1 dimulai `serentak/10`, lalu `serentak/2`, lalu penuh. Berhenti dan tandai gagal bila >5% permintaan non-200 pada tingkat mana pun.

- [ ] **Step 6: Jalan kering dengan beban kecil**

```bash
UAT_AUTO_ALLOW=1 php artisan --env=uat sop:beban-jalan --serentak=5 --detik=10 --karyawan=5
```
Expected: berkas JSON lahir, ketiga fase terisi, `sop:uat-bersihkan --semua --dry-run` melaporkan nol sisa setelah dibersihkan.

- [ ] **Step 7: Commit**

```bash
git add app/Services/SOP/Uat/UatBebanFase.php app/Console/Commands/SopBebanJalan.php tests/Unit/LMS/SOP/Uat/UatBebanFaseTest.php
git commit -m "feat(sop): orkestrator uji beban tiga fase dengan potret blocking per detik"
```

---

### Task 5: Laporan — `sop:beban-laporan`

**Files:**
- Create: `app/Console/Commands/SopBebanLaporan.php`
- Test: `tests/Unit/LMS/SOP/Uat/UatBebanLaporanTest.php`

**Interfaces:**
- Consumes: berkas `storage/app/uat-auto/beban-<jalan>.json`, tabel `N_HRIS_Request_Diagnostics`, `N_HRIS_DB_Health_Snapshot`
- Produces: markdown di `plans/sop/uji-beban-<tanggal>.md`; kode keluar bukan-nol bila ada kriteria yang gagal

- [ ] **Step 1: Tulis uji yang gagal**

```php
// tests/Unit/LMS/SOP/Uat/UatBebanLaporanTest.php
public function test_p95_dua_kali_lipat_dinyatakan_gagal(): void
{
    $nilai = SopBebanLaporan::nilaiKriteria([
        'fase' => [
            ['nama' => 'dasar',  'p95' => 100.0, 'gagal' => 0, 'timeout' => 0],
            ['nama' => 'assign', 'p95' => 250.0, 'gagal' => 0, 'timeout' => 0],
        ],
        'blocking_terlama_ms' => 0,
        'transaksi_massal_ms' => [1200],
    ]);

    $this->assertFalse($nilai['lulus']);
    $this->assertContains('p95', implode(' ', $nilai['alasan']));
}

public function test_semua_dalam_batas_dinyatakan_lulus(): void
{
    $nilai = SopBebanLaporan::nilaiKriteria([
        'fase' => [
            ['nama' => 'dasar',  'p95' => 100.0, 'gagal' => 0, 'timeout' => 0],
            ['nama' => 'assign', 'p95' => 150.0, 'gagal' => 0, 'timeout' => 0],
        ],
        'blocking_terlama_ms' => 800,
        'transaksi_massal_ms' => [3000],
    ]);

    $this->assertTrue($nilai['lulus'], implode(' | ', $nilai['alasan']));
}

public function test_satu_permintaan_gagal_sudah_menggugurkan(): void
{
    $nilai = SopBebanLaporan::nilaiKriteria([
        'fase' => [
            ['nama' => 'dasar', 'p95' => 100.0, 'gagal' => 1, 'timeout' => 0],
        ],
        'blocking_terlama_ms' => 0,
        'transaksi_massal_ms' => [],
    ]);

    $this->assertFalse($nilai['lulus'],
        'Satu permintaan gagal harus menggugurkan — kriteria 1 tidak punya toleransi.');
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test tests/Unit/LMS/SOP/Uat/UatBebanLaporanTest.php`
Expected: FAIL — kelas tidak ditemukan

- [ ] **Step 3: Implementasi `nilaiKriteria()` + perintahnya**

```php
/**
 * Enam kriteria dari spesifikasi, dinilai dari berkas hasil.
 *
 * Ambang p95 (2×) satu-satunya yang angkanya pilihan, bukan turunan sistem —
 * ditulis sebagai tetapan bernama supaya terlihat sebagai keputusan, bukan
 * terselip sebagai angka ajaib di tengah perbandingan.
 *
 * @return array{lulus:bool, alasan:list<string>}
 */
public const AMBANG_P95_KALI = 2.0;
public const AMBANG_BLOCKING_MS = 2000;
public const AMBANG_TRANSAKSI_MS = 5000;

public static function nilaiKriteria(array $hasil): array
{
    $alasan = [];
    $fase = $hasil['fase'];
    $dasar = $fase[0]['p95'] ?: 0.0;

    foreach ($fase as $f) {
        if (($f['gagal'] ?? 0) > 0) {
            $alasan[] = "fase {$f['nama']}: {$f['gagal']} permintaan gagal";
        }
        if (($f['timeout'] ?? 0) > 0) {
            $alasan[] = "fase {$f['nama']}: {$f['timeout']} timeout";
        }
        if ($dasar > 0 && $f['p95'] > $dasar * self::AMBANG_P95_KALI) {
            $alasan[] = sprintf('fase %s: p95 %.0f ms > %.1f× dasar (%.0f ms)',
                $f['nama'], $f['p95'], self::AMBANG_P95_KALI, $dasar);
        }
    }

    if (($hasil['blocking_terlama_ms'] ?? 0) > self::AMBANG_BLOCKING_MS) {
        $alasan[] = "pembaca terblokir {$hasil['blocking_terlama_ms']} ms";
    }

    foreach ($hasil['transaksi_massal_ms'] ?? [] as $ms) {
        if ($ms > self::AMBANG_TRANSAKSI_MS) {
            $alasan[] = "transaksi massal {$ms} ms melewati ambang";
        }
    }

    return ['lulus' => $alasan === [], 'alasan' => $alasan];
}
```

Perintah `sop:beban-laporan {--jalan=} {--keluaran=}` membaca berkas JSON, menarik baris `N_HRIS_Request_Diagnostics` dan `N_HRIS_DB_Health_Snapshot` dalam jendela waktu jalan-uji, lalu menulis markdown yang memuat: tabel per fase (n, p50, p95, maks, gagal, timeout), delta `LCK_M_*` per fase, daftar korban diagnostik, rantai blocking terlama, lama transaksi massal, dan **hasil keenam kriteria**. Wajib memuat kalimat bahwa angka ini **batas bawah** karena lapis webnya bukan Cloud Run.

- [ ] **Step 4: Jalankan, pastikan lulus**

Run: `php artisan test tests/Unit/LMS/SOP/Uat/UatBebanLaporanTest.php`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/SopBebanLaporan.php tests/Unit/LMS/SOP/Uat/UatBebanLaporanTest.php
git commit -m "feat(sop): laporan uji beban dengan enam kriteria yang ditetapkan di muka"
```

---

### Task 6: Jalan penuh dan pembacaan hasil

**Files:**
- Create: `plans/sop/uji-beban-2026-08-14.md` (dihasilkan)

- [ ] **Step 1: Nyalakan penanda sesi diagnostik**

Tambahkan ke `.env`: `REQUEST_DIAGNOSTICS_TAG_SESSION=true`, lalu `php artisan config:clear`.
Catat bahwa ini **wajib dimatikan lagi** di langkah terakhir.

- [ ] **Step 2: Pastikan basis data bersih sebelum mulai**

Run: `UAT_AUTO_ALLOW=1 php artisan --env=uat sop:uat-bersihkan --semua`
Expected: "Tidak ada jurnal yang perlu dibersihkan."

- [ ] **Step 3: Jalan penuh**

```bash
UAT_AUTO_ALLOW=1 php artisan --env=uat sop:beban-jalan --serentak=50 --detik=60 --karyawan=50
```

- [ ] **Step 4: Bangun laporan**

```bash
php artisan sop:beban-laporan
```

- [ ] **Step 5: Bersihkan dan kembalikan setelan**

```bash
UAT_AUTO_ALLOW=1 php artisan --env=uat sop:uat-bersihkan --semua
```
Hapus `REQUEST_DIAGNOSTICS_TAG_SESSION=true` dari `.env`, lalu `php artisan config:clear`.
Periksa: nol baris `UAT-AUTO` di `N_SOP_Master_Paket`, `N_SOP_Penjadwalan_Detail`, `N_HRIS_Notification`.

- [ ] **Step 6: Commit laporan**

```bash
git add plans/sop/uji-beban-2026-08-14.md
git commit -m "docs(sop): hasil uji beban & kunci di dev — batas bawah, lapis web bukan Cloud Run"
```
