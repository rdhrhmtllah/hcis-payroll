-- database/ddl/DDL-2026-128-rekap-struktur-production/forward.sql
-- Target eksekusi: production (emi_db_web_hr) lewat SQL Tracker.
--
-- STRUKTUR SAJA: 7 tabel baru dan 11 kolom baru. Tanpa satu pun CREATE INDEX,
-- ALTER TABLE ADD CONSTRAINT, atau DROP INDEX — itu semua di DDL-2026-129.
--
-- URUTAN: PERTAMA. DDL-2026-129 memasang indeks di atas tabel yang lahir di sini.
--
-- Seluruh pernyataan berpenjaga (IF NOT EXISTS / COL_LENGTH / OBJECT_ID),
-- jadi berkas ini aman diulang. Isinya disalin apa adanya dari berkas DDL
-- yang sudah ditinjau dan dijalankan di development; yang dicabut hanya
-- penjaga "hanya Web_HRIS" pada DDL-087/088/090, sebab sasaran berkas ini
-- memang production.

SET NOCOUNT ON;
GO
-- database/ddl/DDL-2026-128-rekap-skema-production-lanjutan/forward.sql
-- Target eksekusi: production (emi_db_web_hr) lewat SQL Tracker.
-- Dokumentasi: docs/changes/DDL-2026-128-rekap-skema-production-lanjutan/README.md
--
-- APA INI
-- Satu berkas yang menutup SELURUH selisih skema production terhadap master
-- pada 21 Sep 2026, diverifikasi langsung ke production (read-only):
--     7 tabel, 11 kolom, dan 36 indeks/constraint yang belum ada.
--
-- ISINYA BUKAN SQL BARU. Tiap bagian disalin apa adanya dari berkas DDL yang
-- sudah ditinjau dan dijalankan di development, supaya yang naik ke production
-- adalah teks yang sama dengan yang diuji — bukan tulisan ulang yang mirip.
--
-- AMAN DIULANG. Setiap pernyataan berpenjaga (IF NOT EXISTS / COL_LENGTH /
-- OBJECT_ID), jadi menjalankannya dua kali tidak menggandakan apa pun dan
-- tidak menggagalkan sisa berkas.
--
-- TIDAK ADA DI SINI, dan sengaja:
--   DDL-2026-067 (RCSI)            — ALTER DATABASE tingkat basis data.
--                                    Production juga masih RCSI=0. Ini
--                                    keputusan tersendiri, butuh jendela
--                                    pemeliharaan, bukan tumpangan rekap.
--   DDL-2026-089 (indeks kembar)   — berisi DROP INDEX. Rekap ini hanya
--                                    MENAMBAH; pembuangan diputuskan terpisah.
--   DDL-2026-059 (kategori NULL)   — sudah berlaku di production; kedua kolom
--                                    Id_Kategori_SOP terbukti sudah NULLABLE.
--   DDL-2026-101/102/103/114       — seluruh objeknya sudah tercakup Bagian C
--                                    lewat DDL-2026-118.
--
-- Bagian C memuat TIGA DROP INDEX bawaan DDL-2026-118: ketiganya penggantian
-- indeks yang definisinya kedaluwarsa, berpenjaga, dan sudah ditinjau di
-- change tersebut. Tidak ada DROP TABLE, DROP COLUMN, TRUNCATE, UPDATE,
-- DELETE, atau MERGE di berkas ini.

SET NOCOUNT ON;
GO

/* ==========================================================================
   BAGIAN A — SOP: master, kolom dokumen, dan indeks pencariannya
   ========================================================================== */


-- ------------------------------------------------------------------------
-- SUMBER: database/ddl/DDL-2026-016-sop-jenis-dokumen-kepemilikan/forward.sql
-- ------------------------------------------------------------------------
-- database/ddl/DDL-2026-016-sop-jenis-dokumen-kepemilikan/forward.sql
-- Target: Web_HRIS (development). Jalankan manual di production lewat SQL Tracker.
-- Dokumentasi: docs/changes/DDL-2026-016-sop-jenis-dokumen-kepemilikan/README.md
--
-- Seluruh pernyataan di berkas ini bersifat MENAMBAH. Tidak ada kolom, baris,
-- indeks, atau constraint yang dihapus atau ditimpa.

IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_SOP_Master_Jenis_Dokumen')
CREATE TABLE N_SOP_Master_Jenis_Dokumen (
    Id_SOP_Jenis_Dokumen  int           IDENTITY(1,1) NOT NULL
        CONSTRAINT PK_SOP_Jenis_Dokumen PRIMARY KEY,
    Kode_Jenis            varchar(30)   NOT NULL,
    Nama_Jenis            nvarchar(100) NOT NULL,
    Level_Hirarki         int           NOT NULL,
    Deskripsi             nvarchar(500) NULL,
    Urutan                int           NOT NULL DEFAULT 0,
    Flag_Aktif            char(1)       NOT NULL DEFAULT 'Y',
    Created_At            datetime2(0)  NOT NULL DEFAULT (SYSDATETIME()),
    Created_By            int           NULL,
    Updated_At            datetime2(0)  NULL,
    Updated_By            int           NULL
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_SOP_Master_Jenis_Dokumen_Tipe_Item')
CREATE TABLE N_SOP_Master_Jenis_Dokumen_Tipe_Item (
    Id_SOP_Jenis_Dokumen_Tipe_Item int          IDENTITY(1,1) NOT NULL
        CONSTRAINT PK_SOP_Jenis_Dokumen_Tipe_Item PRIMARY KEY,
    Jenis_Dokumen_Id               int          NOT NULL,
    Tipe_Item                      varchar(12)  NOT NULL,
    Flag_Aktif                     char(1)      NOT NULL DEFAULT 'Y',
    Created_At                     datetime2(0) NOT NULL DEFAULT (SYSDATETIME()),
    Created_By                     int          NULL,
    CONSTRAINT FK_SOP_Jenis_Tipe_Jenis FOREIGN KEY (Jenis_Dokumen_Id)
        REFERENCES N_SOP_Master_Jenis_Dokumen (Id_SOP_Jenis_Dokumen)
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('N_SOP_Dokumen') AND name = 'Jenis_Dokumen_Id')
ALTER TABLE N_SOP_Dokumen ADD Jenis_Dokumen_Id int NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('N_SOP_Dokumen') AND name = 'Divisi_Id')
ALTER TABLE N_SOP_Dokumen ADD Divisi_Id bigint NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('N_SOP_Dokumen') AND name = 'Sub_Divisi_Id')
ALTER TABLE N_SOP_Dokumen ADD Sub_Divisi_Id bigint NULL;
GO

-- ------------------------------------------------------------------------
-- SUMBER: database/ddl/DDL-2026-019-hris-master-entitas/forward.sql
-- ------------------------------------------------------------------------
-- database/ddl/DDL-2026-019-hris-master-entitas/forward.sql
-- Target: Web_HRIS (development). Jalankan manual di production lewat SQL Tracker.
-- Dokumentasi: docs/changes/DDL-2026-019-hris-master-entitas/README.md
--
-- Seluruh pernyataan di berkas ini bersifat MENAMBAH. Tidak ada kolom, baris,
-- indeks, atau constraint yang dihapus atau ditimpa.

IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_HRIS_Master_Entitas')
CREATE TABLE N_HRIS_Master_Entitas (
    Id_Entitas       int           IDENTITY(1,1) NOT NULL
        CONSTRAINT PK_HRIS_Entitas PRIMARY KEY,
    Kode_Entitas     varchar(10)   NOT NULL,
    Nama_Entitas     nvarchar(150) NOT NULL,
    Nama_Singkat     nvarchar(50)  NULL,
    Warna            varchar(20)   NULL,
    Induk_Entitas_Id int           NULL,
    Urutan           int           NOT NULL DEFAULT 0,
    Flag_Aktif       char(1)       NOT NULL DEFAULT 'Y',
    Created_At       datetime2(0)  NOT NULL DEFAULT (SYSDATETIME()),
    Created_By       int           NULL,
    Updated_At       datetime2(0)  NULL,
    Updated_By       int           NULL
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('N_SOP_Dokumen') AND name = 'Entitas_Pemilik_Id')
ALTER TABLE N_SOP_Dokumen ADD Entitas_Pemilik_Id int NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('N_SOP_Dokumen') AND name = 'Entitas_Lingkup_Id')
ALTER TABLE N_SOP_Dokumen ADD Entitas_Lingkup_Id int NULL;
GO

-- ------------------------------------------------------------------------
-- SUMBER: database/ddl/DDL-2026-023-sop-master-apqc/forward.sql
-- ------------------------------------------------------------------------
-- database/ddl/DDL-2026-023-sop-master-apqc/forward.sql
-- Target: Web_HRIS (development). Jalankan manual di production lewat SQL Tracker.
-- Dokumentasi: docs/changes/DDL-2026-023-sop-master-apqc/README.md
--
-- Seluruh pernyataan bersifat MENAMBAH. Tidak ada yang dihapus atau ditimpa.

IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_SOP_Master_APQC')
CREATE TABLE N_SOP_Master_APQC (
    Id_SOP_APQC   int           IDENTITY(1,1) NOT NULL
        CONSTRAINT PK_SOP_APQC PRIMARY KEY,
    Kode_Proses   varchar(10)   NOT NULL,
    Nama_Proses   nvarchar(200) NOT NULL,
    Deskripsi     nvarchar(500) NULL,
    Urutan        int           NOT NULL DEFAULT 0,
    Flag_Aktif    char(1)       NOT NULL DEFAULT 'Y',
    Created_At    datetime2(0)  NOT NULL DEFAULT (SYSDATETIME()),
    Created_By    int           NULL,
    Updated_At    datetime2(0)  NULL,
    Updated_By    int           NULL
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_SOP_Master_APQC_Tipe_Item')
CREATE TABLE N_SOP_Master_APQC_Tipe_Item (
    Id_SOP_APQC_Tipe_Item int          IDENTITY(1,1) NOT NULL
        CONSTRAINT PK_SOP_APQC_Tipe_Item PRIMARY KEY,
    APQC_Id               int          NOT NULL,
    Tipe_Item             varchar(12)  NOT NULL,
    Flag_Aktif            char(1)      NOT NULL DEFAULT 'Y',
    Created_At            datetime2(0) NOT NULL DEFAULT (SYSDATETIME()),
    Created_By            int          NULL,
    CONSTRAINT FK_SOP_APQC_Tipe_APQC FOREIGN KEY (APQC_Id)
        REFERENCES N_SOP_Master_APQC (Id_SOP_APQC)
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('N_SOP_Dokumen') AND name = 'APQC_Id')
ALTER TABLE N_SOP_Dokumen ADD APQC_Id int NULL;
GO

-- ------------------------------------------------------------------------
-- SUMBER: database/ddl/DDL-2026-027-hris-master-departemen/forward.sql
-- ------------------------------------------------------------------------
-- database/ddl/DDL-2026-027-hris-master-departemen/forward.sql
-- Target: Web_HRIS (development). Jalankan manual di production lewat SQL Tracker.
-- Dokumentasi: docs/changes/DDL-2026-027-hris-master-departemen/README.md
--
-- Seluruh pernyataan bersifat MENAMBAH. Tidak ada yang dihapus atau ditimpa.

IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_HRIS_Master_Departemen')
CREATE TABLE N_HRIS_Master_Departemen (
    Id_Departemen   int           IDENTITY(1,1) NOT NULL
        CONSTRAINT PK_HRIS_Departemen PRIMARY KEY,
    Kode_Departemen varchar(10)   NOT NULL,
    Nama_Departemen nvarchar(150) NOT NULL,
    Sub_Divisi_Id   bigint        NULL,
    Urutan          int           NOT NULL DEFAULT 0,
    Flag_Aktif      char(1)       NOT NULL DEFAULT 'Y',
    Created_At      datetime2(0)  NOT NULL DEFAULT (SYSDATETIME()),
    Created_By      int           NULL,
    Updated_At      datetime2(0)  NULL,
    Updated_By      int           NULL
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('N_SOP_Dokumen') AND name = 'Departemen_Id')
ALTER TABLE N_SOP_Dokumen ADD Departemen_Id int NULL;
GO

-- ------------------------------------------------------------------------
-- SUMBER: database/ddl/DDL-2026-053-sop-aturan-multi-paket/forward.sql
-- ------------------------------------------------------------------------
/* Change ID: DDL-2026-053-sop-aturan-multi-paket
 *
 * URUTAN DISENGAJA. Penjaga "satu paket satu aturan" berpindah rumah: dari
 * UNIQUE CONSTRAINT pada N_SOP_Assignment_Rule.Id_SOP_Paket ke filtered unique
 * index pada tabel jembatan. Penjaga BARU dibangun lebih dulu (bagian 1), baru
 * penjaga LAMA dilepas (bagian 3), supaya tidak pernah ada jeda ketika basis
 * data berjalan tanpa penjaga apa pun.
 *
 * Seluruh statement idempoten (IF EXISTS / IF NOT EXISTS): berkas ini aman
 * dijalankan ulang, termasuk pada basis data yang sudah menerima sebagiannya.
 */

/* ================= 1. PENJAGA BARU: TABEL JEMBATAN ATURAN <-> PAKET ================= */

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'N_SOP_Assignment_Rule_Paket')
CREATE TABLE N_SOP_Assignment_Rule_Paket (
    Id_SOP_Assignment_Rule_Paket int IDENTITY(1,1) NOT NULL,
    Id_SOP_Assignment_Rule       int NOT NULL,
    Id_SOP_Paket                 int NOT NULL,
    Urutan                       int NOT NULL CONSTRAINT DF_SOP_ARP_Urutan DEFAULT (1),
    Flag_Override_Jadwal         char(1) NOT NULL CONSTRAINT DF_SOP_ARP_Override DEFAULT ('T'),
    Deadline_Mode                varchar(10) NULL,
    Deadline_Hari                int NULL,
    Tanggal_Deadline             date NULL,
    Flag_Reminder                char(1) NULL,
    Irama_Reminder_Hari          int NULL,
    Last_Apply_Status            varchar(20) NOT NULL CONSTRAINT DF_SOP_ARP_ApplyStatus DEFAULT ('IDLE'),
    Last_Apply_Error             nvarchar(1000) NULL,
    Last_Applied_At              datetime2 NULL,
    Created_By                   int NULL,
    Created_At                   datetime2 NOT NULL CONSTRAINT DF_SOP_ARP_CreatedAt DEFAULT (SYSDATETIME()),
    Updated_By                   int NULL,
    Updated_At                   datetime2 NULL,
    Flag_Aktif                   char(1) NOT NULL CONSTRAINT DF_SOP_ARP_FlagAktif DEFAULT ('Y'),
    CONSTRAINT PK_SOP_Assignment_Rule_Paket PRIMARY KEY CLUSTERED (Id_SOP_Assignment_Rule_Paket),
    CONSTRAINT FK_SOP_ARP_Rule  FOREIGN KEY (Id_SOP_Assignment_Rule) REFERENCES N_SOP_Assignment_Rule (Id_SOP_Assignment_Rule),
    CONSTRAINT FK_SOP_ARP_Paket FOREIGN KEY (Id_SOP_Paket) REFERENCES N_SOP_Master_Paket (Id_SOP_Paket),
    CONSTRAINT CK_SOP_ARP_Override    CHECK (Flag_Override_Jadwal IN ('Y','T')),
    CONSTRAINT CK_SOP_ARP_FlagAktif   CHECK (Flag_Aktif IN ('Y','T')),
    CONSTRAINT CK_SOP_ARP_DeadlineMod CHECK (Deadline_Mode IS NULL OR Deadline_Mode IN ('NONE','RELATIVE','ABSOLUTE'))
);
GO

/* ================= 2. RIWAYAT: BARIS INDUK + BARIS ANAK PER PAKET ================= */

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Automation_Run' AND COLUMN_NAME = 'Id_SOP_Paket')
    ALTER TABLE N_SOP_Automation_Run ADD Id_SOP_Paket int NULL;
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Automation_Run' AND COLUMN_NAME = 'Id_Run_Induk')
    ALTER TABLE N_SOP_Automation_Run ADD Id_Run_Induk int NULL;
GO

/* ================= 3. PENJAGA LAMA DILEPAS ================= */

/* UQ_N_SOP_Assignment_Rule_Paket -- UNIQUE CONSTRAINT pada
 * N_SOP_Assignment_Rule.Id_SOP_Paket. Inilah penjaga "satu paket satu aturan"
 * versi lama, yang perannya sudah diambil alih bagian 1.
 *
 * WAJIB dilepas, bukan sekadar boleh: mulai perubahan ini kolom Id_SOP_Paket
 * berhenti diisi, sehingga setiap aturan baru bernilai NULL di sana. SQL Server
 * menganggap NULL bentrok dengan NULL pada UNIQUE CONSTRAINT, jadi selama
 * constraint ini berdiri SELURUH tabel hanya bisa memuat SATU baris aturan --
 * bukan satu per paket, melainkan satu, titik.
 *
 * Yang dibuang hanya aturan keunikannya beserta index penopangnya. Kolom
 * Id_SOP_Paket tetap ada, FK_N_SOP_Rule_Paket tetap ada, IX_N_SOP_Rule_Status
 * tetap ada, dan tak satu baris data pun tersentuh. */
