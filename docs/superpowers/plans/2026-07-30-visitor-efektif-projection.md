# Visitor Efektif Projection Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a denormalized "current effective access status" projection (`N_VISITOR_Permit_Efektif` / `N_VISITOR_Tamu_Efektif`) that a separate gate/security application can pull directly via SQL Server, without needing any of the Visitor module's master tables.

**Architecture:** A new `VisitorEfektifService` is the single write-path into two new projection tables. It's called from four trigger points: approval completion, auto-approve at submit, self-service cancel (existing `destroy()`), and a new admin-revoke endpoint — plus a scheduled command for expiry. Source transaction tables (`N_EMI_VISITOR_Transaksi_Permit`/`_Tamu`) are never touched by this feature; they stay as pure approval-process history.

**Tech Stack:** Laravel 10 (PHP), raw `DB::table()` query builder (no Eloquent models in this domain), SQL Server (sqlsrv), Laravel Console Commands + DB-driven scheduler (`N_HRIS_Scheduler_Configs`).

## Global Constraints

- No Laravel migrations for this domain — DDL is raw SQL written to `database/visitor/*.sql` and run manually by the user against SQL Server. Do NOT run `php artisan migrate` or execute the DDL yourself.
- `Flag_Sync` uses `Y`/`T` convention (matches `Flag_Aktif`/`Flag_Approval` elsewhere in this codebase), NOT `Y`/`N`.
- All master-derived fields are literal text, never `Id_Master_*` foreign keys.
- No PHPUnit feature tests with `RefreshDatabase` — this project's DB has no migrations/factories for `N_EMI_VISITOR_*`/`N_VISITOR_*` tables. Verify each task by running a short PHP script through `php artisan tinker <script-path>` against the real dev DB (same pattern used throughout this project's Visitor module work), per the `AllTransactionsReadParityTest` precedent (`tests/Feature/Transaksi/AllTransactionsReadParityTest.php`) of testing read/write directly against a live DB rather than fixtures.
- Follow existing code style in this domain: no Eloquent, `DB::table()` everywhere, `ResponseHelper::success()`/`ResponseHelper::error()` for controller responses, Indonesian identifier names matching the codebase.
- Full design rationale lives in `docs/superpowers/specs/2026-07-30-visitor-efektif-projection-design.md` — read it if any task instruction here seems to omit context.

---

## File Structure

**Create:**
- `database/visitor/2026_07_30_visitor_efektif_projection.sql` — DDL for both tables + scheduler config row (manual execution by user)
- `app/Services/Visitor/VisitorEfektifService.php` — single write-path service (sync + revoke methods)
- `app/Console/Commands/ExpireVisitorEfektif.php` — scheduled command for expiry transitions

**Modify:**
- `app/Http/Controllers/VerifikatorVisitor/VerifikatorVisitorController.php` — hook sync into `approvePermit()`/`approveTamu()`; add `revokePermit()`/`revokeTamu()` endpoints
- `app/Http/Controllers/TransaksiPermit/TransaksiPermitController.php` — hook sync into `store()` auto-approve branch; hook revoke into `destroy()`
- `app/Http/Controllers/TransaksiTamu/TransaksiTamuController.php` — hook sync into `store()` auto-approve branch; hook revoke into `destroy()`
- `routes/VerifikatorVisitor/VerifikatorVisitorWeb.php` — add 2 new revoke routes

---

### Task 1: DDL — Efektif tables + scheduler config row

**Files:**
- Create: `database/visitor/2026_07_30_visitor_efektif_projection.sql`

**Interfaces:**
- Produces: tables `N_VISITOR_Permit_Efektif`, `N_VISITOR_Tamu_Efektif` with columns listed below — every later task's SQL statements depend on these exact column names.

- [ ] **Step 1: Write the DDL file**

