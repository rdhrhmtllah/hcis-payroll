/* Rollback DDL-2026-082. Membuang tiga kolom pembatalan beserta CHECK-nya.
   Jalankan HANYA di development. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50336, 'drop DDL-2026-082 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_Assignment_Cancel_Lengkap')
    ALTER TABLE N_PA_Assignment DROP CONSTRAINT CK_PA_Assignment_Cancel_Lengkap;
GO
IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_Assignment_Cancel_Reason')
    ALTER TABLE N_PA_Assignment DROP CONSTRAINT CK_PA_Assignment_Cancel_Reason;
GO
IF COL_LENGTH('N_PA_Assignment', 'Cancelled_By') IS NOT NULL
    ALTER TABLE N_PA_Assignment DROP COLUMN Cancelled_By;
GO
IF COL_LENGTH('N_PA_Assignment', 'Cancelled_At') IS NOT NULL
    ALTER TABLE N_PA_Assignment DROP COLUMN Cancelled_At;
GO
IF COL_LENGTH('N_PA_Assignment', 'Cancel_Reason') IS NOT NULL
    ALTER TABLE N_PA_Assignment DROP COLUMN Cancel_Reason;
GO
