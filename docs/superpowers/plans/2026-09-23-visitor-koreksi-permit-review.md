# Koreksi Data Permit saat Review — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Verifikator yang memegang giliran approval dapat mengoreksi jenis permit (antar kategori karyawan), plat, model, warna, tahun, dan keterangan pada permit karyawan yang masih PENDING, dengan alasan wajib dan riwayat per field.

**Architecture:** Satu service baru `PermitKoreksiService` memegang aturan dan transaksi koreksi; `VisitorPermitChecklistService` mendapat penyelarasan snapshot yang mempertahankan jawaban relevan; controller verifikator hanya menambah method tipis. Frontend menambah dua komponen kecil (dialog koreksi, riwayat) yang dipasang di `PermitReviewModal.vue` tanpa memperbesar logikanya.

**Tech Stack:** Laravel 11 (query builder, tanpa Eloquent/migration), SQL Server, Vue 3 Options API + Inertia, komponen `@/components/ui`, PHPUnit (suite Integration terhadap DB development dengan `DatabaseTransactions`), Vitest + `@vue/test-utils`.

**Spec:** `docs/superpowers/specs/2026-09-23-visitor-koreksi-permit-review-design.md`

## Global Constraints

- Tidak ada Laravel migration; skema lewat `database/ddl/DDL-2026-138-visitor-koreksi-permit/{forward,drop}.sql` + entri `database/ddl/status.json` + `docs/changes/<id>/README.md` (AGENTS.md).
- Agen hanya menjalankan **forward DDL di development** (`Web_HRIS`) **setelah user menyetujui SQL-nya**; tidak pernah production, tidak pernah `drop.sql`.
- Tanpa CHECK constraint untuk daftar nilai; validasi `Nama_Field` di backend.
- Kategori tujuan: aktif + `Flag_Muncul_Pengajuan` NULL/`Y` — penyaring yang **sama** dengan form karyawan (satu sumber).
- Hanya permit `Status_Permit = 'PENDING'`, `Sumber_Pengajuan` HCIS (NULL dibaca HCIS), `No_Transaksi_Tamu` NULL, dan pengguna lolos `ApprovalRequestService::validateApproverAccess`.
- `Id_Master_Kategori_Permit` dan `Snapshot_Kategori_Permit` selalu diubah bersama.
- Alasan wajib, maks 500 karakter; permintaan tanpa perubahan ditolak 422 `VISITOR_PERMIT_KOREKSI_TANPA_PERUBAHAN`.
- Kode galat: 404 `…_TIDAK_DITEMUKAN`, 409 `…_STATUS_TIDAK_VALID`, 403 `…_AKSES_DITOLAK`, 422 `…_VALIDASI` / `…_TANPA_PERUBAHAN` (prefix `VISITOR_PERMIT_KOREKSI`).
- Tidak ada notifikasi ke pemohon; approval tidak di-reset.
- Letak berkas mengikuti `VISITOR › Workflow › Verification` (lihat `docs/architecture/module-registry.md`).
- Nilai dari SQL Server via PDO datang sebagai **string** — selalu cast `(int)` sebelum membandingkan id.

## Review Focus

1. **Koreksi ganda serentak** — dua verifikator menyimpan bersamaan: yang kedua harus membaca data hasil yang pertama (kunci `lockForUpdate` pada header) dan riwayatnya mencatat nilai lama yang benar. → Task 4: tes `test_nilai_lama_riwayat_dibaca_setelah_kunci`.
2. **Permit yang giliran approvalnya kedaluwarsa** (`Expired_At` lewat) terbaca EXPIRED, bukan PENDING — koreksi harus ditolak 409 walau `Flag_Approval` masih NULL. → Task 4: `test_approval_kedaluwarsa_ditolak`.
3. **Keterangan diubah dari isi menjadi kosong / spasi** harus tercatat sebagai perubahan ke NULL, dan spasi di sekitar teks yang sama tidak dianggap perubahan. → Task 4: `test_keterangan_spasi_bukan_perubahan_dan_kosong_jadi_null`.
4. **Plat dengan huruf kecil / spasi berlebih** (`" bg  1234 ab "`) harus dinormalkan sama seperti pengajuan dan tidak tercatat sebagai perubahan bila hasilnya identik. → Task 4: `test_plat_dinormalkan_sebelum_dibandingkan`.
5. **Baris akses inspeksi yang sudah berakhir** (bukan AKTIF) tidak boleh dihidupkan lagi oleh koreksi. → Task 4: `test_akses_inspeksi_yang_sudah_berakhir_tidak_dihidupkan`.

---

## File Structure

| Berkas | Tanggung jawab |
|---|---|
| `database/ddl/DDL-2026-138-visitor-koreksi-permit/forward.sql`, `drop.sql` | Tabel riwayat koreksi + indeks |
| `docs/changes/DDL-2026-138-visitor-koreksi-permit/README.md` | Catatan perubahan (AGENTS.md) |
| `docs/database/tables/visitor/n-emi-visitor-transaksi-permit-koreksi.md` | Dokumentasi tabel |
| `app/Services/VISITOR/Permit/VehiclePermit/KategoriPermitPengajuan.php` (baru) | Satu sumber penyaring kategori yang boleh diajukan karyawan |
| `app/Services/VISITOR/Permit/VehiclePermit/VisitorPermitMasterService.php` (baru) | Satu sumber master data form permit (dipakai form karyawan & dialog koreksi) |
| `app/Http/Controllers/VISITOR/Permit/VehiclePermit/VehiclePermitController.php` | Memakai dua kelas di atas (tanpa ubah perilaku) |
| `app/Services/VISITOR/Permit/VehiclePermit/VisitorPermitChecklistService.php` | + `pratinjauSelaras`, `selaraskanSnapshot` |
| `app/Services/VISITOR/Workflow/Verification/PermitKoreksiException.php` (baru) | Galat bernama dengan kode + status HTTP |
| `app/Services/VISITOR/Workflow/Verification/PermitKoreksiService.php` (baru) | Aturan, transaksi, riwayat koreksi |
| `app/Http/Requests/VISITOR/Workflow/Verification/PermitKoreksiRequest.php` (baru) | Bentuk input |
| `app/Http/Controllers/VISITOR/Workflow/Verification/VerifikatorVisitorController.php` | 3 method tipis + `showPermit` diperkaya |
| `routes/VISITOR/Workflow/Verification/VerifikatorVisitorWeb.php` | 3 route baru |
| `resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/masterKendaraan.js` (baru) | Helper pohon model & daftar tahun (dipakai modal & dialog) |
| `…/components/PermitKoreksiDialog.vue` (baru) | Form koreksi + pratinjau checklist |
| `…/components/PermitKoreksiRiwayat.vue` (baru) | Tampilan riwayat |
| `…/components/PermitReviewModal.vue` | Tombol, pemasangan komponen, muat ulang |
| Tes: `tests/Integration/VISITOR/Permit/VehiclePermit/VisitorPermitChecklistSelarasTest.php`, `tests/Integration/VISITOR/Workflow/Verification/PermitKoreksiServiceTest.php`, `tests/Feature/VISITOR/VisitorRouteContractTest.php`, `…/components/masterKendaraan.test.js`, `…/components/PermitKoreksiDialog.test.js`, `…/components/PermitKoreksiRiwayat.test.js` | |

Perintah tes (dari root repo):
- PHP: `env -u DATABASE_URL php artisan test --filter=<NamaKelasTest>`
- JS: `npx vitest run <path>`

---

### Task 1: DDL-2026-138 — tabel riwayat koreksi

**Files:**
- Create: `database/ddl/DDL-2026-138-visitor-koreksi-permit/forward.sql`
- Create: `database/ddl/DDL-2026-138-visitor-koreksi-permit/drop.sql`
- Create: `docs/changes/DDL-2026-138-visitor-koreksi-permit/README.md`
- Create: `docs/database/tables/visitor/n-emi-visitor-transaksi-permit-koreksi.md`
- Modify: `database/ddl/status.json` (tambah entri di `changes`)

**Interfaces:**
- Produces: tabel `dbo.N_EMI_VISITOR_Transaksi_Permit_Koreksi` dengan kolom `Id_Permit_Koreksi, No_Transaksi, Kelompok_Koreksi, Nama_Field, Nilai_Lama, Nilai_Baru, Alasan, Created_At, Created_By`.

- [ ] **Step 1: Laporkan item DDL yang masih tertunda** (AGENTS.md: wajib di awal tugas DDL)

Run: `python3 -c "import json;d=json.load(open('database/ddl/status.json'))['changes'];[print(k,v.get('status')) for k,v in d.items() if v.get('status') not in ('COMPLETED',)]"`
Sampaikan daftar itu ke user (perubahan ini tidak bergantung padanya).

- [ ] **Step 2: Pastikan objek belum ada**

Run: `php artisan tinker --execute='var_dump(DB::selectOne("SELECT OBJECT_ID(?) AS id", ["dbo.N_EMI_VISITOR_Transaksi_Permit_Koreksi"])->id);'`
Expected: `NULL`

- [ ] **Step 3: Tulis `forward.sql`**

```sql
-- Change ID: DDL-2026-138-visitor-koreksi-permit
-- Riwayat koreksi data permit oleh verifikator saat review. Satu baris per field.

IF OBJECT_ID('dbo.N_EMI_VISITOR_Transaksi_Permit_Koreksi', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_EMI_VISITOR_Transaksi_Permit_Koreksi (
        Id_Permit_Koreksi int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_VISITOR_Permit_Koreksi PRIMARY KEY,
        No_Transaksi varchar(50) NOT NULL,
        Kelompok_Koreksi uniqueidentifier NOT NULL,
        Nama_Field varchar(50) NOT NULL,
        Nilai_Lama nvarchar(500) NULL,
        Nilai_Baru nvarchar(500) NULL,
        Alasan nvarchar(500) NOT NULL,
        Created_At datetime2 NOT NULL
            CONSTRAINT DF_VISITOR_Permit_Koreksi_Created_At DEFAULT (sysdatetime()),
        Created_By varchar(100) NOT NULL
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_VISITOR_Permit_Koreksi_No_Transaksi')
BEGIN
    CREATE INDEX IX_VISITOR_Permit_Koreksi_No_Transaksi
        ON dbo.N_EMI_VISITOR_Transaksi_Permit_Koreksi (No_Transaksi)
        INCLUDE (Created_At);
END;
GO
```

- [ ] **Step 4: Tulis `drop.sql`** (alat bantu manual; agen TIDAK menjalankannya)

```sql
-- Change ID: DDL-2026-138-visitor-koreksi-permit (manual cleanup only)
IF OBJECT_ID('dbo.N_EMI_VISITOR_Transaksi_Permit_Koreksi', 'U') IS NOT NULL
    DROP TABLE dbo.N_EMI_VISITOR_Transaksi_Permit_Koreksi;
GO
```

- [ ] **Step 5: Tulis dokumentasi tabel** `docs/database/tables/visitor/n-emi-visitor-transaksi-permit-koreksi.md` — ikuti format `docs/database/tables/visitor/n-visitor-tamu-efektif.md`, mencakup: tujuan; tabel kolom (tipe, null, arti); `Nama_Field` yang sah (`KATEGORI_PERMIT`, `PLAT_NOMOR`, `MODEL_KENDARAAN`, `WARNA`, `TAHUN`, `KETERANGAN`) divalidasi di `PermitKoreksiService::FIELD_*`; `Created_By` = `Kode_Karyawan` verifikator dari `VisitorApprovalPolicyService::requesterKode($request->user())`; relasi logis ke `N_EMI_VISITOR_Transaksi_Permit.No_Transaksi` tanpa FK (pola VISITOR); alasan indeks; pemakai: `PermitKoreksiService`, `VerifikatorVisitorController::koreksiPermit|showPermit`, route `POST verifikator-permit/{noTransaksi}/koreksi`; change ID dan path SQL.

- [ ] **Step 6: Tulis `docs/changes/DDL-2026-138-visitor-koreksi-permit/README.md`** — ikuti `docs/changes/DDL-2026-135-visitor-penanggung-jawab-mitra/README.md`: change ID, target `Web_HRIS`, deskripsi, objek terdampak, path DDL/doc, checksum `forward.sql` (`sha256sum database/ddl/DDL-2026-138-visitor-koreksi-permit/forward.sql`), status, bagian **Agent completed** dan **User tasks** (review, catat ke SQL Tracker, eksekusi production, verifikasi).

- [ ] **Step 7: Tambah entri `status.json`** (salin bentuk entri `DDL-2026-135-…`):

```json
"DDL-2026-138-visitor-koreksi-permit": {
  "change_id": "DDL-2026-138-visitor-koreksi-permit",
  "type": "DDL",
  "title": "Riwayat koreksi data permit oleh verifikator",
  "description": "Verifikator pemegang giliran approval dapat mengoreksi jenis permit (antar kategori karyawan), plat, model, warna, tahun, dan keterangan pada permit karyawan PENDING. Setiap field yang berubah dicatat satu baris beserta alasan dan pelakunya.",
  "status": "REVIEW_REQUIRED",
  "artifact": "database/ddl/DDL-2026-138-visitor-koreksi-permit/forward.sql",
  "documentation": "docs/changes/DDL-2026-138-visitor-koreksi-permit/README.md",
  "objek_terdampak": ["dbo.N_EMI_VISITOR_Transaksi_Permit_Koreksi (CREATE TABLE + IX_VISITOR_Permit_Koreksi_No_Transaksi)"],
  "development": {"status": "PENDING", "database": "Web_HRIS", "result": null},
  "sql_tracker": {"status": "PENDING", "reference": null},
  "production": {"status": "PENDING", "note": null},
  "verification": {"status": "PENDING", "production_verified": false},
  "catatan": null
}
```

