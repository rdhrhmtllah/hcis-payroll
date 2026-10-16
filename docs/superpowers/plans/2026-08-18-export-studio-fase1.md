# Export Studio — Fase 1: Mesin & Dataset Kepatuhan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun mesin laporan deklaratif lintas modul beserta dataset
kepatuhan SOP, dibuktikan menghasilkan berkas yang identik dengan jenis export
`rinci` yang sudah ada.

**Architecture:** Mesin generik di `app/Services/SYSTEM/Application/Reports/`
menerima definisi JSON berisi kunci katalog, menyusunnya jadi satu Query Builder
ber-`GROUP BY`, dan menyerahkannya ke `AbstractExportService` yang sudah ada.
Katalog dataset per modul di `app/Services/LMS/SOP/Export/` menyediakan dimensi,
ukuran, saringan, base query, dan kait lingkup. Tak ada SQL dari pengguna.

**Tech Stack:** Laravel 11, SQL Server (`Web_HRIS`), PHPUnit, PhpSpreadsheet,
barryvdh/laravel-dompdf, Google Cloud Tasks, GCS.

**Spec:** `docs/superpowers/specs/2026-08-18-export-studio-design.md`

## Global Constraints

- **Jangan pernah membuat, menjalankan, atau menerapkan migration Laravel.**
  Skema diubah lewat berkas DDL eksplisit di `database/ddl/<change-id>/forward.sql`.
- **Agent tidak pernah menjalankan DROP atau TRUNCATE**, termasuk `drop.sql`
  yang dibuatnya sendiri.
- DDL hanya dijalankan ke **development** (`Web_HRIS`), tak pernah production.
- Setiap perubahan skema butuh change ID, `forward.sql`, `drop.sql`, README di
  `docs/changes/<change-id>/`, dokumentasi tabel di `docs/database/tables/<modul>/`,
  dan entri di `database/ddl/status.json`.
- **Jangan pernah `git add -A` atau `git add .`** — tambahkan berkas per jalur
  eksplisit.
- Skema baru: `int` untuk id biasa, `datetime2`, panjang string eksplisit,
  `Flag_<Makna> char(1)` bernilai `Y`/`T`, **tanpa CHECK constraint** untuk
  daftar nilai bisnis yang berkembang, PK `Id_<Entitas>`, FK `<Entitas>_Id`,
  tanpa prefix `dbo.`, tanpa FOREIGN KEY.
- Aktor audit `Created_By`/`Updated_By` = `Kode_Karyawan`, `varchar(100)`.
- Response API baru memakai `App\Helpers\ResponseHelper` dengan `error_code`
  stabil; `message` tak boleh memuat stack trace, SQL, nama class, atau path.
- `Module` selalu berasal dari route, **tak pernah** dari badan permintaan.
- Baseline gerbang struktural sudah diambil:
  `plans/sop/baseline/export-studio-sebelum-route.json` (1173 route),
  `plans/sop/baseline/export-studio-sebelum-console.json` (170 command).
  Bandingkan ulang sebelum commit terakhir; setiap beda harus disengaja dan
  dijelaskan di pesan commit.
- Unset `DATABASE_URL` sebelum menjalankan perintah Laravel.
- Jalankan test dengan `php artisan test --filter=<NamaTest>` untuk fokus, dan
  `npm run test:backend:unit` / `npm run test:backend:feature` untuk suite.

## Cakupan fase ini

Masuk: dua tabel, kontrak dataset, validator, compiler, runner, layanan
template, layanan export, dataset `kepatuhan`, satu template sistem (`rinci`),
harness regresi, endpoint API.

**Tidak** masuk (fase berikutnya): layar Studio Vue, empat dataset lain, enam
template sistem lain, bagan, skrip menu DML, pensiun jalur lama.

---

### Task 1: Tabel `N_HRIS_Report_Template` dan `N_HRIS_Report_Template_Akses`

**Files:**
- Create: `database/ddl/DDL-2026-009-create-report-template-tables/forward.sql`
- Create: `database/ddl/DDL-2026-009-create-report-template-tables/drop.sql`
- Create: `docs/changes/DDL-2026-009-create-report-template-tables/README.md`
- Create: `docs/database/tables/hris/n-hris-report-template.md`
- Create: `docs/database/tables/hris/n-hris-report-template-akses.md`
- Modify: `database/ddl/status.json`

**Interfaces:**
- Consumes: tidak ada.
- Produces: dua tabel yang dipakai Task 8 (`ReportTemplateService`). Nama kolom
  persis seperti di `forward.sql` di bawah.

- [ ] **Step 1: Periksa perubahan tertunda di ledger**

Run: `cat database/ddl/status.json | python3 -c "import json,sys; [print(c['change_id'], c.get('sql_tracker')) for c in json.load(sys.stdin)['changes']]"`
Expected: sembilan perubahan, semuanya `sql_tracker` PENDING. Laporkan ini
sebagai temuan; jangan menghalangi pekerjaan — DDL-2026-009 adalah entri baru.

- [ ] **Step 2: Tulis `forward.sql`**

```sql
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_HRIS_Report_Template')
CREATE TABLE N_HRIS_Report_Template (
    Id_Report_Template      int           IDENTITY(1,1) NOT NULL
        CONSTRAINT PK_Report_Template PRIMARY KEY,
    Module                  varchar(30)   NOT NULL,
    Kode                    varchar(60)   NOT NULL,
    Nama                    nvarchar(150) NOT NULL,
    Deskripsi               nvarchar(500) NULL,
    Dataset_Key             varchar(40)   NOT NULL,
    Mode                    varchar(20)   NOT NULL,
    Definisi_Json           nvarchar(max) NOT NULL,
    Versi_Definisi          int           NOT NULL DEFAULT 1,
    Tingkat_Bagi            varchar(20)   NOT NULL,
    Kode_Karyawan_Pemilik   varchar(50)   NOT NULL,
    Kode_Template_Asal      varchar(60)   NULL,
    Flag_Aktif              char(1)       NOT NULL DEFAULT 'Y',
    Created_At              datetime2(0)  NOT NULL DEFAULT (SYSDATETIME()),
    Created_By              varchar(100)  NULL,
    Updated_At              datetime2(0)  NULL,
    Updated_By              varchar(100)  NULL,
    CONSTRAINT CK_Report_Template_DefinisiJson CHECK (ISJSON(Definisi_Json) = 1)
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_Report_Template_Kode')
CREATE UNIQUE INDEX UX_Report_Template_Kode
    ON N_HRIS_Report_Template (Module, Kode);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Report_Template_Pemilik')
CREATE INDEX IX_Report_Template_Pemilik
    ON N_HRIS_Report_Template (Module, Kode_Karyawan_Pemilik, Flag_Aktif);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Report_Template_Bagi')
CREATE INDEX IX_Report_Template_Bagi
    ON N_HRIS_Report_Template (Module, Tingkat_Bagi, Flag_Aktif)
    INCLUDE (Dataset_Key);
GO

IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_HRIS_Report_Template_Akses')
CREATE TABLE N_HRIS_Report_Template_Akses (
    Id_Report_Template_Akses int          IDENTITY(1,1) NOT NULL
        CONSTRAINT PK_Report_Template_Akses PRIMARY KEY,
    Report_Template_Id       int          NOT NULL,
    Tipe_Sasaran             varchar(20)  NOT NULL,
    Nilai_Sasaran            varchar(100) NOT NULL,
    Flag_Aktif               char(1)      NOT NULL DEFAULT 'Y',
    Created_At               datetime2(0) NOT NULL DEFAULT (SYSDATETIME()),
    Created_By               varchar(100) NULL
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_Report_Template_Akses_Sasaran')
CREATE UNIQUE INDEX UX_Report_Template_Akses_Sasaran
    ON N_HRIS_Report_Template_Akses (Report_Template_Id, Tipe_Sasaran, Nilai_Sasaran);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Report_Template_Akses_Cari')
CREATE INDEX IX_Report_Template_Akses_Cari
    ON N_HRIS_Report_Template_Akses (Tipe_Sasaran, Nilai_Sasaran, Flag_Aktif);
GO
```

Tidak ada CHECK untuk `Mode`, `Tingkat_Bagi`, atau `Dataset_Key` — daftar
nilainya akan berkembang, dan `table-design-rules.md` melarang mengunci daftar
semacam itu di database. `ISJSON` tetap ada karena itu integritas struktur.

- [ ] **Step 3: Tulis `drop.sql`** (alat bantu manual — jangan dijalankan)

```sql
DROP INDEX IF EXISTS IX_Report_Template_Akses_Cari ON N_HRIS_Report_Template_Akses;
DROP INDEX IF EXISTS UX_Report_Template_Akses_Sasaran ON N_HRIS_Report_Template_Akses;
DROP TABLE IF EXISTS N_HRIS_Report_Template_Akses;
DROP INDEX IF EXISTS IX_Report_Template_Bagi ON N_HRIS_Report_Template;
DROP INDEX IF EXISTS IX_Report_Template_Pemilik ON N_HRIS_Report_Template;
DROP INDEX IF EXISTS UX_Report_Template_Kode ON N_HRIS_Report_Template;
DROP TABLE IF EXISTS N_HRIS_Report_Template;
```

- [ ] **Step 4: Verifikasi target database sebelum eksekusi**

Run: `unset DATABASE_URL && php artisan tinker --execute="echo DB::selectOne('SELECT DB_NAME() AS d')->d;"`
Expected: `Web_HRIS`. Bila bukan, **berhenti** dan minta keputusan. Jangan
menyimpulkan environment dari nama host.

- [ ] **Step 5: Jalankan forward DDL ke development**

Run: `unset DATABASE_URL && php artisan tinker --execute="foreach (preg_split('/^GO\s*$/mi', file_get_contents('database/ddl/DDL-2026-009-create-report-template-tables/forward.sql')) as \$b) { \$b = trim(\$b); if (\$b !== '') DB::unprepared(\$b); } echo 'ok';"`
Expected: `ok`

- [ ] **Step 6: Verifikasi hasilnya**

Run: `unset DATABASE_URL && php artisan tinker --execute="foreach (['N_HRIS_Report_Template','N_HRIS_Report_Template_Akses'] as \$t) { \$c = DB::selectOne('SELECT COUNT(*) AS n FROM sys.columns WHERE object_id = OBJECT_ID(?)', [\$t]); \$i = DB::selectOne('SELECT COUNT(*) AS n FROM sys.indexes WHERE object_id = OBJECT_ID(?) AND name IS NOT NULL', [\$t]); echo \$t.' kolom='.\$c->n.' indeks='.\$i->n.PHP_EOL; }"`
Expected: `N_HRIS_Report_Template kolom=17 indeks=4` dan
`N_HRIS_Report_Template_Akses kolom=6 indeks=3` (indeks termasuk PK).

- [ ] **Step 7: Tulis dokumentasi tabel**

`docs/database/tables/hris/n-hris-report-template.md` wajib memuat: tujuan
tabel; tiap kolom beserta tipe, nullability, dan artinya; arti dan default
`Flag_Aktif`; sumber aktor audit (`Kode_Karyawan` dari `HRIS_Karyawan`, lewat
`Auth::user()->karyawan`); relasi ke `N_HRIS_Report_Template_Akses` lewat
`Report_Template_Id` tanpa FOREIGN KEY; alasan tiap indeks (`UX` memberi tiap
modul ruang nama kode sendiri; `IX_..._Pemilik` melayani galeri "Milik Saya";
`IX_..._Bagi` melayani galeri "Dibagikan"); change ID `DDL-2026-009`; dan
jalur SQL-nya. Bagian "dipakai oleh" ditulis **setelah** Task 8 dan Task 11
selesai, dengan nama class dan endpoint yang benar-benar ada — jangan mengarang
referensi.

- [ ] **Step 8: Tulis README perubahan dan entri ledger**

`docs/changes/DDL-2026-009-create-report-template-tables/README.md` mengikuti
bentuk `docs/changes/DDL-2026-007-create-pengumpulan-data-tables/README.md`.

Tambahkan ke `database/ddl/status.json` di akhir array `changes`:

```json
{
  "change_id": "DDL-2026-009-create-report-template-tables",
  "type": "DDL",
  "title": "Create cross-module report template tables",
  "status": "DONE",
  "development": "DONE",
  "sql_tracker": "PENDING",
  "production": "PENDING",
  "verification": "DEV_DONE",
  "artifact_path": "database/ddl/DDL-2026-009-create-report-template-tables/forward.sql",
  "documentation_path": "docs/changes/DDL-2026-009-create-report-template-tables/README.md",
  "execution": {
    "database": "Web_HRIS",
    "result": "2 tabel dibuat dan diverifikasi lewat sys.columns dan sys.indexes"
  }
}
```

- [ ] **Step 9: Commit**

```bash
git add database/ddl/DDL-2026-009-create-report-template-tables/forward.sql \
        database/ddl/DDL-2026-009-create-report-template-tables/drop.sql \
        database/ddl/status.json \
        docs/changes/DDL-2026-009-create-report-template-tables/README.md \
        docs/database/tables/hris/n-hris-report-template.md \
        docs/database/tables/hris/n-hris-report-template-akses.md
git commit -m "feat(db): tabel template laporan lintas modul (DDL-2026-009)"
```

---

### Task 2: `ResponseHelper::errorWithCode()`

Standar backend §6 mewajibkan `error_code` stabil dan `errors` per field.
`ResponseHelper::error()` yang ada hanya mengirim `message` dan `status`.
Menambah parameter ke method itu menyentuh seluruh pemanggil lama, jadi
tambahkan method baru — aditif, nol risiko regresi.

Klasifikasi scope: **related change**, diwajibkan standar untuk endpoint baru.

**Files:**
- Modify: `app/Helpers/ResponseHelper.php`
- Test: `tests/Unit/SYSTEM/Application/ResponseHelperErrorCodeTest.php`

**Interfaces:**
- Produces: `ResponseHelper::errorWithCode(string $message, string $errorCode, int $status = 400, array $errors = [])` — dipakai Task 11.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

namespace Tests\Unit\SYSTEM\Application;

use App\Helpers\ResponseHelper;
use Tests\TestCase;

