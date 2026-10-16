/*
| Pembalik DDL-2026-092.
|
| Mengembalikan Bobot_Persen dan Min_Rater menjadi NOT NULL berikut penjaga aslinya.
|
| AKAN GAGAL bila masih ada sel yang BERLAKU tetapi belum berbobot, dan itu memang yang
| diinginkan: baris ber-NULL tidak punya nilai pengganti yang jujur. Nol bukan jawabannya —
| ia berarti "kategori ini menilai, tetapi sumbangannya nol", pernyataan yang berbeda dan
| akan ikut terbawa ke salinan siklus.
|
| Jadi sebelum menjalankan ini: selesaikan dulu pengisian bobotnya di Komposisi Penilai,
| atau cabut sel yang belum berbobot lewat Matriks Jenis Penilai. BAGIAN 1 di bawah
| menyebutkan berapa yang tersisa supaya keputusan itu bisa diambil, bukan ditebak.
*/

SET XACT_ABORT ON;

/* ---------- BAGIAN 1: apa yang menghalangi, hanya SELECT ---------- */

SELECT
    p.ID_Level,
    c.Kode_Kategori,
    d.Id_PA_Rater_Policy_Detail
FROM N_PA_Rater_Policy_Detail AS d
JOIN N_PA_Rater_Policy AS p ON p.Id_PA_Rater_Policy = d.PA_Rater_Policy_Id
JOIN N_PA_Rater_Category AS c ON c.Id_PA_Rater_Category = d.PA_Rater_Category_Id
WHERE d.Flag_Aktif = 'Y'
  AND d.Bobot_Persen IS NULL
ORDER BY p.ID_Level, c.Kode_Kategori;
GO

/* ---------- BAGIAN 2: pembalikan ---------- */

IF EXISTS (SELECT 1 FROM N_PA_Rater_Policy_Detail WHERE Bobot_Persen IS NULL OR Min_Rater IS NULL)
    THROW 51422, 'DDL-2026-092 tidak bisa dibalik: masih ada sel yang belum berbobot. Isi bobotnya atau cabut selnya lebih dulu.', 1;
GO

BEGIN TRANSACTION;

IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_Rater_Policy_Detail_Sepasang')
    ALTER TABLE N_PA_Rater_Policy_Detail DROP CONSTRAINT CK_PA_Rater_Policy_Detail_Sepasang;

IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_Rater_Policy_Detail_Bobot')
    ALTER TABLE N_PA_Rater_Policy_Detail DROP CONSTRAINT CK_PA_Rater_Policy_Detail_Bobot;

IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_Rater_Policy_Detail_Min_Rater')
    ALTER TABLE N_PA_Rater_Policy_Detail DROP CONSTRAINT CK_PA_Rater_Policy_Detail_Min_Rater;

IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_Rater_Policy_Detail_Maks_Rater')
    ALTER TABLE N_PA_Rater_Policy_Detail DROP CONSTRAINT CK_PA_Rater_Policy_Detail_Maks_Rater;

IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Rater_Policy_Detail') AND name = 'IX_PA_Rater_Policy_Detail_Category'
)
    DROP INDEX IX_PA_Rater_Policy_Detail_Category ON N_PA_Rater_Policy_Detail;

ALTER TABLE N_PA_Rater_Policy_Detail ALTER COLUMN Bobot_Persen decimal(5,2) NOT NULL;
ALTER TABLE N_PA_Rater_Policy_Detail ALTER COLUMN Min_Rater smallint NOT NULL;

ALTER TABLE N_PA_Rater_Policy_Detail ADD CONSTRAINT CK_PA_Rater_Policy_Detail_Bobot
    CHECK (Bobot_Persen >= 0 AND Bobot_Persen <= 100);

ALTER TABLE N_PA_Rater_Policy_Detail ADD CONSTRAINT CK_PA_Rater_Policy_Detail_Min_Rater
    CHECK (Min_Rater >= 1);

ALTER TABLE N_PA_Rater_Policy_Detail ADD CONSTRAINT CK_PA_Rater_Policy_Detail_Maks_Rater
    CHECK (Maks_Rater IS NULL OR Maks_Rater >= Min_Rater);

CREATE INDEX IX_PA_Rater_Policy_Detail_Category
    ON N_PA_Rater_Policy_Detail (PA_Rater_Category_Id, Flag_Aktif)
    INCLUDE (PA_Rater_Policy_Id, Bobot_Persen);

DELETE FROM N_SYSTEM_Database_Change
WHERE Kode_Change = 'DDL-2026-092-pa-sel-penilai-belum-berbobot';

COMMIT TRANSACTION;
GO
