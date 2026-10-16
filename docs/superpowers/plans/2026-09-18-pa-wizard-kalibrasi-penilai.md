# PA Saya — Wizard Tugas Penilaian dan Kalibrasi Penilai: Rencana Implementasi

> **Untuk agent pelaksana:** WAJIB memakai superpowers:executing-plans (atau
> superpowers:subagent-driven-development bila pemilik proyek memintanya) untuk menjalankan
> rencana ini tugas demi tugas. Langkah memakai sintaks kotak centang (`- [ ]`).

**Tujuan:** Tab "Tugas Penilaian" di `/performance-appraisal/saya` menjadi wizard berurutan
per kategori penilai, dengan Kalibrasi Penilai di tiap transisi kategori.

**Arsitektur:** Dua kolom baru di `N_PA_Rater_Category` (urutan, penanda boleh-kalibrasi) dan
dua tabel jejak Kalibrasi Penilai yang hanya ditambah. Skor efektif dibaca lewat satu pintu
`PaSkorEfektif`, yang juga dipakai perhitungan. Wizard dilayani `PaWizardPenilaianService` di
atas lingkup "tugas saya" milik `PaPesertaService`, dan ditampilkan lima berkas frontend kecil
yang menggantikan `PaSayaAssignmentPanel.vue` di tab itu.

**Teknologi:** Laravel 10 (PHP 8.2), SQL Server (`sqlsrv`), Inertia + Vue 3 (`<script setup>`),
Vitest + @vue/test-utils, PHPUnit Feature test dengan `DatabaseTransactions`.

**Spec:** `docs/superpowers/specs/2026-09-18-pa-wizard-kalibrasi-penilai-design.md`

## Batasan global

- Modul **KPI** → submodul **Performance Appraisal** → fitur **PA Saya / Tugas Penilaian**. Tidak ada berkas bisnis baru di akar generik atau folder peran.
- Basis data sasaran: `Web_HRIS` di `team311.dyndns.info:1433` (development). Verifikasi `SELECT DB_NAME()` + `@@SERVERNAME` sebelum DDL/DML. Tidak ada migration Laravel, tidak ada `DROP`/`TRUNCATE` yang dijalankan agent.
- Artefak: `database/ddl/DDL-2026-119-pa-kalibrasi-penilai/{forward,drop}.sql`, `database/dml/DML-2026-124-urutan-pengisian-kategori-penilai/data-change.sql`, dokumen di `docs/changes/DDL-2026-119-pa-kalibrasi-penilai/README.md`, entri di `database/ddl/status.json`. Nomor diperiksa ulang sebelum dipakai.
- Nilai awal urutan (dikonfirmasi pemilik proyek 18 September 2026): `SELF = 1`, `ATASAN = 2`, `PEER = 3`, `BAWAHAN = 4`. `Flag_Boleh_Kalibrasi` seluruhnya tetap `'T'`.
- Amplop respons API `result`. Galat memakai `ResponseHelper::errorWithCode()` dengan `error_code` + `request_id`.
- Lingkup selalu dari user yang login (`Auth::id()` → `Karyawan.UserID_Web`). Tidak ada id peserta diterima dari pemanggil. Penugasan milik orang lain dijawab 404.
- Istilah di layar: **Kalibrasi Penilai** (baru) dan **Kalibrasi Forum** (yang lama). Kode dan tabel baru tidak memakai nama milik kalibrasi forum.
- Frontend: `useModalFocus`, `useScrollLock`, `urlAman`, gerak lewat `[data-hcis-motion]` (dilarang `prefers-reduced-motion`), warna dari token lapis peran, skeleton wajib, halaman tanpa gulir menyamping.
- **Uji ditulis di tiap tugas tetapi TIDAK dijalankan** sampai pemilik proyek ACC (aturan tersimpan `jangan-uji-sebelum-acc`, dan `AGENTS.md` meminta lingkup uji ditanyakan saat fitur rampung). Seluruh langkah "jalankan" dikumpulkan di **Tugas 14**. Pemeriksaan statis cepat (`php -l`, Pint, grep) boleh kapan saja.
- Jangan pernah `git add -A` / `git add .`. Tambah berkas per jalur.

## Peta berkas

| Berkas | Status | Tanggung jawab |
|---|---|---|
| `database/ddl/DDL-2026-119-pa-kalibrasi-penilai/forward.sql` | baru | Dua kolom, indeks unik tersaring, dua tabel kalibrasi |
| `database/ddl/DDL-2026-119-pa-kalibrasi-penilai/drop.sql` | baru | Jalan pulang manual, tidak dijalankan agent |
| `database/dml/DML-2026-124-urutan-pengisian-kategori-penilai/data-change.sql` | baru | Nilai awal urutan |
| `docs/changes/DDL-2026-119-pa-kalibrasi-penilai/README.md` | baru | Catatan change |
| `docs/database/tables/kpi/n-pa-kalibrasi-penilai.md`, `...-detail.md` | baru | Dokumen tabel |
| `docs/database/tables/kpi/n-pa-rater-category.md` | ubah | Dua kolom baru |
| `app/Services/KPI/PerformanceAppraisal/PaKalibrasiPenilaiException.php` | baru | Penolakan berkode stabil |
| `app/Services/KPI/PerformanceAppraisal/PaCycleGate.php` | ubah | Operasi `KALIBRASI_PENILAI` |
| `app/Services/KPI/PerformanceAppraisal/PaSkorEfektif.php` | baru | Satu pintu skor efektif + jawaban naratif payload terakhir |
| `app/Services/KPI/PerformanceAppraisal/PaResultCalculationService.php` | ubah | Pemuat skor beralih ke `PaSkorEfektif` |
| `app/Console/Commands/KPI/PerformanceAppraisal/PaVerifyCommand.php` | ubah | Menerapkan kalibrasi secara independen |
| `app/Services/KPI/PerformanceAppraisal/PaKalibrasiPenilaiService.php` | baru | Simpan kalibrasi + riwayat |
| `app/Services/KPI/PerformanceAppraisal/PaPesertaService.php` | ubah | `karyawan()`, `kueriPenugasan()`, `barisTugas()` jadi publik; `urutanKategori()` membaca kolom baru |
| `app/Services/KPI/PerformanceAppraisal/PaWizardPenilaianService.php` | baru | Kerangka wizard, lembar, pratinjau |
| `app/Http/Controllers/KPI/PerformanceAppraisal/Peserta/PaWizardPenilaianController.php` | baru | Empat endpoint penilai |
| `routes/KPI/PerformanceAppraisal/Peserta/PerformanceAppraisalPeserta.php` | ubah | Rute wizard + kalibrasi |
| `app/Services/KPI/PerformanceAppraisal/PaJenisPenilaiMatriksService.php` | ubah | Tata urutan, setel penanda, kolom di `matriks()` |
| `app/Http/Controllers/KPI/PerformanceAppraisal/MasterData/PaMasterDataController.php` | ubah | Dua endpoint admin |
| `routes/KPI/PerformanceAppraisal/MasterData/PerformanceAppraisalMaster.php` | ubah | Rute admin |
| `app/Services/KPI/PerformanceAppraisal/PaParticipantReadService.php` | ubah | `kalibrasi_penilai` di `detailHasil()` |
| `tests/Feature/KPI/PerformanceAppraisal/Support/MenyiapkanLembarPenilaian.php` | baru | Fixture lembar terisi |
| `tests/Feature/KPI/PerformanceAppraisal/{SkorEfektif,KalibrasiPenilai,WizardPenilaian,KalibrasiPenilaiAdmin,SkorEfektifSatuPintu}Test.php` | baru | Uji backend |
| `tests/Feature/KPI/PerformanceAppraisal/{CycleGateTest,KontrakResponsTest}.php` | ubah | Operasi baru, kontrak baru |
| `resources/js/Pages/KPI/PerformanceAppraisal/api/paApi.js` | ubah | `PaApiError` membawa `errorCode`/`requestId`; fungsi baru |
| `resources/js/Pages/KPI/PerformanceAppraisal/api/fixtures/saya-wizard*.json`, `saya-lembar.json`, `saya-pratinjau.json` | baru | Kontrak FE |
| `resources/js/Pages/KPI/PerformanceAppraisal/Peserta/composables/usePaWizardPenilaian.js` (+ `.test.js`) | baru | Keadaan wizard |
| `resources/js/Pages/KPI/PerformanceAppraisal/Peserta/components/PaWizard*.vue` (+ `wizard.test.js`) | baru | Empat komponen tampilan |
| `resources/js/Pages/KPI/PerformanceAppraisal/Peserta/components/PaSayaWizardPenilaian.vue` | baru | Perakit wizard di tab |
| `resources/js/Pages/KPI/PerformanceAppraisal/Peserta/saya.vue` | ubah | Tab tugas memakai perakit wizard |
| `resources/js/Pages/KPI/PerformanceAppraisal/MasterData/components/PaPengaturanKategoriPenilai.vue` (+ test) | baru | Urutan + penanda di layar master |
| `resources/js/Pages/KPI/PerformanceAppraisal/MasterData/jenis-penilai.vue` | ubah | Memasang panel pengaturan |
| `resources/js/Pages/KPI/PerformanceAppraisal/Cycle/components/PaResultDialog.vue` | ubah | Bagian riwayat Kalibrasi Penilai |
| `docs/superpowers/specs/2026-09-01-kontrak-api-performance-appraisal.md` | ubah | Kontrak endpoint baru |

---

### Tugas 1: Artefak database dan eksekusi di development

**Berkas:**
- Buat: `database/ddl/DDL-2026-119-pa-kalibrasi-penilai/forward.sql`
- Buat: `database/ddl/DDL-2026-119-pa-kalibrasi-penilai/drop.sql`
- Buat: `database/dml/DML-2026-124-urutan-pengisian-kategori-penilai/data-change.sql`
- Buat: `docs/changes/DDL-2026-119-pa-kalibrasi-penilai/README.md`
- Buat: `docs/database/tables/kpi/n-pa-kalibrasi-penilai.md`, `docs/database/tables/kpi/n-pa-kalibrasi-penilai-detail.md`
- Ubah: `docs/database/tables/kpi/n-pa-rater-category.md`, `database/ddl/status.json`

**Antarmuka:**
- Menghasilkan: kolom `N_PA_Rater_Category.Urutan_Pengisian smallint NULL`, `N_PA_Rater_Category.Flag_Boleh_Kalibrasi char(1) NOT NULL DEFAULT 'T'`; tabel `N_PA_Kalibrasi_Penilai(Id_PA_Kalibrasi_Penilai, PA_Assignment_Id, Alasan, Created_At, Created_By)` dan `N_PA_Kalibrasi_Penilai_Detail(Id_PA_Kalibrasi_Penilai_Detail, PA_Kalibrasi_Penilai_Id, PA_Competency_Id, Skor_Sebelum, Skor_Sesudah, Skor_Maksimal)`.

- [ ] **Langkah 1: Periksa ulang nomor change**

```bash
node -e "const d=JSON.parse(require('fs').readFileSync('database/ddl/status.json','utf8'));for(const t of ['DDL','DML']){const n=d.changes.map(c=>c.change_id).filter(i=>i.startsWith(t+'-2026-')).map(i=>+i.split('-')[2]);console.log(t,Math.max(...n));}"
```
Harapan: `DDL 118`, `DML 123`. Kalau lebih besar, naikkan nomor di seluruh rencana ini sebelum lanjut.

- [ ] **Langkah 2: Tulis `forward.sql`**

```sql
/* Change ID: DDL-2026-119-pa-kalibrasi-penilai
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-064 (N_PA_Rater_Category), DDL-2026-075 (N_PA_Assignment),
                 DDL-2026-067 (N_PA_Competency).
   Scope: urutan pengisian dan penanda boleh-kalibrasi pada N_PA_Rater_Category; dua tabel
          jejak Kalibrasi Penilai.
   Rancangan: docs/superpowers/specs/2026-09-18-pa-wizard-kalibrasi-penilai-design.md
*/

/* 1. Urutan pengisian. NULL = belum diurutkan; nilainya data bisnis, masuk lewat DML. */
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Rater_Category') AND name = 'Urutan_Pengisian'
)
    ALTER TABLE N_PA_Rater_Category ADD Urutan_Pengisian smallint NULL;
GO

/* 2. Penanda boleh-kalibrasi. Bawaan 'T': fitur tertutup sampai admin menyalakannya. */
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Rater_Category') AND name = 'Flag_Boleh_Kalibrasi'
)
    ALTER TABLE N_PA_Rater_Category
        ADD Flag_Boleh_Kalibrasi char(1) NOT NULL
            CONSTRAINT DF_PA_Rater_Category_Boleh_Kalibrasi DEFAULT ('T');
GO

/* 3. Dua kategori aktif tidak boleh berebut langkah yang sama. Tersaring IS NOT NULL
      karena SQL Server menganggap NULL sama di indeks unik. */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Rater_Category') AND name = 'UX_PA_Rater_Category_Urutan'
)
    CREATE UNIQUE INDEX UX_PA_Rater_Category_Urutan
        ON N_PA_Rater_Category (Urutan_Pengisian)
        WHERE Flag_Aktif = 'Y' AND Urutan_Pengisian IS NOT NULL;
GO

/* 4. Kepala: satu baris per tindakan simpan penilai atas satu orang. */
IF OBJECT_ID('N_PA_Kalibrasi_Penilai', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Kalibrasi_Penilai (
        Id_PA_Kalibrasi_Penilai int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_PA_Kalibrasi_Penilai PRIMARY KEY,
        PA_Assignment_Id int NOT NULL
            CONSTRAINT FK_PA_Kalibrasi_Penilai_Assignment
                REFERENCES N_PA_Assignment (Id_PA_Assignment),
        Alasan nvarchar(500) NOT NULL,
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Kalibrasi_Penilai_Created_At DEFAULT (sysdatetime()),
        Created_By int NULL
    );
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Kalibrasi_Penilai') AND name = 'IX_PA_Kalibrasi_Penilai_Assignment'
)
    CREATE INDEX IX_PA_Kalibrasi_Penilai_Assignment
        ON N_PA_Kalibrasi_Penilai (PA_Assignment_Id, Id_PA_Kalibrasi_Penilai DESC);
GO

/* 5. Rincian: satu baris per kompetensi yang diubah pada satu tindakan. */
IF OBJECT_ID('N_PA_Kalibrasi_Penilai_Detail', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Kalibrasi_Penilai_Detail (
        Id_PA_Kalibrasi_Penilai_Detail int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_PA_Kalibrasi_Penilai_Detail PRIMARY KEY,
        PA_Kalibrasi_Penilai_Id int NOT NULL
            CONSTRAINT FK_PA_Kalibrasi_Penilai_Detail_Kepala
                REFERENCES N_PA_Kalibrasi_Penilai (Id_PA_Kalibrasi_Penilai),
        PA_Competency_Id int NOT NULL
            CONSTRAINT FK_PA_Kalibrasi_Penilai_Detail_Competency
                REFERENCES N_PA_Competency (Id_PA_Competency),
        Skor_Sebelum decimal(6,2) NOT NULL,
        Skor_Sesudah decimal(6,2) NOT NULL,
        Skor_Maksimal decimal(6,2) NOT NULL,
        CONSTRAINT CK_PA_Kalibrasi_Penilai_Detail_Rentang
            CHECK (Skor_Maksimal > 0
                   AND Skor_Sesudah >= 0 AND Skor_Sesudah <= Skor_Maksimal
                   AND Skor_Sebelum >= 0)
    );
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Kalibrasi_Penilai_Detail') AND name = 'UX_PA_Kalibrasi_Penilai_Detail_Kompetensi'
)
    CREATE UNIQUE INDEX UX_PA_Kalibrasi_Penilai_Detail_Kompetensi
        ON N_PA_Kalibrasi_Penilai_Detail (PA_Kalibrasi_Penilai_Id, PA_Competency_Id);
GO

/* 6. Pendaftaran perubahan. */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-119')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
        Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker,
        Status_Production, Status_Verification, Catatan, Created_By
    )
    VALUES (
        'DDL-2026-119',
        'DDL',
        'Urutan pengisian dan Kalibrasi Penilai pada Performance Appraisal',
        'Wizard tugas penilaian berjalan per kategori penilai menurut Urutan_Pengisian. Pada kategori ber-Flag_Boleh_Kalibrasi = Y, penilai boleh mengubah skornya per kompetensi sebelum mengirim; setiap perubahan tercatat di N_PA_Kalibrasi_Penilai(_Detail) tanpa menyentuh N_PA_Assignment_Score.',
        'database/ddl/DDL-2026-119-pa-kalibrasi-penilai/forward.sql',
        'docs/changes/DDL-2026-119-pa-kalibrasi-penilai/README.md',
        'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING',
        'Kamus hidup atas keputusan pemilik proyek 18 September 2026 (menyimpang dari K26). Kalibrasi berkunci (penugasan, kompetensi) supaya selamat saat petakanUlang() menonaktifkan baris skor lama. Tabel kalibrasi hanya ditambah.',
        NULL
    );
END;
GO
```

Rentang `CHECK` di rincian mengikuti preseden `CK_PA_Competency_Ambang` (DDL-2026-096): ia menjaga rentang angka, bukan daftar nilai bisnis yang berkembang.

- [ ] **Langkah 3: Tulis `drop.sql`**

```sql
/* Change ID: DDL-2026-119-pa-kalibrasi-penilai — PEMBATALAN.

   TIDAK DIJALANKAN AGEN. Jalan pulang bila perubahannya harus dicabut, dilakukan manusia
   lewat SQL Tracker.

   PERHATIAN: mencabut kedua tabel MEMBUANG seluruh jejak Kalibrasi Penilai. Hasil yang
   sudah dihitung dari skor terkalibrasi tetap tersimpan, tetapi asal angkanya tidak lagi
   bisa dijelaskan — dan menghitung ulang sesudahnya memakai skor asli HC Learn.
*/

IF OBJECT_ID('N_PA_Kalibrasi_Penilai_Detail', 'U') IS NOT NULL
    DROP TABLE N_PA_Kalibrasi_Penilai_Detail;
GO

IF OBJECT_ID('N_PA_Kalibrasi_Penilai', 'U') IS NOT NULL
    DROP TABLE N_PA_Kalibrasi_Penilai;
GO

IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Rater_Category') AND name = 'UX_PA_Rater_Category_Urutan'
)
    DROP INDEX UX_PA_Rater_Category_Urutan ON N_PA_Rater_Category;
GO

IF EXISTS (
    SELECT 1 FROM sys.default_constraints
    WHERE parent_object_id = OBJECT_ID('N_PA_Rater_Category') AND name = 'DF_PA_Rater_Category_Boleh_Kalibrasi'
)
    ALTER TABLE N_PA_Rater_Category DROP CONSTRAINT DF_PA_Rater_Category_Boleh_Kalibrasi;
GO

IF EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Rater_Category') AND name = 'Flag_Boleh_Kalibrasi'
)
    ALTER TABLE N_PA_Rater_Category DROP COLUMN Flag_Boleh_Kalibrasi;
GO

IF EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Rater_Category') AND name = 'Urutan_Pengisian'
)
    ALTER TABLE N_PA_Rater_Category DROP COLUMN Urutan_Pengisian;
GO

DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-119';
GO
```

- [ ] **Langkah 4: Tulis `data-change.sql` (DML-2026-124)**

```sql
/* Change ID: DML-2026-124-urutan-pengisian-kategori-penilai
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-119-pa-kalibrasi-penilai.
   Scope: nilai awal N_PA_Rater_Category.Urutan_Pengisian. Dikonfirmasi pemilik proyek
          18 September 2026: SELF 1, ATASAN 2, PEER 3, BAWAHAN 4.

   Satu pernyataan UPDATE ... CASE: indeks unik UX_PA_Rater_Category_Urutan diperiksa per
   pernyataan, sehingga menukar posisi di dalam satu pernyataan tidak menabraknya.
   Penyaring "Urutan_Pengisian IS NULL" membuatnya aman diulang dan tidak menimpa urutan
   yang sudah ditata admin lewat layar.
*/

UPDATE N_PA_Rater_Category
SET Urutan_Pengisian = CASE Kode_Kategori
        WHEN 'SELF' THEN 1
        WHEN 'ATASAN' THEN 2
        WHEN 'PEER' THEN 3
        WHEN 'BAWAHAN' THEN 4
    END,
    Updated_At = sysdatetime()
WHERE Kode_Kategori IN ('SELF', 'ATASAN', 'PEER', 'BAWAHAN')
  AND Urutan_Pengisian IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM N_PA_Rater_Category x
      WHERE x.Urutan_Pengisian IS NOT NULL AND x.Flag_Aktif = 'Y'
  );
GO

SELECT Kode_Kategori, Urutan_Pengisian, Flag_Boleh_Kalibrasi
FROM N_PA_Rater_Category
ORDER BY Urutan_Pengisian;
GO
```

Penyaring `NOT EXISTS` sengaja: bila admin sudah menata sebagian urutan lewat layar, DML ini tidak mencampurkan urutan bawaannya ke atasnya.

- [ ] **Langkah 5: Tulis README change dan dokumen tabel**

`docs/changes/DDL-2026-119-pa-kalibrasi-penilai/README.md` memuat bagian: Metadata (change ID, target, status, checksum), Deskripsi (tabel kolom), Latar belakang (rujuk spec §1–2), Keputusan fisik (NULL-able + indeks tersaring; kunci (penugasan, kompetensi); hanya-tambah; `CHECK` rentang), Kamus hidup (W5, penyimpangan sadar dari K26), Artefak (tiga jalur), Verifikasi development (diisi di Langkah 8), Sisa pekerjaan user (tinjau, catat di SQL Tracker, jalankan di production, verifikasi production).

Dua dokumen tabel baru mengikuti bentuk `n-pa-rater-category.md`: Purpose, Ownership and change, Columns, Indexes and constraints, Application usage (`PaSkorEfektif`, `PaKalibrasiPenilaiService`, `PaVerifyCommand`, `PaParticipantReadService`). `n-pa-rater-category.md` mendapat dua baris kolom baru dan satu paragraf "Urutan dan kalibrasi (DDL-2026-119)".

- [ ] **Langkah 6: Hitung checksum dan catat status**

```bash
sha256sum database/ddl/DDL-2026-119-pa-kalibrasi-penilai/forward.sql database/dml/DML-2026-124-urutan-pengisian-kategori-penilai/data-change.sql
```

Tambahkan dua entri ke akhir array `changes` di `database/ddl/status.json` (indentasi 4 spasi, mengikuti bentuk entri DDL-2026-096 dan DML-2026-122), dengan `status: "APPROVED"`, `development: "PENDING"`, `sql_tracker: "PENDING"`, `production: "PENDING"`, `artifact_revision: "r1"`, dan checksum dari perintah di atas. Validasi: `node -e "JSON.parse(require('fs').readFileSync('database/ddl/status.json','utf8'))"`.

- [ ] **Langkah 7: Verifikasi sasaran lalu jalankan di development**

Tampilkan SQL lengkap kepada pemilik proyek, lalu:

```bash
unset DATABASE_URL && php artisan tinker --execute="echo DB::selectOne('SELECT DB_NAME() d, @@SERVERNAME s')->d;"
```
Harapan: `Web_HRIS`. Jalankan `forward.sql` batch demi batch (pisah pada `GO`) lewat `DB::unprepared()`, lalu `data-change.sql`. Jalankan `forward.sql` sekali lagi: harus no-op.

- [ ] **Langkah 8: Verifikasi hasil dan catat**

```bash
unset DATABASE_URL && php artisan tinker --execute="
echo json_encode(DB::table('N_PA_Rater_Category')->orderBy('Urutan_Pengisian')->get(['Kode_Kategori','Urutan_Pengisian','Flag_Boleh_Kalibrasi'])).PHP_EOL;
foreach(['N_PA_Kalibrasi_Penilai','N_PA_Kalibrasi_Penilai_Detail'] as \$t) echo \$t.' '.(DB::selectOne(\"SELECT OBJECT_ID(?, 'U') i\",[\$t])->i ? 'ADA' : 'HILANG').PHP_EOL;
"
```
Harapan: SELF 1, ATASAN 2, PEER 3, BAWAHAN 4, semua `T`; kedua tabel `ADA`. Isi bagian "Verifikasi development" di README, ubah status kedua entri menjadi `TRACKER_PENDING` (`development: "DONE"`, `verification: "DEV_DONE"`).

- [ ] **Langkah 9: Commit**

```bash
git add database/ddl/DDL-2026-119-pa-kalibrasi-penilai/forward.sql database/ddl/DDL-2026-119-pa-kalibrasi-penilai/drop.sql database/dml/DML-2026-124-urutan-pengisian-kategori-penilai/data-change.sql docs/changes/DDL-2026-119-pa-kalibrasi-penilai/README.md docs/database/tables/kpi/n-pa-kalibrasi-penilai.md docs/database/tables/kpi/n-pa-kalibrasi-penilai-detail.md docs/database/tables/kpi/n-pa-rater-category.md database/ddl/status.json
git commit -m "feat(pa): DDL-2026-119 dan DML-2026-124 untuk Kalibrasi Penilai"
```

---

### Tugas 2: Exception berkode dan operasi gerbang

**Berkas:**
- Buat: `app/Services/KPI/PerformanceAppraisal/PaKalibrasiPenilaiException.php`
- Ubah: `app/Services/KPI/PerformanceAppraisal/PaCycleGate.php` (konstanta operasi + baris `IZIN`)
- Uji: `tests/Feature/KPI/PerformanceAppraisal/CycleGateTest.php`

**Antarmuka:**
- Menghasilkan: `PaKalibrasiPenilaiException(string $message, string $errorCode, int $httpStatus)` dengan konstanta `PENUGASAN_TIDAK_DITEMUKAN`, `SIKLUS_TIDAK_TERBUKA`, `LEMBAR_BEKU`, `KALIBRASI_DITUTUP`, `SKOR_DI_LUAR_RENTANG`, `KOMPETENSI_TIDAK_DI_LEMBAR`, `ALASAN_WAJIB`, `TIDAK_ADA_PERUBAHAN`, `LANGKAH_TIDAK_DIKENAL`; `PaCycleGate::KALIBRASI_PENILAI` yang hanya boleh pada `OPEN`.

- [ ] **Langkah 1: Tulis uji gerbang**

Tambahkan ke `CycleGateTest`:

```php
    /**
     * Kalibrasi Penilai hanya selama pengisian terbuka.
     *
     * Berbeda dari KALIBRASI (forum) yang juga menerima CLOSED: penutupan pengisian melepas
     * seluruh lembar TERKUMPUL menjadi SELESAI, jadi sesudah CLOSED tidak ada lagi lembar yang
     * boleh diubah penilainya.
     */
    public function test_kalibrasi_penilai_hanya_saat_open(): void
    {
        $this->assertTrue($this->gate->boleh(PaCycleGate::KALIBRASI_PENILAI, $this->siklus('OPEN')));

        foreach (['DRAFT', 'PREPARED', 'LOCKED', 'CLOSED', 'PUBLISHED'] as $status) {
            $this->assertFalse(
                $this->gate->boleh(PaCycleGate::KALIBRASI_PENILAI, $this->siklus($status)),
                "KALIBRASI_PENILAI tidak boleh diizinkan saat {$status}."
            );
        }
    }
```

- [ ] **Langkah 2: Tulis exception**

```php
<?php

namespace App\Services\KPI\PerformanceAppraisal;

use RuntimeException;

/**
 * Penolakan Kalibrasi Penilai yang membawa kode stabil.
 *
 * Layar wizard bercabang pada `errorCode`, bukan pada kalimat: "lembar sudah terkirim" dan
 * "kategori ini tidak boleh dikalibrasi" menuntut tindakan berbeda, dan kalimatnya boleh
 * diperbaiki kapan saja tanpa mematahkan layar.
 */
class PaKalibrasiPenilaiException extends RuntimeException
{
    public const PENUGASAN_TIDAK_DITEMUKAN = 'PA_PENUGASAN_TIDAK_DITEMUKAN';

    public const SIKLUS_TIDAK_TERBUKA = 'PA_SIKLUS_TIDAK_TERBUKA';

    public const LEMBAR_BEKU = 'PA_LEMBAR_BEKU';

    public const KALIBRASI_DITUTUP = 'PA_KALIBRASI_DITUTUP';

    public const SKOR_DI_LUAR_RENTANG = 'PA_SKOR_DI_LUAR_RENTANG';

    public const KOMPETENSI_TIDAK_DI_LEMBAR = 'PA_KOMPETENSI_TIDAK_DI_LEMBAR';

    public const ALASAN_WAJIB = 'PA_ALASAN_WAJIB';

    public const TIDAK_ADA_PERUBAHAN = 'PA_TIDAK_ADA_PERUBAHAN';

    public const LANGKAH_TIDAK_DIKENAL = 'PA_LANGKAH_TIDAK_DIKENAL';

    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $httpStatus,
    ) {
        parent::__construct($message);
    }
}
```

`KOMPETENSI_TIDAK_DI_LEMBAR` tidak ada di tabel galat spec §6; ditambahkan karena mengirim kompetensi yang tidak ada di lembar bukan "skor di luar rentang", dan kode yang salah menyesatkan layar. Catat penambahan ini di kontrak (Tugas 7).

- [ ] **Langkah 3: Tambahkan operasi ke `PaCycleGate`**

Di bawah konstanta `TERBITKAN`:

```php
    /**
     * Kalibrasi Penilai: penilai mengubah skornya sendiri per kompetensi sebelum mengirim.
     *
     * Hanya OPEN. Berbeda dari KALIBRASI (forum admin atas grade akhir) yang juga menerima
     * CLOSED: penutupan pengisian melepas seluruh lembar TERKUMPUL menjadi SELESAI, jadi
     * sesudah CLOSED memang tidak ada lembar yang boleh dikalibrasi penilainya.
     */
    public const KALIBRASI_PENILAI = 'KALIBRASI_PENILAI';
```

Di `IZIN`, tepat di bawah `self::TERBITKAN => ['OPEN', 'CLOSED'],`:

```php
        self::KALIBRASI_PENILAI => ['OPEN'],
```

- [ ] **Langkah 4: Pemeriksaan statis dan commit**

```bash
php -l app/Services/KPI/PerformanceAppraisal/PaKalibrasiPenilaiException.php && php -l app/Services/KPI/PerformanceAppraisal/PaCycleGate.php
vendor/bin/pint app/Services/KPI/PerformanceAppraisal/PaKalibrasiPenilaiException.php app/Services/KPI/PerformanceAppraisal/PaCycleGate.php tests/Feature/KPI/PerformanceAppraisal/CycleGateTest.php
git add app/Services/KPI/PerformanceAppraisal/PaKalibrasiPenilaiException.php app/Services/KPI/PerformanceAppraisal/PaCycleGate.php tests/Feature/KPI/PerformanceAppraisal/CycleGateTest.php
git commit -m "feat(pa): operasi gerbang KALIBRASI_PENILAI dan exception berkode"
```

---

### Tugas 3: Satu pintu skor efektif (`PaSkorEfektif`) dan fixture uji

**Berkas:**
- Buat: `app/Services/KPI/PerformanceAppraisal/PaSkorEfektif.php`
- Buat: `tests/Feature/KPI/PerformanceAppraisal/Support/MenyiapkanLembarPenilaian.php`
- Uji: `tests/Feature/KPI/PerformanceAppraisal/SkorEfektifTest.php`

