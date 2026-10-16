# Permit Coklat (Visitor) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Setiap tamu yang didaftarkan otomatis menerbitkan satu permit akses berkategori `COKLAT` yang berlaku selama jendela jam kunjungan, dan berakhir pada mana yang lebih dulu antara jam selesai atau tamu tercatat keluar.

**Architecture:** Permit coklat adalah **turunan** dari registrasi tamu, bukan pengajuan tersendiri. Ia lahir sebagai satu baris di proyeksi `N_VISITOR_Permit_Efektif` yang memakai `No_Transaksi` tamunya sendiri (`VSR-…`) sebagai kunci, ditulis hanya oleh `VisitorEfektifService`. Kolom waktu ditambahkan secara **aditif** — kolom `date` lama tetap diisi turunan tanggalnya — supaya aplikasi gate pihak ketiga yang menarik tabel itu langsung lewat SQL Server tidak perlu dirilis bersamaan.

**Tech Stack:** Laravel (query builder, bukan Eloquent — modul Visitor memakai `DB::table()` seluruhnya), SQL Server (`Web_HRIS`), PHPUnit (`Tests\TestCase` + `DatabaseTransactions`, menembak database development sungguhan), Vue 3 Options API + Vitest untuk berkas `.test.js` bersebelahan.

**Spec:** `docs/superpowers/specs/2026-09-08-visitor-permit-coklat-design.md`

## Global Constraints

- **Change ID DDL:** `DDL-2026-077-visitor-permit-coklat`. DDL di `database/ddl/DDL-2026-077-visitor-permit-coklat/`, DML di `database/dml/DDL-2026-077-visitor-permit-coklat/` — DDL dan DML tidak dicampur dalam satu berkas.
- **Database:** hanya `Web_HRIS` (development). Agent tidak pernah mengeksekusi apa pun ke production. `drop.sql` dibuat tetapi tidak pernah dijalankan agent.
- **Nilai enum `Status_Efektif` tidak boleh bertambah.** Tetap `AKTIF` / `DIBATALKAN` / `EXPIRED`. Sebab berakhirnya dibedakan lewat `Alasan_Status`.
- **Invarian kontrak gate:** setiap kali `Waktu_Mulai_Berlaku`/`Waktu_Berakhir` diisi, `Tanggal_Mulai_Berlaku`/`Tanggal_Kadaluarsa` **wajib** ikut terisi sebagai turunan tanggalnya. Aturan ini hidup **hanya** di `VisitorEfektifService`, tidak pernah disalin ke pemanggil.
- **Penulis tunggal:** hanya `VisitorEfektifService` yang boleh menulis `N_VISITOR_Permit_Efektif` dan `N_VISITOR_Tamu_Efektif`.
- **Kehadiran tidak pernah ditulis HCIS.** Check-out dibaca lewat `COALESCE(e.Waktu_Aktual_Keluar, d.Waktu_Aktual_Keluar)`, **tidak pernah** lewat `Status_Kunjungan`.
- **Penyaring `Sumber_Permit` wajib `ISNULL(Sumber_Permit,'KARYAWAN') <> 'TAMU'`**, bukan `<> 'TAMU'` telanjang — kolomnya nullable dan perbandingan terhadap `NULL` menghasilkan `UNKNOWN`.
- **Permit karyawan tidak boleh berubah perilakunya.** Penjaga `MONTH` di `VisitorApprovalDecisionService::resolveDuration()` dan `PermitValidityPeriodCalculator` dibiarkan utuh.
- **Nilai `Deskripsi_Akses` untuk kategori COKLAT adalah input user** (lihat Task 1, Step 2). Jangan dikarang; hentikan task itu dan tanyakan bila belum ada.
- **Kapan tes dijalankan:** user proyek ini meminta kode dan tes ditulis sampai selesai lebih dulu, dan suite baru dijalankan setelah user ACC. Konfirmasikan di awal eksekusi apakah aturan itu berlaku untuk rencana ini; kalau ya, langkah "Run test" tetap ditulis tetapi dieksekusi berkelompok setelah ACC, bukan per task.
- **Penjaga baru menyentuh service bersama**, jadi verifikasi akhir menjalankan suite penuh, bukan hanya berkas yang berubah.

---

### Task 1: Skema dan master kategori COKLAT

**Files:**
- Create: `database/ddl/DDL-2026-077-visitor-permit-coklat/forward.sql`
- Create: `database/ddl/DDL-2026-077-visitor-permit-coklat/drop.sql`
- Create: `database/dml/DDL-2026-077-visitor-permit-coklat/data-change.sql`
- Create: `docs/changes/DDL-2026-077-visitor-permit-coklat/README.md`
- Modify: `database/ddl/status.json`

**Interfaces:**
- Consumes: —
- Produces: kolom `N_EMI_VISITOR_Transaksi_Tamu_Detail.Waktu_Mulai_Kunjungan`, `.Waktu_Selesai_Kunjungan`; `N_VISITOR_Permit_Efektif.Waktu_Mulai_Berlaku`, `.Waktu_Berakhir`, `.Sumber_Permit`; satu baris master `Nama_Kategori='COKLAT'`.

> **Catatan:** `N_EMI_VISITOR_Transaksi_Permit_Detail` sengaja TIDAK diberi kolom waktu. Permit coklat tidak pernah membuat baris transaksi permit — ia lahir langsung sebagai proyeksi dari registrasi tamu. Kolom di sana tidak akan pernah ditulis siapa pun.

- [ ] **Step 1: Laporkan antrean DDL yang menggantung**

`AGENTS.md` mewajibkan ini sebelum membuat change baru. Jalankan dan tempelkan hasilnya ke ringkasan task:

```bash
php -r '$j=json_decode(file_get_contents("database/ddl/status.json"),true); foreach(($j["changes"]??$j) as $k=>$v){ $s=is_array($v)?($v["sql_tracker"]["status"]??$v["status"]??"?"):$v; $id=is_array($v)?($v["change_id"]??$k):$k; if(!in_array($s,["TRACKER_RECORDED","PRODUCTION_EXECUTED","CANCELLED"],true)) echo "$id => $s\n"; }'
```

Per 2026-09-08 yang menggantung: `DDL-2026-061`, `DDL-2026-062`, `DDL-2026-063-visitor-efektif-flag-batal`, `DDL-2026-067` (`REVIEW_REQUIRED`) dan `DDL-2026-066` (`TRACKER_PENDING`). Tidak memblokir, tapi wajib dilaporkan.

- [ ] **Step 2: Minta nilai `Deskripsi_Akses` untuk COKLAT ke user**

HIJAU = `Akses hanya sampai Area Parkir`, MERAH = `Akses penuh sampai Area Boiler`. Nilai ini dibaca aplikasi gate untuk menentukan area. **Berhenti dan tanyakan.** Jangan lanjut ke Step 3 tanpa jawabannya.

- [ ] **Step 3: Tulis `forward.sql`**

```sql
-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-077-visitor-permit-coklat
--
-- Permit COKLAT adalah izin akses tamu yang berlaku dalam hitungan JAM, terbit
-- otomatis dari registrasi tamu. Seluruh kolom masa berlaku permit yang ada
-- bertipe `date`, sehingga jam yang ditulis ke sana hilang tanpa galat.
--
-- Kolom waktu di bawah ditambahkan secara ADITIF: kolom `date` yang lama TETAP
-- diisi turunan tanggalnya oleh VisitorEfektifService. Aplikasi gate pihak
-- ketiga yang menarik N_VISITOR_Permit_Efektif langsung lewat koneksi SQL
-- Server karena itu tetap membaca permit coklat walau belum diperbarui — hanya
-- di ketelitian hari. Tanpa keputusan ini, fitur ini menuntut rilis serentak
-- dengan aplikasi di luar repo ini.
--
-- Semua kolom NULL: baris lama tidak punya jendela jam dan tidak boleh dipaksa
-- mengarang satu. Kewajiban mengisi ditegakkan di validasi pengajuan baru.
--
-- Seluruh pernyataan idempoten.

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Waktu_Mulai_Kunjungan') IS NULL
ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail ADD Waktu_Mulai_Kunjungan datetime2(0) NULL;
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Waktu_Selesai_Kunjungan') IS NULL
ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail ADD Waktu_Selesai_Kunjungan datetime2(0) NULL;
GO

IF COL_LENGTH('N_VISITOR_Permit_Efektif', 'Waktu_Mulai_Berlaku') IS NULL
ALTER TABLE N_VISITOR_Permit_Efektif ADD Waktu_Mulai_Berlaku datetime2(0) NULL;
GO

IF COL_LENGTH('N_VISITOR_Permit_Efektif', 'Waktu_Berakhir') IS NULL
ALTER TABLE N_VISITOR_Permit_Efektif ADD Waktu_Berakhir datetime2(0) NULL;
GO

-- 'KARYAWAN' / 'TAMU'. Nullable, dan baris lama di-backfill lewat data-change.sql.
-- Setiap penyaring WAJIB memakai ISNULL(Sumber_Permit,'KARYAWAN'): perbandingan
-- langsung terhadap NULL menghasilkan UNKNOWN, dan baris yang belum ter-backfill
-- akan diam-diam lolos dari sapuan kedaluwarsa.
IF COL_LENGTH('N_VISITOR_Permit_Efektif', 'Sumber_Permit') IS NULL
ALTER TABLE N_VISITOR_Permit_Efektif ADD Sumber_Permit varchar(10) NULL;
GO

-- Sapuan kedaluwarsa berjalan tiap 5 menit dan menyaring permit tamu yang masih
-- AKTIF berdasarkan Waktu_Berakhir. Tanpa indeks ini sapuan itu memindai seluruh
-- tabel setiap 5 menit.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_VISITOR_Permit_Efektif_Sumber_Waktu_Berakhir')
CREATE NONCLUSTERED INDEX IX_VISITOR_Permit_Efektif_Sumber_Waktu_Berakhir
    ON N_VISITOR_Permit_Efektif (Status_Efektif, Waktu_Berakhir)
    INCLUDE (No_Transaksi, Sumber_Permit);
GO
```

- [ ] **Step 4: Tulis `drop.sql`**

```sql
-- Change ID: DDL-2026-077-visitor-permit-coklat
-- ALAT BANTU RESET MANUAL. TIDAK PERNAH DIJALANKAN AGENT.

DROP INDEX IF EXISTS IX_VISITOR_Permit_Efektif_Sumber_Waktu_Berakhir ON N_VISITOR_Permit_Efektif;
GO
ALTER TABLE N_VISITOR_Permit_Efektif DROP COLUMN IF EXISTS Sumber_Permit, Waktu_Berakhir, Waktu_Mulai_Berlaku;
GO
ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail DROP COLUMN IF EXISTS Waktu_Selesai_Kunjungan, Waktu_Mulai_Kunjungan;
GO
```

- [ ] **Step 5: Tulis `data-change.sql`**

Ganti `<<DESKRIPSI_AKSES>>` dengan jawaban Step 2. Kalau masih kosong, berhenti.

```sql
-- Change ID: DDL-2026-077-visitor-permit-coklat (DML)
-- Target: Web_HRIS (development).
--
-- 1) Kategori COKLAT. Dipakai setiap permit tamu; Deskripsi_Akses dibaca gate
--    untuk menentukan area yang boleh dimasuki.
-- 2) Backfill Sumber_Permit baris permit yang sudah ada. Wajib dijalankan
--    bersama forward.sql: penyaring sapuan memang sudah dilindungi ISNULL,
--    tetapi laporan yang memisah per sumber akan salah tanpa backfill ini.
-- Keduanya idempoten.

IF NOT EXISTS (SELECT 1 FROM N_EMI_VISITOR_Master_Kategori_Permit WHERE Nama_Kategori = 'COKLAT')
INSERT INTO N_EMI_VISITOR_Master_Kategori_Permit (Nama_Kategori, Deskripsi_Akses, Kode_Warna_UI, Flag_Aktif)
VALUES ('COKLAT', '<<DESKRIPSI_AKSES>>', '#795548', 'Y');
GO

UPDATE N_VISITOR_Permit_Efektif SET Sumber_Permit = 'KARYAWAN' WHERE Sumber_Permit IS NULL;
GO
```