class ResponseHelperErrorCodeTest extends TestCase
{
    public function test_mengirim_error_code_dan_errors(): void
    {
        $res = ResponseHelper::errorWithCode(
            'Definisi laporan tidak sah.',
            'REPORT_DEFINITION_INVALID',
            422,
            ['dimensi' => ['Dimensi tidak dikenal: xyz']]
        );

        $this->assertSame(422, $res->getStatusCode());

        $body = json_decode($res->getContent(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('REPORT_DEFINITION_INVALID', $body['error_code']);
        $this->assertSame(['dimensi' => ['Dimensi tidak dikenal: xyz']], $body['errors']);
        $this->assertArrayHasKey('request_id', $body);
    }

    public function test_error_lama_tidak_berubah(): void
    {
        $body = json_decode(ResponseHelper::error('Gagal', 500)->getContent(), true);

        $this->assertSame(['success', 'status', 'message'], array_keys($body));
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `unset DATABASE_URL && php artisan test --filter=ResponseHelperErrorCodeTest`
Expected: FAIL — `Call to undefined method App\Helpers\ResponseHelper::errorWithCode()`

- [ ] **Step 3: Implementasi minimal**

Tambahkan di `app/Helpers/ResponseHelper.php`, setelah `error()`:

```php
    /**
     * Response error dengan kode stabil.
     *
     * Method terpisah, bukan parameter tambahan pada error(): error() dipanggil
     * dari puluhan tempat, dan mengubah tanda tangannya menukar kebutuhan satu
     * fitur dengan risiko regresi di seluruh repo.
     */
    public static function errorWithCode(string $message, string $errorCode, int $status = 400, array $errors = [])
    {
        return response()->json([
            'success' => false,
            'status' => $status,
            'message' => $message,
            'error_code' => $errorCode,
            'request_id' => (string) \Illuminate\Support\Str::uuid(),
            'errors' => $errors,
        ], $status);
    }
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `unset DATABASE_URL && php artisan test --filter=ResponseHelperErrorCodeTest`
Expected: PASS, 2 test

- [ ] **Step 5: Commit**

```bash
git add app/Helpers/ResponseHelper.php tests/Unit/SYSTEM/Application/ResponseHelperErrorCodeTest.php
git commit -m "feat(system): ResponseHelper::errorWithCode untuk kontrak error_code"
```

---

### Task 3: Kontrak dataset dan registry

**Files:**
- Create: `app/Services/SYSTEM/Application/Reports/Kontrak/Dimensi.php`
- Create: `app/Services/SYSTEM/Application/Reports/Kontrak/Ukuran.php`
- Create: `app/Services/SYSTEM/Application/Reports/Kontrak/Saringan.php`
- Create: `app/Services/SYSTEM/Application/Reports/Kontrak/Lingkup.php`
- Create: `app/Services/SYSTEM/Application/Reports/Kontrak/DatasetLaporan.php`
- Create: `app/Services/SYSTEM/Application/Reports/DatasetRegistry.php`
- Test: `tests/Unit/SYSTEM/Application/Reports/DatasetRegistryTest.php`

**Interfaces:**
- Produces:
  - `Dimensi(string $key, string $label, string $select, string $tipe, string $grup, bool $sensitif = false)`
  - `Ukuran(string $key, string $label, string $agregat, string $format, bool $sensitif = false)`
  - `Saringan(string $key, string $label, string $kolom, string $tipe, array $operator, bool $wajibSaatJalan = false)`
  - `Lingkup(string $kodeKaryawan, bool $adminPenuh, array $anggotaTim, bool $bolehFieldTerbatas)`
  - `DatasetLaporan` — interface dengan `kunci(): string`, `label(): string`,
    `grain(): string`, `dimensi(): array`, `ukuran(): array`,
    `saringan(): array`, `baseQuery(): \Illuminate\Database\Query\Builder`,
    `terapkanLingkup($q, Lingkup $l)`
  - `DatasetRegistry::daftarkan(string $module, DatasetLaporan $ds): void`,
    `DatasetRegistry::ambil(string $module, string $kunci): DatasetLaporan`,
    `DatasetRegistry::semua(string $module): array`

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

namespace Tests\Unit\SYSTEM\Application\Reports;

use App\Services\SYSTEM\Application\Reports\DatasetRegistry;
use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Dimensi;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\Kontrak\Saringan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Ukuran;
use RuntimeException;
use Tests\TestCase;

class DatasetRegistryTest extends TestCase
{
    private function datasetPalsu(string $kunci = 'uji'): DatasetLaporan
    {
        return new class($kunci) implements DatasetLaporan {
            public function __construct(private string $k) {}

            public function kunci(): string { return $this->k; }
            public function label(): string { return 'Uji'; }
            public function grain(): string { return 'satu baris per apa pun'; }
            public function dimensi(): array { return [new Dimensi('a', 'A', 't.a', 'teks', 'inti')]; }
            public function ukuran(): array { return [new Ukuran('n', 'N', 'COUNT(*)', 'angka')]; }
            public function saringan(): array { return [new Saringan('a', 'A', 't.a', 'teks', ['salah_satu'])]; }
            public function baseQuery(): \Illuminate\Database\Query\Builder { return \DB::table('N_HRIS_Report_Template as t'); }
            public function terapkanLingkup($q, Lingkup $l) { return $q; }
        };
    }

    public function test_dataset_terdaftar_bisa_diambil(): void
    {
        $reg = new DatasetRegistry();
        $reg->daftarkan('sop', $this->datasetPalsu('kepatuhan'));

        $this->assertSame('kepatuhan', $reg->ambil('sop', 'kepatuhan')->kunci());
    }

    public function test_dataset_modul_lain_tidak_bocor(): void
    {
        $reg = new DatasetRegistry();
        $reg->daftarkan('sop', $this->datasetPalsu('kepatuhan'));

        $this->expectException(RuntimeException::class);
        $reg->ambil('kpi', 'kepatuhan');
    }

    public function test_kunci_tak_dikenal_ditolak_bukan_dikembalikan_null(): void
    {
        $reg = new DatasetRegistry();

        $this->expectException(RuntimeException::class);
        $reg->ambil('sop', 'tidak-ada');
    }

    public function test_semua_hanya_mengembalikan_dataset_modulnya(): void
    {
        $reg = new DatasetRegistry();
        $reg->daftarkan('sop', $this->datasetPalsu('kepatuhan'));
        $reg->daftarkan('kpi', $this->datasetPalsu('parameter'));

        $this->assertSame(['kepatuhan'], array_keys($reg->semua('sop')));
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `unset DATABASE_URL && php artisan test --filter=DatasetRegistryTest`
Expected: FAIL — class `DatasetRegistry` tidak ditemukan

- [ ] **Step 3: Implementasi value object**

`Kontrak/Dimensi.php`:

```php
<?php

namespace App\Services\SYSTEM\Application\Reports\Kontrak;

/**
 * Satu kolom pengelompokan.
 *
 * `select` adalah ekspresi SQL utuh, bukan nama kolom. Itu yang membuat modul
 * seperti Pengumpulan Data — yang jawabannya hidup di dalam kolom JSON — tetap
 * muat tanpa mengubah mesin.
 */
class Dimensi
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $select,
        public readonly string $tipe,      // teks | angka | tanggal
        public readonly string $grup,
        public readonly bool $sensitif = false,
    ) {}
}
```

`Kontrak/Ukuran.php`:

```php
<?php

namespace App\Services\SYSTEM\Application\Reports\Kontrak;

/**
 * Satu angka agregat.
 *
 * `agregat` adalah ekspresi lengkap yang KAMI tulis, bukan hasil rakitan
 * "agregasi × field" dari pengguna. Grain dataset kepatuhan adalah item, jadi
 * COUNT(*) di sana menghitung item — bukan orang. Bila pengguna boleh merakit
 * sendiri, angkanya salah dan tampak benar.
 */
class Ukuran
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $agregat,
        public readonly string $format,    // angka | persen | durasi
        public readonly bool $sensitif = false,
    ) {}
}
```

`Kontrak/Saringan.php`:

```php
<?php

namespace App\Services\SYSTEM\Application\Reports\Kontrak;

class Saringan
{
    /** @param string[] $operator */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $kolom,
        public readonly string $tipe,          // teks | angka | tanggal
        public readonly array $operator,       // sama_dengan | salah_satu | antara | mengandung
        public readonly bool $wajibSaatJalan = false,
    ) {}
}
```

`Kontrak/Lingkup.php`:

```php
<?php

namespace App\Services\SYSTEM\Application\Reports\Kontrak;

/**
 * Siapa yang menjalankan, dan apa yang boleh ia lihat.
 *
 * TIDAK PERNAH disimpan di template. Dibangun ulang tiap kali laporan
 * dijalankan — itulah yang membuat satu template bisa dibagikan ke banyak
 * leader dan tiap orang menerima barisnya sendiri.
 */
class Lingkup
{
    /** @param string[] $anggotaTim */
    public function __construct(
        public readonly string $kodeKaryawan,
        public readonly bool $adminPenuh,
        public readonly array $anggotaTim,
        public readonly bool $bolehFieldTerbatas,
    ) {}
}
```

`Kontrak/DatasetLaporan.php`:

```php
<?php

namespace App\Services\SYSTEM\Application\Reports\Kontrak;

use Illuminate\Database\Query\Builder;

/**
 * Satu dataset = satu query yang bisa di-GROUP BY.
 *
 * Bukan batasan gaya: inilah yang membuat agregasi turun ke SQL Server alih-alih
 * menarik puluhan ribu baris ke memori worker. Modul yang butuh prefetch harus
 * menyatakannya sebagai subquery/CTE di dalam baseQuery(), bukan sebagai
 * rangkaian query yang dijahit di PHP.
 */
interface DatasetLaporan
{
    public function kunci(): string;

    public function label(): string;

    /** Satu kalimat yang menjelaskan arti satu baris. Tampil di UI. */
    public function grain(): string;

    /** @return Dimensi[] */
    public function dimensi(): array;

    /** @return Ukuran[] */
    public function ukuran(): array;

    /** @return Saringan[] */
    public function saringan(): array;

    public function baseQuery(): Builder;

    public function terapkanLingkup(Builder $q, Lingkup $lingkup): Builder;
}
```

- [ ] **Step 4: Implementasi registry**

```php
<?php

namespace App\Services\SYSTEM\Application\Reports;

use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;
use RuntimeException;

/**
 * Katalog dataset per modul.
 *
 * Kunci penyimpanannya (module, kunci) dan bukan kunci saja: modul boleh punya
 * dataset bernama sama tanpa bertabrakan, dan yang lebih penting, satu modul
 * tidak bisa menjangkau dataset modul lain hanya dengan menebak namanya.
 */
class DatasetRegistry
{
    /** @var array<string, array<string, DatasetLaporan>> */
    private array $peta = [];

    public function daftarkan(string $module, DatasetLaporan $dataset): void
    {
        $this->peta[$module][$dataset->kunci()] = $dataset;
    }

    public function ambil(string $module, string $kunci): DatasetLaporan
    {
        // Sengaja melempar, bukan mengembalikan null: pemanggil yang lupa
        // memeriksa null akan meneruskan nilai kosong ke compiler dan gagal
        // jauh dari sebabnya.
        if (! isset($this->peta[$module][$kunci])) {
            throw new RuntimeException("Dataset tidak dikenal: {$module}/{$kunci}");
        }

        return $this->peta[$module][$kunci];
    }

    /** @return array<string, DatasetLaporan> */
    public function semua(string $module): array
    {
        return $this->peta[$module] ?? [];
    }
}
```

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `unset DATABASE_URL && php artisan test --filter=DatasetRegistryTest`
Expected: PASS, 4 test

- [ ] **Step 6: Commit**

```bash
git add app/Services/SYSTEM/Application/Reports/Kontrak/ \
        app/Services/SYSTEM/Application/Reports/DatasetRegistry.php \
        tests/Unit/SYSTEM/Application/Reports/DatasetRegistryTest.php
git commit -m "feat(reports): kontrak dataset dan registry per modul"
```

---

### Task 4: Definisi laporan dan validator

**Files:**
- Create: `app/Services/SYSTEM/Application/Reports/DefinisiLaporan.php`
- Create: `app/Services/SYSTEM/Application/Reports/DefinisiValidator.php`
- Test: `tests/Unit/SYSTEM/Application/Reports/DefinisiValidatorTest.php`

**Interfaces:**
- Consumes: `DatasetLaporan`, `Dimensi`, `Ukuran`, `Saringan` (Task 3).
- Produces:
  - `DefinisiLaporan::dariArray(array $a): self` dengan properti publik
    `dataset, mode, dimensi[], ukuran[], kolom[], saringan[], urut[], opsi[], bagan, formatBawaan`
  - `DefinisiLaporan::toArray(): array`
  - `DefinisiValidator::periksa(DefinisiLaporan $d, DatasetLaporan $ds): array`
    — mengembalikan `[]` bila sah, atau `['<field>' => ['pesan', ...]]`

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

namespace Tests\Unit\SYSTEM\Application\Reports;

use App\Services\SYSTEM\Application\Reports\DefinisiLaporan;
use App\Services\SYSTEM\Application\Reports\DefinisiValidator;
use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Dimensi;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\Kontrak\Saringan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Ukuran;
use Tests\TestCase;

class DefinisiValidatorTest extends TestCase
{
    private function dataset(): DatasetLaporan
    {
        return new class implements DatasetLaporan {
            public function kunci(): string { return 'kepatuhan'; }
            public function label(): string { return 'Kepatuhan'; }
            public function grain(): string { return 'karyawan x paket x item'; }

            public function dimensi(): array
            {
                return [
                    new Dimensi('divisi', 'Divisi', 'k.Divisi', 'teks', 'identitas'),
                    new Dimensi('paket', 'Paket', 'mp.Nama_Paket', 'teks', 'paket'),
                    new Dimensi('ip', 'IP', 'pr.Konfirmasi_IP', 'teks', 'atestasi', true),
                ];
            }

            public function ukuran(): array
            {
                return [
                    new Ukuran('jml_karyawan', 'Karyawan', 'COUNT(DISTINCT d.Kode_Karyawan)', 'angka'),
                    new Ukuran('persen_selesai', 'Selesai', 'AVG(x)', 'persen'),
                ];
            }

            public function saringan(): array
            {
                return [
                    new Saringan('periode', 'Periode', 'd.Tanggal_Assign', 'tanggal', ['antara']),
                    new Saringan('divisi', 'Divisi', 'k.Divisi', 'teks', ['salah_satu']),
                ];
            }

            public function baseQuery(): \Illuminate\Database\Query\Builder { return \DB::table('N_HRIS_Report_Template'); }
            public function terapkanLingkup($q, Lingkup $l) { return $q; }
        };
    }

    private function periksa(array $definisi): array
    {
        return (new DefinisiValidator())->periksa(
            DefinisiLaporan::dariArray($definisi),
            $this->dataset()
        );
    }

    private function sah(): array
    {
        return [
            'dataset' => 'kepatuhan',
            'mode' => 'ringkasan',
            'dimensi' => ['divisi'],
            'ukuran' => ['jml_karyawan'],
            'saringan' => [['field' => 'divisi', 'op' => 'salah_satu', 'nilai' => ['PROD']]],
        ];
    }

    public function test_definisi_sah_lolos(): void
    {
        $this->assertSame([], $this->periksa($this->sah()));
    }

    public function test_dimensi_tak_dikenal_ditolak(): void
    {
        $d = $this->sah();
        $d['dimensi'] = ['divisi', 'tidak_ada'];

        $this->assertArrayHasKey('dimensi', $this->periksa($d));
    }

    /**
     * Kunci bermusuhan harus DITOLAK, bukan di-escape. Klaim "injeksi mustahil
     * secara konstruksi" hanya benar selama tak ada satu pun jalur yang
     * menerima string bebas menuju SQL.
     */
    public function test_kunci_bermusuhan_ditolak_bukan_di_escape(): void
    {
        $d = $this->sah();
        $d['dimensi'] = ["divisi'; DROP TABLE N_HRIS_Report_Template; --"];

        $this->assertArrayHasKey('dimensi', $this->periksa($d));
    }

    public function test_ukuran_tak_dikenal_ditolak(): void
    {
        $d = $this->sah();
        $d['ukuran'] = ['tidak_ada'];

        $this->assertArrayHasKey('ukuran', $this->periksa($d));
    }

    public function test_ringkasan_tanpa_ukuran_ditolak(): void
    {
        $d = $this->sah();
        $d['ukuran'] = [];

        $this->assertArrayHasKey('ukuran', $this->periksa($d));
    }

    public function test_rinci_tanpa_kolom_ditolak(): void
    {
        $this->assertArrayHasKey('kolom', $this->periksa([
            'dataset' => 'kepatuhan',
            'mode' => 'rinci',
            'kolom' => [],
        ]));
    }

    public function test_dimensi_lebih_dari_enam_ditolak(): void
    {
        $d = $this->sah();
        $d['dimensi'] = array_fill(0, 7, 'divisi');

        $this->assertArrayHasKey('dimensi', $this->periksa($d));
    }

    public function test_operator_tak_didukung_tipe_field_ditolak(): void
    {
        $d = $this->sah();
        $d['saringan'] = [['field' => 'divisi', 'op' => 'antara', 'nilai' => ['a', 'b']]];

        $this->assertArrayHasKey('saringan', $this->periksa($d));
    }

    public function test_bagan_di_luar_pilihan_ditolak(): void
    {
        $d = $this->sah();
        $d['bagan'] = ['tipe' => 'batang', 'dimensi' => 'paket', 'ukuran' => ['jml_karyawan']];

        $this->assertArrayHasKey('bagan', $this->periksa($d));
    }

    public function test_mode_tak_dikenal_ditolak(): void
    {
        $d = $this->sah();
        $d['mode'] = 'pivot3d';

        $this->assertArrayHasKey('mode', $this->periksa($d));
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `unset DATABASE_URL && php artisan test --filter=DefinisiValidatorTest`
Expected: FAIL — class `DefinisiLaporan` tidak ditemukan

- [ ] **Step 3: Implementasi `DefinisiLaporan`**

```php
<?php

