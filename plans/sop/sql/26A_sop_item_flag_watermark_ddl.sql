/* ============================================================================
   SOP 26A - DDL Flag_Watermark per item dokumen

   Toggle watermark keamanan per item DOKUMEN, di-set admin di SOP Builder.
   Default 'Y' (aktif) supaya perilaku watermark-selalu-aktif yang berlaku
   sekarang tidak berubah untuk item lama maupun baru sampai admin sengaja
   mematikannya.
   ============================================================================ */

IF COL_LENGTH('dbo.N_SOP_Paket_Item', 'Flag_Watermark') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Paket_Item
        ADD Flag_Watermark CHAR(1) NOT NULL DEFAULT ('Y');
END;
GO

IF COL_LENGTH('dbo.N_SOP_Paket_Draft_Item', 'Flag_Watermark') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Paket_Draft_Item
        ADD Flag_Watermark CHAR(1) NOT NULL DEFAULT ('Y');
END;
GO
