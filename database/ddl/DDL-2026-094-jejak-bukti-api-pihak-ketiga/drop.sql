-- database/ddl/DDL-2026-094-jejak-bukti-api-pihak-ketiga/drop.sql
--
-- ALAT BANTU MANUAL. Jangan dijalankan sebagai bagian rilis.
--
-- Membalik forward.sql untuk keperluan reset di DEVELOPMENT. Menghapus kolomnya
-- MENGHILANGKAN seluruh jejak bukti yang sudah terkumpul: id korelasi, status
-- respons sebenarnya, durasi, dan ringkasan request/response. Justru bukti itu
-- yang tidak bisa dibuat ulang setelah kejadiannya lewat.

SET NOCOUNT ON;
GO

IF EXISTS (SELECT 1 FROM sys.check_constraints
            WHERE name = N'CK_N_LMS_Api_Logs_Sumber_Error'
              AND parent_object_id = OBJECT_ID('dbo.[N_LMS_Api_Logs]'))
    ALTER TABLE dbo.[N_LMS_Api_Logs] DROP CONSTRAINT [CK_N_LMS_Api_Logs_Sumber_Error];
GO

IF EXISTS (SELECT 1 FROM sys.check_constraints
            WHERE name = N'CK_N_LMS_Api_Logs_Modul'
              AND parent_object_id = OBJECT_ID('dbo.[N_LMS_Api_Logs]'))
    ALTER TABLE dbo.[N_LMS_Api_Logs] DROP CONSTRAINT [CK_N_LMS_Api_Logs_Modul];
GO

IF EXISTS (SELECT 1 FROM sys.indexes
            WHERE name = N'IX_N_LMS_Api_Logs_Correlation_Id'
              AND object_id = OBJECT_ID('dbo.[N_LMS_Api_Logs]'))
    DROP INDEX [IX_N_LMS_Api_Logs_Correlation_Id] ON dbo.[N_LMS_Api_Logs];
GO

IF EXISTS (SELECT 1 FROM sys.indexes
            WHERE name = N'IX_N_LMS_Api_Logs_Aplikasi_Waktu'
              AND object_id = OBJECT_ID('dbo.[N_LMS_Api_Logs]'))
    DROP INDEX [IX_N_LMS_Api_Logs_Aplikasi_Waktu] ON dbo.[N_LMS_Api_Logs];
GO

IF COL_LENGTH('dbo.[N_LMS_Api_Logs]', N'Error_Message') IS NOT NULL
    ALTER TABLE dbo.[N_LMS_Api_Logs] DROP COLUMN [Error_Message];
GO
IF COL_LENGTH('dbo.[N_LMS_Api_Logs]', N'Response_Ringkas') IS NOT NULL
    ALTER TABLE dbo.[N_LMS_Api_Logs] DROP COLUMN [Response_Ringkas];
GO
IF COL_LENGTH('dbo.[N_LMS_Api_Logs]', N'Request_Ringkas') IS NOT NULL
    ALTER TABLE dbo.[N_LMS_Api_Logs] DROP COLUMN [Request_Ringkas];
GO
IF COL_LENGTH('dbo.[N_LMS_Api_Logs]', N'Payload_Hash') IS NOT NULL
    ALTER TABLE dbo.[N_LMS_Api_Logs] DROP COLUMN [Payload_Hash];
GO
IF COL_LENGTH('dbo.[N_LMS_Api_Logs]', N'Sumber_Error') IS NOT NULL
    ALTER TABLE dbo.[N_LMS_Api_Logs] DROP COLUMN [Sumber_Error];
GO
IF COL_LENGTH('dbo.[N_LMS_Api_Logs]', N'Durasi_Ms') IS NOT NULL
    ALTER TABLE dbo.[N_LMS_Api_Logs] DROP COLUMN [Durasi_Ms];
GO
IF COL_LENGTH('dbo.[N_LMS_Api_Logs]', N'Modul') IS NOT NULL
    ALTER TABLE dbo.[N_LMS_Api_Logs] DROP COLUMN [Modul];
GO
IF COL_LENGTH('dbo.[N_LMS_Api_Logs]', N'Correlation_Id') IS NOT NULL
    ALTER TABLE dbo.[N_LMS_Api_Logs] DROP COLUMN [Correlation_Id];
GO
IF COL_LENGTH('dbo.[N_LMS_Api_Logs]', N'Http_Method') IS NOT NULL
    ALTER TABLE dbo.[N_LMS_Api_Logs] DROP COLUMN [Http_Method];
GO

/* selesai */
