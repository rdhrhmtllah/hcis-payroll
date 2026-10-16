/* Rollback DDL-2026-084. Membuang tabel N_PA_Cycle_Pre_Exclusion beserta indeksnya.

   AMAN DIBUANG karena tabel ini lahir bersama change ini dan tidak dirujuk tabel lain —
   ia menunjuk N_PA_Cycle, bukan ditunjuk. Membuangnya mengembalikan perilaku penyiapan
   ke keadaan sebelum 3 September 2026: pengecualian hanya bisa dilakukan sesudah peserta
   terbentuk.

   Daftar pengecualian yang sudah ditulis IKUT HILANG. Kalau siklus berjalan sudah
   memakainya, salin dulu isinya sebelum menjalankan berkas ini.

   Jalankan HANYA di development. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50422, 'drop DDL-2026-084 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

IF OBJECT_ID('N_PA_Cycle_Pre_Exclusion', 'U') IS NOT NULL
    DROP TABLE N_PA_Cycle_Pre_Exclusion;
GO