namespace App\Services\SYSTEM\Application\Reports;

/**
 * Definisi laporan — seluruh nilainya adalah KUNCI KATALOG, tak pernah SQL.
 *
 * Itu sebabnya injeksi mustahil secara konstruksi: tidak ada satu pun jalur
 * dari sini menuju query yang membawa string bebas dari pengguna. Kunci yang
 * tak dikenal ditolak validator, bukan di-escape.
 */
class DefinisiLaporan
{
    public function __construct(
        public readonly string $dataset,
        public readonly string $mode,          // ringkasan | rinci
        public readonly array $dimensi,
        public readonly array $ukuran,
        public readonly array $kolom,
        public readonly array $saringan,
        public readonly array $urut,
        public readonly array $opsi,
        public readonly ?array $bagan,
        public readonly string $formatBawaan,
        public readonly int $versi,
    ) {}

    public static function dariArray(array $a): self
    {
        return new self(
            dataset: (string) ($a['dataset'] ?? ''),
            mode: strtolower((string) ($a['mode'] ?? 'ringkasan')),
            dimensi: array_values((array) ($a['dimensi'] ?? [])),
            ukuran: array_values((array) ($a['ukuran'] ?? [])),
            kolom: array_values((array) ($a['kolom'] ?? [])),
            saringan: array_values((array) ($a['saringan'] ?? [])),
            urut: array_values((array) ($a['urut'] ?? [])),
            opsi: (array) ($a['opsi'] ?? []),
            bagan: isset($a['bagan']) && is_array($a['bagan']) ? $a['bagan'] : null,
            formatBawaan: (string) ($a['format_bawaan'] ?? 'xlsx'),
            versi: (int) ($a['versi'] ?? 1),
        );
    }

    public function toArray(): array
    {
        return [
            'versi' => $this->versi,
            'dataset' => $this->dataset,
            'mode' => $this->mode,
            'dimensi' => $this->dimensi,
            'ukuran' => $this->ukuran,
            'kolom' => $this->kolom,
            'saringan' => $this->saringan,
            'urut' => $this->urut,
            'opsi' => $this->opsi,
            'bagan' => $this->bagan,
            'format_bawaan' => $this->formatBawaan,
        ];
    }
}
```

- [ ] **Step 4: Implementasi `DefinisiValidator`**

```php
<?php

namespace App\Services\SYSTEM\Application\Reports;

use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;

/**
 * Memeriksa definisi terhadap katalog dataset.
 *
 * Dijalankan DUA KALI: saat simpan dan saat jalan. Sebuah template bisa menjadi
 * tak sah tanpa disentuh siapa pun — field dihapus dari katalog, atau hak
 * penjalankannya berubah. Galat saat jalan WAJIB menyebut field mana yang
 * bermasalah; "template rusak" tanpa nama field memaksa pengguna membangun
 * ulang dari nol.
 */
class DefinisiValidator
{
    public const MAKS_DIMENSI = 6;
    public const MAKS_UKURAN = 12;
    public const MAKS_KOLOM = 60;
    public const MAKS_SARINGAN = 20;

    private const MODE = ['ringkasan', 'rinci'];

    private const OPERATOR_PER_TIPE = [
        'teks' => ['sama_dengan', 'salah_satu', 'mengandung'],
        'angka' => ['sama_dengan', 'salah_satu', 'antara'],
        'tanggal' => ['antara', 'sama_dengan'],
    ];

    /** @return array<string, string[]> kosong bila sah */
    public function periksa(DefinisiLaporan $d, DatasetLaporan $ds): array
    {
        $galat = [];

        $dimensiSah = $this->kunci($ds->dimensi());
        $ukuranSah = $this->kunci($ds->ukuran());
        $saringanSah = [];
        foreach ($ds->saringan() as $s) {
            $saringanSah[$s->key] = $s;
        }

        if (! in_array($d->mode, self::MODE, true)) {
            $galat['mode'][] = "Mode tidak dikenal: {$d->mode}";
        }

        foreach ($d->dimensi as $k) {
            if (! is_string($k) || ! isset($dimensiSah[$k])) {
                $galat['dimensi'][] = 'Dimensi tidak dikenal: '.(is_string($k) ? $k : gettype($k));
            }
        }
        if (count($d->dimensi) > self::MAKS_DIMENSI) {
            $galat['dimensi'][] = 'Maksimal '.self::MAKS_DIMENSI.' dimensi.';
        }

        foreach ($d->ukuran as $k) {
            if (! is_string($k) || ! isset($ukuranSah[$k])) {
                $galat['ukuran'][] = 'Ukuran tidak dikenal: '.(is_string($k) ? $k : gettype($k));
            }
        }
        if (count($d->ukuran) > self::MAKS_UKURAN) {
            $galat['ukuran'][] = 'Maksimal '.self::MAKS_UKURAN.' ukuran.';
        }

        foreach ($d->kolom as $k) {
            if (! is_string($k) || ! isset($dimensiSah[$k])) {
                $galat['kolom'][] = 'Kolom tidak dikenal: '.(is_string($k) ? $k : gettype($k));
            }
        }
        if (count($d->kolom) > self::MAKS_KOLOM) {
            $galat['kolom'][] = 'Maksimal '.self::MAKS_KOLOM.' kolom.';
        }

        if ($d->mode === 'ringkasan' && count($d->ukuran) === 0) {
            $galat['ukuran'][] = 'Mode ringkasan membutuhkan minimal satu ukuran.';
        }
        if ($d->mode === 'rinci' && count($d->kolom) === 0) {
            $galat['kolom'][] = 'Mode rinci membutuhkan minimal satu kolom.';
        }

        if (count($d->saringan) > self::MAKS_SARINGAN) {
            $galat['saringan'][] = 'Maksimal '.self::MAKS_SARINGAN.' saringan.';
        }
        foreach ($d->saringan as $s) {
            $field = is_array($s) ? ($s['field'] ?? null) : null;
            $op = is_array($s) ? ($s['op'] ?? null) : null;

            if (! is_string($field) || ! isset($saringanSah[$field])) {
                $galat['saringan'][] = 'Saringan tidak dikenal: '.(is_string($field) ? $field : gettype($field));

                continue;
            }

            $def = $saringanSah[$field];
            $diizinkanTipe = self::OPERATOR_PER_TIPE[$def->tipe] ?? [];

            if (! is_string($op) || ! in_array($op, $def->operator, true) || ! in_array($op, $diizinkanTipe, true)) {
                $galat['saringan'][] = "Operator '{$op}' tidak berlaku untuk field '{$field}' bertipe {$def->tipe}.";
            }
        }

        if ($d->bagan !== null) {
            $dim = $d->bagan['dimensi'] ?? null;
            if (! in_array($dim, $d->dimensi, true)) {
                $galat['bagan'][] = 'Dimensi bagan harus salah satu dimensi yang dipilih.';
            }
            foreach ((array) ($d->bagan['ukuran'] ?? []) as $u) {
                if (! in_array($u, $d->ukuran, true)) {
                    $galat['bagan'][] = 'Ukuran bagan harus salah satu ukuran yang dipilih.';
                }
            }
        }

        return $galat;
    }

    /** @return array<string, object> */
    private function kunci(array $daftar): array
    {
        $peta = [];
        foreach ($daftar as $item) {
            $peta[$item->key] = $item;
        }

        return $peta;
    }
}
```

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `unset DATABASE_URL && php artisan test --filter=DefinisiValidatorTest`
Expected: PASS, 10 test

- [ ] **Step 6: Commit**

```bash
git add app/Services/SYSTEM/Application/Reports/DefinisiLaporan.php \
        app/Services/SYSTEM/Application/Reports/DefinisiValidator.php \
        tests/Unit/SYSTEM/Application/Reports/DefinisiValidatorTest.php
git commit -m "feat(reports): definisi laporan dan validator dua-tahap"
```

---

### Task 5: Dataset `kepatuhan` SOP

**Files:**
- Create: `app/Services/LMS/SOP/Export/SopDatasetKepatuhan.php`
- Create: `app/Services/LMS/SOP/Export/SopReportServiceProvider.php`
- Modify: `config/app.php` (daftarkan provider) atau `bootstrap/providers.php`
  sesuai versi Laravel yang dipakai repo — periksa mana yang ada lebih dulu.
- Test: `tests/Unit/LMS/SOP/Export/SopDatasetKepatuhanTest.php`

**Interfaces:**
- Consumes: `DatasetLaporan`, `Dimensi`, `Ukuran`, `Saringan`, `Lingkup`,
  `DatasetRegistry` (Task 3).
- Produces: `SopDatasetKepatuhan` terdaftar di registry dengan modul `'sop'` dan
  kunci `'kepatuhan'`.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

namespace Tests\Unit\LMS\SOP\Export;

use App\Services\LMS\SOP\Export\SopDatasetKepatuhan;
use App\Services\SYSTEM\Application\Reports\DatasetRegistry;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use Tests\TestCase;

class SopDatasetKepatuhanTest extends TestCase
{
    private function ds(): SopDatasetKepatuhan
    {
        return app(SopDatasetKepatuhan::class);
    }

    public function test_terdaftar_di_registry_modul_sop(): void
    {
        $this->assertSame('kepatuhan', app(DatasetRegistry::class)->ambil('sop', 'kepatuhan')->kunci());
    }

    public function test_kunci_dimensi_dan_ukuran_unik(): void
    {
        $dim = array_map(fn ($d) => $d->key, $this->ds()->dimensi());
        $ukr = array_map(fn ($u) => $u->key, $this->ds()->ukuran());

        $this->assertSame(array_unique($dim), $dim, 'Ada kunci dimensi ganda.');
        $this->assertSame(array_unique($ukr), $ukr, 'Ada kunci ukuran ganda.');
    }

    /**
     * Penjaga kelas kebocoran: COUNT(DISTINCT ip) membocorkan persis apa yang
     * di-mask di kolomnya. Ukuran yang menyebut ekspresi field sensitif harus
     * ikut bertanda sensitif.
     */
    public function test_ukuran_turunan_field_sensitif_ikut_sensitif(): void
    {
        $ekspresiSensitif = [];
        foreach ($this->ds()->dimensi() as $d) {
            if ($d->sensitif) {
                $ekspresiSensitif[] = $d->select;
            }
        }

        foreach ($this->ds()->ukuran() as $u) {
            foreach ($ekspresiSensitif as $ekspresi) {
                if (str_contains($u->agregat, $ekspresi) && ! $u->sensitif) {
                    $this->fail("Ukuran '{$u->key}' memakai field sensitif tapi tidak ditandai sensitif.");
                }
            }
        }

        $this->assertTrue(true);
    }

    public function test_ip_dan_perangkat_ditandai_sensitif(): void
    {
        $peta = [];
        foreach ($this->ds()->dimensi() as $d) {
            $peta[$d->key] = $d->sensitif;
        }

        $this->assertTrue($peta['ip'] ?? false);
        $this->assertTrue($peta['perangkat'] ?? false);
    }

    public function test_base_query_memaksa_aturan_bisnis(): void
    {
        $sql = $this->ds()->baseQuery()->toSql();

        $this->assertStringContainsString('N_SOP_Penjadwalan_Detail', $sql);
        $this->assertStringContainsString('left join', strtolower($sql), 'Join ke progress harus LEFT — inner join menghapus item yang belum pernah dibuka dan membuat kepatuhan terbaca lebih baik daripada kenyataannya.');
    }

    public function test_lingkup_leader_menyaring_ke_anggota_tim(): void
    {
        $q = $this->ds()->terapkanLingkup(
            $this->ds()->baseQuery(),
            new Lingkup('L001', false, ['A001', 'A002'], false)
        );

        $this->assertContains('A001', $q->getBindings());
        $this->assertContains('A002', $q->getBindings());
    }

    public function test_lingkup_admin_tidak_menyaring(): void
    {
        $sebelum = $this->ds()->baseQuery()->toSql();
        $sesudah = $this->ds()
            ->terapkanLingkup($this->ds()->baseQuery(), new Lingkup('AD1', true, [], true))
            ->toSql();

        $this->assertSame($sebelum, $sesudah);
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `unset DATABASE_URL && php artisan test --filter=SopDatasetKepatuhanTest`
Expected: FAIL — class `SopDatasetKepatuhan` tidak ditemukan

- [ ] **Step 3: Implementasi dataset**

Katalog lengkapnya mengambil ulang 38 kolom di
`SopExportRinciService::columns()`. Buka berkas itu dan salin label serta
pengelompokannya supaya keduanya tak berbeda. Kerangkanya:

```php
<?php

namespace App\Services\LMS\SOP\Export;

use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Dimensi;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\Kontrak\Saringan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Ukuran;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Satu baris = satu (karyawan x penugasan paket x item).
 *
 * TIGA ATURAN BISNIS DIPATRI DI baseQuery() DAN TIDAK BISA DIMATIKAN TEMPLATE:
 *
 *  1. d.Flag_Aktif = 'Y'
 *  2. d.Flag_Cabut_Akses = 'T' — penugasan yang aksesnya dicabut bukan lagi
 *     kewajiban karyawan; seluruh layar Monitoring sudah mengecualikannya.
 *  3. Join ke N_SOP_Progress adalah LEFT. Item yang belum pernah dibuka TIDAK
 *     punya baris progress; inner join menghapusnya dari laporan sehingga
 *     tunggakan lenyap dan kepatuhan terbaca lebih baik daripada kenyataannya.
 */
class SopDatasetKepatuhan implements DatasetLaporan
{
    public function kunci(): string
    {
        return 'kepatuhan';
    }

    public function label(): string
    {
        return 'Kepatuhan & Penugasan SOP';
    }

    public function grain(): string
    {
        return 'Satu baris = satu karyawan × paket × item.';
    }