```sql
-- database/visitor/2026_07_30_visitor_efektif_projection.sql
-- Jalankan manual di SQL Server (Web_HRIS - sesuaikan environment target).
-- Desain lengkap: docs/superpowers/specs/2026-07-30-visitor-efektif-projection-design.md

IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_VISITOR_Permit_Efektif')
CREATE TABLE N_VISITOR_Permit_Efektif (
    Id_Permit_Efektif      INT IDENTITY(1,1) PRIMARY KEY,
    Kode_Perusahaan        VARCHAR(10)   NOT NULL DEFAULT '001',
    No_Transaksi           VARCHAR(20)   NOT NULL,
    Kode_Karyawan_Pemohon  VARCHAR(20)   NOT NULL,
    Nama_Pemohon           VARCHAR(150)  NOT NULL,
    No_HP_Pemohon          VARCHAR(30)   NULL,
    Departemen             VARCHAR(150)  NULL,
    Jabatan                VARCHAR(150)  NULL,
    Keterangan_Pengajuan   VARCHAR(500)  NULL,
    No_Plat_Kendaraan      VARCHAR(20)   NULL,
    Nama_Kategori_Permit   VARCHAR(150)  NULL,
    Deskripsi_Akses        VARCHAR(500)  NULL,
    Kode_Warna_UI          VARCHAR(20)   NULL,
    Nama_Vehicle_Type      VARCHAR(150)  NULL,
    Nama_Model_Kendaraan   VARCHAR(150)  NULL,
    Warna_Kendaraan        VARCHAR(100)  NULL,
    Tahun_Kendaraan        VARCHAR(10)   NULL,
    Nama_Durasi            VARCHAR(150)  NULL,
    Tanggal_Mulai_Berlaku  DATE          NULL,
    Tanggal_Kadaluarsa     DATE          NULL,
    Status_Efektif         VARCHAR(20)   NOT NULL,
    Alasan_Status          VARCHAR(500)  NULL,
    Dibatalkan_Oleh_Nama   VARCHAR(150)  NULL,
    Dibatalkan_At          DATETIME      NULL,
    Disetujui_Oleh_Nama    VARCHAR(150)  NOT NULL,
    Disetujui_At           DATETIME      NOT NULL,
    Riwayat_Approval       NVARCHAR(MAX) NOT NULL,
    Dibuat_At              DATETIME      NOT NULL,
    Dibuat_Oleh_Nama       VARCHAR(150)  NOT NULL,
    Updated_At             DATETIME      NOT NULL,
    Updated_Oleh_Nama      VARCHAR(150)  NULL,
    Flag_Sync              CHAR(1)       NOT NULL DEFAULT 'T',
    Synced_At              DATETIME      NULL,
    CONSTRAINT UQ_Permit_Efektif_NoTransaksi UNIQUE (No_Transaksi)
);

IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_VISITOR_Tamu_Efektif')
CREATE TABLE N_VISITOR_Tamu_Efektif (
    Id_Tamu_Efektif           INT IDENTITY(1,1) PRIMARY KEY,
    Kode_Perusahaan           VARCHAR(10)   NOT NULL DEFAULT '001',
    No_Transaksi              VARCHAR(20)   NOT NULL,
    Nama_Tamu                 VARCHAR(150)  NOT NULL,
    Instansi_Asal             VARCHAR(150)  NULL,
    No_HP                     VARCHAR(30)   NULL,
    Keperluan                 VARCHAR(500)  NULL,
    Keterangan_Tujuan_Detail  VARCHAR(500)  NULL,
    Keterangan_Tambahan       VARCHAR(500)  NULL,
    No_Identitas              VARCHAR(50)   NULL,
    Jenis_Identitas           VARCHAR(30)   NULL,
    Kode_Karyawan_PIC         VARCHAR(20)   NOT NULL,
    Nama_PIC                  VARCHAR(150)  NOT NULL,
    Divisi_PIC                VARCHAR(150)  NULL,
    Jenis_Kendaraan           VARCHAR(50)   NULL,
    No_Plat_Kendaraan         VARCHAR(20)   NULL,
    Tanggal_Kunjungan         DATE          NOT NULL,
    Waktu_Estimasi_Kunjungan  VARCHAR(50)   NULL,
    Jumlah_Tamu               INT           NULL,
    Status_Efektif            VARCHAR(20)   NOT NULL,
    Alasan_Status             VARCHAR(500)  NULL,
    Dibatalkan_Oleh_Nama      VARCHAR(150)  NULL,
    Dibatalkan_At             DATETIME      NULL,
    Disetujui_Oleh_Nama       VARCHAR(150)  NOT NULL,
    Disetujui_At              DATETIME      NOT NULL,
    Riwayat_Approval          NVARCHAR(MAX) NOT NULL,
    Dibuat_At                 DATETIME      NOT NULL,
    Dibuat_Oleh_Nama          VARCHAR(150)  NOT NULL,
    Updated_At                DATETIME      NOT NULL,
    Updated_Oleh_Nama         VARCHAR(150)  NULL,
    Flag_Sync                 CHAR(1)       NOT NULL DEFAULT 'T',
    Synced_At                 DATETIME      NULL,
    CONSTRAINT UQ_Tamu_Efektif_NoTransaksi UNIQUE (No_Transaksi)
);

-- Jadwalkan command expire lewat scheduler DB-driven (lihat app/Console/Kernel.php,
-- pola sama dengan izin:expiredIzin / cuti:tambahCuti). Command class dibuat di Task 10.
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Scheduler_Configs WHERE Command = 'visitor:expire-efektif')
INSERT INTO N_HRIS_Scheduler_Configs (Command, Cron_Expression, Timezone, Flag_Aktif, Keterangan, Created_At)
VALUES ('visitor:expire-efektif', '5 0 * * *', 'Asia/Jakarta', 'Y', 'Expire proyeksi Efektif visitor lewat Tanggal_Kadaluarsa/Tanggal_Kunjungan (Daily 00:05)', GETDATE());
```

- [ ] **Step 2: STOP — ask the user to run this file manually**

This task cannot be verified until the user executes `database/visitor/2026_07_30_visitor_efektif_projection.sql` against the target SQL Server database (per explicit instruction earlier in this project: Claude does not execute DDL/migrations for this feature). Tell the user:

> "DDL siap di `database/visitor/2026_07_30_visitor_efektif_projection.sql`. Tolong jalankan dulu di SQL Server sebelum saya lanjut ke Task 2, karena task-task berikutnya perlu tabel ini sudah ada untuk bisa dites."

Do not proceed to Task 2 until the user confirms the DDL ran successfully. Once confirmed, verify read access with:

```php
// tinker verification
$cols = DB::select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME IN ('N_VISITOR_Permit_Efektif','N_VISITOR_Tamu_Efektif')");
echo count($cols) . " columns found across both tables\n"; // expect 33 + 32 = 65
```

Expected: 65 columns total. If 0, the DDL has not been run yet — stop and ask again.

- [ ] **Step 3: Commit**

```bash
git add database/visitor/2026_07_30_visitor_efektif_projection.sql
git commit -m "docs(visitor): add DDL for Efektif projection tables"
```

---

### Task 2: VisitorEfektifService — syncPermitEfektif + shared approval-history resolver

**Files:**
- Create: `app/Services/Visitor/VisitorEfektifService.php`

**Interfaces:**
- Consumes: `App\Services\Visitor\VisitorPermitQueryService::basePermitQuery(): \Illuminate\Database\Query\Builder` (existing, `app/Services/Visitor/VisitorPermitQueryService.php`)
- Produces: `VisitorEfektifService::syncPermitEfektif(string $noTransaksi): void` — later tasks (5, 6) call this exact method.

- [ ] **Step 1: Write the service with `syncPermitEfektif()` and the private approval-history resolver**