IF EXISTS (SELECT 1 FROM sys.objects
           WHERE name = 'UQ_N_SOP_Assignment_Rule_Paket'
             AND parent_object_id = OBJECT_ID('N_SOP_Assignment_Rule'))
    ALTER TABLE N_SOP_Assignment_Rule DROP CONSTRAINT UQ_N_SOP_Assignment_Rule_Paket;
GO

/* Kolom paket lama berhenti dipakai. TIDAK di-drop di sini: pembuangan kolom
 * membuang data dan wajib dijalankan manual. Dijadikan nullable supaya INSERT
 * baru tak lagi menuntutnya. Statement drop-nya tersedia di drop.sql.
 *
 * Harus SESUDAH DROP CONSTRAINT di atas: SQL Server menolak mengubah kolom yang
 * masih ditopang sebuah unique constraint. */
IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
           WHERE TABLE_NAME = 'N_SOP_Assignment_Rule' AND COLUMN_NAME = 'Id_SOP_Paket' AND IS_NULLABLE = 'NO')
    ALTER TABLE N_SOP_Assignment_Rule ALTER COLUMN Id_SOP_Paket int NULL;
GO

/* ================= 4. CATAT DI DASHBOARD PERUBAHAN ================= */
/* Paling akhir dengan sengaja: perubahan hanya tercatat bila seluruh langkah
 * di atasnya lolos. */

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-053-sop-aturan-multi-paket')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DDL-2026-053-sop-aturan-multi-paket', 'DDL', 'Satu aturan otomasi menampung banyak paket', 'Tabel jembatan N_SOP_Assignment_Rule_Paket dengan filtered unique index sebagai penjaga klaim paket; kolom Id_SOP_Paket dan Id_Run_Induk pada N_SOP_Automation_Run untuk riwayat per paket; UQ_N_SOP_Assignment_Rule_Paket dilepas dan N_SOP_Assignment_Rule.Id_SOP_Paket dijadikan nullable.', 'database/ddl/DDL-2026-053-sop-aturan-multi-paket/forward.sql', 'docs/changes/DDL-2026-053-sop-aturan-multi-paket/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'otomasi-multi-paket');
GO

-- ------------------------------------------------------------------------
-- SUMBER: database/ddl/DDL-2026-055-sop-dokumen-nonaktif/forward.sql
-- ------------------------------------------------------------------------
/* Change ID: DDL-2026-055-sop-dokumen-nonaktif

   Penonaktifan Dokumen SOP direkam pada kolomnya SENDIRI, bukan dengan
   membalik Flag_Aktif.

   Alasannya bukan selera. Flag_Aktif dokumen ditanyakan oleh 28 kueri, tiga di
   antaranya jalur baca karyawan (SopProgressReadService, SopRepositoryReadService,
   SopJangkauanPublikService). Membalik Flag_Aktif akan mencabut dokumen dari
   paket yang SEDANG berjalan — padahal keputusan produknya justru sebaliknya:
   dokumen yang dinonaktifkan hanya tertutup bagi pemakaian BARU, sedangkan
   kewajiban yang sudah terbit tetap berjalan sampai adminnya melepas sendiri.
   Dengan kolom terpisah, tak satu pun pembaca perlu disunting.

   Nonaktif_At merangkap penanda status: NULL berarti aktif. Tidak ada kolom
   "Status" tersendiri karena keadaannya hanya dua, dan dua keadaan yang
   disimpan di dua tempat pada akhirnya akan berselisih.

   Nonaktif_Paket & Nonaktif_Orang adalah CUPLIKAN, bukan turunan. Justru karena
   paket tetap berjalan setelah dokumen dinonaktifkan, angkanya terus berubah
   sesudahnya; "kondisi terakhir saat dinonaktifkan" hanya bisa benar bila
   dibekukan pada detik itu. */

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Dokumen' AND COLUMN_NAME = 'Nonaktif_At')
    ALTER TABLE N_SOP_Dokumen ADD Nonaktif_At datetime NULL;
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Dokumen' AND COLUMN_NAME = 'Nonaktif_By')
    ALTER TABLE N_SOP_Dokumen ADD Nonaktif_By int NULL;
GO

/* Catatan dibiarkan NULL-able di tingkat skema semata-mata karena baris lama
   tak punya catatan; aplikasi mewajibkannya untuk setiap penonaktifan baru.
   NOT NULL di sini akan menuntut nilai bohongan bagi seluruh baris yang sudah
   ada, dan nilai bohongan itu tak bisa dibedakan dari catatan sungguhan. */
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Dokumen' AND COLUMN_NAME = 'Nonaktif_Catatan')
    ALTER TABLE N_SOP_Dokumen ADD Nonaktif_Catatan nvarchar(500) NULL;
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Dokumen' AND COLUMN_NAME = 'Nonaktif_Paket')
    ALTER TABLE N_SOP_Dokumen ADD Nonaktif_Paket int NULL;
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Dokumen' AND COLUMN_NAME = 'Nonaktif_Orang')
    ALTER TABLE N_SOP_Dokumen ADD Nonaktif_Orang int NULL;
GO

ALTER TABLE N_SOP_Dokumen ALTER COLUMN Id_Versi_Aktif INT NULL;
GO

/* ─────────────────────────────────────────────────────────────────────────────
   VERIFIKASI — jalankan setelah skrip di atas selesai
   ───────────────────────────────────────────────────────────────────────────*/
SELECT i.name AS indeks, i.is_unique,
       STUFF((SELECT ',' + c.name
                FROM sys.index_columns ic
                JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
               WHERE ic.object_id = i.object_id AND ic.index_id = i.index_id
               ORDER BY ic.key_ordinal FOR XML PATH('')), 1, 1, '') AS kunci
  FROM sys.indexes i
 WHERE i.object_id = OBJECT_ID('dbo.N_LMS_Api_Nonces')
   AND i.name IS NOT NULL
 ORDER BY i.name;
GO

SELECT name, definition
  FROM sys.check_constraints
 WHERE parent_object_id = OBJECT_ID('dbo.N_LMS_Api_Logs')
 ORDER BY name;
GO

/* ==========================================================================
   BAGIAN C — Visitor: rekap lama, tamu B2B, matriks, inspeksi, kartu permit
   ========================================================================== */


