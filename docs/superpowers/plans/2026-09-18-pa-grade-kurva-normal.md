# Grade PA dari Kurva Normal — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. **Pemilik proyek meminta TANPA subagent** — kerjakan inline (executing-plans).

**Goal:** Grade sistem PA lahir dari z-score per grup level yang dikunci per siklus, bukan dari pita nilai tetap.

**Architecture:** Master pita grade mendapat `Batas_Z`; master baru Grup Level Kurva; keduanya disalin per siklus. Saat admin "Kunci kurva", `PaKurvaService` menghitung μ/σ sampel per grup dari hasil `DIHITUNG`, menurunkan pita absolut lewat `PaKurvaStatistik` (murni, tanpa DB), membekukannya di `N_PA_Cycle_Curve(_Band)`, dan memberi grade secara set-based. Perhitungan siklus `KURVA` membaca pita beku grup peserta; siklus `PITA` tidak berubah.

**Tech Stack:** Laravel 10 + SQL Server (DDL manual lewat SQL Tracker), Inertia + Vue 3 `<script setup>`, PHPUnit (Unit + Feature `DatabaseTransactions` di dev DB), Vitest.

**Spec:** `docs/superpowers/specs/2026-09-18-pa-grade-kurva-normal-design.md`

## Global Constraints

- Keputusan G1–G8 spec tidak ditawar ulang. Isi awal z: **A +2 · B_PLUS +1 · B 0 · C_PLUS −1 · C −2 · D NULL**.
- σ = simpangan baku **sampel** (n−1), setara `STDEV` Excel. Populasi = hasil `DIHITUNG` peserta `DIIKUTKAN`.
- Nilai akhir di atas 100 dipakai apa adanya. Batas atas pita **eksklusif** (DML-2026-079). Pita grade teratas terbuka sampai 999.99; terendah mulai 0.
- Ambang grup kecil = 30 (`PaDistribusiService::MIN_UNTUK_KURVA`) → **peringatan**, tidak memblok.
- Buka kunci wajib alasan (5–500 karakter) dan **ditolak** bila sudah ada hasil `TERBIT` di siklus.
- Siklus `PITA` tidak boleh berubah perilakunya sedikit pun.
- **Penyimpangan sadar dari spec §4.2:** `Metode_Grade` ber-DEFAULT `'PITA'` (siklus lama otomatis PITA tanpa DML, tanpa jeda berbahaya antara DDL dan DML); siklus BARU diset `'KURVA'` oleh `PaCycleSetupService`. DML-2026-125 karena itu hanya mengisi `Batas_Z` dan mendaftarkan menu.
- AGENTS.md: tanpa migration; agen tidak menjalankan DROP/TRUNCATE; verifikasi `SELECT DB_NAME()` = `Web_HRIS` sebelum DDL/DML; `unset DATABASE_URL` sebelum artisan; `git add` per berkas; jangan jalankan uji/build sebelum ACC pemilik proyek (Tugas 12); commit diakhiri `Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>`.
- Frontend: token lapis peran (`--hcis-surface*`, `--hcis-text*`, `--hcis-border*`, `--hcis-space-*`, `--hcis-radius-*`), `HcisDialog` (sudah menangani fokus dan kunci gulir), `v-html` hanya lewat `htmlAman()`.
- Kontrak kit (terverifikasi 18 Sep 2026): `HcisButton` prop `label`/`icon`/`trailing-icon`/`variant` (`primary|secondary|danger|...`); `HcisTextarea` wajib `id` dan `label`; `HcisSelect` merender opsi setelah dibuka; `HcisTabs` pakai `key`.

---

## Peta berkas

| Berkas | Tugas | Tanggung jawab |
|---|---|---|
| `database/ddl/DDL-2026-120-pa-grade-kurva/forward.sql`, `drop.sql` | 1 | Kolom & tabel kurva |
| `database/dml/DML-2026-125-batas-z-grade-dan-menu-grup-kurva/data-change.sql` | 1 | Isi `Batas_Z`, menu halaman grup kurva |
| `docs/changes/DDL-2026-120-pa-grade-kurva/README.md`, `database/ddl/status.json`, `docs/database/tables/kpi/*.md` | 1 | Jejak perubahan |
| `app/Services/KPI/PerformanceAppraisal/Kurva/PaKurvaStatistik.php` | 2 | μ/σ, pita dari z, grade dari pita — murni |
| `tests/Unit/KPI/PerformanceAppraisal/PaKurvaStatistikTest.php` | 2 | Reproduksi Excel & G8 |
| `app/Services/KPI/PerformanceAppraisal/PaMasterWriteService.php`, `PaMasterReadService.php` | 3 | `Batas_Z` pada master pita |
| `app/Services/KPI/PerformanceAppraisal/Kurva/PaGrupKurvaMasterService.php` | 3 | Master grup level kurva |
| `app/Services/KPI/PerformanceAppraisal/PaCyclePreparationService.php`, `PaCycleSetupService.php` | 4 | Salin `Batas_Z` & grup ke siklus; siklus baru `KURVA` |
| `app/Services/KPI/PerformanceAppraisal/Kurva/PaKurvaService.php` | 5 | Pratinjau, kunci, buka, pindah metode, grup siklus, pita beku |
| `app/Services/KPI/PerformanceAppraisal/PaCycleGate.php` | 5 | `KUNCI_KURVA`, `BUKA_KURVA` |
| `app/Services/KPI/PerformanceAppraisal/PaResultCalculationService.php`, `PaCalibrationService.php`, `PaResultPublishService.php` | 6 | Grade dari pita beku; gerbang kalibrasi/terbit |
| `app/Console/Commands/KPI/PerformanceAppraisal/PaVerifyCommand.php` | 7 | Pemeriksaan kurva |
| Controller, rute, fixture, kontrak API | 8 | HTTP |
| `resources/js/.../api/paApi.js` | 9 | Seam frontend |
| `resources/js/.../Hasil/components/PaKurvaPanel.vue`, `MasterData/grup-kurva.vue`, editor pita | 10–11 | Layar |

---

### Tugas 1: Artefak database dan eksekusi di development

**Berkas:**
- Buat: `database/ddl/DDL-2026-120-pa-grade-kurva/forward.sql`, `drop.sql`
- Buat: `database/dml/DML-2026-125-batas-z-grade-dan-menu-grup-kurva/data-change.sql`
- Buat: `docs/changes/DDL-2026-120-pa-grade-kurva/README.md`
- Buat: `docs/database/tables/kpi/n-pa-curve-group.md`, `n-pa-cycle-curve.md`
- Ubah: `database/ddl/status.json` (tambah dua entri di akhir daftar)

**Interfaces:**
- Produces: kolom `N_PA_Score_Range.Batas_Z decimal(5,2) NULL`, `N_PA_Cycle_Score_Range_Snapshot.Batas_Z decimal(5,2) NULL`, `N_PA_Cycle.Metode_Grade char(5) NOT NULL DEFAULT 'PITA'` (CHECK `PITA|KURVA`), `N_PA_Result.PA_Cycle_Curve_Id int NULL`; tabel `N_PA_Curve_Group`, `N_PA_Curve_Group_Level`, `N_PA_Cycle_Curve_Group`, `N_PA_Cycle_Curve_Group_Level`, `N_PA_Cycle_Curve`, `N_PA_Cycle_Curve_Band`; halaman `PaMasterGrupKurvaPage` di `/performance-appraisal/master/grup-kurva`.

- [ ] **Langkah 1: Tulis `forward.sql`**

```sql
/* Change ID: DDL-2026-120-pa-grade-kurva
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-064 (N_PA_Score_Range, N_PA_Cycle), DDL-2026-078 (N_PA_Result),
                 HRIS_Level (PK_Level).
   Scope: grade PA dari z-score per grup level, dikunci per siklus.
   Rancangan: docs/superpowers/specs/2026-09-18-pa-grade-kurva-normal-design.md
*/

/* 1. Batas bawah z per grade (NULL = grade terendah / belum diatur). */
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Score_Range') AND name = 'Batas_Z')
    ALTER TABLE N_PA_Score_Range ADD Batas_Z decimal(5,2) NULL;
GO
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Cycle_Score_Range_Snapshot') AND name = 'Batas_Z')
    ALTER TABLE N_PA_Cycle_Score_Range_Snapshot ADD Batas_Z decimal(5,2) NULL;
GO

/* 2. Metode grade per siklus. DEFAULT 'PITA' mengisi siklus yang sudah ada tanpa DML;
      siklus baru diset 'KURVA' oleh aplikasi (PaCycleSetupService). */
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Cycle') AND name = 'Metode_Grade')
    ALTER TABLE N_PA_Cycle ADD Metode_Grade char(5) NOT NULL
        CONSTRAINT DF_PA_Cycle_Metode_Grade DEFAULT ('PITA');
GO
IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_Cycle_Metode_Grade')
    ALTER TABLE N_PA_Cycle WITH CHECK ADD CONSTRAINT CK_PA_Cycle_Metode_Grade
        CHECK (Metode_Grade IN ('PITA', 'KURVA'));
GO

/* 3. Master grup level kurva. */
IF OBJECT_ID('N_PA_Curve_Group', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Curve_Group (
        Id_PA_Curve_Group int IDENTITY(1,1) NOT NULL CONSTRAINT PK_PA_Curve_Group PRIMARY KEY,
        Kode_Grup varchar(40) NOT NULL,
        Nama_Grup nvarchar(200) NOT NULL,
        Urutan_Tampil smallint NOT NULL CONSTRAINT DF_PA_Curve_Group_Urutan DEFAULT (1),
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_PA_Curve_Group_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL CONSTRAINT DF_PA_Curve_Group_Created DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Curve_Group') AND name = 'UX_PA_Curve_Group_Kode')
    CREATE UNIQUE INDEX UX_PA_Curve_Group_Kode ON N_PA_Curve_Group (Kode_Grup) WHERE Flag_Aktif = 'Y';
GO
IF OBJECT_ID('N_PA_Curve_Group_Level', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Curve_Group_Level (
        Id_PA_Curve_Group_Level int IDENTITY(1,1) NOT NULL CONSTRAINT PK_PA_Curve_Group_Level PRIMARY KEY,
        PA_Curve_Group_Id int NOT NULL
            CONSTRAINT FK_PA_Curve_Group_Level_Grup REFERENCES N_PA_Curve_Group (Id_PA_Curve_Group),
        ID_Level bigint NOT NULL
            CONSTRAINT FK_PA_Curve_Group_Level_Level REFERENCES HRIS_Level (ID_Level),
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_PA_Curve_Group_Level_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL CONSTRAINT DF_PA_Curve_Group_Level_Created DEFAULT (sysdatetime()),
        Created_By int NULL
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Curve_Group_Level') AND name = 'UX_PA_Curve_Group_Level_Level')
    CREATE UNIQUE INDEX UX_PA_Curve_Group_Level_Level ON N_PA_Curve_Group_Level (ID_Level) WHERE Flag_Aktif = 'Y';
GO

/* 4. Salinan grup per siklus. */
IF OBJECT_ID('N_PA_Cycle_Curve_Group', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Curve_Group (
        Id_PA_Cycle_Curve_Group int IDENTITY(1,1) NOT NULL CONSTRAINT PK_PA_Cycle_Curve_Group PRIMARY KEY,
        PA_Cycle_Id int NOT NULL
            CONSTRAINT FK_PA_Cycle_Curve_Group_Cycle REFERENCES N_PA_Cycle (Id_PA_Cycle),
        PA_Curve_Group_Id int NULL
            CONSTRAINT FK_PA_Cycle_Curve_Group_Sumber REFERENCES N_PA_Curve_Group (Id_PA_Curve_Group),
        Kode_Grup varchar(40) NOT NULL,
        Nama_Grup nvarchar(200) NOT NULL,
        Urutan_Tampil smallint NOT NULL CONSTRAINT DF_PA_Cycle_Curve_Group_Urutan DEFAULT (1),
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_PA_Cycle_Curve_Group_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL CONSTRAINT DF_PA_Cycle_Curve_Group_Created DEFAULT (sysdatetime()),
        Created_By int NULL
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Curve_Group') AND name = 'UX_PA_Cycle_Curve_Group_Kode')
    CREATE UNIQUE INDEX UX_PA_Cycle_Curve_Group_Kode ON N_PA_Cycle_Curve_Group (PA_Cycle_Id, Kode_Grup) WHERE Flag_Aktif = 'Y';
GO
IF OBJECT_ID('N_PA_Cycle_Curve_Group_Level', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Curve_Group_Level (
        Id_PA_Cycle_Curve_Group_Level int IDENTITY(1,1) NOT NULL CONSTRAINT PK_PA_Cycle_Curve_Group_Level PRIMARY KEY,
        PA_Cycle_Curve_Group_Id int NOT NULL
            CONSTRAINT FK_PA_Cycle_Curve_Group_Level_Grup REFERENCES N_PA_Cycle_Curve_Group (Id_PA_Cycle_Curve_Group),
        PA_Cycle_Id int NOT NULL
            CONSTRAINT FK_PA_Cycle_Curve_Group_Level_Cycle REFERENCES N_PA_Cycle (Id_PA_Cycle),
        ID_Level bigint NOT NULL
            CONSTRAINT FK_PA_Cycle_Curve_Group_Level_Level REFERENCES HRIS_Level (ID_Level),
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_PA_Cycle_Curve_Group_Level_Aktif DEFAULT ('Y')
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Curve_Group_Level') AND name = 'UX_PA_Cycle_Curve_Group_Level_Level')
    CREATE UNIQUE INDEX UX_PA_Cycle_Curve_Group_Level_Level ON N_PA_Cycle_Curve_Group_Level (PA_Cycle_Id, ID_Level) WHERE Flag_Aktif = 'Y';
GO

/* 5. Hasil kunci: satu baris per grup per penguncian, hanya ditambah dan dinonaktifkan. */
IF OBJECT_ID('N_PA_Cycle_Curve', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Curve (
        Id_PA_Cycle_Curve int IDENTITY(1,1) NOT NULL CONSTRAINT PK_PA_Cycle_Curve PRIMARY KEY,
        PA_Cycle_Id int NOT NULL
            CONSTRAINT FK_PA_Cycle_Curve_Cycle REFERENCES N_PA_Cycle (Id_PA_Cycle),
        PA_Cycle_Curve_Group_Id int NOT NULL
            CONSTRAINT FK_PA_Cycle_Curve_Grup REFERENCES N_PA_Cycle_Curve_Group (Id_PA_Cycle_Curve_Group),
        Jumlah_Peserta int NOT NULL,
        Rata_Rata decimal(9,4) NULL,
        Simpangan_Baku decimal(9,4) NULL,
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_PA_Cycle_Curve_Aktif DEFAULT ('Y'),
        Locked_At datetime2(0) NOT NULL CONSTRAINT DF_PA_Cycle_Curve_Locked DEFAULT (sysdatetime()),
        Locked_By int NULL,
        Unlocked_At datetime2(0) NULL,
        Unlocked_By int NULL,
        Alasan_Buka nvarchar(500) NULL,
        CONSTRAINT CK_PA_Cycle_Curve_Jumlah CHECK (Jumlah_Peserta >= 0),
        CONSTRAINT CK_PA_Cycle_Curve_Sd CHECK (Simpangan_Baku IS NULL OR Simpangan_Baku >= 0),
        CONSTRAINT CK_PA_Cycle_Curve_Buka CHECK (Flag_Aktif = 'Y' OR (Unlocked_At IS NOT NULL AND Alasan_Buka IS NOT NULL))
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Curve') AND name = 'UX_PA_Cycle_Curve_Grup_Aktif')
    CREATE UNIQUE INDEX UX_PA_Cycle_Curve_Grup_Aktif ON N_PA_Cycle_Curve (PA_Cycle_Curve_Group_Id) WHERE Flag_Aktif = 'Y';
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Curve') AND name = 'IX_PA_Cycle_Curve_Cycle')
    CREATE INDEX IX_PA_Cycle_Curve_Cycle ON N_PA_Cycle_Curve (PA_Cycle_Id, Flag_Aktif);
GO
IF OBJECT_ID('N_PA_Cycle_Curve_Band', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Curve_Band (
        Id_PA_Cycle_Curve_Band int IDENTITY(1,1) NOT NULL CONSTRAINT PK_PA_Cycle_Curve_Band PRIMARY KEY,
        PA_Cycle_Curve_Id int NOT NULL
            CONSTRAINT FK_PA_Cycle_Curve_Band_Curve REFERENCES N_PA_Cycle_Curve (Id_PA_Cycle_Curve),
        Kode_Range varchar(40) NOT NULL,
        Nama_Range nvarchar(200) NOT NULL,
        Batas_Z decimal(5,2) NULL,
        Nilai_Dari decimal(9,4) NOT NULL,
        Nilai_Sampai decimal(9,4) NOT NULL,
        Urutan_Tampil smallint NOT NULL,
        CONSTRAINT CK_PA_Cycle_Curve_Band_Rentang CHECK (Nilai_Sampai >= Nilai_Dari)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Curve_Band') AND name = 'UX_PA_Cycle_Curve_Band_Kode')
    CREATE UNIQUE INDEX UX_PA_Cycle_Curve_Band_Kode ON N_PA_Cycle_Curve_Band (PA_Cycle_Curve_Id, Kode_Range);
GO

/* 6. Kurva yang memberi grade sebuah hasil. */
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Result') AND name = 'PA_Cycle_Curve_Id')
    ALTER TABLE N_PA_Result ADD PA_Cycle_Curve_Id int NULL
        CONSTRAINT FK_PA_Result_Cycle_Curve REFERENCES N_PA_Cycle_Curve (Id_PA_Cycle_Curve);
GO

/* 7. Pendaftaran perubahan. */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-120')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification,
        Catatan, Created_By
    )
    VALUES (
        'DDL-2026-120', 'DDL', 'Grade Performance Appraisal dari kurva normal per grup level',
        'Grade sistem PA ditentukan dari z-score per grup level: master pita grade menyimpan Batas_Z, master N_PA_Curve_Group mengelompokkan level, keduanya disalin per siklus; kunci kurva membekukan rata-rata, simpangan baku, dan pita absolut di N_PA_Cycle_Curve(_Band). Siklus lama tetap Metode_Grade PITA.',
        'database/ddl/DDL-2026-120-pa-grade-kurva/forward.sql',
        'docs/changes/DDL-2026-120-pa-grade-kurva/README.md',
        'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING',
        'Metode_Grade DEFAULT PITA (siklus lama); siklus baru diset KURVA oleh aplikasi. Kurva hanya ditambah dan dinonaktifkan.',
        NULL
    );
END;
GO
```

