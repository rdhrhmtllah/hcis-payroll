/* Rollback DDL-2026-085-performance-appraisal-cycle-setup-step
   Target: Web_HRIS development; production only through SQL Tracker.

   KEHILANGAN YANG DITERIMA

   Membuang tabel ini menghapus catatan sudah sampai mana tiap siklus disiapkan, dan
   ringkasan tiap langkah. Setelan siklusnya SENDIRI tidak ikut hilang — bobot pilar,
   paket soal, dan aturan penilai tersimpan di tabel snapshot masing-masing. Yang hilang
   hanya jejak proses penyiapannya.

   Siklus yang sedang setengah disiapkan akan tampak seperti belum pernah disentuh.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50432, 'Rollback DDL-2026-085 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

IF OBJECT_ID('N_PA_Cycle_Setup_Step', 'U') IS NOT NULL
    DROP TABLE N_PA_Cycle_Setup_Step;
GO

IF EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Cycle') AND name = 'Langkah_Setup_Aktif'
)
BEGIN
    ALTER TABLE N_PA_Cycle DROP COLUMN Langkah_Setup_Aktif;
END;
GO