Run: `python3 -m json.tool database/ddl/status.json > /dev/null && echo OK` → `OK`

- [ ] **Step 8: STOP — minta persetujuan user.** Tampilkan isi `forward.sql` persis dan target `Web_HRIS` development. Jangan lanjut ke Step 9 tanpa "ya" eksplisit.

- [ ] **Step 9: Jalankan forward DDL di development** (setelah disetujui)

Run: `php artisan tinker --execute='foreach (preg_split("/^GO\s*$/m", file_get_contents("database/ddl/DDL-2026-138-visitor-koreksi-permit/forward.sql")) as $b) { if (trim($b) !== "") DB::unprepared($b); } echo "ok";'`
Lalu verifikasi: `php artisan tinker --execute='print_r(DB::select("SELECT c.name, t.name tipe, c.max_length, c.is_nullable FROM sys.columns c JOIN sys.types t ON t.user_type_id=c.user_type_id WHERE c.object_id=OBJECT_ID(?)", ["dbo.N_EMI_VISITOR_Transaksi_Permit_Koreksi"]));'`
Expected: 9 kolom sesuai forward.sql. Perbarui `status.json`: `status` → `DEV_EXECUTED`, `development.status` → `DONE`, `development.result` → tanggal + hasil verifikasi.

- [ ] **Step 10: Commit**

```bash
git add database/ddl/DDL-2026-138-visitor-koreksi-permit database/ddl/status.json docs/changes/DDL-2026-138-visitor-koreksi-permit docs/database/tables/visitor/n-emi-visitor-transaksi-permit-koreksi.md
git commit -m "feat(visitor): DDL-2026-138 tabel riwayat koreksi permit"
```

---

### Task 2: Satu sumber kategori pengajuan & master form permit

**Files:**
- Create: `app/Services/VISITOR/Permit/VehiclePermit/KategoriPermitPengajuan.php`
- Create: `app/Services/VISITOR/Permit/VehiclePermit/VisitorPermitMasterService.php`
- Modify: `app/Http/Controllers/VISITOR/Permit/VehiclePermit/VehiclePermitController.php` (`terapkanKategoriPengajuan` :1172, `getMasterData` ~:940-990, pemeriksaan kategori di `store` :242-246)
- Test: `tests/Integration/VISITOR/Permit/VehiclePermit/KategoriPermitMunculPengajuanTest.php` (sudah ada — harus tetap hijau), tambah `tests/Integration/VISITOR/Permit/VehiclePermit/KategoriPermitPengajuanTest.php`

**Interfaces:**
- Produces:
  - `KategoriPermitPengajuan::saring(\Illuminate\Database\Query\Builder $query): Builder`
  - `KategoriPermitPengajuan::temukan(int $idKategori): ?object` — baris master aktif & boleh diajukan, atau null
  - `VisitorPermitMasterService::untukPengajuan(): array` — kunci `plat_codes, vehicle_types, models, colors, kategoris, durations` (isi identik dengan respons `getMasterData` hari ini)

- [ ] **Step 1: Tulis tes gagal** `tests/Integration/VISITOR/Permit/VehiclePermit/KategoriPermitPengajuanTest.php`

```php
<?php

namespace Tests\Integration\VISITOR\Permit\VehiclePermit;

use App\Services\VISITOR\Permit\VehiclePermit\KategoriPermitPengajuan;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitMasterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class KategoriPermitPengajuanTest extends TestCase
{
    use DatabaseTransactions;

    private function buatKategori(?string $muncul, string $aktif = 'Y'): int
    {
        return (int) DB::table('N_EMI_VISITOR_Master_Kategori_Permit')->insertGetId([
            'Nama_Kategori' => 'UJI '.Str::upper(Str::random(8)),
            'Deskripsi_Akses' => 'Kategori uji',
            'Kode_Warna_UI' => '#6366F1',
            'Flag_Aktif' => $aktif,
            'Flag_Muncul_Pengajuan' => $muncul,
        ]);
    }

    public function test_temukan_menerima_kategori_yang_muncul_dan_null(): void
    {
        $this->assertNotNull(KategoriPermitPengajuan::temukan($this->buatKategori('Y')));
        $this->assertNotNull(KategoriPermitPengajuan::temukan($this->buatKategori(null)));
    }

    public function test_temukan_menolak_kategori_tersembunyi_dan_nonaktif(): void
    {
        $this->assertNull(KategoriPermitPengajuan::temukan($this->buatKategori('T')));
        $this->assertNull(KategoriPermitPengajuan::temukan($this->buatKategori('Y', 'T')));
    }

    public function test_coklat_tidak_pernah_ditemukan(): void
    {
        $idCoklat = (int) DB::table('N_EMI_VISITOR_Master_Kategori_Permit')->where('Nama_Kategori', 'COKLAT')->value('Id_Kategori_Permit');

        $this->assertGreaterThan(0, $idCoklat, 'Master COKLAT tidak ada di DB development.');
        $this->assertNull(KategoriPermitPengajuan::temukan($idCoklat));
    }

    public function test_master_pengajuan_hanya_berisi_kategori_yang_boleh_diajukan(): void
    {
        $tersembunyi = $this->buatKategori('T');
        $master = app(VisitorPermitMasterService::class)->untukPengajuan();

        $this->assertSame(
            ['plat_codes', 'vehicle_types', 'models', 'colors', 'kategoris', 'durations'],
            array_keys($master),
        );
        $this->assertNotContains(
            $tersembunyi,
            collect($master['kategoris'])->pluck('Id_Kategori_Permit')->map(fn ($v) => (int) $v)->all(),
        );
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `env -u DATABASE_URL php artisan test --filter=KategoriPermitPengajuanTest`
Expected: FAIL — `Class "App\Services\VISITOR\Permit\VehiclePermit\KategoriPermitPengajuan" not found`

- [ ] **Step 3: Buat `KategoriPermitPengajuan.php`**

```php
<?php

namespace App\Services\VISITOR\Permit\VehiclePermit;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Kategori permit yang boleh dipilih karyawan. Satu sumber untuk dropdown
 * pengajuan, validasi pengajuan, dan koreksi verifikator: penyaring yang
 * hanya ada di satu jalur membiarkan COKLAT lolos lewat jalur lainnya.
 * NULL dibaca boleh tampil (lihat KategoriPermitMunculPengajuanTest).
 */
final class KategoriPermitPengajuan
{
    public static function saring(Builder $query): Builder
    {
        return $query->whereNull('Flag_Muncul_Pengajuan')->orWhere('Flag_Muncul_Pengajuan', 'Y');
    }

    public static function temukan(int $idKategori): ?object
    {
        return DB::table('N_EMI_VISITOR_Master_Kategori_Permit')
            ->where('Id_Kategori_Permit', $idKategori)
            ->where('Flag_Aktif', 'Y')
            ->where(fn (Builder $query) => self::saring($query))
            ->first();
    }
}
```

- [ ] **Step 4: Buat `VisitorPermitMasterService.php`** — pindahkan isi array di `VehiclePermitController::getMasterData` apa adanya:

```php
<?php

namespace App\Services\VISITOR\Permit\VehiclePermit;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Master data form permit karyawan; dipakai form pengajuan dan dialog koreksi verifikator. */
class VisitorPermitMasterService
{
    public function untukPengajuan(): array
    {
        return [
            'plat_codes' => DB::table('N_EMI_VISITOR_Master_Plat_Kendaraan')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Kode_Plat')
                ->pluck('Kode_Plat'),
            'vehicle_types' => DB::table('N_EMI_VISITOR_Master_Vehicle_Type')
                ->where('Flag_Aktif', 'Y')
                ->where(function ($query) {
                    $query->whereNull('Akses')->orWhereIn('Akses', ['PERMIT', 'ALL']);
                })
                ->orderBy('Nama_Tipe_Kendaraan')
                ->select('Id_Vehicle_Type as Id', 'Id_Vehicle_Type', 'Nama_Tipe_Kendaraan as Nama_Vehicle_Type', 'Nama_Tipe_Kendaraan')
                ->get(),
            'models' => DB::table('N_EMI_VISITOR_Master_Vehicle_Model')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Nama_Model_Kendaraan')
                ->select('Id_Vehicle_Model as Id', 'Id_Vehicle_Model', 'Id_Master_Tipe', 'Nama_Model_Kendaraan')
                ->get(),
            'colors' => DB::table('N_EMI_VISITOR_Master_Vehicle_Color')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Nama_Warna')
                ->select('Id_Master_Color as Id', 'Id_Master_Color', 'Nama_Warna')
                ->get(),
            'kategoris' => DB::table('N_EMI_VISITOR_Master_Kategori_Permit')
                ->where('Flag_Aktif', 'Y')
                ->where(fn (Builder $query) => KategoriPermitPengajuan::saring($query))
                ->orderBy('Nama_Kategori')
                ->select(
                    'Id_Kategori_Permit as Id', 'Id_Kategori_Permit', 'Nama_Kategori',
                    'Deskripsi_Akses as Deskripsi', 'Deskripsi_Akses',
                    'Kode_Warna_UI as Kode_Warna', 'Kode_Warna_UI',
                )
                ->get(),
            'durations' => DB::table('N_EMI_VISITOR_Master_Durasi_Permit')
                ->where('Flag_Aktif', 'Y')
                ->where('Satuan_Waktu', 'MONTH')
                ->where('Jumlah_Angka', '>', 0)
                ->orderBy('Jumlah_Angka')
                ->select('Id_Durasi_Permit', 'Nama_Durasi', 'Jumlah_Angka', 'Satuan_Waktu')
                ->get(),
        ];
    }
}
```

Sebelum memindahkan, `diff` isi array lama dengan yang di atas — bila `getMasterData` di repo berbeda dari cuplikan ini, **salin versi repo** (yang di repo adalah kebenaran).

- [ ] **Step 5: Ubah controller** — tubuh `getMasterData` menjadi `return ResponseHelper::success(app(VisitorPermitMasterService::class)->untukPengajuan());` di dalam `try` yang sama; `terapkanKategoriPengajuan($query)` menjadi `return KategoriPermitPengajuan::saring($query);`. Tambah `use` untuk kedua kelas.

- [ ] **Step 6: Jalankan tes**

Run: `env -u DATABASE_URL php artisan test --filter='KategoriPermitPengajuanTest|KategoriPermitMunculPengajuanTest'`
Expected: PASS semua.

- [ ] **Step 7: Commit**

```bash
git add app/Services/VISITOR/Permit/VehiclePermit/KategoriPermitPengajuan.php app/Services/VISITOR/Permit/VehiclePermit/VisitorPermitMasterService.php app/Http/Controllers/VISITOR/Permit/VehiclePermit/VehiclePermitController.php tests/Integration/VISITOR/Permit/VehiclePermit/KategoriPermitPengajuanTest.php
git commit -m "refactor(visitor): satu sumber kategori pengajuan dan master form permit"
```

---

### Task 3: Penyelarasan snapshot checklist

**Files:**
- Modify: `app/Services/VISITOR/Permit/VehiclePermit/VisitorPermitChecklistService.php` (ekstrak kueri pertanyaan dari `buatSnapshot` :30-60)
- Test: `tests/Integration/VISITOR/Permit/VehiclePermit/VisitorPermitChecklistSelarasTest.php`

**Interfaces:**
- Produces:
  - `pratinjauSelaras(string $noTransaksi, int $idKategoriPermit, ?int $idTipeKendaraan): array{dipertahankan:int, dibuang: list<array{teks:string, terjawab:bool}>, ditambah: list<array{teks:string}>}` — tidak menulis apa pun
  - `selaraskanSnapshot(string $noTransaksi, int $idDetailPermit, int $idKategoriPermit, ?int $idTipeKendaraan): array{dibuang:int, ditambah:int}` — baris yang pertanyaannya masih berlaku dipertahankan beserta `Jawaban_Admin`/`Catatan_Admin`

- [ ] **Step 1: Tulis tes gagal**

```php
<?php

namespace Tests\Integration\VISITOR\Permit\VehiclePermit;