- [ ] **Langkah 2: Tulis `drop.sql`** (tidak dijalankan agen)

```sql
/* Change ID: DDL-2026-120-pa-grade-kurva — PEMBATALAN. TIDAK DIJALANKAN AGEN.
   PERHATIAN: membuang seluruh jejak kurva; grade hasil yang sudah diberi dari kurva tetap
   tersimpan, tetapi asal angkanya tidak lagi bisa dijelaskan. */
IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Result_Cycle_Curve')
    ALTER TABLE N_PA_Result DROP CONSTRAINT FK_PA_Result_Cycle_Curve;
GO
IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Result') AND name = 'PA_Cycle_Curve_Id')
    ALTER TABLE N_PA_Result DROP COLUMN PA_Cycle_Curve_Id;
GO
IF OBJECT_ID('N_PA_Cycle_Curve_Band', 'U') IS NOT NULL DROP TABLE N_PA_Cycle_Curve_Band;
GO
IF OBJECT_ID('N_PA_Cycle_Curve', 'U') IS NOT NULL DROP TABLE N_PA_Cycle_Curve;
GO
IF OBJECT_ID('N_PA_Cycle_Curve_Group_Level', 'U') IS NOT NULL DROP TABLE N_PA_Cycle_Curve_Group_Level;
GO
IF OBJECT_ID('N_PA_Cycle_Curve_Group', 'U') IS NOT NULL DROP TABLE N_PA_Cycle_Curve_Group;
GO
IF OBJECT_ID('N_PA_Curve_Group_Level', 'U') IS NOT NULL DROP TABLE N_PA_Curve_Group_Level;
GO
IF OBJECT_ID('N_PA_Curve_Group', 'U') IS NOT NULL DROP TABLE N_PA_Curve_Group;
GO
IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_Cycle_Metode_Grade')
    ALTER TABLE N_PA_Cycle DROP CONSTRAINT CK_PA_Cycle_Metode_Grade;
GO
IF EXISTS (SELECT 1 FROM sys.default_constraints WHERE name = 'DF_PA_Cycle_Metode_Grade')
    ALTER TABLE N_PA_Cycle DROP CONSTRAINT DF_PA_Cycle_Metode_Grade;
GO
IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Cycle') AND name = 'Metode_Grade')
    ALTER TABLE N_PA_Cycle DROP COLUMN Metode_Grade;
GO
IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Cycle_Score_Range_Snapshot') AND name = 'Batas_Z')
    ALTER TABLE N_PA_Cycle_Score_Range_Snapshot DROP COLUMN Batas_Z;
GO
IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Score_Range') AND name = 'Batas_Z')
    ALTER TABLE N_PA_Score_Range DROP COLUMN Batas_Z;
GO
DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-120';
GO
```

- [ ] **Langkah 3: Tulis DML-2026-125** — `data-change.sql`

```sql
/* Change ID: DML-2026-125-batas-z-grade-dan-menu-grup-kurva
   Target: Web_HRIS development; production only through SQL Tracker. Dependencies: DDL-2026-120.
   Scope: (1) Batas_Z enam grade master, hanya bila belum diisi; (2) halaman master Grup Level
   Kurva di menu Master Data PA — N_HRIS_Menus, N_HRIS_Grup_Fitur_Map (grup KPI),
   HRIS_Page_Access untuk dua user pengembang (pola DML-2026-085).
   First-run maximum scope: UPDATE N_PA_Score_Range <= 6, N_HRIS_Menus <= 1,
   N_HRIS_Grup_Fitur_Map <= 1, HRIS_Page_Access <= 2. Aman diulang.
   Sesudah dijalankan: panggil flushMenuCache (lihat README). */
SET XACT_ABORT ON;
BEGIN TRANSACTION;

UPDATE r
SET r.Batas_Z = v.Batas_Z, r.Updated_At = SYSDATETIME()
FROM N_PA_Score_Range r
JOIN (VALUES ('A', 2.00), ('B_PLUS', 1.00), ('B', 0.00), ('C_PLUS', -1.00), ('C', -2.00)) AS v(Kode_Range, Batas_Z)
  ON v.Kode_Range = r.Kode_Range
WHERE r.Score_Type = 'FINAL' AND r.Score_Usage = 'GRADE' AND r.Flag_Aktif = 'Y' AND r.Batas_Z IS NULL;

INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu,
     Header_Code, Header_Label, Header_Icon, Sort_Header,
     Is_Global_Menu, Target, Is_Show_Sidebar, Sort_Menu)
SELECT 'PaMasterGrupKurvaPage', '001', N'Grup Level Kurva', 'bi-diagram-2',
       '/performance-appraisal/master/grup-kurva',
       'pa_master', N'Master Data', 'bi bi-clipboard2-data', 14, 'T', 'self', 'Y', 5
WHERE NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PaMasterGrupKurvaPage');

INSERT INTO N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
SELECT g.Id_Grup_Fitur, 'PaMasterGrupKurvaPage', 'T', 'Y', 'Y'
FROM N_HRIS_Master_Grup_Fitur g
WHERE g.Kode_Grup_Fitur = 'KPI'
  AND NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map x WHERE x.Jenis_page = 'PaMasterGrupKurvaPage');

INSERT INTO HRIS_Page_Access (Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Flag_Aktif, Created_At)
SELECT b.Kode_Perusahaan, b.ID_Level, b.ID_Divisi, 'PaMasterGrupKurvaPage', b.UserID_Web, 'Y', SYSDATETIME()
FROM HRIS_Page_Access b
WHERE b.UserID_Web IN (4, 622)
  AND b.Id_Page_Access = (SELECT MIN(c.Id_Page_Access) FROM HRIS_Page_Access c WHERE c.UserID_Web = b.UserID_Web)
  AND NOT EXISTS (SELECT 1 FROM HRIS_Page_Access d WHERE d.UserID_Web = b.UserID_Web AND d.Jenis_page = 'PaMasterGrupKurvaPage');

COMMIT TRANSACTION;
GO
```

Sebelum menulis, jalankan `SELECT Header_Code, Header_Label, Header_Icon, Sort_Header, MAX(Sort_Menu) FROM N_HRIS_Menus WHERE Header_Code = 'pa_master' GROUP BY Header_Code, Header_Label, Header_Icon, Sort_Header` dan sesuaikan `Header_Icon`/`Sort_Header`/`Sort_Menu` di atas dengan hasilnya (DML-2026-093 memindahkan grup `pa_master` ke Sort_Header 14 dengan `@ikon`).

- [ ] **Langkah 4: Jalankan di development** — verifikasi `SELECT DB_NAME(), @@SERVERNAME` = `Web_HRIS @ instance-20240809-031237`; jalankan `forward.sql` per batch `GO` dua kali (putaran kedua harus no-op: jumlah tabel `N_PA_*Curve*` = 6, ledger 1); jalankan DML dua kali (putaran kedua 0 baris); `SELECT Kode_Range, Batas_Z FROM N_PA_Score_Range WHERE Score_Type='FINAL' AND Score_Usage='GRADE' AND Flag_Aktif='Y'` → A 2, B_PLUS 1, B 0, C_PLUS −1, C −2, D NULL; `SELECT COUNT(*) FROM N_PA_Cycle WHERE Metode_Grade <> 'PITA'` → 0. Lalu `php artisan tinker --execute="app(\App\Services\SYSTEM\Shell\ShellService::class)->flushMenuCache();"` (cari nama service pembersih cache menu dengan `grep -rn "function flushMenuCache" app` dan pakai kelas itu).

- [ ] **Langkah 5: Dokumen dan ledger** — README change (metadata, deskripsi, keputusan fisik termasuk penyimpangan DEFAULT PITA, verifikasi development dengan angka sebelum/sesudah, sisa pekerjaan user), dua dokumen tabel, dua entri `status.json` (`artifact_revision` r1, checksum `sha256sum` forward & data-change, `TRACKER_PENDING`, blok `execution`). Hanya menambah entri; entri lain tidak disentuh.

- [ ] **Langkah 6: Commit**

```bash
git add database/ddl/DDL-2026-120-pa-grade-kurva/forward.sql database/ddl/DDL-2026-120-pa-grade-kurva/drop.sql database/dml/DML-2026-125-batas-z-grade-dan-menu-grup-kurva/data-change.sql docs/changes/DDL-2026-120-pa-grade-kurva/README.md docs/database/tables/kpi/n-pa-curve-group.md docs/database/tables/kpi/n-pa-cycle-curve.md database/ddl/status.json
git commit -m "feat(pa): DDL-2026-120 dan DML-2026-125 grade kurva normal"
```

---

### Tugas 2: `PaKurvaStatistik` — matematika murni

**Berkas:**
- Buat: `app/Services/KPI/PerformanceAppraisal/Kurva/PaKurvaStatistik.php`
- Uji: `tests/Unit/KPI/PerformanceAppraisal/PaKurvaStatistikTest.php`

**Interfaces:**
- Produces:
  - `PaKurvaStatistik::ringkas(list<float> $nilai): array{n:int, rata:?float, sd:?float}` — `sd` sampel; NULL bila n < 2.
  - `PaKurvaStatistik::pita(list<array{kode:string, nama:string, batas_z:?float, urutan:int}> $grade, ?float $rata, ?float $sd): list<array{kode:string, nama:string, batas_z:?float, nilai_dari:float, nilai_sampai:float, urutan:int}>` — `$grade` dalam urutan apa pun; hasil urut z turun; batas 4 desimal; degenerate (rata/sd NULL atau sd = 0) → grade yang rentang z-nya memuat 0 mendapat [0, 999.99), lainnya selebar nol.
  - `PaKurvaStatistik::gradeUntuk(list<array> $pita, float $nilai): ?string` — batas bawah inklusif, atas eksklusif.
  - `PaKurvaStatistik::pastikanBatasZ(list<array{kode:string, batas_z:?float}> $grade): void` — `RuntimeException` bila bukan tepat satu NULL atau z tidak unik.
  - Konstanta `ATAS = 999.99`.

- [ ] **Langkah 1: Tulis uji**

```php
<?php

namespace Tests\Unit\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaStatistik;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Reproduksi `Dummy Data_ Normal Curve.xlsx` milik HC (47 orang). Kalau angka di sini
 * berubah, grade seluruh siklus berubah — ujinya harus dibaca sebagai kontrak dengan HC.
 */
class PaKurvaStatistikTest extends TestCase
{
    private const NILAI_EXCEL = [
        76.6675, 70.5225, 61.59, 62.0875, 56.1375, 54.62, 59.3175, 81.97, 57.1975, 56.44, 86.7375,
        56.0, 53.41, 56.25, 97.4875, 59.653, 56.2875, 82.855, 87.295, 84.6525, 56.44, 69.1425,
        55.8325, 93.75, 56.2875, 88.0725, 83.7325, 91.41, 115.4175, 87.5925, 56.25, 94.501,
        81.8625, 57.045, 59.1675, 57.045, 76.1825, 53.41, 62.3475, 99.0325, 52.6525, 61.1375,
        90.145, 57.045, 104.82, 55.2275, 88.32,
    ];

    private function grade(array $z): array
    {
        $kode = array_keys($z);

        return array_map(fn ($k, $i) => ['kode' => $k, 'nama' => $k, 'batas_z' => $z[$k], 'urutan' => $i + 1], $kode, array_keys($kode));
    }

    private function sebaran(array $pita): array
    {
        $hitung = array_fill_keys(array_column($pita, 'kode'), 0);

        foreach (self::NILAI_EXCEL as $v) {
            $hitung[PaKurvaStatistik::gradeUntuk($pita, $v)]++;
        }

        return $hitung;
    }

    public function test_rata_dan_simpangan_baku_sampel_sama_dengan_excel(): void
    {
        $r = PaKurvaStatistik::ringkas(self::NILAI_EXCEL);

        $this->assertSame(47, $r['n']);
        $this->assertEqualsWithDelta(71.5116, $r['rata'], 0.0001);
        $this->assertEqualsWithDelta(17.1790, $r['sd'], 0.0001);
    }

    public function test_empat_grade_excel_direproduksi_persis(): void
    {
        $r = PaKurvaStatistik::ringkas(self::NILAI_EXCEL);
        $pita = PaKurvaStatistik::pita($this->grade(['A' => 1.0, 'B+' => 0.0, 'B-' => -1.0, 'C' => null]), $r['rata'], $r['sd']);

        $this->assertSame(['A' => 8, 'B+' => 12, 'B-' => 24, 'C' => 3], $this->sebaran($pita));
    }

    public function test_enam_grade_selebar_satu_sd_keputusan_g8(): void
    {
        $r = PaKurvaStatistik::ringkas(self::NILAI_EXCEL);
        $pita = PaKurvaStatistik::pita(
            $this->grade(['A' => 2.0, 'B_PLUS' => 1.0, 'B' => 0.0, 'C_PLUS' => -1.0, 'C' => -2.0, 'D' => null]),
            $r['rata'], $r['sd'],
        );

        $this->assertSame(['A' => 1, 'B_PLUS' => 7, 'B' => 12, 'C_PLUS' => 24, 'C' => 3, 'D' => 0], $this->sebaran($pita));
        $this->assertEqualsWithDelta(71.5116, $pita[2]['nilai_dari'], 0.0001, 'μ adalah batas bawah B.');
        $this->assertSame(PaKurvaStatistik::ATAS, $pita[0]['nilai_sampai']);
        $this->assertSame(0.0, $pita[5]['nilai_dari']);
        $this->assertSame($pita[0]['nilai_dari'], $pita[1]['nilai_sampai'], 'Pita harus bersambung tanpa lubang.');
    }

    public function test_urutan_masukan_tidak_berpengaruh(): void
    {
        $r = PaKurvaStatistik::ringkas(self::NILAI_EXCEL);
        $acak = $this->grade(['C' => null, 'A' => 1.0, 'B-' => -1.0, 'B+' => 0.0]);

        $this->assertSame(['A', 'B+', 'B-', 'C'], array_column(PaKurvaStatistik::pita($acak, $r['rata'], $r['sd']), 'kode'));
    }

    public function test_sebaran_datar_semua_ke_grade_yang_memuat_z_nol(): void
    {
        $pita = PaKurvaStatistik::pita($this->grade(['A' => 1.0, 'B' => 0.0, 'C' => null]), 80.0, 0.0);

        $this->assertSame('B', PaKurvaStatistik::gradeUntuk($pita, 80.0));
        $this->assertSame('B', PaKurvaStatistik::gradeUntuk($pita, 120.0));

        $satu = PaKurvaStatistik::ringkas([75.0]);
        $this->assertNull($satu['sd']);
        $this->assertSame('B', PaKurvaStatistik::gradeUntuk(PaKurvaStatistik::pita($this->grade(['A' => 1.0, 'B' => 0.0, 'C' => null]), $satu['rata'], $satu['sd']), 75.0));
    }

    public function test_batas_z_harus_tepat_satu_kosong_dan_unik(): void
    {
        foreach ([['A' => 1.0, 'B' => 0.0], ['A' => null, 'B' => null], ['A' => 1.0, 'B' => 1.0, 'C' => null]] as $salah) {
            try {
                PaKurvaStatistik::pastikanBatasZ(array_map(fn ($k) => ['kode' => $k, 'batas_z' => $salah[$k]], array_keys($salah)));
                $this->fail('Seharusnya ditolak: '.json_encode($salah));
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Batas z', $e->getMessage());
            }
        }

        PaKurvaStatistik::pastikanBatasZ([['kode' => 'A', 'batas_z' => 1.0], ['kode' => 'B', 'batas_z' => null]]);
        $this->addToAssertionCount(1);
    }
}
```

