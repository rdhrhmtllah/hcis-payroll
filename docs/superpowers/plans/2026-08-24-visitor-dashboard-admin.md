# Dashboard Admin Visitor — Rencana Implementasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambah tab **Dashboard** pada halaman Laporan & Dashboard Visitor yang menjawab "apa yang harus saya kerjakan sekarang" — keadaan area, antrian approval, tren, permit, dan kesehatan data — tanpa menambah satu pun endpoint tulis.

**Architecture:** Dua endpoint baca dipisah menurut sifat kesegarannya (`sekarang` cache 60 detik, `analitik` cache 10 menit berkunci rentang). Kueri mengambil baris berjumlah kecil, agregasinya dikerjakan fungsi murni di PHP sehingga bisa diuji tanpa menyentuh database. Frontend memakai kit `@/components/ui` yang sudah ada.

**Tech Stack:** Laravel 10 · SQL Server (`Web_HRIS`) · Inertia + Vue 3 (Options/`<script setup>` campuran, ikut berkas tetangga) · kit `Hcis*` · Vitest · PHPUnit.

**Spec:** `docs/superpowers/specs/2026-08-24-visitor-dashboard-admin-design.md`

## Global Constraints

- **Sumber lifecycle tamu = header + detail, BUKAN `N_VISITOR_Tamu_Efektif`.** Tabel efektif adalah salinan saat approval; check-in/check-out tidak pernah menulisnya. Membacanya menghasilkan nol senyap.
- **Permit tidak punya check-in/check-out.** Permit tidak pernah ikut dijumlahkan ke headcount "di dalam area".
- **`Waktu_Estimasi_Kunjungan` haram dipakai sebagai jam jatuh tempo** — `varchar(50)` teks bebas (`"1 - 2 Jam"`, `"2-4 jam"`).
- **Orang ≠ transaksi.** Setiap angka kunjungan wajib memisahkan `SUM(ISNULL(d.Jumlah_Tamu, 1))` dari `COUNT(*)`.
- **`N_EMI_VISITOR_Transaksi_Tamu.Tanggal_Kunjungan` bertipe `datetime`.** Perbandingan "hari ini" memakai rentang setengah terbuka `>= CAST(GETDATE() AS date) AND < DATEADD(day, 1, CAST(GETDATE() AS date))`. Jangan pernah membungkus kolom itu dengan `CAST()` di sisi kiri predikat.
- **Status permit memakai `VisitorPermitQueryService::permitStatusSql()`** — jangan menulis ulang kosakata status.
- **Approver visitor adalah kolam peran**: `Flag_Group='Y'`, `Group_Reference_Id` = `N_HRIS_Role_Functional.Id_Role`, `Approver_Karyawan_Kode` NULL.
- **Middleware tetap** `['auth', 'check.jabatan:visitorLaporanPage']`. Tidak ada izin baru, tidak ada baris menu baru.
- **Uji berjalan di DB development bersama** (`phpunit.xml` tidak mengalihkan `DB_CONNECTION`). Uji fitur wajib **read-only**; tidak ada `RefreshDatabase`, tidak ada `insert`, tidak ada `update`. Logika yang butuh data rekaan diuji lewat fungsi murni di uji Unit.
- **Setiap pembatasan daftar wajib dilaporkan** ke pengguna ("8 dari 23"). Pemotongan senyap terbaca sebagai "cuma segini".
- Balasan API memakai `ResponseHelper::success($data, $pesan)`.

---

## Peta Berkas

**Dibuat:**

| Berkas | Tanggung jawab |
|---|---|
| `app/Services/VISITOR/Workflow/Reporting/Dashboard/VisitorDashboardOperasionalService.php` | Baris 1–2: kueri kecil + agregator murni |
| `app/Services/VISITOR/Workflow/Reporting/Dashboard/VisitorDashboardAnalitikService.php` | Baris 3–5: agregasi berbasis `GROUP BY` |
| `app/Http/Controllers/VISITOR/Workflow/Reporting/VisitorDashboardController.php` | Dua aksi baca + cache + stempel waktu |
| `routes/VISITOR/Workflow/Reporting/VisitorDashboardApi.php` | Pendaftaran rute |
| `tests/Unit/VISITOR/Workflow/Reporting/VisitorDashboardAgregatorTest.php` | Penjaga logika hitung (rombongan, overdue, umur antrian) |
| `tests/Feature/VISITOR/Workflow/Reporting/VisitorDashboardApiTest.php` | Kontrak HTTP read-only + penjaga sumber kueri |
| `resources/js/Pages/VISITOR/Workflow/Reporting/Laporan/api/visitorDashboardApi.js` | Klien HTTP |
| `resources/js/Pages/VISITOR/Workflow/Reporting/Laporan/components/VisitorDashboardPanel.vue` | Tata letak, rentang, tombol segarkan, stempel waktu |
| `resources/js/Pages/VISITOR/Workflow/Reporting/Laporan/components/dashboard/DashboardKartuKeadaan.vue` | Baris 1 |
| `resources/js/Pages/VISITOR/Workflow/Reporting/Laporan/components/dashboard/DashboardDaftarTindakan.vue` | Baris 2 (dipakai dua kali) |
| `resources/js/Pages/VISITOR/Workflow/Reporting/Laporan/components/dashboard/DashboardBlokTren.vue` | Baris 3 |
| `resources/js/Pages/VISITOR/Workflow/Reporting/Laporan/components/dashboard/DashboardBlokPermit.vue` | Baris 4 |
| `resources/js/Pages/VISITOR/Workflow/Reporting/Laporan/components/dashboard/DashboardKesehatanData.vue` | Baris 5 |
| `resources/js/Pages/VISITOR/Workflow/Reporting/Laporan/components/dashboard/VisitorDashboardPanel.test.js` | Uji komponen |

**Disunting:**

| Berkas | Perubahan |
|---|---|
| `routes/web.php:181` | `require` berkas rute baru sesudah `VisitorExportStudio.php` |
| `resources/js/.../Laporan/laporanVisitor.vue` | Tab + panel `dashboard` sebagai tab pertama |
| `resources/js/.../Laporan/laporanVisitor.test.js` | Harapan daftar tab bertambah |
| `app/Http/Controllers/VISITOR/Workflow/Reporting/LaporanVisitorController.php:130-134` | Perbaikan `stats.sudah_keluar` / `stats.expired` yang selalu 0 |

---

### Task 1: Agregator murni operasional

Fungsi hitung dipisah dari kueri supaya bisa diuji dengan data rekaan tanpa menyentuh DB bersama.

**Files:**
- Create: `app/Services/VISITOR/Workflow/Reporting/Dashboard/VisitorDashboardOperasionalService.php`
- Test: `tests/Unit/VISITOR/Workflow/Reporting/VisitorDashboardAgregatorTest.php`

**Interfaces:**
- Consumes: —
- Produces:
  - `VisitorDashboardOperasionalService::agregasiDiDalamArea(iterable $rows, CarbonInterface $now): array`
  - `VisitorDashboardOperasionalService::agregasiAntrian(iterable $rows, CarbonInterface $now): array`
  - Tetapan `VisitorDashboardOperasionalService::BATAS_DAFTAR = 8`

Bentuk baris masukan `agregasiDiDalamArea` (satu objek per transaksi tamu yang sedang di dalam area):
`No_Transaksi`, `Nama_Tamu`, `Instansi`, `Nama_PIC`, `Jumlah_Tamu`, `Waktu_Aktual_Masuk`, `Tanggal_Kunjungan`.

Bentuk baris masukan `agregasiAntrian`:
`No_Transaksi`, `masuk_antrian`, `ada_pending` (0/1), `ada_kadaluarsa` (0/1).

- [ ] **Step 1: Tulis uji yang gagal**

Buat `tests/Unit/VISITOR/Workflow/Reporting/VisitorDashboardAgregatorTest.php`:

```php
<?php

namespace Tests\Unit\VISITOR\Workflow\Reporting;

use App\Services\VISITOR\Workflow\Reporting\Dashboard\VisitorDashboardOperasionalService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class VisitorDashboardAgregatorTest extends TestCase
{
    private function now(): Carbon
    {
        return Carbon::parse('2026-08-24 15:00:00');
    }

    private function baris(array $ubah = []): object
    {
        return (object) array_merge([
            'No_Transaksi' => 'VSR0826-0001',
            'Nama_Tamu' => 'Budi',
            'Instansi' => 'PT Contoh',
            'Nama_PIC' => 'Sari',
            'Jumlah_Tamu' => 1,
            'Waktu_Aktual_Masuk' => '2026-08-24 08:00:00',
            'Tanggal_Kunjungan' => '2026-08-24 00:00:00',
        ], $ubah);
    }

    /** Rombongan: satu transaksi berisi lima orang tetap satu transaksi. */
    public function test_rombongan_dihitung_sebagai_orang_bukan_transaksi(): void
    {
        $hasil = VisitorDashboardOperasionalService::agregasiDiDalamArea([
            $this->baris(['Jumlah_Tamu' => 5]),
            $this->baris(['No_Transaksi' => 'VSR0826-0002', 'Jumlah_Tamu' => 1]),
        ], $this->now());

        $this->assertSame(6, $hasil['orang']);
        $this->assertSame(2, $hasil['transaksi']);
    }

    /** Jumlah_Tamu kosong dihitung satu orang, bukan nol. */
    public function test_jumlah_tamu_kosong_dihitung_satu(): void
    {
        $hasil = VisitorDashboardOperasionalService::agregasiDiDalamArea([
            $this->baris(['Jumlah_Tamu' => null]),
        ], $this->now());

        $this->assertSame(1, $hasil['orang']);
    }

    /** Overdue = tanggal kunjungan sudah lewat, bukan lama di dalam area. */
    public function test_overdue_hanya_untuk_tanggal_yang_sudah_lewat(): void
    {
        $hasil = VisitorDashboardOperasionalService::agregasiDiDalamArea([
            $this->baris(['No_Transaksi' => 'KEMARIN', 'Tanggal_Kunjungan' => '2026-08-23 00:00:00', 'Jumlah_Tamu' => 3]),
            $this->baris(['No_Transaksi' => 'HARI_INI', 'Tanggal_Kunjungan' => '2026-08-24 00:00:00']),
        ], $this->now());

        $this->assertSame(1, $hasil['overdue']['transaksi']);
        $this->assertSame(3, $hasil['overdue']['orang']);
        $this->assertSame('KEMARIN', $hasil['daftar_perlu_ditutup'][0]['no_transaksi']);
    }

    /** Daftar dipotong, dan sisanya dilaporkan — bukan hilang diam-diam. */
    public function test_daftar_dipotong_dan_sisanya_dilaporkan(): void
    {
        $rows = [];
        for ($i = 1; $i <= 11; $i++) {
            $rows[] = $this->baris([
                'No_Transaksi' => 'VSR'.$i,
                'Tanggal_Kunjungan' => '2026-08-23 00:00:00',
                'Waktu_Aktual_Masuk' => '2026-08-23 '.str_pad((string) $i, 2, '0', STR_PAD_LEFT).':00:00',
            ]);
        }

        $hasil = VisitorDashboardOperasionalService::agregasiDiDalamArea($rows, $this->now());

        $this->assertCount(VisitorDashboardOperasionalService::BATAS_DAFTAR, $hasil['daftar_perlu_ditutup']);
        $this->assertSame(11, $hasil['daftar_perlu_ditutup_total']);
        $this->assertSame('VSR1', $hasil['daftar_perlu_ditutup'][0]['no_transaksi']);
    }

    /** Umur antrian dipecah tiga ember, dan kedaluwarsa bukan pending. */
    public function test_antrian_dipecah_umur_dan_kedaluwarsa_terpisah(): void
    {
        $hasil = VisitorDashboardOperasionalService::agregasiAntrian([
            (object) ['No_Transaksi' => 'A', 'masuk_antrian' => '2026-08-24 14:30:00', 'ada_pending' => 1, 'ada_kadaluarsa' => 0],
            (object) ['No_Transaksi' => 'B', 'masuk_antrian' => '2026-08-24 12:30:00', 'ada_pending' => 1, 'ada_kadaluarsa' => 0],
            (object) ['No_Transaksi' => 'C', 'masuk_antrian' => '2026-08-24 03:00:00', 'ada_pending' => 1, 'ada_kadaluarsa' => 0],
            (object) ['No_Transaksi' => 'D', 'masuk_antrian' => '2026-08-20 03:00:00', 'ada_pending' => 0, 'ada_kadaluarsa' => 1],
        ], $this->now());

        $this->assertSame(3, $hasil['pending']);
        $this->assertSame(1, $hasil['umur']['kurang_1_jam']);
        $this->assertSame(1, $hasil['umur']['1_sampai_4_jam']);
        $this->assertSame(1, $hasil['umur']['lebih_4_jam']);
        $this->assertSame(1, $hasil['kadaluarsa']);
        $this->assertSame('C', $hasil['daftar'][0]['no_transaksi']);
    }
}
```

