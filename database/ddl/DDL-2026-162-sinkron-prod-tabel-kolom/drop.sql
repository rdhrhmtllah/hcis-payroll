/* Change ID: DDL-2026-162-sinkron-prod-tabel-kolom — DROP/RESET MANUAL. JANGAN dijalankan oleh agen.
   Jalankan DDL-2026-163 drop.sql lebih dulu. Menghapus tabel beserta datanya. */

IF OBJECT_ID(N'dbo.N_SOP_Item_Versi_Lampiran', N'U') IS NOT NULL
    DROP TABLE dbo.[N_SOP_Item_Versi_Lampiran];
