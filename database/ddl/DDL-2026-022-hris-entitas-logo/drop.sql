-- database/ddl/DDL-2026-022-hris-entitas-logo/drop.sql
-- BANTUAN RESET MANUAL SAJA. Agent tidak boleh menjalankan berkas ini.
-- Menjalankannya membuang rujukan logo seluruh entitas; berkasnya sendiri
-- tetap ada di GCS tapi tak ada lagi yang menunjuknya.

IF EXISTS (SELECT 1 FROM sys.columns
           WHERE object_id = OBJECT_ID('N_HRIS_Master_Entitas') AND name = 'Logo_Path')
ALTER TABLE N_HRIS_Master_Entitas DROP COLUMN Logo_Path;
GO