use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitChecklistService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class VisitorPermitChecklistSelarasTest extends TestCase
{
    use DatabaseTransactions;

    private int $katA;
    private int $katB;
    private int $qUmum;
    private int $qA;
    private int $qB;

    protected function setUp(): void
    {
        parent::setUp();

        // Pertanyaan master lain dinonaktifkan dalam transaksi uji supaya
        // hitungan tidak bergantung pada isi master development.
        DB::table('N_EMI_VISITOR_Master_Approval_Question')->update(['Flag_Aktif' => 'T']);

        $this->katA = $this->kategori();
        $this->katB = $this->kategori();
        $this->qUmum = $this->pertanyaan('Umum', null);
        $this->qA = $this->pertanyaan('Khusus A', $this->katA);
        $this->qB = $this->pertanyaan('Khusus B', $this->katB);
    }

    public function test_pratinjau_menghitung_tanpa_menulis(): void
    {
        [$no] = $this->permitDenganChecklist($this->katA);
        $sebelum = DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')->where('No_Transaksi', $no)->get();

        $hasil = $this->svc()->pratinjauSelaras($no, $this->katB, null);

        $this->assertSame(1, $hasil['dipertahankan']);
        $this->assertSame([['teks' => 'Khusus A', 'terjawab' => true]], $hasil['dibuang']);
        $this->assertSame([['teks' => 'Khusus B']], $hasil['ditambah']);
        $this->assertEquals($sebelum, DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')->where('No_Transaksi', $no)->get());
    }

    public function test_selaraskan_mempertahankan_jawaban_yang_masih_berlaku(): void
    {
        [$no, $idDetail] = $this->permitDenganChecklist($this->katA);

        $hasil = $this->svc()->selaraskanSnapshot($no, $idDetail, $this->katB, null);

        $this->assertSame(['dibuang' => 1, 'ditambah' => 1], $hasil);
        $baris = DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')->where('No_Transaksi', $no)
            ->get()->keyBy(fn ($r) => (int) $r->Id_Master_Pertanyaan);

        $this->assertSame([$this->qUmum, $this->qB], $baris->keys()->sort()->values()->all());
        $this->assertSame('YA', $baris[$this->qUmum]->Jawaban_Admin);
        $this->assertNull($baris[$this->qB]->Jawaban_Admin);
    }

    public function test_selaraskan_tanpa_selisih_tidak_mengubah_apa_pun(): void
    {
        [$no, $idDetail] = $this->permitDenganChecklist($this->katA);

        $this->assertSame(['dibuang' => 0, 'ditambah' => 0], $this->svc()->selaraskanSnapshot($no, $idDetail, $this->katA, null));
    }

    private function svc(): VisitorPermitChecklistService
    {
        return app(VisitorPermitChecklistService::class);
    }

    private function kategori(): int
    {
        return (int) DB::table('N_EMI_VISITOR_Master_Kategori_Permit')->insertGetId([
            'Nama_Kategori' => 'UJI '.Str::upper(Str::random(8)),
            'Flag_Aktif' => 'Y',
            'Flag_Muncul_Pengajuan' => 'Y',
        ]);
    }

    private function pertanyaan(string $teks, ?int $kategori): int
    {
        return (int) DB::table('N_EMI_VISITOR_Master_Approval_Question')->insertGetId([
            'Teks_Pertanyaan' => $teks,
            'Target_Id_Kategori_Permit' => $kategori,
            'Target_Id_Tipe_Kendaraan' => null,
            'Flag_Aktif' => 'Y',
        ]);
    }

    /** @return array{0:string,1:int} nomor permit, id detail — checklist terjawab 'YA' semua */
    private function permitDenganChecklist(int $kategori): array
    {
        $no = 'PMT-SLR-'.Str::upper(Str::random(8));
        $idHeader = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $no, 'Kode_Karyawan_Pemohon' => 'A1', 'Sumber_Pengajuan' => 'HCIS',
            'Flag_Aktif' => 'Y', 'Created_At' => now(), 'Created_By' => 'A1',
            'Idempotency_Key' => 'SLR-'.Str::upper(Str::random(20)),
        ]);
        $idDetail = (int) DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insertGetId([
            'Id_Transaksi_Permit' => $idHeader, 'No_Transaksi' => $no, 'Id_Master_Kategori_Permit' => $kategori,
        ]);
        $this->svc()->buatSnapshot($no, $idDetail, $kategori, null);
        DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')->where('No_Transaksi', $no)->update(['Jawaban_Admin' => 'YA']);

        return [$no, $idDetail];
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `env -u DATABASE_URL php artisan test --filter=VisitorPermitChecklistSelarasTest`
Expected: FAIL — `Call to undefined method …::pratinjauSelaras()`

- [ ] **Step 3: Implementasi** — di `VisitorPermitChecklistService`, ekstrak kueri pertanyaan dari `buatSnapshot` menjadi `pertanyaanBerlaku` (dipakai `buatSnapshot` juga), lalu tambahkan dua method:

```php
    /** Pertanyaan master aktif yang berlaku untuk pasangan kategori + tipe kendaraan. */
    private function pertanyaanBerlaku(int $idKategoriPermit, ?int $idTipeKendaraan): \Illuminate\Support\Collection
    {
        return DB::table('N_EMI_VISITOR_Master_Approval_Question')
            ->where('Flag_Aktif', 'Y')
            ->where(function ($query) use ($idKategoriPermit) {
                $query->whereNull('Target_Id_Kategori_Permit')
                    ->orWhere('Target_Id_Kategori_Permit', $idKategoriPermit);
            })
            ->where(function ($query) use ($idTipeKendaraan) {
                $query->whereNull('Target_Id_Tipe_Kendaraan');

                if ($idTipeKendaraan) {
                    $query->orWhere('Target_Id_Tipe_Kendaraan', $idTipeKendaraan);
                }
            })
            ->orderBy('Id_Approval_Question')
            ->get()
            ->keyBy(fn ($p) => (int) $p->Id_Approval_Question);
    }

    /** @return array{0:\Illuminate\Support\Collection,1:\Illuminate\Support\Collection,2:\Illuminate\Support\Collection} ada, dibuang, ditambah */
    private function selisihSnapshot(string $noTransaksi, int $idKategoriPermit, ?int $idTipeKendaraan): array
    {
        $target = $this->pertanyaanBerlaku($idKategoriPermit, $idTipeKendaraan);
        $ada = DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')
            ->where('No_Transaksi', $noTransaksi)
            ->get(['Id_Permit_Checklist', 'Id_Master_Pertanyaan', 'Snapshot_Pertanyaan', 'Jawaban_Admin']);
        $idAda = $ada->map(fn ($r) => (int) $r->Id_Master_Pertanyaan)->all();

        $dibuang = $ada->reject(fn ($r) => $r->Id_Master_Pertanyaan !== null && $target->has((int) $r->Id_Master_Pertanyaan))->values();
        $ditambah = $target->reject(fn ($p, $id) => in_array((int) $id, $idAda, true))->values();

        return [$ada, $dibuang, $ditambah];
    }

    public function pratinjauSelaras(string $noTransaksi, int $idKategoriPermit, ?int $idTipeKendaraan): array
    {
        [$ada, $dibuang, $ditambah] = $this->selisihSnapshot($noTransaksi, $idKategoriPermit, $idTipeKendaraan);

        return [
            'dipertahankan' => $ada->count() - $dibuang->count(),
            'dibuang' => $dibuang->map(fn ($r) => [
                'teks' => (string) $r->Snapshot_Pertanyaan,
                'terjawab' => trim((string) $r->Jawaban_Admin) !== '',
            ])->all(),
            'ditambah' => $ditambah->map(fn ($p) => ['teks' => (string) $p->Teks_Pertanyaan])->all(),
        ];
    }

    public function selaraskanSnapshot(string $noTransaksi, int $idDetailPermit, int $idKategoriPermit, ?int $idTipeKendaraan): array
    {
        [, $dibuang, $ditambah] = $this->selisihSnapshot($noTransaksi, $idKategoriPermit, $idTipeKendaraan);

        if ($dibuang->isNotEmpty()) {
            DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')
                ->whereIn('Id_Permit_Checklist', $dibuang->pluck('Id_Permit_Checklist')->all())
                ->delete();
        }

        if ($ditambah->isNotEmpty()) {
            DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')->insert($ditambah->map(fn ($p) => [
                'Id_Detail_Permit' => $idDetailPermit,
                'No_Transaksi' => $noTransaksi,
                'Id_Master_Pertanyaan' => (int) $p->Id_Approval_Question,
                'Snapshot_Pertanyaan' => $p->Teks_Pertanyaan,
            ])->all());
        }

        return ['dibuang' => $dibuang->count(), 'ditambah' => $ditambah->count()];
    }
```

`buatSnapshot` kini memanggil `$this->pertanyaanBerlaku(...)->values()` sebagai ganti kueri inline (perilaku sama).

- [ ] **Step 4: Jalankan tes** (termasuk pemakai `buatSnapshot` yang sudah ada)

Run: `env -u DATABASE_URL php artisan test --filter='VisitorPermitChecklistSelarasTest|InspeksiPermitCoklatTest'`
Expected: PASS semua.

- [ ] **Step 5: Commit**

```bash
git add app/Services/VISITOR/Permit/VehiclePermit/VisitorPermitChecklistService.php tests/Integration/VISITOR/Permit/VehiclePermit/VisitorPermitChecklistSelarasTest.php
git commit -m "feat(visitor): selaraskan snapshot checklist permit tanpa membuang jawaban relevan"
```

---

### Task 4: `PermitKoreksiService` — aturan, transaksi, riwayat

**Files:**
- Create: `app/Services/VISITOR/Workflow/Verification/PermitKoreksiException.php`
- Create: `app/Services/VISITOR/Workflow/Verification/PermitKoreksiService.php`
- Test: `tests/Integration/VISITOR/Workflow/Verification/PermitKoreksiServiceTest.php`

**Interfaces:**
- Consumes: `KategoriPermitPengajuan::temukan(int)`, `VisitorPermitChecklistService::{pratinjauSelaras,selaraskanSnapshot}` (Task 2-3); `ApprovalRequestService::validateApproverAccess(string,string): array{valid:bool,reason:string}`; `VisitorPermitQueryService::basePermitQuery()` (kolom `Status_Permit`); `JadwalInspeksiService::aktif(string): ?object` (`Waktu_Mulai`, `Waktu_Selesai`); `VisitorEfektifService::terbitkanAksesInspeksi(string, CarbonInterface, CarbonInterface)`, `VisitorEfektifService::JENIS_INSPEKSI`; `PlatNomorKendaraan::normalkan(?string,?string,?string,?string): array{prefix,angka,suffix}`, `::muat(array,int): ?string`, `::LEBAR_PERMIT`.
- Produces:
  - `PermitKoreksiException extends \RuntimeException` dengan `public readonly string $kode`, `public readonly int $status`
  - `PermitKoreksiService::koreksi(string $noTransaksi, string $kodeKaryawan, array $input, string $alasan): array` — mengembalikan daftar `Nama_Field` yang berubah. `$input` kunci: `id_kategori_permit, no_plat, id_vehicle_model, id_vehicle_color, tahun_kendaraan, keterangan_pengajuan`
  - `PermitKoreksiService::bolehKoreksi(string $noTransaksi, string $kodeKaryawan): bool`
  - `PermitKoreksiService::pratinjauChecklist(string $noTransaksi, string $kodeKaryawan, int $idKategori, int $idModel): array` (bentuk = `pratinjauSelaras`)
  - `PermitKoreksiService::riwayat(string $noTransaksi): array` — `list<array{kelompok:string, waktu:string, oleh:string, nama:string, alasan:string, perubahan: list<array{field:string, lama:?string, baru:?string}>}>`, terbaru dulu
  - Konstanta `FIELD_KATEGORI='KATEGORI_PERMIT'`, `FIELD_PLAT='PLAT_NOMOR'`, `FIELD_MODEL='MODEL_KENDARAAN'`, `FIELD_WARNA='WARNA'`, `FIELD_TAHUN='TAHUN'`, `FIELD_KETERANGAN='KETERANGAN'`, `T_KOREKSI='N_EMI_VISITOR_Transaksi_Permit_Koreksi'`

- [ ] **Step 1: Tulis tes gagal** `tests/Integration/VISITOR/Workflow/Verification/PermitKoreksiServiceTest.php`

```php
<?php

namespace Tests\Integration\VISITOR\Workflow\Verification;

use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Permit\Inspection\JadwalInspeksiService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitChecklistService;
use App\Services\VISITOR\Workflow\Verification\PermitKoreksiException;
use App\Services\VISITOR\Workflow\Verification\PermitKoreksiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Koreksi data permit karyawan oleh verifikator saat review.
 * Master dev yang dipakai (diverifikasi 2026-09-23): model 3 = Toyota Avanza
 * (tipe 3 Mobil Penumpang), model 1 = tipe 2 Sepeda Motor, warna 1 = Hitam,
 * prefix plat 'BG' aktif, karyawan 'A1' ada.
 */
class PermitKoreksiServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const VERIFIKATOR = 'A1';

    private int $katA;
    private int $katB;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('N_EMI_VISITOR_Master_Approval_Question')->update(['Flag_Aktif' => 'T']);
        $this->katA = $this->kategori('Y');
        $this->katB = $this->kategori('Y');
    }

    public function test_koreksi_mengubah_detail_dan_mencatat_riwayat_per_field(): void
    {
        $no = $this->permitPending();

        $berubah = $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input([
            'id_kategori_permit' => $this->katB,
            'no_plat' => 'BG 9999 ZZ',
        ]), 'Salah pilih zona');

        $this->assertSame([PermitKoreksiService::FIELD_KATEGORI, PermitKoreksiService::FIELD_PLAT], $berubah);

        $d = DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->where('No_Transaksi', $no)->first();
        $this->assertSame($this->katB, (int) $d->Id_Master_Kategori_Permit);
        $this->assertSame($this->namaKategori($this->katB), $d->Snapshot_Kategori_Permit);
        $this->assertSame(['BG', '9999', 'ZZ'], [$d->Plat_Nomor_Prefix, $d->Plat_Nomor_Angka, $d->Plat_Nomor_Suffix]);

        $riwayat = DB::table(PermitKoreksiService::T_KOREKSI)->where('No_Transaksi', $no)->orderBy('Id_Permit_Koreksi')->get();
        $this->assertCount(2, $riwayat);
        $this->assertSame(1, $riwayat->pluck('Kelompok_Koreksi')->unique()->count());
        $this->assertSame('BG 1234 AA', $riwayat[1]->Nilai_Lama);
        $this->assertSame('BG 9999 ZZ', $riwayat[1]->Nilai_Baru);
        $this->assertSame('Salah pilih zona', $riwayat[0]->Alasan);
        $this->assertSame(self::VERIFIKATOR, $riwayat[0]->Created_By);

        $this->assertSame(self::VERIFIKATOR, DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $no)->value('Updated_By'));
    }

    public function test_tanpa_perubahan_ditolak(): void
    {
        $no = $this->permitPending();
        $this->assertDitolak('VISITOR_PERMIT_KOREKSI_TANPA_PERUBAHAN', 422, fn () => $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(), 'x'));
    }

    public function test_alasan_kosong_ditolak(): void
    {
        $no = $this->permitPending();
        $this->assertDitolak('VISITOR_PERMIT_KOREKSI_VALIDASI', 422, fn () => $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['tahun_kendaraan' => 2019]), '   '));
    }

    public function test_coklat_dan_kategori_nonaktif_ditolak(): void
    {
        $no = $this->permitPending();
        $coklat = (int) DB::table('N_EMI_VISITOR_Master_Kategori_Permit')->where('Nama_Kategori', 'COKLAT')->value('Id_Kategori_Permit');

        foreach ([$coklat, $this->kategori('Y', 'T'), $this->kategori('T')] as $kategori) {
            $this->assertDitolak('VISITOR_PERMIT_KOREKSI_VALIDASI', 422, fn () => $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['id_kategori_permit' => $kategori]), 'x'));
        }
    }

    public function test_prefix_plat_tidak_dikenal_ditolak(): void
    {
        $no = $this->permitPending();
        $this->assertDitolak('VISITOR_PERMIT_KOREKSI_VALIDASI', 422, fn () => $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['no_plat' => 'QQQ 1234 AA']), 'x'));
    }

    public function test_bukan_pemegang_giliran_ditolak(): void
    {
        $no = $this->permitPending(approver: 'ZZ_BUKAN_SIAPA');
        $this->assertDitolak('VISITOR_PERMIT_KOREKSI_AKSES_DITOLAK', 403, fn () => $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['tahun_kendaraan' => 2019]), 'x'));
        $this->assertFalse($this->svc()->bolehKoreksi($no, self::VERIFIKATOR));
    }

    public function test_permit_sudah_diputus_ditolak(): void
    {
        $no = $this->permitPending();
        DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $no)->update(['Flag_Approval' => 'T']);
        $this->assertDitolak('VISITOR_PERMIT_KOREKSI_STATUS_TIDAK_VALID', 409, fn () => $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['tahun_kendaraan' => 2019]), 'x'));
    }

    public function test_permit_tamu_ditolak(): void
    {
        $no = $this->permitPending();
        DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $no)->update(['Sumber_Pengajuan' => 'API']);
        $this->assertDitolak('VISITOR_PERMIT_KOREKSI_STATUS_TIDAK_VALID', 409, fn () => $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['tahun_kendaraan' => 2019]), 'x'));
    }

    public function test_approval_kedaluwarsa_ditolak(): void
    {
        $no = $this->permitPending();
        DB::table('HRIS_Approval_Request')->where('No_Transaksi', $no)->update(['Expired_At' => now()->subDay()]);
        $this->assertDitolak('VISITOR_PERMIT_KOREKSI_STATUS_TIDAK_VALID', 409, fn () => $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['tahun_kendaraan' => 2019]), 'x'));
    }

    public function test_permit_tidak_ada_ditolak_404(): void
    {
        $this->assertDitolak('VISITOR_PERMIT_KOREKSI_TIDAK_DITEMUKAN', 404, fn () => $this->svc()->koreksi('PMT-TIDAK-ADA', self::VERIFIKATOR, $this->input(), 'x'));
    }

    public function test_ganti_tipe_kendaraan_menyelaraskan_checklist_dan_menjaga_jawaban(): void
    {
        $umum = $this->pertanyaan('Umum', null, null);
        $mobil = $this->pertanyaan('Khusus mobil', null, 3);
        $motor = $this->pertanyaan('Khusus motor', null, 2);
        $no = $this->permitPending();

        $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['id_vehicle_model' => 1]), 'Ternyata motor');

        $baris = DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')->where('No_Transaksi', $no)->get()->keyBy(fn ($r) => (int) $r->Id_Master_Pertanyaan);
        $this->assertSame([$umum, $motor], $baris->keys()->sort()->values()->all());
        $this->assertSame('YA', $baris[$umum]->Jawaban_Admin);
        $this->assertArrayNotHasKey($mobil, $baris->all());
    }

    public function test_pratinjau_tidak_menulis(): void
    {
        $this->pertanyaan('Khusus mobil', null, 3);
        $no = $this->permitPending();

        $hasil = $this->svc()->pratinjauChecklist($no, self::VERIFIKATOR, $this->katA, 1);

        $this->assertCount(1, $hasil['dibuang']);
        $this->assertSame(1, DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')->where('No_Transaksi', $no)->count());
    }

    public function test_akses_inspeksi_aktif_ikut_disinkron(): void
    {
        $no = $this->permitPending();
        $this->pasangAksesInspeksi($no);

        $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['id_kategori_permit' => $this->katB, 'no_plat' => 'BG 9999 ZZ']), 'x');

        $pe = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $no)->first();
        $this->assertSame($this->namaKategori($this->katB), $pe->Nama_Kategori_Permit);
        $this->assertStringContainsString('9999', (string) $pe->No_Plat_Kendaraan);
        $this->assertSame('T', $pe->Flag_Sync);
    }

    public function test_akses_inspeksi_yang_sudah_berakhir_tidak_dihidupkan(): void
    {
        $no = $this->permitPending();
        $this->pasangAksesInspeksi($no);
        DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $no)->update(['Status_Efektif' => 'BERAKHIR']);

        $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['id_kategori_permit' => $this->katB]), 'x');

        $this->assertSame('BERAKHIR', DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $no)->value('Status_Efektif'));
    }

    public function test_keterangan_spasi_bukan_perubahan_dan_kosong_jadi_null(): void
    {
        $no = $this->permitPending();
        $this->assertDitolak('VISITOR_PERMIT_KOREKSI_TANPA_PERUBAHAN', 422, fn () => $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['keterangan_pengajuan' => '  Parkir harian  ']), 'x'));

        $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['keterangan_pengajuan' => '   ']), 'x');
        $this->assertNull(DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->where('No_Transaksi', $no)->value('Keterangan_Pengajuan'));
    }

    public function test_plat_dinormalkan_sebelum_dibandingkan(): void
    {
        $no = $this->permitPending();
        $this->assertDitolak('VISITOR_PERMIT_KOREKSI_TANPA_PERUBAHAN', 422, fn () => $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['no_plat' => ' bg  1234 aa ']), 'x'));
    }

    public function test_nilai_lama_riwayat_dibaca_setelah_kunci(): void
    {
        // Dua koreksi berurutan: nilai lama koreksi kedua adalah nilai baru koreksi pertama.
        $no = $this->permitPending();
        $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['tahun_kendaraan' => 2019]), 'pertama');
        $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['tahun_kendaraan' => 2018]), 'kedua');

        $tahun = DB::table(PermitKoreksiService::T_KOREKSI)->where('No_Transaksi', $no)->orderBy('Id_Permit_Koreksi')->get();
        $this->assertSame(['2020', '2019'], $tahun->pluck('Nilai_Lama')->all());
    }

    public function test_riwayat_dikelompokkan_terbaru_dulu(): void
    {
        $no = $this->permitPending();
        $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['tahun_kendaraan' => 2019, 'id_vehicle_color' => $this->warnaLain()]), 'pertama');
        $this->svc()->koreksi($no, self::VERIFIKATOR, $this->input(['tahun_kendaraan' => 2018, 'id_vehicle_color' => $this->warnaLain()]), 'kedua');

        $riwayat = $this->svc()->riwayat($no);

        $this->assertCount(2, $riwayat);
        $this->assertSame('kedua', $riwayat[0]['alasan']);
        $this->assertSame(self::VERIFIKATOR, $riwayat[0]['oleh']);
        $this->assertContains(['field' => PermitKoreksiService::FIELD_TAHUN, 'lama' => '2020', 'baru' => '2019'], $riwayat[1]['perubahan']);
    }

    // ── helper ─────────────────────────────────────────────────────────

    private function svc(): PermitKoreksiService
    {
        return app(PermitKoreksiService::class);
    }

    private function assertDitolak(string $kode, int $status, callable $aksi): void
    {
        try {
            $aksi();
            $this->fail("Diharapkan PermitKoreksiException {$kode}");
        } catch (PermitKoreksiException $e) {
            $this->assertSame($kode, $e->kode, $e->getMessage());
            $this->assertSame($status, $e->status);
        }
    }

    /** Isian lengkap = data permit saat ini; timpa hanya field yang diuji. */
    private function input(array $timpa = []): array
    {
        return array_merge([
            'id_kategori_permit' => $this->katA,
            'no_plat' => 'BG 1234 AA',
            'id_vehicle_model' => 3,
            'id_vehicle_color' => 1,
            'tahun_kendaraan' => 2020,
            'keterangan_pengajuan' => 'Parkir harian',
        ], $timpa);
    }

    private function kategori(string $muncul, string $aktif = 'Y'): int
    {
        return (int) DB::table('N_EMI_VISITOR_Master_Kategori_Permit')->insertGetId([
            'Nama_Kategori' => 'UJI '.Str::upper(Str::random(8)),
            'Flag_Aktif' => $aktif,
            'Flag_Muncul_Pengajuan' => $muncul,
        ]);
    }

    private function namaKategori(int $id): string
    {
        return (string) DB::table('N_EMI_VISITOR_Master_Kategori_Permit')->where('Id_Kategori_Permit', $id)->value('Nama_Kategori');
    }

    private function warnaLain(): int
    {
        return (int) DB::table('N_EMI_VISITOR_Master_Vehicle_Color')->where('Flag_Aktif', 'Y')->where('Id_Master_Color', '<>', 1)->inRandomOrder()->value('Id_Master_Color');
    }

    private function pertanyaan(string $teks, ?int $kategori, ?int $tipe): int
    {
        return (int) DB::table('N_EMI_VISITOR_Master_Approval_Question')->insertGetId([
            'Teks_Pertanyaan' => $teks,
            'Target_Id_Kategori_Permit' => $kategori,
            'Target_Id_Tipe_Kendaraan' => $tipe,
            'Flag_Aktif' => 'Y',
        ]);
    }

    /** Permit HCIS PENDING milik 'A1', kategori A, Avanza hitam 2020, checklist terjawab 'YA'. */
    private function permitPending(string $approver = self::VERIFIKATOR): string
    {
        $no = 'PMT-KRK-'.Str::upper(Str::random(8));
        $idHeader = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $no, 'Kode_Karyawan_Pemohon' => 'A1', 'Sumber_Pengajuan' => 'HCIS',
            'Flag_Aktif' => 'Y', 'Flag_Approval' => null, 'Created_At' => now(), 'Created_By' => 'A1',
            'Idempotency_Key' => 'KRK-'.Str::upper(Str::random(20)),
        ]);
        $idDetail = (int) DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insertGetId([
            'Id_Transaksi_Permit' => $idHeader, 'No_Transaksi' => $no,
            'Plat_Nomor_Prefix' => 'BG', 'Plat_Nomor_Angka' => '1234', 'Plat_Nomor_Suffix' => 'AA',
            'Id_Master_Model' => 3, 'Id_Master_Warna' => 1, 'Tahun_Kendaraan' => 2020,
            'Id_Master_Kategori_Permit' => $this->katA, 'Snapshot_Kategori_Permit' => $this->namaKategori($this->katA),
            'Keterangan_Pengajuan' => 'Parkir harian',
        ]);
        app(VisitorPermitChecklistService::class)->buatSnapshot($no, $idDetail, $this->katA, 3);
        DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')->where('No_Transaksi', $no)->update(['Jawaban_Admin' => 'YA']);
        DB::table('HRIS_Approval_Request')->insert([
            'No_Transaksi' => $no, 'Kode_Karyawan' => 'A1', 'Request_Order_Flow' => 1,
            'Approver_Karyawan_Kode' => $approver, 'Flag_Approval' => null,
            'Expired_At' => now()->addDays(3), 'created_at' => now(), 'update_at' => now(),
        ]);

        return $no;
    }

    private function pasangAksesInspeksi(string $no): void
    {
        $mulai = now()->addDay()->setTime(10, 0);
        DB::table(JadwalInspeksiService::T_BOOKING)->insert([
            'No_Transaksi_Permit' => $no,
            'Id_Pos_Inspeksi' => DB::table(JadwalInspeksiService::T_POS)->where('Flag_Aktif', 'Y')->value('Id_Pos_Inspeksi'),
            'Snapshot_Nama_Pos' => 'Pos Uji Koreksi',
            'Waktu_Mulai' => $mulai, 'Waktu_Selesai' => $mulai->copy()->addMinutes(30),
            'Status' => JadwalInspeksiService::TERJADWAL, 'Created_At' => now(), 'Created_By' => 'UJI',
        ]);
        app(VisitorEfektifService::class)->terbitkanAksesInspeksi($no, $mulai, $mulai->copy()->addMinutes(30));
    }
}
```

Catatan: tes `test_ganti_tipe…` dan `test_pratinjau…` membuat pertanyaan SETELAH `setUp` menonaktifkan master, dan `permitPending()` dipanggil sesudahnya supaya snapshot awal memuatnya. Bila `T_POS` tidak punya pos aktif di dev, tes akses inspeksi gagal di `insert` — laporkan, jangan menebak id.

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `env -u DATABASE_URL php artisan test --filter=PermitKoreksiServiceTest`
Expected: FAIL — `Class "App\Services\VISITOR\Workflow\Verification\PermitKoreksiService" not found`

- [ ] **Step 3: Buat `PermitKoreksiException.php`**

```php
<?php

namespace App\Services\VISITOR\Workflow\Verification;

/** Penolakan koreksi permit yang membawa kode galat dan status HTTP-nya sendiri. */
class PermitKoreksiException extends \RuntimeException
{
    public function __construct(string $pesan, public readonly string $kode, public readonly int $status)
    {
        parent::__construct($pesan);
    }

    public static function validasi(string $pesan): self
    {
        return new self($pesan, 'VISITOR_PERMIT_KOREKSI_VALIDASI', 422);
    }
}
```

- [ ] **Step 4: Buat `PermitKoreksiService.php`**

```php
<?php

namespace App\Services\VISITOR\Workflow\Verification;

use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalRequestService;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Permit\Inspection\JadwalInspeksiService;
use App\Services\VISITOR\Permit\VehiclePermit\KategoriPermitPengajuan;
use App\Services\VISITOR\Permit\VehiclePermit\PlatNomorKendaraan;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitChecklistService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Koreksi data permit karyawan oleh verifikator yang memegang giliran
 * approval, selama permit masih PENDING. Persetujuan tahap sebelumnya tidak
 * di-reset; setiap field yang berubah dicatat satu baris di riwayat.
 */
class PermitKoreksiService
{
    public const T_KOREKSI = 'N_EMI_VISITOR_Transaksi_Permit_Koreksi';

    public const FIELD_KATEGORI = 'KATEGORI_PERMIT';
    public const FIELD_PLAT = 'PLAT_NOMOR';
    public const FIELD_MODEL = 'MODEL_KENDARAAN';
    public const FIELD_WARNA = 'WARNA';
    public const FIELD_TAHUN = 'TAHUN';
    public const FIELD_KETERANGAN = 'KETERANGAN';

    public function __construct(
        private ApprovalRequestService $approvalRequest,
        private VisitorPermitQueryService $permitQuery,
        private VisitorPermitChecklistService $checklist,
        private JadwalInspeksiService $jadwal,
        private VisitorEfektifService $efektif,
        private PlatNomorKendaraan $platNomor,
    ) {}

    public function bolehKoreksi(string $noTransaksi, string $kodeKaryawan): bool
    {
        try {
            $this->pastikanBolehDikoreksi($noTransaksi, $kodeKaryawan, kunci: false);

            return true;
        } catch (PermitKoreksiException) {
            return false;
        }
    }

    public function pratinjauChecklist(string $noTransaksi, string $kodeKaryawan, int $idKategori, int $idModel): array
    {
        $this->pastikanBolehDikoreksi($noTransaksi, $kodeKaryawan, kunci: false);
        $model = $this->modelBerlaku($idModel);

        return $this->checklist->pratinjauSelaras($noTransaksi, $idKategori, (int) $model->Id_Master_Tipe);
    }

    /** @return list<string> Nama_Field yang berubah */
    public function koreksi(string $noTransaksi, string $kodeKaryawan, array $input, string $alasan): array
    {
        $alasan = trim($alasan);
        if ($alasan === '') {
            throw PermitKoreksiException::validasi('Alasan koreksi wajib diisi.');
        }

        return DB::transaction(function () use ($noTransaksi, $kodeKaryawan, $input, $alasan) {
            $this->pastikanBolehDikoreksi($noTransaksi, $kodeKaryawan, kunci: true);

            $detail = DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->where('No_Transaksi', $noTransaksi)->first();
            $modelLama = $detail->Id_Master_Model ? $this->namaModel((int) $detail->Id_Master_Model) : null;

            $kategori = KategoriPermitPengajuan::temukan((int) ($input['id_kategori_permit'] ?? 0))
                ?? throw PermitKoreksiException::validasi('Jenis permit tujuan tidak tersedia untuk pengajuan karyawan.');
            $plat = $this->platBerlaku((string) ($input['no_plat'] ?? ''));
            $model = $this->modelBerlaku((int) ($input['id_vehicle_model'] ?? 0));
            $warna = DB::table('N_EMI_VISITOR_Master_Vehicle_Color')
                ->where('Id_Master_Color', (int) ($input['id_vehicle_color'] ?? 0))->where('Flag_Aktif', 'Y')->first()
                ?? throw PermitKoreksiException::validasi('Warna kendaraan tidak valid atau sudah nonaktif.');
            $tahun = (int) ($input['tahun_kendaraan'] ?? 0);
            if ($tahun < 1900 || $tahun > (int) date('Y') + 1) {
                throw PermitKoreksiException::validasi('Tahun kendaraan tidak valid.');
            }
            $keterangan = trim((string) ($input['keterangan_pengajuan'] ?? ''));
            $keterangan = $keterangan === '' ? null : $keterangan;

            $platLama = [(string) $detail->Plat_Nomor_Prefix, (string) $detail->Plat_Nomor_Angka, (string) $detail->Plat_Nomor_Suffix];
            $platBaru = [$plat['prefix'], $plat['angka'], $plat['suffix']];
            $ketLama = trim((string) $detail->Keterangan_Pengajuan);
            $ketLama = $ketLama === '' ? null : $ketLama;

            $perubahan = [];
            $kolom = [];

            if ((int) $detail->Id_Master_Kategori_Permit !== (int) $kategori->Id_Kategori_Permit) {
                $perubahan[] = [self::FIELD_KATEGORI, $detail->Snapshot_Kategori_Permit, $kategori->Nama_Kategori];
                $kolom['Id_Master_Kategori_Permit'] = (int) $kategori->Id_Kategori_Permit;
                $kolom['Snapshot_Kategori_Permit'] = $kategori->Nama_Kategori;
            }
            if ($platLama !== $platBaru) {
                $perubahan[] = [self::FIELD_PLAT, implode(' ', array_filter($platLama)), implode(' ', $platBaru)];
                $kolom['Plat_Nomor_Prefix'] = $plat['prefix'];
                $kolom['Plat_Nomor_Angka'] = $plat['angka'];
                $kolom['Plat_Nomor_Suffix'] = $plat['suffix'];
            }
            if ((int) $detail->Id_Master_Model !== (int) $model->Id_Vehicle_Model) {
                $perubahan[] = [self::FIELD_MODEL, $modelLama?->label, $model->label];
                $kolom['Id_Master_Model'] = (int) $model->Id_Vehicle_Model;
            }
            if ((int) $detail->Id_Master_Warna !== (int) $warna->Id_Master_Color) {
                $namaWarnaLama = $detail->Id_Master_Warna
                    ? DB::table('N_EMI_VISITOR_Master_Vehicle_Color')->where('Id_Master_Color', $detail->Id_Master_Warna)->value('Nama_Warna')
                    : null;
                $perubahan[] = [self::FIELD_WARNA, $namaWarnaLama, $warna->Nama_Warna];
                $kolom['Id_Master_Warna'] = (int) $warna->Id_Master_Color;
            }
            if ((int) $detail->Tahun_Kendaraan !== $tahun) {
                $perubahan[] = [self::FIELD_TAHUN, $detail->Tahun_Kendaraan !== null ? (string) (int) $detail->Tahun_Kendaraan : null, (string) $tahun];
                $kolom['Tahun_Kendaraan'] = $tahun;
            }
            if ($ketLama !== $keterangan) {
                $perubahan[] = [self::FIELD_KETERANGAN, $ketLama, $keterangan];
                $kolom['Keterangan_Pengajuan'] = $keterangan;
            }

            if ($perubahan === []) {
                throw new PermitKoreksiException('Tidak ada data yang berubah.', 'VISITOR_PERMIT_KOREKSI_TANPA_PERUBAHAN', 422);
            }

            DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->where('No_Transaksi', $noTransaksi)->update($kolom);

            $tipeLama = $modelLama ? (int) $modelLama->Id_Master_Tipe : null;
            if (isset($kolom['Id_Master_Kategori_Permit']) || $tipeLama !== (int) $model->Id_Master_Tipe) {
                $this->checklist->selaraskanSnapshot(
                    $noTransaksi,
                    (int) $detail->Id_Permit_Detail,
                    (int) $kategori->Id_Kategori_Permit,
                    (int) $model->Id_Master_Tipe,
                );
            }

            $this->sinkronAksesInspeksi($noTransaksi);

            $kelompok = (string) Str::uuid();
            $sekarang = now();
            DB::table(self::T_KOREKSI)->insert(array_map(fn (array $p) => [
                'No_Transaksi' => $noTransaksi,
                'Kelompok_Koreksi' => $kelompok,
                'Nama_Field' => $p[0],
                'Nilai_Lama' => $p[1],
                'Nilai_Baru' => $p[2],
                'Alasan' => $alasan,
                'Created_At' => $sekarang,
                'Created_By' => $kodeKaryawan,
            ], $perubahan));

            DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $noTransaksi)
                ->update(['Updated_At' => $sekarang, 'Updated_By' => $kodeKaryawan]);

            return array_column($perubahan, 0);
        });
    }

    public function riwayat(string $noTransaksi): array
    {
        return DB::table(self::T_KOREKSI.' as r')
            ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 'r.Created_By')
            ->where('r.No_Transaksi', $noTransaksi)
            // Id identity, bukan Created_At: dua koreksi di detik yang sama tetap berurutan benar.
            ->orderByDesc('r.Id_Permit_Koreksi')
            ->get(['r.Id_Permit_Koreksi', 'r.Kelompok_Koreksi', 'r.Nama_Field', 'r.Nilai_Lama', 'r.Nilai_Baru', 'r.Alasan', 'r.Created_At', 'r.Created_By', 'k.Nama'])
            ->groupBy('Kelompok_Koreksi')
            ->map(fn ($baris) => [
                'kelompok' => (string) $baris[0]->Kelompok_Koreksi,
                'waktu' => (string) $baris[0]->Created_At,
                'oleh' => (string) $baris[0]->Created_By,
                'nama' => (string) ($baris[0]->Nama ?: $baris[0]->Created_By),
                'alasan' => (string) $baris[0]->Alasan,
                'perubahan' => $baris->sortBy('Id_Permit_Koreksi')->map(fn ($r) => [
                    'field' => (string) $r->Nama_Field,
                    'lama' => $r->Nilai_Lama,
                    'baru' => $r->Nilai_Baru,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    private function pastikanBolehDikoreksi(string $noTransaksi, string $kodeKaryawan, bool $kunci): void
    {
        $header = DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->where('No_Transaksi', $noTransaksi)
            ->where('Flag_Aktif', 'Y')
            ->when($kunci, fn ($q) => $q->lockForUpdate())
            ->first();

        if (! $header) {
            throw new PermitKoreksiException('Data permit tidak ditemukan.', 'VISITOR_PERMIT_KOREKSI_TIDAK_DITEMUKAN', 404);
        }

        $sumber = strtoupper((string) ($header->Sumber_Pengajuan ?? 'HCIS'));
        if ($sumber !== 'HCIS' || ($header->No_Transaksi_Tamu ?? null) !== null) {
            throw new PermitKoreksiException('Hanya permit pengajuan karyawan yang bisa dikoreksi.', 'VISITOR_PERMIT_KOREKSI_STATUS_TIDAK_VALID', 409);
        }

        $status = $this->permitQuery->basePermitQuery()->where('h.No_Transaksi', $noTransaksi)->value(DB::raw($this->permitQuery->permitStatusSql()));
        if ($status !== 'PENDING') {
            throw new PermitKoreksiException('Permit sudah tidak menunggu review, datanya tidak bisa dikoreksi.', 'VISITOR_PERMIT_KOREKSI_STATUS_TIDAK_VALID', 409);
        }

        $akses = $this->approvalRequest->validateApproverAccess($noTransaksi, $kodeKaryawan);
        if (! $akses['valid']) {
            throw new PermitKoreksiException($akses['reason'], 'VISITOR_PERMIT_KOREKSI_AKSES_DITOLAK', 403);
        }
    }

    private function platBerlaku(string $noPlat): array
    {
        $plat = $this->platNomor->normalkan($noPlat, null, null, null);
        if (! $plat['prefix'] || ! $plat['angka'] || ! $plat['suffix']) {
            throw PermitKoreksiException::validasi('Nomor plat kendaraan wajib diisi lengkap.');
        }
        if ($bagian = $this->platNomor->muat($plat, PlatNomorKendaraan::LEBAR_PERMIT)) {
            throw PermitKoreksiException::validasi("Bagian plat nomor '{$bagian}' melebihi ".PlatNomorKendaraan::LEBAR_PERMIT.' karakter.');
        }
        $prefixAda = DB::table('N_EMI_VISITOR_Master_Plat_Kendaraan')->where('Kode_Plat', $plat['prefix'])->where('Flag_Aktif', 'Y')->exists();
        if (! $prefixAda) {
            throw PermitKoreksiException::validasi("Kode wilayah plat '{$plat['prefix']}' tidak dikenal.");
        }

        return $plat;
    }

    /** Model aktif yang tipenya boleh untuk permit; ->label = "Nama Model (Nama Tipe)". */
    private function modelBerlaku(int $idModel): object
    {
        $model = $this->kueriModel($idModel)
            ->where('vm.Flag_Aktif', 'Y')
            ->where('vt.Flag_Aktif', 'Y')
            ->where(fn ($q) => $q->whereNull('vt.Akses')->orWhereIn('vt.Akses', ['PERMIT', 'ALL']))
            ->first();

        if (! $model) {
            throw PermitKoreksiException::validasi('Model kendaraan tidak valid atau sudah nonaktif.');
        }

        return $model;
    }

    private function namaModel(int $idModel): ?object
    {
        return $this->kueriModel($idModel)->first();
    }

    private function kueriModel(int $idModel): \Illuminate\Database\Query\Builder
    {
        return DB::table('N_EMI_VISITOR_Master_Vehicle_Model as vm')
            ->leftJoin('N_EMI_VISITOR_Master_Vehicle_Type as vt', 'vm.Id_Master_Tipe', '=', 'vt.Id_Vehicle_Type')
            ->where('vm.Id_Vehicle_Model', $idModel)
            ->select(
                'vm.Id_Vehicle_Model',
                'vm.Id_Master_Tipe',
                DB::raw("CONCAT(vm.Nama_Model_Kendaraan, ' (', ISNULL(vt.Nama_Tipe_Kendaraan, '-'), ')') as label"),
            );
    }

    /** Baris palang INSPEKSI yang masih AKTIF ikut membawa data terbaru; yang sudah berakhir dibiarkan. */
    private function sinkronAksesInspeksi(string $noTransaksi): void
    {
        $booking = $this->jadwal->aktif($noTransaksi);
        $aksesAktif = DB::table('N_VISITOR_Permit_Efektif')
            ->where('No_Transaksi', $noTransaksi)
            ->where('Jenis_Akses', VisitorEfektifService::JENIS_INSPEKSI)
            ->where('Status_Efektif', 'AKTIF')
            ->exists();

        if ($booking && $aksesAktif) {
            $this->efektif->terbitkanAksesInspeksi(
                $noTransaksi,
                Carbon::parse($booking->Waktu_Mulai),
                Carbon::parse($booking->Waktu_Selesai),
            );
        }
    }
}
```

Sebelum menulis, periksa tanda tangan `PlatNomorKendaraan::normalkan` di `app/Services/VISITOR/Permit/VehiclePermit/PlatNomorKendaraan.php:67` — bila parameternya tidak nullable, sesuaikan pemanggilan (mis. string kosong) mengikuti `VehiclePermitController::resolvePlatNomor`.

- [ ] **Step 5: Jalankan tes**

Run: `env -u DATABASE_URL php artisan test --filter=PermitKoreksiServiceTest`
Expected: PASS (18 tes). Bila `test_akses_inspeksi_aktif_ikut_disinkron` gagal karena `No_Plat_Kendaraan` diformat berbeda, sesuaikan assert ke format `platNomorSql` — jangan ubah service.

- [ ] **Step 6: Commit**

```bash
git add app/Services/VISITOR/Workflow/Verification tests/Integration/VISITOR/Workflow/Verification/PermitKoreksiServiceTest.php
git commit -m "feat(visitor): service koreksi data permit oleh verifikator"
```

---

### Task 5: Endpoint verifikator

**Files:**
- Create: `app/Http/Requests/VISITOR/Workflow/Verification/PermitKoreksiRequest.php`
- Modify: `app/Http/Controllers/VISITOR/Workflow/Verification/VerifikatorVisitorController.php` (constructor :34; `showPermit` :284-318; method baru setelah `updatePermitChecklist`)
- Modify: `routes/VISITOR/Workflow/Verification/VerifikatorVisitorWeb.php` (grup :40-52)
- Test: `tests/Feature/VISITOR/VisitorRouteContractTest.php`

**Interfaces:**
- Consumes: `PermitKoreksiService::{koreksi,bolehKoreksi,pratinjauChecklist,riwayat}`, `PermitKoreksiException`, `VisitorPermitMasterService::untukPengajuan()`
- Produces (HTTP, format `ResponseHelper`: `{success, message, result}`):
  - `POST /verifikator-permit/{noTransaksi}/koreksi` body `{id_kategori_permit, no_plat, id_vehicle_model, id_vehicle_color, tahun_kendaraan, keterangan_pengajuan?, alasan}` → `result: {berubah: string[]}`
  - `GET /verifikator-permit/{noTransaksi}/koreksi/pratinjau-checklist?id_kategori_permit=&id_vehicle_model=` → `result` = bentuk `pratinjauSelaras`
  - `GET /verifikator-permit-koreksi-master` → `result` = `untukPengajuan()`
  - `GET /verifikator-permit/{id}` kini juga `result.boleh_koreksi: bool`, `result.riwayat_koreksi: array`

- [ ] **Step 1: Tulis tes kontrak route yang gagal** — tambahkan ke `VisitorRouteContractTest`:

```php
    public function test_route_koreksi_permit_terdaftar_di_controller_verifikator(): void
    {
        $router = app('router')->getRoutes();
        $c = 'App\Http\Controllers\VISITOR\Workflow\Verification\VerifikatorVisitorController';

        $this->assertSame("{$c}@koreksiPermit", $router->match(Request::create('/verifikator-permit/PMT-1/koreksi', 'POST'))->getActionName());
        $this->assertSame("{$c}@pratinjauKoreksiChecklist", $router->match(Request::create('/verifikator-permit/PMT-1/koreksi/pratinjau-checklist', 'GET'))->getActionName());
        $this->assertSame("{$c}@masterKoreksiPermit", $router->match(Request::create('/verifikator-permit-koreksi-master', 'GET'))->getActionName());

        $koreksi = $router->match(Request::create('/verifikator-permit/PMT-1/koreksi', 'POST'));
        $this->assertContains('check.jabatan:visitorVerifikatorPermitPage', $koreksi->middleware());
    }
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `env -u DATABASE_URL php artisan test --filter=test_route_koreksi_permit_terdaftar_di_controller_verifikator`
Expected: FAIL (route tidak cocok / action berbeda).

- [ ] **Step 3: Buat `PermitKoreksiRequest.php`**

```php
<?php

namespace App\Http\Requests\VISITOR\Workflow\Verification;

use Illuminate\Foundation\Http\FormRequest;

class PermitKoreksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_kategori_permit' => ['required', 'integer'],
            'no_plat' => ['required', 'string', 'max:30'],
            'id_vehicle_model' => ['required', 'integer'],
            'id_vehicle_color' => ['required', 'integer'],
            'tahun_kendaraan' => ['required', 'integer', 'min:1900', 'max:'.((int) date('Y') + 1)],
            'keterangan_pengajuan' => ['nullable', 'string', 'max:500'],
            'alasan' => ['required', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['alasan' => 'alasan koreksi', 'no_plat' => 'nomor plat', 'id_kategori_permit' => 'jenis permit'];
    }
}
```

- [ ] **Step 4: Controller** — suntik `PermitKoreksiService $permitKoreksi` di constructor (ikuti gaya properti promosi yang ada), lalu:

Di `showPermit`, setelah `'is_final_approval' => …`:

```php
                'boleh_koreksi' => $this->permitKoreksi->bolehKoreksi(
                    $header->No_Transaksi,
                    $this->visitorApprovalPolicy->requesterKode(request()->user()) ?? '',
                ),
                'riwayat_koreksi' => $this->permitKoreksi->riwayat($header->No_Transaksi),