- [ ] **Langkah 2: Tulis `PaKurvaStatistik`**

```php
<?php

namespace App\Services\KPI\PerformanceAppraisal\Kurva;

use RuntimeException;

/**
 * Matematika kurva grade — MURNI, tanpa basis data, supaya bisa dibuktikan terhadap Excel HC.
 *
 * Grade = pita nilai absolut yang diturunkan dari z: batas bawah grade = μ + z·σ. Tiap grade
 * dalam isi awal (G8) selebar satu σ dan μ adalah batas bawah B. σ SAMPEL (n−1), setara
 * `STDEV` Excel — memakai σ populasi menggeser setiap batas dan grade sebagian orang.
 */
final class PaKurvaStatistik
{
    public const ATAS = 999.99;

    /**
     * @param  list<float|int>  $nilai
     * @return array{n:int, rata:?float, sd:?float}
     */
    public static function ringkas(array $nilai): array
    {
        $n = count($nilai);

        if ($n === 0) {
            return ['n' => 0, 'rata' => null, 'sd' => null];
        }

        $rata = array_sum($nilai) / $n;

        if ($n < 2) {
            return ['n' => $n, 'rata' => $rata, 'sd' => null];
        }

        $kuadrat = 0.0;

        foreach ($nilai as $v) {
            $kuadrat += ($v - $rata) ** 2;
        }

        return ['n' => $n, 'rata' => $rata, 'sd' => sqrt($kuadrat / ($n - 1))];
    }

    /**
     * @param  list<array{kode:string, nama:string, batas_z:?float, urutan:int}>  $grade
     * @return list<array{kode:string, nama:string, batas_z:?float, nilai_dari:float, nilai_sampai:float, urutan:int}>
     */
    public static function pita(array $grade, ?float $rata, ?float $sd): array
    {
        self::pastikanBatasZ($grade);

        // z turun; grade tanpa batas (terendah) paling akhir.
        usort($grade, fn ($a, $b) => ($b['batas_z'] ?? -INF) <=> ($a['batas_z'] ?? -INF));

        $datar = $rata === null || $sd === null || $sd <= 0.0;
        $hasil = [];
        $atas = self::ATAS;

        foreach ($grade as $i => $g) {
            if ($datar) {
                /* Tanpa sebaran, satu-satunya jawaban jujur adalah "semua di rata-rata":
                   grade yang rentang z-nya memuat 0 menerima seluruh nilai. */
                $zAtas = $i === 0 ? INF : (float) $grade[$i - 1]['batas_z'];
                $memuatNol = ($g['batas_z'] === null || $g['batas_z'] <= 0.0) && 0.0 < $zAtas;
                $dari = 0.0;
                $sampai = $memuatNol ? self::ATAS : 0.0;
            } else {
                $dari = $g['batas_z'] === null ? 0.0 : max(0.0, round($rata + $g['batas_z'] * $sd, 4));
                $sampai = $atas;
                $atas = $dari;
            }

            $hasil[] = [
                'kode' => $g['kode'],
                'nama' => $g['nama'],
                'batas_z' => $g['batas_z'],
                'nilai_dari' => $dari,
                'nilai_sampai' => max($dari, $sampai),
                'urutan' => $i + 1,
            ];
        }

        return $hasil;
    }

    /** @param list<array{kode:string, nilai_dari:float, nilai_sampai:float}> $pita */
    public static function gradeUntuk(array $pita, float $nilai): ?string
    {
        foreach ($pita as $p) {
            if ($p['nilai_dari'] <= $nilai && $nilai < $p['nilai_sampai']) {
                return $p['kode'];
            }
        }

        return null;
    }

    /** @param list<array{kode:string, batas_z:?float}> $grade */
    public static function pastikanBatasZ(array $grade): void
    {
        $kosong = array_filter($grade, fn ($g) => $g['batas_z'] === null);
        $isi = array_map(fn ($g) => (string) $g['batas_z'], array_filter($grade, fn ($g) => $g['batas_z'] !== null));

        if (count($kosong) !== 1) {
            throw new RuntimeException(
                'Batas z grade harus diisi untuk setiap grade kecuali tepat SATU grade terendah. '
                .'Sekarang ada '.count($kosong).' grade tanpa batas z.'
            );
        }

        if (count($isi) !== count(array_unique($isi))) {
            throw new RuntimeException('Batas z grade tidak boleh kembar: dua grade dengan batas sama membuat salah satunya tidak pernah dipakai.');
        }
    }
}
```

- [ ] **Langkah 3: Periksa statis dan commit**

```bash
php -l app/Services/KPI/PerformanceAppraisal/Kurva/PaKurvaStatistik.php
vendor/bin/pint app/Services/KPI/PerformanceAppraisal/Kurva/PaKurvaStatistik.php tests/Unit/KPI/PerformanceAppraisal/PaKurvaStatistikTest.php
git add app/Services/KPI/PerformanceAppraisal/Kurva/PaKurvaStatistik.php tests/Unit/KPI/PerformanceAppraisal/PaKurvaStatistikTest.php
git commit -m "feat(pa): PaKurvaStatistik mereproduksi kurva grade Excel HC"
```

Catatan: uji Unit murni ini boleh dijalankan segera (`php vendor/bin/phpunit tests/Unit/KPI/PerformanceAppraisal/PaKurvaStatistikTest.php`) — tidak menyentuh DB; harapan 6 lulus. Hapus sementara `- 1` pada pembagi σ untuk melihatnya MERAH, lalu kembalikan.

---

### Tugas 3: Master — `Batas_Z` pada pita grade dan Grup Level Kurva

**Berkas:**
- Ubah: `app/Services/KPI/PerformanceAppraisal/PaMasterWriteService.php` (`simpanPitaNilai`)
- Ubah: `app/Services/KPI/PerformanceAppraisal/PaMasterReadService.php` (katalog `pita-nilai`.`kolom`)
- Buat: `app/Services/KPI/PerformanceAppraisal/Kurva/PaGrupKurvaMasterService.php`
- Uji: `tests/Feature/KPI/PerformanceAppraisal/GrupKurvaMasterTest.php`

**Interfaces:**
- Consumes: `PaKurvaStatistik::pastikanBatasZ()`.
- Produces:
  - Master `pita-nilai` menerima `batas_z` (nullable numeric −9.99..9.99) dan membacanya sebagai `Batas_Z`. Sesudah simpan baris `FINAL|GRADE`: bila ada satu pun `Batas_Z` terisi, `pastikanBatasZ()` atas seluruh baris aktif `FINAL|GRADE`.
  - `PaGrupKurvaMasterService::daftar(): array{grup: list<array{id:int, kode:string, nama:string, urutan:int, level: list<array{id:int, nama:string}>}>, level_tanpa_grup: list<array{id:int, nama:string}>, cacah_level: array<int,int>}` — `cacah_level` = peserta per `ID_Level_Saat_Cycle` di siklus terbaru yang berpeserta.
  - `PaGrupKurvaMasterService::simpan(list<array{kode:string, nama:string, level_ids: list<int>}> $grup, ?int $olehUserId): array` — mengganti SELURUH konfigurasi dalam satu transaksi (grup & level lama dinonaktifkan, yang baru disisipkan); menolak level kembar lintas grup, kode kembar, grup tanpa level, level yang tidak ada di `HRIS_Level`. Mengembalikan `daftar()`.

