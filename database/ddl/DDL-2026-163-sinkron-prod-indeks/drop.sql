/* Change ID: DDL-2026-163-sinkron-prod-indeks — DROP/RESET MANUAL. JANGAN dijalankan oleh agen. */

IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_SOP_Item_Versi_Lampiran') AND name = N'IX_SOP_Versi_Lampiran_Versi') DROP INDEX [IX_SOP_Versi_Lampiran_Versi] ON dbo.[N_SOP_Item_Versi_Lampiran];
