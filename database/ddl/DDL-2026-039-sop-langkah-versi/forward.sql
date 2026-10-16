/* Change ID: DDL-2026-039-sop-langkah-versi */

/* ALUR BERLANGKAH — membuat delapan tahap terlihat sebagai perjalanan.

   Tahap sudah hidup dan sudah menegakkan sejak DDL-2026-032, tetapi layar
   menampilkan satu form panjang yang isinya tak berubah sedikit pun antara
   Drafting dan Approval. Tahapnya karena itu terasa seperti hiasan.

   URUTAN LANGKAH DITURUNKAN DARI MASTER, bukan ditulis di kode. Alternatif yang
   ditolak: menyembunyikan field per tahap lewat cabang di layar — master sudah
   menyimpan "apa yang penting kapan" (Butuh_Berkas, Butuh_Pengesahan), dan layar
   yang menyimpan aturan sama akan menyimpang begitu master diubah. */

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Master_Tahap_Versi' AND COLUMN_NAME = 'Langkah')
    ALTER TABLE N_SOP_Master_Tahap_Versi ADD Langkah int NULL;
GO

/* Kunci kelompok field yang dibuka langkah ini. Disaring daftar putih di
   service — nama yang datang dari data tak pernah dipercaya sebagai rujukan
   komponen begitu saja, sama seperti Cap_Tanggal. */
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Master_Tahap_Versi' AND COLUMN_NAME = 'Kelompok')
    ALTER TABLE N_SOP_Master_Tahap_Versi ADD Kelompok varchar(60) NULL;
GO

/* KOLOM KOMPATIBILITAS JENIS PENGESAH.

   Rancangan awal sempat memisahkan RENCANA/REALISASI. Konsep rencana terpisah
   dibatalkan 2026-08-24: Tanggal Permintaan/Respon berada di Pengajuan, dan
   pengesah diisi satu kali di langkah Pengesahan. Semua jalur menulis
   REALISASI; kolom dipertahankan agar skema yang sudah dieksekusi tetap
   idempoten dan data yang ada tak perlu di-drop. */
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Item_Versi_Pengesah' AND COLUMN_NAME = 'Jenis')
    ALTER TABLE N_SOP_Item_Versi_Pengesah
        ADD Jenis varchar(12) NOT NULL CONSTRAINT DF_SOP_Pengesah_Jenis DEFAULT ('REALISASI');
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_SOP_Item_Versi_Pengesah') AND name = 'IX_SOP_Pengesah_Jenis')
    CREATE INDEX IX_SOP_Pengesah_Jenis ON N_SOP_Item_Versi_Pengesah (Id_SOP_Item_Versi, Jenis, Flag_Aktif);
GO

/* SYARAT GENERIK PER TAHAP.

   Butuh_Berkas/Butuh_Pengesahan tetap dipertahankan untuk kompatibilitas,
   tetapi kebutuhan baru tak boleh terus menambah kolom Butuh_X. Satu tabel
   generik memungkinkan tahap baru menyatakan syarat baru tanpa perubahan
   skema, service, maupun layar. */
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'N_SOP_Master_Tahap_Versi_Syarat')
CREATE TABLE N_SOP_Master_Tahap_Versi_Syarat (
    Id_Syarat   int IDENTITY(1,1) NOT NULL,
    Tahap_Id    int           NOT NULL,
    Kode_Syarat varchar(40)   NOT NULL,
    Nama_Syarat nvarchar(100) NOT NULL,
    Urutan      int           NOT NULL CONSTRAINT DF_SOP_Tahap_Syarat_Urut DEFAULT (0),
    Flag_Aktif  char(1)       NOT NULL CONSTRAINT DF_SOP_Tahap_Syarat_Aktif DEFAULT ('Y'),
    Created_At  datetime2(0)  NOT NULL CONSTRAINT DF_SOP_Tahap_Syarat_Created DEFAULT (SYSDATETIME()),
    Created_By  int           NULL,
    CONSTRAINT PK_SOP_Master_Tahap_Versi_Syarat PRIMARY KEY (Id_Syarat),
    CONSTRAINT FK_SOP_Tahap_Syarat_Tahap FOREIGN KEY (Tahap_Id)
        REFERENCES N_SOP_Master_Tahap_Versi (Id_Tahap)
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_SOP_Master_Tahap_Versi_Syarat') AND name = 'UX_SOP_Tahap_Syarat')
    CREATE UNIQUE INDEX UX_SOP_Tahap_Syarat
        ON N_SOP_Master_Tahap_Versi_Syarat (Tahap_Id, Kode_Syarat);
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-039-sop-langkah-versi')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DDL-2026-039-sop-langkah-versi', 'DDL', 'Alur berlangkah versi dan syarat generik', 'Kolom Langkah/Kelompok pada master tahap, kolom kompatibilitas Jenis, serta tabel syarat generik per tahap.', 'database/ddl/DDL-2026-039-sop-langkah-versi/forward.sql', 'docs/changes/DDL-2026-039-sop-langkah-versi/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'langkah-versi');

/* Sinkronkan metadata bila change ini sudah didaftarkan sebelum keputusan final. */
UPDATE N_SYSTEM_Database_Change
SET Judul_Change = 'Alur berlangkah versi dan syarat generik',
    Deskripsi_Change = 'Kolom Langkah/Kelompok pada master tahap, kolom kompatibilitas Jenis, serta tabel syarat generik per tahap.'
WHERE Kode_Change = 'DDL-2026-039-sop-langkah-versi';
GO
