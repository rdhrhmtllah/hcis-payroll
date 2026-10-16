/* DDL-2026-065 — indeks mencakup untuk panel Titik Henti (penyerapan versi)
   Target: Web_HRIS

   penyerapanVersi() menyaring N_SOP_Progress berdasarkan Status, menjoinnya
   lewat Id_SOP_Paket_Item, lalu mengelompokkan per (item, karyawan) dengan
   Id_SOP_Item_Versi sebagai pembeda. IX_N_SOP_Prg_Item yang ada hanya berkunci
   Id_SOP_Paket_Item tanpa mencakup kolom keluarannya, sehingga pengoptimal
   memilih pindaian penuh ketimbang seek + lookup. */

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Prg_Item_Status')
CREATE NONCLUSTERED INDEX IX_N_SOP_Prg_Item_Status
    ON N_SOP_Progress (Id_SOP_Paket_Item, Status)
    INCLUDE (Id_SOP_Item_Versi, Kode_Karyawan);
GO