    public function dimensi(): array
    {
        return [
            new Dimensi('kode', 'Kode Karyawan', 'd.Kode_Karyawan', 'teks', 'identitas'),
            new Dimensi('nama', 'Nama Karyawan', 'k.Nama', 'teks', 'identitas'),
            new Dimensi('no_induk', 'No. Induk', 'k.No_Induk_Karyawan', 'teks', 'identitas'),
            new Dimensi('lokasi', 'Lokasi Kerja', 'k.Lokasi', 'teks', 'identitas'),
            new Dimensi('divisi', 'Divisi', 'k.Divisi', 'teks', 'identitas'),
            new Dimensi('departemen', 'Departemen', 'k.Departemen', 'teks', 'identitas'),
            new Dimensi('jabatan', 'Jabatan', 'k.Jabatan', 'teks', 'identitas'),

            new Dimensi('paket', 'Nama Paket SOP', 'mp.Nama_Paket', 'teks', 'paket'),
            new Dimensi('kode_paket', 'Kode Paket', 'mp.Kode_Paket', 'teks', 'paket'),
            new Dimensi('kategori', 'Kategori', 'kt.Nama_Kategori', 'teks', 'paket'),
            new Dimensi('prioritas', 'Prioritas', 'mp.Prioritas', 'teks', 'paket'),
            new Dimensi('status_paket', 'Status Paket', 'd.Status_Paket', 'teks', 'paket'),
            new Dimensi('tenggat', 'Tenggat', 'pj.Tanggal_Deadline', 'tanggal', 'paket'),

            new Dimensi('section', 'Section', 'ps.Nama_Section', 'teks', 'item'),
            new Dimensi('item', 'Judul Item', 'pi.Judul_Item', 'teks', 'item'),
            new Dimensi('nomor_sop', 'No. SOP Resmi', 'pi.Nomor_SOP_Resmi', 'teks', 'item'),
            new Dimensi('tipe_item', 'Tipe Item', 'pi.Tipe_Item', 'teks', 'item'),
            new Dimensi('wajib', 'Wajib', 'pi.Flag_Wajib', 'teks', 'item'),

            new Dimensi('status_item', 'Status Item', 'pr.Status', 'teks', 'versi'),
            new Dimensi('waktu_konfirmasi', 'Waktu Konfirmasi', 'pr.Waktu_Konfirmasi', 'tanggal', 'baca'),
            new Dimensi('bulan_konfirmasi', 'Bulan Konfirmasi', "FORMAT(pr.Waktu_Konfirmasi, 'yyyy-MM')", 'teks', 'baca'),

            new Dimensi('ip', 'IP Konfirmasi', 'pr.Konfirmasi_IP', 'teks', 'atestasi', true),
            new Dimensi('perangkat', 'Perangkat', 'pr.Konfirmasi_User_Agent', 'teks', 'atestasi', true),
        ];
    }

    public function ukuran(): array
    {
        $selesai = "CASE WHEN pr.Status IN ('".SopStatus::ITEM_SELESAI."','".SopStatus::ITEM_DIKONFIRMASI."') THEN 1 ELSE 0 END";

        return [
            new Ukuran('jml_karyawan', 'Jumlah Karyawan', 'COUNT(DISTINCT d.Kode_Karyawan)', 'angka'),
            new Ukuran('jml_penugasan', 'Jumlah Penugasan', 'COUNT(DISTINCT d.Id_SOP_Penjadwalan_Detail)', 'angka'),
            new Ukuran('jml_item', 'Jumlah Item', 'COUNT(*)', 'angka'),
            new Ukuran('jml_selesai', 'Item Selesai', "SUM({$selesai})", 'angka'),
            new Ukuran('persen_selesai', 'Persen Selesai', "CAST(SUM({$selesai}) * 100.0 / NULLIF(COUNT(*), 0) AS decimal(5,1))", 'persen'),
            new Ukuran('durasi_baca_rata', 'Durasi Baca Rata-rata', 'AVG(CAST(pr.Durasi_Detik AS float))', 'durasi'),
        ];
    }

    public function saringan(): array
    {
        return [
            new Saringan('periode', 'Periode Konfirmasi', 'pr.Waktu_Konfirmasi', 'tanggal', ['antara']),
            new Saringan('divisi', 'Divisi', 'k.Divisi', 'teks', ['salah_satu']),
            new Saringan('departemen', 'Departemen', 'k.Departemen', 'teks', ['salah_satu']),
            new Saringan('lokasi', 'Lokasi', 'k.Lokasi', 'teks', ['salah_satu']),
            new Saringan('paket', 'Paket', 'mp.Id_SOP_Paket', 'angka', ['salah_satu']),
            new Saringan('kategori', 'Kategori', 'mp.Id_Kategori_SOP', 'angka', ['salah_satu']),
            new Saringan('prioritas', 'Prioritas', 'mp.Prioritas', 'teks', ['salah_satu']),
            new Saringan('status_item', 'Status Item', 'pr.Status', 'teks', ['salah_satu']),
            new Saringan('kode_karyawan', 'Karyawan', 'd.Kode_Karyawan', 'teks', ['sama_dengan'], true),
        ];
    }

    public function baseQuery(): Builder
    {
        return DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Master_Paket as mp', 'mp.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->leftJoin('N_SOP_Kategori as kt', 'kt.Id_Kategori_SOP', '=', 'mp.Id_Kategori_SOP')
            ->join('N_SOP_Paket_Item as pi', 'pi.Id_SOP_Paket', '=', 'mp.Id_SOP_Paket')
            ->leftJoin('N_SOP_Paket_Section as ps', 'ps.Id_SOP_Paket_Section', '=', 'pi.Id_SOP_Paket_Section')
            ->leftJoin('HRIS_Karyawan as k', 'k.Kode_Karyawan', '=', 'd.Kode_Karyawan')
            // LEFT, bukan INNER. Lihat catatan kelas.
            ->leftJoin('N_SOP_Progress as pr', function ($j) {
                $j->on('pr.Id_SOP_Penjadwalan_Detail', '=', 'd.Id_SOP_Penjadwalan_Detail')
                    ->on('pr.Id_SOP_Paket_Item', '=', 'pi.Id_SOP_Paket_Item');
            })
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', SopStatus::INAKTIF)
            ->where('pi.Flag_Aktif', SopStatus::AKTIF);
    }

    public function terapkanLingkup(Builder $q, Lingkup $lingkup): Builder
    {
        if ($lingkup->adminPenuh) {
            return $q;
        }

        // Daftar kosong berarti "tak ada anggota tim" -> nol baris, bukan
        // seluruh baris. whereIn dengan array kosong menghasilkan 0=1, yang
        // memang perilaku yang benar di sini.
        return $q->whereIn('d.Kode_Karyawan', $lingkup->anggotaTim);
    }
}
```

**Sebelum menulis:** verifikasi nama kolom yang dipakai di atas terhadap
database. Sebagian (`pi.Id_SOP_Paket_Section`, `pr.Id_SOP_Paket_Item`,
`mp.Kode_Paket`, `k.Lokasi`) diambil dari pembacaan service existing dan wajib
dicek ke `sys.columns` sebelum dipakai:

Run: `unset DATABASE_URL && php artisan tinker --execute="foreach (['N_SOP_Paket_Item','N_SOP_Progress','N_SOP_Master_Paket','N_SOP_Paket_Section'] as \$t) { echo \$t.': '.implode(', ', array_map(fn(\$c) => \$c->name, DB::select('SELECT name FROM sys.columns WHERE object_id = OBJECT_ID(?)', [\$t]))).PHP_EOL.PHP_EOL; }"`

Sesuaikan ekspresi `select` ke kolom yang benar-benar ada. **Jangan menebak** —
kolom yang salah baru ketahuan saat query dijalankan, jauh dari sini.

**Hasil verifikasi 2026-08-18 (Web_HRIS).** Lima nama di kerangka atas ternyata
keliru dan sudah dikoreksi di sini:

| Ditulis di kerangka | Yang benar |
|---|---|
| `pi.Id_SOP_Paket_Section` | `pi.Id_SOP_Section` |
| `ps.Id_SOP_Paket_Section` | `ps.Id_SOP_Section` (PK tabel section) |
| `pr.Durasi_Detik` | `pr.Durasi_Aktif_Detik` |
| `pr.Skor_Kuis` | `pr.Skor_Quiz` |
| `d.Tanggal_Assign` | `pj.Tanggal_Assign` — kolomnya ada di `N_SOP_Penjadwalan`, bukan di `_Detail` |

Yang sudah benar: `mp.Kode_Paket`, `mp.Prioritas`, `d.Status_Paket`,
`pr.Konfirmasi_IP`, `pr.Konfirmasi_User_Agent`, `pi.Nomor_SOP_Resmi`,
`pi.Flag_Wajib`, `kt.Nama_Kategori`, `pj.Tanggal_Deadline`.

Perhatikan `N_SOP_Master_Paket` memakai kolom `Status` (status paket:
DRAFT/PUBLISHED/ARCHIVED), sedangkan `Status_Paket` adalah kolom
`N_SOP_Penjadwalan_Detail` (kepatuhan seseorang: BELUM/PROGRESS/SELESAI).
Keduanya berbeda arti dan mudah tertukar.

**Masih harus dicek sebelum menulis:** tabel karyawan. Kerangka memakai
`HRIS_Karyawan as k`, tetapi `SopTeamService` membaca `DB::table('Karyawan')`.
Jalankan:

```bash
unset DATABASE_URL && php artisan tinker --execute="
foreach (['Karyawan','HRIS_Karyawan'] as \\$t) {
    \\$n = DB::selectOne('SELECT COUNT(*) AS n FROM sys.tables WHERE name = ?', [\\$t])->n;
    echo \\$t.' => '.(\\$n ? 'ADA' : 'tidak ada').PHP_EOL;
}"
```

lalu pastikan kolom `Nama`, `No_Induk_Karyawan`, `Lokasi`, `Divisi`,
`Departemen`, `Jabatan` benar-benar ada di tabel yang dipilih.

- [ ] **Step 4: Daftarkan dataset lewat service provider**

```php
<?php

namespace App\Services\LMS\SOP\Export;

use App\Services\SYSTEM\Application\Reports\DatasetRegistry;
use Illuminate\Support\ServiceProvider;

class SopReportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DatasetRegistry::class, fn () => new DatasetRegistry());
    }

    public function boot(): void
    {
        $this->app->make(DatasetRegistry::class)
            ->daftarkan('sop', $this->app->make(SopDatasetKepatuhan::class));
    }
}
```

Daftarkan provider. Periksa dulu berkas mana yang dipakai repo:

Run: `ls bootstrap/providers.php 2>/dev/null || grep -n "'providers'" config/app.php`

Lalu tambahkan `App\Services\LMS\SOP\Export\SopReportServiceProvider::class` ke
daftar yang benar.

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `unset DATABASE_URL && php artisan test --filter=SopDatasetKepatuhanTest`
Expected: PASS, 7 test

- [ ] **Step 6: Commit**

```bash
git add app/Services/LMS/SOP/Export/SopDatasetKepatuhan.php \
        app/Services/LMS/SOP/Export/SopReportServiceProvider.php \
        tests/Unit/LMS/SOP/Export/SopDatasetKepatuhanTest.php
git add bootstrap/providers.php   # atau config/app.php, sesuai hasil Step 4
git commit -m "feat(sop): katalog dataset kepatuhan untuk Export Studio"
```

---

### Task 6: Compiler definisi → Query Builder

**Files:**
- Create: `app/Services/SYSTEM/Application/Reports/ReportCompiler.php`
- Test: `tests/Unit/SYSTEM/Application/Reports/ReportCompilerTest.php`

**Interfaces:**
- Consumes: `DefinisiLaporan`, `DatasetLaporan`, `Lingkup`.
- Produces: `ReportCompiler::susun(DefinisiLaporan $d, DatasetLaporan $ds, Lingkup $l): \Illuminate\Database\Query\Builder`

- [ ] **Step 1: Tulis test yang gagal**

Pakai dataset palsu yang sama seperti `DefinisiValidatorTest` (salin kelas
anonimnya — jangan merujuk "seperti Task 4", pembacanya mungkin membaca tugas
ini lebih dulu).

```php
<?php

namespace Tests\Unit\SYSTEM\Application\Reports;

use App\Services\SYSTEM\Application\Reports\DefinisiLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Dimensi;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\Kontrak\Saringan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Ukuran;
use App\Services\SYSTEM\Application\Reports\ReportCompiler;
use Tests\TestCase;

class ReportCompilerTest extends TestCase
{
    private function dataset(): DatasetLaporan
    {
        return new class implements DatasetLaporan {
            public function kunci(): string { return 'uji'; }
            public function label(): string { return 'Uji'; }
            public function grain(): string { return 'satu baris per template'; }

            public function dimensi(): array
            {
                return [
                    new Dimensi('modul', 'Modul', 't.Module', 'teks', 'inti'),
                    new Dimensi('mode', 'Mode', 't.Mode', 'teks', 'inti'),
                ];
            }

            public function ukuran(): array
            {
                return [new Ukuran('jml', 'Jumlah', 'COUNT(*)', 'angka')];
            }

            public function saringan(): array
            {
                return [
                    new Saringan('modul', 'Modul', 't.Module', 'teks', ['salah_satu']),
                    new Saringan('dibuat', 'Dibuat', 't.Created_At', 'tanggal', ['antara']),
                ];
            }

            public function baseQuery(): \Illuminate\Database\Query\Builder
            {
                return \DB::table('N_HRIS_Report_Template as t');
            }

            public function terapkanLingkup($q, Lingkup $l)
            {
                return $l->adminPenuh ? $q : $q->whereIn('t.Kode_Karyawan_Pemilik', $l->anggotaTim);
            }
        };
    }

    private function admin(): Lingkup
    {
        return new Lingkup('AD1', true, [], true);
    }

    private function susun(array $definisi, ?Lingkup $l = null)
    {
        return (new ReportCompiler())->susun(
            DefinisiLaporan::dariArray($definisi),
            $this->dataset(),
            $l ?? $this->admin()
        );
    }

    public function test_ringkasan_membentuk_group_by(): void
    {
        $sql = strtolower($this->susun([
            'dataset' => 'uji',
            'mode' => 'ringkasan',
            'dimensi' => ['modul'],
            'ukuran' => ['jml'],
        ])->toSql());

        $this->assertStringContainsString('group by', $sql);
        $this->assertStringContainsString('count(*)', $sql);
    }

    public function test_rinci_tidak_membentuk_group_by(): void
    {
        $sql = strtolower($this->susun([
            'dataset' => 'uji',
            'mode' => 'rinci',
            'kolom' => ['modul', 'mode'],
        ])->toSql());

        $this->assertStringNotContainsString('group by', $sql);
    }

    public function test_saringan_salah_satu_jadi_binding_bukan_literal(): void
    {
        $q = $this->susun([
            'dataset' => 'uji',
            'mode' => 'ringkasan',
            'dimensi' => ['modul'],
            'ukuran' => ['jml'],
            'saringan' => [['field' => 'modul', 'op' => 'salah_satu', 'nilai' => ['sop', 'kpi']]],
        ]);

        $this->assertContains('sop', $q->getBindings());
        $this->assertStringNotContainsString('sop', $q->toSql());
    }

    public function test_saringan_antara_memakai_dua_batas(): void
    {
        $q = $this->susun([
            'dataset' => 'uji',
            'mode' => 'ringkasan',
            'dimensi' => ['modul'],
            'ukuran' => ['jml'],
            'saringan' => [['field' => 'dibuat', 'op' => 'antara', 'nilai' => ['2026-01-01', '2026-03-31']]],
        ]);

        $this->assertContains('2026-01-01', $q->getBindings());
        $this->assertContains('2026-03-31', $q->getBindings());
    }

    public function test_lingkup_leader_ikut_tersusun(): void
    {
        $q = $this->susun(
            ['dataset' => 'uji', 'mode' => 'ringkasan', 'dimensi' => ['modul'], 'ukuran' => ['jml']],
            new Lingkup('L1', false, ['A1'], false)
        );

        $this->assertContains('A1', $q->getBindings());
    }

