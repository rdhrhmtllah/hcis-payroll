/* Rollback DDL-2026-087-performance-appraisal-question-binding-master
   Target: Web_HRIS development; production only through SQL Tracker.

   KEHILANGAN YANG DITERIMA

   Membuang tabel ini menghapus pilihan paket baku beserta sidik susunan yang menjadi acuan
   verifikasi. Siklus yang SUDAH menyalinnya tidak terpengaruh — salinannya ada di
   N_PA_Cycle_Question_Package dan berdiri sendiri. Yang hilang adalah kemampuan siklus
   BERIKUTNYA menyalin, dan kemampuan mendeteksi bahwa HC Learn mengubah paketnya.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50472, 'Rollback DDL-2026-087 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

IF OBJECT_ID('N_PA_Question_Binding_Master', 'U') IS NOT NULL
    DROP TABLE N_PA_Question_Binding_Master;
GO