-- ------------------------------------------------------------------------
-- SUMBER: database/ddl/DDL-2026-118-rekap-skema-production/forward.sql
-- ------------------------------------------------------------------------
/* =====================================================================
   DDL-2026-118-rekap-skema-production
   ---------------------------------------------------------------------
   SATU BERKAS, SEKALI EKSEKUSI.

   Menutup SELURUH selisih skema antara development (Web_HRIS) dan
   production (emi_db_web_hr @ 34.143.236.80) yang ditemukan audit
   READ ONLY 16 September 2026:

       23 kolom  (10 SOP + 13 Visitor/RBAC/Pengumpulan Data)
        1 foreign key
        6 indeks (3 belum ada, 3 definisinya kedaluwarsa)
        8 constraint DEFAULT/CHECK

   Sumber temuan : docs/audit/daftar-temuan-sop-production-2026-09-16.md
                   docs/audit/adendum-pasca-merge-master-2026-09-16.md

   ---------------------------------------------------------------------
   BERKAS INI MENGGANTIKAN EKSEKUSI SEMBILAN DDL BERIKUT
   ---------------------------------------------------------------------
   Setelah berkas ini dijalankan, kesembilan DDL di bawah TIDAK PERLU
   dijalankan lagi. Isinya sudah tercakup seluruhnya di sini:

       DDL-2026-079-visitor-permit-api-b2b
       DDL-2026-100-visitor-daftar-anggota-tamu
       DDL-2026-101-visitor-tipe-tamu-permit-approver
       DDL-2026-102-visitor-tamu-api-b2b
       DDL-2026-103-visitor-permit-coklat-approval
       DDL-2026-104-visitor-kategori-permit-akses
       DDL-2026-110-hibah-role-per-halaman
       DDL-2026-110-cakupan-lokasi-pengumpulan-data
       (dan DDL-2026-114 yang sebelumnya dibuat untuk kolom SOP - artefaknya
        sudah dicabut, isinya pindah ke Bagian 1-2 di sini)

   Seluruh pernyataan tetap idempoten, jadi kalau salah satu DDL di atas
   terlanjur dijalankan lebih dulu, berkas ini akan melewatinya dengan
   selamat - dan sebaliknya.

   ---------------------------------------------------------------------
   YANG DI LUAR CAKUPAN
   ---------------------------------------------------------------------
   - DDL-2026-092-sinkronisasi-skema-production. Sampel Bagian 1-2 (10
     kolom) terbukti SUDAH ADA di production meski ledger menandainya
     PENDING, dan Bagian 5-nya sudah mati total karena ke-44 tabelnya sudah
     ada. Bagian 3 (indeks), 4 (default), 6 (trigger), dan 7 (foreign key)
     BELUM diperiksa dan tidak ikut di sini.
   - N_HRIS_KANDIDAT_Api_* dan kolom HRIS_KANDIDAT_Users - sistem rekrutmen
     terpisah, hanya dipakai middleware yatim. Dilewati atas keputusan user.
   - fn_GetShiftLengkap_Range - hanya dipanggil OvertimeController::
     userActive/userActiveAll yang tidak punya rute. Dilewati atas keputusan
     user.
   - View_Golongan_Sub_Golongan_Level_Jabatan (Flag_Extra_Meal, Flag_Harian).
     Dilewati atas keputusan user.
   - Seed data: DML-2026-115-seed-sop-prioritas berdiri sendiri sebagai DML.

   ---------------------------------------------------------------------
   PERHATIAN: BERKAS INI MEMUAT TIGA DROP INDEX
   ---------------------------------------------------------------------
   Bagian 11 membuang lalu membuat ulang tiga indeks yang definisinya di
   production sudah kedaluwarsa - dua di antaranya indeks UNIK:

     UX_VISITOR_Permit_Requester_Idempotency
        prod  : (Kode_Karyawan_Pemohon, Idempotency_Key), tanpa filter
        harus : (Kode_Karyawan_Pemohon, Id_Api_Aplikasi, Idempotency_Key)
                WHERE Idempotency_Key IS NOT NULL
     UX_VISITOR_Tamu_Requester_Idempotency
        prod  : (Kode_Karyawan_PIC, Idempotency_Key), tanpa filter
        harus : (Kode_Karyawan_PIC, Id_Api_Aplikasi, Idempotency_Key)
                WHERE Idempotency_Key IS NOT NULL
     IX_VISITOR_Permit_Api_Aplikasi
        prod  : tanpa filter
        harus : WHERE Id_Api_Aplikasi IS NOT NULL

   Tanpa Id_Api_Aplikasi di kunci, dua aplikasi mitra yang mengirim
   Idempotency_Key yang sama akan bertabrakan - persis masalah yang
   DDL-2026-079 dan 102 hendak selesaikan.

   Risikonya kecil tapi harus disadari: ketiga tabelnya BERISI 0 BARIS di
   production per 2026-09-16 (N_EMI_VISITOR_Transaksi_Permit 0,
   N_EMI_VISITOR_Transaksi_Tamu 0), jadi pembuatan ulang berlangsung
   seketika dan tidak mungkin gagal karena nilai kembar. Tiap DROP tetap
   dijaga: hanya dijalankan bila definisi lamanya memang terbukti
   kedaluwarsa, dan Bagian 12 melempar RAISERROR bila ada indeks yang tidak
   berhasil terbentuk kembali.

   ---------------------------------------------------------------------
   SIFAT PERUBAHAN
   ---------------------------------------------------------------------
   - 22 dari 23 kolom NULLABLE tanpa default -> operasi metadata saja.
     Satu kolom NOT NULL berdefault (N_HRIS_Grup_Fitur_Map.Flag_Hibah_Role),
     lihat Bagian 9.
   - Tidak ada DROP TABLE, DROP COLUMN, TRUNCATE, UPDATE, DELETE, atau MERGE.
     Satu-satunya DROP adalah tiga DROP INDEX di Bagian 11, masing-masing
     langsung diikuti CREATE penggantinya.
   - Seluruh pernyataan idempoten: aman diulang, dan no-op penuh di
     development karena di sana semuanya sudah ada.
   - Nama DEFAULT, CHECK, dan INDEX disalin PERSIS dari DDL asal supaya
     tidak lahir objek kembar bernama beda.

   ---------------------------------------------------------------------
   KENAPA URUTANNYA SEPERTI INI
   ---------------------------------------------------------------------
   1. SELURUH KOLOM (Bagian 1-10) mendahului SELURUH INDEKS (Bagian 11).
      Empat dari enam indeks menyebut kolom yang baru lahir di berkas ini
      (Id_Api_Aplikasi, No_Transaksi_Tamu); membangunnya lebih dulu pasti
      gagal.
   2. Bagian 4 (DDL-100) WAJIB mendahului Bagian 5 (DDL-101). Dua tabel
      menerima kolom dari keduanya - N_EMI_VISITOR_Transaksi_Tamu_Detail dan
      N_VISITOR_Tamu_Efektif - dan SQL Server memberi column_id sesuai urutan
      penambahan. Membalik urutannya membuat ordinal production berbeda dari
      development.
   3. Foreign key dan CHECK selalu di BATCH TERPISAH setelah kolomnya. SQL
      Server mengompilasi satu batch utuh sebelum menjalankannya, jadi
      merujuk kolom yang baru dibuat di batch yang sama gagal dengan
      "Invalid column name".
   4. DEFAULT dipasang INLINE pada ALTER ... ADD. Untuk kolom NOT NULL itu
      wajib: tanpa default, ALTER ditolak pada tabel yang sudah berisi baris.
   5. Satu ALTER per kolom, masing-masing berpenjaga sendiri. Menggabungkan
      beberapa kolom dalam satu ALTER membuat penjaganya salah begitu
      sebagian kolom sudah ada.

   Target        : emi_db_web_hr (production)
   Dapat dijalankan juga di : Web_HRIS (development) -> hasilnya no-op
   Rollback      : drop.sql (MANUAL, jangan dijalankan otomatis)
   ===================================================================== */

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

PRINT '=== DDL-2026-118 dijalankan pada database: ' + DB_NAME() + ' ===';
PRINT '=== Mulai: ' + CONVERT(varchar(30), SYSDATETIME(), 121) + ' ===';
GO

/* =====================================================================
   BAGIAN 1 - KOLOM SOP
   Urutan dev: Master_Paket 22-23, Penjadwalan 15/16/18, Progress 23-24,
   Absensi 11-13. Semuanya kolom ekor, jadi urutan di bawah menghasilkan
   posisi yang identik dengan development.
   ===================================================================== */