```php
<?php

namespace App\Services\Visitor;

use Illuminate\Support\Facades\DB;

class VisitorEfektifService
{
    public function __construct(
        protected VisitorPermitQueryService $permitQuery,
    ) {
    }

    public function syncPermitEfektif(string $noTransaksi): void
    {
        $header = $this->permitQuery->basePermitQuery()
            ->where('h.No_Transaksi', $noTransaksi)
            ->first();

        if (! $header) {
            return;
        }

        $existing = DB::table('N_VISITOR_Permit_Efektif')
            ->where('No_Transaksi', $noTransaksi)
            ->first();

        $now = now();
        $row = [
            'Kode_Perusahaan' => '001',
            'No_Transaksi' => $noTransaksi,
            'Kode_Karyawan_Pemohon' => $header->Kode_Karyawan_Pemohon,
            'Nama_Pemohon' => $header->Nama_Pemohon,
            'No_HP_Pemohon' => $header->No_HP_Pemohon,
            'Departemen' => $header->Departemen,
            'Jabatan' => $header->Jabatan,
            'Keterangan_Pengajuan' => $header->Keterangan_Pengajuan,
            'No_Plat_Kendaraan' => $header->No_Plat_Kendaraan,
            'Nama_Kategori_Permit' => $header->Nama_Kategori,
            'Deskripsi_Akses' => $header->Deskripsi_Kategori,
            'Kode_Warna_UI' => $header->Kode_Warna,
            'Nama_Vehicle_Type' => $header->Nama_Vehicle_Type,
            'Nama_Model_Kendaraan' => $header->Nama_Model_Manual,
            'Warna_Kendaraan' => $header->Warna_Manual,
            'Tahun_Kendaraan' => $header->Tahun_Kendaraan,
            'Nama_Durasi' => null,
            'Tanggal_Mulai_Berlaku' => $header->Tanggal_Mulai_Berlaku,
            'Tanggal_Kadaluarsa' => $header->Tanggal_Kadaluarsa,
            'Status_Efektif' => 'AKTIF',
            'Updated_At' => $now,
        ];

        if ($existing) {
            DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $noTransaksi)->update($row);

            return;
        }

        $meta = $this->resolveApprovalMeta($noTransaksi);

        DB::table('N_VISITOR_Permit_Efektif')->insert(array_merge($row, [
            'Disetujui_Oleh_Nama' => $meta['disetujui_oleh_nama'],
            'Disetujui_At' => $meta['disetujui_at'],
            'Riwayat_Approval' => $meta['riwayat_approval'],
            'Dibuat_At' => $header->Tanggal_Pengajuan,
            'Dibuat_Oleh_Nama' => $header->Nama_Pemohon,
            'Updated_Oleh_Nama' => $meta['disetujui_oleh_nama'],
            'Flag_Sync' => 'T',
        ]));
    }

    /**
     * Resolve final-step approver + full step history from HRIS_Approval_Request.
     * Empty result (no rows) means auto-approve — no human approver involved.
     *
     * @return array{disetujui_oleh_nama: string, disetujui_at: \Illuminate\Support\Carbon, riwayat_approval: string}
     */
    private function resolveApprovalMeta(string $noTransaksi): array
    {
        $rows = DB::table('HRIS_Approval_Request as ar')
            ->leftJoin('Karyawan as k', 'ar.Approved_By_Kode', '=', 'k.Kode_Karyawan')
            ->where('ar.No_Transaksi', $noTransaksi)
            ->orderBy('ar.Request_Order_Flow')
            ->select('ar.Request_Order_Flow', 'ar.Tanggal_Approval', 'k.Nama as Nama_Approver')
            ->get();

        if ($rows->isEmpty()) {
            return [
                'disetujui_oleh_nama' => 'Sistem (Auto-Approve)',
                'disetujui_at' => now(),
                'riwayat_approval' => '[]',
            ];
        }

        $last = $rows->last();

        $riwayat = $rows->map(fn ($r) => [
            'urutan' => (int) $r->Request_Order_Flow,
            'nama' => $r->Nama_Approver ?? 'Tidak diketahui',
            'tanggal' => (string) $r->Tanggal_Approval,
        ])->values()->all();

        return [
            'disetujui_oleh_nama' => $last->Nama_Approver ?? 'Tidak diketahui',
            'disetujui_at' => $last->Tanggal_Approval,
            'riwayat_approval' => json_encode($riwayat),
        ];
    }
}
```

- [ ] **Step 2: Verify against dev DB with tinker**

Requires Task 1's DDL already run. Use the real permit `PMT0426-0001` from the dev DB (already approved/expired in this project's history — see `docs/superpowers/specs/2026-07-30-visitor-efektif-projection-design.md` context) or any `No_Transaksi` you know exists in `N_EMI_VISITOR_Transaksi_Permit`.

```php
// tinker verification
$svc = app(\App\Services\Visitor\VisitorEfektifService::class);
$svc->syncPermitEfektif('PMT0426-0001');

$row = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', 'PMT0426-0001')->first();
echo "Status_Efektif: {$row->Status_Efektif}\n";
echo "Disetujui_Oleh_Nama: {$row->Disetujui_Oleh_Nama}\n";
echo "Dibuat_Oleh_Nama: {$row->Dibuat_Oleh_Nama}\n";
echo "Flag_Sync: {$row->Flag_Sync}\n";
echo "Riwayat_Approval: {$row->Riwayat_Approval}\n";

// Re-run to confirm upsert (no duplicate row)
$svc->syncPermitEfektif('PMT0426-0001');
$count = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', 'PMT0426-0001')->count();
echo "Row count after re-sync: {$count}\n"; // MUST be 1, not 2
```

Expected: `Status_Efektif` = `AKTIF`, `Flag_Sync` = `T`, `Riwayat_Approval` a valid JSON array (or `[]`), row count stays `1` after re-sync.

- [ ] **Step 3: Commit**

```bash
git add app/Services/Visitor/VisitorEfektifService.php
git commit -m "feat(visitor): add syncPermitEfektif to VisitorEfektifService"
```

---

### Task 3: VisitorEfektifService — syncTamuEfektif

**Files:**
- Modify: `app/Services/Visitor/VisitorEfektifService.php`

**Interfaces:**
- Consumes: `VisitorPermitQueryService::baseTamuQuery(): \Illuminate\Database\Query\Builder` (existing)
- Produces: `VisitorEfektifService::syncTamuEfektif(string $noTransaksi): void` — used by Tasks 5 and 7.

- [ ] **Step 1: Add `syncTamuEfektif()` to the service**

