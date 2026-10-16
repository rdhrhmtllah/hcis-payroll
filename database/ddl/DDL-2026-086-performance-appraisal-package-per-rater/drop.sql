/* Rollback DDL-2026-086-performance-appraisal-package-per-rater
   Target: Web_HRIS development; production only through SQL Tracker.

   KEHILANGAN YANG DITERIMA

   Membuang kolom ini menghapus pembedaan soal per kategori penilai. Paket yang terikat
   kategori tertentu akan kehilangan ikatannya, dan bila ada lebih dari satu paket aktif
   untuk satu (siklus, level) — yang sah setelah DDL-086 — indeks unik lama TIDAK BISA
   dibangun kembali sampai kelebihannya dinonaktifkan lebih dulu.

   Periksa dulu sebelum menjalankan:
     SELECT PA_Cycle_Id, ID_Level, COUNT(*) FROM N_PA_Cycle_Question_Package
     WHERE Flag_Aktif = 'Y' GROUP BY PA_Cycle_Id, ID_Level HAVING COUNT(*) > 1;
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50463, 'Rollback DDL-2026-086 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Question_Package') AND name = 'UX_PA_CycleQPkg_Cycle_Level_Rater')
    DROP INDEX UX_PA_CycleQPkg_Cycle_Level_Rater ON N_PA_Cycle_Question_Package;

IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Question_Package') AND name = 'IX_PA_CycleQPkg_Cycle_Aktif')
    DROP INDEX IX_PA_CycleQPkg_Cycle_Aktif ON N_PA_Cycle_Question_Package;
GO

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_CycleQPkg_RaterCategory')
    ALTER TABLE N_PA_Cycle_Question_Package DROP CONSTRAINT FK_PA_CycleQPkg_RaterCategory;
GO

IF EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Cycle_Question_Package') AND name = 'PA_Rater_Category_Id')
    ALTER TABLE N_PA_Cycle_Question_Package DROP COLUMN PA_Rater_Category_Id;
GO

/* Indeks unik lama dikembalikan. Akan GAGAL bila masih ada dua paket aktif pada satu
   (siklus, level) -- lihat kueri pemeriksaan di kepala berkas. */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Question_Package') AND name = 'UX_PA_Cycle_Question_Package_Aktif')
    CREATE UNIQUE INDEX UX_PA_Cycle_Question_Package_Aktif
        ON N_PA_Cycle_Question_Package (PA_Cycle_Id, ID_Level)
        WHERE Flag_Aktif = 'Y';
GO