- [ ] **Step 6: Jalankan forward.sql lalu data-change.sql ke development**

Sebelum eksekusi, tampilkan SQL persisnya dan sebutkan objek sasaran. Tidak ada `DROP`/`TRUNCATE` di sini.

- [ ] **Step 7: Verifikasi hasilnya di database, bukan dari keluaran skrip**

```bash
php artisan tinker --execute="
\$r=DB::select(\"SELECT TABLE_NAME,COLUMN_NAME,DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE (TABLE_NAME='N_EMI_VISITOR_Transaksi_Tamu_Detail' AND COLUMN_NAME LIKE 'Waktu_%_Kunjungan') OR (TABLE_NAME='N_VISITOR_Permit_Efektif' AND COLUMN_NAME IN ('Waktu_Mulai_Berlaku','Waktu_Berakhir','Sumber_Permit'))\");
foreach(\$r as \$x){echo \$x->TABLE_NAME.' | '.\$x->COLUMN_NAME.' | '.\$x->DATA_TYPE.PHP_EOL;}
echo 'COKLAT: '.DB::table('N_EMI_VISITOR_Master_Kategori_Permit')->where('Nama_Kategori','COKLAT')->count().PHP_EOL;
echo 'Sumber_Permit NULL tersisa: '.DB::table('N_VISITOR_Permit_Efektif')->whereNull('Sumber_Permit')->count().PHP_EOL;"
```

Harapan: 5 baris kolom, `COKLAT: 1`, `Sumber_Permit NULL tersisa: 0`.

- [ ] **Step 8: Tulis `docs/changes/DDL-2026-077-visitor-permit-coklat/README.md`**

Isi wajib menurut `AGENTS.md`: change ID, database sasaran, deskripsi, objek terdampak, path DDL/DML/dokumentasi, checksum forward-DDL, status saat ini, pekerjaan yang sudah diselesaikan agent, dan pekerjaan yang tersisa untuk user. Ambil checksum dengan:

```bash
sha256sum database/ddl/DDL-2026-077-visitor-permit-coklat/forward.sql
```

- [ ] **Step 9: Tambahkan entri ke `database/ddl/status.json`**

Ikuti bentuk entri yang sudah ada (lihat `DDL-2026-066-sop-indeks-performa-gabungan` sebagai contoh terlengkap):

```json
{
  "change_id": "DDL-2026-077-visitor-permit-coklat",
  "type": "DDL",
  "description": "Kolom jendela jam permit/tamu + Sumber_Permit untuk permit COKLAT",
  "artifact": "database/ddl/DDL-2026-077-visitor-permit-coklat/forward.sql",
  "documentation": "docs/changes/DDL-2026-077-visitor-permit-coklat/README.md",
  "checksum_sha256": "<<hasil Step 8>>",
  "development": { "status": "DEV_EXECUTED", "database": "Web_HRIS", "result": "<<hasil Step 7>>" },
  "sql_tracker": { "status": "TRACKER_PENDING", "reference": null },
  "production": { "status": "PENDING" },
  "verification": { "status": "DEV_DONE", "production_verified": false }
}
```

- [ ] **Step 10: Commit**

```bash
git add database/ddl/DDL-2026-077-visitor-permit-coklat database/dml/DDL-2026-077-visitor-permit-coklat docs/changes/DDL-2026-077-visitor-permit-coklat database/ddl/status.json
git commit -m "feat(visitor): skema jendela jam permit coklat dan master kategori COKLAT"
```

---

### Task 2: Jendela jam pada registrasi tamu (backend)

**Files:**
- Modify: `app/Http/Controllers/VISITOR/Guest/Visit/GuestRegistrationController.php` (`store()` validasi + insert detail, `getMasterData()`)
- Modify: `app/Services/VISITOR/Permit/VehiclePermit/VisitorPermitQueryService.php:113-148` (`baseTamuQuery()` select)
- Test: `tests/Integration/VISITOR/Guest/Visit/JendelaKunjunganTamuTest.php`

**Interfaces:**
- Consumes: kolom dari Task 1.
- Produces: input request `waktu_mulai_kunjungan` dan `waktu_selesai_kunjungan` (string `Y-m-d H:i:s`), tersimpan di `N_EMI_VISITOR_Transaksi_Tamu_Detail`; `baseTamuQuery()` mengembalikan kolom `Waktu_Mulai_Kunjungan`, `Waktu_Selesai_Kunjungan`, dan `No_HP_PIC`.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

namespace Tests\Integration\VISITOR\Guest\Visit;

use App\Http\Controllers\VISITOR\Guest\Visit\GuestRegistrationController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Jendela jam kunjungan adalah SATU-SATUNYA sumber masa berlaku permit coklat.
 *
 * Sebelum ini, satu-satunya isyarat waktu pada registrasi tamu adalah
 * Waktu_Estimasi_Kunjungan: varchar(50) berisi label kasar hasil dropdown
 * ('1-2 jam', 'Seharian') yang di-hardcode di getMasterData(). Kolom itu tidak
 * bisa menjawab "tamu ini berlaku sampai jam berapa", dan datanya sudah
 * bercampur — ada baris berisi '13:30'. Kalau jendela jam boleh kosong atau
 * boleh terbalik, permit coklat lahir dengan masa berlaku yang mustahil.
 */
class JendelaKunjunganTamuTest extends TestCase
{
    use DatabaseTransactions;

    private const PIC = 'ZZRVK1';

    public function test_jendela_jam_tersimpan_apa_adanya(): void
    {
        $nomor = $this->daftarkan([
            'waktu_mulai_kunjungan' => '2026-09-10 08:00:00',
            'waktu_selesai_kunjungan' => '2026-09-10 17:00:00',
        ]);

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->first();

        $this->assertSame('2026-09-10 08:00:00', substr((string) $detail->Waktu_Mulai_Kunjungan, 0, 19));
        $this->assertSame('2026-09-10 17:00:00', substr((string) $detail->Waktu_Selesai_Kunjungan, 0, 19));
    }

    public function test_jendela_boleh_menyeberang_tengah_malam(): void
    {
        // Kunjungan shift malam. Tanggal_Kunjungan tetap tanggal MULAI supaya
        // papan harian dan laporan tidak goyah; akhir jendela yang menyeberang.
        $nomor = $this->daftarkan([
            'tanggal_kunjungan' => '2026-09-10',
            'waktu_mulai_kunjungan' => '2026-09-10 20:00:00',
            'waktu_selesai_kunjungan' => '2026-09-11 06:00:00',
        ]);

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->first();
        $header = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $nomor)->first();

        $this->assertSame('2026-09-11 06:00:00', substr((string) $detail->Waktu_Selesai_Kunjungan, 0, 19));
        $this->assertSame('2026-09-10', substr((string) $header->Tanggal_Kunjungan, 0, 10));
    }

    public function test_jendela_terbalik_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->daftarkan([
            'waktu_mulai_kunjungan' => '2026-09-10 17:00:00',
            'waktu_selesai_kunjungan' => '2026-09-10 08:00:00',
        ]);
    }

    public function test_jendela_wajib_diisi(): void
    {
        $this->expectException(ValidationException::class);

        $this->daftarkan(['waktu_mulai_kunjungan' => null, 'waktu_selesai_kunjungan' => null]);
    }

    public function test_base_tamu_query_membawa_jendela(): void
    {
        // Proyeksi permit coklat membaca jendelanya lewat baseTamuQuery().
        // Kalau kolomnya tidak ikut ter-select, permitnya lahir tanpa masa
        // berlaku dan syncPermitCoklatEfektif diam-diam jadi no-op.
        $nomor = $this->daftarkan([
            'waktu_mulai_kunjungan' => '2026-09-10 08:00:00',
            'waktu_selesai_kunjungan' => '2026-09-10 17:00:00',
        ]);

        $baris = app(\App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService::class)
            ->baseTamuQuery()->where('h.No_Transaksi', $nomor)->first();

        $this->assertNotNull($baris->Waktu_Mulai_Kunjungan);
        $this->assertNotNull($baris->Waktu_Selesai_Kunjungan);
    }

    /** @param array<string,mixed> $ubah */
    private function daftarkan(array $ubah): string
    {
        $payload = array_merge([
            'nama_tamu' => 'Tamu Uji Jendela',
            'keperluan' => 'Meeting',
            'pic_tujuan' => 'HRD',
            'tanggal_kunjungan' => '2026-09-10',
            'jumlah_tamu' => 1,
            'instansi_asal' => 'PT Uji',
            'jenis_kendaraan' => 'Tidak Berkendara',
            'waktu_mulai_kunjungan' => '2026-09-10 08:00:00',
            'waktu_selesai_kunjungan' => '2026-09-10 17:00:00',
        ], $ubah);

        $request = Request::create('/tamu', 'POST', $payload);
        $request->headers->set('Idempotency-Key', 'JDL-'.Str::upper(Str::random(20)));
        $request->setUserResolver(fn () => (object) ['Kode_Karyawan' => self::PIC]);

        $isi = json_decode(app(GuestRegistrationController::class)->store($request)->getContent(), true);

        $this->assertNotEmpty($isi['result']['no_transaksi'] ?? null, 'Registrasi tamu gagal: '.json_encode($isi));

        return $isi['result']['no_transaksi'];
    }
}
```

- [ ] **Step 2: Jalankan tes, pastikan MERAH**

Run: `php artisan test tests/Integration/VISITOR/Guest/Visit/JendelaKunjunganTamuTest.php`
Expected: FAIL — `test_jendela_wajib_diisi` dan `test_jendela_terbalik_ditolak` tidak melempar `ValidationException`, dan kolom jendela tersimpan `null`.

- [ ] **Step 3: Tambahkan aturan validasi di `store()`**

Sisipkan ke dalam array `$request->validate([...])` yang sudah ada, tepat setelah `'tanggal_kunjungan' => 'required|date',`:

```php
'waktu_mulai_kunjungan' => 'required|date',
'waktu_selesai_kunjungan' => 'required|date|after:waktu_mulai_kunjungan',
```

Hapus baris `'waktu_estimasi' => 'nullable|string|max:50',`.

- [ ] **Step 4: Simpan jendelanya di insert detail**

Di `DB::table(self::T_DETAIL)->insert([...])`, ganti baris `'Waktu_Estimasi_Kunjungan' => $request->input('waktu_estimasi'),` menjadi:

```php
// Waktu_Estimasi_Kunjungan dipensiunkan: varchar teks bebas berisi label kasar
// ('1-2 jam', 'Seharian') yang tidak bisa menjawab "berlaku sampai jam berapa".
// Kolomnya dipertahankan sebagai arsip data lama, tidak diisi lagi.
'Waktu_Mulai_Kunjungan' => $request->input('waktu_mulai_kunjungan'),
'Waktu_Selesai_Kunjungan' => $request->input('waktu_selesai_kunjungan'),
```

- [ ] **Step 5: Pensiunkan daftar `waktuEstimasi` di `getMasterData()`**

Hapus baris `$waktuEstimasi = ['< 30 menit', ...];` dan kunci `'waktu_estimasi' => $waktuEstimasi,` dari array respons.

- [ ] **Step 6: Tambahkan kolom jendela dan HP PIC ke `baseTamuQuery()`**

Di `VisitorPermitQueryService::baseTamuQuery()`, setelah `'d.Waktu_Estimasi_Kunjungan',` tambahkan:

```php
'd.Waktu_Mulai_Kunjungan',
'd.Waktu_Selesai_Kunjungan',
'k.HP as No_HP_PIC',
```

- [ ] **Step 7: Jalankan tes, pastikan HIJAU**

Run: `php artisan test tests/Integration/VISITOR/Guest/Visit/JendelaKunjunganTamuTest.php`
Expected: PASS (5 tes).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/VISITOR/Guest/Visit/GuestRegistrationController.php app/Services/VISITOR/Permit/VehiclePermit/VisitorPermitQueryService.php tests/Integration/VISITOR/Guest/Visit/JendelaKunjunganTamuTest.php
git commit -m "feat(visitor): jendela jam kunjungan pada registrasi tamu"
```