- [ ] **Langkah 1: Tulis uji `GrupKurvaMasterTest`**

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Kurva\PaGrupKurvaMasterService;
use App\Services\KPI\PerformanceAppraisal\PaMasterWriteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class GrupKurvaMasterTest extends TestCase
{
    use DatabaseTransactions;

    private function layanan(): PaGrupKurvaMasterService
    {
        return app(PaGrupKurvaMasterService::class);
    }

    /** @return list<int> */
    private function level(int $jumlah): array
    {
        return DB::table('HRIS_Level')->orderBy('ID_Level')->limit($jumlah)->pluck('ID_Level')->map(fn ($x) => (int) $x)->all();
    }

    public function test_simpan_mengganti_seluruh_konfigurasi(): void
    {
        [$a, $b, $c] = $this->level(3);

        $this->layanan()->simpan([
            ['kode' => 'UJI_STAF', 'nama' => 'Staf', 'level_ids' => [$a, $b]],
            ['kode' => 'UJI_MGR', 'nama' => 'Manajer', 'level_ids' => [$c]],
        ], 9);
        $hasil = $this->layanan()->simpan([['kode' => 'UJI_SEMUA', 'nama' => 'Semua', 'level_ids' => [$a, $b, $c]]], 9);

        $this->assertSame(['UJI_SEMUA'], array_column($hasil['grup'], 'kode'));
        $this->assertSame([$a, $b, $c], array_column($hasil['grup'][0]['level'], 'id'));
        $this->assertSame(0, DB::table('N_PA_Curve_Group')->where('Kode_Grup', 'UJI_STAF')->where('Flag_Aktif', 'Y')->count());
    }

    public function test_level_kembar_kode_kembar_dan_grup_kosong_ditolak(): void
    {
        [$a, $b] = $this->level(2);

        foreach ([
            [['kode' => 'X1', 'nama' => 'x', 'level_ids' => [$a]], ['kode' => 'X2', 'nama' => 'y', 'level_ids' => [$a]]],
            [['kode' => 'X1', 'nama' => 'x', 'level_ids' => [$a]], ['kode' => 'X1', 'nama' => 'y', 'level_ids' => [$b]]],
            [['kode' => 'X1', 'nama' => 'x', 'level_ids' => []]],
            [['kode' => 'X1', 'nama' => 'x', 'level_ids' => [999999]]],
        ] as $salah) {
            try {
                $this->layanan()->simpan($salah, null);
                $this->fail('Seharusnya ditolak: '.json_encode($salah));
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_level_tanpa_grup_dilaporkan(): void
    {
        [$a] = $this->level(1);
        $hasil = $this->layanan()->simpan([['kode' => 'UJI_SATU', 'nama' => 'Satu', 'level_ids' => [$a]]], null);

        $this->assertNotContains($a, array_column($hasil['level_tanpa_grup'], 'id'));
        $this->assertGreaterThan(0, count($hasil['level_tanpa_grup']));
    }

    public function test_batas_z_pita_grade_divalidasi_saat_disimpan(): void
    {
        $b = DB::table('N_PA_Score_Range')->where('Score_Type', 'FINAL')->where('Score_Usage', 'GRADE')
            ->where('Kode_Range', 'B')->where('Flag_Aktif', 'Y')->first();
        $this->assertNotNull($b, 'Grade B master tidak ada.');

        $data = [
            'sumbu' => 'FINAL', 'kegunaan' => 'GRADE', 'kode_range' => 'B', 'nama_range' => $b->Nama_Range,
            'nilai_dari' => (float) $b->Nilai_Dari, 'nilai_sampai' => (float) $b->Nilai_Sampai,
            'target_persen' => $b->Target_Persen, 'urutan_tampil' => $b->Urutan_Tampil,
        ];

        // Batas z sama dengan grade lain → ditolak.
        $this->expectException(RuntimeException::class);
        app(PaMasterWriteService::class)->simpan('pita-nilai', (int) $b->Id_PA_Score_Range, $data + ['batas_z' => 1.0], null);
    }
}
```

- [ ] **Langkah 2: `Batas_Z` pada master pita**

Di `PaMasterReadService::KATALOG['pita-nilai']['kolom']` tambahkan `'Batas_Z'` sesudah `'Nilai_Sampai'`. Di `PaMasterWriteService::simpanPitaNilai()`:

```php
            'Urutan_Tampil' => max(1, (int) ($d['urutan_tampil'] ?? 1)),
            // Batas bawah z (grade kurva). Kosong = grade terendah, atau belum diatur.
            'Batas_Z' => ($d['batas_z'] ?? null) === null || $d['batas_z'] === '' ? null : round((float) $d['batas_z'], 2),
        ];
```

dan di dalam transaksinya, sesudah `pastikanPitaBersinambung(...)`:

```php
            if ($isi['Score_Type'] === 'FINAL' && $isi['Score_Usage'] === 'GRADE') {
                $this->pastikanBatasZGrade();
            }
```

serta method baru:

```php
    /**
     * Batas z grade dinilai SEBAGAI HIMPUNAN, sesudah baris tertulis. Selama belum satu pun
     * diisi (masa peralihan), pita lama tetap sah dan tidak ada yang ditolak.
     */
    private function pastikanBatasZGrade(): void
    {
        $grade = DB::table('N_PA_Score_Range')
            ->where('Score_Type', 'FINAL')->where('Score_Usage', 'GRADE')->where('Flag_Aktif', 'Y')
            ->get(['Kode_Range', 'Batas_Z']);

        if ($grade->whereNotNull('Batas_Z')->isEmpty()) {
            return;
        }

        PaKurvaStatistik::pastikanBatasZ($grade->map(fn ($g) => [
            'kode' => (string) $g->Kode_Range,
            'batas_z' => $g->Batas_Z === null ? null : (float) $g->Batas_Z,
        ])->values()->all());
    }
```

Tambahkan `use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaStatistik;`. Validasi request di controller master (`simpanMaster`/rule per master) — cari aturan `nilai_sampai` di `PaMasterDataController` (`grep -n "nilai_sampai" app/Http/Controllers/KPI/PerformanceAppraisal/MasterData/PaMasterDataController.php`) dan tambahkan di sebelahnya `'batas_z' => ['nullable', 'numeric', 'between:-9.99,9.99'],`. Bila validasinya ada di service, cukup langkah di atas.

- [ ] **Langkah 3: Tulis `PaGrupKurvaMasterService`**

```php
<?php

namespace App\Services\KPI\PerformanceAppraisal\Kurva;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Master Grup Level Kurva: level mana yang dibandingkan dalam satu kurva grade.
 *
 * Disimpan sebagai SATU KONFIGURASI UTUH, bukan per grup: memindah satu level dari grup A ke B
 * adalah dua perubahan yang harus terjadi bersamaan, dan indeks unik "satu level satu grup
 * aktif" akan menolak langkah pertamanya bila dikerjakan satu per satu.
 */
class PaGrupKurvaMasterService
{
    /** @return array<string, mixed> */
    public function daftar(): array
    {
        $grup = DB::table('N_PA_Curve_Group')->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan_Tampil')->orderBy('Id_PA_Curve_Group')
            ->get(['Id_PA_Curve_Group', 'Kode_Grup', 'Nama_Grup', 'Urutan_Tampil']);

        $anggota = DB::table('N_PA_Curve_Group_Level as gl')
            ->join('HRIS_Level as l', 'l.ID_Level', '=', 'gl.ID_Level')
            ->where('gl.Flag_Aktif', 'Y')
            ->orderBy('gl.ID_Level')
            ->get(['gl.PA_Curve_Group_Id', 'gl.ID_Level', 'l.Keterangan'])
            ->groupBy('PA_Curve_Group_Id');

        $terpakai = DB::table('N_PA_Curve_Group_Level')->where('Flag_Aktif', 'Y')->pluck('ID_Level')->map(fn ($x) => (int) $x)->all();

        $siklus = DB::table('N_PA_Participant')->orderByDesc('PA_Cycle_Id')->value('PA_Cycle_Id');
        $cacah = $siklus === null ? [] : DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $siklus)->where('Status_Peserta', 'DIIKUTKAN')
            ->groupBy('ID_Level_Saat_Cycle')
            ->selectRaw('ID_Level_Saat_Cycle as l, COUNT(*) as n')
            ->pluck('n', 'l')->map(fn ($n) => (int) $n)->all();

        return [
            'grup' => $grup->map(fn ($g) => [
                'id' => (int) $g->Id_PA_Curve_Group,
                'kode' => (string) $g->Kode_Grup,
                'nama' => (string) $g->Nama_Grup,
                'urutan' => (int) $g->Urutan_Tampil,
                'level' => collect($anggota->get($g->Id_PA_Curve_Group, []))
                    ->map(fn ($l) => ['id' => (int) $l->ID_Level, 'nama' => (string) $l->Keterangan])->values()->all(),
            ])->values()->all(),
            'level_tanpa_grup' => DB::table('HRIS_Level')->whereNotIn('ID_Level', $terpakai ?: [0])
                ->orderBy('ID_Level')->get(['ID_Level', 'Keterangan'])
                ->map(fn ($l) => ['id' => (int) $l->ID_Level, 'nama' => (string) $l->Keterangan])->values()->all(),
            'cacah_level' => $cacah,
        ];
    }

    /**
     * @param  list<array{kode:string, nama:string, level_ids:list<int>}>  $grup
     * @return array<string, mixed>
     */
    public function simpan(array $grup, ?int $olehUserId): array
    {
        $bersih = $this->periksa($grup);

        DB::transaction(function () use ($bersih, $olehUserId) {
            DB::table('N_PA_Curve_Group_Level')->where('Flag_Aktif', 'Y')->update(['Flag_Aktif' => 'T']);
            DB::table('N_PA_Curve_Group')->where('Flag_Aktif', 'Y')
                ->update(['Flag_Aktif' => 'T', 'Updated_At' => now(), 'Updated_By' => $olehUserId]);

            foreach ($bersih as $i => $g) {
                $id = (int) DB::table('N_PA_Curve_Group')->insertGetId([
                    'Kode_Grup' => $g['kode'],
                    'Nama_Grup' => $g['nama'],
                    'Urutan_Tampil' => $i + 1,
                    'Created_By' => $olehUserId,
                ], 'Id_PA_Curve_Group');

                DB::table('N_PA_Curve_Group_Level')->insert(array_map(fn (int $l) => [
                    'PA_Curve_Group_Id' => $id,
                    'ID_Level' => $l,
                    'Created_By' => $olehUserId,
                ], $g['level_ids']));
            }
        });

        return $this->daftar();
    }

    /**
     * Publik: dipakai juga untuk grup SALINAN siklus (PaKurvaService::aturGrupSiklus) —
     * aturan grup di master dan di siklus harus satu, bukan dua salinan yang berselisih.
     *
     * @param  list<array<string, mixed>>  $grup
     * @return list<array{kode:string, nama:string, level_ids:list<int>}>
     */
    public function periksa(array $grup): array
    {
        $kodeDilihat = [];
        $levelDilihat = [];
        $ada = DB::table('HRIS_Level')->pluck('ID_Level')->map(fn ($x) => (int) $x)->flip();
        $bersih = [];

        foreach ($grup as $g) {
            $kode = strtoupper(trim((string) ($g['kode'] ?? '')));
            $nama = trim((string) ($g['nama'] ?? ''));
            $level = array_values(array_unique(array_map('intval', (array) ($g['level_ids'] ?? []))));

            if ($kode === '' || $nama === '') {
                throw new RuntimeException('Setiap grup wajib punya kode dan nama.');
            }

            if (isset($kodeDilihat[$kode])) {
                throw new RuntimeException("Kode grup {$kode} dipakai dua kali.");
            }

            if ($level === []) {
                throw new RuntimeException("Grup {$kode} belum berisi level. Grup tanpa anggota tidak punya kurva.");
            }

            foreach ($level as $l) {
                if (! $ada->has($l)) {
                    throw new RuntimeException("Level {$l} tidak ada di HRIS_Level.");
                }

                if (isset($levelDilihat[$l])) {
                    throw new RuntimeException("Level {$l} dimasukkan ke dua grup ({$levelDilihat[$l]} dan {$kode}). Satu level hanya boleh di satu kurva.");
                }

                $levelDilihat[$l] = $kode;
            }

            $kodeDilihat[$kode] = true;
            $bersih[] = ['kode' => $kode, 'nama' => $nama, 'level_ids' => $level];
        }

        return $bersih;
    }
}
```

- [ ] **Langkah 4: Periksa statis dan commit**

```bash
php -l app/Services/KPI/PerformanceAppraisal/Kurva/PaGrupKurvaMasterService.php && php -l app/Services/KPI/PerformanceAppraisal/PaMasterWriteService.php
vendor/bin/pint app/Services/KPI/PerformanceAppraisal/Kurva/PaGrupKurvaMasterService.php app/Services/KPI/PerformanceAppraisal/PaMasterWriteService.php app/Services/KPI/PerformanceAppraisal/PaMasterReadService.php tests/Feature/KPI/PerformanceAppraisal/GrupKurvaMasterTest.php
git add app/Services/KPI/PerformanceAppraisal/Kurva/PaGrupKurvaMasterService.php app/Services/KPI/PerformanceAppraisal/PaMasterWriteService.php app/Services/KPI/PerformanceAppraisal/PaMasterReadService.php tests/Feature/KPI/PerformanceAppraisal/GrupKurvaMasterTest.php
git commit -m "feat(pa): master batas z grade dan grup level kurva"
```

---

### Tugas 4: Salinan per siklus dan siklus baru bermetode KURVA

**Berkas:**
- Ubah: `app/Services/KPI/PerformanceAppraisal/PaCyclePreparationService.php` (`salinPita`, method baru `salinGrupKurva`, pemanggilnya)
- Ubah: `app/Services/KPI/PerformanceAppraisal/PaCycleSetupService.php:225` (sisip `Metode_Grade`)
- Uji: `tests/Feature/KPI/PerformanceAppraisal/SalinanKurvaTest.php`

**Interfaces:**
- Produces: `PaCyclePreparationService::salinGrupKurva(int $cycleId, ?int $olehUserId): int` (public — dipakai `PaKurvaService::ubahMetode`). Menyalin grup aktif beserta levelnya; **setiap level `HRIS_Level` yang tidak terpetakan menjadi grupnya sendiri** (`Kode_Grup` = `'LV'.ID_Level`, `Nama_Grup` = `Keterangan`). No-op bila siklus sudah punya grup aktif. Siklus baru dari `PaCycleSetupService` ber-`Metode_Grade = 'KURVA'`.

- [ ] **Langkah 1: Uji**

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaCyclePreparationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

class SalinanKurvaTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanLembarPenilaian;

    public function test_grup_disalin_dan_level_tanpa_grup_jadi_grupnya_sendiri(): void
    {
        [$a, $b] = DB::table('HRIS_Level')->orderBy('ID_Level')->limit(2)->pluck('ID_Level')->map(fn ($x) => (int) $x)->all();
        DB::table('N_PA_Curve_Group_Level')->update(['Flag_Aktif' => 'T']);
        DB::table('N_PA_Curve_Group')->update(['Flag_Aktif' => 'T']);
        $g = (int) DB::table('N_PA_Curve_Group')->insertGetId(['Kode_Grup' => 'UJI_G', 'Nama_Grup' => 'Uji'], 'Id_PA_Curve_Group');
        DB::table('N_PA_Curve_Group_Level')->insert(['PA_Curve_Group_Id' => $g, 'ID_Level' => $a]);

        $cycleId = $this->siklusLembar('OPEN');
        $n = app(PaCyclePreparationService::class)->salinGrupKurva($cycleId, null);

        $peta = DB::table('N_PA_Cycle_Curve_Group_Level as l')
            ->join('N_PA_Cycle_Curve_Group as g', 'g.Id_PA_Cycle_Curve_Group', '=', 'l.PA_Cycle_Curve_Group_Id')
            ->where('l.PA_Cycle_Id', $cycleId)->where('l.Flag_Aktif', 'Y')
            ->pluck('g.Kode_Grup', 'l.ID_Level');

        $this->assertSame('UJI_G', $peta[$a]);
        $this->assertSame('LV'.$b, $peta[$b]);
        $this->assertSame(DB::table('HRIS_Level')->count(), $peta->count(), 'Setiap level wajib punya grup di siklus.');
        $this->assertSame(0, app(PaCyclePreparationService::class)->salinGrupKurva($cycleId, null), 'Salinan kedua harus no-op.');
        $this->assertGreaterThan(0, $n);
    }
}
```

- [ ] **Langkah 2: Implementasi**

Di `salinPita()` tambahkan `'Batas_Z' => $p->Batas_Z,` sesudah `'Nilai_Sampai' => $p->Nilai_Sampai,`. Tambahkan method:

```php
    /**
     * Grup level kurva disalin per siklus seperti pita: mengubah master sesudah siklus
     * disiapkan tidak boleh menggeser pembanding siapa pun. Setiap level WAJIB punya grup di
     * siklus — level yang belum dipetakan admin menjadi grupnya sendiri, supaya kunci kurva
     * tidak pernah menemukan peserta tanpa kurva.
     */
    public function salinGrupKurva(int $cycleId, ?int $olehUserId): int
    {
        if (DB::table('N_PA_Cycle_Curve_Group')->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')->exists()) {
            return 0;
        }

        $grup = DB::table('N_PA_Curve_Group')->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan_Tampil')->get(['Id_PA_Curve_Group', 'Kode_Grup', 'Nama_Grup', 'Urutan_Tampil']);
        $anggota = DB::table('N_PA_Curve_Group_Level')->where('Flag_Aktif', 'Y')
            ->get(['PA_Curve_Group_Id', 'ID_Level'])->groupBy('PA_Curve_Group_Id');
        $terpakai = [];
        $n = 0;

        foreach ($grup as $g) {
            $id = (int) DB::table('N_PA_Cycle_Curve_Group')->insertGetId([
                'PA_Cycle_Id' => $cycleId,
                'PA_Curve_Group_Id' => $g->Id_PA_Curve_Group,
                'Kode_Grup' => $g->Kode_Grup,
                'Nama_Grup' => $g->Nama_Grup,
                'Urutan_Tampil' => $g->Urutan_Tampil,
                'Created_By' => $olehUserId,
            ], 'Id_PA_Cycle_Curve_Group');

            foreach ($anggota->get($g->Id_PA_Curve_Group, []) as $l) {
                DB::table('N_PA_Cycle_Curve_Group_Level')->insert([
                    'PA_Cycle_Curve_Group_Id' => $id, 'PA_Cycle_Id' => $cycleId, 'ID_Level' => $l->ID_Level,
                ]);
                $terpakai[(int) $l->ID_Level] = true;
            }

            $n++;
        }

        $urutan = $grup->count();

        foreach (DB::table('HRIS_Level')->orderBy('ID_Level')->get(['ID_Level', 'Keterangan']) as $l) {
            if (isset($terpakai[(int) $l->ID_Level])) {
                continue;
            }

            $id = (int) DB::table('N_PA_Cycle_Curve_Group')->insertGetId([
                'PA_Cycle_Id' => $cycleId,
                'Kode_Grup' => 'LV'.$l->ID_Level,
                'Nama_Grup' => (string) $l->Keterangan,
                'Urutan_Tampil' => ++$urutan,
                'Created_By' => $olehUserId,
            ], 'Id_PA_Cycle_Curve_Group');

            DB::table('N_PA_Cycle_Curve_Group_Level')->insert([
                'PA_Cycle_Curve_Group_Id' => $id, 'PA_Cycle_Id' => $cycleId, 'ID_Level' => $l->ID_Level,
            ]);
            $n++;
        }

        return $n;
    }
```

Panggil `$this->salinGrupKurva($cycleId, $olehUserId)` tepat sesudah pemanggilan `salinPita($cycleId)` yang sudah ada (`grep -n "salinPita(" app/Services/KPI/PerformanceAppraisal/PaCyclePreparationService.php`; gunakan variabel pelaku yang tersedia di sana, atau `null`). Di `PaCycleSetupService` baris `insertGetId($isi + [` tambahkan `'Metode_Grade' => 'KURVA',` ke array yang disisipkan.

- [ ] **Langkah 3: Commit**

```bash
php -l app/Services/KPI/PerformanceAppraisal/PaCyclePreparationService.php && php -l app/Services/KPI/PerformanceAppraisal/PaCycleSetupService.php
vendor/bin/pint app/Services/KPI/PerformanceAppraisal/PaCyclePreparationService.php app/Services/KPI/PerformanceAppraisal/PaCycleSetupService.php tests/Feature/KPI/PerformanceAppraisal/SalinanKurvaTest.php
git add app/Services/KPI/PerformanceAppraisal/PaCyclePreparationService.php app/Services/KPI/PerformanceAppraisal/PaCycleSetupService.php tests/Feature/KPI/PerformanceAppraisal/SalinanKurvaTest.php
git commit -m "feat(pa): batas z dan grup kurva disalin per siklus, siklus baru bermetode kurva"
```

---

### Tugas 5: `PaKurvaService` — pratinjau, kunci, buka, pindah metode, grup siklus

**Berkas:**
- Buat: `app/Services/KPI/PerformanceAppraisal/Kurva/PaKurvaService.php`
- Ubah: `app/Services/KPI/PerformanceAppraisal/PaCycleGate.php` (dua operasi)
- Uji: `tests/Feature/KPI/PerformanceAppraisal/KurvaKunciTest.php`; `CycleGateTest` (satu uji baru)

**Interfaces:**
- Consumes: `PaKurvaStatistik`, `PaCyclePreparationService::salinGrupKurva()`, `PaCycleGate`.
- Produces (`PaKurvaService`):
  - `const MIN_GRUP = 30;`
  - `metode(int $cycleId): string` — `'PITA'|'KURVA'`.
  - `terkunci(int $cycleId): bool`.
  - `pratinjau(int $cycleId): array{metode:string, terkunci:bool, kunci:?array{pada:string, oleh:?int}, grup: list<array{id:int, kode:string, nama:string, level: list<array{id:int, nama:string}>, n:int, rata:?float, sd:?float, pita: list<array>, sebaran: array<string,int>, peringatan: list<string>}>, peringatan: list<string>, hasil_sesudah_kunci:int, sudah_terbit:int, tertahan:int}`.
  - `kunci(int $cycleId, ?int $olehUserId): array` → `pratinjau()` sesudah dikunci.
  - `buka(int $cycleId, ?string $alasan, ?int $olehUserId): array` → `pratinjau()`.
  - `ubahMetode(int $cycleId, string $metode, ?int $olehUserId): array` → `pratinjau()`.
  - `aturGrupSiklus(int $cycleId, list<array{kode:string, nama:string, level_ids:list<int>}> $grup, ?int $olehUserId): array` → `pratinjau()`.
  - `gradeBeku(int $participantId, float $nilai): array{kode:?string, curve_id:?int}` — `['kode'=>null,'curve_id'=>null]` bila siklus `PITA` atau belum dikunci; `RuntimeException` bila terkunci tetapi nilai tidak masuk pita.
  - `pastikanTerkunci(int $cycleId): void` — `RuntimeException` pada siklus `KURVA` yang belum dikunci; no-op pada `PITA`.
- Gerbang: `PaCycleGate::KUNCI_KURVA` dan `BUKA_KURVA`, keduanya `['OPEN', 'CLOSED']`.

- [ ] **Langkah 1: Tulis uji `KurvaKunciTest`**

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

/**
 * Siklus KURVA tiruan: dua grup level dengan sebaran berbeda, hasil DIHITUNG ditanam langsung.
 */
class KurvaKunciTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanLembarPenilaian;

    private function layanan(): PaKurvaService
    {
        return app(PaKurvaService::class);
    }

    /**
     * @param  array<int, list<float>>  $nilaiPerLevel  ID_Level => nilai akhir
     * @return array{cycle_id:int, peserta: array<int, list<int>>}
     */
    private function siklusKurva(array $nilaiPerLevel, string $status = 'CLOSED'): array
    {
        $cycleId = $this->siklusLembar($status);
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->update(['Metode_Grade' => 'KURVA']);

        foreach ([['A', 2.0, 1], ['B_PLUS', 1.0, 2], ['B', 0.0, 3], ['C_PLUS', -1.0, 4], ['C', -2.0, 5], ['D', null, 6]] as [$k, $z, $u]) {
            DB::table('N_PA_Cycle_Score_Range_Snapshot')->insert([
                'PA_Cycle_Id' => $cycleId, 'Score_Type' => 'FINAL', 'Score_Usage' => 'GRADE',
                'Kode_Range' => $k, 'Nama_Range' => $k, 'Nilai_Dari' => 0, 'Nilai_Sampai' => 999.99,
                'Batas_Z' => $z, 'Urutan_Tampil' => $u, 'Flag_Aktif' => 'Y',
            ]);
        }

        $karyawan = DB::table('Karyawan')->where('Aktif', 'Y')->orderBy('Kode_Karyawan')
            ->limit(array_sum(array_map('count', $nilaiPerLevel)))->get(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama']);
        $i = 0;
        $peserta = [];

        foreach ($nilaiPerLevel as $level => $daftar) {
            $grup = (int) DB::table('N_PA_Cycle_Curve_Group')->insertGetId(
                ['PA_Cycle_Id' => $cycleId, 'Kode_Grup' => 'G'.$level, 'Nama_Grup' => 'G'.$level], 'Id_PA_Cycle_Curve_Group');
            DB::table('N_PA_Cycle_Curve_Group_Level')->insert(['PA_Cycle_Curve_Group_Id' => $grup, 'PA_Cycle_Id' => $cycleId, 'ID_Level' => $level]);

            foreach ($daftar as $nilai) {
                $k = $karyawan[$i++];
                $pid = (int) DB::table('N_PA_Participant')->insertGetId([
                    'PA_Cycle_Id' => $cycleId, 'Kode_Perusahaan' => $k->Kode_Perusahaan, 'Kode_Karyawan' => $k->Kode_Karyawan,
                    'Nama_Karyawan_Saat_Cycle' => $k->Nama, 'ID_Level_Saat_Cycle' => $level, 'Status_Peserta' => 'DIIKUTKAN',
                ], 'Id_PA_Participant');
                DB::table('N_PA_Result')->insert([
                    'PA_Participant_Id' => $pid, 'Status_Hasil' => 'DIHITUNG', 'Final_Score' => $nilai,
                    'Calculation_Version' => 1, 'Flag_Aktif' => 'Y',
                ]);
                $peserta[$level][] = $pid;
            }
        }

        return ['cycle_id' => $cycleId, 'peserta' => $peserta];
    }

    private function grade(int $pid): ?string
    {
        return DB::table('N_PA_Result')->where('PA_Participant_Id', $pid)->where('Flag_Aktif', 'Y')->value('Kode_Grade_Sistem');
    }

    private function levelUji(): array
    {
        return DB::table('HRIS_Level')->orderBy('ID_Level')->limit(2)->pluck('ID_Level')->map(fn ($x) => (int) $x)->all();
    }

    public function test_kunci_memberi_grade_per_grup_dari_kurvanya_sendiri(): void
    {
        [$l1, $l2] = $this->levelUji();
        // Nilai 80 di grup rendah adalah bintang; di grup tinggi adalah rata-rata.
        $s = $this->siklusKurva([$l1 => [50, 55, 60, 65, 80], $l2 => [70, 75, 80, 85, 90]]);

        $hasil = $this->layanan()->kunci($s['cycle_id'], 7);

        $this->assertTrue($hasil['terkunci']);
        $this->assertSame('B_PLUS', $this->grade($s['peserta'][$l1][4]), 'z = +1,37 di grup rendah.');
        $this->assertSame('B', $this->grade($s['peserta'][$l2][2]), 'z = 0 di grup tinggi.');
        $this->assertSame(2, DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $s['cycle_id'])->where('Flag_Aktif', 'Y')->count());
        $this->assertNotNull(DB::table('N_PA_Result')->where('PA_Participant_Id', $s['peserta'][$l1][0])->value('PA_Cycle_Curve_Id'));
        $this->assertContains('Grup G'.$l1.' hanya 5 orang (di bawah 30); kurvanya kurang bermakna.', $hasil['grup'][0]['peringatan']);
    }

    public function test_sebelum_kunci_grade_hanya_sementara_dan_kosong_di_basis_data(): void
    {
        [$l1] = $this->levelUji();
        $s = $this->siklusKurva([$l1 => [50, 60, 70, 80, 90]]);

        $p = $this->layanan()->pratinjau($s['cycle_id']);

        $this->assertFalse($p['terkunci']);
        $this->assertSame(1, $p['grup'][0]['sebaran']['B_PLUS']);
        $this->assertNull($this->grade($s['peserta'][$l1][4]));
    }

    public function test_kunci_ganda_dan_siklus_pita_ditolak(): void
    {
        [$l1] = $this->levelUji();
        $s = $this->siklusKurva([$l1 => [50, 60, 70]]);
        $this->layanan()->kunci($s['cycle_id'], null);

        $this->expectException(RuntimeException::class);
        $this->layanan()->kunci($s['cycle_id'], null);
    }

    public function test_buka_wajib_alasan_dan_ditolak_bila_ada_yang_terbit(): void
    {
        [$l1] = $this->levelUji();
        $s = $this->siklusKurva([$l1 => [50, 60, 70]]);
        $this->layanan()->kunci($s['cycle_id'], null);

        try {
            $this->layanan()->buka($s['cycle_id'], '  ', null);
            $this->fail('Buka tanpa alasan seharusnya ditolak.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        DB::table('N_PA_Result')->where('PA_Participant_Id', $s['peserta'][$l1][0])->update(['Status_Hasil' => 'TERBIT']);

        try {
            $this->layanan()->buka($s['cycle_id'], 'Salah grouping', null);
            $this->fail('Buka sesudah ada yang terbit seharusnya ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('terbit', $e->getMessage());
        }
    }

    public function test_buka_mengosongkan_grade_mesin_tetapi_mempertahankan_keputusan_forum(): void
    {
        [$l1] = $this->levelUji();
        $s = $this->siklusKurva([$l1 => [50, 60, 70]]);
        $this->layanan()->kunci($s['cycle_id'], null);
        [$p0, $p1] = $s['peserta'][$l1];
        DB::table('N_PA_Result')->where('PA_Participant_Id', $p1)->update(['Flag_Dikalibrasi' => 'Y', 'Kode_Grade_Final' => 'A']);

        $this->layanan()->buka($s['cycle_id'], 'Grouping diperbaiki', 3);

        $this->assertNull($this->grade($p0));
        $this->assertNull(DB::table('N_PA_Result')->where('PA_Participant_Id', $p0)->value('Kode_Grade_Final'));
        $this->assertSame('A', DB::table('N_PA_Result')->where('PA_Participant_Id', $p1)->value('Kode_Grade_Final'));
        $this->assertSame(0, DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $s['cycle_id'])->where('Flag_Aktif', 'Y')->count());
        $this->assertSame(1, DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $s['cycle_id'])->whereNotNull('Alasan_Buka')->count());
    }

    public function test_grade_beku_dipakai_sesudah_kunci_dan_tidak_menggeser_kurva(): void
    {
        [$l1] = $this->levelUji();
        $s = $this->siklusKurva([$l1 => [50, 60, 70, 80, 90]]);
        $this->layanan()->kunci($s['cycle_id'], null);
        $rata = (float) DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $s['cycle_id'])->where('Flag_Aktif', 'Y')->value('Rata_Rata');

        $beku = $this->layanan()->gradeBeku($s['peserta'][$l1][0], 100.0);

        $this->assertSame('A', $beku['kode']);
        $this->assertEqualsWithDelta(70.0, $rata, 0.0001);
    }

    public function test_pindah_ke_kurva_ditolak_bila_ada_yang_terbit(): void
    {
        [$l1] = $this->levelUji();
        $s = $this->siklusKurva([$l1 => [50, 60]]);
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $s['cycle_id'])->update(['Metode_Grade' => 'PITA']);
        DB::table('N_PA_Result')->where('PA_Participant_Id', $s['peserta'][$l1][0])->update(['Status_Hasil' => 'TERBIT']);

        $this->expectException(RuntimeException::class);
        $this->layanan()->ubahMetode($s['cycle_id'], 'KURVA', null);
    }

    public function test_pastikan_terkunci_menjaga_kalibrasi_dan_penerbitan(): void
    {
        [$l1] = $this->levelUji();
        $s = $this->siklusKurva([$l1 => [50, 60]]);

        try {
            $this->layanan()->pastikanTerkunci($s['cycle_id']);
            $this->fail('Siklus kurva belum dikunci seharusnya ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Kunci kurva', $e->getMessage());
        }

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $s['cycle_id'])->update(['Metode_Grade' => 'PITA']);
        $this->layanan()->pastikanTerkunci($s['cycle_id']);
        $this->addToAssertionCount(1);
    }
}
```

Tambahkan juga di `CycleGateTest`:

```php
    public function test_kunci_dan_buka_kurva_hanya_saat_open_atau_closed(): void
    {
        foreach ([PaCycleGate::KUNCI_KURVA, PaCycleGate::BUKA_KURVA] as $op) {
            $this->assertSame(['OPEN', 'CLOSED'], PaCycleGate::IZIN[$op]);
        }
    }