IF OBJECT_ID('dbo.N_SOP_Master_Paket', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_SOP_Master_Paket', 'Versi_Paket_Aktif') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Master_Paket ADD [Versi_Paket_Aktif] int NULL;
    PRINT '  + N_SOP_Master_Paket.Versi_Paket_Aktif';
END
GO

IF OBJECT_ID('dbo.N_SOP_Master_Paket', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_SOP_Master_Paket', 'Id_SOP_Paket_Versi_Aktif') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Master_Paket ADD [Id_SOP_Paket_Versi_Aktif] bigint NULL;
    PRINT '  + N_SOP_Master_Paket.Id_SOP_Paket_Versi_Aktif';
END
GO

IF OBJECT_ID('dbo.N_SOP_Penjadwalan', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_SOP_Penjadwalan', 'Nama_Batch') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Penjadwalan ADD [Nama_Batch] nvarchar(150) NULL;
    PRINT '  + N_SOP_Penjadwalan.Nama_Batch';
END
GO

IF OBJECT_ID('dbo.N_SOP_Penjadwalan', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_SOP_Penjadwalan', 'Keterangan') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Penjadwalan ADD [Keterangan] nvarchar(500) NULL;
    PRINT '  + N_SOP_Penjadwalan.Keterangan';
END
GO

IF OBJECT_ID('dbo.N_SOP_Penjadwalan', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_SOP_Penjadwalan', 'Id_SOP_Assignment_Rule') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Penjadwalan ADD [Id_SOP_Assignment_Rule] int NULL;
    PRINT '  + N_SOP_Penjadwalan.Id_SOP_Assignment_Rule';
END
GO

IF OBJECT_ID('dbo.N_SOP_Progress', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_SOP_Progress', 'Id_Ujian_Token_Hasil') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Progress ADD [Id_Ujian_Token_Hasil] varchar(100) NULL;
    PRINT '  + N_SOP_Progress.Id_Ujian_Token_Hasil';
END
GO

IF OBJECT_ID('dbo.N_SOP_Progress', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_SOP_Progress', 'Id_Versi_Asal') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Progress ADD [Id_Versi_Asal] int NULL;
    PRINT '  + N_SOP_Progress.Id_Versi_Asal';
END
GO

IF OBJECT_ID('dbo.N_SOP_Absensi', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_SOP_Absensi', 'Akurasi_Meter') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Absensi ADD [Akurasi_Meter] decimal(9,2) NULL;
    PRINT '  + N_SOP_Absensi.Akurasi_Meter';
END
GO

IF OBJECT_ID('dbo.N_SOP_Absensi', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_SOP_Absensi', 'User_Agent') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Absensi ADD [User_Agent] varchar(255) NULL;
    PRINT '  + N_SOP_Absensi.User_Agent';
END
GO

IF OBJECT_ID('dbo.N_SOP_Absensi', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_SOP_Absensi', 'IP_Address') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Absensi ADD [IP_Address] varchar(45) NULL;
    PRINT '  + N_SOP_Absensi.IP_Address';
END
GO

/* =====================================================================
   BAGIAN 3 - dari DDL-2026-079-visitor-permit-api-b2b (kolom)
   Sebelas dari dua belas kolom ini sudah ada di production; hanya
   Kode_Karyawan_PIC yang benar-benar baru. Sisanya tetap ditulis lengkap
   agar berkas ini berdiri sendiri dan tetap benar bila dijalankan di
   lingkungan lain yang lebih tertinggal.
   ===================================================================== */

IF OBJECT_ID('dbo.N_HRIS_Api_Aplikasi', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_HRIS_Api_Aplikasi', 'Kode_Karyawan_PIC') IS NULL
BEGIN
    ALTER TABLE dbo.N_HRIS_Api_Aplikasi ADD [Kode_Karyawan_PIC] varchar(20) NULL;
    PRINT '  + N_HRIS_Api_Aplikasi.Kode_Karyawan_PIC';
END
GO

IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Permit', 'Sumber_Pengajuan') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Permit ADD [Sumber_Pengajuan] varchar(10) NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Permit.Sumber_Pengajuan';
END
GO

IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Permit', 'Id_Api_Aplikasi') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Permit ADD [Id_Api_Aplikasi] int NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Permit.Id_Api_Aplikasi';
END
GO

IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Permit', 'Referensi_Mitra') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Permit ADD [Referensi_Mitra] varchar(100) NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Permit.Referensi_Mitra';
END
GO

IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Permit_Detail', 'Waktu_Mulai_Berlaku') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Permit_Detail ADD [Waktu_Mulai_Berlaku] datetime2(0) NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Permit_Detail.Waktu_Mulai_Berlaku';
END
GO

IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Permit_Detail', 'Waktu_Berakhir') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Permit_Detail ADD [Waktu_Berakhir] datetime2(0) NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Permit_Detail.Waktu_Berakhir';
END
GO

IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Permit_Detail', 'Nama_Tamu') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Permit_Detail ADD [Nama_Tamu] varchar(150) NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Permit_Detail.Nama_Tamu';
END
GO

IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Permit_Detail', 'Instansi_Tamu') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Permit_Detail ADD [Instansi_Tamu] varchar(150) NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Permit_Detail.Instansi_Tamu';
END
GO

IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Permit_Detail', 'No_Identitas_Tamu') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Permit_Detail ADD [No_Identitas_Tamu] varchar(100) NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Permit_Detail.No_Identitas_Tamu';
END
GO

IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Permit_Detail', 'Jenis_Identitas_Tamu') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Permit_Detail ADD [Jenis_Identitas_Tamu] varchar(20) NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Permit_Detail.Jenis_Identitas_Tamu';
END
GO

IF COL_LENGTH('dbo.N_VISITOR_Permit_Efektif', 'Nama_Pemegang') IS NULL
BEGIN
    ALTER TABLE dbo.N_VISITOR_Permit_Efektif ADD [Nama_Pemegang] varchar(150) NULL;
    PRINT '  + N_VISITOR_Permit_Efektif.Nama_Pemegang';
END
GO

IF COL_LENGTH('dbo.N_VISITOR_Permit_Efektif', 'Instansi_Pemegang') IS NULL
BEGIN
    ALTER TABLE dbo.N_VISITOR_Permit_Efektif ADD [Instansi_Pemegang] varchar(150) NULL;
    PRINT '  + N_VISITOR_Permit_Efektif.Instansi_Pemegang';
END
GO

/* =====================================================================
   BAGIAN 4 - dari DDL-2026-100-visitor-daftar-anggota-tamu
   WAJIB mendahului Bagian 5 agar ordinal kolom sama dengan development.
   ===================================================================== */

IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Daftar_Anggota_Json') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Tamu_Detail ADD [Daftar_Anggota_Json] nvarchar(max) NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Tamu_Detail.Daftar_Anggota_Json';
END
GO

IF COL_LENGTH('dbo.N_VISITOR_Tamu_Efektif', 'Daftar_Anggota_Json') IS NULL
BEGIN
    ALTER TABLE dbo.N_VISITOR_Tamu_Efektif ADD [Daftar_Anggota_Json] nvarchar(max) NULL;
    PRINT '  + N_VISITOR_Tamu_Efektif.Daftar_Anggota_Json';
END
GO

/* =====================================================================
   BAGIAN 5 - dari DDL-2026-101-visitor-tipe-tamu-permit-approver
   HARUS setelah Bagian 4.
   ===================================================================== */

IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Tipe_Tamu') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Tamu_Detail
        ADD [Tipe_Tamu] varchar(20) NULL
            CONSTRAINT [DF_N_EMI_VISITOR_Transaksi_Tamu_Detail_Tipe_Tamu] DEFAULT 'REGULAR';
    PRINT '  + N_EMI_VISITOR_Transaksi_Tamu_Detail.Tipe_Tamu';
END
GO

IF COL_LENGTH('dbo.N_VISITOR_Tamu_Efektif', 'Tipe_Tamu') IS NULL
BEGIN
    ALTER TABLE dbo.N_VISITOR_Tamu_Efektif
        ADD [Tipe_Tamu] varchar(20) NULL
            CONSTRAINT [DF_N_VISITOR_Tamu_Efektif_Tipe_Tamu] DEFAULT 'REGULAR';
    PRINT '  + N_VISITOR_Tamu_Efektif.Tipe_Tamu';
END
GO

/* =====================================================================
   BAGIAN 6 - dari DDL-2026-102-visitor-tamu-api-b2b (kolom)
   Urutan dev: 17, 18, 19.
   ===================================================================== */

IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Tamu', 'Sumber_Pengajuan') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Tamu ADD [Sumber_Pengajuan] varchar(10) NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Tamu.Sumber_Pengajuan';
END
GO

IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Tamu', 'Id_Api_Aplikasi') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Tamu ADD [Id_Api_Aplikasi] int NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Tamu.Id_Api_Aplikasi';
END
GO

IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Tamu', 'Referensi_Mitra') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Tamu ADD [Referensi_Mitra] varchar(100) NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Tamu.Referensi_Mitra';
END
GO

/* =====================================================================
   BAGIAN 7 - dari DDL-2026-103-visitor-permit-coklat-approval (kolom)
   ===================================================================== */

IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Permit', 'No_Transaksi_Tamu') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Permit ADD [No_Transaksi_Tamu] varchar(50) NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Permit.No_Transaksi_Tamu';
END
GO

IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Permit_Detail', 'Jenis_Kendaraan_Tamu') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Permit_Detail ADD [Jenis_Kendaraan_Tamu] varchar(50) NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Permit_Detail.Jenis_Kendaraan_Tamu';
END
GO

/* =====================================================================
   BAGIAN 8 - dari DDL-2026-104-visitor-kategori-permit-akses
   Nullable TANPA default, persis seperti DDL asal: kodenya membaca NULL
   sebagai 'Y', sehingga empat baris kategori di production tetap muncul
   di layar pengajuan tanpa perlu di-backfill.
   ===================================================================== */

IF COL_LENGTH('dbo.N_EMI_VISITOR_Master_Kategori_Permit', 'Flag_Muncul_Pengajuan') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Master_Kategori_Permit ADD [Flag_Muncul_Pengajuan] char(1) NULL;
    PRINT '  + N_EMI_VISITOR_Master_Kategori_Permit.Flag_Muncul_Pengajuan';
END
GO

/* =====================================================================
   BAGIAN 9 - dari DDL-2026-110-hibah-role-per-halaman
   SATU-SATUNYA kolom NOT NULL di berkas ini. Default 'Y' WAJIB ada:
   tanpanya ALTER ditolak karena tabelnya berisi 75 baris di production.
   Seluruh baris lama otomatis 'Y' - perilaku hibah role yang berlaku
   sekarang dipertahankan apa adanya, dan pengecualian ditandai 'T'
   belakangan lewat perubahan data tersendiri.
   ===================================================================== */

IF OBJECT_ID('dbo.N_HRIS_Grup_Fitur_Map', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_HRIS_Grup_Fitur_Map', 'Flag_Hibah_Role') IS NULL
BEGIN
    ALTER TABLE dbo.N_HRIS_Grup_Fitur_Map
        ADD [Flag_Hibah_Role] char(1) NOT NULL
            CONSTRAINT [DF_N_HRIS_Grup_Fitur_Map_Flag_Hibah_Role] DEFAULT ('Y');
    PRINT '  + N_HRIS_Grup_Fitur_Map.Flag_Hibah_Role';
END
GO

/* =====================================================================
   BAGIAN 10 - dari DDL-2026-110-cakupan-lokasi-pengumpulan-data
   ===================================================================== */

IF OBJECT_ID('dbo.N_HRIS_Pengumpulan_Data_Config', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_HRIS_Pengumpulan_Data_Config', 'Cakupan_Lokasi') IS NULL
BEGIN
    ALTER TABLE dbo.N_HRIS_Pengumpulan_Data_Config ADD [Cakupan_Lokasi] varchar(2000) NULL;
    PRINT '  + N_HRIS_Pengumpulan_Data_Config.Cakupan_Lokasi';
END
GO

/* =====================================================================
   BAGIAN 12 - PENJAGA AKHIR
   Melempar galat bila ada satu pun objek yang gagal terbentuk. Paling
   penting: memastikan tidak ada indeks yang terbuang di Bagian 11 lalu
   gagal dibuat ulang.
   ===================================================================== */

DECLARE @kurang int = 0, @pesan nvarchar(2000) = N'';

;WITH wajib AS (
    SELECT * FROM (VALUES
        ('N_SOP_Master_Paket', 'Versi_Paket_Aktif'),
        ('N_SOP_Master_Paket', 'Id_SOP_Paket_Versi_Aktif'),
        ('N_SOP_Penjadwalan', 'Nama_Batch'),
        ('N_SOP_Penjadwalan', 'Keterangan'),
        ('N_SOP_Penjadwalan', 'Id_SOP_Assignment_Rule'),
        ('N_SOP_Progress', 'Id_Ujian_Token_Hasil'),
        ('N_SOP_Progress', 'Id_Versi_Asal'),
        ('N_SOP_Absensi', 'Akurasi_Meter'),
        ('N_SOP_Absensi', 'User_Agent'),
        ('N_SOP_Absensi', 'IP_Address'),
        ('N_HRIS_Api_Aplikasi', 'Kode_Karyawan_PIC'),
        ('N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Daftar_Anggota_Json'),
        ('N_VISITOR_Tamu_Efektif', 'Daftar_Anggota_Json'),
        ('N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Tipe_Tamu'),
        ('N_VISITOR_Tamu_Efektif', 'Tipe_Tamu'),
        ('N_EMI_VISITOR_Transaksi_Tamu', 'Sumber_Pengajuan'),
        ('N_EMI_VISITOR_Transaksi_Tamu', 'Id_Api_Aplikasi'),
        ('N_EMI_VISITOR_Transaksi_Tamu', 'Referensi_Mitra'),
        ('N_EMI_VISITOR_Transaksi_Permit', 'No_Transaksi_Tamu'),
        ('N_EMI_VISITOR_Transaksi_Permit_Detail', 'Jenis_Kendaraan_Tamu'),
        ('N_EMI_VISITOR_Master_Kategori_Permit', 'Flag_Muncul_Pengajuan'),
        ('N_HRIS_Grup_Fitur_Map', 'Flag_Hibah_Role'),
        ('N_HRIS_Pengumpulan_Data_Config', 'Cakupan_Lokasi')
    ) AS v (Tabel, Kolom)
)
SELECT @kurang = COUNT(*),
       @pesan  = STRING_AGG(Tabel + '.' + Kolom, ', ')
FROM wajib
WHERE COL_LENGTH('dbo.' + Tabel, Kolom) IS NULL;

IF @kurang > 0
    RAISERROR('GAGAL: %d kolom belum terbentuk -> %s', 16, 1, @kurang, @pesan);
ELSE
    PRINT 'OK: 23 kolom lengkap.';

DECLARE @idxKurang int = 0, @idxPesan nvarchar(2000) = N'';
SELECT @idxKurang = COUNT(*), @idxPesan = STRING_AGG(Nama, ', ')
FROM (VALUES
        ('UX_VISITOR_Permit_Requester_Idempotency'),
        ('IX_VISITOR_Permit_Api_Aplikasi'),
        ('UX_VISITOR_Tamu_Requester_Idempotency'),
        ('IX_VISITOR_Tamu_Api_Aplikasi'),
        ('UX_VISITOR_Permit_Transaksi_Tamu'),
        ('IX_VISITOR_Permit_Pemohon_Sumber_Tamu')
     ) AS v (Nama)
WHERE NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = v.Nama);

IF @idxKurang > 0
    RAISERROR('GAGAL: %d indeks belum terbentuk -> %s', 16, 1, @idxKurang, @idxPesan);
ELSE
    PRINT 'OK: 6 indeks lengkap.';

IF OBJECT_ID('dbo.FK_N_SOP_Pjd_Rule', 'F') IS NULL
    RAISERROR('GAGAL: FK_N_SOP_Pjd_Rule belum terbentuk.', 16, 1);
ELSE
    PRINT 'OK: FK_N_SOP_Pjd_Rule ada.';
GO

/* =====================================================================
   BAGIAN 13 - LAPORAN AKHIR
   ===================================================================== */

SELECT  d.Bagian, d.Tabel, d.Kolom, d.Tipe_Harapan,
        CASE WHEN COL_LENGTH('dbo.' + d.Tabel, d.Kolom) IS NULL THEN 'BELUM ADA' ELSE 'ADA' END AS Keadaan
FROM (VALUES
        (1, 'N_SOP_Master_Paket',                    'Versi_Paket_Aktif',        'int NULL'),
        (1, 'N_SOP_Master_Paket',                    'Id_SOP_Paket_Versi_Aktif', 'bigint NULL'),
        (1, 'N_SOP_Penjadwalan',                     'Nama_Batch',               'nvarchar(150) NULL'),
        (1, 'N_SOP_Penjadwalan',                     'Keterangan',               'nvarchar(500) NULL'),
        (1, 'N_SOP_Penjadwalan',                     'Id_SOP_Assignment_Rule',   'int NULL'),
        (1, 'N_SOP_Progress',                        'Id_Ujian_Token_Hasil',     'varchar(100) NULL'),
        (1, 'N_SOP_Progress',                        'Id_Versi_Asal',            'int NULL'),
        (1, 'N_SOP_Absensi',                         'Akurasi_Meter',            'decimal(9,2) NULL'),
        (1, 'N_SOP_Absensi',                         'User_Agent',               'varchar(255) NULL'),
        (1, 'N_SOP_Absensi',                         'IP_Address',               'varchar(45) NULL'),
        (3, 'N_HRIS_Api_Aplikasi',                   'Kode_Karyawan_PIC',        'varchar(20) NULL'),
        (4, 'N_EMI_VISITOR_Transaksi_Tamu_Detail',   'Daftar_Anggota_Json',      'nvarchar(max) NULL'),
        (4, 'N_VISITOR_Tamu_Efektif',                'Daftar_Anggota_Json',      'nvarchar(max) NULL'),
        (5, 'N_EMI_VISITOR_Transaksi_Tamu_Detail',   'Tipe_Tamu',                'varchar(20) NULL'),
        (5, 'N_VISITOR_Tamu_Efektif',                'Tipe_Tamu',                'varchar(20) NULL'),
        (6, 'N_EMI_VISITOR_Transaksi_Tamu',          'Sumber_Pengajuan',         'varchar(10) NULL'),
        (6, 'N_EMI_VISITOR_Transaksi_Tamu',          'Id_Api_Aplikasi',          'int NULL'),
        (6, 'N_EMI_VISITOR_Transaksi_Tamu',          'Referensi_Mitra',          'varchar(100) NULL'),
        (7, 'N_EMI_VISITOR_Transaksi_Permit',        'No_Transaksi_Tamu',        'varchar(50) NULL'),
        (7, 'N_EMI_VISITOR_Transaksi_Permit_Detail', 'Jenis_Kendaraan_Tamu',     'varchar(50) NULL'),
        (8, 'N_EMI_VISITOR_Master_Kategori_Permit',  'Flag_Muncul_Pengajuan',    'char(1) NULL'),
        (9, 'N_HRIS_Grup_Fitur_Map',                 'Flag_Hibah_Role',          'char(1) NOT NULL'),
        (10, 'N_HRIS_Pengumpulan_Data_Config',       'Cakupan_Lokasi',           'varchar(2000) NULL')
     ) AS d (Bagian, Tabel, Kolom, Tipe_Harapan)
ORDER BY d.Bagian, d.Tabel, d.Kolom;

SELECT  d.Nama, d.Jenis,
        CASE WHEN OBJECT_ID('dbo.' + d.Nama) IS NULL THEN 'BELUM ADA' ELSE 'ADA' END AS Keadaan
FROM (VALUES
        ('FK_N_SOP_Pjd_Rule',                                          'FOREIGN KEY'),
        ('CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Daftar_Anggota_Json', 'CHECK'),
        ('CK_N_VISITOR_Tamu_Efektif_Daftar_Anggota_Json',              'CHECK'),
        ('DF_N_EMI_VISITOR_Transaksi_Tamu_Detail_Tipe_Tamu',           'DEFAULT'),
        ('CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Tipe_Tamu',           'CHECK'),
        ('DF_N_VISITOR_Tamu_Efektif_Tipe_Tamu',                        'DEFAULT'),
        ('CK_N_VISITOR_Tamu_Efektif_Tipe_Tamu',                        'CHECK'),
        ('DF_N_HRIS_Grup_Fitur_Map_Flag_Hibah_Role',                   'DEFAULT'),
        ('CK_N_HRIS_Grup_Fitur_Map_Flag_Hibah_Role',                   'CHECK')
     ) AS d (Nama, Jenis);

SELECT  i.name AS Indeks, OBJECT_NAME(i.object_id) AS Tabel, i.is_unique AS Unik,
        i.filter_definition AS Filter,
        STUFF((SELECT ', ' + c.name
               FROM sys.index_columns ic
               JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
               WHERE ic.object_id = i.object_id AND ic.index_id = i.index_id AND ic.is_included_column = 0
               ORDER BY ic.key_ordinal
               FOR XML PATH('')), 1, 2, '') AS Kolom_Kunci
FROM sys.indexes i
WHERE i.name IN ('UX_VISITOR_Permit_Requester_Idempotency', 'IX_VISITOR_Permit_Api_Aplikasi',
                 'UX_VISITOR_Tamu_Requester_Idempotency', 'IX_VISITOR_Tamu_Api_Aplikasi',
                 'UX_VISITOR_Permit_Transaksi_Tamu', 'IX_VISITOR_Permit_Pemohon_Sumber_Tamu')
ORDER BY OBJECT_NAME(i.object_id), i.name;
GO

PRINT '=== Selesai: ' + CONVERT(varchar(30), SYSDATETIME(), 121) + ' ===';
GO

-- ------------------------------------------------------------------------
-- SUMBER: database/ddl/DDL-2026-121-visitor-pisah-tamu-regular-b2b/forward.sql
-- ------------------------------------------------------------------------
-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-121-visitor-pisah-tamu-regular-b2b

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Tamu_Kategori') IS NULL
BEGIN
    ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail ADD Tamu_Kategori varchar(10) NULL;
END
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Path_Foto_Kendaraan') IS NULL
BEGIN
    ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail ADD Path_Foto_Kendaraan varchar(500) NULL;
END
GO

-- ------------------------------------------------------------------------
-- SUMBER: database/ddl/DDL-2026-122-visitor-matriks-tamu-departemen/forward.sql
-- ------------------------------------------------------------------------
-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-122-visitor-matriks-tamu-departemen

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Tamu_Detail', 'B2B_Unit') IS NULL
BEGIN
    ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail ADD B2B_Unit varchar(20) NULL;
END
GO

IF OBJECT_ID('N_VISITOR_Matriks_Tamu', 'U') IS NULL
BEGIN
    CREATE TABLE N_VISITOR_Matriks_Tamu (
        Id_Matriks_Tamu int IDENTITY(1,1) NOT NULL,
        Role_Id int NOT NULL,
        Kategori_Tamu varchar(20) NOT NULL,
        Flag_Lihat char(1) NOT NULL CONSTRAINT DF_N_VISITOR_Matriks_Tamu_Flag_Lihat DEFAULT ('T'),
        Flag_Ajukan char(1) NOT NULL CONSTRAINT DF_N_VISITOR_Matriks_Tamu_Flag_Ajukan DEFAULT ('T'),
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_N_VISITOR_Matriks_Tamu_Flag_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL CONSTRAINT DF_N_VISITOR_Matriks_Tamu_Created_At DEFAULT (SYSDATETIME()),
        Created_By varchar(100) NULL,
        Updated_At datetime2(0) NULL,
        Updated_By varchar(100) NULL,
        CONSTRAINT PK_N_VISITOR_Matriks_Tamu PRIMARY KEY (Id_Matriks_Tamu),
        CONSTRAINT FK_N_VISITOR_Matriks_Tamu_Role FOREIGN KEY (Role_Id) REFERENCES N_HRIS_Role_Functional (Id_Role)
    );
END
GO

-- ------------------------------------------------------------------------
-- SUMBER: database/ddl/DDL-2026-123-visitor-jadwal-inspeksi-permit/forward.sql
-- ------------------------------------------------------------------------
-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-123-visitor-jadwal-inspeksi-permit
-- r2: pos inspeksi menjadi master (N_VISITOR_Master_Pos_Inspeksi); pola,
-- pengecualian, dan booking merujuknya lewat Id_Pos_Inspeksi. Booking juga
-- menyimpan Snapshot_Nama_Pos (nama saat dipesan).
-- r3: shift kerja karyawan (N_VISITOR_Master_Shift_Inspeksi) menjadi wadah
-- jadwal; SHE membuka slot di sela-sela jam kerja tiap shift. Jam jadwal TIDAK
-- dibatasi jam shift — justru sering di luarnya.
-- r4: tiap hari punya ketiga shift; sakelar aktif/nonaktif disimpan per
-- (hari, shift) dan per hari penuh di N_VISITOR_Inspeksi_Hari_Shift.

IF OBJECT_ID('N_VISITOR_Master_Shift_Inspeksi', 'U') IS NULL
BEGIN
    CREATE TABLE N_VISITOR_Master_Shift_Inspeksi (
        Id_Shift_Inspeksi int IDENTITY(1,1) NOT NULL,
        Nama_Shift varchar(50) NOT NULL,
        -- Jam KERJA karyawan shift ini. Jam_Selesai <= Jam_Mulai berarti
        -- melewati tengah malam (Shift 3: 23:00-07:00).
        Jam_Mulai time(0) NOT NULL,
        Jam_Selesai time(0) NOT NULL,
        Urutan smallint NOT NULL CONSTRAINT DF_N_VISITOR_Master_Shift_Inspeksi_Urutan DEFAULT (0),
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_N_VISITOR_Master_Shift_Inspeksi_Flag_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL CONSTRAINT DF_N_VISITOR_Master_Shift_Inspeksi_Created_At DEFAULT (SYSDATETIME()),
        Created_By varchar(100) NULL,
        Updated_At datetime2(0) NULL,
        Updated_By varchar(100) NULL,
        CONSTRAINT PK_N_VISITOR_Master_Shift_Inspeksi PRIMARY KEY (Id_Shift_Inspeksi)
    );
END
GO

IF OBJECT_ID('N_VISITOR_Master_Pos_Inspeksi', 'U') IS NULL
BEGIN
    CREATE TABLE N_VISITOR_Master_Pos_Inspeksi (
        Id_Pos_Inspeksi int IDENTITY(1,1) NOT NULL,
        Nama_Pos varchar(100) NOT NULL,
        Keterangan_Lokasi varchar(250) NULL,
        Urutan smallint NOT NULL CONSTRAINT DF_N_VISITOR_Master_Pos_Inspeksi_Urutan DEFAULT (0),
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_N_VISITOR_Master_Pos_Inspeksi_Flag_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL CONSTRAINT DF_N_VISITOR_Master_Pos_Inspeksi_Created_At DEFAULT (SYSDATETIME()),
        Created_By varchar(100) NULL,
        Updated_At datetime2(0) NULL,
        Updated_By varchar(100) NULL,
        CONSTRAINT PK_N_VISITOR_Master_Pos_Inspeksi PRIMARY KEY (Id_Pos_Inspeksi)
    );
END
GO

IF OBJECT_ID('N_VISITOR_Inspeksi_Pola', 'U') IS NULL
BEGIN
    CREATE TABLE N_VISITOR_Inspeksi_Pola (
        Id_Inspeksi_Pola int IDENTITY(1,1) NOT NULL,
        Id_Pos_Inspeksi int NOT NULL,
        Id_Shift_Inspeksi int NOT NULL,
        Hari_Minggu tinyint NOT NULL,
        Jam_Mulai time(0) NOT NULL,
        Jam_Selesai time(0) NOT NULL,
        Durasi_Menit smallint NOT NULL,
        Kuota_Per_Slot smallint NOT NULL,
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_N_VISITOR_Inspeksi_Pola_Flag_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL CONSTRAINT DF_N_VISITOR_Inspeksi_Pola_Created_At DEFAULT (SYSDATETIME()),
        Created_By varchar(100) NULL,
        Updated_At datetime2(0) NULL,
        Updated_By varchar(100) NULL,
        CONSTRAINT PK_N_VISITOR_Inspeksi_Pola PRIMARY KEY (Id_Inspeksi_Pola)
    );
END
GO

IF OBJECT_ID('N_VISITOR_Inspeksi_Pengecualian', 'U') IS NULL
BEGIN
    CREATE TABLE N_VISITOR_Inspeksi_Pengecualian (
        Id_Inspeksi_Pengecualian int IDENTITY(1,1) NOT NULL,
        Tanggal date NOT NULL,
        Id_Pos_Inspeksi int NULL, -- NULL = semua pos (hanya TUTUP)
        Id_Shift_Inspeksi int NULL, -- slot tambahan boleh tanpa shift
        Jenis varchar(10) NOT NULL,
        Jam_Mulai time(0) NULL,
        Jam_Selesai time(0) NULL,
        Durasi_Menit smallint NULL,
        Kuota_Per_Slot smallint NULL,
        Keterangan varchar(250) NULL,
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_N_VISITOR_Inspeksi_Pengecualian_Flag_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL CONSTRAINT DF_N_VISITOR_Inspeksi_Pengecualian_Created_At DEFAULT (SYSDATETIME()),
        Created_By varchar(100) NULL,
        Updated_At datetime2(0) NULL,
        Updated_By varchar(100) NULL,
        CONSTRAINT PK_N_VISITOR_Inspeksi_Pengecualian PRIMARY KEY (Id_Inspeksi_Pengecualian)
    );
END
GO

IF OBJECT_ID('N_VISITOR_Inspeksi_Booking', 'U') IS NULL
BEGIN
    CREATE TABLE N_VISITOR_Inspeksi_Booking (
        Id_Inspeksi_Booking int IDENTITY(1,1) NOT NULL,
        No_Transaksi_Permit varchar(50) NOT NULL,
        Id_Pos_Inspeksi int NOT NULL,
        Snapshot_Nama_Pos varchar(100) NOT NULL,
        Id_Shift_Inspeksi int NULL,
        Waktu_Mulai datetime2(0) NOT NULL,
        Waktu_Selesai datetime2(0) NOT NULL,
        Status varchar(20) NOT NULL,
        Alasan varchar(250) NULL,
        Diinspeksi_Oleh varchar(100) NULL,
        Diinspeksi_At datetime2(0) NULL,
        Created_At datetime2(0) NOT NULL CONSTRAINT DF_N_VISITOR_Inspeksi_Booking_Created_At DEFAULT (SYSDATETIME()),
        Created_By varchar(100) NULL,
        Updated_At datetime2(0) NULL,
        Updated_By varchar(100) NULL,
        CONSTRAINT PK_N_VISITOR_Inspeksi_Booking PRIMARY KEY (Id_Inspeksi_Booking)
    );
END
GO

-- Sakelar hari & shift. TIDAK ADA BARIS = aktif; baris dengan Flag_Aktif='T'
-- mematikannya. Id_Shift_Inspeksi NULL = SELURUH hari itu dimatikan.
IF OBJECT_ID('N_VISITOR_Inspeksi_Hari_Shift', 'U') IS NULL
BEGIN
    CREATE TABLE N_VISITOR_Inspeksi_Hari_Shift (
        Id_Inspeksi_Hari_Shift int IDENTITY(1,1) NOT NULL,
        Hari_Minggu tinyint NOT NULL,
        Id_Shift_Inspeksi int NULL,
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_N_VISITOR_Inspeksi_Hari_Shift_Flag_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL CONSTRAINT DF_N_VISITOR_Inspeksi_Hari_Shift_Created_At DEFAULT (SYSDATETIME()),
        Created_By varchar(100) NULL,
        Updated_At datetime2(0) NULL,
        Updated_By varchar(100) NULL,
        CONSTRAINT PK_N_VISITOR_Inspeksi_Hari_Shift PRIMARY KEY (Id_Inspeksi_Hari_Shift)
    );
END
GO

-- ------------------------------------------------------------------------
-- SUMBER: database/ddl/DDL-2026-126-visitor-tamu-efektif-lengkap/forward.sql
-- ------------------------------------------------------------------------
-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-126-visitor-tamu-efektif-lengkap
--
-- Proyeksi N_VISITOR_Tamu_Efektif (dibaca aplikasi palang) ikut membawa
-- kolom hasil pemisahan Tamu Regular/B2B (DDL-2026-121, DDL-2026-122).
-- Tipe disamakan persis dengan asalnya di N_EMI_VISITOR_Transaksi_Tamu_Detail.
-- Rentang estimasi dinamai Waktu_Estimasi_Masuk/Keluar (asal Detail:
-- Waktu_Mulai/Selesai_Kunjungan) supaya berpasangan dengan Waktu_Aktual_Masuk/
-- Keluar yang ditulis palang dan tidak tertukar saat dibaca.
-- Kolom lama tidak diubah: palang yang ada tetap berjalan.

IF COL_LENGTH('N_VISITOR_Tamu_Efektif', 'Waktu_Estimasi_Masuk') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Tamu_Efektif ADD Waktu_Estimasi_Masuk datetime2(0) NULL;
END
GO

IF COL_LENGTH('N_VISITOR_Tamu_Efektif', 'Waktu_Estimasi_Keluar') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Tamu_Efektif ADD Waktu_Estimasi_Keluar datetime2(0) NULL;
END
GO

IF COL_LENGTH('N_VISITOR_Tamu_Efektif', 'Tamu_Kategori') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Tamu_Efektif ADD Tamu_Kategori varchar(10) NULL;
END
GO

IF COL_LENGTH('N_VISITOR_Tamu_Efektif', 'B2B_Unit') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Tamu_Efektif ADD B2B_Unit varchar(20) NULL;
END
GO

IF COL_LENGTH('N_VISITOR_Tamu_Efektif', 'Path_Foto_Kendaraan') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Tamu_Efektif ADD Path_Foto_Kendaraan varchar(500) NULL;
END
GO

-- ------------------------------------------------------------------------
-- SUMBER: database/ddl/DDL-2026-127-visitor-kode-kartu-permit/forward.sql
-- ------------------------------------------------------------------------
-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-127-visitor-kode-kartu-permit
--
-- Kode acak per kartu permit untuk QR keaslian.
--
-- Sebelumnya QR memuat nomor permit APA ADANYA + tanda tangan HMAC APP_KEY.
-- Dua akibatnya: nomor internal permit ikut tercetak di kartu, dan seluruh
-- kartu yang sudah tercetak mati serentak bila APP_KEY dirotasi.
--
-- Kolom ini menyimpan kode acak 22 karakter (16 byte, base64url). Verifikasi
-- mencarinya langsung, jadi kartu tidak lagi bergantung pada APP_KEY dan kode
-- satu kartu bisa diputar ulang tanpa menyentuh kartu lain.
--
-- NULL diperbolehkan: permit lama diisi DML, dan permit yang belum pernah
-- dicetak kartunya memang belum butuh kode.

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit', 'Kode_Kartu') IS NULL
BEGIN
    ALTER TABLE N_EMI_VISITOR_Transaksi_Permit ADD Kode_Kartu varchar(32) NULL;
END
GO


/* ==========================================================================
   VERIFIKASI STRUKTUR — jalankan SESUDAH berkas ini selesai.
   Harus mengembalikan NOL baris.
   ========================================================================== */
SELECT 'tabel' AS jenis, objek, 'BELUM ADA' AS keadaan
FROM (VALUES
    ('N_VISITOR_Matriks_Tamu'),
    ('N_VISITOR_Master_Shift_Inspeksi'),
    ('N_VISITOR_Master_Pos_Inspeksi'),
    ('N_VISITOR_Inspeksi_Pola'),
    ('N_VISITOR_Inspeksi_Pengecualian'),
    ('N_VISITOR_Inspeksi_Booking'),
    ('N_VISITOR_Inspeksi_Hari_Shift')
) AS t(objek)
WHERE OBJECT_ID('dbo.'+objek, 'U') IS NULL

UNION ALL
SELECT 'kolom', tabel+'.'+kolom, 'BELUM ADA'
FROM (VALUES
    ('N_EMI_VISITOR_Transaksi_Tamu_Detail','Tipe_Tamu'),
    ('N_EMI_VISITOR_Transaksi_Tamu_Detail','Tamu_Kategori'),
    ('N_EMI_VISITOR_Transaksi_Tamu_Detail','Path_Foto_Kendaraan'),
    ('N_EMI_VISITOR_Transaksi_Tamu_Detail','B2B_Unit'),
    ('N_VISITOR_Tamu_Efektif','Tipe_Tamu'),
    ('N_VISITOR_Tamu_Efektif','Waktu_Estimasi_Masuk'),
    ('N_VISITOR_Tamu_Efektif','Waktu_Estimasi_Keluar'),
    ('N_VISITOR_Tamu_Efektif','Tamu_Kategori'),
    ('N_VISITOR_Tamu_Efektif','B2B_Unit'),
    ('N_VISITOR_Tamu_Efektif','Path_Foto_Kendaraan'),
    ('N_EMI_VISITOR_Transaksi_Permit','Kode_Kartu')
) AS k(tabel, kolom)
WHERE COL_LENGTH(tabel, kolom) IS NULL;
GO