---

### Task 3: Penerbitan permit coklat di VisitorEfektifService

**Files:**
- Modify: `app/Services/VISITOR/Operations/Expiration/VisitorEfektifService.php`
- Test: `tests/Integration/VISITOR/Operations/PermitCoklatTerbitTest.php`

**Interfaces:**
- Consumes: `baseTamuQuery()` dengan kolom jendela (Task 2); kolom skema (Task 1).
- Produces: `VisitorEfektifService::syncPermitCoklatEfektif(string $noTransaksiTamu): void`. Pencabutannya **memakai metode yang sudah ada** `revokePermitEfektif(string $noTransaksi, ?string $alasan, string $olehKode): int` — karena baris permit coklat memakai `No_Transaksi` tamunya, metode itu bekerja apa adanya tanpa varian baru.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

namespace Tests\Integration\VISITOR\Operations;

use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Permit coklat lahir sebagai proyeksi, bukan sebagai pengajuan.
 *
 * Yang diuji di sini adalah kontrak yang dibaca aplikasi gate pihak ketiga.
 * Dua di antaranya tidak akan menyalakan error apa pun kalau salah:
 *
 *  - Kolom `date` lama WAJIB ikut terisi turunan dari kolom waktu. Gate yang
 *    belum diperbarui hanya membaca kolom `date`; kalau kosong, permit coklat
 *    tidak terlihat sama sekali di gerbang.
 *  - Tamu pejalan kaki tetap dapat permit. Plat kosong bukan alasan melewatkan
 *    penerbitan — 29 dari 83 baris tamu di development tidak berplat padahal
 *    hanya 3 yang menyatakan tidak berkendara.
 */
class PermitCoklatTerbitTest extends TestCase
{
    use DatabaseTransactions;

    private const PIC = 'ZZRVK1';

    public function test_permit_coklat_terbit_dengan_jendela_jamnya(): void
    {
        $nomor = $this->buatTamuEfektif('2026-09-10 08:00:00', '2026-09-10 17:00:00');

        app(VisitorEfektifService::class)->syncPermitCoklatEfektif($nomor);

        $baris = $this->permit($nomor);

        $this->assertSame('AKTIF', $baris->Status_Efektif);
        $this->assertSame('TAMU', $baris->Sumber_Permit);
        $this->assertSame('COKLAT', $baris->Nama_Kategori_Permit);
        $this->assertSame('2026-09-10 08:00:00', substr((string) $baris->Waktu_Mulai_Berlaku, 0, 19));
        $this->assertSame('2026-09-10 17:00:00', substr((string) $baris->Waktu_Berakhir, 0, 19));
    }

    public function test_kolom_tanggal_lama_ikut_terisi(): void
    {
        // Invarian kontrak gate. Kalau ini merah, permit coklat tidak terlihat
        // oleh aplikasi gerbang yang belum mengadopsi kolom jam.
        $nomor = $this->buatTamuEfektif('2026-09-10 20:00:00', '2026-09-11 06:00:00');

        app(VisitorEfektifService::class)->syncPermitCoklatEfektif($nomor);

        $baris = $this->permit($nomor);

        $this->assertSame('2026-09-10', substr((string) $baris->Tanggal_Mulai_Berlaku, 0, 10));
        $this->assertSame('2026-09-11', substr((string) $baris->Tanggal_Kadaluarsa, 0, 10));
    }

    public function test_tamu_pejalan_kaki_tetap_dapat_permit(): void
    {
        $nomor = $this->buatTamuEfektif('2026-09-10 08:00:00', '2026-09-10 12:00:00', berplat: false);

        app(VisitorEfektifService::class)->syncPermitCoklatEfektif($nomor);

        $baris = $this->permit($nomor);

        $this->assertSame('AKTIF', $baris->Status_Efektif);
        $this->assertSame('', trim((string) $baris->No_Plat_Kendaraan));
    }

    public function test_pemanggilan_ulang_tidak_menggandakan_baris(): void
    {
        $nomor = $this->buatTamuEfektif('2026-09-10 08:00:00', '2026-09-10 17:00:00');

        app(VisitorEfektifService::class)->syncPermitCoklatEfektif($nomor);
        app(VisitorEfektifService::class)->syncPermitCoklatEfektif($nomor);

        $this->assertSame(1, DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $nomor)->count());
    }

    public function test_tamu_tanpa_jendela_tidak_menerbitkan_permit(): void
    {
        // Baris tamu lama (sebelum fitur ini) tidak punya jendela. Menerbitkan
        // permit tanpa masa berlaku berarti akses yang tidak pernah kedaluwarsa.
        $nomor = $this->buatTamuEfektif(null, null);

        app(VisitorEfektifService::class)->syncPermitCoklatEfektif($nomor);

        $this->assertSame(0, DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $nomor)->count());
    }

    public function test_pencabutan_memakai_metode_permit_yang_sudah_ada(): void
    {
        $nomor = $this->buatTamuEfektif('2026-09-10 08:00:00', '2026-09-10 17:00:00');
        app(VisitorEfektifService::class)->syncPermitCoklatEfektif($nomor);

        $terpengaruh = app(VisitorEfektifService::class)
            ->revokePermitEfektif($nomor, 'Kunjungan dibatalkan', self::PIC);

        $this->assertSame(1, $terpengaruh);
        $this->assertSame('DIBATALKAN', $this->permit($nomor)->Status_Efektif);
    }

    private function permit(string $nomor): object
    {
        $baris = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $nomor)->first();
        $this->assertNotNull($baris, "Permit coklat untuk {$nomor} tidak terbit.");

        return $baris;
    }

    private function buatTamuEfektif(?string $mulai, ?string $selesai, bool $berplat = true): string
    {
        $nomor = 'VSR-CKL-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_PIC' => self::PIC,
            'Status_Kunjungan' => 'TERJADWAL',
            'Flag_Approval' => 'Y',
            'Tanggal_Kunjungan' => '2026-09-10',
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Idempotency_Key' => 'CKL-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id,
            'No_Transaksi' => $nomor,
            'Nama_Lengkap_Tamu' => 'Tamu Coklat',
            'Instansi_Atau_Asal' => 'PT Uji',
            'Deksripsi_Keperluan' => 'Meeting',
            'Jumlah_Tamu' => 1,
            'Jenis_Kendaraan' => $berplat ? 'Mobil' : 'Tidak Berkendara',
            'Plat_Nomor_Prefix' => $berplat ? 'BG' : null,
            'Plat_Nomor_Angka' => $berplat ? (string) random_int(10000, 99999) : null,
            'Plat_Nomor_Suffix' => $berplat ? 'CK' : null,
            'Waktu_Mulai_Kunjungan' => $mulai,
            'Waktu_Selesai_Kunjungan' => $selesai,
        ]);

        return $nomor;
    }
}
```

- [ ] **Step 2: Jalankan tes, pastikan MERAH**

Run: `php artisan test tests/Integration/VISITOR/Operations/PermitCoklatTerbitTest.php`
Expected: FAIL — `Call to undefined method ...::syncPermitCoklatEfektif()`.

- [ ] **Step 3: Tambahkan `syncPermitCoklatEfektif()` ke `VisitorEfektifService`**

Letakkan tepat setelah `syncTamuEfektif()`, sebelum `revokePermitEfektif()`:

```php
/**
 * Terbitkan permit COKLAT — izin akses tamu berbasis jendela jam.
 *
 * Barisnya memakai No_Transaksi TAMU-nya sendiri sebagai kunci. Kolom itu
 * sudah UNIQUE, jadi upsert-nya idempoten tanpa tambahan apa pun, korelasi ke
 * registrasi jadi trivial, dan kita tidak perlu memanggil generateNoTransaksi()
 * sekali lagi per tamu — pembangkit nomor itu pernah jadi sumber beban CPU.
 *
 * INVARIAN: selama kolom Waktu_* diisi, kolom Tanggal_* WAJIB ikut terisi
 * turunan tanggalnya. Aplikasi gate pihak ketiga yang belum mengadopsi kolom
 * jam hanya membaca kolom Tanggal_*; membiarkannya kosong membuat permit
 * coklat tak terlihat di gerbang tanpa satu pun galat. Aturan ini hidup di
 * sini saja dan tidak boleh disalin ke pemanggil.
 */
public function syncPermitCoklatEfektif(string $noTransaksiTamu): void
{
    $header = $this->permitQuery->baseTamuQuery()
        ->where('h.No_Transaksi', $noTransaksiTamu)
        ->first();

    if (! $header || ! $header->Waktu_Mulai_Kunjungan || ! $header->Waktu_Selesai_Kunjungan) {
        // Baris tamu lama tidak punya jendela. Permit tanpa masa berlaku
        // adalah akses yang tidak pernah kedaluwarsa — lebih baik tidak ada.
        return;
    }

    $kategori = DB::table('N_EMI_VISITOR_Master_Kategori_Permit')
        ->where('Nama_Kategori', 'COKLAT')
        ->where('Flag_Aktif', 'Y')
        ->first();

    if (! $kategori) {
        throw new \RuntimeException('Master kategori permit COKLAT tidak ada atau nonaktif.');
    }

    $mulai = \Illuminate\Support\Carbon::parse($header->Waktu_Mulai_Kunjungan);
    $selesai = \Illuminate\Support\Carbon::parse($header->Waktu_Selesai_Kunjungan);
    $now = now();

    $row = [
        'Kode_Perusahaan' => '001',
        'No_Transaksi' => $noTransaksiTamu,
        'Kode_Karyawan_Pemohon' => $header->Kode_Karyawan_PIC,
        'Nama_Pemohon' => $header->Nama_PIC ?: $header->Kode_Karyawan_PIC,
        'No_HP_Pemohon' => $header->No_HP_PIC ?? null,
        'Departemen' => $header->Divisi_PIC ?? null,
        'Keterangan_Pengajuan' => trim(sprintf(
            'Tamu: %s (%s). Keperluan: %s',
            $header->Nama_Tamu ?? '-',
            $header->Instansi_Asal ?? '-',
            $header->Keperluan ?? '-',
        )),
        'No_Plat_Kendaraan' => $header->No_Plat_Kendaraan,
        'Nama_Kategori_Permit' => $kategori->Nama_Kategori,
        'Deskripsi_Akses' => $kategori->Deskripsi_Akses,
        'Kode_Warna_UI' => $kategori->Kode_Warna_UI,
        'Nama_Vehicle_Type' => $header->Jenis_Kendaraan,
        'Nama_Durasi' => $this->labelDurasiJendela($mulai, $selesai),
        'Waktu_Mulai_Berlaku' => $mulai,
        'Waktu_Berakhir' => $selesai,
        'Tanggal_Mulai_Berlaku' => $mulai->toDateString(),
        'Tanggal_Kadaluarsa' => $selesai->toDateString(),
        'Sumber_Permit' => 'TAMU',
        'Status_Efektif' => 'AKTIF',
        'Flag_Batal' => 'T',
        'Updated_At' => $now,
        'Flag_Sync' => 'T',
    ];

    $existing = DB::table('N_VISITOR_Permit_Efektif')
        ->where('No_Transaksi', $noTransaksiTamu)
        ->first();

    if ($existing) {
        DB::table('N_VISITOR_Permit_Efektif')
            ->where('No_Transaksi', $noTransaksiTamu)
            ->where('Status_Efektif', 'AKTIF')
            ->update($row);

        return;
    }

    $meta = $this->resolveApprovalMeta($noTransaksiTamu);

    DB::table('N_VISITOR_Permit_Efektif')->insert(array_merge($row, [
        'Disetujui_Oleh_Nama' => $meta['disetujui_oleh_nama'],
        'Disetujui_At' => $meta['disetujui_at'],
        'Riwayat_Approval' => $meta['riwayat_approval'],
        'Dibuat_At' => $header->Created_At,
        'Dibuat_Oleh_Nama' => $header->Nama_PIC ?: $header->Kode_Karyawan_PIC,
        'Updated_Oleh_Nama' => $meta['disetujui_oleh_nama'],
    ]));
}

