/*
| Pembalik DDL-2026-090.
|
| Hanya STRUKTUR yang dikembalikan. Data ikatan dan snapshot siklus yang dihapus BAGIAN 1
| forward.sql tidak dipulihkan — seluruhnya artefak simulasi dan siklus uji development,
| dan memulihkannya berarti mengembalikan baris berkontrak kode-soal yang sudah tidak
| bisa dibaca kode mana pun.
*/

SET XACT_ABORT ON;
BEGIN TRANSACTION;

CREATE TABLE N_PA_Competency_Kode_Soal (
    Id_PA_Competency_Kode_Soal int IDENTITY(1,1) NOT NULL,
    PA_Competency_Id           int NOT NULL,
    Kode_Soal                  varchar(100) NOT NULL,
    Nama_Soal_Sumber           nvarchar(200) NULL,
    Sumber_Pemetaan            varchar(20) NOT NULL,
    Flag_Aktif                 char(1) NOT NULL
        CONSTRAINT DF_PA_CompKodeSoal_Flag DEFAULT ('Y'),
    Created_At                 datetime2(0) NOT NULL
        CONSTRAINT DF_PA_CompKodeSoal_Created DEFAULT (SYSDATETIME()),
    Created_By                 int NULL,
    Updated_At                 datetime2(0) NULL,
    Updated_By                 int NULL,
    CONSTRAINT PK_PA_Competency_Kode_Soal PRIMARY KEY (Id_PA_Competency_Kode_Soal),
    CONSTRAINT FK_PA_Competency_Kode_Soal_Competency FOREIGN KEY (PA_Competency_Id)
        REFERENCES N_PA_Competency (Id_PA_Competency)
);

DROP INDEX IX_PA_Cycle_Question_Item_Cakupan ON N_PA_Cycle_Question_Package_Item;
DROP INDEX UX_PA_CycleQItem_Kompetensi       ON N_PA_Cycle_Question_Package_Item;
DROP INDEX UX_PA_CycleQItem_Bagian           ON N_PA_Cycle_Question_Package_Item;
ALTER TABLE N_PA_Cycle_Question_Package_Item ALTER COLUMN PA_Competency_Id int NULL;
ALTER TABLE N_PA_Cycle_Question_Package_Item DROP CONSTRAINT DF_PA_CycleQItem_Indikator;
ALTER TABLE N_PA_Cycle_Question_Package_Item DROP COLUMN Id_Jenis_Indikator, Nama_Alat_Tes;

DROP TABLE N_PA_Question_Binding_Master_Detail;

ALTER TABLE N_PA_Cycle_Question_Package DROP COLUMN Id_Master_Paket_Ujian, Nama_Paket_Ujian_Sumber;

ALTER TABLE N_PA_Question_Binding_Master DROP CONSTRAINT DF_PA_QBindMaster_Kesehatan;
ALTER TABLE N_PA_Question_Binding_Master
    DROP COLUMN Id_Master_Paket_Ujian, Nama_Paket_Ujian_Sumber, Status_Kesehatan, Masalah_Json;

EXEC sp_rename 'N_PA_Cycle_Question_Package_Item.Nama_Bagian_Sumber', 'Nama_Soal_Sumber',  'COLUMN';
EXEC sp_rename 'N_PA_Cycle_Question_Package_Item.Id_Jenis_Soal',      'Kode_Soal',         'COLUMN';
EXEC sp_rename 'N_PA_Cycle_Question_Package.Nama_Ujian_Sumber',       'Nama_Paket_Sumber', 'COLUMN';
EXEC sp_rename 'N_PA_Cycle_Question_Package.Id_Master_Ujian',         'Kode_Paket_Sumber', 'COLUMN';

EXEC sp_rename 'N_PA_Question_Binding_Master.Sidik_Bagian',      'Sidik_Susunan',     'COLUMN';
EXEC sp_rename 'N_PA_Question_Binding_Master.Jumlah_Bagian',     'Jumlah_Soal',       'COLUMN';
EXEC sp_rename 'N_PA_Question_Binding_Master.Nama_Ujian_Sumber', 'Nama_Paket_Sumber', 'COLUMN';
EXEC sp_rename 'N_PA_Question_Binding_Master.Id_Master_Ujian',   'Kode_Paket_Sumber', 'COLUMN';

/* Dua indeks yang dicabut forward.sql dibangun kembali dengan bentuk aslinya. Dibuat SESUDAH
   nama kolomnya dipulihkan, kalau tidak ia menunjuk kolom yang belum ada lagi. */
CREATE UNIQUE INDEX UX_PA_Cycle_Question_Item_Aktif
    ON N_PA_Cycle_Question_Package_Item (PA_Cycle_Question_Package_Id, Kode_Soal)
    WHERE Flag_Aktif = 'Y';

CREATE INDEX IX_PA_Cycle_Question_Item_Cakupan
    ON N_PA_Cycle_Question_Package_Item (PA_Cycle_Question_Package_Id, Flag_Aktif)
    INCLUDE (Kode_Soal, PA_Competency_Id, Bobot_Penilaian, Skor_Maksimal);

COMMIT TRANSACTION;
