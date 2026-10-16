-- Change ID: DDL-2026-158-sop-versi-lampiran-form
-- PEMBERSIHAN MANUAL — tidak dijalankan agent. Hanya tabel lampiran; berkas di Library Konten tidak tersentuh.
IF OBJECT_ID('dbo.N_SOP_Item_Versi_Lampiran', 'U') IS NOT NULL
    DROP TABLE dbo.N_SOP_Item_Versi_Lampiran;
GO
