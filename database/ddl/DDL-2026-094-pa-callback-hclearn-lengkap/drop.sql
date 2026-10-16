/* Change ID: DDL-2026-094-pa-callback-hclearn-lengkap — PEMBERSIH MANUAL.
   BUKAN untuk dijalankan agent, dan BUKAN rollback produksi.

   Menjatuhkan kolomnya MEMBUANG DATA: interpretasi, warna, keterangan, cacah soal, dan
   seluruh blok penilaian yang sudah telanjur diterima dari HC Learn tidak bisa dipulihkan
   dari mana pun kecuali dengan mengurai ulang Payload_Json satu per satu — dan payload
   yang datang sesudah kolomnya dijatuhkan tidak akan pernah mengisinya lagi.

   Dipakai hanya untuk membangun ulang basis pengembangan dari nol. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50196, 'drop DDL-2026-094 hanya untuk Web_HRIS pengembangan.', 1;
GO

IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_AsgScore_Terjawab')
    ALTER TABLE N_PA_Assignment_Score DROP CONSTRAINT CK_PA_AsgScore_Terjawab;
GO

IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_AsgPayload_Flag_Multi')
    ALTER TABLE N_PA_Assignment_Payload DROP CONSTRAINT CK_PA_AsgPayload_Flag_Multi;
GO

IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_AsgPayload_Status_Skor')
    ALTER TABLE N_PA_Assignment_Payload DROP CONSTRAINT CK_PA_AsgPayload_Status_Skor;
GO

IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Assignment_Payload') AND name = 'IX_PA_AsgPayload_ApiRef'
)
    DROP INDEX IX_PA_AsgPayload_ApiRef ON N_PA_Assignment_Payload;
GO

ALTER TABLE N_PA_Assignment_Score
    DROP COLUMN Kode_Alat_Tes, Nama_Alat_Tes, Status_Interpretation, Warna, Keterangan,
                Jumlah_Soal, Jumlah_Terjawab;
GO

ALTER TABLE N_PA_Assignment_Payload
    DROP COLUMN Api_Reference, Status_Skor, Kode_Akses, Flag_Multi, Total_Nilai,
                Nama_Tes, Waktu_Mulai_Akses, Waktu_Selesai_Akses, Ambang_Batas_Nilai,
                Status_Kelulusan, Warna;
GO

DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-094';
GO