```

(Periksa nama konstanta daftar izin di `PaCycleGate` — `grep -n "const IZIN\|private const\|public const [A-Z_]* = \[" app/Services/KPI/PerformanceAppraisal/PaCycleGate.php`; bila privat, uji lewat `boleh()` pada siklus tiruan berstatus `OPEN` dan `DRAFT` mengikuti pola `test_kalibrasi_penilai_hanya_saat_open`.)

- [ ] **Langkah 2: Gerbang** — di `PaCycleGate` tambahkan konstanta dan aturan:

```php
    /** Membekukan kurva grade siklus bermetode KURVA. */
    public const KUNCI_KURVA = 'KUNCI_KURVA';

    /** Membuka kurva yang terkunci — wajib alasan, ditolak bila sudah ada yang terbit. */
    public const BUKA_KURVA = 'BUKA_KURVA';
```

```php
        self::KUNCI_KURVA => ['OPEN', 'CLOSED'],
        self::BUKA_KURVA => ['OPEN', 'CLOSED'],
```

- [ ] **Langkah 3: Tulis `PaKurvaService`**

```php
<?php

namespace App\Services\KPI\PerformanceAppraisal\Kurva;

use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use App\Services\KPI\PerformanceAppraisal\PaCyclePreparationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Kurva grade per grup level, dikunci per siklus (spec 2026-09-18 grade kurva normal).
 *
 * SEBELUM KUNCI grade hanya dihitung untuk DIBACA (pratinjau), tidak pernah disimpan: grade
 * seseorang bergantung pada semua orang di grupnya, jadi grade yang disimpan sebelum semua
 * hasil masuk hanyalah grade basi yang menunggu dipakai.
 *
 * SESUDAH KUNCI μ, σ, dan pita absolut tiap grup dibekukan di N_PA_Cycle_Curve(_Band). Hasil
 * yang dihitung sesudahnya memakai pita beku dan tidak menggeser kurva siapa pun.
 */
