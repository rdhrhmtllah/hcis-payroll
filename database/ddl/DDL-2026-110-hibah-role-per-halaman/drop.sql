/* Change ID: DDL-2026-110-hibah-role-per-halaman — DROP */

/*
    JANGAN dijalankan tanpa mengembalikan kueri hibahnya lebih dulu.

    Setelah kolom ini hilang, User::memilikiAksesBaru() dan InertiaShellService
    akan menyaring kolom yang tidak ada dan setiap permintaan gagal dengan galat
    basis data. Urutan pencabutan yang benar:

      1. kembalikan kedua kueri hibah ke bentuk tanpa penyaring;
      2. baru jalankan berkas ini.

    Perlu dicatat: mencabut kolomnya mengembalikan hibah menyeluruh per grup,
    sehingga ADMIN_PERMIT dan ADMIN_TAMU kembali melihat seluruh halaman
    Visitor. Itu memang keadaan sebelum DDL ini — bukan kerusakan, tetapi juga
    bukan sesuatu yang terjadi tanpa konsekuensi.
*/

IF EXISTS (
    SELECT 1 FROM sys.check_constraints
     WHERE parent_object_id = OBJECT_ID('N_HRIS_Grup_Fitur_Map')
       AND name = 'CK_N_HRIS_Grup_Fitur_Map_Flag_Hibah_Role'
)
BEGIN
    ALTER TABLE N_HRIS_Grup_Fitur_Map
        DROP CONSTRAINT CK_N_HRIS_Grup_Fitur_Map_Flag_Hibah_Role;
END;
GO

IF EXISTS (
    SELECT 1 FROM sys.default_constraints
     WHERE parent_object_id = OBJECT_ID('N_HRIS_Grup_Fitur_Map')
       AND name = 'DF_N_HRIS_Grup_Fitur_Map_Flag_Hibah_Role'
)
BEGIN
    ALTER TABLE N_HRIS_Grup_Fitur_Map
        DROP CONSTRAINT DF_N_HRIS_Grup_Fitur_Map_Flag_Hibah_Role;
END;
GO

IF EXISTS (
    SELECT 1 FROM sys.columns
     WHERE object_id = OBJECT_ID('N_HRIS_Grup_Fitur_Map')
       AND name = 'Flag_Hibah_Role'
)
BEGIN
    ALTER TABLE N_HRIS_Grup_Fitur_Map DROP COLUMN Flag_Hibah_Role;
END;
GO