```

Method baru setelah `updatePermitChecklist`:

```php
    public function koreksiPermit(PermitKoreksiRequest $request, string $noTransaksi)
    {
        try {
            $berubah = $this->permitKoreksi->koreksi(
                $noTransaksi,
                $this->visitorApprovalPolicy->requesterKode($request->user()) ?? '',
                $request->safe()->except('alasan'),
                (string) $request->validated('alasan'),
            );

            return ResponseHelper::success(['berubah' => $berubah], 'Data permit dikoreksi.');
        } catch (PermitKoreksiException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), $e->kode, $e->status);
        } catch (\Throwable $e) {
            return VisitorApiFailure::respond($e, 'verification_permit_koreksi', 'VISITOR_PERMIT_KOREKSI_FAILED', 'Koreksi data permit gagal disimpan.');
        }
    }

    public function pratinjauKoreksiChecklist(Request $request, string $noTransaksi)
    {
        $request->validate([
            'id_kategori_permit' => 'required|integer',
            'id_vehicle_model' => 'required|integer',
        ]);

        try {
            return ResponseHelper::success($this->permitKoreksi->pratinjauChecklist(
                $noTransaksi,
                $this->visitorApprovalPolicy->requesterKode($request->user()) ?? '',
                (int) $request->query('id_kategori_permit'),
                (int) $request->query('id_vehicle_model'),
            ));
        } catch (PermitKoreksiException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), $e->kode, $e->status);
        } catch (\Throwable $e) {
            return VisitorApiFailure::respond($e, 'verification_permit_koreksi_pratinjau', 'VISITOR_PERMIT_KOREKSI_PRATINJAU_FAILED', 'Pratinjau checklist gagal dimuat.');
        }
    }

    public function masterKoreksiPermit()
    {
        try {
            return ResponseHelper::success(app(VisitorPermitMasterService::class)->untukPengajuan());
        } catch (\Throwable $e) {
            return VisitorApiFailure::respond($e, 'verification_permit_koreksi_master', 'VISITOR_PERMIT_KOREKSI_MASTER_FAILED', 'Master data koreksi gagal dimuat.');
        }
    }