private function labelDurasiJendela(\Illuminate\Support\Carbon $mulai, \Illuminate\Support\Carbon $selesai): string
{
    $menit = max(1, $mulai->diffInMinutes($selesai));

    if ($menit < 60) {
        return $menit.' menit';
    }

    $jam = intdiv($menit, 60);
    $sisa = $menit % 60;

    return $sisa === 0 ? $jam.' jam' : $jam.' jam '.$sisa.' menit';
}
```

- [ ] **Step 4: Tulis `Sumber_Permit='KARYAWAN'` di `syncPermitEfektif()`**

Di `syncPermitEfektif()`, tambahkan ke array `$row` (mis. tepat sesudah `'Nama_Durasi' => $header->Nama_Durasi,`):

```php
// Pasangan Sumber_Permit='TAMU' pada permit coklat. Ditulis eksplisit supaya
// baris yang di-sync ulang ikut diluruskan, bukan mengandalkan backfill sekali.
'Sumber_Permit' => 'KARYAWAN',
```

- [ ] **Step 5: Jalankan tes, pastikan HIJAU**

Run: `php artisan test tests/Integration/VISITOR/Operations/PermitCoklatTerbitTest.php`
Expected: PASS (6 tes).

- [ ] **Step 6: Commit**

```bash
git add app/Services/VISITOR/Operations/Expiration/VisitorEfektifService.php tests/Integration/VISITOR/Operations/PermitCoklatTerbitTest.php
git commit -m "feat(visitor): terbitkan permit coklat dari registrasi tamu"
```

---

### Task 4: Sambungkan kelima pintu

**Files:**
- Modify: `app/Http/Controllers/VISITOR/Guest/Visit/GuestRegistrationController.php:255` (auto-approve), `:455` (destroy)
- Modify: `app/Services/VISITOR/Workflow/Approval/VisitorApprovalDecisionService.php:132` (approveTamu)
- Modify: `app/Http/Controllers/VISITOR/Workflow/Verification/VerifikatorVisitorController.php:679` (revoke tamu)
- Modify: `app/Console/Commands/VISITOR/Operations/Demo/SeedPapanHarianDemo.php:357`
- Test: `tests/Integration/VISITOR/Operations/PermitCoklatPintuTest.php`

**Interfaces:**
- Consumes: `syncPermitCoklatEfektif()` dan `revokePermitEfektif()` (Task 3).
- Produces: jaminan bahwa setiap tamu efektif punya permit coklat, dan setiap pencabutan tamu ikut mencabutnya.

> **Kenapa task ini yang paling menentukan.** Pola bug yang berulang di proyek ini adalah aturan yang hanya dipasang di sebagian jalur setara. Menutup empat dari lima pintu tidak menyalakan satu pun error — gejalanya baru muncul sebagai tamu yang lolos gerbang. Kelima pintu di bawah adalah hasil penelusuran menyeluruh terhadap seluruh pemanggil `syncTamuEfektif` / `revokeTamuEfektif`.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

namespace Tests\Integration\VISITOR\Operations;

use App\Http\Controllers\VISITOR\Guest\Visit\GuestRegistrationController;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Setiap pintu yang menulis proyeksi TAMU wajib ikut mengurus permit coklatnya.
 *
 * Ada lima. Menutup empat di antaranya tidak menyalakan error apa pun; yang
 * terjadi adalah tamu tanpa permit di gerbang, atau permit yang tetap hidup
 * sesudah kunjungannya dicabut. Satu tes per pintu, supaya pintu yang
 * terlewat ketahuan sebagai kegagalan yang menyebut namanya sendiri.
 */
class PermitCoklatPintuTest extends TestCase
{
    use DatabaseTransactions;

    private const PIC = 'ZZRVK1';

    public function test_pintu_1_auto_approve_saat_submit_menerbitkan_permit(): void
    {
        // Berlaku hanya bila konfigurasi approval tamu mematikan approval.
        // Kalau tidak, pintu ini diperiksa lewat pintu 2.
        $config = app(\App\Services\VISITOR\Workflow\Approval\VisitorApprovalPolicyService::class)->config('tamu');

        if ((bool) $config['needs_approval']) {
            $this->markTestSkipped('Approval tamu aktif; pintu auto-approve diperiksa di pintu 2.');
        }

        $nomor = $this->daftarkanLewatController();

        $this->assertSame('AKTIF', $this->permit($nomor)->Status_Efektif);
    }

    public function test_pintu_2_approval_final_menerbitkan_permit(): void
    {
        $nomor = $this->buatTamu();

        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);
        app(VisitorEfektifService::class)->syncPermitCoklatEfektif($nomor);

        // Prasyarat: approveTamu() memanggil KEDUA sync di atas. Uji ini
        // menagih hasil akhirnya, bukan urutan pemanggilan.
        $this->assertSame('AKTIF', $this->permit($nomor)->Status_Efektif);
        $this->assertNotNull(DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $nomor)->first());
    }

    public function test_pintu_3_pic_menghapus_tamu_mencabut_permitnya(): void
    {
        $nomor = $this->buatTamuEfektifPenuh();
        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $nomor)->value('Id_Transaksi_Tamu');

        $request = Request::create('/tamu/'.$id, 'DELETE');
        $request->setUserResolver(fn () => (object) ['Kode_Karyawan' => self::PIC]);
        app(GuestRegistrationController::class)->destroy($request, (string) $id);

        $this->assertSame('DIBATALKAN', $this->permit($nomor)->Status_Efektif);
    }

    public function test_pintu_4_verifikator_mencabut_tamu_mencabut_permitnya(): void
    {
        $nomor = $this->buatTamuEfektifPenuh();

        app(VisitorEfektifService::class)->revokeTamuEfektif($nomor, 'Dicabut verifikator', self::PIC);
        app(VisitorEfektifService::class)->revokePermitEfektif($nomor, 'Dicabut verifikator', self::PIC);

        $this->assertSame('DIBATALKAN', $this->permit($nomor)->Status_Efektif);
    }

    public function test_pintu_5_seeder_demo_ikut_menerbitkan_permit_coklat(): void
    {
        // Papan demo dipakai untuk memperagakan modul. Tanpa permit coklat,
        // peragaan itu menunjukkan keadaan yang tidak mungkin terjadi.
        \Illuminate\Support\Facades\Artisan::call('visitor:seed-papan-harian');

        $tamuDemo = DB::table('N_VISITOR_Tamu_Efektif')
            ->where('Status_Efektif', 'AKTIF')
            ->orderByDesc('Id_Tamu_Efektif')
            ->limit(1)
            ->value('No_Transaksi');

        $this->assertNotNull($tamuDemo, 'Seeder demo tidak menghasilkan tamu efektif.');
        $this->assertNotNull(
            DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $tamuDemo)->first(),
            'Seeder demo menerbitkan tamu tanpa permit coklat.',
        );
    }

    private function permit(string $nomor): object
    {
        $baris = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $nomor)->first();
        $this->assertNotNull($baris, "Permit coklat untuk {$nomor} tidak ada.");

        return $baris;
    }

    private function daftarkanLewatController(): string
    {
        $request = Request::create('/tamu', 'POST', [
            'nama_tamu' => 'Tamu Pintu', 'keperluan' => 'Meeting', 'pic_tujuan' => 'HRD',
            'tanggal_kunjungan' => '2026-09-10', 'jumlah_tamu' => 1, 'instansi_asal' => 'PT Uji',
            'jenis_kendaraan' => 'Tidak Berkendara',
            'waktu_mulai_kunjungan' => '2026-09-10 08:00:00',
            'waktu_selesai_kunjungan' => '2026-09-10 17:00:00',
        ]);
        $request->headers->set('Idempotency-Key', 'PTU-'.Str::upper(Str::random(20)));
        $request->setUserResolver(fn () => (object) ['Kode_Karyawan' => self::PIC]);

        $isi = json_decode(app(GuestRegistrationController::class)->store($request)->getContent(), true);

        return $isi['result']['no_transaksi'];
    }

    private function buatTamu(): string
    {
        $nomor = 'VSR-PTU-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor, 'Kode_Karyawan_PIC' => self::PIC,
            'Status_Kunjungan' => 'TERJADWAL', 'Flag_Approval' => 'Y',
            'Tanggal_Kunjungan' => '2026-09-10', 'Flag_Aktif' => 'Y',
            'Created_At' => now(), 'Created_By' => self::PIC,
            'Idempotency_Key' => 'PTU-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id, 'No_Transaksi' => $nomor,
            'Nama_Lengkap_Tamu' => 'Tamu Pintu', 'Instansi_Atau_Asal' => 'PT Uji',
            'Deksripsi_Keperluan' => 'Meeting', 'Jumlah_Tamu' => 1,
            'Jenis_Kendaraan' => 'Mobil', 'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(10000, 99999), 'Plat_Nomor_Suffix' => 'PT',
            'Waktu_Mulai_Kunjungan' => '2026-09-10 08:00:00',
            'Waktu_Selesai_Kunjungan' => '2026-09-10 17:00:00',
        ]);

        return $nomor;
    }

    private function buatTamuEfektifPenuh(): string
    {
        $nomor = $this->buatTamu();
        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);
        app(VisitorEfektifService::class)->syncPermitCoklatEfektif($nomor);

        return $nomor;
    }
}
```

- [ ] **Step 2: Jalankan tes, pastikan MERAH**

Run: `php artisan test tests/Integration/VISITOR/Operations/PermitCoklatPintuTest.php`
Expected: FAIL pada pintu 1, 3, dan 5 (permit tidak terbit / tidak tercabut). Pintu 2 dan 4 memanggil service langsung sehingga sudah hijau — keduanya baru bernilai setelah Step 3 menyambungkannya di kode produksi; keduanya tetap ada sebagai penjaga hasil akhir.

- [ ] **Step 3: Sambungkan kelima pintu**

Pintu 1 — `GuestRegistrationController::store()`, di dalam blok `if (! $needsApproval) {` (baris ~252):

```php
if (! $needsApproval) {
    // Proyeksi ini menentukan akses tamu, sehingga harus commit atau
    // rollback bersama header/detail; bukan side effect best-effort.
    $this->visitorEfektif->syncTamuEfektif($noTransaksi);
    $this->visitorEfektif->syncPermitCoklatEfektif($noTransaksi);
}
```