- [ ] **Step 2: Jalankan uji, pastikan MERAH**

Run: `php artisan test tests/Unit/VISITOR/Workflow/Reporting/VisitorDashboardAgregatorTest.php`
Expected: FAIL — `Class "App\Services\...\VisitorDashboardOperasionalService" not found`.

Kelima uji **harus** pernah terlihat merah. Uji yang tidak pernah merah tidak menjaga apa pun.

- [ ] **Step 3: Tulis agregatornya**

Buat `app/Services/VISITOR/Workflow/Reporting/Dashboard/VisitorDashboardOperasionalService.php`:

```php
<?php

namespace App\Services\VISITOR\Workflow\Reporting\Dashboard;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Baris 1-2 dashboard Visitor: keadaan area dan antrian approval.
 *
 * Module: VISITOR · Submodule: Workflow · Feature: Reporting Dashboard.
 *
 * Sumber lifecycle tamu adalah tabel header + detail, BUKAN
 * N_VISITOR_Tamu_Efektif. Tabel efektif hanya salinan saat approval;
 * check-in/check-out tidak pernah menulisnya, jadi membacanya untuk
 * keadaan area menghasilkan nol tanpa error.
 */
class VisitorDashboardOperasionalService
{
    public const BATAS_DAFTAR = 8;

    /**
     * @param  iterable<object>  $rows  satu baris per transaksi tamu yang sedang di dalam area
     */
    public static function agregasiDiDalamArea(iterable $rows, CarbonInterface $now): array
    {
        $hariIni = $now->copy()->startOfDay();
        $orang = 0;
        $transaksi = 0;
        $overdueOrang = 0;
        $overdue = [];

        foreach ($rows as $row) {
            $jumlah = max((int) ($row->Jumlah_Tamu ?? 1), 1);
            $orang += $jumlah;
            $transaksi++;

            if (! self::sebelum($row->Tanggal_Kunjungan ?? null, $hariIni)) {
                continue;
            }

            $overdueOrang += $jumlah;
            $masuk = self::waktu($row->Waktu_Aktual_Masuk ?? null);
            $overdue[] = [
                'no_transaksi' => (string) ($row->No_Transaksi ?? ''),
                'nama' => (string) ($row->Nama_Tamu ?? '-'),
                'instansi' => (string) ($row->Instansi ?? '-'),
                'pic' => (string) ($row->Nama_PIC ?? '-'),
                'jumlah_tamu' => $jumlah,
                'masuk_pada' => $masuk?->toIso8601String(),
                'lama_menit' => $masuk ? $masuk->diffInMinutes($now) : null,
            ];
        }

        usort($overdue, fn ($a, $b) => ($b['lama_menit'] ?? -1) <=> ($a['lama_menit'] ?? -1));

        return [
            'orang' => $orang,
            'transaksi' => $transaksi,
            'overdue' => [
                'transaksi' => count($overdue),
                'orang' => $overdueOrang,
                'tertua_menit' => $overdue[0]['lama_menit'] ?? null,
            ],
            'daftar_perlu_ditutup' => array_slice($overdue, 0, self::BATAS_DAFTAR),
            'daftar_perlu_ditutup_total' => count($overdue),
        ];
    }

    /**
     * @param  iterable<object>  $rows  satu baris per transaksi yang belum diputus
     */
    public static function agregasiAntrian(iterable $rows, CarbonInterface $now): array
    {
        $umur = ['kurang_1_jam' => 0, '1_sampai_4_jam' => 0, 'lebih_4_jam' => 0];
        $kadaluarsa = 0;
        $pending = [];

        foreach ($rows as $row) {
            if ((int) ($row->ada_pending ?? 0) !== 1) {
                if ((int) ($row->ada_kadaluarsa ?? 0) === 1) {
                    $kadaluarsa++;
                }

                continue;
            }

            $sejak = self::waktu($row->masuk_antrian ?? null);
            $menit = $sejak ? $sejak->diffInMinutes($now) : 0;

            $ember = $menit < 60 ? 'kurang_1_jam' : ($menit < 240 ? '1_sampai_4_jam' : 'lebih_4_jam');
            $umur[$ember]++;

            $pending[] = [
                'no_transaksi' => (string) ($row->No_Transaksi ?? ''),
                'sejak' => $sejak?->toIso8601String(),
                'umur_menit' => $menit,
            ];
        }

        usort($pending, fn ($a, $b) => $b['umur_menit'] <=> $a['umur_menit']);

        return [
            'pending' => count($pending),
            'umur' => $umur,
            'kadaluarsa' => $kadaluarsa,
            'daftar' => array_slice($pending, 0, self::BATAS_DAFTAR),
            'daftar_total' => count($pending),
        ];
    }

    private static function waktu(mixed $nilai): ?Carbon
    {
        if (! $nilai) {
            return null;
        }

        try {
            return Carbon::parse((string) $nilai);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function sebelum(mixed $nilai, CarbonInterface $batas): bool
    {
        $waktu = self::waktu($nilai);

        return $waktu !== null && $waktu->startOfDay()->lt($batas);
    }
}
```

- [ ] **Step 4: Jalankan uji, pastikan HIJAU**

Run: `php artisan test tests/Unit/VISITOR/Workflow/Reporting/VisitorDashboardAgregatorTest.php`
Expected: PASS, 5 uji.

- [ ] **Step 5: Commit**

```bash
git add app/Services/VISITOR/Workflow/Reporting/Dashboard/VisitorDashboardOperasionalService.php tests/Unit/VISITOR/Workflow/Reporting/VisitorDashboardAgregatorTest.php
git commit -m "feat(VISITOR): agregator operasional dashboard visitor"
```

---

### Task 2: Kueri operasional dan endpoint `sekarang`

**Files:**
- Modify: `app/Services/VISITOR/Workflow/Reporting/Dashboard/VisitorDashboardOperasionalService.php`
- Create: `app/Http/Controllers/VISITOR/Workflow/Reporting/VisitorDashboardController.php`
- Create: `routes/VISITOR/Workflow/Reporting/VisitorDashboardApi.php`
- Modify: `routes/web.php` (sesudah baris 181)
- Create: `tests/Feature/VISITOR/Workflow/Reporting/VisitorDashboardApiTest.php`

**Interfaces:**
- Consumes: `VisitorDashboardOperasionalService::agregasiDiDalamArea/agregasiAntrian` (Task 1)
- Produces:
  - `VisitorDashboardOperasionalService::queryDiDalamArea(): \Illuminate\Database\Query\Builder`
  - `VisitorDashboardOperasionalService::ringkasan(?CarbonInterface $now = null): array`
    dengan kunci `di_dalam_area`, `terjadwal_belum_datang`, `antrian`
  - `GET /api/v1/visitor/dashboard/sekarang` → `{success, result: {di_dalam_area, terjadwal_belum_datang, antrian, dihitung_pada}}`

- [ ] **Step 1: Tulis uji fitur yang gagal**

Buat `tests/Feature/VISITOR/Workflow/Reporting/VisitorDashboardApiTest.php`:

```php
<?php

namespace Tests\Feature\VISITOR\Workflow\Reporting;

use App\Models\ESS\Employee\Models\User;
use App\Services\VISITOR\Workflow\Reporting\Dashboard\VisitorDashboardOperasionalService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Kontrak HTTP read-only, aman dijalankan pada database development bersama. */
class VisitorDashboardApiTest extends TestCase
{
    private const BASE = 'http://localhost/api/v1/visitor/dashboard';

    private function admin(): User
    {
        $userId = DB::table('HRIS_Page_Access')
            ->where('Flag_Aktif', 'Y')
            ->where('Jenis_page', 'visitorLaporanPage')
            ->whereNotNull('UserID_Web')
            ->value('UserID_Web');

        $user = $userId ? User::find($userId) : null;
        if (! $user) {
            $this->markTestSkipped('Tak ada user aktif dengan visitorLaporanPage di database development.');
        }

        return $user;
    }

    public function test_tanpa_autentikasi_ditolak(): void
    {
        $this->getJson(self::BASE.'/sekarang')->assertStatus(401);
    }

    public function test_sekarang_punya_kontrak_stabil_walau_data_kosong(): void
    {
        $this->actingAs($this->admin())
            ->getJson(self::BASE.'/sekarang')
            ->assertOk()
            ->assertJsonStructure([
                'result' => [
                    'di_dalam_area' => ['orang', 'transaksi', 'overdue', 'daftar_perlu_ditutup', 'daftar_perlu_ditutup_total'],
                    'terjadwal_belum_datang' => ['orang', 'transaksi'],
                    'antrian' => ['pending', 'umur', 'kadaluarsa', 'daftar', 'daftar_total'],
                    'dihitung_pada',
                ],
            ]);
    }

    /**
     * Penjaga langsung atas jebakan sumber data: keadaan area TIDAK BOLEH
     * dibaca dari N_VISITOR_Tamu_Efektif, yang tidak pernah ditulis oleh
     * check-in/check-out. Kalau kueri dipindah ke sana, uji ini merah.
     */
    public function test_kueri_keadaan_area_membaca_header_bukan_tabel_efektif(): void
    {
        $sql = app(VisitorDashboardOperasionalService::class)->queryDiDalamArea()->toSql();

        $this->assertStringContainsString('N_EMI_VISITOR_Transaksi_Tamu', $sql);
        $this->assertStringContainsString('N_EMI_VISITOR_Transaksi_Tamu_Detail', $sql);
        $this->assertStringNotContainsString('N_VISITOR_Tamu_Efektif', $sql);
    }

    /** Kolom teks bebas tidak boleh menyusup jadi jam jatuh tempo. */
    public function test_kueri_tidak_menyentuh_waktu_estimasi(): void
    {
        $sql = app(VisitorDashboardOperasionalService::class)->queryDiDalamArea()->toSql();

        $this->assertStringNotContainsString('Waktu_Estimasi_Kunjungan', $sql);
    }
}
```

- [ ] **Step 2: Jalankan uji, pastikan MERAH**

Run: `php artisan test tests/Feature/VISITOR/Workflow/Reporting/VisitorDashboardApiTest.php`
Expected: FAIL — rute `/api/v1/visitor/dashboard/sekarang` menghasilkan 404 dan `queryDiDalamArea()` belum ada.