**Antarmuka:**
- Menghasilkan:
  - `PaSkorEfektif::butirBerskor(array $assignmentIds): array<int, Collection<int, object>>` — tiap butir `{PA_Competency_Id:?int, Skor:float (efektif), Skor_Asli:float, Skor_Maksimal:float, Bobot_Sumber:?float, Terkalibrasi:bool}`.
  - `PaSkorEfektif::kalibrasiTerbaru(array $assignmentIds): array<int, array<int, float>>` — penugasan → kompetensi → `Skor_Sesudah` terbaru.
  - `PaSkorEfektif::jawabanNaratif(array $assignmentIds): array<int, list<array{competency_id:?int, jawaban:string}>>`.
  - Trait uji `MenyiapkanLembarPenilaian::lembarTerisi(array $opsi = []): array{cycle_id:int, assignment_id:int, participant_id:int, payload_id:int, kategori_id:int, kompetensi:list<int>, penilai:object}`.

- [ ] **Langkah 1: Tulis trait fixture**

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Support;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Satu lembar penilaian yang SUDAH TERISI, milik user uji sebagai penilai.
 *
 * Baris payload dan skornya ditulis langsung. Itu sah untuk uji yang membaca skor
 * (PaSkorEfektif, wizard, kalibrasi), karena yang diuji adalah pembacanya, bukan pemeta.
 * Uji yang MEMBUTUHKAN jalur pemeta — "kalibrasi selamat melewati petakanUlang()" — tidak
 * memakai trait ini dan mengirim payload sungguhan lewat PaPayloadMapperService.
 *
 * Setiap siklus dibuat sendiri, dan pemanggil WAJIB memakai `cycle_id` dari hasilnya:
 * basis data pengembangan bisa punya siklus OPEN lain (mis. siklus contoh presentasi) yang
 * memuat user uji yang sama.
 */
trait MenyiapkanLembarPenilaian
{
    protected function penilaiUji(): User
    {
        $userId = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->whereNotNull('UserID_Web')
            ->orderBy('Kode_Karyawan')
            ->value('UserID_Web');

        $this->assertNotNull($userId, 'Tidak ada karyawan aktif yang tersambung ke user.');

        return User::find($userId);
    }

    /**
     * @param  array{status_siklus?:string, status_penugasan?:string, kategori?:string,
     *               skor?:list<float>, skor_maksimal?:float, cycle_id?:int,
     *               penilai?:User, boleh_kalibrasi?:string}  $opsi
     * @return array{cycle_id:int, assignment_id:int, participant_id:int, payload_id:int,
     *               kategori_id:int, kompetensi:list<int>, penilai:object}
     */
    protected function lembarTerisi(array $opsi = []): array
    {
        $user = $opsi['penilai'] ?? $this->penilaiUji();

        $penilai = DB::table('Karyawan')
            ->where('UserID_Web', $user->Id_Users)
            ->where('Aktif', 'Y')
            ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama']);

        $cycleId = $opsi['cycle_id'] ?? $this->siklusLembar($opsi['status_siklus'] ?? 'OPEN');

        $kodeKategori = $opsi['kategori'] ?? 'ATASAN';
        $kategoriId = (int) DB::table('N_PA_Rater_Category')
            ->where('Kode_Kategori', $kodeKategori)
            ->value('Id_PA_Rater_Category');
        $this->assertGreaterThan(0, $kategoriId, "Kategori {$kodeKategori} tidak ada di master.");

        if (isset($opsi['boleh_kalibrasi'])) {
            DB::table('N_PA_Rater_Category')
                ->where('Id_PA_Rater_Category', $kategoriId)
                ->update(['Flag_Boleh_Kalibrasi' => $opsi['boleh_kalibrasi']]);
        }

        // Orang yang dinilai: karyawan aktif yang belum menjadi peserta di siklus ini.
        $dinilai = DB::table('Karyawan as k')
            ->where('k.Aktif', 'Y')
            ->where('k.Kode_Karyawan', '<>', $penilai->Kode_Karyawan)
            ->whereNotExists(function ($q) use ($cycleId) {
                $q->from('N_PA_Participant as p')
                    ->whereColumn('p.Kode_Karyawan', 'k.Kode_Karyawan')
                    ->where('p.PA_Cycle_Id', $cycleId);
            })
            ->orderBy('k.Kode_Karyawan')
            ->first(['k.Kode_Perusahaan', 'k.Kode_Karyawan', 'k.Nama', 'k.ID_Level_Jabatan']);

        $levelId = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');

        $participantId = (int) DB::table('N_PA_Participant')->insertGetId([
            'PA_Cycle_Id' => $cycleId,
            'Kode_Perusahaan' => $dinilai->Kode_Perusahaan,
            'Kode_Karyawan' => $dinilai->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $dinilai->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $dinilai->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $levelId,
            'Status_Peserta' => 'DIIKUTKAN',
        ], 'Id_PA_Participant');

        $assignmentId = (int) DB::table('N_PA_Assignment')->insertGetId([
            'PA_Participant_Id' => $participantId,
            'Kode_Perusahaan_Penilai' => $penilai->Kode_Perusahaan,
            'Kode_Karyawan_Penilai' => $penilai->Kode_Karyawan,
            'Nama_Penilai_Saat_Cycle' => $penilai->Nama,
            'PA_Rater_Category_Id' => $kategoriId,
            'Bobot_Dalam_Kategori' => 100,
            'Status_Penugasan' => $opsi['status_penugasan'] ?? 'TERKUMPUL',
            'Due_At' => now()->addDays(7)->format('Y-m-d H:i:s'),
        ], 'Id_PA_Assignment');

        $integrationId = (int) DB::table('N_PA_Assignment_Integration')->insertGetId([
            'PA_Assignment_Id' => $assignmentId,
            'Status_Integrasi' => 'BERHASIL',
            'Flag_Aktif' => 'Y',
        ], 'Id_PA_Assignment_Integration');

        $payloadId = (int) DB::table('N_PA_Assignment_Payload')->insertGetId([
            'PA_Assignment_Integration_Id' => $integrationId,
            'Payload_Json' => '{}',
            'Payload_Hash' => bin2hex(random_bytes(16)),
            'Flag_Terpeta' => 'Y',
            'Status_Skor' => 'FINAL',
        ], 'Id_PA_Assignment_Payload');

        $skor = $opsi['skor'] ?? [70.0, 85.0];
        $maks = $opsi['skor_maksimal'] ?? 100.0;

        $kompetensi = DB::table('N_PA_Competency')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Berskor', 'Y')
            ->orderBy('Id_PA_Competency')
            ->limit(count($skor))
            ->pluck('Id_PA_Competency')
            ->map(fn ($x) => (int) $x)
            ->all();
        $this->assertCount(count($skor), $kompetensi, 'Master kompetensi berskor kurang.');

        foreach ($skor as $i => $nilai) {
            DB::table('N_PA_Assignment_Score')->insert([
                'PA_Assignment_Payload_Id' => $payloadId,
                'PA_Competency_Id' => $kompetensi[$i],
                'Kode_Soal_Sumber' => 'UJI'.($i + 1),
                'Skor' => $nilai,
                'Skor_Maksimal' => $maks,
                'Mapper_Version' => 1,
                'Urutan_Tampil' => $i + 1,
                'Flag_Aktif' => 'Y',
            ]);
        }

        /* Jawaban naratif opsional, pada kompetensi tak berskor. Uji kontrak membutuhkannya:
           pembanding bentuk menganggap daftar kosong berbeda dari daftar berisi, jadi tanpa
           satu jawaban naratif bentuk `naratif[]` tidak pernah terbandingkan. */
        if (! empty($opsi['naratif'])) {
            DB::table('N_PA_Assignment_Score')->insert([
                'PA_Assignment_Payload_Id' => $payloadId,
                'PA_Competency_Id' => DB::table('N_PA_Competency')->where('Flag_Aktif', 'Y')
                    ->where('Flag_Berskor', 'T')->orderBy('Id_PA_Competency')->value('Id_PA_Competency'),
                'Kode_Soal_Sumber' => 'UJI-TEKS',
                'Skor' => null,
                'Skor_Maksimal' => null,
                'Jawaban_Teks' => (string) $opsi['naratif'],
                'Mapper_Version' => 1,
                'Urutan_Tampil' => count($skor) + 1,
                'Flag_Aktif' => 'Y',
            ]);
        }

        return [
            'cycle_id' => $cycleId,
            'assignment_id' => $assignmentId,
            'participant_id' => $participantId,
            'payload_id' => $payloadId,
            'kategori_id' => $kategoriId,
            'kompetensi' => $kompetensi,
            'penilai' => $penilai,
        ];
    }

    protected function siklusLembar(string $status = 'OPEN'): int
    {
        return (int) DB::table('N_PA_Cycle')->insertGetId([
            'Kode_Cycle' => 'UJI-KLB-'.substr(bin2hex(random_bytes(4)), 0, 8),
            'Nama_Cycle' => 'Siklus Uji Kalibrasi Penilai',
            'Tahun' => (int) date('Y'),
            'Status_Cycle' => $status,
            'Flag_Aktif' => 'Y',
            'Pengisian_Dari' => now()->subDay()->format('Y-m-d H:i:s'),
            'Pengisian_Sampai' => now()->addDays(20)->format('Y-m-d H:i:s'),
        ], 'Id_PA_Cycle');
    }

    /** Menyimpan kalibrasi langsung ke tabel — untuk uji pembaca, bukan uji penulis. */
    protected function kalibrasiLangsung(int $assignmentId, int $competencyId, float $sebelum, float $sesudah, float $maks = 100.0): int
    {
        $kepala = (int) DB::table('N_PA_Kalibrasi_Penilai')->insertGetId([
            'PA_Assignment_Id' => $assignmentId,
            'Alasan' => 'Uji',
        ], 'Id_PA_Kalibrasi_Penilai');

        DB::table('N_PA_Kalibrasi_Penilai_Detail')->insert([
            'PA_Kalibrasi_Penilai_Id' => $kepala,
            'PA_Competency_Id' => $competencyId,
            'Skor_Sebelum' => $sebelum,
            'Skor_Sesudah' => $sesudah,
            'Skor_Maksimal' => $maks,
        ]);

        return $kepala;
    }
}
```

- [ ] **Langkah 2: Tulis uji `SkorEfektifTest`**

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaSkorEfektif;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

class SkorEfektifTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanLembarPenilaian;

    public function test_tanpa_kalibrasi_skor_efektif_sama_dengan_asli(): void
    {
        $l = $this->lembarTerisi(['skor' => [70, 85]]);

        $butir = app(PaSkorEfektif::class)->butirBerskor([$l['assignment_id']])[$l['assignment_id']];

        $this->assertCount(2, $butir);
        $this->assertSame([70.0, 85.0], $butir->pluck('Skor')->all());
        $this->assertSame([70.0, 85.0], $butir->pluck('Skor_Asli')->all());
        $this->assertSame([false, false], $butir->pluck('Terkalibrasi')->all());
    }

    public function test_kalibrasi_terbaru_menang_dan_skor_asli_tidak_tersentuh(): void
    {
        $l = $this->lembarTerisi(['skor' => [70, 85]]);
        $k = $l['kompetensi'][0];

        $this->kalibrasiLangsung($l['assignment_id'], $k, 70, 60);
        $this->kalibrasiLangsung($l['assignment_id'], $k, 60, 65);

        $butir = app(PaSkorEfektif::class)->butirBerskor([$l['assignment_id']])[$l['assignment_id']]
            ->keyBy('PA_Competency_Id');

        $this->assertSame(65.0, $butir[$k]->Skor, 'Yang berlaku harus kalibrasi TERAKHIR.');
        $this->assertSame(70.0, $butir[$k]->Skor_Asli);
        $this->assertTrue($butir[$k]->Terkalibrasi);
        $this->assertSame(85.0, $butir[$l['kompetensi'][1]]->Skor, 'Kompetensi lain tidak boleh ikut berubah.');

        $this->assertEquals(70, (float) DB::table('N_PA_Assignment_Score')
            ->where('PA_Assignment_Payload_Id', $l['payload_id'])
            ->where('PA_Competency_Id', $k)
            ->value('Skor'), 'N_PA_Assignment_Score tidak boleh ditimpa.');
    }

    public function test_payload_terakhir_yang_berlaku(): void
    {
        $l = $this->lembarTerisi(['skor' => [70]]);
        $integrasi = (int) DB::table('N_PA_Assignment_Payload')
            ->where('Id_PA_Assignment_Payload', $l['payload_id'])
            ->value('PA_Assignment_Integration_Id');

        $baru = (int) DB::table('N_PA_Assignment_Payload')->insertGetId([
            'PA_Assignment_Integration_Id' => $integrasi,
            'Payload_Json' => '{}',
            'Payload_Hash' => bin2hex(random_bytes(16)),
            'Flag_Terpeta' => 'Y',
            'Status_Skor' => 'FINAL',
        ], 'Id_PA_Assignment_Payload');

        DB::table('N_PA_Assignment_Score')->insert([
            'PA_Assignment_Payload_Id' => $baru,
            'PA_Competency_Id' => $l['kompetensi'][0],
            'Kode_Soal_Sumber' => 'UJI1',
            'Skor' => 90,
            'Skor_Maksimal' => 100,
            'Mapper_Version' => 1,
            'Flag_Aktif' => 'Y',
        ]);

        $butir = app(PaSkorEfektif::class)->butirBerskor([$l['assignment_id']])[$l['assignment_id']];

        $this->assertSame([90.0], $butir->pluck('Skor')->all());
    }

    public function test_daftar_kosong_tidak_menyentuh_basis_data(): void
    {
        DB::enableQueryLog();
        $this->assertSame([], app(PaSkorEfektif::class)->butirBerskor([]));
        $this->assertSame([], DB::getQueryLog());
    }
}
```

- [ ] **Langkah 3: Tulis `PaSkorEfektif`**

```php
<?php

namespace App\Services\KPI\PerformanceAppraisal;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SATU-SATUNYA pembaca skor butir untuk keperluan NILAI.
 *
 * Skor efektif (penugasan P, kompetensi K) = `Skor_Sesudah` Kalibrasi Penilai terbaru untuk
 * (P, K) bila ada; bila tidak, `Skor` asli dari HC Learn pada payload terpeta terakhir P.
 * `N_PA_Assignment_Score` tidak pernah ditimpa: ia tetap catatan apa yang dikirim.
 *
 * KENAPA SATU PINTU. Skor dibaca di beberapa service, dan aturan "pakai yang terbaru" yang
 * hanya dipasang di satu dari dua jalur setara adalah bug yang sudah berulang di proyek ini.
 * `SkorEfektifSatuPintuTest` menolak berkas lain yang membaca kolom `Skor` langsung.
 *
 * KENAPA BERKUNCI (PENUGASAN, KOMPETENSI), BUKAN ID BARIS SKOR. `petakanUlang()` menonaktifkan
 * baris skor lama dan menyisipkan yang baru dengan `Mapper_Version` lebih tinggi. Kalibrasi
 * yang menempel ke id baris akan hilang tanpa jejak begitu kirimannya dipetakan ulang.
 */
class PaSkorEfektif
{
    /** Batas 2.100 parameter SQL Server; 500 menyisakan ruang bagi parameter lain. */
    private const POTONGAN = 500;

    /**
     * Butir berskor per penugasan, dengan `Skor` sudah efektif.
     *
     * Hanya butir ber-`Skor` tidak NULL dan `Skor_Maksimal` > 0 — bentuk yang sama dengan
     * yang dipakai perhitungan sejak awal.
     *
     * @param  list<int>  $assignmentIds
     * @return array<int, Collection<int, object>>
     */
    public function butirBerskor(array $assignmentIds): array
    {
        $assignmentIds = $this->bersihkan($assignmentIds);

        if ($assignmentIds === []) {
            return [];
        }

        $payloadKePenugasan = $this->payloadTerakhir($assignmentIds);

        if ($payloadKePenugasan === []) {
            return [];
        }

        $kalibrasi = $this->kalibrasiTerbaru(array_values(array_unique($payloadKePenugasan)));
        $hasil = [];

        foreach (array_chunk(array_keys($payloadKePenugasan), self::POTONGAN) as $potongan) {
            $baris = DB::table('N_PA_Assignment_Score')
                ->whereIn('PA_Assignment_Payload_Id', $potongan)
                ->where('Flag_Aktif', 'Y')
                ->whereNotNull('Skor')
                ->where('Skor_Maksimal', '>', 0)
                ->orderBy('PA_Assignment_Payload_Id')
                ->orderBy('Urutan_Tampil')
                ->orderBy('Id_PA_Assignment_Score')
                ->get(['PA_Assignment_Payload_Id', 'PA_Competency_Id', 'Skor', 'Skor_Maksimal', 'Bobot_Sumber']);

            foreach ($baris as $b) {
                $assignmentId = $payloadKePenugasan[(int) $b->PA_Assignment_Payload_Id];
                $kompetensi = $b->PA_Competency_Id === null ? null : (int) $b->PA_Competency_Id;
                $revisi = $kompetensi === null ? null : ($kalibrasi[$assignmentId][$kompetensi] ?? null);

                $hasil[$assignmentId][] = (object) [
                    'PA_Competency_Id' => $kompetensi,
                    'Skor' => $revisi ?? (float) $b->Skor,
                    'Skor_Asli' => (float) $b->Skor,
                    'Skor_Maksimal' => (float) $b->Skor_Maksimal,
                    'Bobot_Sumber' => $b->Bobot_Sumber === null ? null : (float) $b->Bobot_Sumber,
                    'Terkalibrasi' => $revisi !== null,
                ];
            }
        }

        return array_map(fn (array $butir) => collect($butir), $hasil);
    }

    /**
     * `Skor_Sesudah` dari tindakan kalibrasi TERBARU per (penugasan, kompetensi).
     *
     * "Terbaru" = `Id_PA_Kalibrasi_Penilai` terbesar. Tabelnya hanya ditambah, dan
     * penyimpanan dikunci `lockForUpdate` pada baris penugasan, jadi dua tindakan atas
     * penugasan yang sama tidak pernah berjalan bersamaan dan id naik mengikuti urutannya.
     *
     * @param  list<int>  $assignmentIds
     * @return array<int, array<int, float>>
     */
    public function kalibrasiTerbaru(array $assignmentIds): array
    {
        $hasil = [];

        foreach (array_chunk($this->bersihkan($assignmentIds), self::POTONGAN) as $potongan) {
            $baris = DB::table('N_PA_Kalibrasi_Penilai_Detail as d')
                ->join('N_PA_Kalibrasi_Penilai as k', 'k.Id_PA_Kalibrasi_Penilai', '=', 'd.PA_Kalibrasi_Penilai_Id')
                ->whereIn('k.PA_Assignment_Id', $potongan)
                ->orderBy('k.PA_Assignment_Id')
                ->orderBy('d.PA_Competency_Id')
                ->orderByDesc('k.Id_PA_Kalibrasi_Penilai')
                ->get(['k.PA_Assignment_Id', 'd.PA_Competency_Id', 'd.Skor_Sesudah']);

            foreach ($baris as $b) {
                $hasil[(int) $b->PA_Assignment_Id][(int) $b->PA_Competency_Id] ??= (float) $b->Skor_Sesudah;
            }
        }

        return $hasil;
    }

    /**
     * Jawaban naratif pada payload terpeta terakhir tiap penugasan.
     *
     * Ditaruh di sini, bukan di pemanggil, karena "payload mana yang berlaku" adalah
     * pengetahuan yang sama dengan butir berskor — dua salinan aturan itu akan berselisih.
     *
     * @param  list<int>  $assignmentIds
     * @return array<int, list<array{competency_id:?int, jawaban:string}>>
     */
    public function jawabanNaratif(array $assignmentIds): array
    {
        $payloadKePenugasan = $this->payloadTerakhir($this->bersihkan($assignmentIds));
        $hasil = [];

        foreach (array_chunk(array_keys($payloadKePenugasan), self::POTONGAN) as $potongan) {
            $baris = DB::table('N_PA_Assignment_Score')
                ->whereIn('PA_Assignment_Payload_Id', $potongan)
                ->where('Flag_Aktif', 'Y')
                ->whereNotNull('Jawaban_Teks')
                ->where('Jawaban_Teks', '<>', '')
                ->orderBy('PA_Assignment_Payload_Id')
                ->orderBy('Urutan_Tampil')
                ->get(['PA_Assignment_Payload_Id', 'PA_Competency_Id', 'Jawaban_Teks']);

            foreach ($baris as $b) {
                $hasil[$payloadKePenugasan[(int) $b->PA_Assignment_Payload_Id]][] = [
                    'competency_id' => $b->PA_Competency_Id === null ? null : (int) $b->PA_Competency_Id,
                    'jawaban' => (string) $b->Jawaban_Teks,
                ];
            }
        }

        return $hasil;
    }

    /**
     * Payload terpeta TERAKHIR per penugasan. Satu penugasan bisa punya beberapa payload
     * bila kiriman berbeda isi datang dua kali, dan yang berlaku adalah yang terakhir.
     * Aturannya sama persis dengan pemuat lama di PaResultCalculationService, supaya
     * perpindahan ke sini tidak mengubah hasil siapa pun.
     *
     * @param  list<int>  $assignmentIds
     * @return array<int, int> Id_PA_Assignment_Payload => Id_PA_Assignment
     */
    private function payloadTerakhir(array $assignmentIds): array
    {
        $peta = [];

        foreach (array_chunk($assignmentIds, self::POTONGAN) as $potongan) {
            $baris = DB::table('N_PA_Assignment_Payload as p')
                ->join('N_PA_Assignment_Integration as i',
                    'i.Id_PA_Assignment_Integration', '=', 'p.PA_Assignment_Integration_Id')
                ->whereIn('i.PA_Assignment_Id', $potongan)
                ->where('p.Flag_Terpeta', 'Y')
                ->orderBy('i.PA_Assignment_Id')
                ->orderByDesc('p.Id_PA_Assignment_Payload')
                ->get(['p.Id_PA_Assignment_Payload', 'i.PA_Assignment_Id']);

            $sudah = [];

            foreach ($baris as $b) {
                $a = (int) $b->PA_Assignment_Id;

                if (isset($sudah[$a])) {
                    continue;
                }

                $sudah[$a] = true;
                $peta[(int) $b->Id_PA_Assignment_Payload] = $a;
            }
        }

        return $peta;
    }

    /**
     * @param  array<int|string>  $ids
     * @return list<int>
     */
    private function bersihkan(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $x) => $x > 0)));
    }
}
```

- [ ] **Langkah 4: Pemeriksaan statis dan commit**

```bash
php -l app/Services/KPI/PerformanceAppraisal/PaSkorEfektif.php
vendor/bin/pint app/Services/KPI/PerformanceAppraisal/PaSkorEfektif.php tests/Feature/KPI/PerformanceAppraisal/Support/MenyiapkanLembarPenilaian.php tests/Feature/KPI/PerformanceAppraisal/SkorEfektifTest.php
git add app/Services/KPI/PerformanceAppraisal/PaSkorEfektif.php tests/Feature/KPI/PerformanceAppraisal/Support/MenyiapkanLembarPenilaian.php tests/Feature/KPI/PerformanceAppraisal/SkorEfektifTest.php
git commit -m "feat(pa): PaSkorEfektif sebagai satu pintu skor efektif"
```

---

### Tugas 4: Perhitungan, pemverifikasi, dan penjaga satu pintu

**Berkas:**
- Ubah: `app/Services/KPI/PerformanceAppraisal/PaResultCalculationService.php` (konstruktor, `penugasanDenganNilai()`, `jenisTerjawabTanpaAngka()`)
- Ubah: `app/Console/Commands/KPI/PerformanceAppraisal/PaVerifyCommand.php` (pemuat skor pada pemeriksaan hasil)
- Buat: `tests/Feature/KPI/PerformanceAppraisal/SkorEfektifSatuPintuTest.php`
- Uji: tambahan di `tests/Feature/KPI/PerformanceAppraisal/SkorEfektifTest.php`

**Antarmuka:**
- Mengonsumsi: `PaSkorEfektif::butirBerskor()` (Tugas 3).
- Menghasilkan: tidak ada API baru; perilaku perhitungan kini memakai skor efektif.

- [ ] **Langkah 1: Tulis uji "perhitungan memakai skor efektif"**

Tambahkan ke `SkorEfektifTest` (plus `use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;`):

```php
    /**
     * Titik sambung perhitungan diuji langsung lewat refleksi. `hitung()` penuh menuntut
     * salinan aturan, pita nilai, dan KPI — menyiapkannya menguji jauh lebih banyak dari
     * sambungan yang dimaksud, dan kegagalannya tidak akan menunjuk ke sini.
     */
    public function test_perhitungan_memakai_skor_efektif(): void
    {
        $l = $this->lembarTerisi(['skor' => [70, 85], 'status_penugasan' => 'SELESAI']);
        $this->kalibrasiLangsung($l['assignment_id'], $l['kompetensi'][0], 70, 50);

        $m = new \ReflectionMethod(PaResultCalculationService::class, 'penugasanDenganNilai');
        $m->setAccessible(true);
        $hasil = $m->invoke(app(PaResultCalculationService::class), $l['participant_id']);

        $penilai = $hasil[$l['kategori_id']]['penilai'][0];

        // Tanpa bobot butir: rata-rata biasa atas skor ternormalisasi, (50 + 85) / 2.
        $this->assertEqualsWithDelta(67.5, $penilai['nilai'], 0.0001);
        $this->assertEqualsWithDelta(50.0, $penilai['kompetensi'][$l['kompetensi'][0]], 0.0001);
    }
```

- [ ] **Langkah 2: Suntikkan `PaSkorEfektif` ke perhitungan**

Konstruktor `PaResultCalculationService` menjadi:

```php
    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaParticipantRuleResolver $resolver,
        private readonly PaBobotGuard $bobot,
        private readonly KpiScoringService $scoring,
        private readonly PaSkorEfektif $skorEfektif,
    ) {}
```

- [ ] **Langkah 3: Ganti pemuat skor di `penugasanDenganNilai()`**

Hapus blok dari komentar `// Satu penugasan bisa punya beberapa payload bila kiriman berbeda isinya datang` sampai `->groupBy('PA_Assignment_Payload_Id');` (definisi `$payloadTerakhir` dan `$skor`), ganti dengan:

```php
        /* SKOR EFEKTIF, LEWAT SATU PINTU. PaSkorEfektif memegang dua aturan sekaligus:
           payload terpeta terakhir yang berlaku, dan Kalibrasi Penilai terbaru menang atas
           skor asli HC Learn. Membaca N_PA_Assignment_Score langsung di sini berarti
           perhitungan diam-diam mengabaikan setiap kalibrasi. */
        $butirPerTugas = $this->skorEfektif->butirBerskor(
            $tugas->pluck('Id_PA_Assignment')->map(fn ($x) => (int) $x)->all()
        );
```

Lalu di dalam `foreach ($tugas as $t)`, ganti:

```php
            $payload = $payloadTerakhir->get($t->Id_PA_Assignment);

            if ($payload === null) {
                continue;
            }

            $butir = $skor->get($payload->Id_PA_Assignment_Payload);
```

dengan:

```php
            $butir = $butirPerTugas[(int) $t->Id_PA_Assignment] ?? null;
```

`nilaiSatuPenilai()`, `nilaiKompetensiSatuPenilai()`, dan `normal()` tidak berubah: butir dari `PaSkorEfektif` membawa `Skor`, `Skor_Maksimal`, `Bobot_Sumber`, dan `PA_Competency_Id` dengan nama yang sama.

- [ ] **Langkah 4: Tandai satu-satunya bacaan mentah yang sah**

Di `jenisTerjawabTanpaAngka()`, baris `->whereNull('s.Skor')` menjadi:

```php
            ->whereNull('s.Skor') // skor-mentah: hanya memeriksa ADA-TIDAKNYA angka; butir tanpa angka tidak pernah dikalibrasi.
```

- [ ] **Langkah 5: Terapkan kalibrasi secara independen di `PaVerifyCommand`**

Pada pemuat skor pemeriksaan hasil, tambahkan `'PA_Competency_Id'` ke daftar kolom `get([...])`, lalu ganti baris `$skor = $skor->groupBy('PA_Assignment_Payload_Id');` dengan:

```php
        /* KALIBRASI PENILAI DITERAPKAN SENDIRI, tidak lewat PaSkorEfektif. Perintah ini
           berguna justru karena ia menghitung ulang dengan jalannya sendiri; memakai pintu
           yang sama dengan mesin berarti kesalahan di pintu itu akan cocok sempurna di kedua
           sisi. Tanpa langkah ini, setiap hasil dari skor terkalibrasi dilaporkan berselisih. */
        $kalibrasi = $this->kalibrasiPenilaiTerbaru(
            $payload->pluck('PA_Assignment_Id')->map(fn ($x) => (int) $x)->all()
        );
        $payloadKeTugas = $payload
            ->mapWithKeys(fn ($p) => [(int) $p->Id_PA_Assignment_Payload => (int) $p->PA_Assignment_Id])
            ->all();

        $skor = $skor->map(function ($b) use ($kalibrasi, $payloadKeTugas) {
            $tugasId = $payloadKeTugas[(int) $b->PA_Assignment_Payload_Id] ?? null;
            $kompetensiId = $b->PA_Competency_Id === null ? null : (int) $b->PA_Competency_Id;

            if ($tugasId !== null && $kompetensiId !== null && isset($kalibrasi[$tugasId][$kompetensiId])) {
                $b->Skor = $kalibrasi[$tugasId][$kompetensiId];
            }

            return $b;
        })->groupBy('PA_Assignment_Payload_Id');
```

Dan tambahkan method privat di akhir kelas:

```php
    /**
     * Skor_Sesudah terbaru per (penugasan, kompetensi), ditulis ulang di sini dengan sengaja
     * — lihat catatan pada pemakainya.
     *
     * @param  list<int>  $assignmentIds
     * @return array<int, array<int, float>>
     */
    private function kalibrasiPenilaiTerbaru(array $assignmentIds): array
    {
        $hasil = [];

        foreach (array_chunk(array_values(array_unique($assignmentIds)), 1500) as $potongan) {
            $baris = DB::table('N_PA_Kalibrasi_Penilai as k')
                ->join('N_PA_Kalibrasi_Penilai_Detail as d', 'd.PA_Kalibrasi_Penilai_Id', '=', 'k.Id_PA_Kalibrasi_Penilai')
                ->whereIn('k.PA_Assignment_Id', $potongan)
                ->orderByDesc('k.Id_PA_Kalibrasi_Penilai')
                ->get(['k.PA_Assignment_Id', 'd.PA_Competency_Id', 'd.Skor_Sesudah']);

            foreach ($baris as $b) {
                $hasil[(int) $b->PA_Assignment_Id][(int) $b->PA_Competency_Id] ??= (float) $b->Skor_Sesudah;
            }
        }

        return $hasil;
    }
```

Tidak ada uji unit untuk bagian ini: `VerifyCommandTest` tidak punya perkakas membangun hasil terhitung. Buktinya diambil end-to-end di Tugas 14 Langkah 6.

- [ ] **Langkah 6: Tulis `SkorEfektifSatuPintuTest`**

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use Tests\TestCase;