Pintu 2 — `VisitorApprovalDecisionService::approveTamu()`, di blok `if ($isFinal) {` (baris ~132):

```php
$this->visitorEfektif->syncTamuEfektif($noTransaksi);
$this->visitorEfektif->syncPermitCoklatEfektif($noTransaksi);
```

Pintu 3 — `GuestRegistrationController::destroy()` (baris ~455), di dalam `try` yang sama:

```php
$this->visitorEfektif->revokeTamuEfektif($header->No_Transaksi, 'Dibatalkan sendiri oleh PIC', $kodeKaryawan);
// Permit coklat memakai No_Transaksi tamu yang sama, jadi metode permit yang
// sudah ada bekerja apa adanya. No-op kalau permitnya memang tidak ada.
$this->visitorEfektif->revokePermitEfektif($header->No_Transaksi, 'Dibatalkan sendiri oleh PIC', $kodeKaryawan);
```

Pintu 4 — `VerifikatorVisitorController` baris ~679, tepat setelah `revokeTamuEfektif(...)`:

```php
$this->visitorEfektif->revokePermitEfektif($noTransaksi, $request->input('alasan'), $olehKode);
```

Pintu 5 — `SeedPapanHarianDemo` baris ~357, tepat setelah `$efektif->syncTamuEfektif($no);`:

```php
$efektif->syncPermitCoklatEfektif($no);
```

Seeder juga harus mengisi `Waktu_Mulai_Kunjungan`/`Waktu_Selesai_Kunjungan` pada detail tamu yang dibuatnya; tanpa itu `syncPermitCoklatEfektif()` no-op dan pintu 5 tetap merah. Isi dengan jendela di hari yang sama dengan `Tanggal_Kunjungan` demo, mis. `08:00`–`17:00`.

- [ ] **Step 4: Jalankan tes, pastikan HIJAU**

Run: `php artisan test tests/Integration/VISITOR/Operations/PermitCoklatPintuTest.php`
Expected: PASS (5 tes; pintu 1 mungkin `skipped` tergantung konfigurasi approval).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/VISITOR app/Services/VISITOR/Workflow/Approval/VisitorApprovalDecisionService.php app/Console/Commands/VISITOR/Operations/Demo/SeedPapanHarianDemo.php tests/Integration/VISITOR/Operations/PermitCoklatPintuTest.php
git commit -m "feat(visitor): sambungkan permit coklat ke kelima pintu proyeksi tamu"
```

---

### Task 5: Sinkron ulang saat jendela kunjungan diubah

**Files:**
- Modify: `app/Http/Controllers/VISITOR/Guest/Visit/GuestRegistrationController.php:422-441` (`update()`)
- Test: `tests/Integration/VISITOR/Guest/Visit/UbahJendelaTersinkronTest.php`

**Interfaces:**
- Consumes: `syncTamuEfektif()`, `syncPermitCoklatEfektif()`.
- Produces: `update()` menerima `waktu_mulai_kunjungan`/`waktu_selesai_kunjungan` dan menyinkronkan ulang kedua proyeksi.

> **Ini menambal cacat yang sudah ada.** `update()` hari ini mengubah `Tanggal_Kunjungan` tanpa menyentuh proyeksi sama sekali — data di gerbang sudah basi sejak sebelum fitur ini. Permit coklat mempertajamnya karena jendela kini menentukan masa berlaku.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

namespace Tests\Integration\VISITOR\Guest\Visit;

use App\Http\Controllers\VISITOR\Guest\Visit\GuestRegistrationController;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mengubah jendela kunjungan harus sampai ke gerbang.
 *
 * update() sebelumnya mengubah Tanggal_Kunjungan tanpa menyentuh proyeksi sama
 * sekali: aplikasi gate terus membaca nilai lama tanpa satu pun galat. Dengan
 * permit coklat, nilai basi itu bukan lagi sekadar tampilan yang keliru —
 * ia adalah masa berlaku akses fisik yang salah.
 */
class UbahJendelaTersinkronTest extends TestCase
{
    use DatabaseTransactions;

    private const PIC = 'ZZRVK1';

    public function test_mengubah_jendela_memperbarui_masa_berlaku_permit(): void
    {
        [$nomor, $id] = $this->buatTamuEfektif();

        $request = Request::create('/tamu/'.$id, 'PUT', [
            'waktu_mulai_kunjungan' => '2026-09-10 13:00:00',
            'waktu_selesai_kunjungan' => '2026-09-10 15:00:00',
        ]);
        $request->setUserResolver(fn () => (object) ['Kode_Karyawan' => self::PIC]);

        app(GuestRegistrationController::class)->update($request, (string) $id);

        $permit = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $nomor)->first();

        $this->assertSame('2026-09-10 15:00:00', substr((string) $permit->Waktu_Berakhir, 0, 19));
        $this->assertSame('T', $permit->Flag_Sync, 'Perubahan tidak ditandai untuk ditarik ulang gerbang.');
    }

    public function test_mengubah_jendela_juga_memperbarui_proyeksi_tamu(): void
    {
        [$nomor, $id] = $this->buatTamuEfektif();

        $request = Request::create('/tamu/'.$id, 'PUT', ['tanggal_kunjungan' => '2026-09-12']);
        $request->setUserResolver(fn () => (object) ['Kode_Karyawan' => self::PIC]);

        app(GuestRegistrationController::class)->update($request, (string) $id);

        $tamu = DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $nomor)->first();

        $this->assertSame('2026-09-12', substr((string) $tamu->Tanggal_Kunjungan, 0, 10));
    }

    /** @return array{0:string,1:int} */
    private function buatTamuEfektif(): array
    {
        $nomor = 'VSR-UBH-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor, 'Kode_Karyawan_PIC' => self::PIC,
            'Status_Kunjungan' => 'TERJADWAL', 'Flag_Approval' => 'Y',
            'Tanggal_Kunjungan' => '2026-09-10', 'Flag_Aktif' => 'Y',
            'Created_At' => now(), 'Created_By' => self::PIC,
            'Idempotency_Key' => 'UBH-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id, 'No_Transaksi' => $nomor,
            'Nama_Lengkap_Tamu' => 'Tamu Ubah', 'Instansi_Atau_Asal' => 'PT Uji',
            'Deksripsi_Keperluan' => 'Meeting', 'Jumlah_Tamu' => 1,
            'Jenis_Kendaraan' => 'Mobil', 'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(10000, 99999), 'Plat_Nomor_Suffix' => 'UB',
            'Waktu_Mulai_Kunjungan' => '2026-09-10 08:00:00',
            'Waktu_Selesai_Kunjungan' => '2026-09-10 17:00:00',
        ]);

        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);
        app(VisitorEfektifService::class)->syncPermitCoklatEfektif($nomor);

        return [$nomor, (int) $id];
    }
}
```

- [ ] **Step 2: Jalankan tes, pastikan MERAH**

Run: `php artisan test tests/Integration/VISITOR/Guest/Visit/UbahJendelaTersinkronTest.php`
Expected: FAIL — `Waktu_Berakhir` masih `17:00:00`, `Tanggal_Kunjungan` proyeksi masih `2026-09-10`.

- [ ] **Step 3: Ganti isi `update()`**

```php
public function update(Request $request, string $id)
{
    try {
        $request->validate([
            'tanggal_kunjungan' => 'nullable|date',
            'waktu_mulai_kunjungan' => 'nullable|date',
            'waktu_selesai_kunjungan' => 'nullable|date|after:waktu_mulai_kunjungan',
        ]);

        $header = DB::table(self::T_HEADER)->where('Id_Transaksi_Tamu', $id)->first();

        if (! $header) {
            return ResponseHelper::error('Data tamu tidak ditemukan.', 404);
        }

        $headerUpdate = array_filter(
            [
                'Tanggal_Kunjungan' => $request->input('tanggal_kunjungan'),
                'Status_Kunjungan' => $request->input('status_kunjungan'),
            ],
            fn ($value) => $value !== null,
        );

        $detailUpdate = array_filter(
            [
                'Waktu_Mulai_Kunjungan' => $request->input('waktu_mulai_kunjungan'),
                'Waktu_Selesai_Kunjungan' => $request->input('waktu_selesai_kunjungan'),
            ],
            fn ($value) => $value !== null,
        );

        if (! empty($headerUpdate)) {
            DB::table(self::T_HEADER)->where('Id_Transaksi_Tamu', $id)->update($headerUpdate);
        }

        if (! empty($detailUpdate)) {
            DB::table(self::T_DETAIL)->where('No_Transaksi', $header->No_Transaksi)->update($detailUpdate);
        }

        // Tanpa sinkron ulang, aplikasi gerbang terus membaca jendela dan
        // tanggal yang lama tanpa satu pun galat — dan sejak permit coklat,
        // nilai basi itu adalah masa berlaku akses fisik yang salah.
        if (! empty($headerUpdate) || ! empty($detailUpdate)) {
            $this->visitorEfektif->syncTamuEfektif($header->No_Transaksi);
            $this->visitorEfektif->syncPermitCoklatEfektif($header->No_Transaksi);
        }

        return ResponseHelper::success(null, 'Data tamu berhasil diperbarui.');
    } catch (\Exception $e) {
        return VisitorApiFailure::respond($e, 'guest_update', 'VISITOR_GUEST_UPDATE_FAILED', 'Data tamu gagal diperbarui.');
    }
}
```

- [ ] **Step 4: Jalankan tes, pastikan HIJAU**

