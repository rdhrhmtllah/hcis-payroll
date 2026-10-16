/* Rollback DDL-2026-083 r2. Membuang dua kolom yang ditambahkan pada
   N_PA_Cycle_Question_Package_Item beserta FK dan CHECK-nya.

   TIDAK membuang tabelnya: tabel itu milik DDL-2026-073, bukan milik change ini.

   Jalankan HANYA di development. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50350, 'drop DDL-2026-083 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_Cycle_Question_Item_Peran')
    ALTER TABLE N_PA_Cycle_Question_Package_Item DROP CONSTRAINT CK_PA_Cycle_Question_Item_Peran;
GO

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Question_Item_Note_Type')
    ALTER TABLE N_PA_Cycle_Question_Package_Item DROP CONSTRAINT FK_PA_Cycle_Question_Item_Note_Type;
GO

IF COL_LENGTH('N_PA_Cycle_Question_Package_Item', 'PA_Result_Note_Type_Id') IS NOT NULL
    ALTER TABLE N_PA_Cycle_Question_Package_Item DROP COLUMN PA_Result_Note_Type_Id;
GO

IF COL_LENGTH('N_PA_Cycle_Question_Package_Item', 'Jenis_Soal') IS NOT NULL
    ALTER TABLE N_PA_Cycle_Question_Package_Item DROP COLUMN Jenis_Soal;
GO