/**
 * Penegak satu pintu skor efektif (spec §5.4).
 *
 * Perhitungan dan layar yang membaca kolom `Skor` dari `N_PA_Assignment_Score` langsung akan
 * tampak benar sampai ada Kalibrasi Penilai pertama — lalu diam-diam memakai skor asli untuk
 * orang yang skornya sudah direvisi penilainya.
 *
 * Yang dipindai: literal kolom `Skor` dalam tanda kutip ('Skor', 's.Skor', 'Skor as x') pada
 * berkas di app/ yang juga menyebut `N_PA_Assignment_Score`. Pola ini TIDAK menangkap
 * `select *` — tidak ada satu pun pembaca skor di modul ini yang memakainya, dan bila kelak
 * ada, pemindai ini perlu diperluas.
 *
 * Pengecualian per baris: baris yang memuat komentar `skor-mentah: <alasan>` dilewati, dan
 * alasannya WAJIB diisi.
 */
class SkorEfektifSatuPintuTest extends TestCase
{
    private const TABEL = 'N_PA_Assignment_Score';

    private const POLA_KOLOM = "/['\"](?:[A-Za-z]{1,4}\\.)?Skor(?:\\s+as\\s+\\w+)?['\"]/";

    private const PENANDA = 'skor-mentah:';

    private const DAFTAR_IZIN = [
        // Pintunya sendiri.
        'app/Services/KPI/PerformanceAppraisal/PaSkorEfektif.php',
        // Penulis: memetakan kiriman HC Learn menjadi baris skor.
        'app/Services/KPI/PerformanceAppraisal/PaPayloadMapperService.php',
        // Pemverifikasi menghitung ulang dengan jalannya sendiri dan menerapkan kalibrasi
        // secara independen; memakai PaSkorEfektif akan memeriksa mesin dengan mesin yang sama.
        'app/Console/Commands/KPI/PerformanceAppraisal/PaVerifyCommand.php',
        // Perintah semaian pengembangan: MENULIS baris skor contoh, menolak berjalan di production.
        'app/Console/Commands/KPI/PerformanceAppraisal/PaDemoCycleCommand.php',
        'app/Console/Commands/KPI/PerformanceAppraisal/PaSeedSkenarioCommand.php',
    ];

    public function test_hanya_pintu_skor_efektif_yang_membaca_kolom_skor(): void
    {
        $pelanggar = [];

        foreach ($this->berkasTerpindai() as $relatif => $isi) {
            if (in_array($relatif, self::DAFTAR_IZIN, true) || ! str_contains($isi, self::TABEL)) {
                continue;
            }

            foreach (explode("\n", $isi) as $i => $baris) {
                if (preg_match(self::POLA_KOLOM, $baris) && ! str_contains($baris, self::PENANDA)) {
                    $pelanggar[] = $relatif.':'.($i + 1);
                }
            }
        }

        $this->assertSame([], $pelanggar,
            "Kolom Skor hanya boleh dibaca lewat PaSkorEfektif.\n"
            ."Kalau bacaan mentah ini memang sah, tandai barisnya dengan `// skor-mentah: <alasan>`.\n"
            .'Pelanggaran: '.implode('; ', $pelanggar));
    }

    public function test_setiap_penanda_skor_mentah_menyebut_alasan(): void
    {
        $kosong = [];

        foreach ($this->berkasTerpindai() as $relatif => $isi) {
            foreach (explode("\n", $isi) as $i => $baris) {
                $posisi = strpos($baris, self::PENANDA);

                if ($posisi !== false && trim(substr($baris, $posisi + strlen(self::PENANDA))) === '') {
                    $kosong[] = $relatif.':'.($i + 1);
                }
            }
        }

        $this->assertSame([], $kosong, 'Penanda skor-mentah tanpa alasan: '.implode('; ', $kosong));
    }

    /** Penjaga atas penjaga: polanya harus benar-benar menangkap bentuk bacaan yang nyata. */
    public function test_pola_menangkap_bacaan_nyata_dan_pemindaian_menyentuh_berkas(): void
    {
        foreach (["->get(['Skor'])", "->whereNotNull('s.Skor')", '->select("s.Skor as nilai")'] as $contoh) {
            $this->assertSame(1, preg_match(self::POLA_KOLOM, $contoh), "Pola gagal menangkap {$contoh}");
        }

        foreach (["'Skor_Maksimal'", "'Skor_Asli'", '`Skor`'] as $bukan) {
            $this->assertSame(0, preg_match(self::POLA_KOLOM, $bukan), "Pola salah menangkap {$bukan}");
        }

        $berkas = $this->berkasTerpindai();
        $this->assertGreaterThan(200, count($berkas), 'Pemindaian hampir tidak menemukan berkas.');
        $this->assertArrayHasKey('app/Services/KPI/PerformanceAppraisal/PaSkorEfektif.php', $berkas);
    }

    /** @return array<string, string> */
    private function berkasTerpindai(): array
    {
        $hasil = [];
        $akar = str_replace('\\', '/', base_path()).'/';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('app')));

        foreach ($iterator as $berkas) {
            if ($berkas->isFile() && $berkas->getExtension() === 'php') {
                $jalur = str_replace('\\', '/', $berkas->getPathname());
                $hasil[str_replace($akar, '', $jalur)] = (string) file_get_contents($berkas->getPathname());
            }
        }

        return $hasil;
    }
}
```

Sebelum dianggap selesai, periksa daftar izin terhadap kenyataan: `grep -lE "['\"]([A-Za-z]{1,4}\.)?Skor( as [A-Za-z_]+)?['\"]" $(grep -rl N_PA_Assignment_Score app/)` harus hanya memuat berkas di `DAFTAR_IZIN` ditambah `PaResultCalculationService.php` (yang satu-satunya barisnya bertanda).

- [ ] **Langkah 7: Pemeriksaan statis dan commit**

```bash
php -l app/Services/KPI/PerformanceAppraisal/PaResultCalculationService.php && php -l app/Console/Commands/KPI/PerformanceAppraisal/PaVerifyCommand.php
vendor/bin/pint app/Services/KPI/PerformanceAppraisal/PaResultCalculationService.php app/Console/Commands/KPI/PerformanceAppraisal/PaVerifyCommand.php tests/Feature/KPI/PerformanceAppraisal/SkorEfektifSatuPintuTest.php tests/Feature/KPI/PerformanceAppraisal/SkorEfektifTest.php
git add app/Services/KPI/PerformanceAppraisal/PaResultCalculationService.php app/Console/Commands/KPI/PerformanceAppraisal/PaVerifyCommand.php tests/Feature/KPI/PerformanceAppraisal/SkorEfektifSatuPintuTest.php tests/Feature/KPI/PerformanceAppraisal/SkorEfektifTest.php
git commit -m "feat(pa): perhitungan dan pa:verify memakai skor efektif, dijaga uji pemindai"
```

---

### Tugas 5: Menyimpan Kalibrasi Penilai (`PaKalibrasiPenilaiService`)

**Berkas:**
- Buat: `app/Services/KPI/PerformanceAppraisal/PaKalibrasiPenilaiService.php`
- Uji: `tests/Feature/KPI/PerformanceAppraisal/KalibrasiPenilaiTest.php`

**Antarmuka:**
- Mengonsumsi: `PaCycleGate::boleh()`, `PaCycleGate::KALIBRASI_PENILAI`, `PaSkorEfektif::butirBerskor()`, `PaKalibrasiPenilaiException`, `PaStatusPenugasan::TERKUMPUL`.
- Menghasilkan:
  - `simpan(object $penilai, int $assignmentId, array $perubahan, ?string $alasan, ?int $olehUserId): array{kalibrasi_id:int, assignment_id:int, cycle_id:int, kategori:string, diubah:int}` — `$penilai` punya `Kode_Perusahaan`, `Kode_Karyawan`; `$perubahan` = `list<array{competency_id:int, skor:numeric}>`.
  - `riwayatPeserta(int $participantId): list<array{kalibrasi_id:int, assignment_id:int, penilai:array{kode_karyawan:string, nama:string}, kategori:array{kode:string, nama:string}, alasan:string, pada:string, oleh:?int, perubahan:list<array{competency_id:int, kode:string, nama:string, skor_sebelum:float, skor_sesudah:float, skor_maksimal:float}>}>`.

- [ ] **Langkah 1: Tulis uji `KalibrasiPenilaiTest`**

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaKalibrasiPenilaiException;
use App\Services\KPI\PerformanceAppraisal\PaKalibrasiPenilaiService;
use App\Services\KPI\PerformanceAppraisal\PaKirimanTinjauService;
use App\Services\KPI\PerformanceAppraisal\PaPayloadMapperService;
use App\Services\KPI\PerformanceAppraisal\PaSkorEfektif;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

class KalibrasiPenilaiTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanLembarPenilaian;

    private function layanan(): PaKalibrasiPenilaiService
    {
        return app(PaKalibrasiPenilaiService::class);
    }

    /** Menjalankan simpan dan mengembalikan kode galatnya, atau null bila berhasil. */
    private function kodeGalat(callable $kerja): ?string
    {
        try {
            $kerja();

            return null;
        } catch (PaKalibrasiPenilaiException $e) {
            return $e->errorCode;
        }
    }

    public function test_menyimpan_revisi_tanpa_menyentuh_skor_asli(): void
    {
        $l = $this->lembarTerisi(['skor' => [70, 85], 'boleh_kalibrasi' => 'Y']);
        $k = $l['kompetensi'][0];

        $hasil = $this->layanan()->simpan($l['penilai'], $l['assignment_id'],
            [['competency_id' => $k, 'skor' => 60]], 'Diselaraskan dengan rekan setim', 99);

        $this->assertSame(1, $hasil['diubah']);
        $this->assertSame('ATASAN', $hasil['kategori']);

        $detail = DB::table('N_PA_Kalibrasi_Penilai_Detail')
            ->where('PA_Kalibrasi_Penilai_Id', $hasil['kalibrasi_id'])->first();
        $this->assertEquals(70, (float) $detail->Skor_Sebelum);
        $this->assertEquals(60, (float) $detail->Skor_Sesudah);
        $this->assertEquals(99, (int) DB::table('N_PA_Kalibrasi_Penilai')
            ->where('Id_PA_Kalibrasi_Penilai', $hasil['kalibrasi_id'])->value('Created_By'));

        $this->assertEquals(70, (float) DB::table('N_PA_Assignment_Score')
            ->where('PA_Assignment_Payload_Id', $l['payload_id'])->where('PA_Competency_Id', $k)->value('Skor'));
    }

    public function test_rantai_revisi_membawa_skor_sebelum_dari_revisi_sebelumnya(): void
    {
        $l = $this->lembarTerisi(['skor' => [70], 'boleh_kalibrasi' => 'Y']);
        $k = $l['kompetensi'][0];

        $this->layanan()->simpan($l['penilai'], $l['assignment_id'], [['competency_id' => $k, 'skor' => 60]], 'Pertama', null);
        $kedua = $this->layanan()->simpan($l['penilai'], $l['assignment_id'], [['competency_id' => $k, 'skor' => 65]], 'Kedua', null);

        $this->assertEquals(60, (float) DB::table('N_PA_Kalibrasi_Penilai_Detail')
            ->where('PA_Kalibrasi_Penilai_Id', $kedua['kalibrasi_id'])->value('Skor_Sebelum'));
    }

    public function test_penugasan_milik_orang_lain_dijawab_tidak_ditemukan(): void
    {
        $l = $this->lembarTerisi(['boleh_kalibrasi' => 'Y']);
        $orangLain = (object) ['Kode_Perusahaan' => $l['penilai']->Kode_Perusahaan, 'Kode_Karyawan' => 'BUKAN-PENILAI'];

        $this->assertSame(PaKalibrasiPenilaiException::PENUGASAN_TIDAK_DITEMUKAN, $this->kodeGalat(
            fn () => $this->layanan()->simpan($orangLain, $l['assignment_id'],
                [['competency_id' => $l['kompetensi'][0], 'skor' => 10]], 'x', null)));
    }

    public function test_hanya_lembar_terkumpul_yang_bisa_dikalibrasi(): void
    {
        foreach (['BELUM_MULAI', 'SELESAI', 'MENUNGGU_VERIFIKASI'] as $status) {
            $l = $this->lembarTerisi(['status_penugasan' => $status, 'boleh_kalibrasi' => 'Y']);

            $this->assertSame(PaKalibrasiPenilaiException::LEMBAR_BEKU, $this->kodeGalat(
                fn () => $this->layanan()->simpan($l['penilai'], $l['assignment_id'],
                    [['competency_id' => $l['kompetensi'][0], 'skor' => 10]], 'x', null)), $status);
        }
    }

    public function test_kategori_yang_tidak_boleh_dikalibrasi_ditolak(): void
    {
        $l = $this->lembarTerisi(['boleh_kalibrasi' => 'T']);

        $this->assertSame(PaKalibrasiPenilaiException::KALIBRASI_DITUTUP, $this->kodeGalat(
            fn () => $this->layanan()->simpan($l['penilai'], $l['assignment_id'],
                [['competency_id' => $l['kompetensi'][0], 'skor' => 10]], 'x', null)));
    }

    public function test_siklus_yang_tidak_terbuka_ditolak(): void
    {
        $l = $this->lembarTerisi(['status_siklus' => 'CLOSED', 'boleh_kalibrasi' => 'Y']);

        $this->assertSame(PaKalibrasiPenilaiException::SIKLUS_TIDAK_TERBUKA, $this->kodeGalat(
            fn () => $this->layanan()->simpan($l['penilai'], $l['assignment_id'],
                [['competency_id' => $l['kompetensi'][0], 'skor' => 10]], 'x', null)));
    }

    public function test_rentang_alasan_dan_perubahan_ditegakkan(): void
    {
        $l = $this->lembarTerisi(['skor' => [70], 'skor_maksimal' => 100, 'boleh_kalibrasi' => 'Y']);
        $k = $l['kompetensi'][0];
        $simpan = fn (array $p, ?string $alasan = 'x') => $this->kodeGalat(
            fn () => $this->layanan()->simpan($l['penilai'], $l['assignment_id'], $p, $alasan, null));

        $this->assertSame(PaKalibrasiPenilaiException::SKOR_DI_LUAR_RENTANG, $simpan([['competency_id' => $k, 'skor' => 101]]));
        $this->assertSame(PaKalibrasiPenilaiException::SKOR_DI_LUAR_RENTANG, $simpan([['competency_id' => $k, 'skor' => -1]]));
        $this->assertSame(PaKalibrasiPenilaiException::KOMPETENSI_TIDAK_DI_LEMBAR, $simpan([['competency_id' => 999999, 'skor' => 10]]));
        $this->assertSame(PaKalibrasiPenilaiException::ALASAN_WAJIB, $simpan([['competency_id' => $k, 'skor' => 10]], '   '));
        $this->assertSame(PaKalibrasiPenilaiException::ALASAN_WAJIB, $simpan([['competency_id' => $k, 'skor' => 10]], str_repeat('a', 501)));
        $this->assertSame(PaKalibrasiPenilaiException::TIDAK_ADA_PERUBAHAN, $simpan([['competency_id' => $k, 'skor' => 70]]));

        $this->assertSame(0, DB::table('N_PA_Kalibrasi_Penilai')->where('PA_Assignment_Id', $l['assignment_id'])->count(),
            'Penolakan tidak boleh meninggalkan baris kepala.');
    }

    /**
     * UJI TERPENTING FITUR INI. Pemetaan ulang menonaktifkan baris skor lama dan menulis yang
     * baru; kalibrasi yang menempel ke id baris akan hilang tanpa jejak. Payloadnya dikirim
     * lewat PEMETA SUNGGUHAN supaya yang diuji adalah jalur produksi, bukan tiruannya.
     */
    public function test_kalibrasi_selamat_melewati_petakan_ulang(): void
    {
        $siap = $this->penugasanBerpintuUntukPemeta();

        $muatan = json_decode((string) file_get_contents(
            base_path('docs/KPI/performance-appraisal/contoh-callback-hclearn.json')), true)['final_tanpa_esai'];
        unset($muatan['_kapan']);
        $muatan['Api_Reference'] = $siap['api_ref'];
        $muatan['Id_Integrasi'] = $siap['integration_id'];
        $muatan['Kode_Karyawan'] = $siap['penilai']->Kode_Karyawan;

        app(PaPayloadMapperService::class)->terimaDanPetakan($siap['api_ref'], $muatan);

        $this->assertSame('TERKUMPUL', DB::table('N_PA_Assignment')
            ->where('Id_PA_Assignment', $siap['assignment_id'])->value('Status_Penugasan'),
            'Prasyarat: kiriman FINAL harus berhenti di TERKUMPUL.');

        $k = $siap['kompetensi'][0]; // butir BE, skor asli 21 dari 100
        $this->layanan()->simpan($siap['penilai'], $siap['assignment_id'],
            [['competency_id' => $k, 'skor' => 60]], 'Diselaraskan', null);

        $payloadId = (int) DB::table('N_PA_Assignment_Payload')
            ->where('PA_Assignment_Integration_Id', $siap['integration_id'])->value('Id_PA_Assignment_Payload');
        app(PaKirimanTinjauService::class)->petakanUlang($siap['cycle_id'], $payloadId, null);

        $this->assertGreaterThan(1, (int) DB::table('N_PA_Assignment_Score')
            ->where('PA_Assignment_Payload_Id', $payloadId)->max('Mapper_Version'),
            'Prasyarat: pemetaan ulang harus benar-benar menulis versi baru.');

        $butir = app(PaSkorEfektif::class)->butirBerskor([$siap['assignment_id']])[$siap['assignment_id']]
            ->keyBy('PA_Competency_Id');

        $this->assertSame(60.0, $butir[$k]->Skor, 'Kalibrasi hilang sesudah pemetaan ulang.');
        $this->assertSame(21.0, $butir[$k]->Skor_Asli);
    }

    public function test_riwayat_peserta_memuat_rantai_perubahan(): void
    {
        $l = $this->lembarTerisi(['skor' => [70], 'boleh_kalibrasi' => 'Y']);
        $this->layanan()->simpan($l['penilai'], $l['assignment_id'],
            [['competency_id' => $l['kompetensi'][0], 'skor' => 60]], 'Pertama', null);

        $riwayat = $this->layanan()->riwayatPeserta($l['participant_id']);

        $this->assertCount(1, $riwayat);
        $this->assertSame('Pertama', $riwayat[0]['alasan']);
        $this->assertSame('ATASAN', $riwayat[0]['kategori']['kode']);
        $this->assertEquals(70.0, $riwayat[0]['perubahan'][0]['skor_sebelum']);
        $this->assertEquals(60.0, $riwayat[0]['perubahan'][0]['skor_sesudah']);
    }

    /**
     * Paket dua butir (BE, KWN) persis contoh HC Learn, dengan user uji sebagai penilai.
     * Bentuknya meniru CallbackKontrakHcLearnTest::siapkanPenugasanBerpintu().
     *
     * @return array{cycle_id:int, assignment_id:int, integration_id:int, api_ref:string, kompetensi:list<int>, penilai:object}
     */
    private function penugasanBerpintuUntukPemeta(): array
    {
        $user = $this->penilaiUji();
        $penilai = DB::table('Karyawan')->where('UserID_Web', $user->Id_Users)->where('Aktif', 'Y')
            ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama']);
        $dinilai = DB::table('Karyawan')->where('Aktif', 'Y')->where('Kode_Karyawan', '<>', $penilai->Kode_Karyawan)
            ->orderBy('Kode_Karyawan')->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan']);

        $cycleId = $this->siklusLembar('OPEN');
        $levelId = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $kategoriId = (int) DB::table('N_PA_Rater_Category')->where('Kode_Kategori', 'ATASAN')->value('Id_PA_Rater_Category');
        DB::table('N_PA_Rater_Category')->where('Id_PA_Rater_Category', $kategoriId)->update(['Flag_Boleh_Kalibrasi' => 'Y']);

        $paketId = (int) DB::table('N_PA_Cycle_Question_Package')->insertGetId([
            'PA_Cycle_Id' => $cycleId,
            'ID_Level' => $levelId,
            'PA_Rater_Category_Id' => $kategoriId,
            'Id_Master_Ujian' => '900900',
            'Nama_Ujian_Sumber' => 'Employee Assessment 360 - STAFF',
            'Sumber_Kontrak' => 'HCLEARN',
            'Flag_Aktif' => 'Y',
        ], 'Id_PA_Cycle_Question_Package');

        $kompetensi = DB::table('N_PA_Competency')->where('Flag_Aktif', 'Y')->where('Flag_Berskor', 'Y')
            ->orderBy('Id_PA_Competency')->limit(2)->pluck('Id_PA_Competency')->map(fn ($x) => (int) $x)->all();

        foreach ([['BE', 'Business Ethic'], ['KWN', 'Kewarganegaraan']] as $i => [$kode, $nama]) {
            DB::table('N_PA_Cycle_Question_Package_Item')->insert([
                'PA_Cycle_Question_Package_Id' => $paketId,
                'Id_Jenis_Indikator' => '900910',
                'Id_Jenis_Soal' => (string) (900920 + $i),
                'Kode_Soal_Sumber' => $kode,
                'Nama_Alat_Tes' => 'Employee Assessment 360 - STAFF',
                'Nama_Bagian_Sumber' => $nama,
                'PA_Competency_Id' => $kompetensi[$i],
                'Urutan_Tampil' => $i + 1,
                'Flag_Aktif' => 'Y',
            ]);
        }

        $participantId = (int) DB::table('N_PA_Participant')->insertGetId([
            'PA_Cycle_Id' => $cycleId,
            'Kode_Perusahaan' => $dinilai->Kode_Perusahaan,
            'Kode_Karyawan' => $dinilai->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $dinilai->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $dinilai->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $levelId,
            'Status_Peserta' => 'DIIKUTKAN',
        ], 'Id_PA_Participant');

        $assignmentId = (int) DB::table('N_PA_Assignment')->insertGetId([
            'PA_Participant_Id' => $participantId,
            'Kode_Perusahaan_Penilai' => $penilai->Kode_Perusahaan,
            'Kode_Karyawan_Penilai' => $penilai->Kode_Karyawan,
            'Nama_Penilai_Saat_Cycle' => $penilai->Nama,
            'PA_Rater_Category_Id' => $kategoriId,
            'Bobot_Dalam_Kategori' => 100,
            'Status_Penugasan' => 'BELUM_MULAI',
        ], 'Id_PA_Assignment');

        $apiRef = bin2hex(random_bytes(16));
        $integrationId = (int) DB::table('N_PA_Assignment_Integration')->insertGetId([
            'PA_Assignment_Id' => $assignmentId,
            'PA_Cycle_Question_Package_Id' => $paketId,
            'Api_Ref' => $apiRef,
            'Short_Token' => 'UJIKL'.substr(bin2hex(random_bytes(2)), 0, 3),
            'Status_Integrasi' => 'BERHASIL',
            'Flag_Aktif' => 'Y',
        ], 'Id_PA_Assignment_Integration');

        return [
            'cycle_id' => $cycleId,
            'assignment_id' => $assignmentId,
            'integration_id' => $integrationId,
            'api_ref' => $apiRef,
            'kompetensi' => $kompetensi,
            'penilai' => $penilai,
        ];
    }
}
```

- [ ] **Langkah 2: Tulis `PaKalibrasiPenilaiService`**

```php
<?php

namespace App\Services\KPI\PerformanceAppraisal;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Kalibrasi Penilai — penilai mengubah skornya sendiri per kompetensi sebelum mengirim.
 *
 * BUKAN kalibrasi forum (PaCalibrationService), yang menggeser grade akhir sebuah hasil.
 * Keduanya sengaja tidak berbagi tabel maupun nama, supaya riwayat audit tidak pernah
 * mencampur "forum menurunkan grade" dengan "penilai merevisi skornya".
 *
 * Aturannya (spec §5.2) diperiksa di DALAM transaksi dengan lockForUpdate pada baris
 * penugasan: penilai bisa menekan kirim, atau admin melepas paksa, tepat saat kalibrasi
 * disimpan. Tanpa kunci, revisi bisa mendarat pada lembar yang sudah beku.
 */
class PaKalibrasiPenilaiService
{
    public const PANJANG_ALASAN = 500;

    /** Selisih di bawah ini dianggap "tidak berubah"; skornya disimpan dua desimal. */
    private const TOLERANSI = 0.005;

    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaSkorEfektif $skor,
    ) {}

    /**
     * @param  object  $penilai  Karyawan yang login: Kode_Perusahaan, Kode_Karyawan
     * @param  list<array{competency_id:int|string, skor:int|float|string}>  $perubahan
     * @return array{kalibrasi_id:int, assignment_id:int, cycle_id:int, kategori:string, diubah:int}
     */
    public function simpan(object $penilai, int $assignmentId, array $perubahan, ?string $alasan, ?int $olehUserId): array
    {
        $alasan = trim((string) $alasan);

        if ($alasan === '' || mb_strlen($alasan) > self::PANJANG_ALASAN) {
            throw new PaKalibrasiPenilaiException(
                'Alasan kalibrasi wajib diisi, paling panjang '.self::PANJANG_ALASAN.' karakter.',
                PaKalibrasiPenilaiException::ALASAN_WAJIB,
                422,
            );
        }

        return DB::transaction(function () use ($penilai, $assignmentId, $perubahan, $alasan, $olehUserId) {
            $tugas = DB::table('N_PA_Assignment as a')
                ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
                ->join('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'a.PA_Rater_Category_Id')
                ->where('a.Id_PA_Assignment', $assignmentId)
                ->where('a.Flag_Aktif', 'Y')
                ->lockForUpdate()
                ->first([
                    'a.Id_PA_Assignment', 'a.Status_Penugasan', 'a.Kode_Perusahaan_Penilai',
                    'a.Kode_Karyawan_Penilai', 'p.PA_Cycle_Id', 'c.Kode_Kategori', 'c.Flag_Boleh_Kalibrasi',
                ]);

            // Milik orang lain dijawab SAMA dengan yang tidak ada: keberadaannya tidak boleh bocor.
            if ($tugas === null || ! $this->milik($tugas, $penilai)) {
                throw new PaKalibrasiPenilaiException(
                    'Penugasan tidak ditemukan.',
                    PaKalibrasiPenilaiException::PENUGASAN_TIDAK_DITEMUKAN,
                    404,
                );
            }

            if (! $this->gate->boleh(PaCycleGate::KALIBRASI_PENILAI, (int) $tugas->PA_Cycle_Id)) {
                throw new PaKalibrasiPenilaiException(
                    'Kalibrasi Penilai hanya bisa dilakukan selama pengisian siklus masih terbuka.',
                    PaKalibrasiPenilaiException::SIKLUS_TIDAK_TERBUKA,
                    409,
                );
            }

            if ($tugas->Flag_Boleh_Kalibrasi !== 'Y') {
                throw new PaKalibrasiPenilaiException(
                    'Skor pada kategori penilai ini tidak bisa dikalibrasi.',
                    PaKalibrasiPenilaiException::KALIBRASI_DITUTUP,
                    403,
                );
            }

            if ($tugas->Status_Penugasan !== PaStatusPenugasan::TERKUMPUL) {
                throw new PaKalibrasiPenilaiException(
                    'Lembar ini berstatus '.$tugas->Status_Penugasan.'. Kalibrasi hanya untuk lembar '
                    .'yang sudah terisi dan belum dikirim.',
                    PaKalibrasiPenilaiException::LEMBAR_BEKU,
                    409,
                );
            }

            $rincian = $this->rincian($assignmentId, $perubahan);

            $kepalaId = (int) DB::table('N_PA_Kalibrasi_Penilai')->insertGetId([
                'PA_Assignment_Id' => $assignmentId,
                'Alasan' => $alasan,
                'Created_By' => $olehUserId,
            ], 'Id_PA_Kalibrasi_Penilai');

            DB::table('N_PA_Kalibrasi_Penilai_Detail')->insert(array_map(
                fn (array $r) => $r + ['PA_Kalibrasi_Penilai_Id' => $kepalaId],
                $rincian,
            ));

            return [
                'kalibrasi_id' => $kepalaId,
                'assignment_id' => $assignmentId,
                'cycle_id' => (int) $tugas->PA_Cycle_Id,
                'kategori' => (string) $tugas->Kode_Kategori,
                'diubah' => count($rincian),
            ];
        });
    }

    /**
     * Riwayat Kalibrasi Penilai atas satu peserta, terbaru lebih dulu — untuk layar admin.
     *
     * @return list<array<string, mixed>>
     */
    public function riwayatPeserta(int $participantId): array
    {
        $baris = DB::table('N_PA_Kalibrasi_Penilai as k')
            ->join('N_PA_Kalibrasi_Penilai_Detail as d', 'd.PA_Kalibrasi_Penilai_Id', '=', 'k.Id_PA_Kalibrasi_Penilai')
            ->join('N_PA_Assignment as a', 'a.Id_PA_Assignment', '=', 'k.PA_Assignment_Id')
            ->join('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'a.PA_Rater_Category_Id')
            ->join('N_PA_Competency as m', 'm.Id_PA_Competency', '=', 'd.PA_Competency_Id')
            ->where('a.PA_Participant_Id', $participantId)
            ->orderByDesc('k.Id_PA_Kalibrasi_Penilai')
            ->orderBy('m.Kode_Competency')
            ->get([
                'k.Id_PA_Kalibrasi_Penilai', 'k.PA_Assignment_Id', 'k.Alasan', 'k.Created_At', 'k.Created_By',
                'a.Kode_Karyawan_Penilai', 'a.Nama_Penilai_Saat_Cycle', 'c.Kode_Kategori', 'c.Nama_Kategori',
                'd.PA_Competency_Id', 'm.Kode_Competency', 'm.Nama_Competency',
                'd.Skor_Sebelum', 'd.Skor_Sesudah', 'd.Skor_Maksimal',
            ]);

        return $baris->groupBy('Id_PA_Kalibrasi_Penilai')->map(function ($kelompok) {
            $k = $kelompok->first();

            return [
                'kalibrasi_id' => (int) $k->Id_PA_Kalibrasi_Penilai,
                'assignment_id' => (int) $k->PA_Assignment_Id,
                'penilai' => ['kode_karyawan' => (string) $k->Kode_Karyawan_Penilai, 'nama' => (string) $k->Nama_Penilai_Saat_Cycle],
                'kategori' => ['kode' => (string) $k->Kode_Kategori, 'nama' => (string) $k->Nama_Kategori],
                'alasan' => (string) $k->Alasan,
                'pada' => substr((string) $k->Created_At, 0, 19),
                'oleh' => $k->Created_By === null ? null : (int) $k->Created_By,
                'perubahan' => $kelompok->map(fn ($d) => [
                    'competency_id' => (int) $d->PA_Competency_Id,
                    'kode' => (string) $d->Kode_Competency,
                    'nama' => (string) $d->Nama_Competency,
                    'skor_sebelum' => (float) $d->Skor_Sebelum,
                    'skor_sesudah' => (float) $d->Skor_Sesudah,
                    'skor_maksimal' => (float) $d->Skor_Maksimal,
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * Rincian yang benar-benar berubah, dengan Skor_Sebelum = skor efektif saat ini.
     *
     * @param  list<array{competency_id:int|string, skor:int|float|string}>  $perubahan
     * @return list<array{PA_Competency_Id:int, Skor_Sebelum:float, Skor_Sesudah:float, Skor_Maksimal:float}>
     */
    private function rincian(int $assignmentId, array $perubahan): array
    {
        $butir = collect($this->skor->butirBerskor([$assignmentId])[$assignmentId] ?? [])
            ->filter(fn ($b) => $b->PA_Competency_Id !== null)
            ->keyBy('PA_Competency_Id');

        $rincian = [];
        $dilihat = [];

        foreach ($perubahan as $p) {
            $kompetensiId = (int) ($p['competency_id'] ?? 0);
            $b = $butir->get($kompetensiId);

            if ($b === null) {
                throw new PaKalibrasiPenilaiException(
                    "Kompetensi {$kompetensiId} tidak ada di lembar ini, atau butirnya tidak berskor.",
                    PaKalibrasiPenilaiException::KOMPETENSI_TIDAK_DI_LEMBAR,
                    422,
                );
            }

            if (isset($dilihat[$kompetensiId])) {
                throw new RuntimeException("Kompetensi {$kompetensiId} dikirim lebih dari sekali.");
            }

            $dilihat[$kompetensiId] = true;
            $skor = $p['skor'] ?? null;

            if (! is_numeric($skor) || (float) $skor < 0 || (float) $skor > $b->Skor_Maksimal) {
                throw new PaKalibrasiPenilaiException(
                    'Skor harus di antara 0 dan '.$this->angka($b->Skor_Maksimal).'.',
                    PaKalibrasiPenilaiException::SKOR_DI_LUAR_RENTANG,
                    422,
                );
            }

            $baru = round((float) $skor, 2);

            if (abs($baru - $b->Skor) < self::TOLERANSI) {
                continue;
            }

            $rincian[] = [
                'PA_Competency_Id' => $kompetensiId,
                'Skor_Sebelum' => $b->Skor,
                'Skor_Sesudah' => $baru,
                'Skor_Maksimal' => $b->Skor_Maksimal,
            ];
        }

        if ($rincian === []) {
            throw new PaKalibrasiPenilaiException(
                'Tidak ada skor yang berubah dari nilai yang sekarang berlaku.',
                PaKalibrasiPenilaiException::TIDAK_ADA_PERUBAHAN,
                422,
            );
        }

        return $rincian;
    }

    private function milik(object $tugas, object $penilai): bool
    {
        $sama = fn ($a, $b) => strtoupper(trim((string) $a)) === strtoupper(trim((string) $b));

        return $sama($tugas->Kode_Perusahaan_Penilai, $penilai->Kode_Perusahaan)
            && $sama($tugas->Kode_Karyawan_Penilai, $penilai->Kode_Karyawan);
    }

    private function angka(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');
    }
}
```