    public function test_urut_hanya_menerima_kunci_terpilih(): void
    {
        $sql = strtolower($this->susun([
            'dataset' => 'uji',
            'mode' => 'ringkasan',
            'dimensi' => ['modul'],
            'ukuran' => ['jml'],
            'urut' => [['key' => 'jml', 'arah' => 'desc'], ['key' => 'tidak_dipilih', 'arah' => 'asc']],
        ])->toSql());

        $this->assertStringContainsString('order by', $sql);
        $this->assertStringNotContainsString('tidak_dipilih', $sql);
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `unset DATABASE_URL && php artisan test --filter=ReportCompilerTest`
Expected: FAIL — class `ReportCompiler` tidak ditemukan

- [ ] **Step 3: Implementasi compiler**

```php
<?php

namespace App\Services\SYSTEM\Application\Reports;

use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Definisi + dataset + lingkup -> satu Query Builder.
 *
 * SELURUH ekspresi SQL berasal dari katalog, tak satu pun dari pengguna. Yang
 * dibawa pengguna hanyalah KUNCI dan NILAI saringan; kunci dicocokkan ke
 * katalog (yang tak cocok dibuang di sini dan ditolak validator lebih dulu),
 * nilai selalu jadi binding.
 *
 * Agregasi diturunkan ke GROUP BY di SQL Server, bukan dihitung di PHP setelah
 * menarik seluruh baris — perbedaannya adalah ratusan baris hasil versus
 * puluhan ribu baris mentah di memori worker.
 */
class ReportCompiler
{
    public function susun(DefinisiLaporan $d, DatasetLaporan $ds, Lingkup $lingkup): Builder
    {
        $dimensi = $this->petakan($ds->dimensi());
        $ukuran = $this->petakan($ds->ukuran());
        $saringan = $this->petakan($ds->saringan());

        $q = $ds->terapkanLingkup($ds->baseQuery(), $lingkup);

        foreach ($d->saringan as $s) {
            if (! is_array($s) || ! isset($saringan[$s['field'] ?? ''])) {
                continue;
            }
            $this->terapkanSatuSaringan($q, $saringan[$s['field']]->kolom, (string) $s['op'], $s['nilai'] ?? null);
        }

        if ($d->mode === 'rinci') {
            $pilih = [];
            foreach ($d->kolom as $k) {
                if (isset($dimensi[$k])) {
                    $pilih[] = DB::raw($dimensi[$k]->select.' AS ['.$k.']');
                }
            }
            $q->select($pilih);

            $this->terapkanUrut($q, $d->urut, $d->kolom);

            return $q;
        }

        $pilih = [];
        foreach ($d->dimensi as $k) {
            if (isset($dimensi[$k])) {
                $pilih[] = DB::raw($dimensi[$k]->select.' AS ['.$k.']');
                $q->groupBy(DB::raw($dimensi[$k]->select));
            }
        }
        foreach ($d->ukuran as $k) {
            if (isset($ukuran[$k])) {
                $pilih[] = DB::raw($ukuran[$k]->agregat.' AS ['.$k.']');
            }
        }
        $q->select($pilih);

        $this->terapkanUrut($q, $d->urut, array_merge($d->dimensi, $d->ukuran));

        return $q;
    }

    private function terapkanSatuSaringan(Builder $q, string $kolom, string $op, $nilai): void
    {
        $kolomRaw = DB::raw($kolom);

        match ($op) {
            'salah_satu' => $q->whereIn($kolomRaw, (array) $nilai),
            'antara' => $q->whereBetween($kolomRaw, [((array) $nilai)[0] ?? null, ((array) $nilai)[1] ?? null]),
            'mengandung' => $q->where($kolomRaw, 'like', '%'.(is_array($nilai) ? '' : $nilai).'%'),
            default => $q->where($kolomRaw, '=', is_array($nilai) ? ($nilai[0] ?? null) : $nilai),
        };
    }

    private function terapkanUrut(Builder $q, array $urut, array $kunciDipilih): void
    {
        foreach ($urut as $u) {
            $key = is_array($u) ? ($u['key'] ?? null) : null;

            // Mengurut berdasarkan kunci yang tidak dipilih akan merujuk alias
            // yang tak ada di SELECT. Dibuang diam-diam, bukan digagalkan:
            // validator sudah menolaknya lebih dulu di jalur simpan.
            if (! is_string($key) || ! in_array($key, $kunciDipilih, true)) {
                continue;
            }

            $arah = (is_array($u) && ($u['arah'] ?? 'asc') === 'desc') ? 'desc' : 'asc';
            $q->orderBy(DB::raw('['.$key.']'), $arah);
        }
    }

    /** @return array<string, object> */
    private function petakan(array $daftar): array
    {
        $peta = [];
        foreach ($daftar as $item) {
            $peta[$item->key] = $item;
        }

        return $peta;
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `unset DATABASE_URL && php artisan test --filter=ReportCompilerTest`
Expected: PASS, 6 test

- [ ] **Step 5: Commit**

```bash
git add app/Services/SYSTEM/Application/Reports/ReportCompiler.php \
        tests/Unit/SYSTEM/Application/Reports/ReportCompilerTest.php
git commit -m "feat(reports): compiler definisi ke query builder ber-GROUP BY"
```

---

### Task 7: Runner — bentuk hasil, masking, subtotal

**Files:**
- Create: `app/Services/SYSTEM/Application/Reports/ReportRunner.php`
- Test: `tests/Unit/SYSTEM/Application/Reports/ReportRunnerTest.php`

**Interfaces:**
- Consumes: `ReportCompiler`, `DefinisiLaporan`, `DatasetLaporan`, `Lingkup`.
- Produces: `ReportRunner::jalankan(DefinisiLaporan $d, DatasetLaporan $ds, Lingkup $l, string $judul, ?int $batas = null): array`
  mengembalikan `['title' => string, 'generated_at' => string, 'columns' => array, 'rows' => array, 'catatan' => string[]]`
  — bentuk yang sama dengan dataset legacy, ditambah `catatan`.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

namespace Tests\Unit\SYSTEM\Application\Reports;

use App\Services\SYSTEM\Application\Reports\DefinisiLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Dimensi;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\Kontrak\Saringan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Ukuran;
use App\Services\SYSTEM\Application\Reports\ReportRunner;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportRunnerTest extends TestCase
{
    private function dataset(): DatasetLaporan
    {
        return new class implements DatasetLaporan {
            public function kunci(): string { return 'uji'; }
            public function label(): string { return 'Uji'; }
            public function grain(): string { return 'satu baris per template'; }

            public function dimensi(): array
            {
                return [
                    new Dimensi('modul', 'Modul', 't.Module', 'teks', 'inti'),
                    new Dimensi('pemilik', 'Pemilik', 't.Kode_Karyawan_Pemilik', 'teks', 'rahasia', true),
                ];
            }

            public function ukuran(): array { return [new Ukuran('jml', 'Jumlah', 'COUNT(*)', 'angka')]; }
            public function saringan(): array { return [new Saringan('modul', 'Modul', 't.Module', 'teks', ['salah_satu'])]; }
            public function baseQuery(): \Illuminate\Database\Query\Builder { return DB::table('N_HRIS_Report_Template as t'); }
            public function terapkanLingkup($q, Lingkup $l) { return $q; }
        };
    }

    private function jalankan(array $definisi, Lingkup $l, ?int $batas = null): array
    {
        return app(ReportRunner::class)->jalankan(
            DefinisiLaporan::dariArray($definisi),
            $this->dataset(),
            $l,
            'LAPORAN UJI',
            $batas
        );
    }

    public function test_bentuk_hasil_sama_dengan_kontrak_legacy(): void
    {
        $hasil = $this->jalankan(
            ['dataset' => 'uji', 'mode' => 'ringkasan', 'dimensi' => ['modul'], 'ukuran' => ['jml']],
            new Lingkup('AD1', true, [], true)
        );

        foreach (['title', 'generated_at', 'columns', 'rows'] as $k) {
            $this->assertArrayHasKey($k, $hasil);
        }
        $this->assertSame('LAPORAN UJI', $hasil['title']);
        $this->assertSame(['modul', 'jml'], array_column($hasil['columns'], 'key'));
    }

    public function test_field_sensitif_di_mask_bukan_dihapus(): void
    {
        DB::table('N_HRIS_Report_Template')->insert([
            'Module' => 'uji-runner', 'Kode' => 'uji-runner-1', 'Nama' => 'Uji',
            'Dataset_Key' => 'uji', 'Mode' => 'RINCI', 'Definisi_Json' => '{}',
            'Tingkat_Bagi' => 'PRIBADI', 'Kode_Karyawan_Pemilik' => 'RAHASIA1',
        ]);

        $hasil = $this->jalankan(
            [
                'dataset' => 'uji',
                'mode' => 'rinci',
                'kolom' => ['modul', 'pemilik'],
                'saringan' => [['field' => 'modul', 'op' => 'salah_satu', 'nilai' => ['uji-runner']]],
            ],
            new Lingkup('L1', false, [], false)
        );

        DB::table('N_HRIS_Report_Template')->where('Module', 'uji-runner')->delete();

        // Kolomnya TETAP ADA — menghapusnya mengubah bentuk hasil dan merusak
        // pivot yang dibangun di atas template yang sama untuk orang lain.
        $this->assertSame(['modul', 'pemilik'], array_column($hasil['columns'], 'key'));
        $this->assertSame('—', $hasil['rows'][0]['pemilik']);
        $this->assertNotEmpty($hasil['catatan']);
    }

    public function test_field_sensitif_utuh_untuk_yang_berhak(): void
    {
        DB::table('N_HRIS_Report_Template')->insert([
            'Module' => 'uji-runner2', 'Kode' => 'uji-runner-2', 'Nama' => 'Uji',
            'Dataset_Key' => 'uji', 'Mode' => 'RINCI', 'Definisi_Json' => '{}',
            'Tingkat_Bagi' => 'PRIBADI', 'Kode_Karyawan_Pemilik' => 'RAHASIA2',
        ]);

        $hasil = $this->jalankan(
            [
                'dataset' => 'uji',
                'mode' => 'rinci',
                'kolom' => ['modul', 'pemilik'],
                'saringan' => [['field' => 'modul', 'op' => 'salah_satu', 'nilai' => ['uji-runner2']]],
            ],
            new Lingkup('AD1', true, [], true)
        );

        DB::table('N_HRIS_Report_Template')->where('Module', 'uji-runner2')->delete();

        $this->assertSame('RAHASIA2', $hasil['rows'][0]['pemilik']);
        $this->assertSame([], $hasil['catatan']);
    }

    public function test_batas_menulis_catatan_dan_mematikan_subtotal(): void
    {
        $hasil = $this->jalankan(
            [
                'dataset' => 'uji',
                'mode' => 'ringkasan',
                'dimensi' => ['modul'],
                'ukuran' => ['jml'],
                'opsi' => ['grand_total' => true],
            ],
            new Lingkup('AD1', true, [], true),
            1
        );

        $this->assertLessThanOrEqual(1, count($hasil['rows']));
        $this->assertNotEmpty($hasil['catatan']);
        // Grand total atas data terpotong adalah angka yang salah dan tampak benar.
        foreach ($hasil['rows'] as $r) {
            $this->assertArrayNotHasKey('_grand_total', $r);
        }
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `unset DATABASE_URL && php artisan test --filter=ReportRunnerTest`
Expected: FAIL — class `ReportRunner` tidak ditemukan

- [ ] **Step 3: Implementasi runner**

```php
<?php

namespace App\Services\SYSTEM\Application\Reports;

use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use Illuminate\Support\Carbon;

/**
 * Menjalankan definisi dan mengembalikan bentuk yang SAMA PERSIS dengan dataset
 * legacy (title, generated_at, columns, rows) supaya penulis CSV/XLSX/PDF yang
 * sudah ada bisa dipakai tanpa perubahan.
 *
 * Pratinjau layar memanggil method yang sama dengan $batas kecil. Itu jaminan
 * terpenting fitur ini: pratinjau BUKAN pendekatan dari berkas, ia berkas yang
 * sama dijalankan lebih awal.
 */
class ReportRunner
{
    private const MASK = '—';

    public function __construct(private ReportCompiler $compiler) {}

    public function jalankan(
        DefinisiLaporan $d,
        DatasetLaporan $ds,
        Lingkup $lingkup,
        string $judul,
        ?int $batas = null,
    ): array {
        $kunciTerpilih = $d->mode === 'rinci' ? $d->kolom : array_merge($d->dimensi, $d->ukuran);
        $meta = $this->metaKolom($ds, $kunciTerpilih);

        $q = $this->compiler->susun($d, $ds, $lingkup);
        if ($batas !== null) {
            $q->limit($batas + 1);   // +1 untuk mendeteksi pemotongan
        }

        $baris = array_map(fn ($r) => (array) $r, $q->get()->all());

        $terpotong = $batas !== null && count($baris) > $batas;
        if ($terpotong) {
            $baris = array_slice($baris, 0, $batas);
        }

        $catatan = [];

        $disembunyikan = [];
        foreach ($meta as $k => $m) {
            if ($m['sensitif'] && ! $lingkup->bolehFieldTerbatas) {
                $disembunyikan[] = $k;
            }
        }
        if ($disembunyikan !== []) {
            foreach ($baris as &$r) {
                foreach ($disembunyikan as $k) {
                    $r[$k] = self::MASK;
                }
            }
            unset($r);
            $catatan[] = count($disembunyikan).' kolom disembunyikan sesuai hak akses.';
        }

        if ($terpotong) {
            $catatan[] = "Hanya {$batas} baris teratas ditampilkan.";
        }

        // Subtotal dan grand total DIMATIKAN saat data terpotong: total atas
        // potongan adalah angka salah yang tampak benar.
        if (! $terpotong && ($d->opsi['grand_total'] ?? false) && $d->mode === 'ringkasan') {
            $baris[] = $this->grandTotal($baris, $d->dimensi, $d->ukuran);
        }

        return [
            'title' => $judul,
            'generated_at' => Carbon::now()->format('Y-m-d H:i'),
            'columns' => array_values(array_map(
                fn ($k) => ['key' => $k, 'label' => $meta[$k]['label'], 'width' => $meta[$k]['width'], 'center' => $meta[$k]['center']],
                $kunciTerpilih
            )),
            'rows' => $baris,
            'catatan' => $catatan,
        ];
    }

    private function grandTotal(array $baris, array $dimensi, array $ukuran): array
    {
        $total = [];
        foreach ($dimensi as $i => $k) {
            $total[$k] = $i === 0 ? 'GRAND TOTAL' : '';
        }
        foreach ($ukuran as $k) {
            $total[$k] = array_sum(array_map(fn ($r) => (float) ($r[$k] ?? 0), $baris));
        }

        return $total;
    }

    /** @return array<string, array{label:string, width:int, center:bool, sensitif:bool}> */
    private function metaKolom(DatasetLaporan $ds, array $kunci): array
    {
        $semua = [];
        foreach ($ds->dimensi() as $dim) {
            $semua[$dim->key] = ['label' => $dim->label, 'width' => 20, 'center' => false, 'sensitif' => $dim->sensitif];
        }
        foreach ($ds->ukuran() as $u) {
            $semua[$u->key] = ['label' => $u->label, 'width' => 12, 'center' => true, 'sensitif' => $u->sensitif];
        }

        $meta = [];
        foreach ($kunci as $k) {
            $meta[$k] = $semua[$k] ?? ['label' => $k, 'width' => 20, 'center' => false, 'sensitif' => false];
        }

        return $meta;
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `unset DATABASE_URL && php artisan test --filter=ReportRunnerTest`
Expected: PASS, 4 test

- [ ] **Step 5: Commit**

```bash
git add app/Services/SYSTEM/Application/Reports/ReportRunner.php \
        tests/Unit/SYSTEM/Application/Reports/ReportRunnerTest.php
git commit -m "feat(reports): runner dengan masking field sensitif dan penjaga pemotongan"
```

---

### Task 8: Layanan template

**Files:**
- Create: `app/Services/SYSTEM/Application/Reports/ReportTemplateService.php`
- Create: `app/Services/LMS/SOP/Export/SopTemplateSistem.php`
- Test: `tests/Feature/LMS/SOP/Export/ReportTemplateServiceTest.php`

**Interfaces:**
- Consumes: tabel dari Task 1; `DefinisiLaporan`, `DefinisiValidator` (Task 4);
  `DatasetRegistry` (Task 3).
- Produces:
  - `ReportTemplateService::__construct(string $module, DatasetRegistry $registry, DefinisiValidator $validator, array $templateSistem = [])`
    — `$templateSistem` berkunci `Kode`; disuntik pemanggil, tak pernah di-import
  - `->galeri(string $kodeKaryawan): array` — `['resmi' => [], 'saya' => [], 'dibagikan' => []]`
  - `->ambil(string $kode): array` — baris template atau template sistem
  - `->simpan(array $data, string $kodeKaryawan): string` — mengembalikan `Kode`
  - `->duplikat(string $kodeAsal, string $kodeKaryawan): string`
  - `->nonaktifkan(string $kode, string $kodeKaryawan): void`
  - `SopTemplateSistem::semua(): array` — daftar template resmi dari kode

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

namespace Tests\Feature\LMS\SOP\Export;

use App\Services\LMS\SOP\Export\SopTemplateSistem;
use App\Services\SYSTEM\Application\Reports\DatasetRegistry;
use App\Services\SYSTEM\Application\Reports\DefinisiValidator;
use App\Services\SYSTEM\Application\Reports\ReportTemplateService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ReportTemplateServiceTest extends TestCase
{
    private function svc(string $module = 'sop'): ReportTemplateService
    {
        return new ReportTemplateService(
            $module,
            app(DatasetRegistry::class),
            app(DefinisiValidator::class),
            $module === 'sop' ? SopTemplateSistem::semua() : [],
        );
    }

    private function definisiSah(): array
    {
        return [
            'nama' => 'Kepatuhan per Divisi',
            'dataset' => 'kepatuhan',
            'mode' => 'ringkasan',
            'definisi' => [
                'dataset' => 'kepatuhan',
                'mode' => 'ringkasan',
                'dimensi' => ['divisi'],
                'ukuran' => ['jml_karyawan'],
            ],
            'tingkat_bagi' => 'PRIBADI',
        ];
    }

    protected function tearDown(): void
    {
        DB::table('N_HRIS_Report_Template')->where('Kode_Karyawan_Pemilik', 'like', 'UJI%')->delete();
        parent::tearDown();
    }

    public function test_simpan_menghasilkan_kode_unik_berprefix_modul(): void
    {
        $kode = $this->svc()->simpan($this->definisiSah(), 'UJI001');

        $this->assertStringStartsWith('sop-', $kode);
        $this->assertDatabaseHas('N_HRIS_Report_Template', ['Module' => 'sop', 'Kode' => $kode]);
    }

    public function test_definisi_tak_sah_ditolak_saat_simpan(): void
    {
        $d = $this->definisiSah();
        $d['definisi']['ukuran'] = ['tidak_ada'];

        $this->expectException(RuntimeException::class);
        $this->svc()->simpan($d, 'UJI002');
    }

    public function test_template_modul_lain_tidak_bisa_diambil(): void
    {
        $kode = $this->svc('sop')->simpan($this->definisiSah(), 'UJI003');

        $this->expectException(RuntimeException::class);
        $this->svc('kpi')->ambil($kode);
    }

    public function test_galeri_saya_hanya_memuat_milik_sendiri(): void
    {
        $this->svc()->simpan($this->definisiSah(), 'UJI004');
        $this->svc()->simpan($this->definisiSah(), 'UJI005');

        $saya = $this->svc()->galeri('UJI004')['saya'];

        $this->assertCount(1, $saya);
        $this->assertSame('UJI004', $saya[0]->Kode_Karyawan_Pemilik);
    }

    public function test_galeri_resmi_berasal_dari_kode_bukan_baris(): void
    {
        $resmi = $this->svc()->galeri('UJI006')['resmi'];

        $this->assertNotEmpty($resmi);
        $this->assertSame(0, DB::table('N_HRIS_Report_Template')->where('Tingkat_Bagi', 'SISTEM')->count());
    }

    public function test_duplikat_template_sistem_mencatat_asalnya(): void
    {
        $kodeBaru = $this->svc()->duplikat('sop-sistem-rinci', 'UJI007');
        $baris = DB::table('N_HRIS_Report_Template')->where('Kode', $kodeBaru)->first();

        $this->assertSame('sop-sistem-rinci', $baris->Kode_Template_Asal);
        $this->assertSame('UJI007', $baris->Kode_Karyawan_Pemilik);
    }

    public function test_nonaktifkan_tidak_menghapus_baris(): void
    {
        $kode = $this->svc()->simpan($this->definisiSah(), 'UJI008');
        $this->svc()->nonaktifkan($kode, 'UJI008');

        $this->assertDatabaseHas('N_HRIS_Report_Template', ['Kode' => $kode, 'Flag_Aktif' => 'T']);
    }

    public function test_orang_lain_tidak_bisa_menonaktifkan(): void
    {
        $kode = $this->svc()->simpan($this->definisiSah(), 'UJI009');

        $this->expectException(RuntimeException::class);
        $this->svc()->nonaktifkan($kode, 'UJI010');
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `unset DATABASE_URL && php artisan test --filter=ReportTemplateServiceTest`
Expected: FAIL — class `ReportTemplateService` tidak ditemukan

- [ ] **Step 3: Implementasi template sistem**

```php
<?php

namespace App\Services\LMS\SOP\Export;

/**
 * Template resmi SOP — didefinisikan di KODE, bukan baris seed.
 *
 * Alasannya tiga: tidak ada drift antara development dan production, isinya
 * ikut code review seperti kode lain, dan "Duplikat & ubah" jadi sekadar salin
 * kode ke baris milik pengguna.
 */
class SopTemplateSistem
{
    public static function semua(): array
    {
        return [
            'sop-sistem-rinci' => [
                'kode' => 'sop-sistem-rinci',
                'nama' => 'Rincian Kepatuhan per Item',
                'deskripsi' => 'Satu baris per karyawan × paket × item. Berkas terlebar modul SOP, dibuat untuk di-pivot di Excel.',
                'dataset' => 'kepatuhan',
                'mode' => 'rinci',
                'definisi' => [
                    'versi' => 1,
                    'dataset' => 'kepatuhan',
                    'mode' => 'rinci',
                    'kolom' => [
                        'kode', 'nama', 'no_induk', 'lokasi', 'divisi', 'departemen', 'jabatan',
                        'paket', 'kode_paket', 'kategori', 'prioritas', 'status_paket', 'tenggat',
                        'section', 'item', 'nomor_sop', 'tipe_item', 'wajib',
                        'status_item', 'waktu_konfirmasi',
                    ],
                    'saringan' => [],
                    'urut' => [],
                    'opsi' => [],
                    'format_bawaan' => 'xlsx',
                ],
            ],
        ];
    }
}
```

Enam template resmi lainnya ditambahkan di fase berikutnya; fase ini hanya
membutuhkan `rinci` untuk membuktikan kesetaraan.

- [ ] **Step 4: Implementasi layanan template**

```php
<?php

namespace App\Services\SYSTEM\Application\Reports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * CRUD template, selalu terikat satu modul.
 *
 * $module datang dari KONSTRUKTOR, yang diisi controller dari route — tak
 * pernah dari badan permintaan. Kalau modul boleh dikirim pengguna, satu query
 * yang lupa menyaring langsung membocorkan daftar template modul lain.
 *
 * Template sistem juga DISUNTIKKAN, bukan di-import. Kelas ini milik SYSTEM dan
 * tidak boleh menyebut satu pun modul bisnis: begitu ia meng-import
 * SopTemplateSistem, modul kedua harus mengubah mesin untuk bergabung — dan itu
 * persis kegagalan yang ingin dihindari desain ini.
 */
class ReportTemplateService
{
    public const TABEL = 'N_HRIS_Report_Template';

    /** @param array<string, array> $templateSistem berkunci Kode */
    public function __construct(
        private string $module,
        private DatasetRegistry $registry,
        private DefinisiValidator $validator,
        private array $templateSistem = [],
    ) {}

    public function galeri(string $kodeKaryawan): array
    {
        return [
            'resmi' => array_values($this->templateSistem),
            'saya' => DB::table(self::TABEL)
                ->where('Module', $this->module)
                ->where('Kode_Karyawan_Pemilik', $kodeKaryawan)
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Nama')->get()->all(),
            'dibagikan' => DB::table(self::TABEL)
                ->where('Module', $this->module)
                ->whereIn('Tingkat_Bagi', ['DIBAGIKAN', 'ORGANISASI'])
                ->where('Kode_Karyawan_Pemilik', '!=', $kodeKaryawan)
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Nama')->get()->all(),
        ];
    }

    public function ambil(string $kode): array
    {
        $sistem = $this->templateSistem;
        if (isset($sistem[$kode])) {
            return $sistem[$kode] + ['sistem' => true];
        }

        $baris = DB::table(self::TABEL)
            ->where('Module', $this->module)
            ->where('Kode', $kode)
            ->where('Flag_Aktif', 'Y')
            ->first();

        if (! $baris) {
            throw new RuntimeException("Template tidak ditemukan: {$kode}");
        }

        return [
            'kode' => $baris->Kode,
            'nama' => $baris->Nama,
            'deskripsi' => $baris->Deskripsi,
            'dataset' => $baris->Dataset_Key,
            'mode' => strtolower($baris->Mode),
            'definisi' => json_decode($baris->Definisi_Json, true) ?: [],
            'sistem' => false,
        ];
    }

    public function simpan(array $data, string $kodeKaryawan): string
    {
        $definisi = DefinisiLaporan::dariArray($data['definisi'] ?? []);
        $dataset = $this->registry->ambil($this->module, $definisi->dataset);

        $galat = $this->validator->periksa($definisi, $dataset);
        if ($galat !== []) {
            throw new RuntimeException('Definisi laporan tidak sah: '.json_encode($galat, JSON_UNESCAPED_UNICODE));
        }

        $kode = $this->kodeBaru((string) ($data['nama'] ?? 'laporan'));

        DB::table(self::TABEL)->insert([
            'Module' => $this->module,
            'Kode' => $kode,
            'Nama' => (string) $data['nama'],
            'Deskripsi' => $data['deskripsi'] ?? null,
            'Dataset_Key' => $definisi->dataset,
            'Mode' => strtoupper($definisi->mode),
            'Definisi_Json' => json_encode($definisi->toArray(), JSON_UNESCAPED_UNICODE),
            'Tingkat_Bagi' => $data['tingkat_bagi'] ?? 'PRIBADI',
            'Kode_Karyawan_Pemilik' => $kodeKaryawan,
            'Kode_Template_Asal' => $data['kode_template_asal'] ?? null,
            'Created_By' => $kodeKaryawan,
        ]);

        return $kode;
    }

    public function duplikat(string $kodeAsal, string $kodeKaryawan): string
    {
        $asal = $this->ambil($kodeAsal);

        return $this->simpan([
            'nama' => $asal['nama'].' (salinan)',
            'deskripsi' => $asal['deskripsi'] ?? null,
            'definisi' => $asal['definisi'],
            'tingkat_bagi' => 'PRIBADI',
            'kode_template_asal' => $kodeAsal,
        ], $kodeKaryawan);
    }

    public function nonaktifkan(string $kode, string $kodeKaryawan): void
    {
        // Menonaktifkan, bukan menghapus: Kode_Template_Asal pada turunannya
        // harus tetap bisa ditelusuri.
        $terpengaruh = DB::table(self::TABEL)
            ->where('Module', $this->module)
            ->where('Kode', $kode)
            ->where('Kode_Karyawan_Pemilik', $kodeKaryawan)
            ->update(['Flag_Aktif' => 'T', 'Updated_At' => now(), 'Updated_By' => $kodeKaryawan]);

        if ($terpengaruh === 0) {
            throw new RuntimeException("Template tidak ditemukan atau bukan milik Anda: {$kode}");
        }
    }

    private function kodeBaru(string $nama): string
    {
        return $this->module.'-'.Str::slug(Str::limit($nama, 40, '')).'-'.substr(bin2hex(random_bytes(2)), 0, 4);
    }
}
```

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `unset DATABASE_URL && php artisan test --filter=ReportTemplateServiceTest`
Expected: PASS, 8 test

- [ ] **Step 6: Commit**

```bash
git add app/Services/SYSTEM/Application/Reports/ReportTemplateService.php \
        app/Services/LMS/SOP/Export/SopTemplateSistem.php \
        tests/Feature/LMS/SOP/Export/ReportTemplateServiceTest.php
git commit -m "feat(reports): layanan template dengan isolasi modul dan template sistem di kode"
```

---

### Task 9: Layanan export dan job

**Files:**
- Create: `app/Services/SYSTEM/Application/Reports/ReportExportService.php`
- Create: `app/Jobs/LMS/SOP/Export/GenerateSopReportExportJob.php`
- Create: `resources/views/exports/report-pdf.blade.php`
- Test: `tests/Feature/LMS/SOP/Export/ReportExportServiceTest.php`

**Interfaces:**
- Consumes: `ReportRunner` (Task 7), `ReportTemplateService` (Task 8),
  `DatasetRegistry` (Task 3), `AbstractExportService`
  (`App\Services\SYSTEM\Application\Exports\AbstractExportService`).
- Produces: `ReportExportService` dengan `moduleKey() = 'sop-studio'`,
  `exportIdPrefix() = 'rpt-exp'`, `storageDir() = 'report-exports'`.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

namespace Tests\Feature\LMS\SOP\Export;

use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use App\Services\SYSTEM\Application\Reports\ReportExportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportExportServiceTest extends TestCase
{
    private function payload(): array
    {
        return [
            'module' => 'sop',
            'template_kode' => 'sop-sistem-rinci',
            'judul' => 'RINCIAN KEPATUHAN SOP',
            'definisi' => [
                'dataset' => 'kepatuhan',
                'mode' => 'rinci',
                'kolom' => ['kode', 'nama', 'paket', 'item', 'status_item'],
            ],
            'lingkup' => ['kode_karyawan' => 'UJIX01', 'admin_penuh' => true, 'anggota_tim' => [], 'boleh_field_terbatas' => true],
        ];
    }

    protected function tearDown(): void
    {
        DB::table(AbstractExportService::TABLE)->where('Requested_By', 'UJIX01')->delete();
        parent::tearDown();
    }

    public function test_job_menghasilkan_berkas_dan_status_ready(): void
    {
        Storage::fake('gcs');

        $svc = app(ReportExportService::class);
        $id = $svc->makeExportId();
        $svc->startJob($id, 'csv', $this->payload(), 0, 'UJIX01');
        $svc->process($id);

        $job = DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)->first();

        $this->assertSame('ready', $job->Status);
        $this->assertNotNull($job->File_Path);
        Storage::disk('gcs')->assertExists($job->File_Path);
    }

    /**
     * Cloud Tasks bisa mengirim ulang permintaan yang sebenarnya sudah sukses
     * (mis. handler kena timeout proxy). Tanpa guard, job 'ready' ditimpa jadi
     * 'processing' dan berkas lamanya hilang -> unduhan 404.
     */
    public function test_proses_ulang_tidak_menimpa_job_yang_sudah_ready(): void
    {
        Storage::fake('gcs');

        $svc = app(ReportExportService::class);
        $id = $svc->makeExportId();
        $svc->startJob($id, 'csv', $this->payload(), 0, 'UJIX01');
        $svc->process($id);

        $pertama = DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)->first();
        $svc->process($id);
        $kedua = DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)->first();

        $this->assertSame($pertama->File_Path, $kedua->File_Path);
        $this->assertSame('ready', $kedua->Status);
    }

    public function test_job_dibatalkan_tidak_menjadi_failed_generik(): void
    {
        Storage::fake('gcs');

        $svc = app(ReportExportService::class);
        $id = $svc->makeExportId();
        $svc->startJob($id, 'csv', $this->payload(), 0, 'UJIX01');

        $p = $this->payload();
        $p['aborted'] = true;
        DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)
            ->update(['Payload_Json' => json_encode($p), 'Status' => 'queued']);

        $svc->process($id);

        $this->assertNotSame('ready', DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)->value('Status'));
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `unset DATABASE_URL && php artisan test --filter=ReportExportServiceTest`
Expected: FAIL — class `ReportExportService` tidak ditemukan

- [ ] **Step 3: Baca kelas induk sebelum menulis**

Run: `sed -n '1,150p' app/Services/SYSTEM/Application/Exports/AbstractExportService.php`

Perhatikan tanda tangan `startJob`, `bumpProgress`, `renderFile`, dan
`datasetCount`. Salin pola penulis CSV/XLSX dari
`app/Services/LMS/SOP/SopComplianceExportService.php` (`renderCsv`,
`renderExcel`, `renderPdf`) — bentuk datasetnya identik, jadi penulisnya bisa
dipindahkan hampir apa adanya.

- [ ] **Step 4: Implementasi layanan export**

```php
<?php

namespace App\Services\SYSTEM\Application\Reports;

use App\Jobs\LMS\SOP\Export\GenerateSopReportExportJob;
use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;

/**
 * Menyambung mesin laporan ke infrastruktur export yang sudah ada.
 *
 * Tidak ada infrastruktur async baru: lifecycle job, progress, pembatalan,
 * Activity Center, penyimpanan GCS, dan export:cleanup seluruhnya milik
 * AbstractExportService.
 *
 * Module 'sop-studio', bukan 'sop-compliance' — supaya Activity Center bisa
 * membedakan export Studio dari export lama selama masa dua jalur.
 */
class ReportExportService extends AbstractExportService
{
    public function __construct(
        private DatasetRegistry $registry,
        private ReportRunner $runner,
    ) {}

    protected function moduleKey(): string
    {
        return 'sop-studio';
    }

    protected function exportIdPrefix(): string
    {
        return 'rpt-exp';
    }

    protected function storageDir(): string
    {
        return 'report-exports';
    }

    public function dispatchJob(string $exportId): void
    {
        GenerateSopReportExportJob::dispatch($exportId);
    }

    protected function listLabel(object $job): string
    {
        $p = $this->payload($job);

        return ($p['judul'] ?? 'Laporan').' · '.strtoupper($job->Format);
    }

    protected function buildDataset(object $job): array
    {
        $p = $this->payload($job);

        // Definisi diambil dari PAYLOAD, bukan dari tabel template: mengubah
        // atau menghapus template tidak boleh merusak job yang sudah antre.
        $definisi = DefinisiLaporan::dariArray($p['definisi'] ?? []);

        // Modul ikut di payload, ditulis controller dari route. Kelas ini milik
        // SYSTEM; menuliskan 'sop' di sini berarti modul kedua harus mengubah
        // mesin untuk bergabung.
        $dataset = $this->registry->ambil((string) ($p['module'] ?? ''), $definisi->dataset);

        $l = $p['lingkup'] ?? [];
        $lingkup = new Lingkup(
            (string) ($l['kode_karyawan'] ?? ''),
            (bool) ($l['admin_penuh'] ?? false),
            (array) ($l['anggota_tim'] ?? []),
            (bool) ($l['boleh_field_terbatas'] ?? false),
        );

        return $this->runner->jalankan($definisi, $dataset, $lingkup, (string) ($p['judul'] ?? 'LAPORAN'));
    }

    protected function renderFile(object $job, array $dataset): array
    {
        return match ($job->Format) {
            'csv' => $this->renderCsv($job, $dataset),
            'pdf' => $this->renderPdf($job, $dataset),
            default => $this->renderExcel($job, $dataset),
        };
    }
}
```

Tambahkan `renderCsv`, `renderExcel`, dan `renderPdf` dengan menyalin
implementasi dari `SopComplianceExportService`, dengan satu perubahan:
`renderPdf` memakai view `exports.report-pdf`, dan ketiganya menuliskan
`$dataset['catatan']` sebagai baris kaki bila tidak kosong.

- [ ] **Step 5: Buat job dan blade**

```php
<?php

namespace App\Jobs\LMS\SOP\Export;

use App\Services\SYSTEM\Application\Reports\ReportExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateSopReportExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $exportId) {}

    public function handle(ReportExportService $svc): void
    {
        $svc->process($this->exportId);
    }
}
```

Bandingkan dengan `app/Jobs/LMS/SOP/Export/GenerateSopComplianceExportJob.php`
lebih dulu — bila job existing memakai trait atau konfigurasi Cloud Tasks
tambahan, ikuti pola itu, jangan pola generik di atas.

`resources/views/exports/report-pdf.blade.php` menyalin
`resources/views/exports/sop-compliance-pdf.blade.php` apa adanya, ditambah
blok kaki:

```blade
@if (! empty($dataset['catatan']))
    <div class="catatan">
        @foreach ($dataset['catatan'] as $c)
            <div>{{ $c }}</div>
        @endforeach
    </div>
@endif
```

- [ ] **Step 6: Jalankan test, pastikan lulus**

Run: `unset DATABASE_URL && php artisan test --filter=ReportExportServiceTest`
Expected: PASS, 3 test

- [ ] **Step 7: Commit**

```bash
git add app/Services/SYSTEM/Application/Reports/ReportExportService.php \
        app/Jobs/LMS/SOP/Export/GenerateSopReportExportJob.php \
        resources/views/exports/report-pdf.blade.php \
        tests/Feature/LMS/SOP/Export/ReportExportServiceTest.php
git commit -m "feat(reports): layanan export laporan di atas AbstractExportService"
```

---

### Task 10: Harness regresi — mesin baru vs jenis `rinci` lama

Inilah yang membuktikan mesin baru boleh dipercaya. Harness ini **tinggal
permanen di CI**, bukan dibuang setelah migrasi: selama layar monitoring masih
membaca service lama dan Studio membaca dataset baru, dua implementasi
menghitung angka yang sama, dan hanya test ini yang membuat ketidaksepakatannya
terlihat.

**Files:**
- Create: `tests/Feature/LMS/SOP/Export/RegresiRinciTest.php`

**Interfaces:**
- Consumes: `SopComplianceExportService` (legacy), `ReportExportService` (Task 9),
  `SopTemplateSistem` (Task 8), `UatDunia` (`tests/Feature/LMS/SOP/Uat/Support/UatDunia.php`).

- [ ] **Step 1: Baca pembangun dunia dan test legacy**

Run: `sed -n '1,80p' tests/Feature/LMS/SOP/Uat/Support/UatDunia.php`
Run: `sed -n '1,60p' tests/Feature/LMS/SOP/SopExportRinciDatasetTest.php`

Catat cara test legacy memanggil `buildDataset` lewat refleksi dan cara
`UatDunia` membangun penugasan.

- [ ] **Step 2: Tulis test pembanding**

```php
<?php

namespace Tests\Feature\LMS\SOP\Export;

use App\Services\LMS\SOP\Export\SopTemplateSistem;
use App\Services\LMS\SOP\SopComplianceExportService;
use App\Services\SYSTEM\Application\Reports\DatasetRegistry;
use App\Services\SYSTEM\Application\Reports\DefinisiLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\ReportRunner;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Mesin baru dan jalur lama harus menghasilkan BARIS YANG SAMA.
 *
 * Yang dibandingkan datanya, bukan berkasnya: XLSX menyimpan timestamp di dalam
 * zip dan dompdf menulis CreationDate, jadi perbandingan byte akan gagal karena
 * alasan yang tak ada hubungannya dengan kebenaran isi.
 */
class RegresiRinciTest extends TestCase
{
    private function datasetLegacy(array $filters): array
    {
        $job = (object) [
            'Export_Id' => 'regresi-rinci',
            'Format' => 'xlsx',
            'Payload_Json' => json_encode(['jenis' => 'rinci', 'filters' => $filters]),
        ];

        $m = new \ReflectionMethod(SopComplianceExportService::class, 'buildDataset');
        $m->setAccessible(true);

        return $m->invoke(app(SopComplianceExportService::class), $job);
    }

    private function datasetBaru(array $saringan): array
    {
        $tpl = SopTemplateSistem::semua()['sop-sistem-rinci'];
        $definisi = $tpl['definisi'];
        $definisi['saringan'] = $saringan;

        return app(ReportRunner::class)->jalankan(
            DefinisiLaporan::dariArray($definisi),
            app(DatasetRegistry::class)->ambil('sop', 'kepatuhan'),
            new Lingkup('REG001', true, [], true),
            'RINCIAN KEPATUHAN SOP'
        );
    }

    /** @return array<string, array{0: array, 1: array}> */
    public static function skenario(): array
    {
        return [
            'saringan kosong' => [[], []],
            'satu divisi' => [['divisi' => ['PRODUKSI']], [['field' => 'divisi', 'op' => 'salah_satu', 'nilai' => ['PRODUKSI']]]],
            'nol baris' => [['divisi' => ['TIDAK-ADA-DIVISI-INI']], [['field' => 'divisi', 'op' => 'salah_satu', 'nilai' => ['TIDAK-ADA-DIVISI-INI']]]],
        ];
    }

    /** @dataProvider skenario */
    public function test_baris_identik(array $filtersLegacy, array $saringanBaru): void
    {
        Carbon::setTestNow('2026-08-18 10:00:00');

        $lama = $this->datasetLegacy($filtersLegacy);
        $baru = $this->datasetBaru($saringanBaru);

        $kunci = array_column($baru['columns'], 'key');

        $this->assertSame(
            $this->normalkan($lama['rows'], $kunci),
            $this->normalkan($baru['rows'], $kunci),
            'Baris mesin baru berbeda dengan jalur lama.'
        );

        Carbon::setTestNow();
    }

    /** @dataProvider skenario */
    public function test_label_kolom_identik(array $filtersLegacy, array $saringanBaru): void
    {
        $lama = collect($this->datasetLegacy($filtersLegacy)['columns'])->keyBy('key');
        $baru = $this->datasetBaru($saringanBaru)['columns'];

        foreach ($baru as $kol) {
            if (isset($lama[$kol['key']])) {
                $this->assertSame($lama[$kol['key']]['label'], $kol['label'], "Label kolom '{$kol['key']}' berbeda.");
            }
        }
    }

    /** Hanya kolom yang dipilih template baru yang dibandingkan, urut stabil. */
    private function normalkan(array $rows, array $kunci): array
    {
        $out = [];
        foreach ($rows as $r) {
            $r = (array) $r;
            $baris = [];
            foreach ($kunci as $k) {
                $baris[$k] = isset($r[$k]) ? (string) $r[$k] : '';
            }
            $out[] = $baris;
        }

        usort($out, fn ($a, $b) => strcmp(implode('|', $a), implode('|', $b)));

        return $out;
    }
}
```

- [ ] **Step 3: Jalankan dan selesaikan bedanya**

Run: `unset DATABASE_URL && php artisan test --filter=RegresiRinciTest`
Expected: awalnya FAIL. Setiap beda adalah temuan nyata — perbaiki
`SopDatasetKepatuhan` (ekspresi `select`, format tanggal, penanganan NULL
menjadi `-`) sampai identik. **Jangan** melonggarkan assertion untuk membuat
test hijau; itu membuang satu-satunya bukti yang dimiliki fase ini.

Bila sebuah beda ternyata bug di jalur lama, catat di deskripsi commit dan
laporkan — jangan perbaiki jalur lama dalam fase ini.

- [ ] **Step 4: Jalankan seluruh suite backend**

Run: `unset DATABASE_URL && npm run test:backend:unit && npm run test:backend:feature`
Expected: PASS, tanpa kegagalan baru dibanding baseline sebelum fase ini.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/LMS/SOP/Export/RegresiRinciTest.php \
        app/Services/LMS/SOP/Export/SopDatasetKepatuhan.php
git commit -m "test(sop): harness regresi mesin laporan baru vs jenis rinci lama"
```

---

### Task 11: Controller, route, dan Form Request

**Files:**
- Create: `app/Http/Controllers/LMS/SOP/Export/SopExportStudioController.php`
- Create: `app/Http/Requests/LMS/SOP/Export/SimpanTemplateRequest.php`
- Create: `app/Http/Requests/LMS/SOP/Export/JalankanLaporanRequest.php`
- Create: `routes/LMS/SOP/Export/SopExportStudio.php`
- Modify: `routes/web.php` (daftarkan berkas route baru)
- Modify: `app/Services/LMS/SOP/SopTeamService.php:17` (`teamKodes` jadi publik)
- Test: `tests/Feature/LMS/SOP/Export/SopExportStudioApiTest.php`

**Interfaces:**
- Consumes: `ReportTemplateService` (Task 8), `ReportRunner` (Task 7),
  `ReportExportService` (Task 9), `DatasetRegistry` (Task 3),
  `ResponseHelper::errorWithCode` (Task 2);
  `App\Services\LMS\SOP\SopTeamService::teamKodes(string $leader): array`
  (dijadikan publik di Step 5);
  `App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationScopeResolver::resolve(User $user): array{isSystemAdmin: bool, manageableGroupIds: ?array, usablePageTypes: ?array}` (statis).
- Produces: endpoint di bawah prefix `/sop/export-studio`.

- [ ] **Step 1: Periksa cara route SOP didaftarkan**

Run: `grep -n "LMS/SOP" routes/web.php`
Run: `sed -n '1,40p' routes/LMS/SOP/Governance/SopGovernance.php`

Ikuti middleware dan prefix yang sama persis; jangan menyusun grup baru dari
nol.

- [ ] **Step 2: Tulis test yang gagal**

```php
<?php

namespace Tests\Feature\LMS\SOP\Export;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SopExportStudioApiTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::table('N_HRIS_Report_Template')->where('Kode_Karyawan_Pemilik', 'like', 'API%')->delete();
        parent::tearDown();
    }

    public function test_katalog_mengembalikan_dimensi_dan_ukuran(): void
    {
        $res = $this->actingAsAdminSop()->getJson('/sop/export-studio/katalog/kepatuhan');

        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['result' => ['dimensi', 'ukuran', 'saringan', 'grain']]);
    }