- [ ] **Step 3: Tambahkan kueri ke layanan operasional**

Sisipkan ke `VisitorDashboardOperasionalService` (di atas blok `agregasi*`), plus `use` yang diperlukan:

```php
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
```

```php
    private const T_H = 'N_EMI_VISITOR_Transaksi_Tamu';

    private const T_D = 'N_EMI_VISITOR_Transaksi_Tamu_Detail';

    public function __construct(
        protected \App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService $permitQuery,
    ) {}

    /** Tamu yang sedang berada di dalam area. Sumber: header + detail. */
    public function queryDiDalamArea(): Builder
    {
        return DB::table(self::T_H.' as h')
            ->join(self::T_D.' as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 'h.Kode_Karyawan_PIC')
            ->where('h.Flag_Aktif', 'Y')
            ->where('h.Status_Kunjungan', 'CHECKED_IN')
            ->whereNull('d.Waktu_Aktual_Keluar')
            ->select(
                'h.No_Transaksi',
                'h.Tanggal_Kunjungan',
                'd.Nama_Lengkap_Tamu as Nama_Tamu',
                'd.Instansi_Atau_Asal as Instansi',
                'd.Jumlah_Tamu',
                'd.Waktu_Aktual_Masuk',
                DB::raw("ISNULL(k.Nama, '-') as Nama_PIC"),
            );
    }

    /** Terjadwal hari ini tetapi belum check-in. Rentang setengah terbuka: kolomnya datetime. */
    public function queryTerjadwalBelumDatang(): Builder
    {
        return DB::table(self::T_H.' as h')
            ->join(self::T_D.' as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->where('h.Flag_Aktif', 'Y')
            ->where('h.Status_Kunjungan', 'TERJADWAL')
            ->whereRaw('h.Tanggal_Kunjungan >= CAST(GETDATE() AS date)')
            ->whereRaw('h.Tanggal_Kunjungan < DATEADD(day, 1, CAST(GETDATE() AS date))')
            ->whereNull('d.Waktu_Aktual_Masuk')
            ->select('h.No_Transaksi', 'd.Jumlah_Tamu');
    }

    /**
     * Transaksi visitor yang belum diputus, satu baris per No_Transaksi.
     *
     * Penyaring ke ranah visitor lewat EXISTS ke tabel transaksi, bukan lewat
     * awalan nomor: awalan adalah kesepakatan penamaan yang bisa berubah,
     * bukan kunci relasi.
     */
    public function queryAntrianApproval(): Builder
    {
        return DB::table('HRIS_Approval_Request as ar')
            ->whereRaw("EXISTS (SELECT 1 FROM N_EMI_VISITOR_Transaksi_Tamu t WHERE t.No_Transaksi = ar.No_Transaksi AND t.Flag_Aktif = 'Y')
                     OR EXISTS (SELECT 1 FROM N_EMI_VISITOR_Transaksi_Permit p WHERE p.No_Transaksi = ar.No_Transaksi AND p.Flag_Aktif = 'Y')")
            ->groupBy('ar.No_Transaksi')
            ->havingRaw("MAX(CASE WHEN ar.Flag_Approval = 'T' THEN 1 ELSE 0 END) = 0")
            ->havingRaw('MAX(CASE WHEN ar.Flag_Approval IS NULL THEN 1 ELSE 0 END) = 1')
            ->select(
                'ar.No_Transaksi',
                DB::raw('MIN(ar.created_at) as masuk_antrian'),
                DB::raw('MAX(CASE WHEN ar.Flag_Approval IS NULL AND (ar.Expired_At IS NULL OR ar.Expired_At >= GETDATE()) THEN 1 ELSE 0 END) as ada_pending'),
                DB::raw('MAX(CASE WHEN ar.Flag_Approval IS NULL AND ar.Expired_At IS NOT NULL AND ar.Expired_At < GETDATE() THEN 1 ELSE 0 END) as ada_kadaluarsa'),
            );
    }

    public function ringkasan(?CarbonInterface $now = null): array
    {
        $now = $now ? $now->copy() : Carbon::now();

        $terjadwal = $this->queryTerjadwalBelumDatang()->get();

        return [
            'di_dalam_area' => self::agregasiDiDalamArea($this->queryDiDalamArea()->get(), $now),
            'terjadwal_belum_datang' => [
                'transaksi' => $terjadwal->count(),
                'orang' => (int) $terjadwal->sum(fn ($r) => max((int) ($r->Jumlah_Tamu ?? 1), 1)),
            ],
            'antrian' => self::agregasiAntrian($this->queryAntrianApproval()->get(), $now),
        ];
    }
```

- [ ] **Step 4: Buat controller**

Buat `app/Http/Controllers/VISITOR/Workflow/Reporting/VisitorDashboardController.php`:

```php
<?php

namespace App\Http\Controllers\VISITOR\Workflow\Reporting;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\VISITOR\Workflow\Reporting\Dashboard\VisitorDashboardOperasionalService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Dashboard admin Visitor: dua endpoint baca yang dipisah menurut sifat
 * kesegarannya, bukan menurut blok layar.
 */
class VisitorDashboardController extends Controller
{
    private const KUNCI_SEKARANG = 'visitor.dashboard.sekarang';

    private const UMUR_SEKARANG = 60;

    public function __construct(
        protected VisitorDashboardOperasionalService $operasional,
    ) {}

    public function sekarang(Request $request)
    {
        try {
            if ($request->boolean('segarkan')) {
                Cache::forget(self::KUNCI_SEKARANG);
            }

            $hasil = Cache::remember(self::KUNCI_SEKARANG, self::UMUR_SEKARANG, function () {
                return array_merge(
                    $this->operasional->ringkasan(),
                    ['dihitung_pada' => Carbon::now()->toIso8601String()],
                );
            });

            return ResponseHelper::success($hasil, 'Berhasil');
        } catch (\Exception $e) {
            return ResponseHelper::error('Gagal memuat keadaan sekarang: '.$e->getMessage());
        }
    }
}
```

- [ ] **Step 5: Daftarkan rute**

Buat `routes/VISITOR/Workflow/Reporting/VisitorDashboardApi.php`:

```php
<?php

use App\Http\Controllers\VISITOR\Workflow\Reporting\VisitorDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'check.jabatan:visitorLaporanPage'])
    ->prefix('api/v1/visitor/dashboard')
    ->group(function () {
        Route::get('/sekarang', [VisitorDashboardController::class, 'sekarang']);
    });
```

Lalu sisipkan di `routes/web.php`, tepat sesudah baris `require ... VisitorExportStudio.php;`:

```php
    require __DIR__ . '/VISITOR/Workflow/Reporting/VisitorDashboardApi.php';
```

- [ ] **Step 6: Bersihkan cache rute dan jalankan uji**

Cache rute per-worktree bisa lebih tua daripada berkas rute baru dan memunculkan 404 yang menyesatkan.

Run:
```bash
php artisan route:clear
php artisan test tests/Feature/VISITOR/Workflow/Reporting/VisitorDashboardApiTest.php
```
Expected: PASS, 4 uji (atau `skipped` pada dua uji yang butuh user bila DB dev tidak punya pemegang `visitorLaporanPage`).

- [ ] **Step 7: Commit**

```bash
git add app/Services/VISITOR/Workflow/Reporting/Dashboard app/Http/Controllers/VISITOR/Workflow/Reporting/VisitorDashboardController.php routes/VISITOR/Workflow/Reporting/VisitorDashboardApi.php routes/web.php tests/Feature/VISITOR/Workflow/Reporting/VisitorDashboardApiTest.php
git commit -m "feat(VISITOR): endpoint keadaan sekarang dashboard visitor"
```

---

### Task 3: Rincian daftar tindakan

Kartu sudah punya angka; daftarnya masih tanpa nama. Melengkapi identitas dilakukan lewat kueri kecil berbatas `BATAS_DAFTAR` baris, bukan dengan membesarkan kueri agregat.

**Files:**
- Modify: `app/Services/VISITOR/Workflow/Reporting/Dashboard/VisitorDashboardOperasionalService.php`
- Modify: `tests/Feature/VISITOR/Workflow/Reporting/VisitorDashboardApiTest.php`

**Interfaces:**
- Produces: `VisitorDashboardOperasionalService::lengkapiAntrian(array $daftar): array` — menambah kunci `jenis`, `judul`, `pemohon`, `approver` pada tiap elemen.

- [ ] **Step 1: Tulis uji yang gagal**

Tambahkan ke `VisitorDashboardApiTest`:

```php
    /** Setiap baris antrian membawa identitas, bukan hanya nomor transaksi. */
    public function test_daftar_antrian_membawa_identitas(): void
    {
        $daftar = $this->actingAs($this->admin())
            ->getJson(self::BASE.'/sekarang')
            ->assertOk()
            ->json('result.antrian.daftar');

        if ($daftar === []) {
            $this->markTestSkipped('Tak ada antrian approval visitor di database development.');
        }

        foreach ($daftar as $baris) {
            $this->assertArrayHasKey('jenis', $baris);
            $this->assertContains($baris['jenis'], ['TAMU', 'PERMIT']);
            $this->assertArrayHasKey('judul', $baris);
            $this->assertArrayHasKey('pemohon', $baris);
            $this->assertArrayHasKey('approver', $baris);
        }
    }
```

Catatan: `markTestSkipped` di sini sah karena keadaan dev memang bisa kosong — tetapi **jangan** membiarkannya menjadi satu-satunya penjaga. Bentuk keluarannya juga dijaga uji Unit di Step 3.

- [ ] **Step 2: Jalankan uji, pastikan MERAH atau SKIPPED**

Run: `php artisan test tests/Feature/VISITOR/Workflow/Reporting/VisitorDashboardApiTest.php --filter=identitas`

Bila DB dev sedang punya antrian (saat rencana ini ditulis ada 3 permit pending), hasilnya **FAIL** karena kunci `jenis` belum ada. Bila `SKIPPED`, lanjutkan — uji Unit berikutnya yang menjadi penjaganya.

- [ ] **Step 3: Tulis uji Unit untuk penggabungan identitas**

Tambahkan ke `tests/Unit/VISITOR/Workflow/Reporting/VisitorDashboardAgregatorTest.php`:

```php
    /** Identitas ditempelkan ke baris yang cocok; yang tak ditemukan tetap tampil dengan strip. */
    public function test_penggabungan_identitas_tidak_menjatuhkan_baris(): void
    {
        $daftar = [
            ['no_transaksi' => 'A', 'sejak' => null, 'umur_menit' => 10],
            ['no_transaksi' => 'B', 'sejak' => null, 'umur_menit' => 5],
        ];

        $meta = [
            'A' => ['jenis' => 'TAMU', 'judul' => 'Budi', 'pemohon' => 'Sari'],
        ];

        $hasil = VisitorDashboardOperasionalService::gabungIdentitas($daftar, $meta, ['A' => 'SECURITY']);

        $this->assertSame('TAMU', $hasil[0]['jenis']);
        $this->assertSame('SECURITY', $hasil[0]['approver']);
        $this->assertCount(2, $hasil, 'Baris tanpa identitas tidak boleh hilang diam-diam.');
        $this->assertSame('-', $hasil[1]['judul']);
        $this->assertSame('-', $hasil[1]['approver']);
    }
```

- [ ] **Step 4: Jalankan uji Unit, pastikan MERAH**

Run: `php artisan test tests/Unit/VISITOR/Workflow/Reporting/VisitorDashboardAgregatorTest.php --filter=identitas`
Expected: FAIL — `gabungIdentitas()` belum ada.

- [ ] **Step 5: Implementasi**