- [ ] **Langkah 3: Pemeriksaan statis dan commit**

```bash
php -l app/Services/KPI/PerformanceAppraisal/PaKalibrasiPenilaiService.php
vendor/bin/pint app/Services/KPI/PerformanceAppraisal/PaKalibrasiPenilaiService.php tests/Feature/KPI/PerformanceAppraisal/KalibrasiPenilaiTest.php
git add app/Services/KPI/PerformanceAppraisal/PaKalibrasiPenilaiService.php tests/Feature/KPI/PerformanceAppraisal/KalibrasiPenilaiTest.php
git commit -m "feat(pa): PaKalibrasiPenilaiService dengan kunci baris dan riwayat peserta"
```

---

### Tugas 6: Lingkup "tugas saya" dipakai bersama, dan satu sumber urutan kategori

**Berkas:**
- Ubah: `app/Services/KPI/PerformanceAppraisal/PaPesertaService.php`

**Antarmuka:**
- Menghasilkan:
  - `PaPesertaService::KOLOM_TUGAS` (array kolom yang sama dengan `daftarAssignment()` hari ini).
  - `public function karyawan(int $userId): object` (semula privat, isi tidak berubah).
  - `public function kueriPenugasan(object $saya, ?int $cycleId)` (semula privat, isi tidak berubah).
  - `public function barisTugas(object $b, object $saya, bool $simulasiLokal): array` (isi penutup `map` di `daftarAssignment()` dipindah apa adanya).
  - `urutanKategori()` membaca `N_PA_Rater_Category.Urutan_Pengisian` lebih dulu.

- [ ] **Langkah 1: Konstanta kolom tugas**

Tambahkan di bawah `STATUS_SIKLUS_DAPAT_DIISI`:

```php
    /**
     * Kolom baris tugas. Satu daftar untuk daftar tugas lama dan wizard, karena `barisTugas()`
     * membaca kolom-kolom ini; dua salinan daftar berarti satu layar kehilangan tautan lembar
     * begitu kolom ditambah di salinan yang lain.
     */
    public const KOLOM_TUGAS = [
        'a.Id_PA_Assignment', 'a.Status_Penugasan', 'a.Due_At', 'a.Started_At', 'a.Finished_At',
        'c.Kode_Kategori', 'c.Nama_Kategori',
        'p.Id_PA_Participant', 'p.Kode_Karyawan', 'p.Nama_Karyawan_Saat_Cycle',
        'p.Nama_Jabatan_Saat_Cycle', 'p.Nama_Divisi_Saat_Cycle',
        'k.Kode_Cycle', 'k.Nama_Cycle', 'k.Tahun',
        'i.Id_Ujian_Token', 'i.Link_Ujian', 'i.Short_Token', 'i.Akses_Otp',
        'i.Status_Integrasi',
    ];
```

Di `daftarAssignment()`, ganti argumen `->get([...])` yang berisi daftar kolom itu dengan `->get(self::KOLOM_TUGAS)`.

- [ ] **Langkah 2: Keluarkan penutup `map` menjadi `barisTugas()`**

Di `daftarAssignment()`, penutup yang dimulai dengan `->map(function ($b) use ($saya, $simulasiLokal) {` dan berakhir dengan `})` sebelum `->values()` diganti menjadi:

```php
            ->map(fn ($b) => $this->barisTugas($b, $saya, $simulasiLokal))
```

Isinya dipindah **apa adanya** — termasuk seluruh komentar tentang `url_ujian`, `short_token`, `status_pintu`, dan `diri_sendiri` — ke method publik baru, tepat di bawah `daftarAssignment()`:

```php
    /**
     * Satu baris tugas siap tampil: keterlambatan, alamat lembar, kode akses, dan penanda
     * penilaian diri. Dipakai daftar tugas lama dan wizard, supaya aturan "tautan lembar
     * datang dari HC Learn, simulator lokal hanya cadangan di luar production" tidak pernah
     * punya dua salinan.
     *
     * @return array<string, mixed>
     */
    public function barisTugas(object $b, object $saya, bool $simulasiLokal): array
    {
        $k = $this->keterlambatan($b);

        return (array) $b + [
            // ... isi array yang sama persis dengan penutup lama, tanpa perubahan satu baris pun
        ];
    }
```

Komentar `// ... isi array ...` di atas hanya penunjuk untuk rencana ini; di berkas sungguhan, isinya adalah array yang dipindah utuh dari penutup lama (kunci `terlambat`, `hari_terlambat`, `sisa_hari`, `url_ujian`, `short_token`, `akses_otp`, `status_pintu`, `diri_sendiri`). Periksa dengan `git diff -w`: satu-satunya perubahan pada baris-baris itu harus indentasi.

- [ ] **Langkah 3: Buka dua method lingkup**

Ubah `private function karyawan(int $userId): object` menjadi `public function karyawan(int $userId): object`, dan `private function kueriPenugasan(object $saya, ?int $cycleId)` menjadi `public function kueriPenugasan(object $saya, ?int $cycleId)`. Tambahkan satu baris di docblock masing-masing:

```php
     * Publik karena PaWizardPenilaianService memakai lingkup yang SAMA; lingkup "tugas saya"
     * yang ditulis dua kali akan berselisih, dan yang berselisih menentukan apa yang dilihat
     * penilai tentang pekerjaannya sendiri.
```

- [ ] **Langkah 4: Satu sumber urutan kategori**

Ganti isi `urutanKategori()` (docblock ikut diperbarui):

```php
    /**
     * Urutan tampil kategori penilai.
     *
     * SUMBER UTAMANYA `N_PA_Rater_Category.Urutan_Pengisian` (DDL-2026-119), yang juga menata
     * langkah wizard. Panel progres dan wizard wajib menampilkan urutan yang sama; dua sumber
     * urutan pada satu halaman terbaca sebagai dua aturan.
     *
     * Kategori yang belum diberi urutan di master jatuh ke belakang, dan di antara mereka
     * urutan lama (MIN `Urutan_Tampil` kebijakan penilai lintas level) dipertahankan.
     *
     * @return array<string, int>
     */
    private function urutanKategori(): array
    {
        $master = DB::table('N_PA_Rater_Category')
            ->where('Flag_Aktif', 'Y')
            ->whereNotNull('Urutan_Pengisian')
            ->pluck('Urutan_Pengisian', 'Kode_Kategori')
            ->map(fn ($u) => (int) $u)
            ->all();

        $cadangan = DB::table('N_PA_Rater_Policy_Detail as d')
            ->join('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'd.PA_Rater_Category_Id')
            ->where('d.Flag_Aktif', 'Y')
            ->groupBy('c.Kode_Kategori')
            ->get(['c.Kode_Kategori as kode', DB::raw('MIN(d.Urutan_Tampil) as urutan')])
            ->mapWithKeys(fn ($r) => [$r->kode => (int) $r->urutan])
            ->all();

        $dasar = $master === [] ? 0 : max($master);
        $hasil = $master;

        foreach ($cadangan as $kode => $urutan) {
            $hasil[$kode] ??= $dasar + 1000 + $urutan;
        }

        return $hasil;
    }
```

- [ ] **Langkah 5: Pemeriksaan statis dan commit**

```bash
php -l app/Services/KPI/PerformanceAppraisal/PaPesertaService.php
vendor/bin/pint app/Services/KPI/PerformanceAppraisal/PaPesertaService.php
git diff -w --stat app/Services/KPI/PerformanceAppraisal/PaPesertaService.php
git add app/Services/KPI/PerformanceAppraisal/PaPesertaService.php
git commit -m "refactor(pa): lingkup tugas saya dipakai bersama, urutan kategori dari master"
```

Uji yang sudah ada (`PesertaTest`, `KontrakResponsTest::test_assignment_saya_sebentuk_dengan_fixture`) menjaga bahwa daftar tugas lama tidak berubah; keduanya dijalankan di Tugas 14.

---

### Tugas 7: Wizard penilai — service, controller, rute, kontrak

**Berkas:**
- Buat: `app/Services/KPI/PerformanceAppraisal/PaWizardPenilaianService.php`
- Buat: `app/Http/Controllers/KPI/PerformanceAppraisal/Peserta/PaWizardPenilaianController.php`
- Ubah: `routes/KPI/PerformanceAppraisal/Peserta/PerformanceAppraisalPeserta.php`
- Buat: `resources/js/Pages/KPI/PerformanceAppraisal/api/fixtures/saya-wizard.json`, `saya-lembar.json`, `saya-pratinjau.json`, `saya-kalibrasi.json`
- Ubah: `docs/superpowers/specs/2026-09-01-kontrak-api-performance-appraisal.md`
- Uji: `tests/Feature/KPI/PerformanceAppraisal/WizardPenilaianTest.php`, tambahan di `KontrakResponsTest.php`

**Antarmuka:**
- Mengonsumsi: `PaPesertaService::{karyawan, kueriPenugasan, barisTugas, kemajuanPengiriman, KOLOM_TUGAS}`, `PaSkorEfektif::{butirBerskor, jawabanNaratif}`, `PaKalibrasiPenilaiService::simpan()`, `PaCycleGate::boleh()`.
- Menghasilkan:
  - `PaWizardPenilaianService::wizard(int $userId, ?int $cycleId): array{siklus:?array, langkah:list, langkah_aktif:?string, pengiriman:?array, boleh_kirim:bool}`
  - `PaWizardPenilaianService::lembar(int $userId, string $kode, ?int $cycleId): array{siklus, langkah, lembar:list, total:int, dipangkas:bool}`
  - `PaWizardPenilaianService::pratinjau(int $userId, string $kode, ?int $cycleId): array{siklus, kategori, kompetensi:list, orang:list, dipangkas:bool}`
  - `PaWizardPenilaianService::LANGKAH_RINGKASAN = 'RINGKASAN'`
  - HTTP: `GET /api/v1/performance-appraisal/saya/wizard`, `GET .../saya/wizard/langkah/{kode}/lembar`, `GET .../saya/wizard/langkah/{kode}/pratinjau`, `POST .../saya/kalibrasi`.

- [ ] **Langkah 1: Tulis `WizardPenilaianTest`**

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

class WizardPenilaianTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanLembarPenilaian;

    private const API = '/api/v1/performance-appraisal/saya';

    protected function setUp(): void
    {
        parent::setUp();

        // Urutan ditetapkan di sini, tidak menumpang DML-2026-124: uji yang bergantung pada
        // data yang tidak ia buat akan memerah begitu datanya berubah, dengan sebab yang salah.
        DB::table('N_PA_Rater_Category')->update(['Urutan_Pengisian' => null]);
        foreach (['SELF' => 1, 'ATASAN' => 2, 'PEER' => 3, 'BAWAHAN' => 4] as $kode => $urutan) {
            DB::table('N_PA_Rater_Category')->where('Kode_Kategori', $kode)->update(['Urutan_Pengisian' => $urutan]);
        }
    }

    public function test_langkah_berurutan_menurut_master_dan_terkunci_sampai_sebelumnya_tuntas(): void
    {
        $self = $this->lembarTerisi(['kategori' => 'SELF', 'status_penugasan' => 'BELUM_MULAI']);
        $this->lembarTerisi(['kategori' => 'ATASAN', 'cycle_id' => $self['cycle_id']]);

        $this->actingAs($this->penilaiUji());
        $r = $this->getJson(self::API.'/wizard?cycle_id='.$self['cycle_id'])->assertOk();

        $this->assertSame(['SELF', 'ATASAN'], array_column($r->json('result.langkah'), 'kode_kategori'));
        $this->assertSame(['AKTIF', 'TERKUNCI'], array_column($r->json('result.langkah'), 'status'));
        $this->assertStringContainsString('Diri Sendiri', (string) $r->json('result.langkah.1.sebab_terkunci'));
        $this->assertSame('SELF', $r->json('result.langkah_aktif'));
    }

    public function test_kategori_tanpa_lembar_dilompati_dan_semua_tuntas_menuju_ringkasan(): void
    {
        $l = $this->lembarTerisi(['kategori' => 'ATASAN']);

        $this->actingAs($this->penilaiUji());
        $r = $this->getJson(self::API.'/wizard?cycle_id='.$l['cycle_id'])->assertOk();

        $this->assertSame(['ATASAN'], array_column($r->json('result.langkah'), 'kode_kategori'));
        $this->assertSame('TUNTAS', $r->json('result.langkah.0.status'));
        $this->assertSame('RINGKASAN', $r->json('result.langkah_aktif'));
        $this->assertTrue($r->json('result.boleh_kirim'));
    }

    public function test_langkah_terkunci_tidak_membawa_akses_lembar(): void
    {
        $self = $this->lembarTerisi(['kategori' => 'SELF', 'status_penugasan' => 'BELUM_MULAI']);
        $atasan = $this->lembarTerisi(['kategori' => 'ATASAN', 'status_penugasan' => 'BELUM_MULAI', 'cycle_id' => $self['cycle_id']]);
        DB::table('N_PA_Assignment_Integration')->where('PA_Assignment_Id', $atasan['assignment_id'])
            ->update(['Short_Token' => 'KODE1', 'Akses_Otp' => '123456', 'Link_Ujian' => 'https://hclearn.contoh/ujian/1']);

        $this->actingAs($this->penilaiUji());
        $r = $this->getJson(self::API.'/wizard/langkah/ATASAN/lembar?cycle_id='.$self['cycle_id'])->assertOk();

        $this->assertSame('TERKUNCI', $r->json('result.langkah.status'));
        $baris = $r->json('result.lembar.0');
        $this->assertNull($baris['url_ujian']);
        $this->assertNull($baris['short_token']);
        $this->assertNull($baris['akses_otp']);
        $this->assertStringNotContainsString('KODE1', $r->getContent(), 'Kode akses bocor lewat kolom lain.');
    }

    public function test_pratinjau_rata_rata_ternormalisasi_dan_kolom_gabungan(): void
    {
        $a = $this->lembarTerisi(['skor' => [80, 60], 'skor_maksimal' => 100]);
        $b = $this->lembarTerisi(['skor' => [4], 'skor_maksimal' => 5, 'cycle_id' => $a['cycle_id']]);

        $this->actingAs($this->penilaiUji());
        $r = $this->getJson(self::API.'/wizard/langkah/ATASAN/pratinjau?cycle_id='.$a['cycle_id'])->assertOk();

        $kolom = collect($r->json('result.kompetensi'))->keyBy('competency_id');
        // (80/100 + 4/5) dinormalkan: (80 + 80) / 2 = 80; kompetensi kedua hanya dimiliki satu orang.
        $this->assertEquals(80.0, $kolom[$a['kompetensi'][0]]['rata_rata']);
        $this->assertSame(2, $kolom[$a['kompetensi'][0]]['jumlah_dinilai']);
        $this->assertSame(1, $kolom[$a['kompetensi'][1]]['jumlah_dinilai']);
        $this->assertCount(2, $r->json('result.orang'));
    }

    public function test_boleh_diubah_mengikuti_penanda_kategori_dan_status_lembar(): void
    {
        $l = $this->lembarTerisi(['boleh_kalibrasi' => 'T']);
        $this->actingAs($this->penilaiUji());

        $r = $this->getJson(self::API.'/wizard/langkah/ATASAN/pratinjau?cycle_id='.$l['cycle_id'])->assertOk();
        $this->assertFalse($r->json('result.orang.0.boleh_diubah'));

        DB::table('N_PA_Rater_Category')->where('Kode_Kategori', 'ATASAN')->update(['Flag_Boleh_Kalibrasi' => 'Y']);
        $r = $this->getJson(self::API.'/wizard/langkah/ATASAN/pratinjau?cycle_id='.$l['cycle_id'])->assertOk();
        $this->assertTrue($r->json('result.orang.0.boleh_diubah'));
    }

    public function test_kalibrasi_lewat_http_mengembalikan_pratinjau_terbaru(): void
    {
        $l = $this->lembarTerisi(['skor' => [70, 90], 'boleh_kalibrasi' => 'Y']);
        $this->actingAs($this->penilaiUji());

        $r = $this->postJson(self::API.'/kalibrasi', [
            'assignment_id' => $l['assignment_id'],
            'perubahan' => [['competency_id' => $l['kompetensi'][0], 'skor' => 50]],
            'alasan' => 'Terlalu tinggi dibanding rekan',
        ])->assertOk();

        $sel = collect($r->json('result.pratinjau.orang.0.sel'))->keyBy('competency_id');
        $this->assertEquals(50.0, $sel[$l['kompetensi'][0]]['skor_efektif']);
        $this->assertEquals(70.0, $sel[$l['kompetensi'][0]]['skor_asli']);
        $this->assertTrue($sel[$l['kompetensi'][0]]['terkalibrasi']);
        $this->assertSame('Terlalu tinggi dibanding rekan', $r->json('result.pratinjau.orang.0.kalibrasi_terakhir.alasan'));
    }

    public function test_galat_kalibrasi_membawa_kode_stabil(): void
    {
        $l = $this->lembarTerisi(['boleh_kalibrasi' => 'T']);
        $this->actingAs($this->penilaiUji());

        $r = $this->postJson(self::API.'/kalibrasi', [
            'assignment_id' => $l['assignment_id'],
            'perubahan' => [['competency_id' => $l['kompetensi'][0], 'skor' => 50]],
            'alasan' => 'x',
        ]);

        $r->assertStatus(403);
        $this->assertSame('PA_KALIBRASI_DITUTUP', $r->json('error_code'));
        $this->assertNotEmpty($r->json('request_id'));
    }

    public function test_langkah_yang_bukan_milik_saya_dijawab_404(): void
    {
        $l = $this->lembarTerisi(['kategori' => 'ATASAN']);
        $this->actingAs($this->penilaiUji());

        $r = $this->getJson(self::API.'/wizard/langkah/PEER/pratinjau?cycle_id='.$l['cycle_id']);

        $r->assertStatus(404);
        $this->assertSame('PA_LANGKAH_TIDAK_DIKENAL', $r->json('error_code'));
    }

    public function test_panel_progres_dan_wizard_memakai_urutan_yang_sama(): void
    {
        DB::table('N_PA_Rater_Category')->update(['Urutan_Pengisian' => null]);
        DB::table('N_PA_Rater_Category')->where('Kode_Kategori', 'PEER')->update(['Urutan_Pengisian' => 1]);
        DB::table('N_PA_Rater_Category')->where('Kode_Kategori', 'ATASAN')->update(['Urutan_Pengisian' => 2]);

        $a = $this->lembarTerisi(['kategori' => 'ATASAN']);
        $this->lembarTerisi(['kategori' => 'PEER', 'cycle_id' => $a['cycle_id']]);
        $this->actingAs($this->penilaiUji());

        $wizard = array_column($this->getJson(self::API.'/wizard?cycle_id='.$a['cycle_id'])->json('result.langkah'), 'kode_kategori');
        $progres = array_column($this->getJson(self::API.'/progres-kategori?cycle_id='.$a['cycle_id'])->json('result.kategori'), 'kode_kategori');

        $this->assertSame(['PEER', 'ATASAN'], $wizard);
        $this->assertSame($wizard, $progres);
    }
}
```

- [ ] **Langkah 2: Tulis `PaWizardPenilaianService`**

```php
<?php

namespace App\Services\KPI\PerformanceAppraisal;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Wizard tugas penilaian: langkah per kategori penilai, lembar per langkah, dan pratinjau
 * Kalibrasi Penilai di tiap transisi (spec §5.3, §6).
 *
 * Lingkupnya SELALU `PaPesertaService::kueriPenugasan()` — siklus yang dapat diisi, penugasan
 * aktif milik penilai yang login, bukan yang dibatalkan. Tidak ada kueri lingkup kedua di
 * berkas ini, dan jangan ditambahkan.
 *
 * Kunci urutan berlaku di HCIS: langkah terkunci tidak membawa tautan maupun kode akses. Ia
 * tidak mengikat HC Learn — orang yang sudah memegang tautan tetap bisa mengisi lebih dulu,
 * dan kirimannya tetap diterima.
 */
class PaWizardPenilaianService
{
    public const LANGKAH_RINGKASAN = 'RINGKASAN';

    public const MAKS_LEMBAR = 200;

    /** Status yang dihitung "sudah dikerjakan" untuk membuka langkah berikutnya. */
    private const STATUS_TERISI = [
        PaStatusPenugasan::TERKUMPUL,
        PaStatusPenugasan::SELESAI,
        PaStatusPenugasan::MENUNGGU_VERIFIKASI,
    ];

    public function __construct(
        private readonly PaPesertaService $peserta,
        private readonly PaSkorEfektif $skor,
        private readonly PaCycleGate $gate,
    ) {}

    /** @return array<string, mixed> */
    public function wizard(int $userId, ?int $cycleId = null): array
    {
        $saya = $this->peserta->karyawan($userId);
        $siklus = $this->siklus($saya, $cycleId);

        if ($siklus === null) {
            return ['siklus' => null, 'langkah' => [], 'langkah_aktif' => null, 'pengiriman' => null, 'boleh_kirim' => false];
        }

        $langkah = $this->langkah($saya, (int) $siklus->Id_PA_Cycle);
        $aktif = collect($langkah)->firstWhere('status', 'AKTIF');
        $pengiriman = $this->peserta->kemajuanPengiriman($userId, (int) $siklus->Id_PA_Cycle)[0] ?? null;

        return [
            'siklus' => $this->bentukSiklus($siklus),
            'langkah' => $langkah,
            // Ditentukan SERVER dari posisi, bukan ditebak layar dari kelengkapan isi.
            'langkah_aktif' => $aktif['kode_kategori'] ?? ($langkah === [] ? null : self::LANGKAH_RINGKASAN),
            'pengiriman' => $pengiriman,
            'boleh_kirim' => (bool) ($pengiriman['bisa_kirim'] ?? false),
        ];
    }

    /** @return array<string, mixed> */
    public function lembar(int $userId, string $kodeKategori, ?int $cycleId = null): array
    {
        $saya = $this->peserta->karyawan($userId);
        $siklus = $this->siklusAtauGagal($saya, $cycleId);
        $langkah = $this->langkahAtauGagal($saya, (int) $siklus->Id_PA_Cycle, $kodeKategori);
        $terkunci = $langkah['status'] === 'TERKUNCI';
        $simulasiLokal = ! app()->isProduction();

        $dasar = fn () => $this->peserta->kueriPenugasan($saya, (int) $siklus->Id_PA_Cycle)
            ->where('c.Kode_Kategori', $kodeKategori);

        $total = (int) $dasar()->distinct()->count('a.Id_PA_Assignment');

        $baris = $dasar()
            ->orderBy('p.Nama_Karyawan_Saat_Cycle')
            ->orderBy('a.Id_PA_Assignment')
            ->limit(self::MAKS_LEMBAR)
            ->get(PaPesertaService::KOLOM_TUGAS)
            ->unique('Id_PA_Assignment')
            ->map(function ($b) use ($saya, $simulasiLokal, $terkunci) {
                $r = $this->peserta->barisTugas($b, $saya, $simulasiLokal);

                /* Bentuk yang DIKURASI, bukan baris mentah. Baris mentah membawa
                   Id_Ujian_Token, Link_Ujian, Short_Token, dan Akses_Otp apa adanya — dan
                   penguncian yang hanya mengosongkan versi olahannya akan membocorkan kodenya
                   lewat kolom mentah itu. */
                return [
                    'assignment_id' => (int) $r['Id_PA_Assignment'],
                    'status_penugasan' => (string) $r['Status_Penugasan'],
                    'peserta' => [
                        'kode_karyawan' => (string) $r['Kode_Karyawan'],
                        'nama' => (string) $r['Nama_Karyawan_Saat_Cycle'],
                        'jabatan' => $r['Nama_Jabatan_Saat_Cycle'],
                        'divisi' => $r['Nama_Divisi_Saat_Cycle'],
                    ],
                    'diri_sendiri' => (bool) $r['diri_sendiri'],
                    'tenggat' => $r['Due_At'] === null ? null : substr((string) $r['Due_At'], 0, 19),
                    'terlambat' => (bool) $r['terlambat'],
                    'hari_terlambat' => $r['hari_terlambat'],
                    'sisa_hari' => $r['sisa_hari'],
                    'url_ujian' => $terkunci ? null : $r['url_ujian'],
                    'short_token' => $terkunci ? null : $r['short_token'],
                    'akses_otp' => $terkunci ? null : $r['akses_otp'],
                    'status_pintu' => $r['status_pintu'],
                ];
            })
            ->values()
            ->all();

        return [
            'siklus' => $this->bentukSiklus($siklus),
            'langkah' => $langkah,
            'lembar' => $baris,
            'total' => $total,
            'dipangkas' => $total > self::MAKS_LEMBAR,
        ];
    }

    /** @return array<string, mixed> */
    public function pratinjau(int $userId, string $kodeKategori, ?int $cycleId = null): array
    {
        $saya = $this->peserta->karyawan($userId);
        $siklus = $this->siklusAtauGagal($saya, $cycleId);
        $cycle = (int) $siklus->Id_PA_Cycle;
        $langkah = $this->langkahAtauGagal($saya, $cycle, $kodeKategori);
        $siklusTerbuka = $this->gate->boleh(PaCycleGate::KALIBRASI_PENILAI, $cycle);

        $dasar = fn () => $this->peserta->kueriPenugasan($saya, $cycle)->where('c.Kode_Kategori', $kodeKategori);
        $total = (int) $dasar()->distinct()->count('a.Id_PA_Assignment');

        $tugas = $dasar()
            ->orderBy('p.Nama_Karyawan_Saat_Cycle')
            ->orderBy('a.Id_PA_Assignment')
            ->limit(self::MAKS_LEMBAR)
            ->get(['a.Id_PA_Assignment', 'a.Status_Penugasan', 'p.Kode_Karyawan', 'p.Nama_Karyawan_Saat_Cycle',
                'p.Nama_Jabatan_Saat_Cycle', 'p.Nama_Divisi_Saat_Cycle'])
            ->unique('Id_PA_Assignment')
            ->values();

        $ids = $tugas->pluck('Id_PA_Assignment')->map(fn ($x) => (int) $x)->all();
        $butir = $this->skor->butirBerskor($ids);
        $naratif = $this->skor->jawabanNaratif($ids);
        $terakhir = $this->kalibrasiTerakhir($ids);
        $meta = $this->metaKompetensi($butir, $naratif);

        // Rata-rata pada skala 0-100 dan HANYA atas orang yang memiliki kompetensi itu:
        // rata-rata skor mentah dari skala berbeda tidak bermakna.
        $normal = [];
        foreach ($butir as $list) {
            foreach ($list as $b) {
                if ($b->PA_Competency_Id !== null) {
                    $normal[$b->PA_Competency_Id][] = $b->Skor / $b->Skor_Maksimal * 100;
                }
            }
        }

        $kompetensi = $meta
            ->filter(fn ($m) => isset($normal[(int) $m->id]))
            ->map(fn ($m) => [
                'competency_id' => (int) $m->id,
                'kode' => (string) $m->kode,
                'nama' => (string) $m->nama,
                'jenis_kode' => (string) $m->jenis_kode,
                'jenis_nama' => (string) $m->jenis_nama,
                'rata_rata' => round(array_sum($normal[(int) $m->id]) / count($normal[(int) $m->id]), 2),
                'jumlah_dinilai' => count($normal[(int) $m->id]),
            ])
            ->values()
            ->all();

        $namaKompetensi = $meta->keyBy('id');
        $bolehKategori = (bool) $langkah['boleh_kalibrasi'];

        $orang = $tugas->map(function ($t) use ($butir, $naratif, $terakhir, $namaKompetensi, $saya, $siklusTerbuka, $bolehKategori) {
            $aid = (int) $t->Id_PA_Assignment;
            $sel = collect($butir[$aid] ?? [])
                ->filter(fn ($b) => $b->PA_Competency_Id !== null)
                ->map(fn ($b) => [
                    'competency_id' => $b->PA_Competency_Id,
                    'skor_asli' => $b->Skor_Asli,
                    'skor_efektif' => $b->Skor,
                    'skor_maksimal' => $b->Skor_Maksimal,
                    'terkalibrasi' => $b->Terkalibrasi,
                ])
                ->values()
                ->all();

            return [
                'assignment_id' => $aid,
                'peserta' => [
                    'kode_karyawan' => (string) $t->Kode_Karyawan,
                    'nama' => (string) $t->Nama_Karyawan_Saat_Cycle,
                    'jabatan' => $t->Nama_Jabatan_Saat_Cycle,
                    'divisi' => $t->Nama_Divisi_Saat_Cycle,
                ],
                'diri_sendiri' => strtoupper(trim((string) $t->Kode_Karyawan)) === strtoupper(trim((string) $saya->Kode_Karyawan)),
                'status_penugasan' => (string) $t->Status_Penugasan,
                'boleh_diubah' => $bolehKategori && $siklusTerbuka
                    && $t->Status_Penugasan === PaStatusPenugasan::TERKUMPUL && $sel !== [],
                'kalibrasi_terakhir' => $terakhir[$aid] ?? null,
                'sel' => $sel,
                'naratif' => collect($naratif[$aid] ?? [])->map(fn ($n) => [
                    'competency_id' => $n['competency_id'],
                    'nama' => $n['competency_id'] === null ? null : ($namaKompetensi[$n['competency_id']]->nama ?? null),
                    'jawaban' => $n['jawaban'],
                ])->values()->all(),
            ];
        })->values()->all();

        return [
            'siklus' => $this->bentukSiklus($siklus),
            'kategori' => [
                'kode' => $langkah['kode_kategori'],
                'nama' => $langkah['nama_kategori'],
                'boleh_kalibrasi' => $bolehKategori,
                'status' => $langkah['status'],
            ],
            'kompetensi' => $kompetensi,
            'orang' => $orang,
            'dipangkas' => $total > self::MAKS_LEMBAR,
        ];
    }

