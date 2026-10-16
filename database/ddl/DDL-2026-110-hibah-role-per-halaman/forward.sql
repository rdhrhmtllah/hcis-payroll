/* Change ID: DDL-2026-110-hibah-role-per-halaman */

/*
    Memisahkan DUA tugas yang selama ini ditumpuk pada satu tabel.

    N_HRIS_Grup_Fitur_Map mengerjakan dua hal sekaligus:

      1. STRUKTUR — menu ini milik modul yang mana. InertiaShellService
         mem-JOIN tabel ini untuk menyusun sidebar; menu tanpa barisnya tidak
         bisa muncul sama sekali, bahkan bagi orang yang punya kunci halamannya.

      2. HIBAH — pemegang role pada grup itu otomatis mendapat SELURUH halaman
         grup tersebut (User::memilikiAksesBaru dan InertiaShellService).

    Selama keduanya satu baris, pemisahan hak di dalam satu modul mustahil
    diungkapkan. Visitor membuktikannya: ADMIN_PERMIT dan ADMIN_TAMU sama-sama
    menunjuk grup VISITOR, sehingga keduanya melihat seluruh 16 halaman —
    pemisahan yang sudah dibuat di halaman, role, dan kunci per orang tetap
    tidak mengikat. Menghapus barisnya bukan jalan keluar: itu melenyapkan
    menunya dari sidebar untuk semua orang.

    Kolom ini memisahkan keduanya. Struktur tetap memakai SELURUH baris; hibah
    hanya membaca baris ber-'Y'.

    BAWAANNYA 'Y' DAN ITU DISENGAJA: seluruh baris yang sudah ada — ESS, KPI,
    LMS, DMS, SYSTEM, BUILDER — berperilaku persis seperti sebelumnya. Perubahan
    ini tidak boleh terasa di modul mana pun yang tidak diminta berubah.
*/

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
     WHERE object_id = OBJECT_ID('N_HRIS_Grup_Fitur_Map')
       AND name = 'Flag_Hibah_Role'
)
BEGIN
    ALTER TABLE N_HRIS_Grup_Fitur_Map
        ADD Flag_Hibah_Role char(1) NOT NULL
            CONSTRAINT DF_N_HRIS_Grup_Fitur_Map_Flag_Hibah_Role DEFAULT ('Y');
END;
GO

/* Hanya 'Y' atau 'T'. Tanpa batasan ini, nilai ketiga yang masuk lewat jalur
   lain akan diperlakukan sebagai "bukan Y" oleh kueri hibah — yaitu mencabut
   akses diam-diam, kegagalan yang tidak menyalakan galat apa pun. */
IF NOT EXISTS (
    SELECT 1 FROM sys.check_constraints
     WHERE parent_object_id = OBJECT_ID('N_HRIS_Grup_Fitur_Map')
       AND name = 'CK_N_HRIS_Grup_Fitur_Map_Flag_Hibah_Role'
)
BEGIN
    ALTER TABLE N_HRIS_Grup_Fitur_Map
        ADD CONSTRAINT CK_N_HRIS_Grup_Fitur_Map_Flag_Hibah_Role
            CHECK (Flag_Hibah_Role IN ('Y', 'T'));
END;
GO

/* Verifikasi */
SELECT
    COUNT(*) AS total_baris,
    SUM(CASE WHEN Flag_Hibah_Role = 'Y' THEN 1 ELSE 0 END) AS memberi_hibah,
    SUM(CASE WHEN Flag_Hibah_Role = 'T' THEN 1 ELSE 0 END) AS hanya_struktur
FROM N_HRIS_Grup_Fitur_Map;
GO
