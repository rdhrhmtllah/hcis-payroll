/* Change ID: DDL-2026-036-sop-versi-tanggal-obsolete — ROLLBACK
   TIDAK PERNAH dijalankan agen. */
IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Item_Versi' AND COLUMN_NAME = 'Tanggal_Obsolete')
    ALTER TABLE N_SOP_Item_Versi DROP COLUMN Tanggal_Obsolete;
GO