```

Tambah `use` untuk `PermitKoreksiRequest`, `PermitKoreksiException`, `PermitKoreksiService`, `VisitorPermitMasterService`.

- [ ] **Step 5: Routes** — di grup `['auth', 'check.jabatan:visitorVerifikatorPermitPage']`, setelah route checklist:

```php
    Route::post('verifikator-permit/{noTransaksi}/koreksi', [VerifikatorVisitorController::class, 'koreksiPermit']);
    Route::get('verifikator-permit/{noTransaksi}/koreksi/pratinjau-checklist', [VerifikatorVisitorController::class, 'pratinjauKoreksiChecklist']);
    Route::get('verifikator-permit-koreksi-master', [VerifikatorVisitorController::class, 'masterKoreksiPermit']);
```

Pastikan `verifikator-permit/{id}` (GET showPermit) tidak menelan `verifikator-permit-koreksi-master` — URI-nya berbeda segmen pertama, jadi aman; route contract test di Step 1 membuktikannya.

- [ ] **Step 6: Jalankan tes + snapshot route**

Run: `env -u DATABASE_URL php artisan test --filter=VisitorRouteContractTest && php scripts/route-snapshot.php | grep -c koreksi`
Expected: PASS; hitungan `3`.

- [ ] **Step 7: Uji endpoint nyata di dev** (tanpa login browser): jalankan `php artisan tinker` untuk memanggil `app(VerifikatorVisitorController::class)->showPermit('<No_Transaksi PENDING dev>')` dan pastikan `result` memuat `boleh_koreksi` dan `riwayat_koreksi`. Laporkan nomor yang dipakai.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/VISITOR/Workflow/Verification/PermitKoreksiRequest.php app/Http/Controllers/VISITOR/Workflow/Verification/VerifikatorVisitorController.php routes/VISITOR/Workflow/Verification/VerifikatorVisitorWeb.php tests/Feature/VISITOR/VisitorRouteContractTest.php
git commit -m "feat(visitor): endpoint koreksi permit untuk verifikator"
```

