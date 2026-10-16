# Tipe Tamu (REGULAR vs B2B) dan Penentuan Permit COKLAT oleh Approver

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memisahkan registrasi tamu menjadi **Tamu Regular** (tanpa permit COKLAT) dan **Tamu B2B** (membutuhkan permit COKLAT). Pengguna saat mendaftar tidak lagi mengisi jam `waktu_mulai_kunjungan` dan `waktu_selesai_kunjungan` (cukup `tanggal_kunjungan`). Masa berlaku jam permit COKLAT untuk Tamu B2B ditentukan oleh **Approver/Verifikator** saat menyetujui pengajuan di meja persetujuan.

**Architecture:** 
1. `Tipe_Tamu` (`varchar(20)`) ditambahkan di tabel transaksi detail dan proyeksi tamu efektif (`'REGULAR'` vs `'B2B'`).
2. Permit COKLAT hanya diterbitkan oleh `VisitorEfektifService::syncPermitCoklatEfektif()` untuk tamu bertipe `'B2B'` yang sudah memiliki jendela jam `Waktu_Mulai_Kunjungan` dan `Waktu_Selesai_Kunjungan`.
3. Pada pengajuan tamu, jam dari/sampai dihilangkan dari isian pengguna. Pada persetujuan Tamu B2B, `VerifikatorVisitorController::approveTamu` & `VisitorApprovalDecisionService::approveTamu` menerima parameter `waktu_mulai_kunjungan` & `waktu_selesai_kunjungan` yang diinput oleh Approver.

**Tech Stack:** Laravel (query builder `DB::table()`), SQL Server 2022 (`Web_HRIS`), PHPUnit, Vue 3 Options API + Vitest.

---

## Global Constraints

- **Change ID:** `DDL-2026-101-visitor-tipe-tamu-permit-approver`.
- **DDL Artifacts:** `database/ddl/DDL-2026-101-visitor-tipe-tamu-permit-approver/{forward,drop}.sql`, `database/dml/DDL-2026-101-visitor-tipe-tamu-permit-approver/data-change.sql`, entri wajib di `database/ddl/status.json`, dokumentasi di `docs/changes/DDL-2026-101-visitor-tipe-tamu-permit-approver/README.md`.
- **Backfill DML:** Mengisi `Tipe_Tamu = 'REGULAR'` untuk seluruh baris lama di `N_EMI_VISITOR_Transaksi_Tamu_Detail` dan `N_VISITOR_Tamu_Efektif`.
- **Database:** Hanya `Web_HRIS` (development).
- **Aturan Proyek:** Tulis seluruh test terlebih dahulu. Jangan jalankan test/lint/build/penjaga sampai user memberi ACC eksplisit. Jangan push atau buka PR.

---

### Task 1: Skema DDL & DML — kolom `Tipe_Tamu`

**Files:**
- Create: `database/ddl/DDL-2026-101-visitor-tipe-tamu-permit-approver/forward.sql`
- Create: `database/ddl/DDL-2026-101-visitor-tipe-tamu-permit-approver/drop.sql`
- Create: `database/dml/DDL-2026-101-visitor-tipe-tamu-permit-approver/data-change.sql`
- Create: `docs/changes/DDL-2026-101-visitor-tipe-tamu-permit-approver/README.md`
- Modify: `database/ddl/status.json`

**Interfaces:**
- Consumes: —
- Produces: Kolom `Tipe_Tamu varchar(20) NULL` + `CHECK IN ('REGULAR','B2B')` pada `N_EMI_VISITOR_Transaksi_Tamu_Detail` dan `N_VISITOR_Tamu_Efektif`.

- [ ] **Step 1: Tulis `forward.sql`**

```sql
-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-101-visitor-tipe-tamu-permit-approver

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Tipe_Tamu') IS NULL
BEGIN
    ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail ADD Tipe_Tamu varchar(20) NULL CONSTRAINT DF_N_EMI_VISITOR_Transaksi_Tamu_Detail_Tipe_Tamu DEFAULT 'REGULAR';
END
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE name = 'CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Tipe_Tamu'
)
BEGIN
    ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail
        ADD CONSTRAINT CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Tipe_Tamu
            CHECK (Tipe_Tamu IS NULL OR Tipe_Tamu IN ('REGULAR', 'B2B'));
END
GO

IF COL_LENGTH('N_VISITOR_Tamu_Efektif', 'Tipe_Tamu') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Tamu_Efektif ADD Tipe_Tamu varchar(20) NULL CONSTRAINT DF_N_VISITOR_Tamu_Efektif_Tipe_Tamu DEFAULT 'REGULAR';
END
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE name = 'CK_N_VISITOR_Tamu_Efektif_Tipe_Tamu'
)
BEGIN
    ALTER TABLE N_VISITOR_Tamu_Efektif
        ADD CONSTRAINT CK_N_VISITOR_Tamu_Efektif_Tipe_Tamu
            CHECK (Tipe_Tamu IS NULL OR Tipe_Tamu IN ('REGULAR', 'B2B'));
END
GO
```