    public function test_dataset_tak_dikenal_membalas_kode_stabil(): void
    {
        $res = $this->actingAsAdminSop()->getJson('/sop/export-studio/katalog/tidak-ada');

        $res->assertStatus(404)->assertJsonPath('error_code', 'REPORT_DATASET_UNKNOWN');
    }

    public function test_definisi_tak_sah_membalas_422_dengan_errors_per_field(): void
    {
        $res = $this->actingAsAdminSop()->postJson('/sop/export-studio/template', [
            'nama' => 'Uji',
            'definisi' => ['dataset' => 'kepatuhan', 'mode' => 'ringkasan', 'dimensi' => ['tidak_ada'], 'ukuran' => []],
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('error_code', 'REPORT_DEFINITION_INVALID')
            ->assertJsonStructure(['errors' => ['dimensi']]);
    }

    public function test_pratinjau_memakai_definisi_yang_sama_dengan_export(): void
    {
        $res = $this->actingAsAdminSop()->postJson('/sop/export-studio/pratinjau', [
            'definisi' => ['dataset' => 'kepatuhan', 'mode' => 'ringkasan', 'dimensi' => ['divisi'], 'ukuran' => ['jml_karyawan']],
        ]);

        $res->assertOk()->assertJsonStructure(['result' => ['title', 'columns', 'rows', 'catatan']]);
    }

    public function test_jalankan_mengembalikan_task_id(): void
    {
        $res = $this->actingAsAdminSop()->postJson('/sop/export-studio/jalankan', [
            'format' => 'csv',
            'template_kode' => 'sop-sistem-rinci',
        ]);

        $res->assertStatus(202)->assertJsonStructure(['result' => ['task_id']]);
    }
}
```

`actingAsAdminSop()` belum ada. Periksa dulu apakah repo sudah punya helper
serupa:

Run: `grep -rn "function actingAs" tests/ | head`

Bila ada helper login yang sudah dipakai test SOP lain, pakai itu. Bila tidak,
tambahkan method privat di kelas test ini yang membuat user dan
`Auth::login()`-nya, mengikuti pola pada `tests/Feature/LMS/SOP/Uat/Support/UatAkun.php`.

- [ ] **Step 3: Jalankan test, pastikan gagal**

Run: `unset DATABASE_URL && php artisan test --filter=SopExportStudioApiTest`
Expected: FAIL — 404 di semua endpoint

- [ ] **Step 4: Tulis Form Request**

```php
<?php

namespace App\Http\Requests\LMS\SOP\Export;

use Illuminate\Foundation\Http\FormRequest;

class SimpanTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // otorisasi halaman ditegakkan middleware route
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:150'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'definisi' => ['required', 'array'],
            'definisi.dataset' => ['required', 'string', 'max:40'],
            'definisi.mode' => ['required', 'string', 'in:ringkasan,rinci'],
            'tingkat_bagi' => ['nullable', 'string', 'in:PRIBADI,DIBAGIKAN,ORGANISASI'],
        ];
    }
}
```

```php
<?php