---

### Task 6: Helper master kendaraan + `PermitKoreksiDialog.vue`

**Files:**
- Create: `resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/masterKendaraan.js`
- Create: `…/components/masterKendaraan.test.js`
- Modify: `…/components/PermitReviewModal.vue` (`pohonModelKendaraan` :1427-1452, `daftarTahunKendaraan` :1280-1288 memakai helper)
- Create: `…/components/PermitKoreksiDialog.vue`
- Create: `…/components/PermitKoreksiDialog.test.js`

**Interfaces:**
- Produces:
  - `pohonModel(models: Array, vehicleTypes: Array): Array<{value,label,selectable:false,children:Array<{value,label}>}>`
  - `daftarTahun(maks: number = new Date().getFullYear() + 1, min: number = 1970): number[]`
  - `PermitKoreksiDialog` props: `modelValue: Boolean`, `permit: Object` (baris `permit` dari `showPermit`: `No_Transaksi, Id_Master_Kategori_Permit?, Nama_Kategori, No_Plat_Kendaraan, Id_Master_Model, Id_Master_Warna, Tahun_Kendaraan, Keterangan_Pengajuan`); emits `update:modelValue`, `tersimpan` (payload `{ berubah: string[] }`)
  - Method yang diuji: `perubahan` (computed, `string[]` label field yang berubah), `bisaSimpan` (computed), `simpan()`, `muatPratinjau()`

- [ ] **Step 1: Pastikan field `permit` yang dibutuhkan ada.** Run: `grep -n "Id_Master_Kategori_Permit\|No_Plat_Kendaraan\|Nama_Kategori" app/Services/VISITOR/Permit/VehiclePermit/VisitorPermitQueryService.php | head`. Bila `d.Id_Master_Kategori_Permit` belum dipilih di `basePermitQuery`, tambahkan `'d.Id_Master_Kategori_Permit',` di samping `'d.Id_Master_Model',` (commit bersama task ini).

- [ ] **Step 2: Tulis tes helper yang gagal** `masterKendaraan.test.js`

```js
import { describe, expect, it } from 'vitest';
import { daftarTahun, pohonModel } from './masterKendaraan';

describe('masterKendaraan', () => {
    it('mengelompokkan model per jenis kendaraan, urut nama', () => {
        const pohon = pohonModel(
            [
                { Id_Vehicle_Model: 3, Id_Master_Tipe: 3, Nama_Model_Kendaraan: 'Toyota Avanza' },
                { Id_Vehicle_Model: 1, Id_Master_Tipe: 2, Nama_Model_Kendaraan: 'Honda Beat' },
                { Id_Vehicle_Model: 4, Id_Master_Tipe: 3, Nama_Model_Kendaraan: 'Daihatsu Xenia' },
            ],
            [
                { Id_Vehicle_Type: 2, Nama_Tipe_Kendaraan: 'Sepeda Motor' },
                { Id_Vehicle_Type: 3, Nama_Tipe_Kendaraan: 'Mobil Penumpang' },
            ],
        );

        const mobil = pohon.find((p) => p.value === 'jenis-3');
        expect(mobil.label).toBe('Mobil Penumpang');
        expect(mobil.selectable).toBe(false);
        expect(mobil.children.map((c) => c.label)).toEqual(['Daihatsu Xenia', 'Toyota Avanza']);
        expect(mobil.children[1].value).toBe('3');
    });

    it('daftar tahun menurun dari maks ke min', () => {
        expect(daftarTahun(2027, 2024)).toEqual([2027, 2026, 2025, 2024]);
    });
});
```

