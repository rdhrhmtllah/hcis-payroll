/* DDL-2026-064 — indeks penopang jendela 90 hari panel Pemahaman
   Target: Web_HRIS
   Melengkapi perubahan kode yang membatasi kualitasBaca() dan bacaVsEstimasi()
   pada Waktu_Buka >= 90 hari terakhir. Tanpa indeks ini predikatnya tetap
   dievaluasi lewat pindaian penuh; dengan indeks ini ia menjadi range seek. */

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Prg_Waktu_Buka')
CREATE NONCLUSTERED INDEX IX_N_SOP_Prg_Waktu_Buka
    ON N_SOP_Progress (Waktu_Buka)
    INCLUDE (Id_SOP_Paket_Item, Kode_Karyawan, Durasi_Aktif_Detik)
    WHERE Waktu_Buka IS NOT NULL;
GO
