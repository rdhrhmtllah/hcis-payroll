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

/* Penjaga tumpang tindih yang menggantikan UQ_N_SOP_Assignment_Rule_Paket.
 *
 * Terfilter WHERE Flag_Aktif='Y' -- dua sebab. Pertama, paket yang dilepas dari
 * sebuah aturan harus langsung bebas diklaim aturan lain tanpa barisnya
 * dihapus. Kedua, filtered index TIDAK menyamakan baris mati satu sama lain,
 * berbeda dengan UNIQUE CONSTRAINT biasa yang menganggap semua NULL bentrok --
 * jebakan yang persis mematikan penjaga lama. */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_SOP_Assignment_Rule_Paket_Paket')
CREATE UNIQUE INDEX UX_SOP_Assignment_Rule_Paket_Paket
    ON N_SOP_Assignment_Rule_Paket (Id_SOP_Paket)
    WHERE Flag_Aktif = 'Y';
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_SOP_Assignment_Rule_Paket_Rule')
CREATE INDEX IX_SOP_Assignment_Rule_Paket_Rule
    ON N_SOP_Assignment_Rule_Paket (Id_SOP_Assignment_Rule, Urutan);
GO

/* ================= 2. RIWAYAT: BARIS INDUK + BARIS ANAK PER PAKET ================= */

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Automation_Run' AND COLUMN_NAME = 'Id_SOP_Paket')
    ALTER TABLE N_SOP_Automation_Run ADD Id_SOP_Paket int NULL;
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Automation_Run' AND COLUMN_NAME = 'Id_Run_Induk')
    ALTER TABLE N_SOP_Automation_Run ADD Id_Run_Induk int NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_SOP_Automation_Run_Induk')
CREATE INDEX IX_SOP_Automation_Run_Induk
    ON N_SOP_Automation_Run (Id_Run_Induk) WHERE Id_Run_Induk IS NOT NULL;
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
