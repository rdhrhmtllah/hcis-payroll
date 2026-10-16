-- database/ddl/DDL-2026-095-anti-replay-lms-setara-hris/drop.sql
--
-- Pembatalan DDL-2026-095. Idempoten.
--
-- PERINGATAN
--   Menjalankan berkas ini MENGHILANGKAN jaminan anti-replay yang dijaga
--   database pada registry LMS. Setelah itu dua permintaan bernonce sama yang
--   datang bersamaan kembali bisa lolos berdua. Hanya jalankan bila indeksnya
--   terbukti menimbulkan masalah, dan tutup celahnya dengan cara lain.
--
--   Berkas ini TIDAK dijalankan oleh agent. Sesuai AGENTS.md, DROP dan TRUNCATE
--   dalam bentuk apa pun — termasuk DROP INDEX — hanya dijalankan manusia.

IF EXISTS (SELECT 1 FROM sys.check_constraints
            WHERE name = N'CK_N_LMS_Api_Logs_Sig'
              AND parent_object_id = OBJECT_ID('dbo.N_LMS_Api_Logs'))
    ALTER TABLE dbo.N_LMS_Api_Logs DROP CONSTRAINT [CK_N_LMS_Api_Logs_Sig];
GO

IF EXISTS (SELECT 1 FROM sys.indexes
            WHERE name = N'UQ_N_LMS_Api_Nonces_Nonce'
              AND object_id = OBJECT_ID('dbo.N_LMS_Api_Nonces'))
    DROP INDEX [UQ_N_LMS_Api_Nonces_Nonce] ON dbo.N_LMS_Api_Nonces;
GO

IF EXISTS (SELECT 1 FROM sys.indexes
            WHERE name = N'IX_N_LMS_Api_Nonces_Nonce'
              AND object_id = OBJECT_ID('dbo.N_LMS_Api_Nonces'))
    DROP INDEX [IX_N_LMS_Api_Nonces_Nonce] ON dbo.N_LMS_Api_Nonces;
GO

IF EXISTS (SELECT 1 FROM sys.indexes
            WHERE name = N'IX_N_LMS_Api_Nonces_Expired'
              AND object_id = OBJECT_ID('dbo.N_LMS_Api_Nonces'))
    DROP INDEX [IX_N_LMS_Api_Nonces_Expired] ON dbo.N_LMS_Api_Nonces;
GO