```php
    public function syncTamuEfektif(string $noTransaksi): void
    {
        $header = $this->permitQuery->baseTamuQuery()
            ->where('h.No_Transaksi', $noTransaksi)
            ->first();

        if (! $header) {
            return;
        }

        $existing = DB::table('N_VISITOR_Tamu_Efektif')
            ->where('No_Transaksi', $noTransaksi)
            ->first();

        $now = now();
        $row = [
            'Kode_Perusahaan' => '001',
            'No_Transaksi' => $noTransaksi,
            'Nama_Tamu' => $header->Nama_Tamu,
            'Instansi_Asal' => $header->Instansi_Asal,
            'No_HP' => $header->No_HP,
            'Keperluan' => $header->Keperluan,
            'Keterangan_Tujuan_Detail' => $header->PIC_Tujuan,
            'Keterangan_Tambahan' => $header->Keterangan,
            'No_Identitas' => $header->No_Identitas,
            'Jenis_Identitas' => $header->Jenis_Identitas,
            'Kode_Karyawan_PIC' => $header->Kode_Karyawan_PIC,
            'Nama_PIC' => $header->Nama_PIC,
            'Divisi_PIC' => $header->Divisi_PIC,
            'Jenis_Kendaraan' => $header->Jenis_Kendaraan,
            'No_Plat_Kendaraan' => $header->No_Plat_Kendaraan,
            'Tanggal_Kunjungan' => $header->Tanggal_Kunjungan,
            'Waktu_Estimasi_Kunjungan' => $header->Waktu_Estimasi_Kunjungan,
            'Jumlah_Tamu' => $header->Jumlah_Tamu,
            'Status_Efektif' => 'AKTIF',
            'Updated_At' => $now,
        ];

        if ($existing) {
            DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $noTransaksi)->update($row);

            return;
        }

        $meta = $this->resolveApprovalMeta($noTransaksi);

        DB::table('N_VISITOR_Tamu_Efektif')->insert(array_merge($row, [
            'Disetujui_Oleh_Nama' => $meta['disetujui_oleh_nama'],
            'Disetujui_At' => $meta['disetujui_at'],
            'Riwayat_Approval' => $meta['riwayat_approval'],
            'Dibuat_At' => $header->Created_At,
            'Dibuat_Oleh_Nama' => $header->Nama_PIC,
            'Updated_Oleh_Nama' => $meta['disetujui_oleh_nama'],
            'Flag_Sync' => 'T',
        ]));
    }
```

- [ ] **Step 2: Verify against dev DB with tinker**

Use a real `No_Transaksi` from `N_EMI_VISITOR_Transaksi_Tamu` (e.g. `VSR0426-0001`, confirmed present in dev DB from earlier project work).

```php
// tinker verification
$svc = app(\App\Services\Visitor\VisitorEfektifService::class);
$svc->syncTamuEfektif('VSR0426-0001');

$row = DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', 'VSR0426-0001')->first();
echo "Status_Efektif: {$row->Status_Efektif}\n";
echo "Nama_Tamu: {$row->Nama_Tamu}\n";
echo "Dibuat_Oleh_Nama: {$row->Dibuat_Oleh_Nama}\n";
echo "No_Identitas: " . var_export($row->No_Identitas, true) . "\n";

$svc->syncTamuEfektif('VSR0426-0001');
$count = DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', 'VSR0426-0001')->count();
echo "Row count after re-sync: {$count}\n"; // MUST be 1
```

Expected: `Status_Efektif` = `AKTIF`, row count stays `1` after re-sync.

- [ ] **Step 3: Commit**

```bash
git add app/Services/Visitor/VisitorEfektifService.php
git commit -m "feat(visitor): add syncTamuEfektif to VisitorEfektifService"
```

---

### Task 4: VisitorEfektifService — revokePermitEfektif + revokeTamuEfektif

**Files:**
- Modify: `app/Services/Visitor/VisitorEfektifService.php`

**Interfaces:**
- Produces: `VisitorEfektifService::revokePermitEfektif(string $noTransaksi, ?string $alasan, string $olehKode): void`, `VisitorEfektifService::revokeTamuEfektif(string $noTransaksi, ?string $alasan, string $olehKode): void` — used by Tasks 8 and 9.

- [ ] **Step 1: Add both revoke methods**

```php
    public function revokePermitEfektif(string $noTransaksi, ?string $alasan, string $olehKode): void
    {
        $this->revokeEfektifRow('N_VISITOR_Permit_Efektif', $noTransaksi, $alasan, $olehKode);
    }

    public function revokeTamuEfektif(string $noTransaksi, ?string $alasan, string $olehKode): void
    {
        $this->revokeEfektifRow('N_VISITOR_Tamu_Efektif', $noTransaksi, $alasan, $olehKode);
    }

    private function revokeEfektifRow(string $table, string $noTransaksi, ?string $alasan, string $olehKode): void
    {
        $exists = DB::table($table)
            ->where('No_Transaksi', $noTransaksi)
            ->where('Status_Efektif', 'AKTIF')
            ->exists();

        if (! $exists) {
            return;
        }

        $namaOleh = DB::table('Karyawan')->where('Kode_Karyawan', $olehKode)->value('Nama') ?? $olehKode;
        $now = now();

        DB::table($table)->where('No_Transaksi', $noTransaksi)->update([
            'Status_Efektif' => 'DIBATALKAN',
            'Alasan_Status' => $alasan,
            'Dibatalkan_Oleh_Nama' => $namaOleh,
            'Dibatalkan_At' => $now,
            'Updated_At' => $now,
            'Updated_Oleh_Nama' => $namaOleh,
            'Flag_Sync' => 'T',
        ]);
    }
```

- [ ] **Step 2: Verify against dev DB with tinker**

```php
// tinker verification
$svc = app(\App\Services\Visitor\VisitorEfektifService::class);

// Ensure a row exists AKTIF first (re-uses Task 2's synced row)
$svc->syncPermitEfektif('PMT0426-0001');

$svc->revokePermitEfektif('PMT0426-0001', 'Uji coba revoke', 'H45');

$row = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', 'PMT0426-0001')->first();
echo "Status_Efektif: {$row->Status_Efektif}\n"; // DIBATALKAN
echo "Alasan_Status: {$row->Alasan_Status}\n";
echo "Dibatalkan_Oleh_Nama: {$row->Dibatalkan_Oleh_Nama}\n";
echo "Flag_Sync: {$row->Flag_Sync}\n"; // T

// Revoke again on an already-DIBATALKAN row should be a no-op (no exception, no change)
$before = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', 'PMT0426-0001')->first();
$svc->revokePermitEfektif('PMT0426-0001', 'Percobaan kedua', 'H45');
$after = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', 'PMT0426-0001')->first();
echo "Alasan tidak berubah: " . ($before->Alasan_Status === $after->Alasan_Status ? 'YA' : 'TIDAK, ADA BUG') . "\n";

// Revoke on a No_Transaksi with no Efektif row at all must be a safe no-op
$svc->revokeTamuEfektif('VSR-TIDAK-ADA-0000', 'test', 'H45'); // must not throw
echo "No-op case OK, tidak melempar exception\n";
```