Run: `php artisan test tests/Integration/VISITOR/Guest/Visit/UbahJendelaTersinkronTest.php`
Expected: PASS (2 tes).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/VISITOR/Guest/Visit/GuestRegistrationController.php tests/Integration/VISITOR/Guest/Visit/UbahJendelaTersinkronTest.php
git commit -m "fix(visitor): sinkron ulang proyeksi saat jendela kunjungan diubah"
```

---

### Task 6: Akhir masa berlaku — jam habis dan check-out

**Files:**
- Modify: `app/Console/Commands/VISITOR/Operations/Expiration/ExpireVisitorEfektif.php`
- Modify: `app/Http/Controllers/VISITOR/Guest/Visit/GuestRegistrationController.php:513-551` (`checkOut()`)
- Create: `database/dml/DML-2026-078-visitor-expire-tiap-5-menit/data-change.sql`
- Test: `tests/Integration/VISITOR/Operations/PermitCoklatBerakhirTest.php`

**Interfaces:**
- Consumes: `Sumber_Permit`, `Waktu_Berakhir`, `Waktu_Selesai_Kunjungan`.
- Produces: `visitor:expire-efektif` dengan tiga cabang; `checkOut()` menutup permit seketika di jalur manual.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

namespace Tests\Integration\VISITOR\Operations;

use App\Http\Controllers\VISITOR\Guest\Visit\GuestRegistrationController;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Permit coklat berakhir pada MANA YANG LEBIH DULU: jam selesai, atau tamu keluar.
 *
 * Cabang check-out WAJIB memakai definisi kehadiran resmi
 * (COALESCE(efektif.Waktu_Aktual_Keluar, detail.Waktu_Aktual_Keluar)) dan
 * TIDAK BOLEH memakai Status_Kunjungan. Kolom itu hanya ditulis tombol manual
 * HCIS; tamu yang lewat palang tetap berstatus TERJADWAL selamanya, sehingga
 * permit tamu yang benar-benar sudah pulang tidak pernah mati.
 */
class PermitCoklatBerakhirTest extends TestCase
{
    use DatabaseTransactions;

    private const PIC = 'ZZRVK1';

    public function test_permit_mati_saat_jam_selesai_terlewat(): void
    {
        $nomor = $this->buatPermitCoklat(now()->subHours(3), now()->subHour());

        Artisan::call('visitor:expire-efektif');

        $baris = $this->permit($nomor);
        $this->assertSame('EXPIRED', $baris->Status_Efektif);
        $this->assertSame('T', $baris->Flag_Sync);
    }

    public function test_permit_masih_hidup_sebelum_jam_selesai(): void
    {
        $nomor = $this->buatPermitCoklat(now()->subHour(), now()->addHours(3));

        Artisan::call('visitor:expire-efektif');

        $this->assertSame('AKTIF', $this->permit($nomor)->Status_Efektif);
    }

    public function test_permit_mati_saat_palang_mencatat_keluar(): void
    {
        $nomor = $this->buatPermitCoklat(now()->subHour(), now()->addHours(3));

        // Palang menulis HANYA ke tabel efektif — persis seperti aslinya.
        Artisan::call('visitor:simulasi-palang', ['--masuk' => $nomor]);
        Artisan::call('visitor:simulasi-palang', ['--keluar' => $nomor]);

        Artisan::call('visitor:expire-efektif');

        $this->assertSame('EXPIRED', $this->permit($nomor)->Status_Efektif);
        $this->assertSame(
            'TERJADWAL',
            DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $nomor)->value('Status_Kunjungan'),
            'Prasyarat berubah: palang kini menyentuh Status_Kunjungan, uji ini perlu ditinjau.',
        );
    }

    public function test_permit_mati_seketika_lewat_tombol_check_out_manual(): void
    {
        $nomor = $this->buatPermitCoklat(now()->subHour(), now()->addHours(3));
        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $nomor)->value('Id_Transaksi_Tamu');

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
            ->where('No_Transaksi', $nomor)
            ->update(['Waktu_Aktual_Masuk' => now()->subMinutes(30)]);

        $request = Request::create('/tamu/'.$id.'/checkout', 'POST');
        $request->setUserResolver(fn () => (object) ['Kode_Karyawan' => self::PIC]);
        app(GuestRegistrationController::class)->checkOut($request, (string) $id);

        // Tanpa menunggu sapuan: jalur manual adalah pintu kedua yang setara.
        $this->assertSame('EXPIRED', $this->permit($nomor)->Status_Efektif);
    }

    public function test_kunjungan_lintas_hari_tidak_mati_kepagian(): void
    {
        // Jendela 20:00 kemarin sampai 06:00 besok. Sapuan lama membandingkan
        // Tanggal_Kadaluarsa dengan hari ini dan akan mematikannya kepagian.
        $nomor = $this->buatPermitCoklat(now()->subDay()->setTime(20, 0), now()->addDay()->setTime(6, 0));

        Artisan::call('visitor:expire-efektif');

        $this->assertSame('AKTIF', $this->permit($nomor)->Status_Efektif);
    }

    public function test_permit_karyawan_tidak_terpengaruh(): void
    {
        $nomor = 'PMT-EXP-'.Str::upper(Str::random(8));

        DB::table('N_VISITOR_Permit_Efektif')->insert([
            'Kode_Perusahaan' => '001', 'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => self::PIC, 'Nama_Pemohon' => 'Pemohon Uji',
            'Status_Efektif' => 'AKTIF', 'Sumber_Permit' => 'KARYAWAN',
            'Tanggal_Mulai_Berlaku' => now()->subMonth()->toDateString(),
            'Tanggal_Kadaluarsa' => now()->addMonths(2)->toDateString(),
            'Disetujui_Oleh_Nama' => 'Sistem Uji', 'Disetujui_At' => now()->subMonth(),
            'Riwayat_Approval' => '[]', 'Dibuat_At' => now()->subMonth(),
            'Dibuat_Oleh_Nama' => 'Sistem Uji', 'Updated_At' => now(), 'Flag_Sync' => 'Y',
        ]);

        Artisan::call('visitor:expire-efektif');

        $this->assertSame('AKTIF', $this->permit($nomor)->Status_Efektif);
    }

    public function test_permit_karyawan_tanpa_sumber_permit_tetap_kedaluwarsa(): void
    {
        // Sumber_Permit nullable. Penyaring `<> 'TAMU'` telanjang menghasilkan
        // UNKNOWN untuk NULL, sehingga baris yang belum ter-backfill diam-diam
        // berhenti dikedaluwarsakan — tanpa satu pun galat.
        $nomor = 'PMT-NUL-'.Str::upper(Str::random(8));

        DB::table('N_VISITOR_Permit_Efektif')->insert([
            'Kode_Perusahaan' => '001', 'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => self::PIC, 'Nama_Pemohon' => 'Pemohon Uji',
            'Status_Efektif' => 'AKTIF', 'Sumber_Permit' => null,
            'Tanggal_Mulai_Berlaku' => now()->subMonths(6)->toDateString(),
            'Tanggal_Kadaluarsa' => now()->subDays(2)->toDateString(),
            'Disetujui_Oleh_Nama' => 'Sistem Uji', 'Disetujui_At' => now()->subMonths(6),
            'Riwayat_Approval' => '[]', 'Dibuat_At' => now()->subMonths(6),
            'Dibuat_Oleh_Nama' => 'Sistem Uji', 'Updated_At' => now(), 'Flag_Sync' => 'Y',
        ]);

        Artisan::call('visitor:expire-efektif');

        $this->assertSame('EXPIRED', $this->permit($nomor)->Status_Efektif);
    }

    private function permit(string $nomor): object
    {
        $baris = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $nomor)->first();
        $this->assertNotNull($baris, "Permit {$nomor} tidak ada.");

        return $baris;
    }

    private function buatPermitCoklat(\Illuminate\Support\Carbon $mulai, \Illuminate\Support\Carbon $selesai): string
    {
        $nomor = 'VSR-EXP-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor, 'Kode_Karyawan_PIC' => self::PIC,
            'Status_Kunjungan' => 'TERJADWAL', 'Flag_Approval' => 'Y',
            'Tanggal_Kunjungan' => $mulai->toDateString(), 'Flag_Aktif' => 'Y',
            'Created_At' => now(), 'Created_By' => self::PIC,
            'Idempotency_Key' => 'EXP-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id, 'No_Transaksi' => $nomor,
            'Nama_Lengkap_Tamu' => 'Tamu Expire', 'Instansi_Atau_Asal' => 'PT Uji',
            'Deksripsi_Keperluan' => 'Meeting', 'Jumlah_Tamu' => 1,
            'Jenis_Kendaraan' => 'Mobil', 'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(10000, 99999), 'Plat_Nomor_Suffix' => 'EX',
            'Waktu_Mulai_Kunjungan' => $mulai->format('Y-m-d H:i:s'),
            'Waktu_Selesai_Kunjungan' => $selesai->format('Y-m-d H:i:s'),
        ]);

        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);
        app(VisitorEfektifService::class)->syncPermitCoklatEfektif($nomor);

        return $nomor;
    }
}
```

- [ ] **Step 2: Jalankan tes, pastikan MERAH**

Run: `php artisan test tests/Integration/VISITOR/Operations/PermitCoklatBerakhirTest.php`
Expected: FAIL pada cabang jam, cabang palang, cabang manual, dan lintas hari.

- [ ] **Step 3: Tulis ulang `ExpireVisitorEfektif::handle()`**

```php
public function handle(): int
{
    $now = now();
    $today = $now->toDateString();

    // Cabang 1 — permit KARYAWAN, ketelitian hari (tidak berubah).
    //
    // ISNULL(...) bukan hiasan: Sumber_Permit nullable, dan `Sumber_Permit <> 'TAMU'`
    // menghasilkan UNKNOWN untuk NULL. Baris yang belum ter-backfill akan diam-diam
    // berhenti dikedaluwarsakan, tanpa satu pun galat.
    $permitKaryawan = DB::table('N_VISITOR_Permit_Efektif')
        ->where('Status_Efektif', 'AKTIF')
        ->whereRaw("ISNULL(Sumber_Permit, 'KARYAWAN') <> 'TAMU'")
        ->whereDate('Tanggal_Kadaluarsa', '<', $today)
        ->update([
            'Status_Efektif' => 'EXPIRED',
            'Updated_At' => $now,
            'Updated_Oleh_Nama' => 'Sistem (Auto-Expire)',
            'Flag_Sync' => 'T',
        ]);

    // Cabang 2 — permit COKLAT, jendela jamnya habis.
    $permitCoklatJam = DB::table('N_VISITOR_Permit_Efektif')
        ->where('Status_Efektif', 'AKTIF')
        ->where('Sumber_Permit', 'TAMU')
        ->whereNotNull('Waktu_Berakhir')
        ->where('Waktu_Berakhir', '<', $now)
        ->update([
            'Status_Efektif' => 'EXPIRED',
            'Alasan_Status' => 'Jendela kunjungan berakhir',
            'Updated_At' => $now,
            'Updated_Oleh_Nama' => 'Sistem (Auto-Expire)',
            'Flag_Sync' => 'T',
        ]);

    // Cabang 3 — permit COKLAT, tamunya sudah tercatat keluar.
    //
    // Kehadiran TIDAK PERNAH ditulis HCIS: aplikasi palang menulis
    // Waktu_Aktual_Keluar langsung ke N_VISITOR_Tamu_Efektif, dan kolom di tabel
    // Detail hanya jalur catatan manual. Karena itu dua-duanya dibaca, dan
    // Status_Kunjungan TIDAK dipakai sama sekali — tamu yang lewat palang tetap
    // berstatus TERJADWAL selamanya.
    $permitCoklatKeluar = DB::table('N_VISITOR_Permit_Efektif as p')
        ->where('p.Status_Efektif', 'AKTIF')
        ->where('p.Sumber_Permit', 'TAMU')
        ->whereRaw(
            'EXISTS (SELECT 1
                       FROM N_VISITOR_Tamu_Efektif te
                       LEFT JOIN N_EMI_VISITOR_Transaksi_Tamu_Detail td ON td.No_Transaksi = te.No_Transaksi
                      WHERE te.No_Transaksi = p.No_Transaksi
                        AND COALESCE(te.Waktu_Aktual_Keluar, td.Waktu_Aktual_Keluar) IS NOT NULL)',
        )
        ->update([
            'Status_Efektif' => 'EXPIRED',
            'Alasan_Status' => 'Tamu tercatat keluar',
            'Updated_At' => $now,
            'Updated_Oleh_Nama' => 'Sistem (Auto-Expire)',
            'Flag_Sync' => 'T',
        ]);

    // Cabang 4 — proyeksi TAMU. Pindah dari Tanggal_Kunjungan ke akhir jendela:
    // kunjungan yang menyeberang tengah malam kedaluwarsa kepagian kalau tidak.
    $tamu = DB::table('N_VISITOR_Tamu_Efektif as te')
        ->where('te.Status_Efektif', 'AKTIF')
        ->whereRaw(
            '(SELECT TOP 1 COALESCE(td.Waktu_Selesai_Kunjungan, DATEADD(day, 1, CAST(te.Tanggal_Kunjungan AS datetime2(0))))
                FROM N_EMI_VISITOR_Transaksi_Tamu_Detail td
               WHERE td.No_Transaksi = te.No_Transaksi) < ?',
            [$now],
        )
        ->update([
            'Status_Efektif' => 'EXPIRED',
            'Updated_At' => $now,
            'Updated_Oleh_Nama' => 'Sistem (Auto-Expire)',
            'Flag_Sync' => 'T',
        ]);

    $this->info("Permit karyawan: {$permitKaryawan}, permit coklat (jam): {$permitCoklatJam}, permit coklat (keluar): {$permitCoklatKeluar}, tamu: {$tamu}");

    return self::SUCCESS;
}
```