class PaKurvaService
{
    public const MIN_GRUP = 30;

    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaCyclePreparationService $penyiapan,
        private readonly PaGrupKurvaMasterService $grupMaster,
    ) {}

    public function metode(int $cycleId): string
    {
        return (string) (DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->value('Metode_Grade') ?? 'PITA');
    }

    public function terkunci(int $cycleId): bool
    {
        return DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')->exists();
    }

    public function pastikanTerkunci(int $cycleId): void
    {
        if ($this->metode($cycleId) === 'KURVA' && ! $this->terkunci($cycleId)) {
            throw new RuntimeException(
                'Kurva grade siklus ini belum dikunci. Kunci kurva lebih dulu di layar Distribusi — '
                .'sebelum itu grade setiap orang masih bisa bergeser saat hasil lain masuk.'
            );
        }
    }

    /** @return array<string, mixed> */
    public function pratinjau(int $cycleId): array
    {
        $metode = $this->metode($cycleId);
        $terkunci = $this->terkunci($cycleId);
        $gradeDef = $this->gradeSiklus($cycleId);
        $grup = $this->grupSiklus($cycleId);
        $hasil = $this->hasilDihitung($cycleId);
        $beku = $terkunci ? $this->kurvaAktif($cycleId) : collect();
        $daftar = [];

        foreach ($grup as $g) {
            $nilai = $hasil->where('grup_id', $g['id'])->pluck('Final_Score')->map(fn ($v) => (float) $v)->values()->all();
            $kurva = $beku->get($g['id']);

            if ($kurva !== null) {
                $stat = ['n' => (int) $kurva->Jumlah_Peserta, 'rata' => $kurva->Rata_Rata === null ? null : (float) $kurva->Rata_Rata,
                    'sd' => $kurva->Simpangan_Baku === null ? null : (float) $kurva->Simpangan_Baku];
                $pita = $this->pitaBeku((int) $kurva->Id_PA_Cycle_Curve);
            } else {
                $stat = PaKurvaStatistik::ringkas($nilai);
                $pita = $gradeDef === [] ? [] : PaKurvaStatistik::pita($gradeDef, $stat['rata'], $stat['sd']);
            }

            $sebaran = array_fill_keys(array_column($pita, 'kode'), 0);

            foreach ($nilai as $v) {
                $k = PaKurvaStatistik::gradeUntuk($pita, $v);

                if ($k !== null) {
                    $sebaran[$k]++;
                }
            }

            $daftar[] = $g + [
                'n' => $stat['n'], 'rata' => $stat['rata'], 'sd' => $stat['sd'],
                'pita' => $pita, 'sebaran' => $sebaran,
                'peringatan' => $this->peringatanGrup($g, $stat),
            ];
        }

        $kunci = $terkunci ? DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')
            ->orderBy('Id_PA_Cycle_Curve')->first(['Locked_At', 'Locked_By']) : null;

        $peringatan = [];

        if ($metode === 'KURVA' && $gradeDef === []) {
            $peringatan[] = 'Batas z grade belum diatur di salinan siklus ini.';
        }

        $tertahan = (int) DB::table('N_PA_Result as r')->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)->where('r.Flag_Aktif', 'Y')->where('r.Status_Hasil', 'TERTAHAN')->count();

        if ($tertahan > 0) {
            $peringatan[] = "{$tertahan} hasil masih TERTAHAN dan tidak ikut membentuk kurva.";
        }

        return [
            'metode' => $metode,
            'terkunci' => $terkunci,
            'kunci' => $kunci === null ? null : ['pada' => substr((string) $kunci->Locked_At, 0, 19), 'oleh' => $kunci->Locked_By === null ? null : (int) $kunci->Locked_By],
            'grup' => $daftar,
            'peringatan' => $peringatan,
            'hasil_sesudah_kunci' => $kunci === null ? 0 : (int) DB::table('N_PA_Result as r')
                ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
                ->where('p.PA_Cycle_Id', $cycleId)->where('r.Flag_Aktif', 'Y')
                ->where('r.Calculated_At', '>', $kunci->Locked_At)->count(),
            'sudah_terbit' => $this->jumlahTerbit($cycleId),
            'tertahan' => $tertahan,
        ];
    }

    /** @return array<string, mixed> */
    public function kunci(int $cycleId, ?int $olehUserId): array
    {
        $this->gate->pastikanBoleh(PaCycleGate::KUNCI_KURVA, $cycleId);

        if ($this->metode($cycleId) !== 'KURVA') {
            throw new RuntimeException('Siklus ini memakai pita nilai tetap (PITA). Pindahkan ke metode kurva lebih dulu.');
        }

        DB::transaction(function () use ($cycleId, $olehUserId) {
            // Dikunci barisnya supaya dua admin yang menekan bersamaan tidak membuat dua kurva.
            DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->lockForUpdate()->first(['Id_PA_Cycle']);

            if ($this->terkunci($cycleId)) {
                throw new RuntimeException('Kurva siklus ini sudah dikunci.');
            }

            $gradeDef = $this->gradeSiklus($cycleId);

            if ($gradeDef === []) {
                throw new RuntimeException('Batas z grade belum diatur di salinan siklus ini.');
            }

            $hasil = $this->hasilDihitung($cycleId);
            $tanpaGrup = $hasil->whereNull('grup_id');

            if ($tanpaGrup->isNotEmpty()) {
                throw new RuntimeException(sprintf(
                    'Level %s belum masuk grup kurva di siklus ini. Atur grup siklus lebih dulu.',
                    $tanpaGrup->pluck('ID_Level_Saat_Cycle')->unique()->implode(', ')
                ));
            }

            foreach ($this->grupSiklus($cycleId) as $g) {
                $anggota = $hasil->where('grup_id', $g['id']);
                $stat = PaKurvaStatistik::ringkas($anggota->pluck('Final_Score')->map(fn ($v) => (float) $v)->values()->all());
                $pita = PaKurvaStatistik::pita($gradeDef, $stat['rata'], $stat['sd']);

                $kurvaId = (int) DB::table('N_PA_Cycle_Curve')->insertGetId([
                    'PA_Cycle_Id' => $cycleId,
                    'PA_Cycle_Curve_Group_Id' => $g['id'],
                    'Jumlah_Peserta' => $stat['n'],
                    'Rata_Rata' => $stat['rata'] === null ? null : round($stat['rata'], 4),
                    'Simpangan_Baku' => $stat['sd'] === null ? null : round($stat['sd'], 4),
                    'Locked_By' => $olehUserId,
                ], 'Id_PA_Cycle_Curve');

                foreach ($pita as $p) {
                    DB::table('N_PA_Cycle_Curve_Band')->insert([
                        'PA_Cycle_Curve_Id' => $kurvaId, 'Kode_Range' => $p['kode'], 'Nama_Range' => $p['nama'],
                        'Batas_Z' => $p['batas_z'], 'Nilai_Dari' => $p['nilai_dari'], 'Nilai_Sampai' => $p['nilai_sampai'],
                        'Urutan_Tampil' => $p['urutan'],
                    ]);
                }

                $this->beriGrade($cycleId, $g['id'], $kurvaId, $pita);
            }
        });

        return $this->pratinjau($cycleId);
    }

    /** @return array<string, mixed> */
    public function buka(int $cycleId, ?string $alasan, ?int $olehUserId): array
    {
        $this->gate->pastikanBoleh(PaCycleGate::BUKA_KURVA, $cycleId);
        $alasan = trim((string) $alasan);

        if (mb_strlen($alasan) < 5 || mb_strlen($alasan) > 500) {
            throw new RuntimeException('Alasan membuka kunci kurva wajib diisi, 5 sampai 500 karakter.');
        }

        if ($this->jumlahTerbit($cycleId) > 0) {
            throw new RuntimeException(
                'Kurva tidak bisa dibuka: sudah ada hasil yang terbit. Membukanya akan mengubah grade '
                .'yang sudah dibaca karyawan tanpa pemberitahuan.'
            );
        }

        if (! $this->terkunci($cycleId)) {
            throw new RuntimeException('Kurva siklus ini belum dikunci.');
        }

        DB::transaction(function () use ($cycleId, $alasan, $olehUserId) {
            $kurva = DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')
                ->pluck('Id_PA_Cycle_Curve')->all();

            // Grade mesin dikosongkan; keputusan forum (Flag_Dikalibrasi) dipertahankan.
            DB::table('N_PA_Result')->whereIn('PA_Cycle_Curve_Id', $kurva)->update([
                'Kode_Grade_Sistem' => null,
                'Kode_Grade_Final' => DB::raw("CASE WHEN Flag_Dikalibrasi = 'Y' THEN Kode_Grade_Final ELSE NULL END"),
                'PA_Cycle_Curve_Id' => null,
            ]);

            DB::table('N_PA_Cycle_Curve')->whereIn('Id_PA_Cycle_Curve', $kurva)->update([
                'Flag_Aktif' => 'T', 'Unlocked_At' => now(), 'Unlocked_By' => $olehUserId, 'Alasan_Buka' => $alasan,
            ]);
        });

        return $this->pratinjau($cycleId);
    }

    /** @return array<string, mixed> */
    public function ubahMetode(int $cycleId, string $metode, ?int $olehUserId): array
    {
        $metode = strtoupper(trim($metode));

        if (! in_array($metode, ['PITA', 'KURVA'], true)) {
            throw new RuntimeException('Metode grade hanya PITA atau KURVA.');
        }

        if ($this->jumlahTerbit($cycleId) > 0 || $this->terkunci($cycleId)) {
            throw new RuntimeException('Metode grade hanya bisa diubah sebelum kurva dikunci dan sebelum ada hasil yang terbit.');
        }

        DB::transaction(function () use ($cycleId, $metode, $olehUserId) {
            DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->update(['Metode_Grade' => $metode]);

            if ($metode === 'KURVA') {
                // Batas z diambil dari master saat pindah; pita nilai salinan tetap utuh untuk PITA.
                foreach (DB::table('N_PA_Score_Range')->where('Score_Type', 'FINAL')->where('Score_Usage', 'GRADE')
                    ->where('Flag_Aktif', 'Y')->get(['Id_PA_Score_Range', 'Batas_Z']) as $m) {
                    DB::table('N_PA_Cycle_Score_Range_Snapshot')->where('PA_Cycle_Id', $cycleId)
                        ->where('PA_Score_Range_Id', $m->Id_PA_Score_Range)->update(['Batas_Z' => $m->Batas_Z]);
                }

                $this->penyiapan->salinGrupKurva($cycleId, $olehUserId);
                // Grade PITA lama tidak berlaku lagi sampai kurva dikunci.
                DB::table('N_PA_Result as r')->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
                    ->where('p.PA_Cycle_Id', $cycleId)->where('r.Flag_Aktif', 'Y')->where('r.Flag_Dikalibrasi', '<>', 'Y')
                    ->update(['r.Kode_Grade_Sistem' => null, 'r.Kode_Grade_Final' => null]);
            }
        });

        return $this->pratinjau($cycleId);
    }

    /**
     * @param  list<array{kode:string, nama:string, level_ids:list<int>}>  $grup
     * @return array<string, mixed>
     */
    public function aturGrupSiklus(int $cycleId, array $grup, ?int $olehUserId): array
    {
        if ($this->terkunci($cycleId)) {
            throw new RuntimeException('Grup kurva tidak bisa diubah selama kurva terkunci. Buka kunci lebih dulu.');
        }

        $bersih = $this->grupMaster->periksa($grup);

        DB::transaction(function () use ($cycleId, $bersih, $olehUserId) {
            DB::table('N_PA_Cycle_Curve_Group_Level')->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')->update(['Flag_Aktif' => 'T']);
            DB::table('N_PA_Cycle_Curve_Group')->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')->update(['Flag_Aktif' => 'T']);

            foreach ($bersih as $i => $g) {
                $id = (int) DB::table('N_PA_Cycle_Curve_Group')->insertGetId([
                    'PA_Cycle_Id' => $cycleId, 'Kode_Grup' => $g['kode'], 'Nama_Grup' => $g['nama'],
                    'Urutan_Tampil' => $i + 1, 'Created_By' => $olehUserId,
                ], 'Id_PA_Cycle_Curve_Group');

                foreach ($g['level_ids'] as $l) {
                    DB::table('N_PA_Cycle_Curve_Group_Level')->insert(['PA_Cycle_Curve_Group_Id' => $id, 'PA_Cycle_Id' => $cycleId, 'ID_Level' => $l]);
                }
            }
        });

        return $this->pratinjau($cycleId);
    }

    /** @return array{kode:?string, curve_id:?int} */
    public function gradeBeku(int $participantId, float $nilai): array
    {
        $baris = DB::table('N_PA_Participant as p')
            ->join('N_PA_Cycle as c', 'c.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
            ->leftJoin('N_PA_Cycle_Curve_Group_Level as gl', function ($j) {
                $j->on('gl.PA_Cycle_Id', '=', 'p.PA_Cycle_Id')->on('gl.ID_Level', '=', 'p.ID_Level_Saat_Cycle')->where('gl.Flag_Aktif', 'Y');
            })
            ->leftJoin('N_PA_Cycle_Curve as k', function ($j) {
                $j->on('k.PA_Cycle_Curve_Group_Id', '=', 'gl.PA_Cycle_Curve_Group_Id')->where('k.Flag_Aktif', 'Y');
            })
            ->where('p.Id_PA_Participant', $participantId)
            ->first(['c.Metode_Grade', 'k.Id_PA_Cycle_Curve', 'p.PA_Cycle_Id']);

        if ($baris === null || $baris->Metode_Grade !== 'KURVA' || $baris->Id_PA_Cycle_Curve === null) {
            if ($baris !== null && $baris->Metode_Grade === 'KURVA' && $this->terkunci((int) $baris->PA_Cycle_Id)) {
                throw new RuntimeException("Peserta {$participantId} tidak punya kurva beku: levelnya tidak masuk grup mana pun saat kurva dikunci.");
            }

            return ['kode' => null, 'curve_id' => null];
        }

        $kode = PaKurvaStatistik::gradeUntuk($this->pitaBeku((int) $baris->Id_PA_Cycle_Curve), $nilai);

        if ($kode === null) {
            throw new RuntimeException("Nilai akhir {$nilai} peserta {$participantId} tidak masuk satu pun pita kurva beku.");
        }

        return ['kode' => $kode, 'curve_id' => (int) $baris->Id_PA_Cycle_Curve];
    }

    /* ------------------------------------------------------------------ */

    /** @param list<array> $pita */
    private function beriGrade(int $cycleId, int $grupId, int $kurvaId, array $pita): void
    {
        foreach ($pita as $p) {
            if ($p['nilai_sampai'] <= $p['nilai_dari']) {
                continue;
            }

            // Set-based per pita: SATU pernyataan untuk semua orang di rentang ini.
            DB::update(
                "UPDATE N_PA_Result
                 SET Kode_Grade_Sistem = ?,
                     Kode_Grade_Final = CASE WHEN Flag_Dikalibrasi = 'Y' THEN Kode_Grade_Final ELSE ? END,
                     PA_Cycle_Curve_Id = ?
                 WHERE Flag_Aktif = 'Y' AND Status_Hasil = 'DIHITUNG'
                   AND Final_Score >= ? AND Final_Score < ?
                   AND PA_Participant_Id IN (
                       SELECT p.Id_PA_Participant FROM N_PA_Participant p
                       JOIN N_PA_Cycle_Curve_Group_Level gl
                         ON gl.PA_Cycle_Id = p.PA_Cycle_Id AND gl.ID_Level = p.ID_Level_Saat_Cycle AND gl.Flag_Aktif = 'Y'
                       WHERE p.PA_Cycle_Id = ? AND p.Status_Peserta = 'DIIKUTKAN' AND gl.PA_Cycle_Curve_Group_Id = ?
                   )",
                [$p['kode'], $p['kode'], $kurvaId, $p['nilai_dari'], $p['nilai_sampai'], $cycleId, $grupId],
            );
        }
    }

    /** @return list<array{kode:string, nama:string, batas_z:?float, urutan:int}> */
    private function gradeSiklus(int $cycleId): array
    {
        $baris = DB::table('N_PA_Cycle_Score_Range_Snapshot')->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')
            ->where('Score_Type', 'FINAL')->where('Score_Usage', 'GRADE')
            ->orderBy('Urutan_Tampil')->get(['Kode_Range', 'Nama_Range', 'Batas_Z', 'Urutan_Tampil']);

        if ($baris->whereNotNull('Batas_Z')->isEmpty()) {
            return [];
        }

        return $baris->map(fn ($b) => [
            'kode' => (string) $b->Kode_Range, 'nama' => (string) $b->Nama_Range,
            'batas_z' => $b->Batas_Z === null ? null : (float) $b->Batas_Z, 'urutan' => (int) $b->Urutan_Tampil,
        ])->values()->all();
    }

    /** @return list<array{id:int, kode:string, nama:string, level:list<array{id:int, nama:string}>}> */
    private function grupSiklus(int $cycleId): array
    {
        $anggota = DB::table('N_PA_Cycle_Curve_Group_Level as gl')->join('HRIS_Level as l', 'l.ID_Level', '=', 'gl.ID_Level')
            ->where('gl.PA_Cycle_Id', $cycleId)->where('gl.Flag_Aktif', 'Y')->orderBy('gl.ID_Level')
            ->get(['gl.PA_Cycle_Curve_Group_Id', 'gl.ID_Level', 'l.Keterangan'])->groupBy('PA_Cycle_Curve_Group_Id');

        return DB::table('N_PA_Cycle_Curve_Group')->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')
            // Id sebagai pemutus seri: urutan kembar membuat urutan grup berubah antar-muat.
            ->orderBy('Urutan_Tampil')->orderBy('Id_PA_Cycle_Curve_Group')
            ->get(['Id_PA_Cycle_Curve_Group', 'Kode_Grup', 'Nama_Grup'])
            ->map(fn ($g) => [
                'id' => (int) $g->Id_PA_Cycle_Curve_Group, 'kode' => (string) $g->Kode_Grup, 'nama' => (string) $g->Nama_Grup,
                'level' => collect($anggota->get($g->Id_PA_Cycle_Curve_Group, []))
                    ->map(fn ($l) => ['id' => (int) $l->ID_Level, 'nama' => (string) $l->Keterangan])->values()->all(),
            ])->values()->all();
    }

    /** Hasil DIHITUNG peserta DIIKUTKAN beserta grup siklusnya (NULL = level tanpa grup). */
    private function hasilDihitung(int $cycleId): Collection
    {
        return DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->leftJoin('N_PA_Cycle_Curve_Group_Level as gl', function ($j) {
                $j->on('gl.PA_Cycle_Id', '=', 'p.PA_Cycle_Id')->on('gl.ID_Level', '=', 'p.ID_Level_Saat_Cycle')->where('gl.Flag_Aktif', 'Y');
            })
            ->where('p.PA_Cycle_Id', $cycleId)->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('r.Flag_Aktif', 'Y')->whereIn('r.Status_Hasil', ['DIHITUNG', 'TERBIT'])->whereNotNull('r.Final_Score')
            ->get(['r.Final_Score', 'p.ID_Level_Saat_Cycle', 'gl.PA_Cycle_Curve_Group_Id as grup_id'])
            ->map(function ($r) {
                $r->grup_id = $r->grup_id === null ? null : (int) $r->grup_id;

                return $r;
            });
    }

    private function kurvaAktif(int $cycleId): Collection
    {
        return DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')
            ->get(['Id_PA_Cycle_Curve', 'PA_Cycle_Curve_Group_Id', 'Jumlah_Peserta', 'Rata_Rata', 'Simpangan_Baku'])
            ->keyBy(fn ($k) => (int) $k->PA_Cycle_Curve_Group_Id);
    }

    /** @return list<array{kode:string, nama:string, batas_z:?float, nilai_dari:float, nilai_sampai:float, urutan:int}> */
    private function pitaBeku(int $kurvaId): array
    {
        return DB::table('N_PA_Cycle_Curve_Band')->where('PA_Cycle_Curve_Id', $kurvaId)->orderBy('Urutan_Tampil')
            ->get(['Kode_Range', 'Nama_Range', 'Batas_Z', 'Nilai_Dari', 'Nilai_Sampai', 'Urutan_Tampil'])
            ->map(fn ($b) => [
                'kode' => (string) $b->Kode_Range, 'nama' => (string) $b->Nama_Range,
                'batas_z' => $b->Batas_Z === null ? null : (float) $b->Batas_Z,
                'nilai_dari' => (float) $b->Nilai_Dari, 'nilai_sampai' => (float) $b->Nilai_Sampai, 'urutan' => (int) $b->Urutan_Tampil,
            ])->values()->all();
    }

    /** @return list<string> */
    private function peringatanGrup(array $g, array $stat): array
    {
        $p = [];

        if ($stat['n'] < self::MIN_GRUP) {
            $p[] = "Grup {$g['kode']} hanya {$stat['n']} orang (di bawah ".self::MIN_GRUP.'); kurvanya kurang bermakna.';
        }

        if ($stat['n'] >= 1 && ($stat['sd'] === null || $stat['sd'] <= 0.0)) {
            $p[] = "Grup {$g['kode']} tidak punya sebaran; semua anggotanya mendapat grade rata-rata.";
        }

        return $p;
    }

    private function jumlahTerbit(int $cycleId): int
    {
        return (int) DB::table('N_PA_Result as r')->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)->where('r.Flag_Aktif', 'Y')->where('r.Status_Hasil', 'TERBIT')->count();
    }
}
```

Catatan untuk pelaksana: pada uji `test_kunci_memberi_grade...`, grup rendah [50,55,60,65,80] → μ 62, σ 11,51; 80 → z 1,56 → B_PLUS. Grup tinggi [70..90] → μ 80, σ 7,91; 80 → z 0 → B. Tambahkan `use App\Services\KPI\PerformanceAppraisal\Kurva\PaGrupKurvaMasterService;` tidak perlu (satu namespace).

- [ ] **Langkah 4: Commit**

```bash
php -l app/Services/KPI/PerformanceAppraisal/Kurva/PaKurvaService.php && php -l app/Services/KPI/PerformanceAppraisal/PaCycleGate.php
vendor/bin/pint app/Services/KPI/PerformanceAppraisal/Kurva/PaKurvaService.php app/Services/KPI/PerformanceAppraisal/PaCycleGate.php tests/Feature/KPI/PerformanceAppraisal/KurvaKunciTest.php tests/Feature/KPI/PerformanceAppraisal/CycleGateTest.php
git add app/Services/KPI/PerformanceAppraisal/Kurva/PaKurvaService.php app/Services/KPI/PerformanceAppraisal/PaCycleGate.php tests/Feature/KPI/PerformanceAppraisal/KurvaKunciTest.php tests/Feature/KPI/PerformanceAppraisal/CycleGateTest.php
git commit -m "feat(pa): PaKurvaService mengunci kurva grade per grup level"
```

---

### Tugas 6: Perhitungan, kalibrasi forum, dan penerbitan memakai kurva

**Berkas:**
- Ubah: `app/Services/KPI/PerformanceAppraisal/PaResultCalculationService.php` (konstruktor, `konteks()`, blok `$grade = $this->cariPita(...)` sekitar baris 464, `update` hasil sekitar baris 509)
- Ubah: `app/Services/KPI/PerformanceAppraisal/PaCalibrationService.php:85`
- Ubah: `app/Services/KPI/PerformanceAppraisal/PaResultPublishService.php:64,131,238`
- Uji: `tests/Feature/KPI/PerformanceAppraisal/KurvaPerhitunganTest.php`

**Interfaces:**
- Consumes: `PaKurvaService::gradeBeku()`, `pastikanTerkunci()`.
- Produces: pada siklus `KURVA`, `hitung()` mengisi `Kode_Grade_Sistem` dari pita beku (NULL sebelum kunci) dan `PA_Cycle_Curve_Id`; `kalibrasi()`, `terbitkan()`, `terbitkanPeserta()`, `tutupPenerbitan()` menolak siklus `KURVA` yang belum dikunci.

- [ ] **Langkah 1: Uji**

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use App\Services\KPI\PerformanceAppraisal\PaResultPublishService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class KurvaPerhitunganTest extends TestCase
{
    use DatabaseTransactions;

    public function test_penerbitan_menolak_siklus_kurva_yang_belum_dikunci(): void
    {
        $cycleId = (int) DB::table('N_PA_Cycle')->where('Status_Cycle', 'CLOSED')->value('Id_PA_Cycle');

        if ($cycleId === 0) {
            $this->markTestSkipped('Tidak ada siklus CLOSED di dev.');
        }

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->update(['Metode_Grade' => 'KURVA']);
        DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $cycleId)->update(['Flag_Aktif' => 'T', 'Unlocked_At' => now(), 'Alasan_Buka' => 'uji']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Kunci kurva');
        app(PaResultPublishService::class)->terbitkan($cycleId, null);
    }

    public function test_perhitungan_siklus_pita_tidak_menyentuh_kurva(): void
    {
        $this->assertSame(['kode' => null, 'curve_id' => null], app(PaKurvaService::class)->gradeBeku(
            (int) DB::table('N_PA_Participant as p')->join('N_PA_Cycle as c', 'c.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
                ->where('c.Metode_Grade', 'PITA')->value('p.Id_PA_Participant'),
            80.0,
        ));
    }

    public function test_hitung_memanggil_grade_beku_pada_siklus_kurva(): void
    {
        $sumber = file_get_contents(base_path('app/Services/KPI/PerformanceAppraisal/PaResultCalculationService.php'));

        // Penjaga struktural: jalur KURVA harus lewat gradeBeku, bukan cariPita.
        $this->assertStringContainsString('$this->kurva->gradeBeku(', $sumber);
        $this->assertStringContainsString("'PA_Cycle_Curve_Id' =>", $sumber);
    }
}
```