- [ ] **Step 2: Tulis `drop.sql`**

```sql
-- Change ID: DDL-2026-101-visitor-tipe-tamu-permit-approver
-- ALAT BANTU RESET MANUAL. TIDAK PERNAH DIJALANKAN AGENT.

ALTER TABLE N_VISITOR_Tamu_Efektif DROP CONSTRAINT IF EXISTS CK_N_VISITOR_Tamu_Efektif_Tipe_Tamu;
ALTER TABLE N_VISITOR_Tamu_Efektif DROP CONSTRAINT IF EXISTS DF_N_VISITOR_Tamu_Efektif_Tipe_Tamu;
GO
ALTER TABLE N_VISITOR_Tamu_Efektif DROP COLUMN IF EXISTS Tipe_Tamu;
GO

ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail DROP CONSTRAINT IF EXISTS CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Tipe_Tamu;
ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail DROP CONSTRAINT IF EXISTS DF_N_EMI_VISITOR_Transaksi_Tamu_Detail_Tipe_Tamu;
GO
ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail DROP COLUMN IF EXISTS Tipe_Tamu;
GO
```

- [ ] **Step 3: Tulis `data-change.sql`**

```sql
-- DDL-2026-101-visitor-tipe-tamu-permit-approver
-- Target: Web_HRIS.
-- Backfill baris lama menjadi 'REGULAR'

UPDATE N_EMI_VISITOR_Transaksi_Tamu_Detail
   SET Tipe_Tamu = 'REGULAR'
 WHERE Tipe_Tamu IS NULL;
GO

UPDATE N_VISITOR_Tamu_Efektif
   SET Tipe_Tamu = 'REGULAR'
 WHERE Tipe_Tamu IS NULL;
GO
```

- [ ] **Step 4: Tulis `README.md` dan perbarui `status.json`**

Tulis dokumentasi perubahan di `docs/changes/DDL-2026-101-visitor-tipe-tamu-permit-approver/README.md` dan tambahkan entri `DDL-2026-101-visitor-tipe-tamu-permit-approver` ke `database/ddl/status.json`.

---

### Task 2: Backend — Tipe Tamu pada Registrasi & Query Services

**Files:**
- Modify: `app/Http/Controllers/VISITOR/Guest/Visit/GuestRegistrationController.php` — simpan `tipe_tamu`, hilangkan kewajiban jam
- Modify: `app/Services/VISITOR/Permit/VehiclePermit/VisitorPermitQueryService.php` — select `d.Tipe_Tamu`
- Modify: `app/Services/VISITOR/Operations/Expiration/VisitorEfektifService.php` — `syncTamuEfektif()` bawa `Tipe_Tamu`, `syncPermitCoklatEfektif()` hanya untuk B2B
- Test: `tests/Integration/VISITOR/Guest/Visit/TipeTamuRegistrasiTest.php`

**Interfaces:**
- Consumes: Skema `Tipe_Tamu` dari Task 1.
- Produces: `tipe_tamu` (`REGULAR` / `B2B`) tersimpan pada transaksi dan proyeksi; jam kunjungan pada registrasi awal menjadi opsional.

- [ ] **Step 1: Tulis tes yang gagal `TipeTamuRegistrasiTest.php`**

