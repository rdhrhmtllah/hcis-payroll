/* Change ID: DDL-2026-088-catatan-hasil-berpangkal-kompetensi
   BERKAS INI ALAT BANTU MANUAL. Agen tidak menjalankannya.

   Mengembalikan `N_PA_Result_Note` ke keadaan sebelum perubahan: kolom `PA_Competency_Id`
   beserta penjaganya dicabut, dan `PA_Result_Note_Type_Id` dikembalikan NOT NULL.

   PERIKSA DULU SEBELUM MENJALANKAN. Mengembalikan NOT NULL akan GAGAL bila sudah ada baris
   yang lahir sesudah perubahan — baris itu tidak mengisi kolom lama sama sekali. Periksa:

       SELECT COUNT(*) FROM N_PA_Result_Note WHERE PA_Result_Note_Type_Id IS NULL;

   Bila hasilnya bukan nol, baris-baris itu harus diisi lebih dulu (atau dinonaktifkan),
   karena membuangnya berarti menghapus catatan yang sudah dibaca orang.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50534, 'Pembatalan DDL-2026-088 hanya untuk Web_HRIS.', 1;
GO

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_PA_Result_Note_Competency')
    DROP INDEX IX_PA_Result_Note_Competency ON N_PA_Result_Note;
GO

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Result_Note_Competency')
    ALTER TABLE N_PA_Result_Note DROP CONSTRAINT FK_PA_Result_Note_Competency;
GO

IF EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Result_Note') AND name = 'PA_Competency_Id'
)
    ALTER TABLE N_PA_Result_Note DROP COLUMN PA_Competency_Id;
GO

IF EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Result_Note')
      AND name = 'PA_Result_Note_Type_Id'
      AND is_nullable = 1
)
    ALTER TABLE N_PA_Result_Note ALTER COLUMN PA_Result_Note_Type_Id INT NOT NULL;
GO
