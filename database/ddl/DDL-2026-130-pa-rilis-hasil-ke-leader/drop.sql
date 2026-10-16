/* Change ID: DDL-2026-130-pa-rilis-hasil-ke-leader — PEMBATALAN. TIDAK DIJALANKAN AGEN.
   PERHATIAN: membuang jejak siapa merilis hasil ke leader dan kapan, beserta sebab
   pencabutannya. Sesudah ini, hasil hanya terlihat leader bila sudah TERBIT. */
IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Result') AND name = 'IX_PA_Result_Published_Leader')
    DROP INDEX IX_PA_Result_Published_Leader ON N_PA_Result;
GO
IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Result') AND name = 'Alasan_Cabut_Leader')
    ALTER TABLE N_PA_Result DROP COLUMN Alasan_Cabut_Leader;
GO
IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Result') AND name = 'Unpublished_Leader_By')
    ALTER TABLE N_PA_Result DROP COLUMN Unpublished_Leader_By;
GO
IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Result') AND name = 'Unpublished_Leader_At')
    ALTER TABLE N_PA_Result DROP COLUMN Unpublished_Leader_At;
GO
IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Result') AND name = 'Published_Leader_By')
    ALTER TABLE N_PA_Result DROP COLUMN Published_Leader_By;
GO
IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Result') AND name = 'Published_Leader_At')
    ALTER TABLE N_PA_Result DROP COLUMN Published_Leader_At;
GO
DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-130';
GO