Tambahkan ke `VisitorDashboardOperasionalService`:

```php
    /**
     * @param  array<int, array<string, mixed>>  $daftar
     * @param  array<string, array<string, string>>  $meta
     * @param  array<string, string>  $approver
     */
    public static function gabungIdentitas(array $daftar, array $meta, array $approver): array
    {
        return array_map(function (array $baris) use ($meta, $approver) {
            $no = (string) ($baris['no_transaksi'] ?? '');
            $identitas = $meta[$no] ?? [];

            return array_merge($baris, [
                'jenis' => $identitas['jenis'] ?? '-',
                'judul' => $identitas['judul'] ?? '-',
                'pemohon' => $identitas['pemohon'] ?? '-',
                'approver' => $approver[$no] ?? '-',
            ]);
        }, $daftar);
    }

    /** @param array<int, array<string, mixed>> $daftar */
    public function lengkapiAntrian(array $daftar): array
    {
        $nomor = array_values(array_filter(array_column($daftar, 'no_transaksi')));

        if ($nomor === []) {
            return $daftar;
        }

        $meta = [];

        DB::table(self::T_H.' as h')
            ->join(self::T_D.' as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 'h.Kode_Karyawan_PIC')
            ->whereIn('h.No_Transaksi', $nomor)
            ->select('h.No_Transaksi', 'd.Nama_Lengkap_Tamu as judul', DB::raw("ISNULL(k.Nama, '-') as pemohon"))
            ->get()
            ->each(function ($row) use (&$meta) {
                $meta[$row->No_Transaksi] = ['jenis' => 'TAMU', 'judul' => (string) $row->judul, 'pemohon' => (string) $row->pemohon];
            });

        DB::table('N_EMI_VISITOR_Transaksi_Permit as h')
            ->join('N_EMI_VISITOR_Transaksi_Permit_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 'h.Kode_Karyawan_Pemohon')
            ->whereIn('h.No_Transaksi', $nomor)
            ->select(
                'h.No_Transaksi',
                DB::raw($this->permitQuery->platNomorSql('d').' as judul'),
                DB::raw("ISNULL(k.Nama, '-') as pemohon"),
            )
            ->get()
            ->each(function ($row) use (&$meta) {
                $meta[$row->No_Transaksi] = ['jenis' => 'PERMIT', 'judul' => (string) $row->judul, 'pemohon' => (string) $row->pemohon];
            });

        $approver = DB::table('HRIS_Approval_Request as ar')
            ->leftJoin('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ar.Group_Reference_Id')
            ->leftJoin('Karyawan as ka', 'ka.Kode_Karyawan', '=', 'ar.Approver_Karyawan_Kode')
            ->whereIn('ar.No_Transaksi', $nomor)
            ->whereNull('ar.Flag_Approval')
            ->select('ar.No_Transaksi', DB::raw("ISNULL(rf.Role_Name, ISNULL(ka.Nama, '-')) as nama"))
            ->get()
            ->groupBy('No_Transaksi')
            ->map(fn ($rows) => (string) $rows->first()->nama)
            ->all();

        return self::gabungIdentitas($daftar, $meta, $approver);
    }
```

Lalu di `ringkasan()`, ganti baris `'antrian' => ...` menjadi:

```php
        $antrian = self::agregasiAntrian($this->queryAntrianApproval()->get(), $now);
        $antrian['daftar'] = $this->lengkapiAntrian($antrian['daftar']);
```

dan pakai `'antrian' => $antrian,` pada larik yang dikembalikan.

- [ ] **Step 6: Jalankan kedua berkas uji, pastikan HIJAU**

Run:
```bash
php artisan test tests/Unit/VISITOR/Workflow/Reporting/VisitorDashboardAgregatorTest.php
php artisan test tests/Feature/VISITOR/Workflow/Reporting/VisitorDashboardApiTest.php
```
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Services/VISITOR/Workflow/Reporting/Dashboard tests/Unit/VISITOR/Workflow/Reporting tests/Feature/VISITOR/Workflow/Reporting
git commit -m "feat(VISITOR): identitas pada daftar antrian dashboard"
```

---

### Task 4: Layanan analitik dan endpoint `analitik`

**Files:**
- Create: `app/Services/VISITOR/Workflow/Reporting/Dashboard/VisitorDashboardAnalitikService.php`
- Modify: `app/Http/Controllers/VISITOR/Workflow/Reporting/VisitorDashboardController.php`
- Modify: `routes/VISITOR/Workflow/Reporting/VisitorDashboardApi.php`
- Modify: `tests/Feature/VISITOR/Workflow/Reporting/VisitorDashboardApiTest.php`

**Interfaces:**
- Produces:
  - `VisitorDashboardAnalitikService::ringkasan(string $dari, string $sampai): array` dengan kunci `tren`, `sebaran_jam`, `permit_kategori`, `permit_akan_kadaluarsa`, `kesehatan_data`
  - `GET /api/v1/visitor/dashboard/analitik?dari=YYYY-MM-DD&sampai=YYYY-MM-DD`

- [ ] **Step 1: Tulis uji yang gagal**

Tambahkan ke `VisitorDashboardApiTest`:

```php
    public function test_analitik_punya_kontrak_stabil(): void
    {
        $this->actingAs($this->admin())
            ->getJson(self::BASE.'/analitik?dari=2026-08-01&sampai=2026-08-24')
            ->assertOk()
            ->assertJsonStructure([
                'result' => [
                    'tren', 'sebaran_jam', 'permit_kategori', 'permit_akan_kadaluarsa',
                    'kesehatan_data' => ['total', 'sudah_masuk', 'tanpa_waktu_keluar', 'tanpa_identitas', 'tanpa_hp', 'persen_tanpa_waktu_keluar'],
                    'dihitung_pada',
                ],
            ]);
    }

    /** Rentang kosong bukan error: blok kosong, tetap 200. */
    public function test_rentang_tanpa_data_tetap_berhasil(): void
    {
        $result = $this->actingAs($this->admin())
            ->getJson(self::BASE.'/analitik?dari=1990-01-01&sampai=1990-01-02')
            ->assertOk()
            ->json('result');

        $this->assertSame([], $result['tren']);
        $this->assertSame(0, $result['kesehatan_data']['total']);
        $this->assertSame(0.0, $result['kesehatan_data']['persen_tanpa_waktu_keluar']);
    }

    /** Rentang terbalik atau tak sah dirapikan, bukan diteruskan ke SQL. */
    public function test_rentang_tidak_sah_dirapikan(): void
    {
        $this->actingAs($this->admin())
            ->getJson(self::BASE.'/analitik?dari=bukan-tanggal&sampai=')
            ->assertOk();
    }
```

- [ ] **Step 2: Jalankan uji, pastikan MERAH**

Run: `php artisan test tests/Feature/VISITOR/Workflow/Reporting/VisitorDashboardApiTest.php`
Expected: FAIL — 404 pada `/analitik`.

- [ ] **Step 3: Tulis layanan analitik**

Buat `app/Services/VISITOR/Workflow/Reporting/Dashboard/VisitorDashboardAnalitikService.php`:

```php
<?php

namespace App\Services\VISITOR\Workflow\Reporting\Dashboard;

use Illuminate\Support\Facades\DB;

/**
 * Baris 3-5 dashboard Visitor: tren, jam sibuk, permit, kesehatan data.
 *
 * Module: VISITOR · Submodule: Workflow · Feature: Reporting Dashboard.
 *
 * Tren dan jam sibuk membaca header + detail karena waktu aktual hanya
 * benar di sana. Permit membaca N_VISITOR_Permit_Efektif karena tanggal
 * berlakunya memang ditulis ke sana dan tidak berubah setelahnya.
 */
class VisitorDashboardAnalitikService
{
    private const T_H = 'N_EMI_VISITOR_Transaksi_Tamu';

    private const T_D = 'N_EMI_VISITOR_Transaksi_Tamu_Detail';

    public const HARI_KADALUARSA = 30;

    public const BATAS_DAFTAR = 8;

    public function ringkasan(string $dari, string $sampai): array
    {
        return [
            'tren' => $this->tren($dari, $sampai),
            'sebaran_jam' => $this->sebaranJam($dari, $sampai),
            'permit_kategori' => $this->permitPerKategori(),
            'permit_akan_kadaluarsa' => $this->permitAkanKadaluarsa(),
            'kesehatan_data' => $this->kesehatanData($dari, $sampai),
        ];
    }