namespace App\Http\Requests\LMS\SOP\Export;

use Illuminate\Foundation\Http\FormRequest;

class JalankanLaporanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'format' => ['required', 'string', 'in:csv,xlsx,pdf'],
            'template_kode' => ['required_without:definisi', 'nullable', 'string', 'max:60'],
            'definisi' => ['required_without:template_kode', 'nullable', 'array'],
            'parameter' => ['nullable', 'array'],
        ];
    }
}
```

Perhatikan: **tidak ada aturan untuk `module`.** Modul datang dari route.

- [ ] **Step 5: Tulis controller**

```php
<?php

namespace App\Http\Controllers\LMS\SOP\Export;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\LMS\SOP\Export\JalankanLaporanRequest;
use App\Http\Requests\LMS\SOP\Export\SimpanTemplateRequest;
use App\Services\LMS\SOP\Export\SopTemplateSistem;
use App\Services\LMS\SOP\SopTeamService;
use App\Services\SYSTEM\Application\Reports\DatasetRegistry;
use App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationScopeResolver;
use App\Services\SYSTEM\Application\Reports\DefinisiLaporan;
use App\Services\SYSTEM\Application\Reports\DefinisiValidator;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\ReportExportService;
use App\Services\SYSTEM\Application\Reports\ReportRunner;
use App\Services\SYSTEM\Application\Reports\ReportTemplateService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Studio laporan untuk modul SOP.
 *
 * MODULE DIPATRI DI SINI, tak pernah dibaca dari permintaan. Controller inilah
 * satu-satunya tempat kata 'sop' muncul di jalur ini; seluruh lapisan di
 * bawahnya menerimanya lewat konstruktor.
 */