- [ ] **Step 3: Jalankan, pastikan gagal** — Run: `npx vitest run resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/masterKendaraan.test.js` → FAIL (modul tidak ada).

- [ ] **Step 4: Buat `masterKendaraan.js`** (isi dipindah dari modal, perilaku sama)

```js
/**
 * Pohon model kendaraan per jenis — daftar model lebih dari seratus baris,
 * dan dropdown datar memaksa verifikator menggulir campuran motor/mobil/truk.
 * Dipakai modal review dan dialog koreksi supaya keduanya identik.
 */
export function pohonModel(models = [], vehicleTypes = []) {
    const namaJenis = new Map(
        vehicleTypes.map((t) => [String(t.Id_Vehicle_Type), t.Nama_Tipe_Kendaraan || t.Nama_Vehicle_Type]),
    );
    const kelompok = new Map();

    models.forEach((m) => {
        const kunci = String(m.Id_Master_Tipe ?? 'lainnya');
        if (!kelompok.has(kunci)) kelompok.set(kunci, []);
        kelompok.get(kunci).push({ value: String(m.Id_Vehicle_Model), label: m.Nama_Model_Kendaraan });
    });

    return [...kelompok.entries()].map(([kunci, anak]) => ({
        value: `jenis-${kunci}`,
        label: namaJenis.get(kunci) || 'Lainnya',
        selectable: false,
        children: anak.sort((a, b) => a.label.localeCompare(b.label)),
    }));
}

export function daftarTahun(maks = new Date().getFullYear() + 1, min = 1970) {
    const list = [];
    for (let y = maks; y >= min; y--) list.push(y);
    return list;
}
```

Di `PermitReviewModal.vue`: `import { daftarTahun, pohonModel } from './masterKendaraan';`; `daftarTahunKendaraan()` → `return daftarTahun(this.tahunMaksimum);`; `pohonModelKendaraan()` → `return pohonModel(this.masterModels || [], this.masterVehicleTypes || []);` (komentar lama di atasnya dipertahankan).

- [ ] **Step 5: Jalankan tes helper + tes modal yang ada** — Run: `npx vitest run resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/` → PASS (bila ada kegagalan, buktikan pre-existing dengan `git checkout HEAD~1 -- <berkas>` → jalankan → `git checkout HEAD -- <berkas>`; `git stash` dilarang AGENTS.md).

- [ ] **Step 6: Tulis tes dialog yang gagal** `PermitKoreksiDialog.test.js`

```js
import { flushPromises, shallowMount } from '@vue/test-utils';
import axios from 'axios';
import { afterEach, describe, expect, it, vi } from 'vitest';
import PermitKoreksiDialog from './PermitKoreksiDialog.vue';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));

const MASTER = {
    plat_codes: ['BG', 'B'],
    vehicle_types: [{ Id_Vehicle_Type: 2, Nama_Tipe_Kendaraan: 'Sepeda Motor' }, { Id_Vehicle_Type: 3, Nama_Tipe_Kendaraan: 'Mobil Penumpang' }],
    models: [{ Id_Vehicle_Model: 3, Id_Master_Tipe: 3, Nama_Model_Kendaraan: 'Toyota Avanza' }, { Id_Vehicle_Model: 1, Id_Master_Tipe: 2, Nama_Model_Kendaraan: 'Honda Beat' }],
    colors: [{ Id_Master_Color: 1, Nama_Warna: 'Hitam' }, { Id_Master_Color: 2, Nama_Warna: 'Putih' }],
    kategoris: [{ Id_Kategori_Permit: 1, Nama_Kategori: 'HIJAU' }, { Id_Kategori_Permit: 2, Nama_Kategori: 'MERAH' }],
};

const PERMIT = {
    No_Transaksi: 'PMT0926-0001',
    Id_Master_Kategori_Permit: '2',
    Nama_Kategori: 'MERAH',
    No_Plat_Kendaraan: 'BG 1234 AA',
    Id_Master_Model: '3',
    Id_Master_Warna: '1',
    Tahun_Kendaraan: '2020',
    Keterangan_Pengajuan: 'Parkir harian',
};

async function pasang() {
    axios.get.mockImplementation((url) =>
        url === '/verifikator-permit-koreksi-master'
            ? Promise.resolve({ data: { success: true, result: MASTER } })
            : Promise.resolve({ data: { success: true, result: { dipertahankan: 2, dibuang: [{ teks: 'Rem mobil', terjawab: true }], ditambah: [] } } }),
    );
    const wrapper = shallowMount(PermitKoreksiDialog, { props: { modelValue: true, permit: PERMIT } });
    await flushPromises();
    return wrapper;
}

afterEach(() => vi.clearAllMocks());

describe('PermitKoreksiDialog', () => {
    it('form terisi data permit dan belum bisa disimpan tanpa perubahan', async () => {
        const w = await pasang();
        expect(w.vm.form.id_kategori_permit).toBe('2');
        expect(w.vm.form.no_plat).toBe('BG 1234 AA');
        expect(w.vm.perubahan).toEqual([]);
        expect(w.vm.bisaSimpan).toBe(false);
    });

    it('perubahan terdeteksi tapi alasan tetap wajib', async () => {
        const w = await pasang();
        w.vm.form.id_kategori_permit = '1';
        expect(w.vm.perubahan).toEqual(['Jenis permit']);
        expect(w.vm.bisaSimpan).toBe(false);
        w.vm.form.alasan = '  ';
        expect(w.vm.bisaSimpan).toBe(false);
        w.vm.form.alasan = 'Salah zona';
        expect(w.vm.bisaSimpan).toBe(true);
    });

    it('ganti model memuat pratinjau checklist dan menampilkan jawaban yang akan terbuang', async () => {
        const w = await pasang();
        w.vm.form.id_vehicle_model = '1';
        await w.vm.muatPratinjau();
        expect(axios.get).toHaveBeenCalledWith('/verifikator-permit/PMT0926-0001/koreksi/pratinjau-checklist', {
            params: { id_kategori_permit: '2', id_vehicle_model: '1' },
        });
        expect(w.vm.jawabanTerbuang).toBe(1);
    });

    it('simpan mengirim seluruh isian lalu memancarkan tersimpan', async () => {
        axios.post.mockResolvedValueOnce({ data: { success: true, result: { berubah: ['TAHUN'] } } });
        const w = await pasang();
        w.vm.form.tahun_kendaraan = 2019;
        w.vm.form.alasan = 'STNK 2019';
        await w.vm.simpan();
        expect(axios.post).toHaveBeenCalledWith('/verifikator-permit/PMT0926-0001/koreksi', {
            id_kategori_permit: '2',
            no_plat: 'BG 1234 AA',
            id_vehicle_model: '3',
            id_vehicle_color: '1',
            tahun_kendaraan: 2019,
            keterangan_pengajuan: 'Parkir harian',
            alasan: 'STNK 2019',
        });
        expect(w.emitted('tersimpan')[0]).toEqual([{ berubah: ['TAHUN'] }]);
        expect(w.emitted('update:modelValue')[0]).toEqual([false]);
    });

    it('galat server tampil di dialog, dialog tetap terbuka', async () => {
        axios.post.mockRejectedValueOnce({ response: { data: { message: 'Permit sudah tidak menunggu review.' } } });
        const w = await pasang();
        w.vm.form.tahun_kendaraan = 2019;
        w.vm.form.alasan = 'x';
        await w.vm.simpan();
        expect(w.vm.galat).toBe('Permit sudah tidak menunggu review.');
        expect(w.emitted('update:modelValue')).toBeUndefined();
    });
});
```

- [ ] **Step 7: Jalankan, pastikan gagal** — `npx vitest run …/PermitKoreksiDialog.test.js` → FAIL (komponen tidak ada).

- [ ] **Step 8: Buat `PermitKoreksiDialog.vue`**

```vue
<template>
    <HcisDialog
        :model-value="modelValue"
        title="Koreksi Data Pengajuan"
        description="Ubah data yang tidak sesuai hasil pemeriksaan. Setiap perubahan dicatat beserta alasannya."
        icon="pencil-square"
        @update:model-value="$emit('update:modelValue', $event)"
    >
        <HcisAlert v-if="galat" variant="danger" class="mb-3">{{ galat }}</HcisAlert>

        <div v-if="memuatMaster" class="text-muted small">Memuat data master…</div>
        <div v-else class="d-flex flex-column gap-3">
            <HcisField id="koreksi-kategori" label="Jenis Permit" required :hint="petunjuk('id_kategori_permit')">
                <HcisSelect id="koreksi-kategori-select" v-model="form.id_kategori_permit" :clearable="false" @update:model-value="muatPratinjau">
                    <HcisSelectOption v-for="k in master.kategoris" :key="k.Id_Kategori_Permit" :value="String(k.Id_Kategori_Permit)" :label="k.Nama_Kategori" />
                </HcisSelect>
            </HcisField>

            <HcisPlateInput id="koreksi-plat" v-model="form.no_plat" :prefix-options="master.plat_codes" label="Nomor Plat Kendaraan" :hint="petunjuk('no_plat')" required />

            <HcisField id="koreksi-model" label="Merek / Model" required :hint="petunjuk('id_vehicle_model') || 'Jenis kendaraan mengikuti model yang dipilih.'">
                <HcisTreeSelect
                    :model-value="form.id_vehicle_model || ''"
                    :data="pohon"
                    :clearable="false"
                    filterable
                    @update:model-value="form.id_vehicle_model = $event || null; muatPratinjau()"
                />
            </HcisField>

            <div class="row g-3">
                <div class="col-12 col-sm-6">
                    <HcisField id="koreksi-warna" label="Warna" required :hint="petunjuk('id_vehicle_color')">
                        <HcisSelect id="koreksi-warna-select" v-model="form.id_vehicle_color" :clearable="false" filterable>
                            <HcisSelectOption v-for="w in master.colors" :key="w.Id_Master_Color" :value="String(w.Id_Master_Color)" :label="w.Nama_Warna" />
                        </HcisSelect>
                    </HcisField>
                </div>
                <div class="col-12 col-sm-6">
                    <HcisField id="koreksi-tahun" label="Tahun Pembuatan" required :hint="petunjuk('tahun_kendaraan')">
                        <HcisSelect id="koreksi-tahun-select" v-model="form.tahun_kendaraan" :clearable="false" filterable>
                            <HcisSelectOption v-for="th in tahun" :key="th" :value="th" :label="String(th)" />
                        </HcisSelect>
                    </HcisField>
                </div>
            </div>

            <HcisTextarea id="koreksi-keterangan" v-model="form.keterangan_pengajuan" label="Keterangan Pengajuan" :maxlength="500" :hint="petunjuk('keterangan_pengajuan')" />

            <HcisAlert v-if="jawabanTerbuang > 0 || pratinjau?.ditambah?.length" variant="warning">
                Checklist akan disesuaikan: {{ pratinjau.dibuang.length }} pertanyaan dihapus
                <template v-if="jawabanTerbuang">({{ jawabanTerbuang }} sudah dijawab)</template>,
                {{ pratinjau.ditambah.length }} pertanyaan baru ditambahkan.
            </HcisAlert>

            <HcisTextarea id="koreksi-alasan" v-model="form.alasan" label="Alasan Koreksi" :maxlength="500" required placeholder="Contoh: kendaraan hanya parkir, bukan masuk area boiler." />
        </div>

        <template #footer>
            <HcisButton variant="ghost" label="Batal" @click="$emit('update:modelValue', false)" />
            <HcisButton :label="menyimpan ? 'Menyimpan...' : 'Simpan Koreksi'" :disabled="!bisaSimpan || menyimpan" @click="simpan" />
        </template>
    </HcisDialog>
</template>

<script>
import axios from 'axios';
import { HcisAlert, HcisButton, HcisDialog, HcisField, HcisPlateInput, HcisSelect, HcisSelectOption, HcisTextarea, HcisTreeSelect } from '@/components/ui';
import { daftarTahun, pohonModel } from './masterKendaraan';

const LABEL = {
    id_kategori_permit: 'Jenis permit',
    no_plat: 'Plat nomor',
    id_vehicle_model: 'Model kendaraan',
    id_vehicle_color: 'Warna',
    tahun_kendaraan: 'Tahun',
    keterangan_pengajuan: 'Keterangan',
};

const normal = (v) => String(v ?? '').trim().replace(/\s+/g, ' ').toUpperCase();

export default {
    name: 'PermitKoreksiDialog',
    components: { HcisAlert, HcisButton, HcisDialog, HcisField, HcisPlateInput, HcisSelect, HcisSelectOption, HcisTextarea, HcisTreeSelect },
    props: {
        modelValue: { type: Boolean, default: false },
        permit: { type: Object, required: true },
    },
    emits: ['update:modelValue', 'tersimpan'],
    data() {
        return {
            master: { plat_codes: [], vehicle_types: [], models: [], colors: [], kategoris: [] },
            memuatMaster: false,
            awal: {},
            form: {},
            pratinjau: null,
            menyimpan: false,
            galat: '',
        };
    },
    computed: {
        pohon() {
            return pohonModel(this.master.models, this.master.vehicle_types);
        },
        tahun() {
            return daftarTahun();
        },
        perubahan() {
            return Object.keys(LABEL)
                .filter((k) => normal(this.form[k]) !== normal(this.awal[k]))
                .map((k) => LABEL[k]);
        },
        bisaSimpan() {
            return this.perubahan.length > 0 && String(this.form.alasan ?? '').trim() !== '';
        },
        jawabanTerbuang() {
            return (this.pratinjau?.dibuang || []).filter((d) => d.terjawab).length;
        },
    },
    watch: {
        modelValue: {
            immediate: true,
            handler(buka) {
                if (buka) this.mulai();
            },
        },
    },
    methods: {
        async mulai() {
            const p = this.permit;
            this.awal = {
                id_kategori_permit: p.Id_Master_Kategori_Permit ? String(p.Id_Master_Kategori_Permit) : null,
                no_plat: p.No_Plat_Kendaraan || '',
                id_vehicle_model: p.Id_Master_Model ? String(p.Id_Master_Model) : null,
                id_vehicle_color: p.Id_Master_Warna ? String(p.Id_Master_Warna) : null,
                tahun_kendaraan: p.Tahun_Kendaraan ? Number(p.Tahun_Kendaraan) : null,
                keterangan_pengajuan: p.Keterangan_Pengajuan || '',
            };
            this.form = { ...this.awal, alasan: '' };
            this.pratinjau = null;
            this.galat = '';
            this.memuatMaster = true;
            try {
                const { data } = await axios.get('/verifikator-permit-koreksi-master');
                if (data?.success) this.master = { ...this.master, ...data.result };
            } catch (e) {
                this.galat = e.response?.data?.message || 'Master data gagal dimuat.';
            } finally {
                this.memuatMaster = false;
            }
        },
        petunjuk(kunci) {
            if (normal(this.form[kunci]) === normal(this.awal[kunci])) return '';
            return `Sebelumnya: ${this.tampilanAwal(kunci)}`;
        },
        tampilanAwal(kunci) {
            const v = this.awal[kunci];
            const cari = (daftar, idKey, namaKey) => daftar.find((x) => String(x[idKey]) === String(v))?.[namaKey];
            if (kunci === 'id_kategori_permit') return cari(this.master.kategoris, 'Id_Kategori_Permit', 'Nama_Kategori') || this.permit.Nama_Kategori || '-';
            if (kunci === 'id_vehicle_model') return cari(this.master.models, 'Id_Vehicle_Model', 'Nama_Model_Kendaraan') || '-';
            if (kunci === 'id_vehicle_color') return cari(this.master.colors, 'Id_Master_Color', 'Nama_Warna') || '-';
            return v === null || v === '' ? '-' : String(v);
        },
        async muatPratinjau() {
            const berubah = normal(this.form.id_kategori_permit) !== normal(this.awal.id_kategori_permit)
                || normal(this.form.id_vehicle_model) !== normal(this.awal.id_vehicle_model);
            if (!berubah || !this.form.id_kategori_permit || !this.form.id_vehicle_model) {
                this.pratinjau = null;
                return;
            }
            try {
                const { data } = await axios.get(`/verifikator-permit/${this.permit.No_Transaksi}/koreksi/pratinjau-checklist`, {
                    params: { id_kategori_permit: this.form.id_kategori_permit, id_vehicle_model: this.form.id_vehicle_model },
                });
                this.pratinjau = data?.success ? data.result : null;
            } catch (e) {
                this.pratinjau = null;
            }
        },
        async simpan() {
            if (!this.bisaSimpan) return;
            this.menyimpan = true;
            this.galat = '';
            try {
                const { alasan, ...isian } = this.form;
                const { data } = await axios.post(`/verifikator-permit/${this.permit.No_Transaksi}/koreksi`, {
                    ...isian,
                    alasan: alasan.trim(),
                });
                if (!data?.success) {
                    this.galat = data?.message || 'Koreksi gagal disimpan.';
                    return;
                }
                this.$emit('tersimpan', data.result);
                this.$emit('update:modelValue', false);
            } catch (e) {
                this.galat = e.response?.data?.message || 'Koreksi gagal disimpan. Periksa koneksi lalu coba lagi.';
            } finally {
                this.menyimpan = false;
            }
        },
    },
};
</script>
```