Tambahkan juga ke `KurvaKunciTest` (siklus tiruan dari Tugas 5) satu uji end-to-end yang memanggil `PaResultCalculationService::hitung()` tidak praktis tanpa KPI/penilai lengkap — penggantinya adalah bukti `pa:verify` di Tugas 12 Langkah 5.

- [ ] **Langkah 2: Perhitungan** — suntikkan `private readonly PaKurvaService $kurva` di konstruktor. Di `konteks()` tambahkan `'Metode_Grade'` ke kolom `first([...])` dan simpan `'metode_grade' => (string) ($siklus?->Metode_Grade ?? 'PITA')` ke array konteks. Ganti blok pencarian grade:

```php
                $kurvaId = null;

                if ($this->konteks((int) $peserta->PA_Cycle_Id)['metode_grade'] === 'KURVA') {
                    /* Grade dari kurva BEKU grup peserta; NULL selama kurva belum dikunci —
                       sebelum itu grade hanya dibaca sebagai pratinjau di layar Distribusi. */
                    $beku = $this->kurva->gradeBeku($participantId, $final);
                    $grade = $beku['kode'];
                    $kurvaId = $beku['curve_id'];
                } else {
                    $grade = $this->cariPita(
                        (int) $peserta->PA_Cycle_Id, 'FINAL', 'GRADE', $final
                    )?->Kode_Range;

                    if ($grade === null) {
                        throw new RuntimeException(/* pesan lama apa adanya */);
                    }
                }
```

(pertahankan teks pesan `RuntimeException` lama persis; pindahkan `throw` lama ke dalam cabang `else`). Deklarasikan `$kurvaId = null;` juga di samping `$grade = null;` di awal blok supaya hasil `TERTAHAN` menulis NULL. Di array `update([...])` hasil tambahkan `'PA_Cycle_Curve_Id' => $kurvaId,`.

- [ ] **Langkah 3: Kalibrasi forum dan penerbitan** — suntikkan `PaKurvaService $kurva` ke `PaCalibrationService` dan `PaResultPublishService`; tepat sesudah setiap panggilan gerbang:

```php
        $this->gate->pastikanBoleh(PaCycleGate::KALIBRASI, (int) $hasil->PA_Cycle_Id);
        $this->kurva->pastikanTerkunci((int) $hasil->PA_Cycle_Id);
```

```php
        $this->gate->pastikanBoleh(PaCycleGate::TERBITKAN, $cycleId);
        $this->kurva->pastikanTerkunci($cycleId);
```

Untuk `terbitkanPeserta()` ambil `cycleId` peserta (`DB::table('N_PA_Participant')->where('Id_PA_Participant', $participantId)->value('PA_Cycle_Id')`) lalu panggil `pastikanTerkunci`.

- [ ] **Langkah 4: Commit**

```bash
php -l app/Services/KPI/PerformanceAppraisal/PaResultCalculationService.php && php -l app/Services/KPI/PerformanceAppraisal/PaCalibrationService.php && php -l app/Services/KPI/PerformanceAppraisal/PaResultPublishService.php
vendor/bin/pint app/Services/KPI/PerformanceAppraisal/PaResultCalculationService.php app/Services/KPI/PerformanceAppraisal/PaCalibrationService.php app/Services/KPI/PerformanceAppraisal/PaResultPublishService.php tests/Feature/KPI/PerformanceAppraisal/KurvaPerhitunganTest.php
git add app/Services/KPI/PerformanceAppraisal/PaResultCalculationService.php app/Services/KPI/PerformanceAppraisal/PaCalibrationService.php app/Services/KPI/PerformanceAppraisal/PaResultPublishService.php tests/Feature/KPI/PerformanceAppraisal/KurvaPerhitunganTest.php
git commit -m "feat(pa): perhitungan, kalibrasi forum, dan penerbitan mengikuti kurva beku"
```

---

### Tugas 7: `pa:verify` memeriksa kurva

**Berkas:**
- Ubah: `app/Console/Commands/KPI/PerformanceAppraisal/PaVerifyCommand.php`

**Interfaces:**
- Produces: `periksaKurva(int $id)` dipanggil di `periksaSatu()` sebelum `periksaHasilTertahan`; di `periksaHasilTerhitung()` pita untuk siklus `KURVA` dibaca per hasil dari `N_PA_Cycle_Curve_Band` lewat `r.PA_Cycle_Curve_Id` (independen, bukan lewat `PaKurvaService`).

- [ ] **Langkah 1: Implementasi**

Di `periksaSatu()` tambahkan `$this->periksaKurva($id);` sebelum `$this->periksaHasilTertahan($id);`. Method baru:

```php
    /**
     * Kurva grade (siklus KURVA). Dihitung ULANG dari batas z salinan dan μ/σ yang dibekukan
     * — tidak lewat PaKurvaService, supaya pemverifikasi tidak memeriksa mesin dengan mesin.
     */
    private function periksaKurva(int $id): void
    {
        if (DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $id)->value('Metode_Grade') !== 'KURVA') {
            return;
        }

        $kurva = DB::table('N_PA_Cycle_Curve as k')
            ->join('N_PA_Cycle_Curve_Group as g', 'g.Id_PA_Cycle_Curve_Group', '=', 'k.PA_Cycle_Curve_Group_Id')
            ->where('k.PA_Cycle_Id', $id)->where('k.Flag_Aktif', 'Y')
            ->get(['k.Id_PA_Cycle_Curve', 'g.Kode_Grup', 'k.Jumlah_Peserta', 'k.Rata_Rata', 'k.Simpangan_Baku']);

        if ($kurva->isEmpty()) {
            $this->peringatkan('Kurva grade belum dikunci', 'Grade siklus bermetode KURVA baru ada sesudah admin mengunci kurva di layar Distribusi.');

            return;
        }

        $kecil = $kurva->filter(fn ($k) => (int) $k->Jumlah_Peserta < 30)
            ->map(fn ($k) => "{$k->Kode_Grup}: {$k->Jumlah_Peserta} orang")->values()->all();

        if ($kecil !== []) {
            $this->peringatkan(count($kecil).' grup kurva di bawah 30 orang', 'Kurvanya kurang bermakna; pertimbangkan menggabung level.', $kecil);
        }

        $z = DB::table('N_PA_Cycle_Score_Range_Snapshot')->where('PA_Cycle_Id', $id)->where('Flag_Aktif', 'Y')
            ->where('Score_Type', 'FINAL')->where('Score_Usage', 'GRADE')->pluck('Batas_Z', 'Kode_Range');
        $salah = [];

        foreach ($kurva as $k) {
            if ($k->Simpangan_Baku === null || (float) $k->Simpangan_Baku <= 0) {
                continue;
            }

            foreach (DB::table('N_PA_Cycle_Curve_Band')->where('PA_Cycle_Curve_Id', $k->Id_PA_Cycle_Curve)->whereNotNull('Batas_Z')->get() as $b) {
                $harus = max(0.0, round((float) $k->Rata_Rata + (float) $z[$b->Kode_Range] * (float) $k->Simpangan_Baku, 4));

                if (abs($harus - (float) $b->Nilai_Dari) > 0.0001) {
                    $salah[] = sprintf('%s %s: pita %s, seharusnya %s', $k->Kode_Grup, $b->Kode_Range, $b->Nilai_Dari, $harus);
                }
            }
        }

        if ($salah !== []) {
            $this->halangi('Pita kurva tidak sama dengan μ + z·σ', 'Pita beku disunting di luar aplikasi atau batas z salinan berubah sesudah dikunci.', $salah);
        }
    }
```

Di `periksaHasilTerhitung()`: tambahkan `'r.PA_Cycle_Curve_Id'` ke kolom `get([...])` hasil, muat `$pitaKurva = DB::table('N_PA_Cycle_Curve_Band')->whereIn('PA_Cycle_Curve_Id', $hasil->pluck('PA_Cycle_Curve_Id')->filter()->unique())->get(['PA_Cycle_Curve_Id','Kode_Range','Nilai_Dari','Nilai_Sampai'])->groupBy('PA_Cycle_Curve_Id');`, lalu ganti loop pita per hasil menjadi:

```php
            $pitaHasil = $r->PA_Cycle_Curve_Id !== null ? ($pitaKurva[$r->PA_Cycle_Curve_Id] ?? collect()) : $pita;
            $grade = null;

            if ($final !== null && ! ($metode === 'KURVA' && $r->PA_Cycle_Curve_Id === null)) {
                foreach ($pitaHasil as $p) {
                    if ((float) $p->Nilai_Dari <= $final && $final < (float) $p->Nilai_Sampai) {
                        $grade = $p->Kode_Range;
                        break;
                    }
                }
            }
```

dengan `$metode = DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $id)->value('Metode_Grade');` dibaca sekali di awal method.

- [ ] **Langkah 2: Commit**

```bash
php -l app/Console/Commands/KPI/PerformanceAppraisal/PaVerifyCommand.php
vendor/bin/pint app/Console/Commands/KPI/PerformanceAppraisal/PaVerifyCommand.php
git add app/Console/Commands/KPI/PerformanceAppraisal/PaVerifyCommand.php
git commit -m "feat(pa): pa:verify memeriksa kurva grade secara independen"
```

---

### Tugas 8: HTTP — rute, controller, fixture, kontrak

**Berkas:**
- Ubah: `app/Http/Controllers/KPI/PerformanceAppraisal/Report/PaReportController.php` (baca kurva)
- Ubah: `app/Http/Controllers/KPI/PerformanceAppraisal/Cycle/PaCycleAdminController.php` (kunci, buka, metode, grup siklus)
- Ubah: `app/Http/Controllers/KPI/PerformanceAppraisal/MasterData/PaMasterDataController.php` (halaman + baca/simpan grup kurva)
- Ubah: `routes/KPI/PerformanceAppraisal/Cycle/PerformanceAppraisalCycle.php`, `routes/KPI/PerformanceAppraisal/MasterData/PerformanceAppraisalMaster.php`
- Buat: fixture `resources/js/Pages/KPI/PerformanceAppraisal/api/fixtures/kurva-siklus.json`, `master-grup-kurva.json`
- Ubah: `tests/Feature/KPI/PerformanceAppraisal/KontrakLaporanTest.php` (bentuk `kurva-siklus.json`), `docs/superpowers/specs/2026-09-01-kontrak-api-performance-appraisal.md`