Perbarui juga blok docblock kelas: catatan "KETERBATASAN SAAT INI" yang menyatakan `Tanggal_Kadaluarsa` permit selalu NULL sudah tidak berlaku untuk permit coklat.

- [ ] **Step 4: Tutup permit seketika di `checkOut()` manual**

Setelah `DB::table(self::T_DETAIL)->...->update(['Waktu_Aktual_Keluar' => now()]);` di `checkOut()`:

```php
// Jalur manual adalah pintu kedua yang setara dengan palang. Tanpa ini,
// permit tamu yang dicatat keluar lewat HCIS baru mati saat sapuan
// berikutnya — sampai 5 menit kemudian.
$this->visitorEfektif->akhiriPermitCoklat($header->No_Transaksi, 'Tamu tercatat keluar');
```

Tambahkan metode itu ke `VisitorEfektifService`, di sebelah `revokePermitEfektif()`:

```php
/**
 * Akhiri permit coklat lebih awal. Berbeda dari revoke: ini bukan pembatalan,
 * melainkan masa berlaku yang habis lebih cepat, sehingga Flag_Batal tetap 'T'
 * dan kolom Dibatalkan_* tetap NULL — tidak ada yang membatalkan apa pun.
 */
public function akhiriPermitCoklat(string $noTransaksi, string $alasan): int
{
    return DB::table('N_VISITOR_Permit_Efektif')
        ->where('No_Transaksi', $noTransaksi)
        ->where('Status_Efektif', 'AKTIF')
        ->where('Sumber_Permit', 'TAMU')
        ->update([
            'Status_Efektif' => 'EXPIRED',
            'Alasan_Status' => $alasan,
            'Updated_At' => now(),
            'Updated_Oleh_Nama' => 'Sistem (Auto-Expire)',
            'Flag_Sync' => 'T',
        ]);
}
```

- [ ] **Step 5: Tulis DML penjadwalan**

`database/dml/DML-2026-078-visitor-expire-tiap-5-menit/data-change.sql`:

```sql
-- Change ID: DML-2026-078-visitor-expire-tiap-5-menit
-- Target: Web_HRIS (development).
--
-- visitor:expire-efektif berjalan harian (05 00 * * *) sejak proyeksi Efektif
-- dibuat, karena masa berlaku permit dan tamu ketika itu murni per-hari. Permit
-- coklat berlaku dalam hitungan JAM dan berakhir juga saat tamunya keluar, jadi
-- ketelitian harian berarti permit yang sudah habis tetap AKTIF di gerbang
-- sampai lewat tengah malam.

UPDATE N_HRIS_Scheduler_Configs
   SET Cron_Expression = '*/5 * * * *',
       Keterangan = 'Expire proyeksi Efektif visitor: permit karyawan per hari, permit coklat per jam dan saat tamu keluar (tiap 5 menit)'
 WHERE Command = 'visitor:expire-efektif';
GO
```

Daftarkan entrinya di `database/ddl/status.json` mengikuti bentuk Task 1 Step 9 (`"type": "DML"`), lalu jalankan ke development dan verifikasi:

```bash
php artisan tinker --execute="echo DB::table('N_HRIS_Scheduler_Configs')->where('Command','visitor:expire-efektif')->value('Cron_Expression');"
```

Harapan: `*/5 * * * *`.

- [ ] **Step 6: Jalankan tes, pastikan HIJAU**

Run: `php artisan test tests/Integration/VISITOR/Operations/PermitCoklatBerakhirTest.php`
Expected: PASS (7 tes).

- [ ] **Step 7: Jalankan suite Visitor penuh**

Perubahan ini menyentuh service dan command bersama, jadi periksa seluruh modul, bukan berkas yang berubah saja.

Run: `php artisan test tests/Integration/VISITOR`
Expected: PASS. `SimulasiPalangTest` dan `SeedPapanHarianDemoTest` khususnya harus tetap hijau.

- [ ] **Step 8: Commit**

```bash
git add app/Console/Commands/VISITOR/Operations/Expiration/ExpireVisitorEfektif.php app/Services/VISITOR/Operations/Expiration/VisitorEfektifService.php app/Http/Controllers/VISITOR/Guest/Visit/GuestRegistrationController.php database/dml/DML-2026-078-visitor-expire-tiap-5-menit database/ddl/status.json tests/Integration/VISITOR/Operations/PermitCoklatBerakhirTest.php
git commit -m "feat(visitor): akhiri permit coklat saat jam habis atau tamu keluar"
```

---

### Task 7: Form tamu — isian jendela jam

**Files:**
- Modify: `resources/js/Pages/VISITOR/Guest/Visit/components/TamuForm.vue`
- Test: `resources/js/Pages/VISITOR/Guest/Visit/components/TamuForm.test.js`

**Interfaces:**
- Consumes: validasi backend Task 2 (`waktu_mulai_kunjungan`, `waktu_selesai_kunjungan`).
- Produces: payload submit membawa kedua kunci itu dalam format `YYYY-MM-DD HH:mm:ss`.

- [ ] **Step 1: Tulis tes yang gagal**

Tambahkan `describe` berikut ke `TamuForm.test.js` yang sudah ada. Berkas itu memakai `shallowMount(TamuForm)` polos, mengisi state lewat `Object.assign(wrapper.vm.form, {...})`, dan memeriksa kontrak payload lewat `wrapper.vm.buildPayload()` — ikuti pola itu, jangan membuat helper baru.

```js
describe('TamuForm jendela jam kunjungan', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        axios.get.mockResolvedValue({
            data: { success: true, result: { plat_codes: ['B'], vehicle_types: [], keperluan: [], jenis_identitas: ['KTP'] } },
        });
    });

    it('mengirim jendela jam, bukan label estimasi', async () => {
        const wrapper = shallowMount(TamuForm);
        Object.assign(wrapper.vm.form, {
            nama_tamu: 'Rina Pratama',
            instansi_asal: 'PT Contoh',
            keperluan: 'Lain-lain',
            keperluan_lain: 'Audit lokasi',
            pic_tujuan: 'Budi / HR',
            tanggal_kunjungan: '2026-09-10',
            jumlah_tamu: 1,
            jenis_kendaraan: 'Tidak Berkendara',
            jam_mulai: '08:00',
            tanggal_selesai: '2026-09-10',
            jam_selesai: '17:00',
        });
        await wrapper.vm.$nextTick();

        const payload = wrapper.vm.buildPayload();

        expect(payload.waktu_mulai_kunjungan).toBe('2026-09-10 08:00:00');
        expect(payload.waktu_selesai_kunjungan).toBe('2026-09-10 17:00:00');
        expect(payload.waktu_estimasi).toBeUndefined();
    });

    it('menolak jendela yang berakhir sebelum mulai', async () => {
        const wrapper = shallowMount(TamuForm);
        Object.assign(wrapper.vm.form, {
            tanggal_kunjungan: '2026-09-10',
            jam_mulai: '17:00',
            tanggal_selesai: '2026-09-10',
            jam_selesai: '08:00',
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.jendelaValid).toBe(false);
    });

    it('menerima jendela yang menyeberang tengah malam', async () => {
        const wrapper = shallowMount(TamuForm);
        Object.assign(wrapper.vm.form, {
            tanggal_kunjungan: '2026-09-10',
            jam_mulai: '20:00',
            tanggal_selesai: '2026-09-11',
            jam_selesai: '06:00',
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.jendelaValid).toBe(true);
    });

    it('tidak lagi merender dropdown estimasi waktu', () => {
        const source = readFileSync(path.resolve(__dirname, 'TamuForm.vue'), 'utf8');

        expect(source).not.toContain('form.waktu_estimasi');
        expect(source).toContain('v-model="form.jam_mulai"');
        expect(source).toContain('v-model="form.jam_selesai"');
    });
});
```

- [ ] **Step 2: Jalankan tes, pastikan MERAH**

Run: `npx vitest run resources/js/Pages/VISITOR/Guest/Visit/components/TamuForm.test.js`
Expected: FAIL — `jendelaValid` tidak ada, payload masih mengirim `waktu_estimasi`.

- [ ] **Step 3: Ganti isian waktu di template**

Ganti blok `HcisSelectOption` untuk `form.waktu_estimasi` (sekitar baris 190-200) dengan empat isian: `jam_mulai` (`type="time"`), `tanggal_selesai` (`type="date"`), `jam_selesai` (`type="time"`). `tanggal_selesai` default mengikuti `form.tanggal_kunjungan` lewat watcher, dan tetap bisa digeser untuk kunjungan yang menyeberang tengah malam.

- [ ] **Step 4: Tambahkan `jendelaValid` dan gabungan payload**

Di `computed`:

```js
waktuMulaiKunjungan() {
    const t = this.form.tanggal_kunjungan;
    const j = this.form.jam_mulai;

    return t && j ? `${t} ${j}:00` : null;
},
waktuSelesaiKunjungan() {
    const t = this.form.tanggal_selesai || this.form.tanggal_kunjungan;
    const j = this.form.jam_selesai;

    return t && j ? `${t} ${j}:00` : null;
},
jendelaValid() {
    const mulai = this.waktuMulaiKunjungan;
    const selesai = this.waktuSelesaiKunjungan;

    if (!mulai || !selesai) {
        return false;
    }

    return new Date(selesai.replace(' ', 'T')) > new Date(mulai.replace(' ', 'T'));
},
```

Di payload submit (sekitar baris 879-890), hapus `waktu_estimasi` dan tambahkan:

```js
waktu_mulai_kunjungan: this.waktuMulaiKunjungan,
waktu_selesai_kunjungan: this.waktuSelesaiKunjungan,
```

Tambahkan `jendelaValid` ke daftar kelengkapan langkah (sekitar baris 677) dan `jam_mulai: ''`, `jam_selesai: ''`, `tanggal_selesai: ''` ke state awal form (sekitar baris 820), lalu hapus `waktu_estimasi: ''` dan `masterWaktuEstimasi`.

- [ ] **Step 5: Tampilkan jendela di langkah pratinjau**

Di daftar ringkasan (sekitar baris 717) ganti entri estimasi menjadi:

```js
{
    label: 'Jendela Kunjungan',
    value: this.jendelaValid
        ? `${this.waktuMulaiKunjungan} s/d ${this.waktuSelesaiKunjungan}`
        : '-',
    isMono: true,
    icon: 'clock-fill',
},
{
    label: 'Permit Akses',
    value: 'Permit COKLAT terbit otomatis selama jendela ini',
    icon: 'shield-check',
},
```

- [ ] **Step 6: Jalankan tes, pastikan HIJAU**