Expected: status transitions to `DIBATALKAN` with reason/actor recorded; re-revoking an already-`DIBATALKAN` row is a no-op (doesn't overwrite `Alasan_Status`); revoking a non-existent `No_Transaksi` doesn't throw.

- [ ] **Step 3: Commit**

```bash
git add app/Services/Visitor/VisitorEfektifService.php
git commit -m "feat(visitor): add revokePermitEfektif and revokeTamuEfektif"
```

---

### Task 5: Hook sync into approval-completion (VerifikatorVisitorController)

**Files:**
- Modify: `app/Http/Controllers/VerifikatorVisitor/VerifikatorVisitorController.php:1-22` (constructor), `:149-176` (`approvePermit`), `:302-326` (`approveTamu`)

**Interfaces:**
- Consumes: `VisitorEfektifService::syncPermitEfektif()`, `::syncTamuEfektif()` (Tasks 2, 3)

- [ ] **Step 1: Inject `VisitorEfektifService` into the constructor**

```php
use App\Services\Visitor\VisitorEfektifService;
use App\Services\Visitor\VisitorPermitQueryService;
// ... (keep existing use statements)

class VerifikatorVisitorController extends Controller
{
    public function __construct(
        protected VisitorApprovalPolicyService $visitorApprovalPolicy,
        protected ApprovalRequestService $approvalRequestService,
        protected VisitorPermitQueryService $permitQuery,
        protected VisitorEfektifService $visitorEfektif,
    ) {
    }
```

- [ ] **Step 2: Call sync after `isApprovalComplete()` in `approvePermit()`**

Find this exact block (currently lines 160-170):

```php
            if ($this->permitQuery->isApprovalComplete($noTransaksi)) {
                DB::table('N_EMI_VISITOR_Transaksi_Permit')
                    ->where('No_Transaksi', $noTransaksi)
                    ->update([
                        'Flag_Approval' => 'Y',
                        'Approved_At'   => now(),
                        'Approved_By'   => $approverKode,
                        'Updated_At'    => now(),
                        'Updated_By'    => $approverKode,
                    ]);
            }
```

Replace with:

```php
            if ($this->permitQuery->isApprovalComplete($noTransaksi)) {
                DB::table('N_EMI_VISITOR_Transaksi_Permit')
                    ->where('No_Transaksi', $noTransaksi)
                    ->update([
                        'Flag_Approval' => 'Y',
                        'Approved_At'   => now(),
                        'Approved_By'   => $approverKode,
                        'Updated_At'    => now(),
                        'Updated_By'    => $approverKode,
                    ]);

                $this->visitorEfektif->syncPermitEfektif($noTransaksi);
            }
```

- [ ] **Step 3: Call sync after `isApprovalComplete()` in `approveTamu()`**

Find this exact block (currently lines 310-320):

```php
            if ($this->permitQuery->isApprovalComplete($noTransaksi)) {
                // N_EMI_VISITOR_Transaksi_Tamu tidak punya kolom Updated_At/Updated_By (dikonfirmasi
                // via INFORMATION_SCHEMA - hanya Created_At/Created_By) - dulu update ini akan selalu
                // throw "Invalid column name", jadi approveTamu() belum pernah berhasil dipanggil.
                DB::table('N_EMI_VISITOR_Transaksi_Tamu')
                    ->where('No_Transaksi', $noTransaksi)
                    ->update([
                        'Flag_Approval'    => 'Y',
                        'Status_Kunjungan' => 'TERJADWAL',
                    ]);
            }
```

Replace with:

```php
            if ($this->permitQuery->isApprovalComplete($noTransaksi)) {
                // N_EMI_VISITOR_Transaksi_Tamu tidak punya kolom Updated_At/Updated_By (dikonfirmasi
                // via INFORMATION_SCHEMA - hanya Created_At/Created_By) - dulu update ini akan selalu
                // throw "Invalid column name", jadi approveTamu() belum pernah berhasil dipanggil.
                DB::table('N_EMI_VISITOR_Transaksi_Tamu')
                    ->where('No_Transaksi', $noTransaksi)
                    ->update([
                        'Flag_Approval'    => 'Y',
                        'Status_Kunjungan' => 'TERJADWAL',
                    ]);

                $this->visitorEfektif->syncTamuEfektif($noTransaksi);
            }
```

- [ ] **Step 4: Verify with tinker**

```php
// tinker verification
DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', 'PMT0426-0001')->delete();

$controller = app(\App\Http\Controllers\VerifikatorVisitor\VerifikatorVisitorController::class);
$request = \Illuminate\Http\Request::create('/verifikator-permit/PMT0426-0001/approve', 'POST', ['catatan' => 'test']);
$request->setUserResolver(fn () => \App\Models\User::where('Id_Users', 4)->first());

$response = $controller->approvePermit($request, 'PMT0426-0001');
echo json_encode($response->getData()) . "\n";

$row = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', 'PMT0426-0001')->first();
echo $row ? "Efektif row created, Status_Efektif={$row->Status_Efektif}\n" : "FAIL: no Efektif row created\n";
```

Expected: either a successful approval (if the transaction's approval state allows it) creates the Efektif row, OR the call fails with a business-logic error (e.g. "bukan approver" / already expired) — in the latter case, re-run Task 2's `syncPermitEfektif()` call directly instead, since Task 5's job is just to confirm the *hook is wired*, not to force a real approval through an already-resolved transaction.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/VerifikatorVisitor/VerifikatorVisitorController.php
git commit -m "feat(visitor): sync Efektif projection when verifikator approval completes"
```

---

### Task 6: Hook sync into Permit auto-approve (TransaksiPermitController::store)

**Files:**
- Modify: `app/Http/Controllers/TransaksiPermit/TransaksiPermitController.php:1-17` (constructor), `:182-190` (end of `store()`)

**Interfaces:**
- Consumes: `VisitorEfektifService::syncPermitEfektif()` (Task 2)

- [ ] **Step 1: Inject `VisitorEfektifService`**

```php
use App\Services\Visitor\VisitorApprovalPolicyService;
use App\Services\Visitor\VisitorEfektifService;
// ... keep other use statements

class TransaksiPermitController extends Controller
{
    public function __construct(
        protected VisitorApprovalPolicyService $visitorApprovalPolicy,
        protected VisitorEfektifService $visitorEfektif,
    ) {}
```

- [ ] **Step 2: Call sync when auto-approved, right after `DB::commit()`**

Find this exact block (currently lines 182-190):

```php
            if ((bool) $config['needs_approval']) {
                $this->visitorApprovalPolicy->createFlow('permit', $noTransaksi, $kodeKaryawan);
            }

            DB::commit();

            $message = $isAutoApproved
                ? 'Permit berhasil dibuat dan disetujui otomatis.'
                : 'Pengajuan permit berhasil dikirim dan menunggu approval.';
```

Replace with:

```php
            if ((bool) $config['needs_approval']) {
                $this->visitorApprovalPolicy->createFlow('permit', $noTransaksi, $kodeKaryawan);
            }

            DB::commit();

            if ($isAutoApproved) {
                $this->visitorEfektif->syncPermitEfektif($noTransaksi);
            }

            $message = $isAutoApproved
                ? 'Permit berhasil dibuat dan disetujui otomatis.'
                : 'Pengajuan permit berhasil dikirim dan menunggu approval.';
```

Note: called AFTER `DB::commit()`, not inside the transaction — the Efektif tables are a separate concern and shouldn't extend the source transaction's lock scope.

- [ ] **Step 3: Verify with tinker**

Requires `config('services.visitor.permit')['needs_approval']` to be togglable, or verify by reading current config. Simplest check: confirm the code path is reachable without error by directly testing the auto-approve branch logic in isolation — since flipping global config for a real submission is invasive, verify by unit-checking the service call directly (already covered by Task 2's test) and confirm no syntax/injection errors:

```php
// tinker verification — confirm DI resolves and no fatal error
$controller = app(\App\Http\Controllers\TransaksiPermit\TransaksiPermitController::class);
echo "Controller resolved OK: " . get_class($controller) . "\n";
```

Expected: no exception during resolution (confirms constructor injection of `VisitorEfektifService` works). Full end-to-end auto-approve verification happens naturally once a real auto-approve config is active in an environment — note this limitation to the user.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/TransaksiPermit/TransaksiPermitController.php
git commit -m "feat(visitor): sync Efektif projection on permit auto-approve"
```

---

### Task 7: Hook sync into Tamu auto-approve (TransaksiTamuController::store)

**Files:**
- Modify: `app/Http/Controllers/TransaksiTamu/TransaksiTamuController.php:19` (constructor), `:155-159` (end of `store()`)

**Interfaces:**
- Consumes: `VisitorEfektifService::syncTamuEfektif()` (Task 3)

- [ ] **Step 1: Inject `VisitorEfektifService`**

```php
use App\Services\Visitor\VisitorApprovalPolicyService;
use App\Services\Visitor\VisitorEfektifService;

class TransaksiTamuController extends Controller
{
    private const T_HEADER = 'N_EMI_VISITOR_Transaksi_Tamu';

    private const T_DETAIL = 'N_EMI_VISITOR_Transaksi_Tamu_Detail';

    public function __construct(
        protected VisitorApprovalPolicyService $visitorApprovalPolicy,
        protected VisitorEfektifService $visitorEfektif,
    ) {}
```

- [ ] **Step 2: Call sync when auto-approved, right after `DB::commit()`**

Find this exact block (currently lines 155-159):

```php
            if ($needsApproval) {
                $this->visitorApprovalPolicy->createFlow('tamu', $noTransaksi, $kodeKaryawan);
            }

            DB::commit();
```

Replace with:

```php
            if ($needsApproval) {
                $this->visitorApprovalPolicy->createFlow('tamu', $noTransaksi, $kodeKaryawan);
            }

            DB::commit();

            if (! $needsApproval) {
                $this->visitorEfektif->syncTamuEfektif($noTransaksi);
            }
```

- [ ] **Step 3: Verify with tinker**

```php
// tinker verification — confirm DI resolves
$controller = app(\App\Http\Controllers\TransaksiTamu\TransaksiTamuController::class);
echo "Controller resolved OK: " . get_class($controller) . "\n";
```

Expected: no exception (confirms constructor injection works). Same end-to-end caveat as Task 6.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/TransaksiTamu/TransaksiTamuController.php
git commit -m "feat(visitor): sync Efektif projection on tamu auto-approve"
```

---

### Task 8: Hook revoke into self-service cancel (destroy() on both controllers)

**Files:**
- Modify: `app/Http/Controllers/TransaksiPermit/TransaksiPermitController.php:220-231` (`destroy`)
- Modify: `app/Http/Controllers/TransaksiTamu/TransaksiTamuController.php:269-280` (`destroy`)

**Interfaces:**
- Consumes: `VisitorEfektifService::revokePermitEfektif()`, `::revokeTamuEfektif()` (Task 4)

- [ ] **Step 1: Update `TransaksiPermitController::destroy()` to accept `Request` and hook revoke**

Find this exact method:

```php
    public function destroy(string $id)
    {
        try {
            DB::table('N_EMI_VISITOR_Transaksi_Permit')
                ->where('Id_Transaksi_Permit', $id)
                ->update(['Flag_Aktif' => 'N', 'Updated_At' => now()]);

            return ResponseHelper::success(null, 'Permit berhasil dihapus.');
        } catch (\Exception $e) {
            return ResponseHelper::error('Gagal menghapus: '.$e->getMessage());
        }
    }
```

Replace with:

```php
    public function destroy(Request $request, string $id)
    {
        try {
            $header = DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('Id_Transaksi_Permit', $id)->first();

            DB::table('N_EMI_VISITOR_Transaksi_Permit')
                ->where('Id_Transaksi_Permit', $id)
                ->update(['Flag_Aktif' => 'N', 'Updated_At' => now()]);

            if ($header) {
                $kodeKaryawan = $this->visitorApprovalPolicy->requesterKode($request->user()) ?? $header->Kode_Karyawan_Pemohon;
                $this->visitorEfektif->revokePermitEfektif($header->No_Transaksi, 'Dibatalkan sendiri oleh pemohon', $kodeKaryawan);
            }

            return ResponseHelper::success(null, 'Permit berhasil dihapus.');
        } catch (\Exception $e) {
            return ResponseHelper::error('Gagal menghapus: '.$e->getMessage());
        }
    }
```

- [ ] **Step 2: Update `TransaksiTamuController::destroy()` the same way**

Find this exact method (around line 269):

```php
    public function destroy(string $id)
    {
        try {
            DB::table(self::T_HEADER)
                ->where('Id_Transaksi_Tamu', $id)
                ->update(['Flag_Aktif' => 'N']);

            return ResponseHelper::success(null, 'Data tamu berhasil dihapus.');
        } catch (\Exception $e) {
            return ResponseHelper::error('Gagal menghapus: '.$e->getMessage());
        }
    }
```

Replace with:

```php
    public function destroy(Request $request, string $id)
    {
        try {
            $header = DB::table(self::T_HEADER)->where('Id_Transaksi_Tamu', $id)->first();

            DB::table(self::T_HEADER)
                ->where('Id_Transaksi_Tamu', $id)
                ->update(['Flag_Aktif' => 'N']);

            if ($header) {
                $kodeKaryawan = $this->visitorApprovalPolicy->requesterKode($request->user()) ?? $header->Kode_Karyawan_PIC;
                $this->visitorEfektif->revokeTamuEfektif($header->No_Transaksi, 'Dibatalkan sendiri oleh PIC', $kodeKaryawan);
            }

            return ResponseHelper::success(null, 'Data tamu berhasil dihapus.');
        } catch (\Exception $e) {
            return ResponseHelper::error('Gagal menghapus: '.$e->getMessage());
        }
    }
```

- [ ] **Step 3: Verify with tinker**

```php
// tinker verification
$svc = app(\App\Services\Visitor\VisitorEfektifService::class);
$svc->syncPermitEfektif('PMT0426-0001'); // ensure AKTIF baseline

$controller = app(\App\Http\Controllers\TransaksiPermit\TransaksiPermitController::class);
$request = \Illuminate\Http\Request::create('/transaksipermit/7', 'DELETE');
$request->setUserResolver(fn () => \App\Models\User::where('Id_Users', 4)->first());

$response = $controller->destroy($request, '7'); // Id_Transaksi_Permit=7 is PMT0426-0001
echo json_encode($response->getData()) . "\n";

$row = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', 'PMT0426-0001')->first();
echo "Status_Efektif after destroy(): {$row->Status_Efektif}\n"; // expect DIBATALKAN
```

Expected: `Status_Efektif` = `DIBATALKAN` after calling `destroy()`. **Reset the source `Flag_Aktif` back to `'Y'` afterward if this is a shared dev DB record other tests rely on** — confirm with the user before leaving it soft-deleted.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/TransaksiPermit/TransaksiPermitController.php app/Http/Controllers/TransaksiTamu/TransaksiTamuController.php
git commit -m "feat(visitor): revoke Efektif projection when requester self-cancels"
```

---

### Task 9: Admin revoke endpoints (VerifikatorVisitorController)

**Files:**
- Modify: `app/Http/Controllers/VerifikatorVisitor/VerifikatorVisitorController.php` (add 2 new methods)
- Modify: `routes/VerifikatorVisitor/VerifikatorVisitorWeb.php`

**Interfaces:**
- Consumes: `VisitorEfektifService::revokePermitEfektif()`, `::revokeTamuEfektif()` (Task 4)
- Produces: `POST /verifikator-permit/{noTransaksi}/revoke`, `POST /verifikator-tamu/{noTransaksi}/revoke`

- [ ] **Step 1: Add `revokePermit()` after `rejectPermit()`**

Insert after the existing `rejectPermit()` method (currently ending around line 199):

```php
    public function revokePermit(Request $request, string $noTransaksi)
    {
        $request->validate(['alasan' => 'required|string|max:500']);

        try {
            $olehKode = $this->visitorApprovalPolicy->requesterKode($request->user()) ?? '';

            $this->visitorEfektif->revokePermitEfektif($noTransaksi, $request->input('alasan'), $olehKode);

            return ResponseHelper::success(null, 'Permit yang sudah disetujui berhasil dibatalkan.');
        } catch (\Exception $e) {
            return ResponseHelper::error('Gagal membatalkan permit: ' . $e->getMessage());
        }
    }
```

- [ ] **Step 2: Add `revokeTamu()` after `rejectTamu()`**

Insert after the existing `rejectTamu()` method (currently ending around line 348):

```php
    public function revokeTamu(Request $request, string $noTransaksi)
    {
        $request->validate(['alasan' => 'required|string|max:500']);

        try {
            $olehKode = $this->visitorApprovalPolicy->requesterKode($request->user()) ?? '';

            $this->visitorEfektif->revokeTamuEfektif($noTransaksi, $request->input('alasan'), $olehKode);

            return ResponseHelper::success(null, 'Kunjungan tamu yang sudah disetujui berhasil dibatalkan.');
        } catch (\Exception $e) {
            return ResponseHelper::error('Gagal membatalkan kunjungan tamu: ' . $e->getMessage());
        }
    }
```

- [ ] **Step 3: Add the two routes**

In `routes/VerifikatorVisitor/VerifikatorVisitorWeb.php`, inside the existing `Route::middleware(['auth', 'check.jabatan:visitorVerifikatorPage'])->group(function () { ... })` block, add:

```php
    Route::post('verifikator-permit/{noTransaksi}/revoke', [VerifikatorVisitorController::class, 'revokePermit']);
    Route::post('verifikator-tamu/{noTransaksi}/revoke', [VerifikatorVisitorController::class, 'revokeTamu']);
```

- [ ] **Step 4: Verify routes registered and behavior via tinker**

```bash
# shell verification (not tinker)
php artisan route:clear
php artisan route:list --path=verifikator-permit
php artisan route:list --path=verifikator-tamu
```

Expected: both new `POST .../revoke` routes appear, with middleware `App\Http\Middleware\CheckJabatan:visitorVerifikatorPage`.

```php
// tinker verification — full round trip
$svc = app(\App\Services\Visitor\VisitorEfektifService::class);
$svc->syncPermitEfektif('PMT0426-0001'); // reset to AKTIF baseline

$controller = app(\App\Http\Controllers\VerifikatorVisitor\VerifikatorVisitorController::class);
$request = \Illuminate\Http\Request::create('/verifikator-permit/PMT0426-0001/revoke', 'POST', ['alasan' => 'Karyawan resign']);
$request->setUserResolver(fn () => \App\Models\User::where('Id_Users', 4)->first());

$response = $controller->revokePermit($request, 'PMT0426-0001');
echo json_encode($response->getData()) . "\n";

$row = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', 'PMT0426-0001')->first();
echo "Status_Efektif: {$row->Status_Efektif}, Alasan_Status: {$row->Alasan_Status}\n";
```

Expected: `Status_Efektif` = `DIBATALKAN`, `Alasan_Status` = `Karyawan resign`.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/VerifikatorVisitor/VerifikatorVisitorController.php routes/VerifikatorVisitor/VerifikatorVisitorWeb.php
git commit -m "feat(visitor): add admin revoke endpoints for approved permit/tamu"
```

---

### Task 10: Scheduled command — expire Efektif rows

**Files:**
- Create: `app/Console/Commands/ExpireVisitorEfektif.php`

**Interfaces:**
- Consumes: nothing beyond `DB::table()` — this is a standalone command, no service dependency (deliberately simple, matches spec's "only touch AKTIF rows" rule).
- Note: the `N_HRIS_Scheduler_Configs` row that wires this command's cron schedule was already inserted by Task 1's DDL (`visitor:expire-efektif`, daily 00:05 Asia/Jakarta) — no `Kernel.php` edit needed, this project's scheduler is DB-driven (see `app/Console/Kernel.php`).

- [ ] **Step 1: Write the command**

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Set Status_Efektif='EXPIRED' pada baris N_VISITOR_Permit_Efektif / N_VISITOR_Tamu_Efektif
 * yang tanggal berlakunya sudah lewat, dan reset Flag_Sync='T' supaya aplikasi puller
 * menarik ulang status barunya. HANYA menyentuh baris yang masih 'AKTIF' - mencegah
 * race condition menimpa baris yang baru saja di-DIBATALKAN.
 */
class ExpireVisitorEfektif extends Command
{
    protected $signature = 'visitor:expire-efektif';

    protected $description = 'Expire proyeksi Efektif visitor (permit lewat Tanggal_Kadaluarsa, tamu lewat Tanggal_Kunjungan)';

    public function handle(): int
    {
        $now = now();
        $today = $now->toDateString();

        $permitCount = DB::table('N_VISITOR_Permit_Efektif')
            ->where('Status_Efektif', 'AKTIF')
            ->whereDate('Tanggal_Kadaluarsa', '<', $today)
            ->update([
                'Status_Efektif' => 'EXPIRED',
                'Updated_At' => $now,
                'Updated_Oleh_Nama' => 'Sistem (Auto-Expire)',
                'Flag_Sync' => 'T',
            ]);

        $tamuCount = DB::table('N_VISITOR_Tamu_Efektif')
            ->where('Status_Efektif', 'AKTIF')
            ->whereDate('Tanggal_Kunjungan', '<', $today)
            ->update([
                'Status_Efektif' => 'EXPIRED',
                'Updated_At' => $now,
                'Updated_Oleh_Nama' => 'Sistem (Auto-Expire)',
                'Flag_Sync' => 'T',
            ]);

        $this->info("Permit expired: {$permitCount}, Tamu expired: {$tamuCount}");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 2: Verify with tinker**

```php
// tinker verification — force an AKTIF row into the past, then run the command
$svc = app(\App\Services\Visitor\VisitorEfektifService::class);
$svc->syncPermitEfektif('PMT0426-0001');
DB::table('N_VISITOR_Permit_Efektif')
    ->where('No_Transaksi', 'PMT0426-0001')
    ->update(['Tanggal_Kadaluarsa' => now()->subDay()->toDateString(), 'Status_Efektif' => 'AKTIF', 'Flag_Sync' => 'Y']);

\Illuminate\Support\Facades\Artisan::call('visitor:expire-efektif');
echo \Illuminate\Support\Facades\Artisan::output();

$row = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', 'PMT0426-0001')->first();
echo "Status_Efektif: {$row->Status_Efektif}, Flag_Sync: {$row->Flag_Sync}\n"; // EXPIRED, T

// Confirm it does NOT touch a DIBATALKAN row
DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', 'PMT0426-0001')->update(['Status_Efektif' => 'DIBATALKAN', 'Flag_Sync' => 'Y']);
\Illuminate\Support\Facades\Artisan::call('visitor:expire-efektif');
$row2 = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', 'PMT0426-0001')->first();
echo "Masih DIBATALKAN (tidak ditimpa): " . ($row2->Status_Efektif === 'DIBATALKAN' ? 'YA' : 'BUG: ' . $row2->Status_Efektif) . "\n";
```

Expected: first run flips `AKTIF` → `EXPIRED` with `Flag_Sync='T'`; second run leaves a `DIBATALKAN` row untouched.

- [ ] **Step 3: Commit**

```bash
git add app/Console/Commands/ExpireVisitorEfektif.php
git commit -m "feat(visitor): add scheduled command to expire Efektif projection rows"
```

---

## Post-Implementation Checklist

- [ ] Confirm `php artisan route:list --path=verifikator` shows both new revoke routes with correct middleware
- [ ] Confirm `N_HRIS_Scheduler_Configs` has the `visitor:expire-efektif` row with `Flag_Aktif='Y'` (inserted by Task 1's DDL)
- [ ] Manually walk through the full lifecycle once end-to-end on the dev DB: submit a test permit → approve → confirm Efektif row AKTIF → revoke via admin endpoint → confirm DIBATALKAN → confirm `Flag_Sync` reset to `T` at each transition
- [ ] Ask the user whether they want the two new routes' revoke actions surfaced in the `VerifikatorPermitPanel.vue`/`VerifikatorTamuPanel.vue` UI (out of scope for this plan — spec only covers the backend contract, no frontend button was requested)
