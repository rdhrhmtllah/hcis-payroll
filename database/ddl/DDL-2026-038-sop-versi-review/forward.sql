/* Change ID: DDL-2026-038-sop-versi-review */

/* CATATAN REVIEW PER VERSI — bisa lebih dari satu.

   Sebuah versi biasanya direview lebih dari sekali sebelum disetujui, dan tiap
   putaran punya tanggal serta hasilnya sendiri. Menyimpannya sebagai kolom di
   N_SOP_Item_Versi berarti memilih angka maksimum di muka — dan angka itu selalu
   salah: terlalu kecil bagi dokumen yang berputar lama, terlalu besar bagi yang
   lolos sekali jalan.

   BEDA DARI Tanggal_Respon (DDL-2026-034). Yang di sana adalah SATU tanggal
   ringkas pada identitas versi — kapan hasil review keluar, tercap otomatis saat
   tahap bergeser. Yang di sini adalah CATATANNYA: isi review putaran ke berapa,
   apa hasilnya, dan lampirannya. Keduanya menjawab pertanyaan berbeda dan
   sengaja tidak digabung. */

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'N_SOP_Item_Versi_Review')
CREATE TABLE N_SOP_Item_Versi_Review (
    Id_Review         int IDENTITY(1,1) NOT NULL,
    Id_SOP_Item_Versi int            NOT NULL,
    /* Nomor putaran, dihitung per versi. Disimpan (bukan diturunkan dari urutan
       baris) supaya "Review 2" tetap bernama Review 2 setelah Review 1 dihapus. */
    Review_Ke         int            NOT NULL,
    Tanggal_Review    date           NULL,
    /* HTML TERSANITASI. Disanitasi di SERVER lewat SopHtml::sanitize() — daftar
       putih 15 tag yang tak memuat <img>, jadi gambar tak pernah bisa menyusup
       lewat isi teks. Sanitasi di layar saja bisa dilewati panggilan API
       langsung, dan karena itu tak dianggap penjagaan. */
    Hasil_Review      nvarchar(max)  NULL,
    Flag_Aktif        char(1)        NOT NULL CONSTRAINT DF_SOP_Review_Aktif DEFAULT ('Y'),
    Created_At        datetime2(0)   NOT NULL CONSTRAINT DF_SOP_Review_Created DEFAULT (SYSDATETIME()),
    Created_By        int            NULL,
    Updated_At        datetime2(0)   NULL,
    Updated_By        int            NULL,
    CONSTRAINT PK_SOP_Item_Versi_Review PRIMARY KEY (Id_Review),
    CONSTRAINT FK_SOP_Review_Versi FOREIGN KEY (Id_SOP_Item_Versi)
        REFERENCES N_SOP_Item_Versi (Id_SOP_Item_Versi)
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_SOP_Item_Versi_Review') AND name = 'IX_SOP_Review_Versi')
    CREATE INDEX IX_SOP_Review_Versi ON N_SOP_Item_Versi_Review (Id_SOP_Item_Versi, Flag_Aktif, Review_Ke);
GO

/* LAMPIRAN GAMBAR — tabel sendiri, bukan kolom.

   Satu review boleh punya nol sampai banyak gambar. Kolom Lampiran_1..n akan
   memaksa memilih angka maksimum di muka, dan baris yang tak memakainya menyimpan
   kolom kosong selamanya. */
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'N_SOP_Item_Versi_Review_Lampiran')
CREATE TABLE N_SOP_Item_Versi_Review_Lampiran (
    Id_Lampiran   int IDENTITY(1,1) NOT NULL,
    Id_Review     int            NOT NULL,
    /* Path pada disk 'gcs'. Mengikuti pola N_HRIS_Master_Entitas.Logo_Path:
       menampung path hasil unggah ATAU URL/path publik yang sudah ada. */
    Path_Berkas   varchar(500)   NOT NULL,
    Nama_Berkas   nvarchar(255)  NULL,
    Tipe_Berkas   varchar(50)    NULL,
    Ukuran_Byte   bigint         NULL,
    Urutan        int            NOT NULL CONSTRAINT DF_SOP_Review_Lamp_Urut DEFAULT (0),
    Flag_Aktif    char(1)        NOT NULL CONSTRAINT DF_SOP_Review_Lamp_Aktif DEFAULT ('Y'),
    Created_At    datetime2(0)   NOT NULL CONSTRAINT DF_SOP_Review_Lamp_Created DEFAULT (SYSDATETIME()),
    Created_By    int            NULL,
    CONSTRAINT PK_SOP_Item_Versi_Review_Lampiran PRIMARY KEY (Id_Lampiran),
    CONSTRAINT FK_SOP_Review_Lampiran_Review FOREIGN KEY (Id_Review)
        REFERENCES N_SOP_Item_Versi_Review (Id_Review)
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_SOP_Item_Versi_Review_Lampiran') AND name = 'IX_SOP_Review_Lampiran')
    CREATE INDEX IX_SOP_Review_Lampiran ON N_SOP_Item_Versi_Review_Lampiran (Id_Review, Flag_Aktif, Urutan);
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-038-sop-versi-review')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DDL-2026-038-sop-versi-review', 'DDL', 'Catatan review per versi beserta lampirannya', 'Dua tabel anak: catatan review berulang (HTML tersanitasi server) dan lampiran gambarnya.', 'database/ddl/DDL-2026-038-sop-versi-review/forward.sql', 'docs/changes/DDL-2026-038-sop-versi-review/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'versi-review');
GO