    /* ------------------------------------------------------------------ *
     * Bagian dalam                                                        *
     * ------------------------------------------------------------------ */

    /** Siklus terbuka tempat saya punya tugas; `cycle_id` hanya mempersempit. */
    private function siklus(object $saya, ?int $cycleId): ?object
    {
        return $this->peserta->kueriPenugasan($saya, $cycleId)
            ->orderByDesc('k.Id_PA_Cycle')
            ->first(['k.Id_PA_Cycle', 'k.Kode_Cycle', 'k.Nama_Cycle', 'k.Tahun']);
    }

    private function siklusAtauGagal(object $saya, ?int $cycleId): object
    {
        return $this->siklus($saya, $cycleId) ?? throw new PaKalibrasiPenilaiException(
            'Tidak ada siklus terbuka yang memuat tugas penilaian Anda.',
            PaKalibrasiPenilaiException::LANGKAH_TIDAK_DIKENAL,
            404,
        );
    }

    /** @return array<string, mixed> */
    private function langkahAtauGagal(object $saya, int $cycleId, string $kode): array
    {
        return collect($this->langkah($saya, $cycleId))->firstWhere('kode_kategori', $kode)
            ?? throw new PaKalibrasiPenilaiException(
                "Anda tidak punya tugas penilaian kategori {$kode} pada siklus ini.",
                PaKalibrasiPenilaiException::LANGKAH_TIDAK_DIKENAL,
                404,
            );
    }

    /** @return list<array<string, mixed>> */
    private function langkah(object $saya, int $cycleId): array
    {
        $tugas = $this->peserta->kueriPenugasan($saya, $cycleId)
            ->get(['a.Id_PA_Assignment', 'a.Status_Penugasan', 'c.Id_PA_Rater_Category', 'c.Kode_Kategori',
                'c.Nama_Kategori', 'c.Urutan_Pengisian', 'c.Flag_Boleh_Kalibrasi'])
            ->unique('Id_PA_Assignment');

        $terkalibrasi = $this->penugasanTerkalibrasi($tugas->pluck('Id_PA_Assignment')->map(fn ($x) => (int) $x)->all());

        $kelompok = $tugas->groupBy('Kode_Kategori')
            ->map(function (Collection $baris) use ($terkalibrasi) {
                $k = $baris->first();

                return [
                    'id' => (int) $k->Id_PA_Rater_Category,
                    'urutan' => $k->Urutan_Pengisian === null ? null : (int) $k->Urutan_Pengisian,
                    'kode_kategori' => (string) $k->Kode_Kategori,
                    'nama_kategori' => (string) $k->Nama_Kategori,
                    'boleh_kalibrasi' => $k->Flag_Boleh_Kalibrasi === 'Y',
                    'jumlah_lembar' => $baris->count(),
                    'jumlah_terisi' => $baris->whereIn('Status_Penugasan', self::STATUS_TERISI)->count(),
                    'jumlah_terkalibrasi' => $baris->filter(fn ($b) => isset($terkalibrasi[(int) $b->Id_PA_Assignment]))->count(),
                ];
            })
            ->sort(fn ($a, $b) => [$a['urutan'] ?? PHP_INT_MAX, $a['id']] <=> [$b['urutan'] ?? PHP_INT_MAX, $b['id']])
            ->values();

        $sebelumnyaTuntas = true;
        $penghambat = null;
        $hasil = [];

        foreach ($kelompok as $i => $l) {
            $tuntas = $l['jumlah_terisi'] >= $l['jumlah_lembar'];

            $status = ! $sebelumnyaTuntas ? 'TERKUNCI' : ($tuntas ? 'TUNTAS' : 'AKTIF');
            $sebab = $status === 'TERKUNCI' ? "Selesaikan penilaian {$penghambat} lebih dulu." : null;

            if ($sebelumnyaTuntas && ! $tuntas) {
                $penghambat = $l['nama_kategori'];
            }

            $sebelumnyaTuntas = $sebelumnyaTuntas && $tuntas;
            unset($l['id']);

            $hasil[] = $l + ['langkah_ke' => $i + 1, 'status' => $status, 'sebab_terkunci' => $sebab];
        }

        return $hasil;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, true>
     */
    private function penugasanTerkalibrasi(array $ids): array
    {
        $hasil = [];

        foreach (array_chunk($ids, 500) as $potongan) {
            foreach (DB::table('N_PA_Kalibrasi_Penilai')->whereIn('PA_Assignment_Id', $potongan)
                ->distinct()->pluck('PA_Assignment_Id') as $id) {
                $hasil[(int) $id] = true;
            }
        }

        return $hasil;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{alasan:string, pada:string}>
     */
    private function kalibrasiTerakhir(array $ids): array
    {
        $hasil = [];

        foreach (array_chunk($ids, 500) as $potongan) {
            foreach (DB::table('N_PA_Kalibrasi_Penilai')->whereIn('PA_Assignment_Id', $potongan)
                ->orderByDesc('Id_PA_Kalibrasi_Penilai')
                ->get(['PA_Assignment_Id', 'Alasan', 'Created_At']) as $k) {
                $hasil[(int) $k->PA_Assignment_Id] ??= [
                    'alasan' => (string) $k->Alasan,
                    'pada' => substr((string) $k->Created_At, 0, 19),
                ];
            }
        }

        return $hasil;
    }

    /**
     * Nama dan jenis seluruh kompetensi yang muncul, diurut seperti layar pelabelan paket
     * (jenis lalu kode), supaya kolom matriks punya urutan yang sama dengan yang dikenal admin.
     *
     * @param  array<int, Collection<int, object>>  $butir
     * @param  array<int, list<array{competency_id:?int, jawaban:string}>>  $naratif
     */
    private function metaKompetensi(array $butir, array $naratif): Collection
    {
        $ids = collect($butir)->flatten(1)->pluck('PA_Competency_Id')
            ->merge(collect($naratif)->flatten(1)->pluck('competency_id'))
            ->filter()
            ->map(fn ($x) => (int) $x)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return DB::table('N_PA_Competency as c')
            ->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'c.PA_Competency_Type_Id')
            ->whereIn('c.Id_PA_Competency', $ids)
            ->orderBy('t.Kode_Type')
            ->orderBy('c.Kode_Competency')
            ->get(['c.Id_PA_Competency as id', 'c.Kode_Competency as kode', 'c.Nama_Competency as nama',
                't.Kode_Type as jenis_kode', 't.Nama_Type as jenis_nama']);
    }

    /** @return array{cycle_id:int, kode_cycle:string, nama_cycle:string, tahun:int} */
    private function bentukSiklus(object $s): array
    {
        return [
            'cycle_id' => (int) $s->Id_PA_Cycle,
            'kode_cycle' => (string) $s->Kode_Cycle,
            'nama_cycle' => (string) $s->Nama_Cycle,
            'tahun' => (int) $s->Tahun,
        ];
    }
}
```

- [ ] **Langkah 3: Tulis controller**

```php
<?php

namespace App\Http\Controllers\KPI\PerformanceAppraisal\Peserta;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\KPI\PerformanceAppraisal\PaKalibrasiPenilaiException;
use App\Services\KPI\PerformanceAppraisal\PaKalibrasiPenilaiService;
use App\Services\KPI\PerformanceAppraisal\PaPesertaService;
use App\Services\KPI\PerformanceAppraisal\PaWizardPenilaianService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Wizard tugas penilaian dan Kalibrasi Penilai — sisi penilai.
 *
 * Aturan yang sama dengan PaPesertaController: TIDAK ADA identitas peserta yang diterima.
 * `{kode}` hanya memilih kategori di antara tugas milik yang login, dan `assignment_id` pada
 * kalibrasi diperiksa kepemilikannya di service; milik orang lain dijawab 404.
 */
class PaWizardPenilaianController extends Controller
{
    public function __construct(
        private readonly PaWizardPenilaianService $wizard,
        private readonly PaKalibrasiPenilaiService $kalibrasi,
        private readonly PaPesertaService $peserta,
    ) {}

    public function wizard(Request $request)
    {
        return $this->jawab(fn () => $this->wizard->wizard((int) Auth::id(), $this->cycleId($request)));
    }

    public function lembar(Request $request, string $kode)
    {
        return $this->jawab(fn () => $this->wizard->lembar((int) Auth::id(), $kode, $this->cycleId($request)));
    }

    public function pratinjau(Request $request, string $kode)
    {
        return $this->jawab(fn () => $this->wizard->pratinjau((int) Auth::id(), $kode, $this->cycleId($request)));
    }

    /**
     * Menyimpan Kalibrasi Penilai atas satu orang, lalu mengembalikan pratinjau kategorinya
     * UTUH — bukan hanya baris orang itu — karena rata-rata per kompetensi ikut berubah.
     */
    public function kalibrasi(Request $request)
    {
        $data = $request->validate([
            'assignment_id' => ['required', 'integer', 'min:1'],
            'perubahan' => ['required', 'array', 'min:1', 'max:100'],
            'perubahan.*.competency_id' => ['required', 'integer', 'min:1', 'distinct'],
            'perubahan.*.skor' => ['required', 'numeric'],
            // Diperiksa service, bukan di sini, supaya penolakannya membawa PA_ALASAN_WAJIB.
            'alasan' => ['nullable', 'string'],
        ]);

        return $this->jawab(function () use ($data) {
            $userId = (int) Auth::id();
            $hasil = $this->kalibrasi->simpan(
                $this->peserta->karyawan($userId),
                (int) $data['assignment_id'],
                $data['perubahan'],
                $data['alasan'] ?? null,
                $userId,
            );

            return $hasil + ['pratinjau' => $this->wizard->pratinjau($userId, $hasil['kategori'], $hasil['cycle_id'])];
        });
    }

    private function cycleId(Request $request): ?int
    {
        $v = $request->query('cycle_id');

        return ($v === null || $v === '') ? null : (int) $v;
    }

    private function jawab(callable $kerja)
    {
        try {
            return ResponseHelper::success($kerja());
        } catch (PaKalibrasiPenilaiException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), $e->errorCode, $e->httpStatus);
        } catch (RuntimeException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }
    }
}
```

- [ ] **Langkah 4: Daftarkan rute**

Di `routes/KPI/PerformanceAppraisal/Peserta/PerformanceAppraisalPeserta.php`, tambahkan `use App\Http\Controllers\KPI\PerformanceAppraisal\Peserta\PaWizardPenilaianController;` dan, di dalam `Route::prefix('api/v1/performance-appraisal/saya')->group(...)` sesudah `/riwayat`:

```php
        /* WIZARD TUGAS PENILAIAN. `{kode}` hanya memilih kategori di antara tugas milik yang
           login; lingkupnya tetap dari Auth::id(). Lihat PaWizardPenilaianController. */
        Route::get('/wizard', [PaWizardPenilaianController::class, 'wizard']);
        Route::get('/wizard/langkah/{kode}/lembar', [PaWizardPenilaianController::class, 'lembar'])
            ->where('kode', '[A-Z_]{1,40}');
        Route::get('/wizard/langkah/{kode}/pratinjau', [PaWizardPenilaianController::class, 'pratinjau'])
            ->where('kode', '[A-Z_]{1,40}');
        Route::post('/kalibrasi', [PaWizardPenilaianController::class, 'kalibrasi']);
```

- [ ] **Langkah 5: Tulis empat fixture**

`saya-wizard.json`:

```json
{
    "success": true,
    "status": 200,
    "message": "Berhasil",
    "result": {
        "siklus": { "cycle_id": 7233, "kode_cycle": "PA-2026-11", "nama_cycle": "Penilaian Kinerja Triwulan III 2026", "tahun": 2026 },
        "langkah": [
            { "urutan": 1, "kode_kategori": "SELF", "nama_kategori": "Diri Sendiri", "boleh_kalibrasi": false, "jumlah_lembar": 1, "jumlah_terisi": 1, "jumlah_terkalibrasi": 0, "langkah_ke": 1, "status": "TUNTAS", "sebab_terkunci": null },
            { "urutan": 2, "kode_kategori": "ATASAN", "nama_kategori": "Atasan", "boleh_kalibrasi": true, "jumlah_lembar": 2, "jumlah_terisi": 1, "jumlah_terkalibrasi": 1, "langkah_ke": 2, "status": "AKTIF", "sebab_terkunci": null },
            { "urutan": 3, "kode_kategori": "PEER", "nama_kategori": "Rekan Sejawat", "boleh_kalibrasi": true, "jumlah_lembar": 2, "jumlah_terisi": 0, "jumlah_terkalibrasi": 0, "langkah_ke": 3, "status": "TERKUNCI", "sebab_terkunci": "Selesaikan penilaian Atasan lebih dulu." }
        ],
        "langkah_aktif": "ATASAN",
        "pengiriman": { "cycle_id": 7233, "kode_cycle": "PA-2026-11", "nama_cycle": "Penilaian Kinerja Triwulan III 2026", "total": 5, "terkumpul": 2, "terkirim": 0, "sisa": 3, "persen": 40, "bisa_kirim": false, "sudah_terkirim_semua": false },
        "boleh_kirim": false
    }
}
```

`saya-lembar.json`:

```json
{
    "success": true,
    "status": 200,
    "message": "Berhasil",
    "result": {
        "siklus": { "cycle_id": 7233, "kode_cycle": "PA-2026-11", "nama_cycle": "Penilaian Kinerja Triwulan III 2026", "tahun": 2026 },
        "langkah": { "urutan": 2, "kode_kategori": "ATASAN", "nama_kategori": "Atasan", "boleh_kalibrasi": true, "jumlah_lembar": 2, "jumlah_terisi": 1, "jumlah_terkalibrasi": 1, "langkah_ke": 2, "status": "AKTIF", "sebab_terkunci": null },
        "lembar": [
            { "assignment_id": 34740, "status_penugasan": "BELUM_MULAI", "peserta": { "kode_karyawan": "B6", "nama": "Andre", "jabatan": "Staff Operasional", "divisi": "Operasional" }, "diri_sendiri": false, "tenggat": "2026-10-14 00:00:00", "terlambat": false, "hari_terlambat": null, "sisa_hari": 26, "url_ujian": "https://hclearn.contoh/ujian/abc", "short_token": "K7Q2P", "akses_otp": "481920", "status_pintu": "BERHASIL" }
        ],
        "total": 2,
        "dipangkas": false
    }
}
```

`saya-pratinjau.json`:

```json
{
    "success": true,
    "status": 200,
    "message": "Berhasil",
    "result": {
        "siklus": { "cycle_id": 7233, "kode_cycle": "PA-2026-11", "nama_cycle": "Penilaian Kinerja Triwulan III 2026", "tahun": 2026 },
        "kategori": { "kode": "ATASAN", "nama": "Atasan", "boleh_kalibrasi": true, "status": "TUNTAS" },
        "kompetensi": [
            { "competency_id": 11, "kode": "CV01", "nama": "Integritas", "jenis_kode": "CORE_VALUE", "jenis_nama": "Core Value", "rata_rata": 78.5, "jumlah_dinilai": 2 }
        ],
        "orang": [
            {
                "assignment_id": 34740,
                "peserta": { "kode_karyawan": "B6", "nama": "Andre", "jabatan": "Staff Operasional", "divisi": "Operasional" },
                "diri_sendiri": false,
                "status_penugasan": "TERKUMPUL",
                "boleh_diubah": true,
                "kalibrasi_terakhir": { "alasan": "Diselaraskan dengan rekan setim", "pada": "2026-09-18 10:15:00" },
                "sel": [
                    { "competency_id": 11, "skor_asli": 90, "skor_efektif": 82, "skor_maksimal": 100, "terkalibrasi": true }
                ],
                "naratif": [
                    { "competency_id": 21, "nama": "Strength", "jawaban": "Konsisten menutup pekerjaan tepat waktu." }
                ]
            }
        ],
        "dipangkas": false
    }
}
```

`saya-kalibrasi.json`: amplop yang sama dengan `result` = `{ "kalibrasi_id": 12, "assignment_id": 34740, "cycle_id": 7233, "kategori": "ATASAN", "diubah": 1, "pratinjau": <isi result saya-pratinjau.json> }` — tulis lengkap, bukan rujukan.

- [ ] **Langkah 6: Tambahkan uji kontrak**

Di `KontrakResponsTest`: tambahkan `use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;` dan `use MenyiapkanLembarPenilaian;` di badan kelas, lalu:

```php
    /**
     * Keempat bentuk wizard dibandingkan atas data yang TERISI: langkah berisi, lembar
     * berisi, orang pertama terkalibrasi dan punya jawaban naratif. Pembanding bentuk
     * menganggap null berbeda dari objek dan daftar kosong berbeda dari daftar berisi.
     */
    public function test_wizard_saya_sebentuk_dengan_fixture(): void
    {
        $l = $this->lembarTerisi(['boleh_kalibrasi' => 'Y', 'naratif' => 'Teks uji']);
        $this->actingAs($this->penilaiUji());

        $this->samakanBentuk('saya-wizard.json',
            $this->getJson('/api/v1/performance-appraisal/saya/wizard?cycle_id='.$l['cycle_id'])->assertOk()->json());
    }

    public function test_lembar_langkah_sebentuk_dengan_fixture(): void
    {
        $l = $this->lembarTerisi(['boleh_kalibrasi' => 'Y']);
        $this->actingAs($this->penilaiUji());

        $this->samakanBentuk('saya-lembar.json',
            $this->getJson('/api/v1/performance-appraisal/saya/wizard/langkah/ATASAN/lembar?cycle_id='.$l['cycle_id'])
                ->assertOk()->json());
    }

    public function test_pratinjau_langkah_sebentuk_dengan_fixture(): void
    {
        $l = $this->lembarTerisi(['boleh_kalibrasi' => 'Y', 'naratif' => 'Teks uji']);
        $this->kalibrasiLangsung($l['assignment_id'], $l['kompetensi'][0], 70, 60);
        $this->actingAs($this->penilaiUji());

        $this->samakanBentuk('saya-pratinjau.json',
            $this->getJson('/api/v1/performance-appraisal/saya/wizard/langkah/ATASAN/pratinjau?cycle_id='.$l['cycle_id'])
                ->assertOk()->json());
    }

    public function test_kalibrasi_sebentuk_dengan_fixture(): void
    {
        $l = $this->lembarTerisi(['boleh_kalibrasi' => 'Y', 'naratif' => 'Teks uji']);
        $this->actingAs($this->penilaiUji());

        $this->samakanBentuk('saya-kalibrasi.json', $this->postJson('/api/v1/performance-appraisal/saya/kalibrasi', [
            'assignment_id' => $l['assignment_id'],
            'perubahan' => [['competency_id' => $l['kompetensi'][0], 'skor' => 60]],
            'alasan' => 'Uji kontrak',
        ])->assertOk()->json());
    }
```

- [ ] **Langkah 7: Perbarui kontrak API**

Di `docs/superpowers/specs/2026-09-01-kontrak-api-performance-appraisal.md`, tambahkan bagian baru "PA Saya — Wizard dan Kalibrasi Penilai (18 September 2026)" berisi empat endpoint (alamat, parameter, bentuk respons merujuk keempat fixture), tabel kode galat dari spec §6 **ditambah** `PA_KOMPETENSI_TIDAK_DI_LEMBAR` (422) dan `PA_LANGKAH_TIDAK_DIKENAL` (404), serta catatan bahwa `POST /kalibrasi` mengembalikan pratinjau kategori UTUH.

- [ ] **Langkah 8: Pemeriksaan statis dan commit**

```bash
php -l app/Services/KPI/PerformanceAppraisal/PaWizardPenilaianService.php && php -l app/Http/Controllers/KPI/PerformanceAppraisal/Peserta/PaWizardPenilaianController.php && php -l routes/KPI/PerformanceAppraisal/Peserta/PerformanceAppraisalPeserta.php
for f in resources/js/Pages/KPI/PerformanceAppraisal/api/fixtures/saya-wizard.json resources/js/Pages/KPI/PerformanceAppraisal/api/fixtures/saya-lembar.json resources/js/Pages/KPI/PerformanceAppraisal/api/fixtures/saya-pratinjau.json resources/js/Pages/KPI/PerformanceAppraisal/api/fixtures/saya-kalibrasi.json; do node -e "JSON.parse(require('fs').readFileSync('$f','utf8'))" && echo "ok $f"; done
unset DATABASE_URL && php artisan route:list --path=performance-appraisal/saya | grep -E "wizard|kalibrasi"
vendor/bin/pint app/Services/KPI/PerformanceAppraisal/PaWizardPenilaianService.php app/Http/Controllers/KPI/PerformanceAppraisal/Peserta/PaWizardPenilaianController.php routes/KPI/PerformanceAppraisal/Peserta/PerformanceAppraisalPeserta.php tests/Feature/KPI/PerformanceAppraisal/WizardPenilaianTest.php tests/Feature/KPI/PerformanceAppraisal/KontrakResponsTest.php
git add app/Services/KPI/PerformanceAppraisal/PaWizardPenilaianService.php app/Http/Controllers/KPI/PerformanceAppraisal/Peserta/PaWizardPenilaianController.php routes/KPI/PerformanceAppraisal/Peserta/PerformanceAppraisalPeserta.php resources/js/Pages/KPI/PerformanceAppraisal/api/fixtures/saya-wizard.json resources/js/Pages/KPI/PerformanceAppraisal/api/fixtures/saya-lembar.json resources/js/Pages/KPI/PerformanceAppraisal/api/fixtures/saya-pratinjau.json resources/js/Pages/KPI/PerformanceAppraisal/api/fixtures/saya-kalibrasi.json docs/superpowers/specs/2026-09-01-kontrak-api-performance-appraisal.md tests/Feature/KPI/PerformanceAppraisal/WizardPenilaianTest.php tests/Feature/KPI/PerformanceAppraisal/KontrakResponsTest.php
git commit -m "feat(pa): API wizard tugas penilaian dan Kalibrasi Penilai"
```

Harapan `route:list`: empat rute (`GET wizard`, `GET wizard/langkah/{kode}/lembar`, `GET wizard/langkah/{kode}/pratinjau`, `POST kalibrasi`).

---

### Tugas 8: Pengaturan urutan dan penanda kalibrasi di layar master

**Berkas:**
- Ubah: `app/Services/KPI/PerformanceAppraisal/PaJenisPenilaiMatriksService.php` (`matriks()`, dua method baru)
- Ubah: `app/Http/Controllers/KPI/PerformanceAppraisal/MasterData/PaMasterDataController.php` (dua endpoint)
- Ubah: `routes/KPI/PerformanceAppraisal/MasterData/PerformanceAppraisalMaster.php`
- Uji: `tests/Feature/KPI/PerformanceAppraisal/KalibrasiPenilaiAdminTest.php`

**Antarmuka:**
- Menghasilkan:
  - `matriks()['kategori'][]` kini memuat `urutan_pengisian: ?int` dan `boleh_kalibrasi: bool`, diurutkan menurut urutan.
  - `tataUrutanKategori(array $kodeBerurut, ?int $olehUserId): list<array{id:int, kode:string, nama:string, urutan_pengisian:int, boleh_kalibrasi:bool}>`
  - `setelBolehKalibrasi(int $kategoriId, bool $boleh, ?int $olehUserId): array{id:int, kode:string, boleh_kalibrasi:bool}`
  - HTTP: `PUT /api/v1/performance-appraisal/master/jenis-penilai/urutan-kategori` (`{urutan: string[]}`), `PUT .../master/jenis-penilai/kategori/{kategoriId}/kalibrasi` (`{boleh: bool}`).

- [ ] **Langkah 1: Tulis `KalibrasiPenilaiAdminTest`**

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KalibrasiPenilaiAdminTest extends TestCase
{
    use DatabaseTransactions;

    private const API = '/api/v1/performance-appraisal/master/jenis-penilai';

    private function admin(): User
    {
        $userId = DB::table('HRIS_Page_Access')
            ->where('Jenis_page', 'PaMasterKategoriPenilaiPage')
            ->where('Flag_Aktif', 'Y')
            ->whereNotNull('UserID_Web')
            ->value('UserID_Web');

        $this->assertNotNull($userId, 'Tidak ada user berakses PaMasterKategoriPenilaiPage.');

        return User::find($userId);
    }

    private function kodeAktif(): array
    {
        return DB::table('N_PA_Rater_Category')->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan_Pengisian')->orderBy('Id_PA_Rater_Category')->pluck('Kode_Kategori')->all();
    }

    public function test_tata_urutan_menukar_posisi_dalam_satu_pernyataan(): void
    {
        $this->actingAs($this->admin());
        $urutan = $this->kodeAktif();
        $dibalik = array_reverse($urutan);

        $this->putJson(self::API.'/urutan-kategori', ['urutan' => $dibalik])->assertOk();

        $this->assertSame($dibalik, $this->kodeAktif(), 'Urutan baru tidak tersimpan apa adanya.');
    }

    public function test_urutan_yang_tidak_lengkap_atau_berlebih_ditolak(): void
    {
        $this->actingAs($this->admin());
        $urutan = $this->kodeAktif();

        $this->putJson(self::API.'/urutan-kategori', ['urutan' => array_slice($urutan, 1)])->assertStatus(422);
        $this->putJson(self::API.'/urutan-kategori', ['urutan' => [...$urutan, 'TIDAK_ADA']])->assertStatus(422);
        $this->assertSame($urutan, $this->kodeAktif(), 'Penolakan tidak boleh mengubah apa pun.');
    }

    public function test_penanda_kalibrasi_bisa_dinyalakan_dan_matriks_melaporkannya(): void
    {
        $this->actingAs($this->admin());
        $id = (int) DB::table('N_PA_Rater_Category')->where('Kode_Kategori', 'PEER')->value('Id_PA_Rater_Category');

        $this->putJson(self::API."/kategori/{$id}/kalibrasi", ['boleh' => true])->assertOk();
        $this->assertSame('Y', DB::table('N_PA_Rater_Category')->where('Id_PA_Rater_Category', $id)->value('Flag_Boleh_Kalibrasi'));

        $kategori = collect($this->getJson(self::API.'/matriks')->assertOk()->json('result.kategori'))->keyBy('kode');
        $this->assertTrue($kategori['PEER']['boleh_kalibrasi']);
        $this->assertArrayHasKey('urutan_pengisian', $kategori['PEER']);
    }
}
```

- [ ] **Langkah 2: Kolom baru di `matriks()`**

Kueri kategori di awal `matriks()` menjadi:

```php
        $kategori = DB::table('N_PA_Rater_Category')
            ->where('Flag_Aktif', 'Y')
            // Kategori tanpa urutan jatuh ke belakang, lalu menurut id — sama dengan wizard.
            ->orderByRaw('CASE WHEN Urutan_Pengisian IS NULL THEN 1 ELSE 0 END')
            ->orderBy('Urutan_Pengisian')
            ->orderBy('Id_PA_Rater_Category')
            ->get(['Id_PA_Rater_Category as id', 'Kode_Kategori as kode', 'Nama_Kategori as nama',
                'Deskripsi as deskripsi', 'Urutan_Pengisian as urutan', 'Flag_Boleh_Kalibrasi as boleh'])
            ->map(fn ($k) => [
                'id' => (int) $k->id,
                'kode' => $k->kode,
                'nama' => $k->nama,
                'deskripsi' => $k->deskripsi,
                'urutan_pengisian' => $k->urutan === null ? null : (int) $k->urutan,
                'boleh_kalibrasi' => $k->boleh === 'Y',
            ])
            ->values()
            ->all();
```

Fixture `master-kategori-penilai.json` tidak memakai `matriks()` (ia milik rute generik), jadi tidak berubah. Bila `jenis-penilai.test.js` memakai objek kategori tiruan, tambahkan kedua kunci ke tiruannya.

- [ ] **Langkah 3: Dua method tulis di `PaJenisPenilaiMatriksService`**

```php
    /**
     * Menata urutan pengisian SELURUH kategori aktif sekaligus.
     *
     * Satu pernyataan UPDATE ... CASE, bukan satu UPDATE per kategori: indeks unik
     * UX_PA_Rater_Category_Urutan diperiksa per pernyataan, sehingga menukar dua posisi satu
     * per satu akan menabraknya di tengah jalan. Daftarnya wajib memuat setiap kategori aktif
     * tepat sekali — urutan sebagian akan meninggalkan dua kategori berebut satu langkah.
     *
     * @param  list<string>  $kodeBerurut
     * @return list<array<string, mixed>>
     */
    public function tataUrutanKategori(array $kodeBerurut, ?int $olehUserId = null): array
    {
        $aktif = DB::table('N_PA_Rater_Category')->where('Flag_Aktif', 'Y')->pluck('Kode_Kategori')->all();
        $diminta = array_values(array_map(fn ($k) => strtoupper(trim((string) $k)), $kodeBerurut));

        $kurang = array_values(array_diff($aktif, $diminta));
        $lebih = array_values(array_diff($diminta, $aktif));

        if ($kurang !== [] || $lebih !== [] || count($diminta) !== count(array_unique($diminta))) {
            throw new RuntimeException(
                'Urutan harus memuat setiap kategori penilai aktif tepat sekali.'
                .($kurang === [] ? '' : ' Belum ada: '.implode(', ', $kurang).'.')
                .($lebih === [] ? '' : ' Tidak dikenal: '.implode(', ', $lebih).'.')
            );
        }

        $kasus = implode(' ', array_fill(0, count($diminta), 'WHEN ? THEN ?'));
        $binding = [];

        foreach ($diminta as $i => $kode) {
            $binding[] = $kode;
            $binding[] = $i + 1;
        }

        DB::update(
            "UPDATE N_PA_Rater_Category
             SET Urutan_Pengisian = CASE Kode_Kategori {$kasus} END,
                 Updated_At = sysdatetime(),
                 Updated_By = ?
             WHERE Flag_Aktif = 'Y'",
            [...$binding, $olehUserId],
        );

        return array_values(array_map(
            fn ($k) => array_intersect_key($k, array_flip(['id', 'kode', 'nama', 'urutan_pengisian', 'boleh_kalibrasi'])),
            $this->matriks()['kategori'],
        ));
    }

    /**
     * Menyalakan atau mematikan Kalibrasi Penilai untuk satu kategori.
     *
     * Berlaku SEKETIKA, termasuk pada siklus yang sedang berjalan: penanda ini kamus hidup
     * atas keputusan pemilik proyek 18 September 2026 (spec W5), menyimpang dari K26.
     *
     * @return array{id:int, kode:string, boleh_kalibrasi:bool}
     */
    public function setelBolehKalibrasi(int $kategoriId, bool $boleh, ?int $olehUserId = null): array
    {
        $kategori = DB::table('N_PA_Rater_Category')
            ->where('Id_PA_Rater_Category', $kategoriId)
            ->where('Flag_Aktif', 'Y')
            ->first(['Id_PA_Rater_Category', 'Kode_Kategori']);

        if ($kategori === null) {
            throw new RuntimeException("Kategori penilai {$kategoriId} tidak ada atau sudah nonaktif.");
        }

        DB::table('N_PA_Rater_Category')->where('Id_PA_Rater_Category', $kategoriId)->update([
            'Flag_Boleh_Kalibrasi' => $boleh ? 'Y' : 'T',
            'Updated_At' => now(),
            'Updated_By' => $olehUserId,
        ]);

        return ['id' => $kategoriId, 'kode' => (string) $kategori->Kode_Kategori, 'boleh_kalibrasi' => $boleh];
    }
```