**Interfaces:**
- `GET /api/v1/performance-appraisal/cycles/{cycleId}/kurva` → `PaKurvaService::pratinjau()` (blok baca `PaHasilPage`).
- `POST /api/v1/performance-appraisal/cycles/{cycleId}/kurva/kunci` → `kunci()`.
- `POST /api/v1/performance-appraisal/cycles/{cycleId}/kurva/buka` (`{alasan}`) → `buka()`.
- `PUT /api/v1/performance-appraisal/cycles/{cycleId}/kurva/metode` (`{metode}`) → `ubahMetode()`.
- `PUT /api/v1/performance-appraisal/cycles/{cycleId}/kurva/grup` (`{grup:[{kode,nama,level_ids[]}]}`) → `aturGrupSiklus()`.
- Tulis empat di atas di grup rute `check.jabatan:PaHasilPage` yang sudah memuat `terbitkan`.
- `GET /performance-appraisal/master/grup-kurva` (halaman Inertia `KPI/PerformanceAppraisal/MasterData/grup-kurva`), `GET /api/v1/performance-appraisal/master/grup-kurva`, `PUT /api/v1/performance-appraisal/master/grup-kurva` (`{grup:[...]}`) — gerbang rute master ditambah `PaMasterGrupKurvaPage` pada daftar `check.jabatan` master; controller memanggil `pastikanBolehMaster()`-setara: `abort_unless($request->user()?->memilikiJabatan('PaMasterGrupKurvaPage'), 403)`.

- [ ] **Langkah 1: Controller** — pola sama dengan `terbitkan()`:

```php
    public function kunciKurva(int $cycleId)
    {
        return $this->jalankan(fn () => $this->kurva->kunci($cycleId, $this->userId()));
    }

    public function bukaKurva(Request $request, int $cycleId)
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'max:500']]);

        return $this->jalankan(fn () => $this->kurva->buka($cycleId, $data['alasan'], $this->userId()));
    }

    public function ubahMetodeGrade(Request $request, int $cycleId)
    {
        $data = $request->validate(['metode' => ['required', 'in:PITA,KURVA']]);

        return $this->jalankan(fn () => $this->kurva->ubahMetode($cycleId, $data['metode'], $this->userId()));
    }

    public function aturGrupKurva(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'grup' => ['required', 'array', 'min:1', 'max:50'],
            'grup.*.kode' => ['required', 'string', 'max:40'],
            'grup.*.nama' => ['required', 'string', 'max:200'],
            'grup.*.level_ids' => ['required', 'array', 'min:1'],
            'grup.*.level_ids.*' => ['required', 'integer'],
        ]);

        return $this->jalankan(fn () => $this->kurva->aturGrupSiklus($cycleId, $data['grup'], $this->userId()));
    }
```

`PaReportController::kurva(int $cycleId)` → `$this->jawab(fn () => $this->kurva->pratinjau($cycleId))`. `PaMasterDataController`: `halamanGrupKurva()` → `inertia('KPI/PerformanceAppraisal/MasterData/grup-kurva')`; `daftarGrupKurva()`/`simpanGrupKurva(Request)` (validasi sama dengan `aturGrupKurva`) memanggil `PaGrupKurvaMasterService`.

- [ ] **Langkah 2: Rute** — sisipkan di blok baca siklus (dekat `/cycles/{cycleId}/distribusi`): `Route::get('/cycles/{cycleId}/kurva', [PaReportController::class, 'kurva'])->whereNumber('cycleId');` dan di blok `PaHasilPage` tulis (dekat `terbitkan`):

```php
        // Kurva grade: kunci membekukan μ/σ per grup; buka wajib alasan, ditolak bila sudah ada terbit.
        Route::post('/cycles/{cycleId}/kurva/kunci', [PaCycleAdminController::class, 'kunciKurva'])->whereNumber('cycleId');
        Route::post('/cycles/{cycleId}/kurva/buka', [PaCycleAdminController::class, 'bukaKurva'])->whereNumber('cycleId');
        Route::put('/cycles/{cycleId}/kurva/metode', [PaCycleAdminController::class, 'ubahMetodeGrade'])->whereNumber('cycleId');
        Route::put('/cycles/{cycleId}/kurva/grup', [PaCycleAdminController::class, 'aturGrupKurva'])->whereNumber('cycleId');
```

Master: tambahkan `PaMasterGrupKurvaPage` ke string `check.jabatan:` gerbang master; rute halaman `/performance-appraisal/master/grup-kurva` DI ATAS rute berpola `{master}`; API `Route::get('/grup-kurva', ...)`, `Route::put('/grup-kurva', ...)` DI ATAS `/{master}`.

- [ ] **Langkah 3: Fixture & kontrak** — `kurva-siklus.json` berisi amplop `{success, status, message, result}` dengan `result` persis bentuk `pratinjau()` (satu grup berisi dua level, enam pita, `sebaran`, satu `peringatan`, `kunci` terisi). `master-grup-kurva.json` dengan bentuk `daftar()`. Tambah uji bentuk di `KontrakLaporanTest` memakai siklus tiruan `KurvaKunciTest::siklusKurva` (pindahkan helper ke trait `Support/MenyiapkanSiklusKurva.php` bila dipakai dua kelas). Kontrak: bagian baru **§2.25 Kurva grade** dan **§4.12 Grup level kurva**, plus baris riwayat.

- [ ] **Langkah 4: Commit** (per berkas, pesan `feat(pa): API kurva grade dan master grup level kurva`).

---

### Tugas 9: Seam API frontend

**Berkas:** Ubah `resources/js/Pages/KPI/PerformanceAppraisal/api/paApi.js`; Uji `resources/js/Pages/KPI/PerformanceAppraisal/api/paApi.kurva.test.js`

```js
/** Kurva grade siklus: per grup n, μ, σ, pita, sebaran, peringatan. */
export function kurvaSiklus(cycleId) {
    return panggil('get', `/cycles/${cycleId}/kurva`);
}

export function kunciKurva(cycleId) {
    return panggil('post', `/cycles/${cycleId}/kurva/kunci`);
}

export function bukaKurva(cycleId, alasan) {
    return panggil('post', `/cycles/${cycleId}/kurva/buka`, { alasan });
}

export function ubahMetodeGrade(cycleId, metode) {
    return panggil('put', `/cycles/${cycleId}/kurva/metode`, { metode });
}

export function aturGrupKurvaSiklus(cycleId, grup) {
    return panggil('put', `/cycles/${cycleId}/kurva/grup`, { grup });
}

export function daftarGrupKurva() {
    return panggil('get', '/master/grup-kurva');
}

export function simpanGrupKurva(grup) {
    return panggil('put', '/master/grup-kurva', { grup });
}
```

Masukkan ketujuhnya ke `export default`. Uji (pola `paApi.galat.test.js`, `vi.mock('axios')`): alamat & badan tiap fungsi; `bukaKurva(7,'x')` → `POST /api/v1/performance-appraisal/cycles/7/kurva/buka` dengan `{alasan:'x'}`. Commit `feat(pa): seam API kurva grade`.

---

### Tugas 10: Layar Distribusi — `PaKurvaPanel`

**Berkas:**
- Buat: `resources/js/Pages/KPI/PerformanceAppraisal/Hasil/components/PaKurvaPanel.vue` (+ `PaKurvaPanel.test.js`)
- Ubah: `resources/js/Pages/KPI/PerformanceAppraisal/Hasil/distribusi.vue` — pasang `<PaKurvaPanel v-if="selectedCycleId" :cycle-id="Number(selectedCycleId)" @berubah="muatDistribusi()" />` tepat sebelum `<!-- SECTION 1: Analisis Kurva Normal per Tingkat Jabatan -->` (nama fungsi pemuat distribusi: cari `async function` pemanggil `paApi.distribusiSiklus` dan pakai namanya).

**Perilaku komponen (wajib, diuji):**
1. Memuat `paApi.kurvaSiklus(cycleId)` saat dipasang dan saat `cycleId` berubah; skeleton saat memuat; `HcisResultState` saat galat (kode + request id).
2. Metode `PITA`: kartu info "Siklus ini memakai pita nilai tetap" + tombol **Pindah ke kurva** (konfirmasi `HcisDialog`; mati bila `sudah_terbit > 0`) → `ubahMetodeGrade(id,'KURVA')`.
3. Metode `KURVA`: lencana **Sementara**/**Terkunci** (+ waktu kunci); peringatan global & per grup sebagai `HcisAlert` `warning`; satu kartu per grup: nama grup, daftar level, n, μ, σ (format `id-ID`, 2 desimal), tabel pita (Grade, Batas z, Rentang nilai `≥ nilai_dari` / `nilai_dari – <nilai_sampai`), sebaran per grade vs proporsi teoretis (Φ dihitung di komponen dari batas z: `Φ(z_atas) − Φ(z_bawah)`, pakai pendekatan `erf` numerik).
4. Tombol **Kunci kurva** (primary; hanya bila belum terkunci): dialog ringkasan jumlah grup, jumlah peserta, semua peringatan; konfirmasi → `kunciKurva`, lalu `hcisMessage.success('Kurva terkunci.')` dan emit `berubah`.
5. Tombol **Buka kunci** (danger; hanya bila terkunci): mati dengan keterangan bila `sudah_terbit > 0`; dialog dengan `HcisTextarea id="pa-kurva-alasan" label="Alasan membuka kunci"` wajib 5–500 karakter → `bukaKurva`.
6. Tombol **Atur grup** (hanya bila belum terkunci): dialog berisi satu baris per level (dari seluruh `grup[].level`) dengan `HcisSelect` grup tujuan + tombol tambah grup baru (kode, nama) → `aturGrupKurvaSiklus`.
7. `hasil_sesudah_kunci > 0` → `HcisAlert info` "N hasil dihitung sesudah kunci memakai pita beku".

Uji `PaKurvaPanel.test.js` (mock `paApi`, stub `HcisDialog`/`HcisSelect` seperti `jenis-penilai.test.js`): menampilkan μ/σ/pita dari fixture `kurva-siklus.json`; tombol Kunci memanggil `kunciKurva` hanya sesudah konfirmasi; Buka menolak alasan < 5 karakter tanpa memanggil API; Buka mati bila `sudah_terbit > 0`; PITA menampilkan Pindah ke kurva. Commit `feat(pa): panel kurva grade di layar Distribusi`.

---

### Tugas 11: Master — Grup Level Kurva & batas z di Pita Nilai; lencana grade sementara

**Berkas:**
- Buat: `resources/js/Pages/KPI/PerformanceAppraisal/MasterData/grup-kurva.vue` (+ `grup-kurva.test.js`)
- Ubah: `resources/js/Pages/KPI/PerformanceAppraisal/MasterData/components/PaPitaNilaiEditorDialog.vue` (field `batas_z`), `PaPitaNilaiView.vue` (kolom z untuk `FINAL|GRADE`)
- Ubah: tempat grade ditampilkan di Pemantauan/detail hasil (`grep -rn "Kode_Grade_Sistem\|Kode_Grade_Final" resources/js/Pages/KPI/PerformanceAppraisal --include=*.vue`) — tampilkan "Menunggu kunci kurva" bila grade NULL dan hasil `DIHITUNG`.

**Perilaku:**
1. `grup-kurva.vue`: memuat `daftarGrupKurva()`; kartu per grup (kode, nama, level sebagai lencana dengan cacah peserta siklus terakhir, total cacah; merah bila < 30); daftar "Level tanpa grup" sebagai peringatan; tambah grup; pindah level lewat `HcisSelect` per level; tombol **Simpan** mengirim seluruh konfigurasi (`simpanGrupKurva`) dan menampilkan galat 422 apa adanya. Layout mengikuti `jenis-penilai.vue` (HcisHeader + menu master yang sama).
2. Editor pita: bila `sumbu === 'FINAL' && kegunaan === 'GRADE'`, tampilkan `HcisInputNumber` **Batas z (≥)** (`precision 2`, `min -9.99`, `max 9.99`, boleh kosong = grade terendah) dengan pratinjau "Contoh μ 70, σ 15 → mulai dari {70 + z·15}"; kirim `batas_z`.
3. View pita: untuk baris grade tampilkan `z ≥ {Batas_Z}` atau "terendah".

Uji: grup-kurva menyimpan konfigurasi lengkap; level tanpa grup disorot; editor mengirim `batas_z` hanya untuk grade. Commit `feat(pa): master grup level kurva dan batas z di pita nilai`.

---

### Tugas 12: Verifikasi — HANYA setelah ACC pemilik proyek

- [ ] **Langkah 1:** laporkan apa yang ditulis dan belum dijalankan; minta lingkup (`feature` / `impacted` / `full`).
- [ ] **Langkah 2 (feature):** `php vendor/bin/phpunit tests/Unit/KPI/PerformanceAppraisal/PaKurvaStatistikTest.php`; lalu satu suite satu waktu: `unset DATABASE_URL && php artisan test tests/Feature/KPI/PerformanceAppraisal/GrupKurvaMasterTest.php tests/Feature/KPI/PerformanceAppraisal/SalinanKurvaTest.php tests/Feature/KPI/PerformanceAppraisal/KurvaKunciTest.php tests/Feature/KPI/PerformanceAppraisal/KurvaPerhitunganTest.php tests/Feature/KPI/PerformanceAppraisal/CycleGateTest.php tests/Feature/KPI/PerformanceAppraisal/MasterDataTest.php`.
- [ ] **Langkah 3 (penjaga merah):** hapus sementara `pastikanTerkunci` di `PaResultPublishService::terbitkan` → `KurvaPerhitunganTest` harus MERAH; hapus penolakan terbit di `buka()` → `KurvaKunciTest::test_buka_wajib_alasan...` MERAH; kembalikan, `git diff` bersih.
- [ ] **Langkah 4:** `npx vitest run resources/js/Pages/KPI/PerformanceAppraisal` — uji baru lulus, kegagalan lama tetap 11.
- [ ] **Langkah 5 (end-to-end, digulung balik):** skrip tinker dalam satu transaksi `DB::rollBack()` di akhir: PA-2026-11 → `ubahMetode('KURVA')` → atur grup (gabung Senior Staff + Staff, Senior Officer + Officer) → hitung ulang seluruh peserta → `kunci()` → `pa:verify PA-2026-11` tanpa temuan kurva → cetak sebaran per grup.
- [ ] **Langkah 6:** browser lewat vhost (pastikan `public/hot` menunjuk Vite hidup): Distribusi (sementara → kunci → buka), master Grup Level Kurva, editor pita z.
- [ ] **Langkah 7:** `vendor/bin/pint --test` berkas PHP yang berubah; `node .github/scripts/penjaga-perubahan-db.mjs --base origin/master --head HEAD`.

### Tugas 13: Dokumentasi

Spec: bagian **Revisi r2** (DEFAULT `PITA`, `periksa()` publik, hal lain yang berubah saat implementasi). `plans/kpi/pa-papan-kerja.md` §7: entri bertanggal. Commit `docs(pa): revisi spec grade kurva dan papan kerja`.

---

## Tinjauan mandiri rencana

**Cakupan spec:** §4.1 → T1, T3 · §4.2 → T1, T4 · §4.3 → T1, T5 · §5.1–5.6 → T5, T6 · §5.7 → T7 · §6.1–6.2 → T11 · §6.3 → T10 · §6.4 → T11 · §7 → T1 (DEFAULT PITA), T4, T5 (`ubahMetode`) · §8 → T2 (Excel, G8), T5 (per grup, kunci, buka, σ=0), T6 (gerbang, PITA tak tersentuh), T7 (verify).

**Konsistensi nama:** `PaKurvaStatistik::{ringkas,pita,gradeUntuk,pastikanBatasZ,ATAS}`, `PaKurvaService::{metode,terkunci,pastikanTerkunci,pratinjau,kunci,buka,ubahMetode,aturGrupSiklus,gradeBeku,MIN_GRUP}`, `PaGrupKurvaMasterService::{daftar,simpan,periksa}`, `PaCyclePreparationService::salinGrupKurva`, gerbang `KUNCI_KURVA`/`BUKA_KURVA` — dipakai sama di T2–T11.

**Batas yang disengaja:** uji end-to-end `hitung()` penuh diganti bukti `pa:verify` di T12 L5 (menyiapkan KPI + penilai lengkap untuk uji unit terlalu mahal); grade sementara tidak disimpan (spec §5.1).