    /** Dua seri terpisah: rombongan membuat transaksi dan orang jauh berbeda. */
    public function tren(string $dari, string $sampai): array
    {
        return $this->dalamRentang(DB::table(self::T_H.' as h'), $dari, $sampai)
            ->join(self::T_D.' as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->where('h.Flag_Aktif', 'Y')
            ->groupBy(DB::raw('CAST(h.Tanggal_Kunjungan AS date)'))
            ->orderBy(DB::raw('CAST(h.Tanggal_Kunjungan AS date)'))
            ->select(
                DB::raw('CAST(h.Tanggal_Kunjungan AS date) as tanggal'),
                DB::raw('COUNT(*) as transaksi'),
                DB::raw('SUM(CASE WHEN ISNULL(d.Jumlah_Tamu, 1) < 1 THEN 1 ELSE ISNULL(d.Jumlah_Tamu, 1) END) as orang'),
            )
            ->get()
            ->map(fn ($r) => [
                'tanggal' => (string) $r->tanggal,
                'transaksi' => (int) $r->transaksi,
                'orang' => (int) $r->orang,
            ])
            ->all();
    }

    /**
     * Sebaran jam kedatangan. Dikelompokkan per tanggal + jam lalu dipetakan ke
     * hari di PHP, supaya hasilnya tidak bergantung pada SET DATEFIRST server.
     */
    public function sebaranJam(string $dari, string $sampai): array
    {
        return $this->dalamRentang(DB::table(self::T_H.' as h'), $dari, $sampai)
            ->join(self::T_D.' as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->where('h.Flag_Aktif', 'Y')
            ->whereNotNull('d.Waktu_Aktual_Masuk')
            ->groupBy(DB::raw('CAST(h.Tanggal_Kunjungan AS date)'), DB::raw('DATEPART(hour, d.Waktu_Aktual_Masuk)'))
            ->select(
                DB::raw('CAST(h.Tanggal_Kunjungan AS date) as tanggal'),
                DB::raw('DATEPART(hour, d.Waktu_Aktual_Masuk) as jam'),
                DB::raw('COUNT(*) as jumlah'),
            )
            ->get()
            ->map(fn ($r) => [
                'tanggal' => (string) $r->tanggal,
                'jam' => (int) $r->jam,
                'jumlah' => (int) $r->jumlah,
            ])
            ->all();
    }

    public function permitPerKategori(): array
    {
        return DB::table('N_VISITOR_Permit_Efektif')
            ->where('Status_Efektif', 'AKTIF')
            ->whereRaw('(Tanggal_Kadaluarsa IS NULL OR Tanggal_Kadaluarsa >= CAST(GETDATE() AS date))')
            ->groupBy('Nama_Kategori_Permit')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->select(DB::raw("ISNULL(Nama_Kategori_Permit, '-') as kategori"), DB::raw('COUNT(*) as jumlah'))
            ->get()
            ->map(fn ($r) => ['kategori' => (string) $r->kategori, 'jumlah' => (int) $r->jumlah])
            ->all();
    }

    /** @return array{daftar: array, total: int} */
    public function permitAkanKadaluarsa(): array
    {
        $query = DB::table('N_VISITOR_Permit_Efektif')
            ->where('Status_Efektif', 'AKTIF')
            ->whereNotNull('Tanggal_Kadaluarsa')
            ->whereRaw('Tanggal_Kadaluarsa >= CAST(GETDATE() AS date)')
            ->whereRaw('Tanggal_Kadaluarsa <= DATEADD(day, ?, CAST(GETDATE() AS date))', [self::HARI_KADALUARSA]);

        $total = (clone $query)->count();

        $daftar = $query
            ->orderBy('Tanggal_Kadaluarsa')
            ->limit(self::BATAS_DAFTAR)
            ->select(
                'No_Transaksi', 'No_Plat_Kendaraan', 'Nama_Kategori_Permit',
                'Nama_Pemohon', 'Departemen', 'Tanggal_Kadaluarsa',
                DB::raw('DATEDIFF(day, CAST(GETDATE() AS date), Tanggal_Kadaluarsa) as sisa_hari'),
            )
            ->get()
            ->map(fn ($r) => [
                'no_transaksi' => (string) $r->No_Transaksi,
                'plat' => (string) ($r->No_Plat_Kendaraan ?? '-'),
                'kategori' => (string) ($r->Nama_Kategori_Permit ?? '-'),
                'pemohon' => (string) ($r->Nama_Pemohon ?? '-'),
                'departemen' => (string) ($r->Departemen ?? '-'),
                'kadaluarsa' => (string) $r->Tanggal_Kadaluarsa,
                'sisa_hari' => (int) $r->sisa_hari,
            ])
            ->all();

        return ['daftar' => $daftar, 'total' => $total];
    }

    public function kesehatanData(string $dari, string $sampai): array
    {
        $row = $this->dalamRentang(DB::table(self::T_H.' as h'), $dari, $sampai)
            ->join(self::T_D.' as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->where('h.Flag_Aktif', 'Y')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN d.Waktu_Aktual_Masuk IS NOT NULL THEN 1 ELSE 0 END) as sudah_masuk')
            ->selectRaw('SUM(CASE WHEN d.Waktu_Aktual_Masuk IS NOT NULL AND d.Waktu_Aktual_Keluar IS NULL THEN 1 ELSE 0 END) as tanpa_waktu_keluar')
            ->selectRaw("SUM(CASE WHEN d.No_Identitas IS NULL OR LTRIM(RTRIM(d.No_Identitas)) = '' THEN 1 ELSE 0 END) as tanpa_identitas")
            ->selectRaw("SUM(CASE WHEN d.Nomor_Handphone IS NULL OR LTRIM(RTRIM(d.Nomor_Handphone)) = '' THEN 1 ELSE 0 END) as tanpa_hp")
            ->first();

        $sudahMasuk = (int) ($row->sudah_masuk ?? 0);
        $tanpaKeluar = (int) ($row->tanpa_waktu_keluar ?? 0);

        return [
            'total' => (int) ($row->total ?? 0),
            'sudah_masuk' => $sudahMasuk,
            'tanpa_waktu_keluar' => $tanpaKeluar,
            'tanpa_identitas' => (int) ($row->tanpa_identitas ?? 0),
            'tanpa_hp' => (int) ($row->tanpa_hp ?? 0),
            'persen_tanpa_waktu_keluar' => $sudahMasuk > 0 ? round($tanpaKeluar / $sudahMasuk * 100, 1) : 0.0,
        ];
    }

    /** Rentang setengah terbuka: Tanggal_Kunjungan bertipe datetime, bukan date. */
    private function dalamRentang(\Illuminate\Database\Query\Builder $q, string $dari, string $sampai): \Illuminate\Database\Query\Builder
    {
        return $q
            ->whereRaw('h.Tanggal_Kunjungan >= ?', [$dari])
            ->whereRaw('h.Tanggal_Kunjungan < DATEADD(day, 1, CAST(? AS date))', [$sampai]);
    }
}
```

- [ ] **Step 4: Tambahkan aksi controller dan rute**

Tambahkan ke `VisitorDashboardController` (plus `use App\Services\VISITOR\Workflow\Reporting\Dashboard\VisitorDashboardAnalitikService;`):

```php
    private const UMUR_ANALITIK = 600;

    public function analitik(Request $request, VisitorDashboardAnalitikService $analitik)
    {
        try {
            [$dari, $sampai] = $this->rentang($request);
            $kunci = "visitor.dashboard.analitik.{$dari}.{$sampai}";

            if ($request->boolean('segarkan')) {
                Cache::forget($kunci);
            }

            $hasil = Cache::remember($kunci, self::UMUR_ANALITIK, function () use ($analitik, $dari, $sampai) {
                return array_merge(
                    $analitik->ringkasan($dari, $sampai),
                    ['dihitung_pada' => Carbon::now()->toIso8601String(), 'dari' => $dari, 'sampai' => $sampai],
                );
            });

            return ResponseHelper::success($hasil, 'Berhasil');
        } catch (\Exception $e) {
            return ResponseHelper::error('Gagal memuat analitik: '.$e->getMessage());
        }
    }

    /** @return array{0: string, 1: string} */
    private function rentang(Request $request): array
    {
        $dari = $this->tanggal($request->input('dari'), Carbon::today()->subDays(13));
        $sampai = $this->tanggal($request->input('sampai'), Carbon::today());

        if ($dari->gt($sampai)) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        return [$dari->toDateString(), $sampai->toDateString()];
    }

    private function tanggal(mixed $nilai, Carbon $baku): Carbon
    {
        if (! is_string($nilai) || trim($nilai) === '') {
            return $baku->copy();
        }

        try {
            return Carbon::parse($nilai)->startOfDay();
        } catch (\Throwable) {
            return $baku->copy();
        }
    }
```

Tambahkan rute di `VisitorDashboardApi.php`, di dalam grup yang sama:

```php
        Route::get('/analitik', [VisitorDashboardController::class, 'analitik']);
```

- [ ] **Step 5: Jalankan uji, pastikan HIJAU**

Run:
```bash
php artisan route:clear
php artisan test tests/Feature/VISITOR/Workflow/Reporting/VisitorDashboardApiTest.php
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/VISITOR/Workflow/Reporting/Dashboard app/Http/Controllers/VISITOR/Workflow/Reporting/VisitorDashboardController.php routes/VISITOR/Workflow/Reporting/VisitorDashboardApi.php tests/Feature/VISITOR/Workflow/Reporting/VisitorDashboardApiTest.php
git commit -m "feat(VISITOR): endpoint analitik dashboard visitor"
```

---

### Task 5: Perbaikan `stats` laporan tamu yang selalu nol

`getLaporanTamu()` menghitung `sudah_keluar` dan `expired` dari `N_VISITOR_Tamu_Efektif.Status_Efektif`, kolom yang tidak pernah ditulis check-out. Kedua angka itu **selalu 0**. Dibiarkan, dua tab pada satu halaman akan menampilkan angka yang bertentangan.

**Files:**
- Modify: `app/Http/Controllers/VISITOR/Workflow/Reporting/LaporanVisitorController.php:130-134`
- Modify: `tests/Feature/VISITOR/Workflow/Reporting/VisitorDashboardApiTest.php`

**Interfaces:**
- Consumes: —
- Produces: kontrak `stats` tidak berubah bentuknya; hanya sumbernya yang benar.

- [ ] **Step 1: Tulis uji yang gagal**

Tambahkan ke `VisitorDashboardApiTest`:

```php
    /**
     * stats.sudah_keluar dibaca dari tabel detail, bukan dari salinan efektif
     * yang tak pernah ditulis check-out. Konsisten dengan dashboard.
     */
    public function test_stats_laporan_tamu_konsisten_dengan_sumber_lifecycle(): void
    {
        $dari = '2026-01-01';
        $sampai = '2026-12-31';

        $stats = $this->actingAs($this->admin())
            ->getJson("http://localhost/laporanvisitor-tamu?date_from={$dari}&date_to={$sampai}")
            ->assertOk()
            ->json('stats');

        $sebenarnya = DB::table('N_EMI_VISITOR_Transaksi_Tamu as h')
            ->join('N_EMI_VISITOR_Transaksi_Tamu_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->where('h.Flag_Aktif', 'Y')
            ->whereRaw('h.Tanggal_Kunjungan >= ?', [$dari])
            ->whereRaw('h.Tanggal_Kunjungan < DATEADD(day, 1, CAST(? AS date))', [$sampai])
            ->whereNotNull('d.Waktu_Aktual_Keluar')
            ->count();

        $this->assertSame($sebenarnya, (int) $stats['sudah_keluar']);
    }
```

- [ ] **Step 2: Jalankan uji**

Run: `php artisan test tests/Feature/VISITOR/Workflow/Reporting/VisitorDashboardApiTest.php --filter=stats_laporan_tamu`

Bila DB dev punya minimal satu tamu yang sudah check-out, hasilnya **FAIL** (0 ≠ N). Bila hasilnya sudah PASS, itu karena kedua sisi kebetulan 0 — **uji yang tidak menguji apa pun**. Dalam kasus itu, buktikan cacatnya dulu dengan menjalankan perbandingan berikut, lalu tetap lanjutkan perbaikan:

```bash
php artisan tinker --execute="echo json_encode([
  'efektif_checked_out' => DB::table('N_VISITOR_Tamu_Efektif')->where('Status_Efektif','CHECKED_OUT')->count(),
  'detail_ada_waktu_keluar' => DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->whereNotNull('Waktu_Aktual_Keluar')->count(),
]);"
```

- [ ] **Step 3: Perbaiki sumbernya**

Ganti blok `$stats` di `LaporanVisitorController::getLaporanTamu()`:

```php
            $statsBase = DB::table(self::T_TAMU_H.' as h')
                ->join(self::T_TAMU_D.' as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
                ->where('h.Flag_Aktif', 'Y')
                ->whereRaw('h.Tanggal_Kunjungan >= ?', [$dateFrom])
                ->whereRaw('h.Tanggal_Kunjungan < DATEADD(day, 1, CAST(? AS date))', [$dateTo]);

            // Sumber lifecycle adalah header + detail. N_VISITOR_Tamu_Efektif hanya
            // salinan saat approval - check-in/check-out tidak pernah menulisnya,
            // sehingga membacanya di sini selalu menghasilkan nol.
            $stats = [
                'total_tamu' => (clone $statsBase)->count(),
                'sudah_keluar' => (clone $statsBase)->whereNotNull('d.Waktu_Aktual_Keluar')->count(),
                'expired' => (clone $statsBase)
                    ->whereNull('d.Waktu_Aktual_Masuk')
                    ->whereRaw('h.Tanggal_Kunjungan < CAST(GETDATE() AS date)')
                    ->where('h.Status_Kunjungan', '<>', 'DIBATALKAN')
                    ->count(),
            ];
```

`expired` kini berarti apa yang sebenarnya dilihat admin: terjadwal, tanggalnya sudah lewat, tamunya tidak pernah datang.

- [ ] **Step 4: Jalankan uji, pastikan HIJAU**

Run: `php artisan test tests/Feature/VISITOR/Workflow/Reporting/VisitorDashboardApiTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/VISITOR/Workflow/Reporting/LaporanVisitorController.php tests/Feature/VISITOR/Workflow/Reporting/VisitorDashboardApiTest.php
git commit -m "fix(VISITOR): stats laporan tamu dibaca dari sumber lifecycle yang benar"
```

---

### Task 6: Klien API dan pendaftaran tab

**Files:**
- Create: `resources/js/Pages/VISITOR/Workflow/Reporting/Laporan/api/visitorDashboardApi.js`
- Modify: `resources/js/Pages/VISITOR/Workflow/Reporting/Laporan/laporanVisitor.vue`
- Modify: `resources/js/Pages/VISITOR/Workflow/Reporting/Laporan/laporanVisitor.test.js`

**Interfaces:**
- Produces: `ambilSekarang({ segarkan })`, `ambilAnalitik({ dari, sampai, segarkan })` — keduanya mengembalikan `result` dari balasan.

- [ ] **Step 1: Perbarui uji tab yang akan gagal**

`laporanVisitor.test.js` saat ini menuntut `['harian', 'studio']`. Ubah baris harapan itu menjadi:

```javascript
        expect(wrapper.vm.tabs.map((tab) => tab.key)).toEqual(['dashboard', 'harian', 'studio']);
        expect(wrapper.find('[data-test="visitor-dashboard"]').exists()).toBe(true);
```

dan tambahkan stub panel di bagian atas berkas:

```javascript
vi.mock('./components/VisitorDashboardPanel.vue', () => ({
    default: { name: 'VisitorDashboardPanel', template: '<div data-test="visitor-dashboard" />' },
}));
```

serta tambahkan slot panel baru ke `VisitorWorkspaceStub`:

```javascript
    template: '<main><slot name="panel-dashboard"/><slot name="panel-harian"/><slot name="panel-studio"/></main>',
```

- [ ] **Step 2: Jalankan uji, pastikan MERAH**

Run: `npx vitest run resources/js/Pages/VISITOR/Workflow/Reporting/Laporan/laporanVisitor.test.js`
Expected: FAIL — daftar tab masih `['harian','studio']`.

- [ ] **Step 3: Tulis klien API**

Buat `resources/js/Pages/VISITOR/Workflow/Reporting/Laporan/api/visitorDashboardApi.js`:

```javascript
import axios from 'axios';

const BASE = '/api/v1/visitor/dashboard';

function hasil(response) {
    return response?.data?.result ?? null;
}

export async function ambilSekarang({ segarkan = false } = {}) {
    return hasil(await axios.get(`${BASE}/sekarang`, { params: segarkan ? { segarkan: 1 } : {} }));
}

export async function ambilAnalitik({ dari, sampai, segarkan = false } = {}) {
    return hasil(
        await axios.get(`${BASE}/analitik`, {
            params: { dari, sampai, ...(segarkan ? { segarkan: 1 } : {}) },
        }),
    );
}
```

- [ ] **Step 4: Daftarkan tab**

Di `laporanVisitor.vue`:

1. Tambahkan slot panel sebagai yang pertama, di atas `#panel-harian`:

```html
        <template #panel-dashboard>
            <VisitorDashboardPanel />
        </template>
```

2. Impor komponennya bersama impor lain:

```javascript
import VisitorDashboardPanel from './components/VisitorDashboardPanel.vue';
```

3. Daftarkan di `components` dan ubah `data()`:

```javascript
            activeTab: 'dashboard',
            tabs: [
                { key: 'dashboard', label: 'Dashboard', icon: 'speedometer2' },
                { key: 'harian', label: 'Aktivitas harian', icon: 'calendar-day' },
                { key: 'studio', label: 'Export Studio', icon: 'sliders' },
            ],
```

- [ ] **Step 5: Buat panel sementara agar impor tidak putus**

Buat `resources/js/Pages/VISITOR/Workflow/Reporting/Laporan/components/VisitorDashboardPanel.vue` berisi kerangka minimal; isinya dilengkapi Task 7–8.

```vue
<template>
    <div class="visitor-dashboard">
        <p class="text-muted">Memuat dashboard…</p>
    </div>
</template>

<script setup>
defineOptions({ name: 'VisitorDashboardPanel' });
</script>
```

- [ ] **Step 6: Jalankan uji, pastikan HIJAU**

Run: `npx vitest run resources/js/Pages/VISITOR/Workflow/Reporting/Laporan/laporanVisitor.test.js`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/VISITOR/Workflow/Reporting/Laporan
git commit -m "feat(VISITOR): daftarkan tab dashboard pada laporan visitor"
```

---

### Task 7: Kartu keadaan dan daftar tindakan

**Files:**
- Create: `resources/js/.../Laporan/components/dashboard/DashboardKartuKeadaan.vue`
- Create: `resources/js/.../Laporan/components/dashboard/DashboardDaftarTindakan.vue`
- Modify: `resources/js/.../Laporan/components/VisitorDashboardPanel.vue`
- Create: `resources/js/.../Laporan/components/dashboard/VisitorDashboardPanel.test.js`

**Interfaces:**
- `DashboardKartuKeadaan` props: `sekarang` (Object|null), `loading` (Boolean). Emit `buka` dengan muatan `'verifikator' | 'harian' | 'approval'`.
- `DashboardDaftarTindakan` props: `judul`, `ikon`, `baris` (Array), `total` (Number), `kosongJudul`, `kosongPesan`, `tautan` (String), `loading`.

- [ ] **Step 1: Tulis uji yang gagal**

Buat `resources/js/.../Laporan/components/dashboard/VisitorDashboardPanel.test.js`:

```javascript
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import DashboardDaftarTindakan from './DashboardDaftarTindakan.vue';

const stubs = { HcisCard: { template: '<section><slot /></section>' }, HcisEmptyState: { props: ['title', 'description'], template: '<div data-test="kosong">{{ title }}</div>' }, HcisSkeleton: true, HcisBadge: { template: '<span><slot /></span>' } };

describe('DashboardDaftarTindakan', () => {
    it('melaporkan sisa baris ketika daftar dipotong', () => {
        const wrapper = mount(DashboardDaftarTindakan, {
            props: {
                judul: 'Perlu ditutup',
                baris: Array.from({ length: 8 }, (_, i) => ({ no_transaksi: `T${i}`, nama: `Tamu ${i}` })),
                total: 23,
                kosongJudul: 'Semua tamu sudah check-out',
                kosongPesan: '',
            },
            global: { stubs },
        });

        expect(wrapper.text()).toContain('8 dari 23');
    });

    it('tidak menyebut pemotongan ketika daftar utuh', () => {
        const wrapper = mount(DashboardDaftarTindakan, {
            props: { judul: 'Perlu ditutup', baris: [{ no_transaksi: 'T1', nama: 'Tamu' }], total: 1, kosongJudul: 'x', kosongPesan: '' },
            global: { stubs },
        });

        expect(wrapper.text()).not.toContain('dari');
    });

    it('menampilkan keadaan kosong yang menyatakan keadaan baik, bukan "tidak ada data"', () => {
        const wrapper = mount(DashboardDaftarTindakan, {
            props: { judul: 'Perlu ditutup', baris: [], total: 0, kosongJudul: 'Semua tamu sudah check-out', kosongPesan: '' },
            global: { stubs },
        });

        expect(wrapper.find('[data-test="kosong"]').text()).toBe('Semua tamu sudah check-out');
    });
});
```

- [ ] **Step 2: Jalankan uji, pastikan MERAH**

Run: `npx vitest run resources/js/Pages/VISITOR/Workflow/Reporting/Laporan/components/dashboard/VisitorDashboardPanel.test.js`
Expected: FAIL — komponen belum ada.

- [ ] **Step 3: Buat `DashboardDaftarTindakan.vue`**

```vue
<template>
    <HcisCard :title="judul" :icon="ikon" variant="glass" size="md" divider>
        <template v-if="loading">
            <HcisSkeleton :lines="4" type="text" />
        </template>

        <HcisEmptyState v-else-if="!baris.length" :title="kosongJudul" :description="kosongPesan" icon="check-circle" />

        <template v-else>
            <ul class="daftar-tindakan">
                <li v-for="item in baris" :key="item.no_transaksi" class="daftar-tindakan__baris">
                    <div class="daftar-tindakan__utama">
                        <span class="daftar-tindakan__judul">{{ item.nama || item.judul || item.no_transaksi }}</span>
                        <span class="daftar-tindakan__meta">{{ keterangan(item) }}</span>
                    </div>
                    <HcisBadge v-if="lencana(item)" size="sm">{{ lencana(item) }}</HcisBadge>
                </li>
            </ul>

            <p v-if="dipotong" class="daftar-tindakan__sisa">
                Menampilkan {{ baris.length }} dari {{ total }}.
                <a v-if="tautan" :href="tautan">Lihat semua</a>
            </p>
        </template>
    </HcisCard>
</template>

<script setup>
import { computed } from 'vue';
import { HcisBadge, HcisCard, HcisEmptyState, HcisSkeleton } from '@/components/ui';

defineOptions({ name: 'DashboardDaftarTindakan' });

const props = defineProps({
    judul: { type: String, required: true },
    ikon: { type: String, default: 'list-check' },
    baris: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    kosongJudul: { type: String, required: true },
    kosongPesan: { type: String, default: '' },
    tautan: { type: String, default: '' },
    loading: { type: Boolean, default: false },
});

const dipotong = computed(() => props.total > props.baris.length);

function keterangan(item) {
    return [item.instansi, item.pic, item.pemohon, item.approver].filter(Boolean).join(' · ') || item.no_transaksi;
}

function lencana(item) {
    if (typeof item.lama_menit === 'number') return durasi(item.lama_menit);
    if (typeof item.umur_menit === 'number') return durasi(item.umur_menit);
    return '';
}

function durasi(menit) {
    if (menit < 60) return `${menit} mnt`;
    const jam = Math.floor(menit / 60);
    if (jam < 24) return `${jam} jam`;
    return `${Math.floor(jam / 24)} hari`;
}
</script>

<style scoped>
.daftar-tindakan { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: var(--hcis-space-2, 0.5rem); }
.daftar-tindakan__baris { display: flex; align-items: center; justify-content: space-between; gap: var(--hcis-space-3, 0.75rem); }
.daftar-tindakan__utama { display: flex; flex-direction: column; min-width: 0; }
.daftar-tindakan__judul { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.daftar-tindakan__meta { font-size: 0.8125rem; color: var(--hcis-text-muted, #64748b); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.daftar-tindakan__sisa { margin: var(--hcis-space-3, 0.75rem) 0 0; font-size: 0.8125rem; color: var(--hcis-text-muted, #64748b); }
</style>
```

- [ ] **Step 4: Buat `DashboardKartuKeadaan.vue`**

```vue
<template>
    <HcisStatGroup :cols="4" gap="sm" appearance="crystal">
        <HcisStatCard
            v-for="kartu in kartu"
            :key="kartu.id"
            :label="kartu.label"
            :value="kartu.value"
            :caption="kartu.caption"
            :icon="kartu.icon"
            :sentiment="kartu.sentiment"
            :loading="loading"
            interactive
            layout="compact"
            appearance="crystal"
            @click="$emit('buka', kartu.tujuan)"
        />
    </HcisStatGroup>
</template>

<script setup>
import { computed } from 'vue';
import { HcisStatCard, HcisStatGroup } from '@/components/ui';

defineOptions({ name: 'DashboardKartuKeadaan' });

const props = defineProps({
    sekarang: { type: Object, default: null },
    loading: { type: Boolean, default: false },
});

defineEmits(['buka']);

const kartu = computed(() => {
    const area = props.sekarang?.di_dalam_area ?? {};
    const jadwal = props.sekarang?.terjadwal_belum_datang ?? {};
    const antrian = props.sekarang?.antrian ?? {};
    const umur = antrian.umur ?? {};

    return [
        {
            id: 'area', label: 'Di dalam area', icon: 'people-fill', sentiment: 'purple', tujuan: 'verifikator',
            value: area.orang ?? 0,
            caption: `${area.transaksi ?? 0} transaksi · orang, bukan transaksi`,
        },
        {
            id: 'tutup', label: 'Perlu ditutup', icon: 'clock-history', sentiment: (area.overdue?.transaksi ?? 0) > 0 ? 'warning' : 'neutral', tujuan: 'verifikator',
            value: area.overdue?.transaksi ?? 0,
            caption: area.overdue?.tertua_menit != null ? `Tertua ${durasi(area.overdue.tertua_menit)}` : 'Tidak ada yang tertinggal',
        },
        {
            id: 'jadwal', label: 'Terjadwal belum datang', icon: 'calendar-check', sentiment: 'info', tujuan: 'harian',
            value: jadwal.transaksi ?? 0,
            caption: `${jadwal.orang ?? 0} orang dijadwalkan hari ini`,
        },
        {
            id: 'antrian', label: 'Menunggu approval', icon: 'hourglass-split', sentiment: (umur.lebih_4_jam ?? 0) > 0 ? 'danger' : 'primary', tujuan: 'approval',
            value: antrian.pending ?? 0,
            caption: keteranganAntrian(umur, antrian.kadaluarsa ?? 0),
        },
    ];
});

function keteranganAntrian(umur, kadaluarsa) {
    const bagian = `<1j ${umur.kurang_1_jam ?? 0} · 1-4j ${umur['1_sampai_4_jam'] ?? 0} · >4j ${umur.lebih_4_jam ?? 0}`;
    return kadaluarsa > 0 ? `${bagian} · ${kadaluarsa} kedaluwarsa tanpa keputusan` : bagian;
}

function durasi(menit) {
    if (menit < 60) return `${menit} mnt`;
    const jam = Math.floor(menit / 60);
    return jam < 24 ? `${jam} jam` : `${Math.floor(jam / 24)} hari`;
}
</script>
```

- [ ] **Step 5: Rangkai di panel**

Ganti isi `VisitorDashboardPanel.vue`:

```vue
<template>
    <div class="visitor-dashboard">
        <header class="visitor-dashboard__kepala">
            <span class="visitor-dashboard__stempel">
                <i class="bi bi-clock" aria-hidden="true"></i>
                {{ stempel }}
            </span>
            <HcisButton size="sm" variant="ghost" icon="arrow-clockwise" :loading="memuatSekarang" @click="muatSekarang(true)">
                Segarkan
            </HcisButton>
        </header>

        <HcisAlert v-if="galat" variant="danger" :title="galat" class="mb-3" />

        <DashboardKartuKeadaan :sekarang="sekarang" :loading="memuatSekarang" @buka="buka" />

        <div class="visitor-dashboard__duo">
            <DashboardDaftarTindakan
                judul="Perlu ditutup"
                ikon="door-open"
                :baris="sekarang?.di_dalam_area?.daftar_perlu_ditutup ?? []"
                :total="sekarang?.di_dalam_area?.daftar_perlu_ditutup_total ?? 0"
                kosong-judul="Semua tamu sudah check-out"
                kosong-pesan="Tidak ada kunjungan hari lalu yang masih terbuka."
                tautan="/verifikator-visitor"
                :loading="memuatSekarang"
            />
            <DashboardDaftarTindakan
                judul="Menunggu keputusan"
                ikon="hourglass-split"
                :baris="sekarang?.antrian?.daftar ?? []"
                :total="sekarang?.antrian?.daftar_total ?? 0"
                kosong-judul="Antrian approval kosong"
                kosong-pesan="Tidak ada pengajuan Visitor yang menunggu keputusan."
                tautan="/approvalvisitor"
                :loading="memuatSekarang"
            />
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { HcisAlert, HcisButton } from '@/components/ui';
import { ambilSekarang } from '../api/visitorDashboardApi';
import DashboardDaftarTindakan from './dashboard/DashboardDaftarTindakan.vue';
import DashboardKartuKeadaan from './dashboard/DashboardKartuKeadaan.vue';

defineOptions({ name: 'VisitorDashboardPanel' });

const sekarang = ref(null);
const memuatSekarang = ref(false);
const galat = ref('');

const stempel = computed(() => {
    if (!sekarang.value?.dihitung_pada) return 'Belum dimuat';
    const waktu = new Date(sekarang.value.dihitung_pada);
    return `Data per ${waktu.getHours().toString().padStart(2, '0')}:${waktu.getMinutes().toString().padStart(2, '0')}`;
});

async function muatSekarang(segarkan = false) {
    memuatSekarang.value = true;
    galat.value = '';
    try {
        sekarang.value = await ambilSekarang({ segarkan });
    } catch (e) {
        galat.value = e?.response?.data?.message || 'Gagal memuat keadaan sekarang.';
    } finally {
        memuatSekarang.value = false;
    }
}

function buka(tujuan) {
    const peta = { verifikator: '/verifikator-visitor', harian: '/laporanvisitor', approval: '/approvalvisitor' };
    if (peta[tujuan]) window.location.href = peta[tujuan];
}

onMounted(() => muatSekarang());
</script>

<style scoped>
.visitor-dashboard { display: flex; flex-direction: column; gap: var(--hcis-space-4, 1rem); }
.visitor-dashboard__kepala { display: flex; align-items: center; justify-content: space-between; gap: var(--hcis-space-3, 0.75rem); }
.visitor-dashboard__stempel { font-size: 0.8125rem; color: var(--hcis-text-muted, #64748b); display: inline-flex; align-items: center; gap: 0.375rem; }
.visitor-dashboard__duo { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: var(--hcis-space-4, 1rem); }
</style>
```

- [ ] **Step 6: Jalankan uji, pastikan HIJAU**

Run: `npx vitest run resources/js/Pages/VISITOR/Workflow/Reporting/Laporan`
Expected: PASS.

Verifikasi tautan sebelum melanjutkan — jalur `/verifikator-visitor` dan `/approvalvisitor` harus dicek terhadap `routes/VISITOR/Workflow/` yang sebenarnya:

```bash
php artisan route:list --path=visitor | grep -i "verifikator\|approval"
```

Sesuaikan peta `buka()` dan prop `tautan` bila jalurnya berbeda. Tautan yang salah adalah jalan buntu yang tak berbunyi.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/VISITOR/Workflow/Reporting/Laporan
git commit -m "feat(VISITOR): kartu keadaan dan daftar tindakan dashboard"
```

---

### Task 8: Blok tren, permit, dan kesehatan data

**Files:**
- Create: `resources/js/.../Laporan/components/dashboard/DashboardBlokTren.vue`
- Create: `resources/js/.../Laporan/components/dashboard/DashboardBlokPermit.vue`
- Create: `resources/js/.../Laporan/components/dashboard/DashboardKesehatanData.vue`
- Modify: `resources/js/.../Laporan/components/VisitorDashboardPanel.vue`
- Modify: `resources/js/.../Laporan/components/dashboard/VisitorDashboardPanel.test.js`

**Interfaces:**
- `DashboardBlokTren` props: `tren` (Array), `sebaranJam` (Array), `dataTipis` (Boolean), `loading`.
- `DashboardBlokPermit` props: `kategori` (Array), `akanKadaluarsa` (Object `{daftar, total}`), `loading`.
- `DashboardKesehatanData` props: `kesehatan` (Object|null), `loading`.
- Tetapan bersama: `AMBANG_DATA_TIPIS = 30` (persen).

- [ ] **Step 1: Tulis uji yang gagal**

Tambahkan ke `VisitorDashboardPanel.test.js`:

```javascript
import DashboardBlokTren from './DashboardBlokTren.vue';

const stubsTren = { ...stubs, HcisChart: { props: ['series', 'categories', 'type'], template: '<div data-test="chart" />' } };

describe('DashboardBlokTren', () => {
    it('memisahkan seri transaksi dan orang', () => {
        const wrapper = mount(DashboardBlokTren, {
            props: {
                tren: [
                    { tanggal: '2026-08-23', transaksi: 2, orang: 6 },
                    { tanggal: '2026-08-24', transaksi: 1, orang: 1 },
                ],
                sebaranJam: [],
                dataTipis: false,
            },
            global: { stubs: stubsTren },
        });

        expect(wrapper.vm.seriTren.map((s) => s.name)).toEqual(['Transaksi', 'Orang']);
        expect(wrapper.vm.seriTren[1].data).toEqual([6, 1]);
    });

    it('memasang penanda ketika data tipis, tanpa menyembunyikan grafiknya', () => {
        const wrapper = mount(DashboardBlokTren, {
            props: { tren: [{ tanggal: '2026-08-24', transaksi: 1, orang: 1 }], sebaranJam: [], dataTipis: true },
            global: { stubs: stubsTren },
        });

        expect(wrapper.text()).toContain('belum layak dipercaya');
        expect(wrapper.find('[data-test="chart"]').exists()).toBe(true);
    });
});
```

- [ ] **Step 2: Jalankan uji, pastikan MERAH**

Run: `npx vitest run resources/js/Pages/VISITOR/Workflow/Reporting/Laporan/components/dashboard/VisitorDashboardPanel.test.js`
Expected: FAIL — `DashboardBlokTren.vue` belum ada.

- [ ] **Step 3: Buat `DashboardBlokTren.vue`**

```vue
<template>
    <div class="blok-tren">
        <HcisAlert
            v-if="dataTipis"
            variant="warning"
            title="Angka kunjungan belum layak dipercaya"
            description="Banyak kunjungan tidak punya waktu keluar, sehingga durasi dan jam sibuk di bawah ini belum menggambarkan keadaan sebenarnya."
            class="mb-3"
        />

        <div class="blok-tren__duo">
            <HcisChart
                type="line"
                title="Tren kunjungan"
                description="Transaksi dan orang dipisah karena rombongan membuat keduanya berbeda."
                :series="seriTren"
                :categories="kategoriTren"
                :height="280"
                sentiment="purple"
                :loading="loading"
            />
            <HcisChart
                type="heatmap"
                title="Jam sibuk kedatangan"
                description="Sebaran jam check-in pada rentang terpilih."
                :series="seriJam"
                :height="280"
                sentiment="teal"
                :loading="loading"
            />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { HcisAlert, HcisChart } from '@/components/ui';

defineOptions({ name: 'DashboardBlokTren' });

const props = defineProps({
    tren: { type: Array, default: () => [] },
    sebaranJam: { type: Array, default: () => [] },
    dataTipis: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
});

const kategoriTren = computed(() => props.tren.map((t) => t.tanggal));

const seriTren = computed(() => [
    { name: 'Transaksi', data: props.tren.map((t) => t.transaksi) },
    { name: 'Orang', data: props.tren.map((t) => t.orang) },
]);

const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

const seriJam = computed(() => {
    const ember = new Map();
    props.sebaranJam.forEach((baris) => {
        const hari = HARI[new Date(`${baris.tanggal}T00:00:00`).getDay()];
        const kunci = `${hari}|${baris.jam}`;
        ember.set(kunci, (ember.get(kunci) ?? 0) + baris.jumlah);
    });

    return HARI.map((hari) => ({
        name: hari,
        data: Array.from({ length: 24 }, (_, jam) => ({ x: `${jam}`, y: ember.get(`${hari}|${jam}`) ?? 0 })),
    }));
});
</script>

<style scoped>
.blok-tren__duo { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: var(--hcis-space-4, 1rem); }
</style>
```

- [ ] **Step 4: Buat `DashboardBlokPermit.vue`**

```vue
<template>
    <div class="blok-permit">
        <HcisChart
            type="bar-horizontal"
            title="Permit aktif per kategori"
            :series="[{ name: 'Permit', data: kategori.map((k) => k.jumlah) }]"
            :categories="kategori.map((k) => k.kategori)"
            :height="260"
            sentiment="cyan"
            :loading="loading"
        />

        <HcisCard title="Akan kadaluarsa ≤30 hari" icon="calendar-x" variant="glass" size="md" divider>
            <HcisSkeleton v-if="loading" :lines="4" type="text" />
            <HcisEmptyState
                v-else-if="!daftar.length"
                title="Tidak ada permit yang jatuh tempo"
                description="Tidak ada permit aktif yang kadaluarsa dalam 30 hari ke depan."
                icon="check-circle"
            />
            <template v-else>
                <ul class="blok-permit__daftar">
                    <li v-for="p in daftar" :key="p.no_transaksi">
                        <div>
                            <span class="blok-permit__plat">{{ p.plat }}</span>
                            <span class="blok-permit__meta">{{ p.kategori }} · {{ p.pemohon }} · {{ p.departemen }}</span>
                        </div>
                        <HcisBadge :variant="p.sisa_hari <= 7 ? 'danger' : 'warning'" size="sm">
                            {{ p.sisa_hari }} hari
                        </HcisBadge>
                    </li>
                </ul>
                <p v-if="total > daftar.length" class="blok-permit__sisa">
                    Menampilkan {{ daftar.length }} dari {{ total }}.
                </p>
            </template>
        </HcisCard>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { HcisBadge, HcisCard, HcisChart, HcisEmptyState, HcisSkeleton } from '@/components/ui';

defineOptions({ name: 'DashboardBlokPermit' });

const props = defineProps({
    kategori: { type: Array, default: () => [] },
    akanKadaluarsa: { type: Object, default: () => ({ daftar: [], total: 0 }) },
    loading: { type: Boolean, default: false },
});

const daftar = computed(() => props.akanKadaluarsa?.daftar ?? []);
const total = computed(() => props.akanKadaluarsa?.total ?? 0);
</script>

<style scoped>
.blok-permit { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: var(--hcis-space-4, 1rem); }
.blok-permit__daftar { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.5rem; }
.blok-permit__daftar li { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; }
.blok-permit__plat { display: block; font-weight: 600; }
.blok-permit__meta { font-size: 0.8125rem; color: var(--hcis-text-muted, #64748b); }
.blok-permit__sisa { margin: 0.75rem 0 0; font-size: 0.8125rem; color: var(--hcis-text-muted, #64748b); }
</style>
```

- [ ] **Step 5: Buat tetapan bersama lalu `DashboardKesehatanData.vue`**

Ambang dipakai dua komponen, jadi ia tinggal di satu berkas. Buat dulu
`resources/js/Pages/VISITOR/Workflow/Reporting/Laporan/components/dashboard/tetapan.js`:

```javascript
/** Di atas ambang ini, angka kunjungan diberi penanda "belum layak dipercaya" (persen). */
export const AMBANG_DATA_TIPIS = 30;
```

Lalu komponennya:

```vue
<template>
    <HcisCard title="Kesehatan data buku tamu" icon="clipboard-check" variant="glass" size="md" divider>
        <HcisSkeleton v-if="loading" :lines="2" type="text" />
        <HcisEmptyState
            v-else-if="!kesehatan || kesehatan.total === 0"
            title="Tidak ada kunjungan pada rentang ini"
            description="Pilih rentang lain untuk menilai kelengkapan data."
            icon="inbox"
        />
        <div v-else class="kesehatan">
            <div v-for="ukur in ukuran" :key="ukur.id" class="kesehatan__butir">
                <span class="kesehatan__angka" :class="{ 'kesehatan__angka--waspada': ukur.waspada }">{{ ukur.nilai }}</span>
                <span class="kesehatan__label">{{ ukur.label }}</span>
            </div>
        </div>
    </HcisCard>
</template>

<script setup>
import { computed } from 'vue';
import { HcisCard, HcisEmptyState, HcisSkeleton } from '@/components/ui';
import { AMBANG_DATA_TIPIS } from './tetapan';

defineOptions({ name: 'DashboardKesehatanData' });

const props = defineProps({
    kesehatan: { type: Object, default: null },
    loading: { type: Boolean, default: false },
});

const ukuran = computed(() => {
    const k = props.kesehatan ?? {};
    return [
        { id: 'total', label: 'Kunjungan pada rentang', nilai: k.total ?? 0, waspada: false },
        { id: 'keluar', label: 'Sudah masuk tapi tanpa waktu keluar', nilai: `${k.persen_tanpa_waktu_keluar ?? 0}%`, waspada: (k.persen_tanpa_waktu_keluar ?? 0) >= AMBANG_DATA_TIPIS },
        { id: 'identitas', label: 'Tanpa nomor identitas', nilai: k.tanpa_identitas ?? 0, waspada: (k.tanpa_identitas ?? 0) > 0 },
        { id: 'hp', label: 'Tanpa nomor HP', nilai: k.tanpa_hp ?? 0, waspada: false },
    ];
});
</script>

<style scoped>
.kesehatan { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: var(--hcis-space-4, 1rem); }
.kesehatan__butir { display: flex; flex-direction: column; gap: 0.25rem; }
.kesehatan__angka { font-size: 1.5rem; font-weight: 700; }
.kesehatan__angka--waspada { color: var(--hcis-color-warning, #b45309); }
.kesehatan__label { font-size: 0.8125rem; color: var(--hcis-text-muted, #64748b); }
</style>
```

- [ ] **Step 6: Rangkai blok analitik ke panel**

Di `VisitorDashboardPanel.vue`: tambahkan pemilih rentang dan pemuatan analitik.

Tambahkan ke template, di bawah `visitor-dashboard__duo`:

```html
        <div class="visitor-dashboard__rentang">
            <HcisDateRangePicker v-model="rentang" size="sm" @update:model-value="muatAnalitik()" />
        </div>

        <DashboardBlokTren
            :tren="analitik?.tren ?? []"
            :sebaran-jam="analitik?.sebaran_jam ?? []"
            :data-tipis="dataTipis"
            :loading="memuatAnalitik"
        />
        <DashboardBlokPermit
            :kategori="analitik?.permit_kategori ?? []"
            :akan-kadaluarsa="analitik?.permit_akan_kadaluarsa ?? { daftar: [], total: 0 }"
            :loading="memuatAnalitik"
        />
        <DashboardKesehatanData :kesehatan="analitik?.kesehatan_data ?? null" :loading="memuatAnalitik" />
```

dan ke script:

```javascript
import { HcisDateRangePicker } from '@/components/ui';
import { ambilAnalitik } from '../api/visitorDashboardApi';
import DashboardBlokPermit from './dashboard/DashboardBlokPermit.vue';
import DashboardBlokTren from './dashboard/DashboardBlokTren.vue';
import DashboardKesehatanData from './dashboard/DashboardKesehatanData.vue';
import { AMBANG_DATA_TIPIS } from './dashboard/tetapan';

const analitik = ref(null);
const memuatAnalitik = ref(false);
const rentang = ref([tanggalMundur(13), tanggalMundur(0)]);

const dataTipis = computed(() => (analitik.value?.kesehatan_data?.persen_tanpa_waktu_keluar ?? 0) >= AMBANG_DATA_TIPIS);

function tanggalMundur(hari) {
    const d = new Date();
    d.setDate(d.getDate() - hari);
    return d.toISOString().split('T')[0];
}

async function muatAnalitik(segarkan = false) {
    memuatAnalitik.value = true;
    try {
        analitik.value = await ambilAnalitik({ dari: rentang.value?.[0], sampai: rentang.value?.[1], segarkan });
    } catch (e) {
        galat.value = e?.response?.data?.message || 'Gagal memuat analitik.';
    } finally {
        memuatAnalitik.value = false;
    }
}
```

Ubah `onMounted` menjadi `onMounted(() => { muatSekarang(); muatAnalitik(); })`, dan tombol Segarkan menjadi `@click="muatSekarang(true)"` saja — analitik **tidak** ikut disegarkan, itulah gunanya pemisahan cache.

- [ ] **Step 7: Jalankan uji, pastikan HIJAU**

Run: `npx vitest run resources/js/Pages/VISITOR/Workflow/Reporting/Laporan`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add resources/js/Pages/VISITOR/Workflow/Reporting/Laporan
git commit -m "feat(VISITOR): blok tren, permit, dan kesehatan data dashboard"
```

---

### Task 9: Verifikasi menyeluruh

Penjaga baru menyentuh kode bersama; suite penuh wajib dijalankan, bukan hanya berkas baru.

**Files:** —

- [ ] **Step 1: Bangun frontend**

Run: `npm run build`
Expected: berhasil tanpa galat. Kegagalan impor komponen kit hanya muncul di sini, bukan di Vitest yang memakai stub.

- [ ] **Step 2: Jalankan seluruh uji frontend**

Run: `npm run test:unit`
Expected: PASS. Kegagalan di luar Visitor berarti ada yang tersenggol — perbaiki sebelum lanjut.

- [ ] **Step 3: Jalankan uji backend Unit dan Feature**

Run:
```bash
npm run test:backend:unit
npm run test:backend:feature
```

Catat baseline kegagalan yang **sudah ada sebelum pekerjaan ini** dan pisahkan dari kegagalan baru. Uji berjalan pada DB development bersama; kegagalan yang berkaitan dengan data yang berubah di luar kendali bukan regresi kode.

- [ ] **Step 4: Periksa gaya kode**

Run: `./vendor/bin/pint --test app/Services/VISITOR/Workflow/Reporting/Dashboard app/Http/Controllers/VISITOR/Workflow/Reporting`
Bila ada temuan: `./vendor/bin/pint <path>` lalu jalankan ulang.

- [ ] **Step 5: Periksa kontrak rute**

Run: `php artisan test tests/Feature/VISITOR/VisitorRouteContractTest.php`
Expected: PASS. Berkas rute baru tidak boleh melanggar kontrak modul yang sudah ada.

- [ ] **Step 6: Buka halamannya**

Buka `/laporanvisitor` di peramban. Periksa dengan mata:
1. Tab **Dashboard** muncul sebagai tab pertama dan terpilih otomatis.
2. Stempel "Data per HH:MM" terisi setelah muat.
3. Tombol **Segarkan** mengubah stempel dan **tidak** memuat ulang blok analitik.
4. Semua kartu dan daftar menampilkan keadaan kosong yang berbunyi wajar — bukan strip, bukan grafik bolong, bukan "tidak ada data".
5. Mengklik kartu membawa ke halaman yang benar.

Keadaan kosong adalah tampilan yang paling mungkin terlihat: DB development hanya berisi 5 transaksi tamu dan 9 permit.

- [ ] **Step 7: Commit terakhir bila ada perbaikan**

```bash
git add -A
git commit -m "chore(VISITOR): rapikan dashboard visitor setelah verifikasi menyeluruh"
```

---

## Catatan untuk pelaksana

- **Jangan** memindahkan kueri keadaan area ke `N_VISITOR_Tamu_Efektif` "supaya lebih rapi". Uji `test_kueri_keadaan_area_membaca_header_bukan_tabel_efektif` ada persis untuk menghentikan itu.
- **Jangan** menambahkan penyegaran otomatis. Cache bersama sudah siap menanggungnya bila nanti diminta, tetapi menyalakannya sekarang berarti tiap admin yang membuka halaman menambah beban tetap ke database.
- Bila `php artisan test` terasa mengunci UI: RCSI mati pada `Web_HRIS`, jadi penulis menahan pembaca. Uji dalam rencana ini sengaja read-only — bila sebuah uji butuh menulis, hentikan dan pindahkan logikanya ke fungsi murni yang diuji di suite Unit.
- Jalur `/verifikator-visitor` dan `/approvalvisitor` **wajib diverifikasi** dengan `php artisan route:list` (Task 7, Step 6) sebelum dianggap benar.