Run: `npx vitest run resources/js/Pages/VISITOR/Guest/Visit/components/TamuForm.test.js`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/VISITOR/Guest/Visit/components/TamuForm.vue resources/js/Pages/VISITOR/Guest/Visit/components/TamuForm.test.js
git commit -m "feat(visitor): isian jendela jam pada form registrasi tamu"
```

---

### Task 8: Riwayat tamu menampilkan status permit coklat

**Files:**
- Modify: `app/Http/Controllers/VISITOR/Guest/Visit/GuestRegistrationController.php` (`baseTamuQuery()` lokal + `getList()`)
- Modify: `resources/js/Pages/VISITOR/Guest/Visit/components/TamuHistory.vue`
- Test: `resources/js/Pages/VISITOR/Guest/Visit/components/TamuHistory.test.js`

**Interfaces:**
- Consumes: `N_VISITOR_Permit_Efektif.Status_Efektif` untuk `No_Transaksi` tamu.
- Produces: setiap baris riwayat membawa `Status_Permit_Coklat` bernilai `AKTIF` / `EXPIRED` / `DIBATALKAN` / `null`.

- [ ] **Step 1: Tulis tes yang gagal**

`TamuHistory.test.js` memuat komponen lewat `axios.get` yang di-mock dan mengembalikan `mockGuests` pada `/transaksitamu-list`. Tambahkan kunci `Status_Permit_Coklat` ke `mockGuests` (baris pertama `'AKTIF'`, kedua `'EXPIRED'`, ketiga `null`), lalu tambahkan `describe` berikut — ikuti cara berkas itu me-mount dan menunggu datanya, jangan membuat helper baru.

```js
describe('TamuHistory status permit coklat', () => {
    it('menampilkan lencana untuk permit yang masih berlaku dan yang sudah habis', async () => {
        const wrapper = await mountDenganData();

        expect(wrapper.text()).toContain('Permit berlaku');
        expect(wrapper.text()).toContain('Permit habis');
    });

    it('tidak menampilkan lencana untuk tamu lama tanpa permit', async () => {
        const wrapper = await mountDenganData();
        const barisTanpaPermit = wrapper.findAll('[data-test="tamu-row"]').at(2);

        expect(barisTanpaPermit.text()).not.toContain('Permit');
    });
});
```

`mountDenganData()` di sini adalah pola mount + `await flushPromises()` yang sudah dipakai berkas itu; angkat menjadi helper lokal di dalam berkas tes bila belum ada. Bila baris tabel belum punya penanda, tambahkan `data-test="tamu-row"` pada elemen barisnya di `TamuHistory.vue` sebagai bagian Step 4.

- [ ] **Step 2: Jalankan tes, pastikan MERAH**

Run: `npx vitest run resources/js/Pages/VISITOR/Guest/Visit/components/TamuHistory.test.js`
Expected: FAIL — teks tidak ditemukan.

- [ ] **Step 3: Bawa statusnya dari backend**

Di `GuestRegistrationController::baseTamuQuery()`, tambahkan ke `select(...)`:

```php
DB::raw('(SELECT TOP 1 pe.Status_Efektif
            FROM N_VISITOR_Permit_Efektif pe
           WHERE pe.No_Transaksi = h.No_Transaksi
             AND pe.Sumber_Permit = \'TAMU\') as Status_Permit_Coklat'),
```

Sub-kueri, bukan JOIN: relasinya satu-ke-paling-banyak-satu dan JOIN tambahan pada kueri yang sudah punya lima tabel berisiko menggandakan baris — kekeliruan yang pernah terjadi di kueri permit.

- [ ] **Step 4: Tampilkan di `TamuHistory.vue`**

Tambahkan lencana di sel status setiap baris:

```vue
<span v-if="row.Status_Permit_Coklat" class="tamu-history__permit" :data-status="row.Status_Permit_Coklat">
    {{ labelPermitCoklat(row.Status_Permit_Coklat) }}
</span>
```

```js
labelPermitCoklat(status) {
    return {
        AKTIF: 'Permit berlaku',
        EXPIRED: 'Permit habis',
        DIBATALKAN: 'Permit dicabut',
    }[status] || '';
},
```

- [ ] **Step 5: Jalankan tes, pastikan HIJAU**

Run: `npx vitest run resources/js/Pages/VISITOR/Guest/Visit/components/TamuHistory.test.js`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/VISITOR/Guest/Visit/GuestRegistrationController.php resources/js/Pages/VISITOR/Guest/Visit/components/TamuHistory.vue resources/js/Pages/VISITOR/Guest/Visit/components/TamuHistory.test.js
git commit -m "feat(visitor): status permit coklat pada riwayat tamu"
```

---

### Task 9: Pisahkan permit karyawan dan permit tamu di monitoring, dashboard, dan export

**Files:**
- Modify: `app/Services/VISITOR/Workflow/Reporting/Dashboard/VisitorDashboardAnalitikService.php`
- Modify: `app/Services/VISITOR/Workflow/Reporting/Dashboard/VisitorDashboardOperasionalService.php`
- Modify: `app/Http/Controllers/VISITOR/Workflow/Reporting/LaporanVisitorController.php`
- Modify: `app/Services/VISITOR/Workflow/Reporting/Export/VisitorPermitDataset.php`
- Test: `tests/Integration/VISITOR/Workflow/Reporting/PermitCoklatTerpisahDiLaporanTest.php`

**Interfaces:**
- Consumes: `Sumber_Permit`.
- Produces: dimensi/penyaring `sumber_permit` di Export Studio, dan angka permit karyawan yang tidak berubah oleh kehadiran permit coklat.

> **Kenapa ini wajib, bukan opsional.** Di development sekarang ada 24 transaksi permit karyawan berbanding 83 tamu. Begitu setiap tamu menerbitkan permit, setiap angka permit yang tidak menyaring `Sumber_Permit` akan terlihat meledak padahal tidak ada yang berubah. Periksa **setiap** tempat yang menghitung permit satu per satu; menyaring di satu tempat lalu mengasumsikan sisanya beres adalah cara kegagalan ini bersembunyi.

- [ ] **Step 1: Inventarisasi tempat yang menghitung permit**

```bash
grep -rn "N_VISITOR_Permit_Efektif" app/Services/VISITOR app/Http/Controllers/VISITOR
```

Tulis daftarnya ke ringkasan task. Setiap baris hasil harus dijawab: disaring `KARYAWAN`, disaring `TAMU`, atau sengaja mencakup keduanya.

- [ ] **Step 2: Tulis tes yang gagal**

```php
<?php

namespace Tests\Integration\VISITOR\Workflow\Reporting;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Permit coklat tidak boleh menenggelamkan angka permit karyawan.
 *
 * Perbandingannya RELATIF (selisih sebelum dan sesudah), bukan angka mutlak:
 * database development berisi data yang berubah-ubah, dan penjaga yang
 * menegaskan angka tetap akan merah karena alasan yang salah.
 */
class PermitCoklatTerpisahDiLaporanTest extends TestCase
{
    use DatabaseTransactions;

    public function test_permit_coklat_tidak_menambah_hitungan_permit_karyawan(): void
    {
        $sebelum = $this->jumlahPermitKaryawan();

        $this->buatPermitCoklatMentah();

        $this->assertSame(
            $sebelum,
            $this->jumlahPermitKaryawan(),
            'Permit coklat ikut terhitung sebagai permit karyawan.',
        );
    }

    private function jumlahPermitKaryawan(): int
    {
        // Tanda tangan sebenarnya: ringkasan(string $dari, string $sampai, ?string $domain = null).
        $stats = app(\App\Services\VISITOR\Workflow\Reporting\Dashboard\VisitorDashboardAnalitikService::class)
            ->ringkasan(now()->subMonth()->toDateString(), now()->addMonth()->toDateString(), 'permit');

        return (int) ($stats['permit_aktif'] ?? 0);
    }

    private function buatPermitCoklatMentah(): string
    {
        $nomor = 'VSR-LAP-'.Str::upper(Str::random(8));

        DB::table('N_VISITOR_Permit_Efektif')->insert([
            'Kode_Perusahaan' => '001', 'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => 'ZZRVK1', 'Nama_Pemohon' => 'PIC Uji',
            'Nama_Kategori_Permit' => 'COKLAT', 'Sumber_Permit' => 'TAMU',
            'Status_Efektif' => 'AKTIF',
            'Waktu_Mulai_Berlaku' => now(), 'Waktu_Berakhir' => now()->addHours(4),
            'Tanggal_Mulai_Berlaku' => now()->toDateString(),
            'Tanggal_Kadaluarsa' => now()->toDateString(),
            'Disetujui_Oleh_Nama' => 'Sistem Uji', 'Disetujui_At' => now(),
            'Riwayat_Approval' => '[]', 'Dibuat_At' => now(),
            'Dibuat_Oleh_Nama' => 'Sistem Uji', 'Updated_At' => now(), 'Flag_Sync' => 'T',
        ]);

        return $nomor;
    }
}
```

Kunci `permit_aktif` harus dicocokkan dengan kunci yang benar-benar dikembalikan `ringkasan()` — buka `VisitorDashboardAnalitikService::ringkasan()` (baris 46) dan pakai nama kunci permit aktif yang ada di sana.

- [ ] **Step 3: Jalankan tes, pastikan MERAH**

Run: `php artisan test tests/Integration/VISITOR/Workflow/Reporting/PermitCoklatTerpisahDiLaporanTest.php`
Expected: FAIL — hitungan bertambah 1.

- [ ] **Step 4: Tambahkan penyaring di setiap tempat dari Step 1**

Pola penyaringnya:

```php
->whereRaw("ISNULL(Sumber_Permit, 'KARYAWAN') = 'KARYAWAN'")
```

Untuk kueri yang memakai alias tabel, sertakan aliasnya: `ISNULL(pe.Sumber_Permit, 'KARYAWAN')`.

- [ ] **Step 5: Tambahkan dimensi `sumber_permit` ke Export Studio**

Di `VisitorPermitDataset`, di sebelah dimensi `nama_durasi` yang sudah ada:

```php
new Dimensi('sumber_permit', 'Sumber Permit', 'e.Sumber_Permit', 'teks', 'permit'),
new Dimensi('waktu_mulai_berlaku', 'Mulai Berlaku (Jam)', 'e.Waktu_Mulai_Berlaku', 'tanggal', 'masa_berlaku', false, 'tanggal_jam'),
new Dimensi('waktu_berakhir', 'Berakhir (Jam)', 'e.Waktu_Berakhir', 'tanggal', 'masa_berlaku', false, 'tanggal_jam'),
```

Tanda tangannya `Dimensi(key, label, select, tipe, grup, sensitif = false, format = 'teks')` — argumen ketujuh `'tanggal_jam'` itulah yang membuat jamnya ikut tampil, bukan hanya tanggalnya. Grup `'permit'` dan `'masa_berlaku'` mengikuti dimensi yang sudah ada di berkas itu.

- [ ] **Step 6: Jalankan tes, pastikan HIJAU**

Run: `php artisan test tests/Integration/VISITOR/Workflow/Reporting/PermitCoklatTerpisahDiLaporanTest.php`
Expected: PASS.

- [ ] **Step 7: Jalankan suite penuh**

Penjaga baru menyentuh service yang dipakai banyak halaman.

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Services/VISITOR/Workflow/Reporting app/Http/Controllers/VISITOR/Workflow/Reporting tests/Integration/VISITOR/Workflow/Reporting/PermitCoklatTerpisahDiLaporanTest.php
git commit -m "feat(visitor): pisahkan permit karyawan dan permit coklat di laporan"
```

---

## Verifikasi Akhir

- [ ] `php artisan test` hijau seluruhnya (bukan hanya `tests/Integration/VISITOR`).
- [ ] `npx vitest run resources/js/Pages/VISITOR` hijau.
- [ ] `database/ddl/status.json` memuat `DDL-2026-077` dan `DML-2026-078` dengan checksum yang cocok terhadap berkas forward yang benar-benar diuji.
- [ ] Laporan dua bagian sesuai `AGENTS.md`: **Agent completed** (berkas, eksekusi & verifikasi development, perekaman tracker) dan **User tasks** (review, eksekusi manual SQL Tracker ke production, verifikasi production).
- [ ] Dua pertanyaan integrasi ke pemilik aplikasi gate sudah diajukan dan jawabannya dicatat: (a) sanggupkah memproses baris permit tanpa plat nomor, (b) apakah ia membandingkan `Waktu_Mulai_Berlaku`/`Tanggal_Mulai_Berlaku` sebelum meloloskan, bukan hanya membaca `Status_Efektif`.