```php
<?php

namespace Tests\Integration\VISITOR\Guest\Visit;

use App\Http\Controllers\VISITOR\Guest\Visit\GuestRegistrationController;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TipeTamuRegistrasiTest extends TestCase
{
    use DatabaseTransactions;

    private const PIC = 'A1';

    public function test_registrasi_tamu_regular_tanpa_jam_dan_tanpa_permit_coklat(): void
    {
        $nomor = $this->daftarkan([
            'nama_tamu' => 'Tamu Regular',
            'tipe_tamu' => 'REGULAR',
            'tanggal_kunjungan' => '2026-09-20',
        ]);

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->first();
        $this->assertSame('REGULAR', $detail->Tipe_Tamu);

        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);
        app(VisitorEfektifService::class)->syncPermitCoklatEfektif($nomor);

        $permit = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $nomor)->first();
        $this->assertNull($permit, 'Tamu Regular tidak boleh menerbitkan permit COKLAT.');
    }

    public function test_registrasi_tamu_b2b_tersimpan_dengan_tipe_b2b(): void
    {
        $nomor = $this->daftarkan([
            'nama_tamu' => 'Tamu B2B Mitra',
            'tipe_tamu' => 'B2B',
            'tanggal_kunjungan' => '2026-09-20',
        ]);

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->first();
        $this->assertSame('B2B', $detail->Tipe_Tamu);
    }

    private function daftarkan(array $ubah): string
    {
        $payload = array_merge([
            'nama_tamu' => 'Tamu Uji',
            'keperluan' => 'Meeting',
            'pic_tujuan' => 'HRD',
            'tanggal_kunjungan' => '2026-09-20',
            'instansi_asal' => 'PT Uji',
            'jenis_kendaraan' => 'Tidak Berkendara',
            'daftar_anggota' => [],
        ], $ubah);

        $request = Request::create('/tamu', 'POST', $payload);
        $request->headers->set('Idempotency-Key', 'TPT-'.Str::upper(Str::random(20)));
        $request->setUserResolver(fn () => (object) ['Kode_Karyawan' => self::PIC]);

        $isi = json_decode(app(GuestRegistrationController::class)->store($request)->getContent(), true);

        return $isi['result']['no_transaksi'];
    }
}
```

- [ ] **Step 2: Update `GuestRegistrationController::store()` & `$request->validate()`**

Ubah aturan validasi `store()` di `GuestRegistrationController.php`:
- `'tipe_tamu' => 'required|in:REGULAR,B2B'` (default `'REGULAR'`).
- `'waktu_mulai_kunjungan' => 'nullable|date'`
- `'waktu_selesai_kunjungan' => 'nullable|date|after:waktu_mulai_kunjungan'`

Di insert `N_EMI_VISITOR_Transaksi_Tamu_Detail`:
- `'Tipe_Tamu' => $request->input('tipe_tamu', 'REGULAR'),`

- [ ] **Step 3: Update `baseTamuQuery()` & `VisitorEfektifService`**

Di `VisitorPermitQueryService::baseTamuQuery()` & `GuestRegistrationController::baseTamuQuery()`:
- Tambahkan `'d.Tipe_Tamu',` pada klausa `select()`.

Di `VisitorEfektifService::syncTamuEfektif()`:
- Tambahkan `'Tipe_Tamu' => $header->Tipe_Tamu ?? 'REGULAR',` pada array `$row`.

Di `VisitorEfektifService::syncPermitCoklatEfektif()`:
- Tambahkan pengecekan:
```php
if (($header->Tipe_Tamu ?? 'REGULAR') !== 'B2B') {
    return; // Permit COKLAT hanya untuk Tamu B2B
}
```

---

### Task 3: Approval & Decision — Penentuan Jam Permit COKLAT B2B oleh Approver

**Files:**
- Modify: `app/Http/Requests/VISITOR/Workflow/Approval/VisitorApproveRequest.php` — terima `waktu_mulai_kunjungan` & `waktu_selesai_kunjungan`
- Modify: `app/Services/VISITOR/Workflow/Approval/VisitorApprovalDecisionService.php` — `approveTamu()` update jam B2B sebelum finalisasi
- Modify: `app/Http/Controllers/VISITOR/Workflow/Verification/VerifikatorVisitorController.php` — teruskan jam dari request ke `approveTamu()`
- Test: `tests/Integration/VISITOR/Workflow/Approval/ApprovalTamuB2BPermitTest.php`

**Interfaces:**
- Consumes: `approveTamu($noTransaksi, $approverKode, $catatan, $waktuMulai, $waktuSelesai)`.
- Produces: Approver menentukan jam berlaku permit COKLAT saat menyetujui Tamu B2B; permit COKLAT diterbitkan dengan jam tersebut.

- [ ] **Step 1: Tulis tes yang gagal `ApprovalTamuB2BPermitTest.php`**