Pastikan `use RuntimeException;` ada di kepala berkas.

- [ ] **Langkah 4: Endpoint dan rute admin**

Di `PaMasterDataController`, di bawah `matriksJenisPenilai()`:

```php
    /**
     * Menata urutan pengisian kategori penilai. Hak per master DIPERIKSA: urutan ini menata
     * wizard setiap penilai, bukan sekadar tampilan satu layar admin.
     */
    public function tataUrutanKategoriPenilai(Request $request)
    {
        $this->pastikanBolehMaster($request, 'kategori-penilai');

        $data = $request->validate([
            'urutan' => ['required', 'array', 'min:1'],
            'urutan.*' => ['required', 'string', 'max:40', 'distinct'],
        ]);

        return $this->jalankanTulis(fn () => $this->jenisPenilai->tataUrutanKategori(
            array_values($data['urutan']),
            $this->userId(),
        ));
    }

    /** Menyalakan atau mematikan Kalibrasi Penilai untuk satu kategori. */
    public function setelBolehKalibrasi(Request $request, int $kategoriId)
    {
        $this->pastikanBolehMaster($request, 'kategori-penilai');

        $data = $request->validate(['boleh' => ['required', 'boolean']]);

        return $this->jalankanTulis(fn () => $this->jenisPenilai->setelBolehKalibrasi(
            $kategoriId,
            (bool) $data['boleh'],
            $this->userId(),
        ));
    }
```

Di `PerformanceAppraisalMaster.php`, di dalam prefiks `api/v1/performance-appraisal/master`, tepat di bawah `Route::get('/jenis-penilai/matriks', ...)` dan **di atas** rute berpola `{levelId}`:

```php
            Route::put('/jenis-penilai/urutan-kategori', [PaMasterDataController::class, 'tataUrutanKategoriPenilai']);
            Route::put('/jenis-penilai/kategori/{kategoriId}/kalibrasi', [PaMasterDataController::class, 'setelBolehKalibrasi'])
                ->whereNumber('kategoriId');
```

- [ ] **Langkah 5: Pemeriksaan statis dan commit**

```bash
php -l app/Services/KPI/PerformanceAppraisal/PaJenisPenilaiMatriksService.php && php -l app/Http/Controllers/KPI/PerformanceAppraisal/MasterData/PaMasterDataController.php
unset DATABASE_URL && php artisan route:list --path=performance-appraisal/master/jenis-penilai
vendor/bin/pint app/Services/KPI/PerformanceAppraisal/PaJenisPenilaiMatriksService.php app/Http/Controllers/KPI/PerformanceAppraisal/MasterData/PaMasterDataController.php routes/KPI/PerformanceAppraisal/MasterData/PerformanceAppraisalMaster.php tests/Feature/KPI/PerformanceAppraisal/KalibrasiPenilaiAdminTest.php
git add app/Services/KPI/PerformanceAppraisal/PaJenisPenilaiMatriksService.php app/Http/Controllers/KPI/PerformanceAppraisal/MasterData/PaMasterDataController.php routes/KPI/PerformanceAppraisal/MasterData/PerformanceAppraisalMaster.php tests/Feature/KPI/PerformanceAppraisal/KalibrasiPenilaiAdminTest.php
git commit -m "feat(pa): admin menata urutan pengisian dan penanda Kalibrasi Penilai"
```

---

### Tugas 9: Riwayat Kalibrasi Penilai di detail hasil admin

**Berkas:**
- Ubah: `app/Services/KPI/PerformanceAppraisal/PaParticipantReadService.php` (`detailHasil()`)
- Ubah: `resources/js/Pages/KPI/PerformanceAppraisal/api/fixtures/hasil-peserta.json`
- Uji: `KontrakResponsTest::test_detail_hasil_sebentuk_dengan_fixture` (disesuaikan)

**Antarmuka:**
- Mengonsumsi: `PaKalibrasiPenilaiService::riwayatPeserta()`.
- Menghasilkan: kunci `kalibrasi_penilai` di respons detail hasil admin, berbentuk keluaran `riwayatPeserta()`.

- [ ] **Langkah 1: Tambahkan kunci ke `detailHasil()`**

Suntikkan `private readonly PaKalibrasiPenilaiService $kalibrasiPenilai` lewat konstruktor `PaParticipantReadService`, lalu di array kembalian `detailHasil()`, di sebelah `riwayat_kalibrasi`:

```php
            /* Kalibrasi PENILAI (penilai merevisi skornya sebelum mengirim), dipisah dari
               `riwayat_kalibrasi` yang milik forum. Dua mekanisme, dua kunci: menyatukannya
               membuat pembaca audit tidak bisa lagi membedakan siapa yang mengubah apa. */
            'kalibrasi_penilai' => $this->kalibrasiPenilai->riwayatPeserta($participantId),
```

- [ ] **Langkah 2: Perbarui fixture dan uji kontraknya**

Tambahkan ke `result` di `hasil-peserta.json`:

```json
"kalibrasi_penilai": [
    {
        "kalibrasi_id": 12,
        "assignment_id": 34740,
        "penilai": { "kode_karyawan": "A4", "nama": "Aji" },
        "kategori": { "kode": "ATASAN", "nama": "Atasan" },
        "alasan": "Diselaraskan dengan rekan setim",
        "pada": "2026-09-18 10:15:00",
        "oleh": 634,
        "perubahan": [
            { "competency_id": 11, "kode": "CV01", "nama": "Integritas", "skor_sebelum": 90, "skor_sesudah": 82, "skor_maksimal": 100 }
        ]
    }
]
```

Karena pembanding bentuk membedakan daftar kosong dari berisi, `test_detail_hasil_sebentuk_dengan_fixture` harus membuat satu kalibrasi untuk peserta yang diperiksanya: baca uji itu, temukan id peserta yang ia pakai, tambahkan satu penugasan + payload + skor + baris kalibrasi untuk peserta tersebut dengan pola `MenyiapkanLembarPenilaian::kalibrasiLangsung()` sebelum permintaan dikirim. Bila `oleh` bisa NULL pada data uji, isi `Created_By` supaya bentuknya tetap angka.

- [ ] **Langkah 3: Pemeriksaan statis dan commit**

```bash
php -l app/Services/KPI/PerformanceAppraisal/PaParticipantReadService.php
node -e "JSON.parse(require('fs').readFileSync('resources/js/Pages/KPI/PerformanceAppraisal/api/fixtures/hasil-peserta.json','utf8'))"
vendor/bin/pint app/Services/KPI/PerformanceAppraisal/PaParticipantReadService.php tests/Feature/KPI/PerformanceAppraisal/KontrakResponsTest.php
git add app/Services/KPI/PerformanceAppraisal/PaParticipantReadService.php resources/js/Pages/KPI/PerformanceAppraisal/api/fixtures/hasil-peserta.json tests/Feature/KPI/PerformanceAppraisal/KontrakResponsTest.php
git commit -m "feat(pa): riwayat Kalibrasi Penilai di detail hasil admin"
```

---

### Tugas 10: Seam API frontend

**Berkas:**
- Ubah: `resources/js/Pages/KPI/PerformanceAppraisal/api/paApi.js`
- Uji: `resources/js/Pages/KPI/PerformanceAppraisal/api/paApi.galat.test.js`

**Antarmuka:**
- Menghasilkan: `PaApiError` kini punya `error_code` dan `request_id` (string, kosong bila server tidak mengirim); fungsi `wizardPenilaianSaya(cycleId)`, `lembarLangkahSaya(kode, cycleId)`, `pratinjauLangkahSaya(kode, cycleId)`, `simpanKalibrasiPenilai({ assignmentId, perubahan, alasan })`, `tataUrutanKategoriPenilai(urutan)`, `setelBolehKalibrasiKategori(kategoriId, boleh)` — semuanya juga masuk objek ekspor bawaan.

- [ ] **Langkah 1: Tulis uji**

```js
import axios from 'axios';
import { describe, expect, it, vi } from 'vitest';

import paApi from './paApi.js';

vi.mock('axios', () => ({ default: vi.fn() }));

describe('panggil() meneruskan kode galat server', () => {
    it('membawa error_code dan request_id', async () => {
        axios.mockRejectedValueOnce({
            response: { status: 409, data: { message: 'Lembar sudah terkirim', error_code: 'PA_LEMBAR_BEKU', request_id: 'req-1' } },
        });

        await expect(paApi.simpanKalibrasiPenilai({ assignmentId: 1, perubahan: [], alasan: 'x' }))
            .rejects.toMatchObject({ status: 409, error_code: 'PA_LEMBAR_BEKU', request_id: 'req-1', message: 'Lembar sudah terkirim' });
    });

    it('kode kosong bila server tidak mengirimnya', async () => {
        axios.mockRejectedValueOnce({ response: { status: 422, data: { message: 'Ditolak' } } });

        await expect(paApi.wizardPenilaianSaya()).rejects.toMatchObject({ error_code: '', request_id: '' });
    });

    it('alamat wizard membawa cycle_id hanya bila diberikan', async () => {
        axios.mockResolvedValueOnce({ data: { result: { langkah: [] } } });
        await paApi.wizardPenilaianSaya(7);
        expect(axios).toHaveBeenLastCalledWith(expect.objectContaining({ url: '/api/v1/performance-appraisal/saya/wizard?cycle_id=7' }));
    });
});
```

- [ ] **Langkah 2: Perluas `PaApiError` dan `panggil()`**

```js
export class PaApiError extends Error {
    constructor(message, status, errorCode = '', requestId = '') {
        super(message);
        this.name = 'PaApiError';
        this.status = status;
        /* Nama snake_case disengaja: usePaSaya dan komposabel lain sudah membaca
           `error.error_code` / `error.request_id`. Sebelum ini keduanya TIDAK PERNAH diisi,
           sehingga kode galat PA tidak pernah sampai ke layar mana pun. */
        this.error_code = errorCode;
        this.request_id = requestId;
    }
}
```

Di `panggil()`, baris `throw new PaApiError(pesan, status);` menjadi:

```js
        throw new PaApiError(
            pesan,
            status,
            e?.response?.data?.error_code || '',
            e?.response?.data?.request_id || '',
        );
```

- [ ] **Langkah 3: Fungsi baru**

Tambahkan di bawah `kirimSemuaPenilaianSaya`:

```js
/** Kerangka wizard tugas penilaian saya. `langkah_aktif` ditentukan server. */
export function wizardPenilaianSaya(cycleId = null) {
    return panggil('get', `/saya/wizard${cycleId ? `?cycle_id=${cycleId}` : ''}`);
}

/** Lembar satu langkah. Langkah terkunci datang tanpa tautan maupun kode akses. */
export function lembarLangkahSaya(kode, cycleId = null) {
    return panggil('get', `/saya/wizard/langkah/${encodeURIComponent(kode)}/lembar${cycleId ? `?cycle_id=${cycleId}` : ''}`);
}

/** Pratinjau Kalibrasi Penilai satu langkah: matriks orang × kompetensi. */
export function pratinjauLangkahSaya(kode, cycleId = null) {
    return panggil('get', `/saya/wizard/langkah/${encodeURIComponent(kode)}/pratinjau${cycleId ? `?cycle_id=${cycleId}` : ''}`);
}

/** Menyimpan Kalibrasi Penilai atas satu orang; mengembalikan pratinjau kategorinya utuh. */
export function simpanKalibrasiPenilai({ assignmentId, perubahan, alasan }) {
    return panggil('post', '/saya/kalibrasi', { assignment_id: assignmentId, perubahan, alasan });
}

/** Admin: menata urutan pengisian seluruh kategori aktif sekaligus. */
export function tataUrutanKategoriPenilai(urutan) {
    return panggil('put', '/master/jenis-penilai/urutan-kategori', { urutan });
}

/** Admin: menyalakan/mematikan Kalibrasi Penilai untuk satu kategori. */
export function setelBolehKalibrasiKategori(kategoriId, boleh) {
    return panggil('put', `/master/jenis-penilai/kategori/${kategoriId}/kalibrasi`, { boleh });
}
```

Tambahkan keenam nama itu ke objek `export default { ... }` di akhir berkas, di dekat `kirimSemuaPenilaianSaya`.

- [ ] **Langkah 4: Commit**

```bash
git add resources/js/Pages/KPI/PerformanceAppraisal/api/paApi.js resources/js/Pages/KPI/PerformanceAppraisal/api/paApi.galat.test.js
git commit -m "feat(pa): seam API wizard dan kode galat yang benar-benar sampai ke layar"
```

---

### Tugas 11: Komposabel `usePaWizardPenilaian`

**Berkas:**
- Buat: `resources/js/Pages/KPI/PerformanceAppraisal/Peserta/composables/usePaWizardPenilaian.js`
- Uji: `resources/js/Pages/KPI/PerformanceAppraisal/Peserta/composables/usePaWizardPenilaian.test.js`

**Antarmuka:**
- Mengonsumsi: enam fungsi `paApi` dari Tugas 10 dan `kirimSemuaPenilaianSaya`.
- Menghasilkan: `usePaWizardPenilaian({ cycleId = null, muatSaatPasang = true } = {})` mengembalikan `{ wizard, siklus, langkah, langkahDilihat, langkahBerikut, kodeDilihat, tampilan, kosong, memuat, galat, lembar, pratinjau, memuatIsi, galatIsi, menyimpanId, galatSimpan, kotor, mengirim, galatKirim, muat, muatIsi, buka, bukaRingkasan, lanjut, simpanKalibrasi, kirim }`. `tampilan` ∈ `'lembar' | 'pratinjau' | 'ringkasan'`. `buka`, `bukaRingkasan`, `lanjut` mengembalikan `false` bila ditolak karena `kotor`, kecuali dipanggil dengan `{ paksa: true }`. Konstanta `LANGKAH_RINGKASAN = 'RINGKASAN'`.

- [ ] **Langkah 1: Tulis uji**

```js
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';

import paApi from '../../api/paApi.js';
import { usePaWizardPenilaian } from './usePaWizardPenilaian.js';

vi.mock('../../api/paApi.js', () => ({
    default: {
        wizardPenilaianSaya: vi.fn(),
        lembarLangkahSaya: vi.fn(),
        pratinjauLangkahSaya: vi.fn(),
        simpanKalibrasiPenilai: vi.fn(),
        kirimSemuaPenilaianSaya: vi.fn(),
    },
}));

const langkah = (status, kode = 'ATASAN') => ({ kode_kategori: kode, nama_kategori: kode, status, jumlah_lembar: 1, jumlah_terisi: status === 'AKTIF' ? 0 : 1 });

function pasang() {
    let api;
    mount(defineComponent({ setup() { api = usePaWizardPenilaian(); return () => h('div'); } }));
    return api;
}

describe('usePaWizardPenilaian', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        paApi.lembarLangkahSaya.mockResolvedValue({ lembar: [] });
        paApi.pratinjauLangkahSaya.mockResolvedValue({ orang: [], kompetensi: [] });
    });

    it('membuka langkah yang ditentukan server', async () => {
        paApi.wizardPenilaianSaya.mockResolvedValue({ siklus: { cycle_id: 7 }, langkah: [langkah('AKTIF')], langkah_aktif: 'ATASAN' });
        const w = pasang();
        await flushPromises();

        expect(w.kodeDilihat.value).toBe('ATASAN');
        expect(w.tampilan.value).toBe('lembar');
        expect(paApi.lembarLangkahSaya).toHaveBeenCalledWith('ATASAN', 7);
    });

    it('berpindah ke pratinjau ketika langkah yang dilihat baru saja tuntas', async () => {
        paApi.wizardPenilaianSaya.mockResolvedValueOnce({ siklus: { cycle_id: 7 }, langkah: [langkah('AKTIF'), langkah('TERKUNCI', 'PEER')], langkah_aktif: 'ATASAN' });
        const w = pasang();
        await flushPromises();

        paApi.wizardPenilaianSaya.mockResolvedValueOnce({ siklus: { cycle_id: 7 }, langkah: [langkah('TUNTAS'), langkah('AKTIF', 'PEER')], langkah_aktif: 'PEER' });
        await w.muat({ pertahankanPosisi: true });

        expect(w.kodeDilihat.value).toBe('ATASAN');
        expect(w.tampilan.value).toBe('pratinjau');
        expect(w.langkahBerikut.value.kode_kategori).toBe('PEER');
    });

    it('langsung ke ringkasan bila semua langkah tuntas', async () => {
        paApi.wizardPenilaianSaya.mockResolvedValue({ siklus: { cycle_id: 7 }, langkah: [langkah('TUNTAS')], langkah_aktif: 'RINGKASAN' });
        const w = pasang();
        await flushPromises();

        expect(w.tampilan.value).toBe('ringkasan');
        expect(paApi.lembarLangkahSaya).not.toHaveBeenCalled();
    });

    it('menolak berpindah langkah selagi ada perubahan belum tersimpan, kecuali dipaksa', async () => {
        paApi.wizardPenilaianSaya.mockResolvedValue({ siklus: { cycle_id: 7 }, langkah: [langkah('TUNTAS'), langkah('TUNTAS', 'PEER')], langkah_aktif: 'RINGKASAN' });
        const w = pasang();
        await flushPromises();
        w.kotor.value = true;

        expect(w.buka('PEER')).toBe(false);
        expect(w.buka('PEER', null, { paksa: true })).toBe(true);
        expect(w.kodeDilihat.value).toBe('PEER');
        expect(w.kotor.value).toBe(false);
    });

    it('menandai bersih SEBELUM simpan selesai dilaporkan, dan memakai pratinjau dari server', async () => {
        paApi.wizardPenilaianSaya.mockResolvedValue({ siklus: { cycle_id: 7 }, langkah: [langkah('TUNTAS')], langkah_aktif: 'RINGKASAN' });
        paApi.simpanKalibrasiPenilai.mockResolvedValue({ pratinjau: { orang: [{ assignment_id: 1 }], kompetensi: [] } });
        const w = pasang();
        await flushPromises();
        w.kotor.value = true;

        const ok = await w.simpanKalibrasi({ assignmentId: 1, perubahan: [], alasan: 'x' });

        expect(ok).toBe(true);
        expect(w.kotor.value).toBe(false);
        expect(w.pratinjau.value.orang[0].assignment_id).toBe(1);
    });

    it('galat membawa kode dan nomor permintaan', async () => {
        paApi.wizardPenilaianSaya.mockRejectedValue(Object.assign(new Error('Ditolak'), { status: 404, error_code: 'PA_LANGKAH_TIDAK_DIKENAL', request_id: 'r-9' }));
        const w = pasang();
        await flushPromises();

        expect(w.galat.value).toEqual({ pesan: 'Ditolak', status: 404, kode: 'PA_LANGKAH_TIDAK_DIKENAL', requestId: 'r-9' });
    });
});
```

- [ ] **Langkah 2: Tulis komposabel**

```js
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import paApi from '../../api/paApi.js';

export const LANGKAH_RINGKASAN = 'RINGKASAN';

/**
 * Keadaan wizard tugas penilaian.
 *
 * LANGKAH AKTIF DATANG DARI SERVER (`langkah_aktif`), tidak ditebak dari kelengkapan isi.
 * Layar hanya memegang dua hal: langkah mana yang sedang DILIHAT, dan apakah yang tampil
 * lembarnya atau pratinjau Kalibrasi Penilai-nya.
 *
 * TRANSISI: bila langkah yang sedang dilihat berubah menjadi TUNTAS setelah dimuat ulang —
 * penilai baru menyelesaikan lembar terakhirnya di HC Learn — tampilan berpindah ke pratinjau
 * langkah itu. Itulah titik Kalibrasi Penilai di antara dua kategori.
 *
 * DIMUAT ULANG SAAT TAB AKTIF KEMBALI: penilai mengisi di tab HC Learn dan lembarnya kembali
 * lewat callback. Tanpa ini, ia kembali ke HCIS dan melihat langkahnya seolah belum tuntas.
 */
export function usePaWizardPenilaian({ cycleId = null, muatSaatPasang = true } = {}) {
    const wizard = ref(null);
    const memuat = ref(false);
    const galat = ref(null);

    const kodeDilihat = ref(null);
    const tampilan = ref('lembar');

    const lembar = ref(null);
    const pratinjau = ref(null);
    const memuatIsi = ref(false);
    const galatIsi = ref(null);

    const menyimpanId = ref(0);
    const galatSimpan = ref(null);
    const kotor = ref(false);
    const mengirim = ref(false);
    const galatKirim = ref(null);

    const siklus = computed(() => wizard.value?.siklus ?? null);
    const langkah = computed(() => wizard.value?.langkah ?? []);
    const kosong = computed(() => wizard.value !== null && langkah.value.length === 0);
    const indeksDilihat = computed(() => langkah.value.findIndex((l) => l.kode_kategori === kodeDilihat.value));
    const langkahDilihat = computed(() => (indeksDilihat.value < 0 ? null : langkah.value[indeksDilihat.value]));
    const langkahBerikut = computed(() => {
        const berikut = indeksDilihat.value < 0 ? null : langkah.value[indeksDilihat.value + 1];
        return berikut && berikut.status !== 'TERKUNCI' ? berikut : null;
    });

    function metaGalat(e) {
        return {
            pesan: e?.message || 'Permintaan tidak bisa diselesaikan.',
            status: Number(e?.status || 0),
            kode: e?.error_code || '',
            requestId: e?.request_id || '',
        };
    }

    function arahkanKeServer() {
        const aktif = wizard.value?.langkah_aktif ?? null;

        if (aktif === LANGKAH_RINGKASAN) {
            kodeDilihat.value = null;
            tampilan.value = 'ringkasan';
            return;
        }

        kodeDilihat.value = aktif;
        tampilan.value = 'lembar';
    }

    async function muatIsi() {
        if (tampilan.value === 'ringkasan' || !kodeDilihat.value) {
            lembar.value = null;
            pratinjau.value = null;
            return;
        }

        memuatIsi.value = true;
        galatIsi.value = null;
        const id = siklus.value?.cycle_id ?? cycleId;

        try {
            if (tampilan.value === 'pratinjau') {
                pratinjau.value = await paApi.pratinjauLangkahSaya(kodeDilihat.value, id);
            } else {
                lembar.value = await paApi.lembarLangkahSaya(kodeDilihat.value, id);
            }
        } catch (e) {
            galatIsi.value = metaGalat(e);
        } finally {
            memuatIsi.value = false;
        }
    }

    async function muat({ pertahankanPosisi = false } = {}) {
        memuat.value = true;
        galat.value = null;

        try {
            const statusSebelum = langkahDilihat.value?.status ?? null;
            wizard.value = await paApi.wizardPenilaianSaya(cycleId);

            const posisiHilang = tampilan.value !== 'ringkasan' && !langkahDilihat.value;

            if (!pertahankanPosisi || posisiHilang) {
                arahkanKeServer();
            } else if (tampilan.value === 'lembar' && statusSebelum !== 'TUNTAS' && langkahDilihat.value?.status === 'TUNTAS') {
                tampilan.value = 'pratinjau';
            }

            await muatIsi();
        } catch (e) {
            galat.value = metaGalat(e);
        } finally {
            memuat.value = false;
        }
    }

    function buka(kode, jenis = null, { paksa = false } = {}) {
        if (kotor.value && !paksa) return false;

        const l = langkah.value.find((x) => x.kode_kategori === kode);
        if (!l || l.status === 'TERKUNCI') return true;

        kotor.value = false;
        kodeDilihat.value = kode;
        tampilan.value = jenis ?? (l.status === 'TUNTAS' ? 'pratinjau' : 'lembar');
        muatIsi();

        return true;
    }

    function bukaRingkasan({ paksa = false } = {}) {
        if (kotor.value && !paksa) return false;
        if (wizard.value?.langkah_aktif !== LANGKAH_RINGKASAN) return true;

        kotor.value = false;
        kodeDilihat.value = null;
        tampilan.value = 'ringkasan';
        lembar.value = null;
        pratinjau.value = null;

        return true;
    }

    function lanjut({ paksa = false } = {}) {
        if (kotor.value && !paksa) return false;
        if (langkahBerikut.value) return buka(langkahBerikut.value.kode_kategori, null, { paksa: true });

        return bukaRingkasan({ paksa: true });
    }

    async function simpanKalibrasi({ assignmentId, perubahan, alasan }) {
        menyimpanId.value = assignmentId;
        galatSimpan.value = null;

        try {
            const r = await paApi.simpanKalibrasiPenilai({ assignmentId, perubahan, alasan });

            // Ditandai bersih SEBELUM pemanggil menampilkan pesan sukses.
            kotor.value = false;
            pratinjau.value = r?.pratinjau ?? pratinjau.value;

            try {
                wizard.value = await paApi.wizardPenilaianSaya(cycleId);
            } catch {
                // Hitungan langkah boleh basi sebentar; kalibrasinya sudah tersimpan.
            }

            return true;
        } catch (e) {
            galatSimpan.value = metaGalat(e);
            return false;
        } finally {
            menyimpanId.value = 0;
        }
    }

    async function kirim() {
        if (!siklus.value || mengirim.value) return false;

        mengirim.value = true;
        galatKirim.value = null;

        try {
            await paApi.kirimSemuaPenilaianSaya(siklus.value.cycle_id);
            await muat();
            return true;
        } catch (e) {
            galatKirim.value = metaGalat(e);
            return false;
        } finally {
            mengirim.value = false;
        }
    }

    function saatTerlihat() {
        if (document.visibilityState === 'visible' && !memuat.value) muat({ pertahankanPosisi: true });
    }

    onMounted(() => {
        document.addEventListener('visibilitychange', saatTerlihat);
        if (muatSaatPasang) muat();
    });

    onBeforeUnmount(() => document.removeEventListener('visibilitychange', saatTerlihat));

    return {
        wizard, siklus, langkah, langkahDilihat, langkahBerikut, kodeDilihat, tampilan, kosong,
        memuat, galat, lembar, pratinjau, memuatIsi, galatIsi, menyimpanId, galatSimpan, kotor,
        mengirim, galatKirim, muat, muatIsi, buka, bukaRingkasan, lanjut, simpanKalibrasi, kirim,
    };
}
```

- [ ] **Langkah 3: Commit**

```bash
git add resources/js/Pages/KPI/PerformanceAppraisal/Peserta/composables/usePaWizardPenilaian.js resources/js/Pages/KPI/PerformanceAppraisal/Peserta/composables/usePaWizardPenilaian.test.js
git commit -m "feat(pa): komposabel wizard tugas penilaian"
```

---

### Tugas 12: Komponen wizard dan pemasangannya di PA Saya

**Berkas:**
- Buat: `resources/js/Pages/KPI/PerformanceAppraisal/utils/statusLembar.js`
- Buat: `resources/js/Pages/KPI/PerformanceAppraisal/Peserta/components/PaWizardStepper.vue`
- Buat: `resources/js/Pages/KPI/PerformanceAppraisal/Peserta/components/PaWizardLembarList.vue`
- Buat: `resources/js/Pages/KPI/PerformanceAppraisal/Peserta/components/PaWizardPratinjauKalibrasi.vue`
- Buat: `resources/js/Pages/KPI/PerformanceAppraisal/Peserta/components/PaWizardRingkasanKirim.vue`
- Buat: `resources/js/Pages/KPI/PerformanceAppraisal/Peserta/components/PaSayaWizardPenilaian.vue`
- Ubah: `resources/js/Pages/KPI/PerformanceAppraisal/Peserta/saya.vue`
- Uji: `resources/js/Pages/KPI/PerformanceAppraisal/Peserta/components/wizard.test.js`

**Antarmuka:**
- Mengonsumsi: `usePaWizardPenilaian()` (Tugas 11); `HcisSteps`, `HcisButton`, `HcisBadge`, `HcisStatusBadge`, `HcisAlert`, `HcisDialog`, `HcisInputNumber`, `HcisTextarea`, `HcisSkeleton`, `HcisResultState`, `HcisEmptyState`, `hcisMessage` dari `@/components/ui`.
- Menghasilkan: `<PaSayaWizardPenilaian />` tanpa prop, memancarkan `terkirim` sesudah kirim-semua berhasil.

- [ ] **Langkah 1: Tulis uji komponen**