Sebelum memakai, cocokkan nama prop di `HcisDialog`, `HcisAlert` (`variant`?), `HcisButton` (`variant="ghost"`?) dan `HcisTextarea` dengan berkasnya di `resources/js/components/ui/` — sesuaikan ke nama prop yang benar, jangan menambah prop baru ke komponen UI.

- [ ] **Step 9: Jalankan tes dialog** — `npx vitest run …/PermitKoreksiDialog.test.js` → PASS (5 tes).

- [ ] **Step 10: Commit**

```bash
git add resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/masterKendaraan.js resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/masterKendaraan.test.js resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/PermitKoreksiDialog.vue resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/PermitKoreksiDialog.test.js resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/PermitReviewModal.vue app/Services/VISITOR/Permit/VehiclePermit/VisitorPermitQueryService.php
git commit -m "feat(visitor): dialog koreksi data permit di meja verifikator"
```

---

### Task 7: Riwayat koreksi + pemasangan di modal review

**Files:**
- Create: `…/components/PermitKoreksiRiwayat.vue`
- Create: `…/components/PermitKoreksiRiwayat.test.js`
- Modify: `…/components/PermitReviewModal.vue` (kartu "Spesifikasi Kendaraan" :346-356, `data()`, `loadDetail` :1570-1625, `components`)

**Interfaces:**
- Consumes: `PermitKoreksiDialog` (Task 6), `result.boleh_koreksi`, `result.riwayat_koreksi` (Task 5)
- Produces: `PermitKoreksiRiwayat` props `riwayat: Array` (bentuk `PermitKoreksiService::riwayat`)

- [ ] **Step 1: Tulis tes gagal** `PermitKoreksiRiwayat.test.js`

```js
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PermitKoreksiRiwayat from './PermitKoreksiRiwayat.vue';

const RIWAYAT = [
    {
        kelompok: 'b',
        waktu: '2026-09-23 10:15:00',
        oleh: 'A1',
        nama: 'Budi Santoso',
        alasan: 'Kendaraan hanya parkir',
        perubahan: [
            { field: 'KATEGORI_PERMIT', lama: 'MERAH', baru: 'HIJAU' },
            { field: 'KETERANGAN', lama: 'Masuk boiler', baru: null },
        ],
    },
];

describe('PermitKoreksiRiwayat', () => {
    it('menampilkan pelaku, alasan, dan perubahan lama → baru dengan label manusia', () => {
        const teks = mount(PermitKoreksiRiwayat, { props: { riwayat: RIWAYAT } }).text();
        expect(teks).toContain('Budi Santoso');
        expect(teks).toContain('Kendaraan hanya parkir');
        expect(teks).toContain('Jenis permit');
        expect(teks).toContain('MERAH');
        expect(teks).toContain('HIJAU');
        expect(teks).toContain('(kosong)');
    });

    it('tidak merender apa pun saat riwayat kosong', () => {
        expect(mount(PermitKoreksiRiwayat, { props: { riwayat: [] } }).html()).toBe('<!--v-if-->');
    });
});
```

- [ ] **Step 2: Jalankan, pastikan gagal** — `npx vitest run …/PermitKoreksiRiwayat.test.js` → FAIL.

- [ ] **Step 3: Buat `PermitKoreksiRiwayat.vue`**

```vue
<template>
    <div v-if="riwayat.length" class="permit-koreksi-riwayat">
        <div v-for="r in riwayat" :key="r.kelompok" class="permit-koreksi-riwayat__item">
            <div class="d-flex justify-content-between gap-2 small">
                <strong class="text-slate-800">{{ r.nama }}</strong>
                <span class="text-muted font-tabular">{{ r.waktu }}</span>
            </div>
            <p class="small text-slate-600 mb-1">“{{ r.alasan }}”</p>
            <ul class="small mb-0 ps-3">
                <li v-for="p in r.perubahan" :key="p.field">
                    {{ label(p.field) }}:
                    <span class="text-decoration-line-through text-muted">{{ p.lama ?? '(kosong)' }}</span>
                    →
                    <strong>{{ p.baru ?? '(kosong)' }}</strong>
                </li>
            </ul>
        </div>
    </div>
</template>

<script>
const LABEL = {
    KATEGORI_PERMIT: 'Jenis permit',
    PLAT_NOMOR: 'Plat nomor',
    MODEL_KENDARAAN: 'Model kendaraan',
    WARNA: 'Warna',
    TAHUN: 'Tahun',
    KETERANGAN: 'Keterangan',
};

export default {
    name: 'PermitKoreksiRiwayat',
    props: { riwayat: { type: Array, default: () => [] } },
    methods: {
        label(field) {
            return LABEL[field] || field;
        },
    },
};
</script>

<style scoped>
.permit-koreksi-riwayat { display: flex; flex-direction: column; gap: 0.75rem; }
.permit-koreksi-riwayat__item { border-left: 3px solid var(--hcis-warning, #f59e0b); padding-left: 0.75rem; }
</style>
```

- [ ] **Step 4: Pasang di `PermitReviewModal.vue`**
  - `components`: tambah `PermitKoreksiDialog`, `PermitKoreksiRiwayat` (import biasa dari `./PermitKoreksiDialog.vue`, `./PermitKoreksiRiwayat.vue`).
  - `data()`: tambah `bolehKoreksi: false`, `riwayatKoreksi: []`, `dialogKoreksi: false`.
  - `loadDetail` setelah `this.bolehRevoke = …`: `this.bolehKoreksi = json.result.boleh_koreksi === true && !this.readOnly;` dan `this.riwayatKoreksi = json.result.riwayat_koreksi || [];`
  - Kartu "Spesifikasi Kendaraan": tambah slot actions dan riwayat:

```vue
                        <template #actions>
                            <HcisButton
                                v-if="bolehKoreksi"
                                size="sm"
                                variant="outline"
                                icon="pencil-square"
                                label="Koreksi Data"
                                @click="dialogKoreksi = true"
                            />
                        </template>
```

    dan di dalam kartu, setelah callout keterangan:

```vue
                        <div v-if="riwayatKoreksi.length" class="mt-3">
                            <span class="permit-spec-tile__label">Riwayat Koreksi</span>
                            <PermitKoreksiRiwayat :riwayat="riwayatKoreksi" class="mt-2" />
                        </div>
```

  - Di akhir template modal (di luar kartu, di dalam root):

```vue
        <PermitKoreksiDialog
            v-if="permit && bolehKoreksi"
            v-model="dialogKoreksi"
            :permit="permit"
            @tersimpan="loadDetail"
        />
```

  - Kartu "Spesifikasi Kendaraan" punya `v-if="hasVehicleSpecs"`; permit HCIS selalu berplat sehingga kartunya tampil — periksa `hasVehicleSpecs` dan pastikan benar untuk permit HCIS.

- [ ] **Step 5: Tambah penjaga sumber di `PermitReviewModal.test.js`** (pola `bagianTemplate` yang sudah ada):

```js
describe('Meja verifikator — koreksi data permit', () => {
    it('tombol koreksi hanya dirender saat server mengizinkan', () => {
        const template = bagianTemplate(berkas('PermitReviewModal.vue'));
        expect(template).toContain('v-if="bolehKoreksi"');
        expect(template).toContain('<PermitKoreksiDialog');
        expect(template).toContain('@tersimpan="loadDetail"');
    });

    it('izin koreksi dibaca dari respons server, bukan dihitung sendiri', () => {
        const source = berkas('PermitReviewModal.vue');
        expect(source).toContain("json.result.boleh_koreksi === true");
    });
});
```

- [ ] **Step 6: Jalankan tes folder verifikator** — `npx vitest run resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/` → PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/PermitKoreksiRiwayat.vue resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/PermitKoreksiRiwayat.test.js resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/PermitReviewModal.vue resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/PermitReviewModal.test.js
git commit -m "feat(visitor): tombol koreksi dan riwayat koreksi di modal review permit"
```

---

### Task 8: Verifikasi berlapis & uji di dev server

**Files:** tidak ada berkas baru (kecuali perbaikan dari temuan).

- [ ] **Step 1: Skrip verifikasi**

Run (satu per satu, catat hasil):
```bash
php scripts/verify-references.php
php scripts/console-snapshot.php > /dev/null && echo console-ok
php scripts/route-snapshot.php > /dev/null && echo route-ok
php scripts/verify-inertia-pages.php
php scripts/verify-frontend-imports.php
```
Expected: tanpa referensi putus; `verify-frontend-imports` `putus=0`.

- [ ] **Step 2: Build** — `npm run build` → sukses tanpa galat.

- [ ] **Step 3: Tes terarah**

```bash
env -u DATABASE_URL php artisan test --filter='PermitKoreksiServiceTest|VisitorPermitChecklistSelarasTest|KategoriPermit|InspeksiPermitCoklatTest|VisitorRouteContractTest|AgendaSelarasStatusPermitTest'
npx vitest run resources/js/Pages/VISITOR resources/js/components/VISITOR
```
Expected: PASS. Kegagalan di luar berkas yang disentuh dibandingkan dengan baseline di memori `kendala-environment-kpi-unreal` sebelum disimpulkan.

- [ ] **Step 4: Uji nyata lewat tunnel** — pastikan Vite menyajikan kode terbaru (`stat` berkas vs `ps -o lstart` proses vite; restart `kpi-unreal-vite` bila basi), lalu minta user membuka `https://server-dev-noirxevo.ajt.my.id`, masuk sebagai verifikator, membuka permit PENDING HCIS, menekan **Koreksi Data**, mengganti jenis permit + model, melihat peringatan checklist, menyimpan, dan memastikan riwayat tampil. Cek baris di `N_EMI_VISITOR_Transaksi_Permit_Koreksi` untuk nomor permit tersebut.

- [ ] **Step 5: Laporan akhir** — dua bagian wajib (AGENTS.md): **Agent completed** (berkas, DDL dev dijalankan & diverifikasi, tes) dan **User tasks** (review DDL-2026-138, catat ke SQL Tracker, eksekusi production, verifikasi production, uji UAT).