```php
<?php

namespace Tests\Integration\VISITOR\Workflow\Approval;

use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Workflow\Approval\VisitorApprovalDecisionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApprovalTamuB2BPermitTest extends TestCase
{
    use DatabaseTransactions;

    private const PIC = 'A1';

    public function test_approver_menetapkan_jam_permit_b2b_saat_approve(): void
    {
        $nomor = $this->buatTamuB2BPending();

        app(VisitorApprovalDecisionService::class)->approveTamu(
            $nomor,
            self::PIC,
            'Disetujui dengan permit jam 08-17',
            '2026-09-20 08:00:00',
            '2026-09-20 17:00:00',
        );

        $permit = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $nomor)->first();

        $this->assertNotNull($permit, 'Permit COKLAT B2B harus terbit setelah disetujui.');
        $this->assertSame('2026-09-20 08:00:00', substr((string) $permit->Waktu_Mulai_Berlaku, 0, 19));
        $this->assertSame('2026-09-20 17:00:00', substr((string) $permit->Waktu_Berakhir, 0, 19));
    }

    private function buatTamuB2BPending(): string
    {
        $nomor = 'VSR-B2B-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_PIC' => self::PIC,
            'Status_Kunjungan' => 'MENUNGGU_APPROVAL',
            'Needs_Approval' => 'Y',
            'Flag_Approval' => null,
            'Tanggal_Kunjungan' => '2026-09-20',
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Idempotency_Key' => 'B2B-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id,
            'No_Transaksi' => $nomor,
            'Nama_Lengkap_Tamu' => 'Tamu B2B Mitra',
            'Tipe_Tamu' => 'B2B',
            'Instansi_Atau_Asal' => 'PT Mitra B2B',
            'Deksripsi_Keperluan' => 'Meeting Bisnis',
            'Jumlah_Tamu' => 1,
            'Jenis_Kendaraan' => 'Tidak Berkendara',
        ]);

        return $nomor;
    }
}
```

- [ ] **Step 2: Update `VisitorApproveRequest.php`**

Tambahkan aturan validasi:
```php
'waktu_mulai_kunjungan' => ['nullable', 'date'],
'waktu_selesai_kunjungan' => ['nullable', 'date', 'after:waktu_mulai_kunjungan'],
```

- [ ] **Step 3: Update `VisitorApprovalDecisionService::approveTamu()`**

Ubah signature & implementasi:
```php
public function approveTamu(
    string $noTransaksi,
    string $approverKode,
    ?string $catatan = null,
    ?string $waktuMulai = null,
    ?string $waktuSelesai = null,
): bool
```

Di dalam transaksi approval, jika `$waktuMulai` & `$waktuSelesai` diberikan:
```php
if ($waktuMulai && $waktuSelesai) {
    DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
        ->where('No_Transaksi', $noTransaksi)
        ->update([
            'Waktu_Mulai_Kunjungan' => $waktuMulai,
            'Waktu_Selesai_Kunjungan' => $waktuSelesai,
        ]);
}
```

---

### Task 4: Frontend UI — Formulir Registrasi (`TamuForm.vue`) & Verification Modal (`TamuReviewModal.vue`)

**Files:**
- Modify: `resources/js/Pages/VISITOR/Guest/Visit/components/TamuForm.vue` — tambah pilihan Tipe Tamu, hapus isian jam
- Modify: `resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/TamuReviewModal.vue` — tampilkan Tipe Tamu & tambahkan input Jam Permit COKLAT untuk B2B saat Approve
- Test: `resources/js/Pages/VISITOR/Guest/Visit/components/TamuForm.test.js`
- Test: `resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/components/TamuReviewModal.test.js`

- [ ] **Step 1: Update `TamuForm.vue`**
- Di `defaultForm()`: tambah `tipe_tamu: 'REGULAR'`, hapus `jam_mulai`, `tanggal_selesai`, `jam_selesai`.
- Tambahkan pilihan Tipe Tamu (Regular / B2B) menggunakan `HcisSelect` atau Radio Pill pada bagian Identitas Tamu.
- Di `completionItems`: hapus entri `Jendela jam kunjungan`.
- Di `buildPayload()`: sertakan `tipe_tamu: this.form.tipe_tamu`. Hapus `waktu_mulai_kunjungan` dan `waktu_selesai_kunjungan`.

- [ ] **Step 2: Update `TamuReviewModal.vue`**
- Tampilkan badge `Tipe_Tamu` (`REGULAR` vs `B2B`).
- Saat persetujuan (Approve) untuk Tamu B2B, berikan opsi/modal input `Waktu Mulai` & `Waktu Selesai` permit COKLAT sebelum memanggil API approve.

---

## Verifikasi & Penyelesaian Plan

- Setelah seluruh kode dan tes ditulis, minta konfirmasi pengguna untuk eksekusi DDL & pengujian suite.