```js
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import PaWizardLembarList from './PaWizardLembarList.vue';
import PaWizardPratinjauKalibrasi from './PaWizardPratinjauKalibrasi.vue';
import PaWizardRingkasanKirim from './PaWizardRingkasanKirim.vue';
import PaWizardStepper from './PaWizardStepper.vue';

const kompetensi = [
    { competency_id: 11, kode: 'CV01', nama: 'Integritas', jenis_kode: 'CORE_VALUE', jenis_nama: 'Core Value', rata_rata: 80, jumlah_dinilai: 2 },
    { competency_id: 12, kode: 'CV02', nama: 'Kolaborasi', jenis_kode: 'CORE_VALUE', jenis_nama: 'Core Value', rata_rata: 70, jumlah_dinilai: 1 },
];

const orang = (id, boleh = true, sel = [{ competency_id: 11, skor_asli: 90, skor_efektif: 90, skor_maksimal: 100, terkalibrasi: false }]) => ({
    assignment_id: id,
    peserta: { kode_karyawan: `K${id}`, nama: `Orang ${id}`, jabatan: 'Staff', divisi: 'Ops' },
    diri_sendiri: false,
    status_penugasan: 'TERKUMPUL',
    boleh_diubah: boleh,
    kalibrasi_terakhir: null,
    sel,
    naratif: [],
});

const pratinjau = (o, boleh = true) => ({
    siklus: { cycle_id: 7 },
    kategori: { kode: 'ATASAN', nama: 'Atasan', boleh_kalibrasi: boleh, status: 'TUNTAS' },
    kompetensi,
    orang: o,
    dipangkas: false,
});

describe('PaWizardStepper', () => {
    it('langkah terkunci tidak bisa dipilih dan menyebut sebabnya', async () => {
        const w = mount(PaWizardStepper, {
            props: {
                langkah: [
                    { kode_kategori: 'SELF', nama_kategori: 'Diri Sendiri', status: 'AKTIF', jumlah_lembar: 1, jumlah_terisi: 0, sebab_terkunci: null },
                    { kode_kategori: 'ATASAN', nama_kategori: 'Atasan', status: 'TERKUNCI', jumlah_lembar: 2, jumlah_terisi: 0, sebab_terkunci: 'Selesaikan penilaian Diri Sendiri lebih dulu.' },
                ],
                kodeDilihat: 'SELF',
            },
        });

        expect(w.text()).toContain('Selesaikan penilaian Diri Sendiri lebih dulu.');
        w.vm.pilih({ index: 1 });
        expect(w.emitted('pilih')).toBeUndefined();
        w.vm.pilih({ index: 0 });
        expect(w.emitted('pilih')[0]).toEqual(['SELF']);
    });
});

describe('PaWizardLembarList', () => {
    it('tanpa tautan, tombol Mulai Menilai tidak muncul', () => {
        const w = mount(PaWizardLembarList, {
            props: {
                langkah: { kode_kategori: 'ATASAN', nama_kategori: 'Atasan', status: 'TERKUNCI', sebab_terkunci: 'Selesaikan penilaian Diri Sendiri lebih dulu.' },
                lembar: [{ assignment_id: 1, status_penugasan: 'BELUM_MULAI', peserta: { nama: 'Andre', jabatan: 'Staff', divisi: 'Ops' }, diri_sendiri: false, tenggat: null, terlambat: false, url_ujian: null, short_token: null, akses_otp: null }],
                total: 1,
                dipangkas: false,
            },
        });

        expect(w.text()).not.toContain('Mulai Menilai');
        expect(w.text()).toContain('Selesaikan penilaian Diri Sendiri lebih dulu.');
    });

    it('langkah tuntas menawarkan Kalibrasi Penilai', async () => {
        const w = mount(PaWizardLembarList, {
            props: { langkah: { kode_kategori: 'ATASAN', nama_kategori: 'Atasan', status: 'TUNTAS' }, lembar: [], total: 0, dipangkas: false },
        });

        await w.find('[data-uji="ke-pratinjau"]').trigger('click');
        expect(w.emitted('ke-pratinjau')).toHaveLength(1);
    });
});

describe('PaWizardPratinjauKalibrasi', () => {
    it('hanya-baca bila kategori tidak boleh dikalibrasi', () => {
        const w = mount(PaWizardPratinjauKalibrasi, { props: { pratinjau: pratinjau([orang(1, false)], false) } });

        expect(w.findAll('input').length).toBe(0);
        expect(w.text()).toContain('Hanya tinjau');
    });

    it('sel yang tidak dimiliki seseorang tampil strip, bukan nol', () => {
        const w = mount(PaWizardPratinjauKalibrasi, { props: { pratinjau: pratinjau([orang(1)]) } });

        expect(w.find('[data-uji="sel-1-12"]').text()).toBe('—');
    });

    it('simpan menuntut alasan dan memancarkan perubahan saja', async () => {
        const w = mount(PaWizardPratinjauKalibrasi, { props: { pratinjau: pratinjau([orang(1)]) } });

        w.vm.ubah(w.props('pratinjau').orang[0], kompetensi[0], 75);
        await w.vm.$nextTick();
        expect(w.emitted('kotor').at(-1)).toEqual([true]);

        w.vm.bukaAlasan(w.props('pratinjau').orang[0]);
        w.vm.kirimSimpan();
        expect(w.emitted('simpan')).toBeUndefined();
        expect(w.vm.galatAlasan).toBe('Alasan wajib diisi.');

        w.vm.alasan = 'Terlalu tinggi';
        w.vm.kirimSimpan();
        expect(w.emitted('simpan')[0][0]).toEqual({
            assignmentId: 1,
            perubahan: [{ competency_id: 11, skor: 75 }],
            alasan: 'Terlalu tinggi',
        });
    });
});

describe('PaWizardRingkasanKirim', () => {
    it('kirim meminta konfirmasi lebih dulu', async () => {
        const w = mount(PaWizardRingkasanKirim, {
            props: {
                langkah: [{ kode_kategori: 'ATASAN', nama_kategori: 'Atasan', status: 'TUNTAS', jumlah_lembar: 2, jumlah_terisi: 2, jumlah_terkalibrasi: 1 }],
                pengiriman: { total: 2, terkumpul: 2, terkirim: 0, sisa: 0, sudah_terkirim_semua: false },
                bolehKirim: true,
            },
        });

        await w.find('[data-uji="kirim"]').trigger('click');
        expect(w.emitted('kirim')).toBeUndefined();
        w.vm.konfirmasiKirim();
        expect(w.emitted('kirim')).toHaveLength(1);
    });
});
```

- [ ] **Langkah 2: Tulis `utils/statusLembar.js`**

```js
/**
 * Label dan varian status lembar untuk penilai. Satu tempat, supaya daftar lembar dan
 * pratinjau Kalibrasi Penilai tidak menyebut keadaan yang sama dengan dua nama.
 */
const PETA = {
    BELUM_MULAI: { label: 'Belum dimulai', variant: 'neutral' },
    BERJALAN: { label: 'Sedang diisi', variant: 'info' },
    MENUNGGU_VERIFIKASI: { label: 'Menunggu verifikasi', variant: 'warning' },
    TERKUMPUL: { label: 'Terisi, belum dikirim', variant: 'brand' },
    SELESAI: { label: 'Terkirim', variant: 'success' },
};

export function statusLembar(kode) {
    return PETA[kode] ?? { label: kode || '—', variant: 'neutral' };
}

export function angka(n) {
    if (n === null || n === undefined || Number.isNaN(Number(n))) return '—';
    return Number(n).toLocaleString('id-ID', { maximumFractionDigits: 2 });
}

export function tanggal(teks) {
    if (!teks) return '—';
    const d = new Date(String(teks).replace(' ', 'T'));
    return Number.isNaN(d.getTime()) ? String(teks) : d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}
```

- [ ] **Langkah 3: Tulis `PaWizardStepper.vue`**

```vue
<template>
    <nav class="pa-wizard-stepper" aria-label="Langkah penilaian">
        <HcisSteps :steps="daftar" :current="indeksAktif" clickable allow-all-clickable @change="pilih" />
    </nav>
</template>

<script setup>
import { computed } from 'vue';

import { HcisSteps } from '@/components/ui';

defineOptions({ name: 'PaWizardStepper' });

const props = defineProps({
    langkah: { type: Array, default: () => [] },
    kodeDilihat: { type: String, default: null },
    ringkasanTerbuka: { type: Boolean, default: false },
});

const emit = defineEmits(['pilih']);

const KEADAAN = { TUNTAS: 'done', AKTIF: 'current', TERKUNCI: 'todo' };
const LABEL = { TUNTAS: 'Tuntas', AKTIF: 'Sedang diisi', TERKUNCI: 'Terkunci' };

const daftar = computed(() => [
    ...props.langkah.map((l) => ({
        kode: l.kode_kategori,
        label: l.nama_kategori,
        // Langkah terkunci menyebut SEBABNYA, bukan sekadar abu-abu.
        description: l.status === 'TERKUNCI' ? l.sebab_terkunci : `${l.jumlah_terisi} dari ${l.jumlah_lembar} lembar terisi`,
        status: LABEL[l.status] ?? l.status,
        state: KEADAAN[l.status] ?? 'todo',
        icon: l.status === 'TERKUNCI' ? 'lock-fill' : undefined,
        disabled: l.status === 'TERKUNCI',
    })),
    {
        kode: 'RINGKASAN',
        label: 'Ringkasan & Kirim',
        description: 'Tinjau semua kategori lalu kirim',
        // Tanpa `state` tetap: keadaannya mengikuti `current`, jadi ia menyala saat dilihat.
        disabled: !props.ringkasanTerbuka,
    },
]);

const indeksAktif = computed(() => {
    if (props.kodeDilihat === null) return props.langkah.length;
    return Math.max(0, props.langkah.findIndex((l) => l.kode_kategori === props.kodeDilihat));
});

function pilih({ index }) {
    const langkah = daftar.value[index];
    if (langkah && !langkah.disabled) emit('pilih', langkah.kode);
}

defineExpose({ pilih });
</script>

<style scoped>
.pa-wizard-stepper {
    overflow-x: auto;
    padding-bottom: var(--hcis-space-2);
}
</style>
```

- [ ] **Langkah 4: Tulis `PaWizardLembarList.vue`**

```vue
<template>
    <section class="pa-wizard-lembar" :aria-label="`Lembar penilaian ${langkah?.nama_kategori ?? ''}`">
        <HcisAlert v-if="langkah?.status === 'TERKUNCI'" variant="info" icon="lock-fill" :title="langkah.sebab_terkunci" />
        <HcisAlert v-if="dipangkas" variant="warning" :title="`Menampilkan ${lembar.length} dari ${total} lembar.`" />

        <ul class="pa-wizard-lembar__daftar">
            <li v-for="b in lembar" :key="b.assignment_id" class="pa-wizard-lembar__kartu">
                <div class="pa-wizard-lembar__orang">
                    <strong>{{ b.peserta.nama }}</strong>
                    <span class="pa-wizard-lembar__sub">
                        {{ b.diri_sendiri ? 'Penilaian diri sendiri' : [b.peserta.jabatan, b.peserta.divisi].filter(Boolean).join(' · ') }}
                    </span>
                </div>

                <HcisStatusBadge :label="statusLembar(b.status_penugasan).label" :variant="statusLembar(b.status_penugasan).variant" size="sm" />

                <span v-if="b.terlambat" class="pa-wizard-lembar__tenggat is-terlambat">Terlambat {{ b.hari_terlambat }} hari</span>
                <span v-else-if="b.tenggat" class="pa-wizard-lembar__tenggat">Tenggat {{ tanggal(b.tenggat) }}</span>

                <div class="pa-wizard-lembar__aksi">
                    <HcisButton
                        v-if="!terisi(b) && urlAman(b.url_ujian)"
                        :href="urlAman(b.url_ujian)"
                        target="_blank"
                        rel="noopener"
                        variant="primary"
                        size="sm"
                        trailing-icon="box-arrow-up-right"
                    >Mulai Menilai</HcisButton>
                    <span v-else-if="terisi(b)" class="pa-wizard-lembar__sub">Sudah terisi</span>
                    <span v-else class="pa-wizard-lembar__sub">Tautan lembar belum tersedia</span>

                    <span v-if="!terisi(b) && b.short_token" class="pa-wizard-lembar__kode">
                        Kode {{ b.short_token }}<template v-if="b.akses_otp"> · OTP {{ b.akses_otp }}</template>
                    </span>
                </div>
            </li>
        </ul>

        <div v-if="langkah?.status === 'TUNTAS'" class="pa-wizard-lembar__lanjut">
            <HcisButton variant="primary" icon="sliders" data-uji="ke-pratinjau" @click="emit('ke-pratinjau')">
                Lanjut ke Kalibrasi Penilai
            </HcisButton>
        </div>
    </section>
</template>

<script setup>
import { HcisAlert, HcisButton, HcisStatusBadge } from '@/components/ui';
import { urlAman } from '@/components/ui/urlAman.js';

import { statusLembar, tanggal } from '../../utils/statusLembar.js';

defineOptions({ name: 'PaWizardLembarList' });

defineProps({
    langkah: { type: Object, default: null },
    lembar: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    dipangkas: { type: Boolean, default: false },
});

const emit = defineEmits(['ke-pratinjau']);

const TERISI = ['TERKUMPUL', 'SELESAI', 'MENUNGGU_VERIFIKASI'];

function terisi(b) {
    return TERISI.includes(b.status_penugasan);
}
</script>

<style scoped>
.pa-wizard-lembar { display: grid; gap: var(--hcis-space-3); }
.pa-wizard-lembar__daftar { display: grid; gap: var(--hcis-space-2); list-style: none; margin: 0; padding: 0; }
.pa-wizard-lembar__kartu {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: var(--hcis-space-2) var(--hcis-space-3);
    align-items: center;
    padding: var(--hcis-space-3) var(--hcis-space-4);
    background: var(--hcis-surface);
    border: 1px solid var(--hcis-border-subtle);
    border-radius: var(--hcis-radius-xl);
}
.pa-wizard-lembar__orang { display: grid; gap: 2px; min-width: 0; }
.pa-wizard-lembar__orang strong { color: var(--hcis-text-strong); overflow-wrap: anywhere; }
.pa-wizard-lembar__sub, .pa-wizard-lembar__tenggat { color: var(--hcis-text-muted); font-size: var(--hcis-font-size-sm); }
.pa-wizard-lembar__tenggat.is-terlambat { color: var(--hcis-text-strong); font-weight: 600; }
.pa-wizard-lembar__aksi { grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: var(--hcis-space-2); align-items: center; }
.pa-wizard-lembar__kode { font-family: ui-monospace, monospace; color: var(--hcis-text); }
.pa-wizard-lembar__lanjut { display: flex; justify-content: flex-end; }

@media (max-width: 767px) {
    .pa-wizard-lembar__lanjut :deep(.hcis-button) { width: 100%; }
}
</style>
```

- [ ] **Langkah 5: Tulis `PaWizardPratinjauKalibrasi.vue`**

```vue
<template>
    <section class="pa-kalibrasi" aria-labelledby="pa-kalibrasi-judul">
        <header class="pa-kalibrasi__kepala">
            <div>
                <h3 id="pa-kalibrasi-judul" class="pa-kalibrasi__judul">Kalibrasi Penilai — {{ pratinjau.kategori.nama }}</h3>
                <p class="pa-kalibrasi__uraian">
                    {{ pratinjau.kategori.boleh_kalibrasi
                        ? 'Bandingkan skor yang Anda berikan antar orang. Ubah yang kurang selaras, lalu simpan per orang beserta alasannya.'
                        : 'Ini skor yang Anda berikan di kategori ini. Kategori ini tidak dibuka untuk kalibrasi, jadi hanya untuk ditinjau.' }}
                </p>
            </div>
            <HcisBadge v-if="!pratinjau.kategori.boleh_kalibrasi" variant="neutral">Hanya tinjau</HcisBadge>
        </header>

        <HcisAlert
            v-if="galatSimpan && !dialogTerbuka"
            variant="danger"
            :title="galatSimpan.pesan"
            :description="galatSimpan.requestId ? `Kode ${galatSimpan.kode || '-'} · permintaan ${galatSimpan.requestId}` : ''"
        />

        <!-- Desktop: matriks orang × kompetensi. Menggulir di wadahnya sendiri, halaman tidak. -->
        <div class="pa-kalibrasi__wadah" role="region" aria-label="Matriks skor per orang dan kompetensi" tabindex="0">
            <table class="pa-kalibrasi__matriks">
                <thead>
                    <tr>
                        <th scope="col" class="pa-kalibrasi__nama">Orang yang dinilai</th>
                        <th v-for="k in pratinjau.kompetensi" :key="k.competency_id" scope="col" :title="k.jenis_nama">{{ k.nama }}</th>
                        <th scope="col"><span class="pa-kalibrasi__sr">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="o in pratinjau.orang" :key="o.assignment_id">
                        <th scope="row" class="pa-kalibrasi__nama">
                            {{ o.peserta.nama }}
                            <small>{{ o.diri_sendiri ? 'Diri sendiri' : o.peserta.jabatan }}</small>
                        </th>
                        <td
                            v-for="k in pratinjau.kompetensi"
                            :key="k.competency_id"
                            :data-uji="`sel-${o.assignment_id}-${k.competency_id}`"
                            :class="{ 'is-terkalibrasi': ditandai(o, k) }"
                        >
                            <template v-if="sel(o, k)">
                                <HcisInputNumber
                                    v-if="o.boleh_diubah"
                                    :model-value="nilai(o, k)"
                                    :min="0"
                                    :max="sel(o, k).skor_maksimal"
                                    :precision="2"
                                    size="sm"
                                    controls-position="none"
                                    :aria-label="`Skor ${k.nama} untuk ${o.peserta.nama}`"
                                    @update:model-value="(v) => ubah(o, k, v)"
                                />
                                <span v-else>{{ angka(sel(o, k).skor_efektif) }}</span>
                                <s v-if="ditandai(o, k)" class="pa-kalibrasi__asli" :title="`Skor asli ${angka(sel(o, k).skor_asli)}`">{{ angka(sel(o, k).skor_asli) }}</s>
                            </template>
                            <template v-else>—</template>
                        </td>
                        <td>
                            <HcisButton
                                v-if="o.boleh_diubah"
                                size="sm"
                                variant="primary"
                                :disabled="!adaPerubahan(o)"
                                :loading="menyimpanId === o.assignment_id"
                                @click="bukaAlasan(o)"
                            >Simpan</HcisButton>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row" class="pa-kalibrasi__nama">Rata-rata Anda (skala 0–100)</th>
                        <td v-for="k in pratinjau.kompetensi" :key="k.competency_id">{{ angka(k.rata_rata) }}</td>
                        <td />
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Ponsel: kartu per orang, tanpa gulir menyamping. -->
        <ul class="pa-kalibrasi__kartu-daftar">
            <li v-for="o in pratinjau.orang" :key="o.assignment_id" class="pa-kalibrasi__kartu">
                <header>
                    <strong>{{ o.peserta.nama }}</strong>
                    <small>{{ o.diri_sendiri ? 'Diri sendiri' : o.peserta.jabatan }}</small>
                </header>
                <dl>
                    <template v-for="k in pratinjau.kompetensi" :key="k.competency_id">
                        <div v-if="sel(o, k)" class="pa-kalibrasi__baris" :class="{ 'is-terkalibrasi': ditandai(o, k) }">
                            <dt>{{ k.nama }} <small>rata-rata {{ angka(k.rata_rata) }}</small></dt>
                            <dd>
                                <HcisInputNumber
                                    v-if="o.boleh_diubah"
                                    :model-value="nilai(o, k)"
                                    :min="0"
                                    :max="sel(o, k).skor_maksimal"
                                    :precision="2"
                                    size="sm"
                                    controls-position="none"
                                    :aria-label="`Skor ${k.nama} untuk ${o.peserta.nama}`"
                                    @update:model-value="(v) => ubah(o, k, v)"
                                />
                                <span v-else>{{ angka(sel(o, k).skor_efektif) }}</span>
                                <s v-if="ditandai(o, k)" class="pa-kalibrasi__asli">{{ angka(sel(o, k).skor_asli) }}</s>
                            </dd>
                        </div>
                    </template>
                </dl>
                <HcisButton v-if="o.boleh_diubah" block variant="primary" :disabled="!adaPerubahan(o)" :loading="menyimpanId === o.assignment_id" @click="bukaAlasan(o)">
                    Simpan kalibrasi
                </HcisButton>
            </li>
        </ul>

        <section v-if="adaNaratif" class="pa-kalibrasi__naratif" aria-label="Jawaban naratif Anda">
            <h4>Jawaban naratif Anda</h4>
            <details v-for="o in pratinjau.orang.filter((x) => x.naratif.length)" :key="o.assignment_id">
                <summary>{{ o.peserta.nama }} ({{ o.naratif.length }})</summary>
                <dl>
                    <div v-for="(n, i) in o.naratif" :key="i">
                        <dt>{{ n.nama || 'Pertanyaan uraian' }}</dt>
                        <dd>{{ n.jawaban }}</dd>
                    </div>
                </dl>
            </details>
        </section>

        <footer class="pa-kalibrasi__kaki">
            <HcisButton variant="secondary" icon="arrow-left" @click="emit('ke-lembar')">Lihat lembar</HcisButton>
            <HcisButton variant="primary" trailing-icon="arrow-right" data-uji="lanjut" @click="emit('lanjut')">{{ labelLanjut }}</HcisButton>
        </footer>

        <HcisDialog v-model="dialogTerbuka" :title="`Alasan kalibrasi — ${orangDialog?.peserta.nama ?? ''}`" :size="kecil ? 'full' : 'md'">
            <HcisAlert v-if="galatSimpan" variant="danger" :title="galatSimpan.pesan" />
            <HcisTextarea
                v-model="alasan"
                label="Alasan"
                :maxlength="500"
                :rows="4"
                required
                placeholder="Mis. setelah dibandingkan dengan rekan lain, nilai komunikasi terlalu tinggi."
                :error="galatAlasan"
            />
            <template #footer>
                <div class="pa-kalibrasi__aksi-dialog">
                    <HcisButton variant="secondary" @click="dialogTerbuka = false">Batal</HcisButton>
                    <HcisButton variant="primary" :loading="menyimpanId === orangDialog?.assignment_id" @click="kirimSimpan">Simpan kalibrasi</HcisButton>
                </div>
            </template>
        </HcisDialog>
    </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

import { HcisAlert, HcisBadge, HcisButton, HcisDialog, HcisInputNumber, HcisTextarea } from '@/components/ui';

import { angka } from '../../utils/statusLembar.js';

defineOptions({ name: 'PaWizardPratinjauKalibrasi' });

const props = defineProps({
    pratinjau: { type: Object, required: true },
    menyimpanId: { type: Number, default: 0 },
    galatSimpan: { type: Object, default: null },
    labelLanjut: { type: String, default: 'Lanjut' },
});

const emit = defineEmits(['simpan', 'kotor', 'lanjut', 'ke-lembar']);

const TOLERANSI = 0.005;

// assignment_id → competency_id → skor yang sedang diketik
const draf = reactive({});
const dialogTerbuka = ref(false);
const orangDialog = ref(null);
const alasan = ref('');
const galatAlasan = ref('');
const sedangDisimpan = ref(0);

const kecil = ref(false);
let media = null;
const segarkanUkuran = () => { kecil.value = Boolean(media?.matches); };

onMounted(() => {
    if (typeof window.matchMedia === 'function') {
        media = window.matchMedia('(max-width: 767px)');
        segarkanUkuran();
        media.addEventListener?.('change', segarkanUkuran);
    }
});

onBeforeUnmount(() => media?.removeEventListener?.('change', segarkanUkuran));

function sel(o, k) {
    return o.sel.find((s) => s.competency_id === k.competency_id) ?? null;
}

function nilai(o, k) {
    return draf[o.assignment_id]?.[k.competency_id] ?? sel(o, k)?.skor_efektif ?? null;
}

function berubah(o, k) {
    const d = draf[o.assignment_id]?.[k.competency_id];
    const s = sel(o, k);
    return d !== undefined && s !== null && Math.abs(Number(d) - s.skor_efektif) >= TOLERANSI;
}

function ditandai(o, k) {
    return Boolean(sel(o, k)?.terkalibrasi) || berubah(o, k);
}

function adaPerubahan(o) {
    return props.pratinjau.kompetensi.some((k) => berubah(o, k));
}

function ubah(o, k, v) {
    draf[o.assignment_id] ??= {};
    draf[o.assignment_id][k.competency_id] = v === null || v === '' ? sel(o, k).skor_efektif : Number(v);
}

const adaNaratif = computed(() => props.pratinjau.orang.some((o) => o.naratif.length > 0));
const kotor = computed(() => props.pratinjau.orang.some((o) => adaPerubahan(o)));

watch(kotor, (v) => emit('kotor', v), { immediate: true });

/* Pratinjau baru dari server adalah sumber kebenaran. Draf orang yang baru disimpan dibuang
   dan dialognya ditutup; draf orang lain tetap, karena belum disimpan. */
watch(() => props.pratinjau, () => {
    if (sedangDisimpan.value) {
        delete draf[sedangDisimpan.value];
        sedangDisimpan.value = 0;
        dialogTerbuka.value = false;
    }
});

function bukaAlasan(o) {
    orangDialog.value = o;
    alasan.value = '';
    galatAlasan.value = '';
    dialogTerbuka.value = true;
}

function kirimSimpan() {
    const teks = alasan.value.trim();

    if (!teks) {
        galatAlasan.value = 'Alasan wajib diisi.';
        return;
    }

    const o = orangDialog.value;
    const perubahan = props.pratinjau.kompetensi
        .filter((k) => berubah(o, k))
        .map((k) => ({ competency_id: k.competency_id, skor: Number(draf[o.assignment_id][k.competency_id]) }));

    sedangDisimpan.value = o.assignment_id;
    emit('simpan', { assignmentId: o.assignment_id, perubahan, alasan: teks });
}

defineExpose({ ubah, bukaAlasan, kirimSimpan, alasan, galatAlasan });
</script>

<style scoped>
.pa-kalibrasi { display: grid; gap: var(--hcis-space-4); }
.pa-kalibrasi__kepala { display: flex; justify-content: space-between; gap: var(--hcis-space-3); align-items: flex-start; }
.pa-kalibrasi__judul { margin: 0; color: var(--hcis-text-strong); font-size: var(--hcis-font-size-lg); }
.pa-kalibrasi__uraian { margin: var(--hcis-space-1) 0 0; color: var(--hcis-text-muted); font-size: var(--hcis-font-size-sm); }
.pa-kalibrasi__sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }

.pa-kalibrasi__wadah {
    overflow-x: auto;
    border: 1px solid var(--hcis-border-subtle);
    border-radius: var(--hcis-radius-xl);
    background: var(--hcis-surface);
}
.pa-kalibrasi__matriks { border-collapse: separate; border-spacing: 0; min-width: 100%; font-size: var(--hcis-font-size-sm); }
.pa-kalibrasi__matriks th,
.pa-kalibrasi__matriks td {
    padding: var(--hcis-space-2) var(--hcis-space-3);
    border-bottom: 1px solid var(--hcis-border-subtle);
    text-align: center;
    white-space: nowrap;
    color: var(--hcis-text);
}
.pa-kalibrasi__matriks thead th { color: var(--hcis-text-muted); font-weight: 600; background: var(--hcis-surface-soft); }
.pa-kalibrasi__nama {
    position: sticky;
    left: 0;
    z-index: 1;
    text-align: left !important;
    background: var(--hcis-surface);
    color: var(--hcis-text-strong);
}
.pa-kalibrasi__nama small { display: block; color: var(--hcis-text-muted); font-weight: 400; }
.pa-kalibrasi__matriks tfoot td,
.pa-kalibrasi__matriks tfoot th { background: var(--hcis-surface-soft); font-weight: 600; }
.is-terkalibrasi { background: var(--hcis-surface-brand); }
.pa-kalibrasi__asli { display: block; color: var(--hcis-text-subtle); font-size: var(--hcis-font-size-xs); }

.pa-kalibrasi__kartu-daftar { display: none; list-style: none; margin: 0; padding: 0; gap: var(--hcis-space-3); }
.pa-kalibrasi__kartu {
    display: grid;
    gap: var(--hcis-space-3);
    padding: var(--hcis-space-4);
    border: 1px solid var(--hcis-border-subtle);
    border-radius: var(--hcis-radius-xl);
    background: var(--hcis-surface);
}
.pa-kalibrasi__kartu header { display: grid; }
.pa-kalibrasi__kartu header small { color: var(--hcis-text-muted); }
.pa-kalibrasi__kartu dl { display: grid; gap: var(--hcis-space-2); margin: 0; }
.pa-kalibrasi__baris { display: grid; grid-template-columns: minmax(0, 1fr) 7rem; gap: var(--hcis-space-2); align-items: center; padding: var(--hcis-space-1) var(--hcis-space-2); border-radius: var(--hcis-radius-md); }
.pa-kalibrasi__baris dt { overflow-wrap: anywhere; color: var(--hcis-text); }
.pa-kalibrasi__baris dt small { display: block; color: var(--hcis-text-muted); }
.pa-kalibrasi__baris dd { margin: 0; text-align: right; }

.pa-kalibrasi__naratif h4 { margin: 0 0 var(--hcis-space-2); color: var(--hcis-text-strong); }
.pa-kalibrasi__naratif details { padding: var(--hcis-space-2) 0; border-top: 1px solid var(--hcis-border-subtle); }
.pa-kalibrasi__naratif dd { margin: 0 0 var(--hcis-space-2); color: var(--hcis-text); white-space: pre-line; }

.pa-kalibrasi__kaki { display: flex; justify-content: space-between; gap: var(--hcis-space-3); }
.pa-kalibrasi__aksi-dialog { display: flex; justify-content: flex-end; gap: var(--hcis-space-2); width: 100%; }

@media (max-width: 767px) {
    .pa-kalibrasi__wadah { display: none; }
    .pa-kalibrasi__kartu-daftar { display: grid; }
    .pa-kalibrasi__kaki,
    .pa-kalibrasi__aksi-dialog { display: grid; grid-template-columns: 1fr 1fr; }
}
</style>
```

- [ ] **Langkah 6: Tulis `PaWizardRingkasanKirim.vue`**

```vue
<template>
    <section class="pa-ringkasan" aria-labelledby="pa-ringkasan-judul">
        <h3 id="pa-ringkasan-judul" class="pa-ringkasan__judul">Ringkasan penilaian Anda</h3>

        <HcisAlert v-if="pengiriman?.sudah_terkirim_semua" variant="success" title="Seluruh penilaian Anda sudah terkirim." />
        <HcisAlert v-else-if="galatKirim" variant="danger" :title="galatKirim.pesan"
            :description="galatKirim.requestId ? `Kode ${galatKirim.kode || '-'} · permintaan ${galatKirim.requestId}` : ''" />

        <ul class="pa-ringkasan__daftar">
            <li v-for="l in langkah" :key="l.kode_kategori" class="pa-ringkasan__baris">
                <div>
                    <strong>{{ l.nama_kategori }}</strong>
                    <span>{{ l.jumlah_terisi }} dari {{ l.jumlah_lembar }} lembar terisi · {{ l.jumlah_terkalibrasi }} dikalibrasi</span>
                </div>
                <HcisButton v-if="!pengiriman?.sudah_terkirim_semua" size="sm" variant="secondary" @click="emit('buka', l.kode_kategori)">Tinjau</HcisButton>
            </li>
        </ul>

        <div v-if="!pengiriman?.sudah_terkirim_semua" class="pa-ringkasan__kirim">
            <p>Setelah dikirim, skor Anda tidak bisa diubah lagi dan mulai dipakai menghitung hasil.</p>
            <HcisButton variant="primary" icon="send-fill" data-uji="kirim" :disabled="!bolehKirim" :loading="mengirim" @click="konfirmasiTerbuka = true">
                Kirim seluruh penilaian
            </HcisButton>
        </div>

        <HcisDialog v-model="konfirmasiTerbuka" title="Kirim seluruh penilaian?" size="sm">
            <p>{{ pengiriman?.total ?? 0 }} lembar akan dikirim. Skor yang sudah dikirim tidak bisa dikalibrasi lagi.</p>
            <template #footer>
                <div class="pa-ringkasan__aksi">
                    <HcisButton variant="secondary" @click="konfirmasiTerbuka = false">Batal</HcisButton>
                    <HcisButton variant="primary" :loading="mengirim" @click="konfirmasiKirim">Kirim</HcisButton>
                </div>
            </template>
        </HcisDialog>
    </section>
</template>

<script setup>
import { ref } from 'vue';

import { HcisAlert, HcisButton, HcisDialog } from '@/components/ui';

defineOptions({ name: 'PaWizardRingkasanKirim' });

defineProps({
    langkah: { type: Array, default: () => [] },
    pengiriman: { type: Object, default: null },
    bolehKirim: { type: Boolean, default: false },
    mengirim: { type: Boolean, default: false },
    galatKirim: { type: Object, default: null },
});

const emit = defineEmits(['buka', 'kirim']);

const konfirmasiTerbuka = ref(false);

function konfirmasiKirim() {
    konfirmasiTerbuka.value = false;
    emit('kirim');
}

defineExpose({ konfirmasiKirim });
</script>

<style scoped>
.pa-ringkasan { display: grid; gap: var(--hcis-space-4); }
.pa-ringkasan__judul { margin: 0; color: var(--hcis-text-strong); font-size: var(--hcis-font-size-lg); }
.pa-ringkasan__daftar { display: grid; gap: var(--hcis-space-2); list-style: none; margin: 0; padding: 0; }
.pa-ringkasan__baris {
    display: flex; justify-content: space-between; align-items: center; gap: var(--hcis-space-3);
    padding: var(--hcis-space-3) var(--hcis-space-4);
    border: 1px solid var(--hcis-border-subtle); border-radius: var(--hcis-radius-xl); background: var(--hcis-surface);
}
.pa-ringkasan__baris div { display: grid; }
.pa-ringkasan__baris strong { color: var(--hcis-text-strong); }
.pa-ringkasan__baris span { color: var(--hcis-text-muted); font-size: var(--hcis-font-size-sm); }
.pa-ringkasan__kirim { display: grid; gap: var(--hcis-space-2); justify-items: end; }
.pa-ringkasan__kirim p { margin: 0; color: var(--hcis-text-muted); font-size: var(--hcis-font-size-sm); }
.pa-ringkasan__aksi { display: flex; justify-content: flex-end; gap: var(--hcis-space-2); width: 100%; }

@media (max-width: 767px) {
    .pa-ringkasan__kirim { justify-items: stretch; }
    .pa-ringkasan__aksi { display: grid; grid-template-columns: 1fr 1fr; }
}
</style>
```

