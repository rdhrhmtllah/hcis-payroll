/* Change ID: DDL-2026-032-sop-tahap-versi */

/* TAHAP PROSES VERSI — bahasa manusia di atas Status yang sudah ada.

   Sebelum ini seluruh versi yang belum terbit hanya bernama 'DRAFT': tak
   terbedakan antara "baru diketik", "sedang direview", "menunggu tanda tangan",
   dan "ditahan". Tiga puluh dari 75 versi memang sudah lahir tanpa berkas, jadi
   yang hilang bukan kemampuannya melainkan tahapannya.

   KENAPA MELAPISI, BUKAN MENGGANTI Status. Kolom N_SOP_Item_Versi.Status dibaca
   sangat luas — rilis paket, progres karyawan, monitoring, ekspor. Mengganti
   isinya dengan delapan nilai baru memaksa 40-an pembaca ikut berubah serentak,
   dan yang terlewat satu pun akan diam-diam salah membaca keadaan versi.

   Karena itu Status TETAP empat nilai teknis (DRAFT/READY/PUBLISHED/OBSOLETE),
   dan pemetaan tahap ke Status disimpan SEBAGAI DATA di kolom Status_Teknis.
   Admin bisa menambah tahap tanpa satu baris kode pun berubah, selama tahap itu
   menyatakan dirinya berdiri di atas Status yang mana. */

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'N_SOP_Master_Tahap_Versi')
CREATE TABLE N_SOP_Master_Tahap_Versi (
    Id_Tahap             int IDENTITY(1,1) NOT NULL,
    Kode_Tahap           varchar(30)   NOT NULL,
    Nama_Tahap           nvarchar(100) NOT NULL,
    /* Status teknis yang diwakili tahap ini. Inilah jembatan ke seluruh
       pembaca lama, dan sengaja data — bukan switch di kode. */
    Status_Teknis        varchar(20)   NOT NULL,
    Deskripsi            nvarchar(500) NULL,
    Warna                varchar(20)   NULL,
    Urutan               int           NOT NULL CONSTRAINT DF_SOP_Tahap_Urutan DEFAULT (0),
    /* Syarat MASUK tahap ini. Dinyatakan sebagai data supaya admin bisa
       mengendurkan atau mengetatkan alur tanpa rilis. */
    Butuh_Berkas         char(1)       NOT NULL CONSTRAINT DF_SOP_Tahap_Berkas DEFAULT ('T'),
    Butuh_Pengesahan     char(1)       NOT NULL CONSTRAINT DF_SOP_Tahap_Sah DEFAULT ('T'),
    /* Tahap akhir: tak punya transisi keluar. */
    Is_Terminal          char(1)       NOT NULL CONSTRAINT DF_SOP_Tahap_Terminal DEFAULT ('T'),
    /* Masuk tahap ini menonaktifkan versinya (dipakai Cancel). */
    Nonaktifkan_Versi    char(1)       NOT NULL CONSTRAINT DF_SOP_Tahap_Nonaktif DEFAULT ('T'),
    Flag_Aktif           char(1)       NOT NULL CONSTRAINT DF_SOP_Tahap_Aktif DEFAULT ('Y'),
    Created_At           datetime2(0)  NOT NULL CONSTRAINT DF_SOP_Tahap_Created DEFAULT (SYSDATETIME()),
    Created_By           int           NULL,
    Updated_At           datetime2(0)  NULL,
    Updated_By           int           NULL,
    CONSTRAINT PK_SOP_Master_Tahap_Versi PRIMARY KEY (Id_Tahap)
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_SOP_Master_Tahap_Versi') AND name = 'UX_SOP_Tahap_Versi_Kode')
    CREATE UNIQUE INDEX UX_SOP_Tahap_Versi_Kode ON N_SOP_Master_Tahap_Versi (Kode_Tahap);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_SOP_Master_Tahap_Versi') AND name = 'IX_SOP_Tahap_Versi_Urut')
    CREATE INDEX IX_SOP_Tahap_Versi_Urut ON N_SOP_Master_Tahap_Versi (Flag_Aktif, Urutan) INCLUDE (Kode_Tahap, Nama_Tahap);
GO

/* TRANSISI YANG DIIZINKAN — juga data, bukan kode.

   Alur persetujuan berubah lebih sering daripada perangkat lunaknya. Menuliskan
   "Review hanya boleh ke Approval" di dalam kode berarti setiap perubahan
   kebijakan menunggu rilis. */
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'N_SOP_Master_Tahap_Versi_Transisi')
CREATE TABLE N_SOP_Master_Tahap_Versi_Transisi (
    Id_Transisi   int IDENTITY(1,1) NOT NULL,
    Dari_Tahap_Id int          NOT NULL,
    Ke_Tahap_Id   int          NOT NULL,
    Flag_Aktif    char(1)      NOT NULL CONSTRAINT DF_SOP_Transisi_Aktif DEFAULT ('Y'),
    Created_At    datetime2(0) NOT NULL CONSTRAINT DF_SOP_Transisi_Created DEFAULT (SYSDATETIME()),
    Created_By    int          NULL,
    CONSTRAINT PK_SOP_Master_Tahap_Versi_Transisi PRIMARY KEY (Id_Transisi),
    CONSTRAINT FK_SOP_Transisi_Dari FOREIGN KEY (Dari_Tahap_Id) REFERENCES N_SOP_Master_Tahap_Versi (Id_Tahap),
    CONSTRAINT FK_SOP_Transisi_Ke   FOREIGN KEY (Ke_Tahap_Id)   REFERENCES N_SOP_Master_Tahap_Versi (Id_Tahap)
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_SOP_Master_Tahap_Versi_Transisi') AND name = 'UX_SOP_Transisi_Pasangan')
    CREATE UNIQUE INDEX UX_SOP_Transisi_Pasangan ON N_SOP_Master_Tahap_Versi_Transisi (Dari_Tahap_Id, Ke_Tahap_Id);
GO

/* Tahap yang sedang ditempati sebuah versi.

   NULLABLE, dan tetap nullable: 75 versi lahir sebelum kolom ini ada. Yang
   kosong dibaca sebagai tahap turunan dari Status-nya, sehingga tak ada versi
   yang mendadak kehilangan keadaan hanya karena backfill belum menyentuhnya. */
IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'N_SOP_Item_Versi' AND COLUMN_NAME = 'Tahap_Id'
)
    ALTER TABLE N_SOP_Item_Versi ADD Tahap_Id int NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_SOP_Item_Versi_Tahap')
    ALTER TABLE N_SOP_Item_Versi ADD CONSTRAINT FK_SOP_Item_Versi_Tahap
        FOREIGN KEY (Tahap_Id) REFERENCES N_SOP_Master_Tahap_Versi (Id_Tahap);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_SOP_Item_Versi') AND name = 'IX_SOP_Item_Versi_Tahap')
    CREATE INDEX IX_SOP_Item_Versi_Tahap ON N_SOP_Item_Versi (Tahap_Id);
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-032-sop-tahap-versi')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DDL-2026-032-sop-tahap-versi', 'DDL', 'Tahap proses versi dokumen SOP', 'Master tahap + transisi sebagai data, plus kolom Tahap_Id pada versi. Melapisi Status teknis, tidak menggantinya.', 'database/ddl/DDL-2026-032-sop-tahap-versi/forward.sql', 'docs/changes/DDL-2026-032-sop-tahap-versi/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'tahap-versi');
GO