class SopExportStudioController extends Controller
{
    private const MODULE = 'sop';
    private const PAGE_MONITORING = 'menuSopMonitoringPage';
    private const BATAS_PRATINJAU_RINGKASAN = 200;
    private const BATAS_PRATINJAU_RINCI = 100;

    public function __construct(
        private DatasetRegistry $registry,
        private DefinisiValidator $validator,
        private ReportRunner $runner,
        private ReportExportService $exportSvc,
        private SopTeamService $team,
    ) {}

    private function templates(): ReportTemplateService
    {
        return new ReportTemplateService(
            self::MODULE,
            $this->registry,
            $this->validator,
            SopTemplateSistem::semua(),
        );
    }

    /**
     * Siapa yang menjalankan, dan apa yang boleh ia lihat.
     *
     * Tidak membuat sistem izin baru: kelayakan diikat ke hak halaman
     * monitoring admin SOP yang SUDAH ada. usablePageTypes bernilai null
     * berarti system admin (akses seluruh halaman) — lihat docblock
     * AuthorizationScopeResolver.
     */
    private function lingkup(): Lingkup
    {
        $user = Auth::user();
        $kode = (string) optional($user->karyawan)->Kode_Karyawan;

        $scope = AuthorizationScopeResolver::resolve($user);
        $halaman = $scope['usablePageTypes'];

        $adminSop = $scope['isSystemAdmin']
            || $halaman === null
            || in_array(self::PAGE_MONITORING, (array) $halaman, true);

        return new Lingkup(
            kodeKaryawan: $kode,
            adminPenuh: $adminSop,
            anggotaTim: $adminSop ? [] : $this->team->teamKodes($kode),
            bolehFieldTerbatas: $adminSop,
        );
    }

    public function katalog(string $dataset)
    {
        try {
            $ds = $this->registry->ambil(self::MODULE, $dataset);
        } catch (RuntimeException $e) {
            return ResponseHelper::errorWithCode('Dataset tidak dikenal.', 'REPORT_DATASET_UNKNOWN', 404);
        }

        return ResponseHelper::success([
            'grain' => $ds->grain(),
            'dimensi' => $ds->dimensi(),
            'ukuran' => $ds->ukuran(),
            'saringan' => $ds->saringan(),
        ]);
    }

    public function galeri()
    {
        return ResponseHelper::success($this->templates()->galeri((string) optional(Auth::user()->karyawan)->Kode_Karyawan));
    }

    public function simpan(SimpanTemplateRequest $request)
    {
        $definisi = DefinisiLaporan::dariArray($request->input('definisi', []));

        try {
            $ds = $this->registry->ambil(self::MODULE, $definisi->dataset);
        } catch (RuntimeException $e) {
            return ResponseHelper::errorWithCode('Dataset tidak dikenal.', 'REPORT_DATASET_UNKNOWN', 404);
        }

        $galat = $this->validator->periksa($definisi, $ds);
        if ($galat !== []) {
            return ResponseHelper::errorWithCode('Definisi laporan tidak sah.', 'REPORT_DEFINITION_INVALID', 422, $galat);
        }

        $kode = $this->templates()->simpan($request->validated(), (string) optional(Auth::user()->karyawan)->Kode_Karyawan);

        return ResponseHelper::success(['kode' => $kode], 'Template tersimpan.', 201);
    }

    public function pratinjau(JalankanLaporanRequest $request)
    {
        $definisi = DefinisiLaporan::dariArray($request->input('definisi', []));

        try {
            $ds = $this->registry->ambil(self::MODULE, $definisi->dataset);
        } catch (RuntimeException $e) {
            return ResponseHelper::errorWithCode('Dataset tidak dikenal.', 'REPORT_DATASET_UNKNOWN', 404);
        }

        $galat = $this->validator->periksa($definisi, $ds);
        if ($galat !== []) {
            return ResponseHelper::errorWithCode('Definisi laporan tidak sah.', 'REPORT_DEFINITION_INVALID', 422, $galat);
        }

        $batas = $definisi->mode === 'rinci' ? self::BATAS_PRATINJAU_RINCI : self::BATAS_PRATINJAU_RINGKASAN;

        try {
            $hasil = $this->runner->jalankan($definisi, $ds, $this->lingkup(), 'PRATINJAU', $batas);
        } catch (\Throwable $e) {
            Log::error('REPORT_PREVIEW_FAILED', [
                'module' => self::MODULE,
                'dataset' => $definisi->dataset,
                'definisi_hash' => md5(json_encode($definisi->toArray())),
                'exception' => $e,
            ]);

            // Pratinjau yang terlalu berat TIDAK memblokir export. Layar
            // memperlakukan kode ini sebagai turun anggun, bukan kegagalan.
            return ResponseHelper::errorWithCode(
                'Definisi ini terlalu berat untuk dipratinjau. Masih bisa dijalankan sebagai export.',
                'REPORT_PREVIEW_TOO_HEAVY',
                422
            );
        }

        return ResponseHelper::success($hasil);
    }

    public function jalankan(JalankanLaporanRequest $request)
    {
        $kodeTemplate = $request->input('template_kode');

        try {
            $sumber = $kodeTemplate
                ? $this->templates()->ambil($kodeTemplate)
                : ['nama' => 'Laporan', 'definisi' => $request->input('definisi', [])];
        } catch (RuntimeException $e) {
            return ResponseHelper::errorWithCode('Template tidak ditemukan.', 'REPORT_TEMPLATE_FORBIDDEN', 403);
        }

        $definisi = DefinisiLaporan::dariArray($sumber['definisi']);

        try {
            $ds = $this->registry->ambil(self::MODULE, $definisi->dataset);
        } catch (RuntimeException $e) {
            return ResponseHelper::errorWithCode('Dataset tidak dikenal.', 'REPORT_DATASET_UNKNOWN', 404);
        }

        $galat = $this->validator->periksa($definisi, $ds);
        if ($galat !== []) {
            return ResponseHelper::errorWithCode('Definisi laporan tidak sah.', 'REPORT_DEFINITION_INVALID', 422, $galat);
        }

        $l = $this->lingkup();
        $exportId = $this->exportSvc->makeExportId();

        // Cuplikan definisi + lingkup ikut ke payload: mengubah atau menghapus
        // template tidak boleh merusak job yang sudah antre.
        $this->exportSvc->startJob($exportId, $request->input('format'), [
            'module' => self::MODULE,
            'template_kode' => $kodeTemplate,
            'judul' => strtoupper($sumber['nama']),
            'definisi' => $definisi->toArray(),
            'lingkup' => [
                'kode_karyawan' => $l->kodeKaryawan,
                'admin_penuh' => $l->adminPenuh,
                'anggota_tim' => $l->anggotaTim,
                'boleh_field_terbatas' => $l->bolehFieldTerbatas,
            ],
        ], 0, $l->kodeKaryawan);

        $this->exportSvc->dispatchJob($exportId);

        return ResponseHelper::success(['task_id' => $exportId], 'Laporan diproses. Cek panel Aktivitas.', 202);
    }
}
```

**Prasyarat:** `SopTeamService::teamKodes()` saat ini `private`, padahal itu
satu-satunya tempat yang sudah benar dalam membaca `HRIS_Karyawan_Team`
(trim, filter, unique). Menyalin query-nya ke controller berarti menyalin
ketiga pembersihan itu juga, dan salinan semacam itulah yang dulu membuat kolom
`Terlambat` menyimpang antara layar dan export.

Ubah visibilitasnya, tanpa mengubah isinya:

```bash
sed -i 's/    private function teamKodes(string \$leader): array/    public function teamKodes(string \$leader): array/' app/Services/LMS/SOP/SopTeamService.php
grep -n 'function teamKodes' app/Services/LMS/SOP/SopTeamService.php
```

Expected: `public function teamKodes(string $leader): array`

Klasifikasi scope: **related change**, perubahan visibilitas murni aditif —
tidak ada pemanggil lama yang terpengaruh.

- [ ] **Step 6: Tulis route**

```php
<?php

use App\Http\Controllers\LMS\SOP\Export\SopExportStudioController;
use Illuminate\Support\Facades\Route;

// Prefix dan middleware menyalin routes/LMS/SOP/Governance/SopGovernance.php.
// Sesuaikan bila grup di sana berbeda.
Route::prefix('sop/export-studio')->group(function () {
    Route::get('/katalog/{dataset}', [SopExportStudioController::class, 'katalog']);
    Route::get('/template', [SopExportStudioController::class, 'galeri']);
    Route::post('/template', [SopExportStudioController::class, 'simpan']);
    Route::post('/pratinjau', [SopExportStudioController::class, 'pratinjau']);
    Route::post('/jalankan', [SopExportStudioController::class, 'jalankan']);
});
```

Daftarkan di `routes/web.php` mengikuti cara berkas route SOP lain dimuat.

- [ ] **Step 7: Jalankan test, pastikan lulus**

Run: `unset DATABASE_URL && php artisan test --filter=SopExportStudioApiTest`
Expected: PASS, 5 test

- [ ] **Step 8: Jalankan gerbang verifikasi struktural**

```bash
unset DATABASE_URL
composer dump-autoload -o --strict-psr
php scripts/verify-references.php --max=0
php scripts/verify-frontend-imports.php
php scripts/verify-inertia-pages.php
php scripts/route-snapshot.php /tmp/sesudah-route.json
php scripts/console-snapshot.php /tmp/sesudah-console.json
diff <(python3 -m json.tool plans/sop/baseline/export-studio-sebelum-route.json) \
     <(python3 -m json.tool /tmp/sesudah-route.json) | head -60
diff <(python3 -m json.tool plans/sop/baseline/export-studio-sebelum-console.json) \
     <(python3 -m json.tool /tmp/sesudah-console.json)
```

Expected: `verify-*` bersih. Diff route menunjukkan **tepat lima route baru** di
bawah `sop/export-studio` dan tidak ada yang lain. Diff console kosong. Setiap
beda di luar itu harus dijelaskan atau diperbaiki — **jangan** menaikkan `--max`
untuk meloloskan temuan baru.

- [ ] **Step 9: Jalankan seluruh suite backend**

Run: `unset DATABASE_URL && npm run test:backend:unit && npm run test:backend:feature`
Expected: PASS tanpa kegagalan baru.

- [ ] **Step 10: Lengkapi dokumentasi tabel**

Isi bagian "dipakai oleh" di `docs/database/tables/hris/n-hris-report-template.md`
dengan class dan endpoint yang sekarang benar-benar ada:
`ReportTemplateService`, `ReportExportService`, `SopExportStudioController`,
dan kelima route di atas.

- [ ] **Step 11: Commit**

```bash
git add app/Http/Controllers/LMS/SOP/Export/SopExportStudioController.php \
        app/Http/Requests/LMS/SOP/Export/ \
        app/Services/LMS/SOP/SopTeamService.php \
        routes/LMS/SOP/Export/SopExportStudio.php \
        routes/web.php \
        docs/database/tables/hris/n-hris-report-template.md \
        tests/Feature/LMS/SOP/Export/SopExportStudioApiTest.php
git commit -m "feat(sop): endpoint Export Studio dengan kontrak error_code

Route bertambah lima di bawah sop/export-studio; snapshot console tidak berubah."
```

---

## Fase berikutnya (rencana terpisah)

**Fase 2 — Layar Studio.** `components/SYSTEM/ReportStudio/`,
`SopExportStudioPage.vue`, pratinjau langsung dengan pembatalan request, empat
status layar wajib, dan test Vitest. Berdiri di atas API fase 1.

**Fase 3 — Katalog penuh dan pensiun jalur lama.** Empat dataset sisanya
(dokumen, katalog, absensi, audit), enam template sistem sisanya, `opsi.batas`,
saringan `wajib_saat_jalan`, bagan SVG dan XLSX, skrip menu `DML-2026-010`,
matriks regresi penuh 63 + 8 kasus, lalu pengalihan endpoint lama.

Satu mekanisme validator ikut ke fase itu dan jangan sampai hilang: spec §8
mewajibkan **saringan periode** pada dataset `audit` dan `absensi` — tabel
kejadian yang tumbuh selamanya, dan tanpa batas waktu query-nya memindai seluruh
sejarah. `DatasetLaporan` perlu menyatakan saringan wajibnya (mis. lewat flag
pada `Saringan`), dan `DefinisiValidator` menolak definisi yang tak memuatnya.
Fase 1 tidak membutuhkannya karena dataset `kepatuhan` sengaja tidak mewajibkan
periode.

Kedua rencana ditulis setelah fase ini selesai dan review-nya lulus — bentuk
akhirnya bergantung pada apa yang ditemukan harness regresi di Task 10.

Dari tiga keputusan terbuka di spec §14, satu terjawab oleh rencana ini:
pemetaan tingkat `terbatas` diikat ke `menuSopMonitoringPage` lewat
`AuthorizationScopeResolver::resolve()` (Task 11). Dua sisanya — konfirmasi
aktor audit dan penempatan `components/SYSTEM/` — masing-masing jadi prasyarat
Task 1 dan pekerjaan fase 2.