- [ ] **Langkah 7: Tulis perakit `PaSayaWizardPenilaian.vue`**

```vue
<template>
    <div class="pa-wizard">
        <HcisSkeleton v-if="memuat && !wizard" :lines="5" />

        <HcisResultState
            v-else-if="galat"
            status="error"
            title="Tugas penilaian gagal dimuat"
            :description="galat.pesan"
            :error-code="galat.kode"
            :request-id="galat.requestId"
            show-retry
            @retry="muat()"
        />

        <HcisEmptyState
            v-else-if="kosong || (wizard && !wizard.siklus)"
            icon="clipboard-check"
            title="Belum ada tugas penilaian"
            description="Tidak ada siklus terbuka yang memuat tugas penilaian Anda."
        />

        <template v-else-if="wizard">
            <header class="pa-wizard__kepala">
                <div>
                    <p class="pa-wizard__siklus">{{ siklus.nama_cycle }}</p>
                    <p v-if="wizard.pengiriman" class="pa-wizard__kemajuan">
                        {{ wizard.pengiriman.terkumpul + wizard.pengiriman.terkirim }} dari {{ wizard.pengiriman.total }} lembar terisi
                    </p>
                </div>
                <HcisButton variant="secondary" size="sm" icon="arrow-clockwise" :loading="memuat" @click="muat({ pertahankanPosisi: true })">
                    Perbarui
                </HcisButton>
            </header>

            <PaWizardStepper
                :langkah="langkah"
                :kode-dilihat="tampilan === 'ringkasan' ? null : kodeDilihat"
                :ringkasan-terbuka="wizard.langkah_aktif === 'RINGKASAN'"
                @pilih="pilihLangkah"
            />

            <HcisSkeleton v-if="memuatIsi" :lines="4" />

            <HcisResultState
                v-else-if="galatIsi"
                status="error"
                title="Isi langkah gagal dimuat"
                :description="galatIsi.pesan"
                :error-code="galatIsi.kode"
                :request-id="galatIsi.requestId"
                show-retry
                @retry="muatIsi()"
            />

            <PaWizardLembarList
                v-else-if="tampilan === 'lembar' && lembar"
                :langkah="lembar.langkah"
                :lembar="lembar.lembar"
                :total="lembar.total"
                :dipangkas="lembar.dipangkas"
                @ke-pratinjau="buka(kodeDilihat, 'pratinjau')"
            />

            <PaWizardPratinjauKalibrasi
                v-else-if="tampilan === 'pratinjau' && pratinjau"
                :pratinjau="pratinjau"
                :menyimpan-id="menyimpanId"
                :galat-simpan="galatSimpan"
                :label-lanjut="labelLanjut"
                @kotor="(v) => (kotor = v)"
                @simpan="simpan"
                @lanjut="denganKonfirmasi((o) => lanjut(o))"
                @ke-lembar="denganKonfirmasi((o) => buka(kodeDilihat, 'lembar', o))"
            />

            <PaWizardRingkasanKirim
                v-else-if="tampilan === 'ringkasan'"
                :langkah="langkah"
                :pengiriman="wizard.pengiriman"
                :boleh-kirim="wizard.boleh_kirim"
                :mengirim="mengirim"
                :galat-kirim="galatKirim"
                @buka="(kode) => buka(kode, 'pratinjau')"
                @kirim="kirimSemua"
            />
        </template>

        <HcisDialog v-model="konfirmasiTerbuka" title="Perubahan belum disimpan" size="sm">
            <p>Skor yang Anda ubah di kategori ini belum disimpan. Tinggalkan dan buang perubahannya?</p>
            <template #footer>
                <div class="pa-wizard__aksi">
                    <HcisButton variant="secondary" @click="konfirmasiTerbuka = false">Tetap di sini</HcisButton>
                    <HcisButton variant="danger" @click="jalankanTertunda">Buang perubahan</HcisButton>
                </div>
            </template>
        </HcisDialog>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';

import { HcisButton, HcisDialog, HcisEmptyState, HcisResultState, HcisSkeleton, hcisMessage } from '@/components/ui';

import { usePaWizardPenilaian } from '../composables/usePaWizardPenilaian.js';
import PaWizardLembarList from './PaWizardLembarList.vue';
import PaWizardPratinjauKalibrasi from './PaWizardPratinjauKalibrasi.vue';
import PaWizardRingkasanKirim from './PaWizardRingkasanKirim.vue';
import PaWizardStepper from './PaWizardStepper.vue';

defineOptions({ name: 'PaSayaWizardPenilaian' });

const emit = defineEmits(['terkirim']);

const {
    wizard, siklus, langkah, langkahBerikut, kodeDilihat, tampilan, kosong,
    memuat, galat, lembar, pratinjau, memuatIsi, galatIsi, menyimpanId, galatSimpan, kotor,
    mengirim, galatKirim, muat, muatIsi, buka, bukaRingkasan, lanjut, simpanKalibrasi, kirim,
} = usePaWizardPenilaian();

const konfirmasiTerbuka = ref(false);
let tertunda = null;

const labelLanjut = computed(() => (langkahBerikut.value ? `Lanjut ke ${langkahBerikut.value.nama_kategori}` : 'Lanjut ke Ringkasan'));

/** Menjalankan aksi berpindah; bila ditolak karena ada perubahan belum tersimpan, minta konfirmasi. */
function denganKonfirmasi(aksi) {
    if (aksi({ paksa: false }) === false) {
        tertunda = aksi;
        konfirmasiTerbuka.value = true;
    }
}

function jalankanTertunda() {
    konfirmasiTerbuka.value = false;
    tertunda?.({ paksa: true });
    tertunda = null;
}

function pilihLangkah(kode) {
    if (kode === 'RINGKASAN') denganKonfirmasi((o) => bukaRingkasan(o));
    else denganKonfirmasi((o) => buka(kode, null, o));
}

async function simpan(payload) {
    if (await simpanKalibrasi(payload)) hcisMessage.success('Kalibrasi tersimpan.');
}

async function kirimSemua() {
    if (await kirim()) {
        hcisMessage.success('Seluruh penilaian Anda terkirim.');
        emit('terkirim');
    }
}
</script>

<style scoped>
.pa-wizard { display: grid; gap: var(--hcis-space-4); min-width: 0; }
.pa-wizard__kepala { display: flex; justify-content: space-between; align-items: center; gap: var(--hcis-space-3); }
.pa-wizard__siklus { margin: 0; color: var(--hcis-text-strong); font-weight: 600; }
.pa-wizard__kemajuan { margin: 0; color: var(--hcis-text-muted); font-size: var(--hcis-font-size-sm); }
.pa-wizard__aksi { display: flex; justify-content: flex-end; gap: var(--hcis-space-2); width: 100%; }

@media (max-width: 767px) {
    .pa-wizard__aksi { display: grid; grid-template-columns: 1fr 1fr; }
}
</style>
```

Periksa pemakaian `hcisMessage` di berkas PA lain (`grep -rn "hcisMessage\." resources/js/Pages/KPI/PerformanceAppraisal | head -3`); bila bentuk panggilannya bukan `hcisMessage.success(teks)`, samakan.

- [ ] **Langkah 8: Pasang di `saya.vue`**

Ganti blok `<PaSayaAssignmentPanel v-else-if="tabAktif === 'assignment'" ... @kirim="kirimSemua" />` dengan:

```vue
            <PaSayaWizardPenilaian
                v-else-if="tabAktif === 'assignment'"
                @terkirim="() => { muat(); muatKemajuanPengiriman(); }"
            />
```

Ganti `import PaSayaAssignmentPanel from './components/PaSayaAssignmentPanel.vue';` dengan `import PaSayaWizardPenilaian from './components/PaSayaWizardPenilaian.vue';`. Berkas `PaSayaAssignmentPanel.vue` dan endpoint `/assignment` **tidak dihapus** (spec §10). Periksa `saya.test.js`: bila ia mencari markup panel lama di tab tugas, arahkan ke stub `PaSayaWizardPenilaian` mengikuti pola stub yang sudah dipakai berkas itu.

- [ ] **Langkah 9: Pemeriksaan statis dan commit**

```bash
php scripts/verify-frontend-imports.php
git add resources/js/Pages/KPI/PerformanceAppraisal/utils/statusLembar.js resources/js/Pages/KPI/PerformanceAppraisal/Peserta/components/PaWizardStepper.vue resources/js/Pages/KPI/PerformanceAppraisal/Peserta/components/PaWizardLembarList.vue resources/js/Pages/KPI/PerformanceAppraisal/Peserta/components/PaWizardPratinjauKalibrasi.vue resources/js/Pages/KPI/PerformanceAppraisal/Peserta/components/PaWizardRingkasanKirim.vue resources/js/Pages/KPI/PerformanceAppraisal/Peserta/components/PaSayaWizardPenilaian.vue resources/js/Pages/KPI/PerformanceAppraisal/Peserta/components/wizard.test.js resources/js/Pages/KPI/PerformanceAppraisal/Peserta/saya.vue
git commit -m "feat(pa): wizard tugas penilaian dengan Kalibrasi Penilai di PA Saya"
```

---

### Tugas 13: Layar admin — pengaturan kategori dan riwayat di detail hasil

**Berkas:**
- Buat: `resources/js/Pages/KPI/PerformanceAppraisal/MasterData/components/PaPengaturanKategoriPenilai.vue` (+ `PaPengaturanKategoriPenilai.test.js`)
- Ubah: `resources/js/Pages/KPI/PerformanceAppraisal/MasterData/jenis-penilai.vue`
- Buat: `resources/js/Pages/KPI/PerformanceAppraisal/Cycle/components/PaRiwayatKalibrasiPenilai.vue` (+ `PaRiwayatKalibrasiPenilai.test.js`)
- Ubah: `resources/js/Pages/KPI/PerformanceAppraisal/Cycle/components/PaResultDialog.vue`

**Antarmuka:**
- Mengonsumsi: `paApi.tataUrutanKategoriPenilai`, `paApi.setelBolehKalibrasiKategori` (Tugas 10); `matriks().kategori[]` berisi `urutan_pengisian`, `boleh_kalibrasi` (Tugas 8); `detail.kalibrasi_penilai` (Tugas 9).
- Menghasilkan: `<PaPengaturanKategoriPenilai :kategori="..." @berubah="..." />`, `<PaRiwayatKalibrasiPenilai :riwayat="..." />`.

- [ ] **Langkah 1: Tulis uji kedua komponen**

`PaPengaturanKategoriPenilai.test.js`:

```js
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import paApi from '../../api/paApi.js';
import PaPengaturanKategoriPenilai from './PaPengaturanKategoriPenilai.vue';

vi.mock('../../api/paApi.js', () => ({
    default: { tataUrutanKategoriPenilai: vi.fn(), setelBolehKalibrasiKategori: vi.fn() },
}));

const kategori = [
    { id: 4, kode: 'SELF', nama: 'Diri Sendiri', urutan_pengisian: 1, boleh_kalibrasi: false },
    { id: 1, kode: 'ATASAN', nama: 'Atasan', urutan_pengisian: 2, boleh_kalibrasi: true },
];

describe('PaPengaturanKategoriPenilai', () => {
    beforeEach(() => vi.clearAllMocks());

    it('menggeser urutan mengirim DAFTAR LENGKAP ke server', async () => {
        paApi.tataUrutanKategoriPenilai.mockResolvedValue([]);
        const w = mount(PaPengaturanKategoriPenilai, { props: { kategori } });

        await w.vm.geser(1, -1);
        await flushPromises();

        expect(paApi.tataUrutanKategoriPenilai).toHaveBeenCalledWith(['ATASAN', 'SELF']);
        expect(w.emitted('berubah')).toHaveLength(1);
    });

    it('menyalakan kalibrasi memanggil endpoint kategori itu', async () => {
        paApi.setelBolehKalibrasiKategori.mockResolvedValue({});
        const w = mount(PaPengaturanKategoriPenilai, { props: { kategori } });

        await w.vm.setelKalibrasi(kategori[0], true);
        await flushPromises();

        expect(paApi.setelBolehKalibrasiKategori).toHaveBeenCalledWith(4, true);
    });

    it('menyebut bahwa perubahan berlaku seketika', () => {
        const w = mount(PaPengaturanKategoriPenilai, { props: { kategori } });
        expect(w.text()).toContain('berlaku seketika');
    });
});
```

`PaRiwayatKalibrasiPenilai.test.js`:

```js
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import PaRiwayatKalibrasiPenilai from './PaRiwayatKalibrasiPenilai.vue';

describe('PaRiwayatKalibrasiPenilai', () => {
    it('menampilkan siapa, alasan, dan perubahan dari berapa ke berapa', () => {
        const w = mount(PaRiwayatKalibrasiPenilai, {
            props: {
                riwayat: [{
                    kalibrasi_id: 1, assignment_id: 2,
                    penilai: { kode_karyawan: 'A4', nama: 'Aji' },
                    kategori: { kode: 'ATASAN', nama: 'Atasan' },
                    alasan: 'Diselaraskan', pada: '2026-09-18 10:15:00', oleh: 1,
                    perubahan: [{ competency_id: 11, kode: 'CV01', nama: 'Integritas', skor_sebelum: 90, skor_sesudah: 82, skor_maksimal: 100 }],
                }],
            },
        });

        expect(w.text()).toContain('Aji');
        expect(w.text()).toContain('Diselaraskan');
        expect(w.text()).toContain('Integritas');
        expect(w.text()).toMatch(/90.*82/);
    });

    it('kosong berarti tidak dirender', () => {
        expect(mount(PaRiwayatKalibrasiPenilai, { props: { riwayat: [] } }).html()).toBe('<!--v-if-->');
    });
});
```

- [ ] **Langkah 2: Tulis `PaPengaturanKategoriPenilai.vue`**

```vue
<template>
    <section class="pa-atur-kategori" aria-labelledby="pa-atur-kategori-judul">
        <header>
            <h3 id="pa-atur-kategori-judul">Urutan pengisian & Kalibrasi Penilai</h3>
            <p>Urutan menentukan langkah wizard penilai. Kalibrasi Penilai mengizinkan penilai mengubah skornya sebelum mengirim. Keduanya <strong>berlaku seketika</strong>, termasuk pada siklus yang sedang berjalan.</p>
        </header>

        <HcisAlert v-if="galat" variant="danger" :title="galat" />

        <ol class="pa-atur-kategori__daftar">
            <li v-for="(k, i) in urut" :key="k.id" class="pa-atur-kategori__baris">
                <span class="pa-atur-kategori__nomor">{{ i + 1 }}</span>
                <span class="pa-atur-kategori__nama">{{ k.nama }}</span>
                <div class="pa-atur-kategori__geser">
                    <HcisButton icon-only icon="arrow-up" size="sm" variant="secondary" :disabled="i === 0 || sibuk" :aria-label="`Naikkan ${k.nama}`" @click="geser(i, -1)" />
                    <HcisButton icon-only icon="arrow-down" size="sm" variant="secondary" :disabled="i === urut.length - 1 || sibuk" :aria-label="`Turunkan ${k.nama}`" @click="geser(i, 1)" />
                </div>
                <HcisSwitch :model-value="k.boleh_kalibrasi" :disabled="sibuk" :aria-label="`Kalibrasi Penilai untuk ${k.nama}`" @update:model-value="(v) => setelKalibrasi(k, v)" />
            </li>
        </ol>
    </section>
</template>

<script setup>
import { computed, ref } from 'vue';

import { HcisAlert, HcisButton, HcisSwitch } from '@/components/ui';

import paApi from '../../api/paApi.js';

defineOptions({ name: 'PaPengaturanKategoriPenilai' });

const props = defineProps({
    kategori: { type: Array, default: () => [] },
});

const emit = defineEmits(['berubah']);

const sibuk = ref(false);
const galat = ref('');

const urut = computed(() => [...props.kategori].sort(
    (a, b) => (a.urutan_pengisian ?? Number.MAX_SAFE_INTEGER) - (b.urutan_pengisian ?? Number.MAX_SAFE_INTEGER) || a.id - b.id,
));

async function jalankan(kerja) {
    sibuk.value = true;
    galat.value = '';

    try {
        await kerja();
        emit('berubah');
    } catch (e) {
        galat.value = e?.message || 'Perubahan gagal disimpan.';
    } finally {
        sibuk.value = false;
    }
}

/** Mengirim DAFTAR LENGKAP: server menulisnya dalam satu pernyataan supaya indeks unik tidak tertabrak. */
function geser(indeks, arah) {
    const daftar = urut.value.map((k) => k.kode);
    const tujuan = indeks + arah;
    if (tujuan < 0 || tujuan >= daftar.length) return Promise.resolve();
    [daftar[indeks], daftar[tujuan]] = [daftar[tujuan], daftar[indeks]];

    return jalankan(() => paApi.tataUrutanKategoriPenilai(daftar));
}

function setelKalibrasi(k, boleh) {
    return jalankan(() => paApi.setelBolehKalibrasiKategori(k.id, Boolean(boleh)));
}

defineExpose({ geser, setelKalibrasi });
</script>

<style scoped>
.pa-atur-kategori { display: grid; gap: var(--hcis-space-3); padding: var(--hcis-space-4); border: 1px solid var(--hcis-border-subtle); border-radius: var(--hcis-radius-xl); background: var(--hcis-surface); }
.pa-atur-kategori h3 { margin: 0; color: var(--hcis-text-strong); font-size: var(--hcis-font-size-md); }
.pa-atur-kategori p { margin: var(--hcis-space-1) 0 0; color: var(--hcis-text-muted); font-size: var(--hcis-font-size-sm); }
.pa-atur-kategori__daftar { display: grid; gap: var(--hcis-space-2); margin: 0; padding: 0; list-style: none; }
.pa-atur-kategori__baris { display: grid; grid-template-columns: 2rem minmax(0, 1fr) auto auto; gap: var(--hcis-space-3); align-items: center; }
.pa-atur-kategori__nomor { color: var(--hcis-text-muted); font-weight: 600; text-align: center; }
.pa-atur-kategori__nama { color: var(--hcis-text-strong); overflow-wrap: anywhere; }
.pa-atur-kategori__geser { display: flex; gap: var(--hcis-space-1); }
</style>
```

Sebelum menulis, periksa prop `HcisSwitch` (`sed -n '/defineProps(/,/^})/p' resources/js/components/ui/HcisSwitch.vue`) dan `HcisButton` (`iconOnly`); samakan nama prop bila berbeda.

- [ ] **Langkah 3: Pasang di `jenis-penilai.vue`**

Temukan variabel yang menampung hasil `/jenis-penilai/matriks` dan fungsi pemuatnya (`grep -n "matriks" resources/js/Pages/KPI/PerformanceAppraisal/MasterData/jenis-penilai.vue`). Impor `PaPengaturanKategoriPenilai` dari `./components/PaPengaturanKategoriPenilai.vue`, lalu pasang tepat di atas tabel matriks:

```vue
<PaPengaturanKategoriPenilai
    v-if="<variabel matriks>?.kategori?.length"
    :kategori="<variabel matriks>.kategori"
    @berubah="<fungsi pemuat matriks>()"
/>
```

`<variabel matriks>` dan `<fungsi pemuat matriks>` diganti dengan nama sungguhan yang ditemukan grep di atas; tidak ada nama baru yang dibuat.

- [ ] **Langkah 4: Tulis `PaRiwayatKalibrasiPenilai.vue`**

```vue
<template>
    <section v-if="riwayat.length" class="pa-riwayat-kp" aria-labelledby="pa-riwayat-kp-judul">
        <h4 id="pa-riwayat-kp-judul">Kalibrasi Penilai</h4>
        <p class="pa-riwayat-kp__uraian">Skor yang direvisi penilai sendiri sebelum mengirim. Berbeda dari Kalibrasi Forum atas grade akhir.</p>
        <ol class="pa-riwayat-kp__daftar">
            <li v-for="r in riwayat" :key="r.kalibrasi_id">
                <div class="pa-riwayat-kp__kepala">
                    <strong>{{ r.penilai.nama }}</strong>
                    <span>{{ r.kategori.nama }} · {{ r.pada }}</span>
                </div>
                <p class="pa-riwayat-kp__alasan">“{{ r.alasan }}”</p>
                <ul>
                    <li v-for="p in r.perubahan" :key="p.competency_id">
                        {{ p.nama }}: {{ angka(p.skor_sebelum) }} → <strong>{{ angka(p.skor_sesudah) }}</strong>
                        <small>/ {{ angka(p.skor_maksimal) }}</small>
                    </li>
                </ul>
            </li>
        </ol>
    </section>
</template>

<script setup>
import { angka } from '../../utils/statusLembar.js';

defineOptions({ name: 'PaRiwayatKalibrasiPenilai' });

defineProps({
    riwayat: { type: Array, default: () => [] },
});
</script>

<style scoped>
.pa-riwayat-kp { display: grid; gap: var(--hcis-space-2); }
.pa-riwayat-kp h4 { margin: 0; color: var(--hcis-text-strong); }
.pa-riwayat-kp__uraian { margin: 0; color: var(--hcis-text-muted); font-size: var(--hcis-font-size-sm); }
.pa-riwayat-kp__daftar { display: grid; gap: var(--hcis-space-3); margin: 0; padding: 0; list-style: none; }
.pa-riwayat-kp__daftar > li { padding: var(--hcis-space-3); border: 1px solid var(--hcis-border-subtle); border-radius: var(--hcis-radius-lg); background: var(--hcis-surface); }
.pa-riwayat-kp__kepala { display: flex; flex-wrap: wrap; justify-content: space-between; gap: var(--hcis-space-2); }
.pa-riwayat-kp__kepala span { color: var(--hcis-text-muted); font-size: var(--hcis-font-size-sm); }
.pa-riwayat-kp__alasan { margin: var(--hcis-space-1) 0; color: var(--hcis-text); font-style: italic; }
.pa-riwayat-kp ul { margin: 0; padding-left: var(--hcis-space-4); color: var(--hcis-text); }
</style>
```

- [ ] **Langkah 5: Pasang di `PaResultDialog.vue`**

Temukan bagian yang merender `riwayat_kalibrasi` (`grep -n "riwayat_kalibrasi" resources/js/Pages/KPI/PerformanceAppraisal/Cycle/components/PaResultDialog.vue`), impor `PaRiwayatKalibrasiPenilai from './PaRiwayatKalibrasiPenilai.vue'`, dan pasang tepat sesudah bagian itu, memakai objek detail yang sama yang dipakai bagian tersebut:

```vue
<PaRiwayatKalibrasiPenilai :riwayat="<objek detail yang sama>.kalibrasi_penilai || []" />
```

Di layar bagian lama, ganti judul "Riwayat Kalibrasi" menjadi "Riwayat Kalibrasi Forum" (W7) — **teksnya saja**; kelas CSS dan kunci API tidak diubah.

- [ ] **Langkah 6: Commit**

```bash
php scripts/verify-frontend-imports.php
git add resources/js/Pages/KPI/PerformanceAppraisal/MasterData/components/PaPengaturanKategoriPenilai.vue resources/js/Pages/KPI/PerformanceAppraisal/MasterData/components/PaPengaturanKategoriPenilai.test.js resources/js/Pages/KPI/PerformanceAppraisal/MasterData/jenis-penilai.vue resources/js/Pages/KPI/PerformanceAppraisal/Cycle/components/PaRiwayatKalibrasiPenilai.vue resources/js/Pages/KPI/PerformanceAppraisal/Cycle/components/PaRiwayatKalibrasiPenilai.test.js resources/js/Pages/KPI/PerformanceAppraisal/Cycle/components/PaResultDialog.vue
git commit -m "feat(pa): admin mengatur urutan dan Kalibrasi Penilai, riwayatnya tampil di detail hasil"
```

---

### Tugas 14: Verifikasi — HANYA setelah ACC pemilik proyek

**Berkas:** tidak ada yang baru; perbaikan yang ditemukan di sini masuk ke berkas tugasnya masing-masing.

- [ ] **Langkah 1: Minta ACC dan lingkup uji**

Laporkan apa yang sudah ditulis dan apa yang belum pernah dijalankan, lalu tanyakan lingkup: `feature` (berkas uji fitur ini), `impacted` (plus seluruh uji PA backend dan frontend), atau `full`. Jangan lanjut sebelum dijawab.

- [ ] **Langkah 2: Uji backend fitur ini**

```bash
unset DATABASE_URL && php artisan test tests/Feature/KPI/PerformanceAppraisal/SkorEfektifTest.php tests/Feature/KPI/PerformanceAppraisal/KalibrasiPenilaiTest.php tests/Feature/KPI/PerformanceAppraisal/WizardPenilaianTest.php tests/Feature/KPI/PerformanceAppraisal/KalibrasiPenilaiAdminTest.php tests/Feature/KPI/PerformanceAppraisal/SkorEfektifSatuPintuTest.php tests/Feature/KPI/PerformanceAppraisal/CycleGateTest.php tests/Feature/KPI/PerformanceAppraisal/KontrakResponsTest.php
```
Harapan: semua lulus. Satu suite satu waktu — dua suite di DB dev yang sama saling mengunci.

- [ ] **Langkah 3: Buktikan penjaga pemindai pernah merah**

Hapus sementara penanda `// skor-mentah: ...` di `jenisTerjawabTanpaAngka()`, jalankan `php artisan test tests/Feature/KPI/PerformanceAppraisal/SkorEfektifSatuPintuTest.php`, harapkan **GAGAL** dengan menyebut `PaResultCalculationService.php`. Kembalikan penandanya dan pastikan lulus lagi (`git diff` bersih untuk berkas itu).

- [ ] **Langkah 4: Uji frontend**

```bash
npx vitest run resources/js/Pages/KPI/PerformanceAppraisal
```
Harapan: seluruh uji baru lulus, dan sebelas kegagalan lama (`distribusi`, `hav-map`, `PaResultDialog` ×8, `panels`) tetap berjumlah sama — tidak bertambah. Bila `PaResultDialog.test.js` berubah jumlah gagalnya, periksa apakah penyebabnya pemasangan di Tugas 13 Langkah 5.

- [ ] **Langkah 5: Lingkup `impacted` / `full` bila diminta**

`impacted`: `php artisan test tests/Feature/KPI/PerformanceAppraisal` (satu jalan). `full`: sesuai `docs/architecture/testing-and-quality-standard.md`.

- [ ] **Langkah 6: Bukti end-to-end di data dev**

Pada siklus contoh berjalan (`pa:seed-presentasi`), nyalakan Kalibrasi Penilai untuk ATASAN lewat layar master, kalibrasi satu lembar `TERKUMPUL` lewat wizard, lepas penilainya (`kirim-semua` atau lepas paksa), hitung peserta itu, lalu:

```bash
unset DATABASE_URL && php artisan pa:verify <kode siklus>
```
Harapan: tidak ada selisih hasil untuk peserta tersebut. Kembalikan penanda kalibrasi ke keadaan semula bila pemilik proyek memintanya.

- [ ] **Langkah 7: Verifikasi browser lewat vhost Laragon**

Buka `/performance-appraisal/saya` lewat vhost (bukan `artisan serve`) sebagai penilai yang punya lembar di beberapa kategori. Periksa di lebar desktop dan ponsel: urutan langkah, langkah terkunci menyebut sebabnya dan tanpa kode akses, transisi ke pratinjau setelah lembar terakhir terisi, matriks tanpa gulir menyamping di tingkat halaman, kartu per orang di ponsel, dialog alasan layar penuh di ponsel dengan dua tombol 50/50, riwayat di detail hasil admin, dan panel pengaturan di layar master.

- [ ] **Langkah 8: Pint dan penjaga CI lokal**

```bash
vendor/bin/pint --test $(git diff --name-only master...HEAD -- '*.php')
node .github/scripts/gerbang-rilis.mjs --target staging --base origin/staging --head HEAD
node .github/scripts/penjaga-perubahan-db.mjs --base origin/staging --head HEAD
```

---

### Tugas 15: Dokumentasi dan papan kerja

**Berkas:**
- Ubah: `docs/superpowers/specs/2026-09-18-pa-wizard-kalibrasi-penilai-design.md`
- Ubah: `plans/kpi/pa-papan-kerja.md`

- [ ] **Langkah 1: Koreksi spec sesuai temuan penelusuran**

Tambahkan bagian "Revisi r2 — 18 September 2026" di akhir spec: (a) penulisan penanda kalibrasi dan urutan hanya lewat `PaJenisPenilaiMatriksService` (layar `jenis-penilai.vue`), bukan `PaMasterWriteService`, karena form kategori di `master.vue` tidak pernah tampil lewat alamat `kategori-penilai`; (b) dua kode galat tambahan `PA_KOMPETENSI_TIDAK_DI_LEMBAR` dan `PA_LANGKAH_TIDAK_DIKENAL`; (c) `POST /kalibrasi` mengembalikan pratinjau kategori utuh; (d) perhitungan hanya punya SATU titik baca skor (bukan dua); (e) `PaVerifyCommand` menerapkan kalibrasi secara independen; (f) `urutanKategori()` di panel progres kini membaca `Urutan_Pengisian`; (g) `PaApiError` kini membawa `error_code`/`request_id`, yang sebelumnya tidak pernah diisi.

- [ ] **Langkah 2: Catat di papan kerja**

Tambahkan entri bertanggal di awal bagian 7 `plans/kpi/pa-papan-kerja.md`: fitur selesai, change DDL-2026-119/DML-2026-124 berstatus `TRACKER_PENDING`, dan temuan `PaApiError` yang membuat kode galat PA tidak pernah sampai ke layar sebelum fitur ini.

- [ ] **Langkah 3: Commit**

```bash
git add docs/superpowers/specs/2026-09-18-pa-wizard-kalibrasi-penilai-design.md plans/kpi/pa-papan-kerja.md
git commit -m "docs(pa): revisi spec wizard dan catatan papan kerja"
```

---

## Tinjauan mandiri rencana

**Cakupan spec:** §4.1–4.3 → Tugas 1; §4.4 → Tugas 3–4; §5.1–5.2 → Tugas 2, 5; §5.3 → Tugas 7; §5.4 → Tugas 3–4; §6 → Tugas 7; §6.1 → Tugas 8–9, 13; §7 → Tugas 10–12; §8 → uji di tiap tugas + Tugas 14; §9 → Tugas 1; §10 dihormati (tidak ada tugas yang menyentuhnya); §11 dicatat di spec dan papan kerja.

**Batas yang disengaja:** kunci urutan tidak mengikat HC Learn (spec §5.3); bukti `pa:verify` diambil end-to-end, bukan uji unit (Tugas 4 Langkah 5); keadaan "pratinjau sudah dilihat" tidak disimpan server — pratinjau disajikan oleh alur layar di tiap transisi, dan langkah berikutnya terbuka menurut status lembar.
