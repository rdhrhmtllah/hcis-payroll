/* Change ID: DDL-2026-036-sop-versi-tanggal-obsolete */

/* Tanggal sebuah versi digantikan.

   Melengkapi DDL-2026-034. Berbeda dari tiga tanggal proses di sana, tahap
   Obsolete DICAPAI LEWAT DUA JALUR — dan yang dominan bukan pemindahan tahap:

     1. pemindahan tahap Filing and Registered -> Obsolete (jarang, manual)
     2. OTOMATIS saat versi lain terbit atau dikembalikan, di
        SopItemVersionService yang meng-obsolete-kan versi berlaku sebelumnya

   Mencapnya hanya di jalur pertama akan melewatkan hampir seluruh kejadian
   nyata. Karena itu pencapan dipasang di ketiganya. */

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Item_Versi' AND COLUMN_NAME = 'Tanggal_Obsolete')
    ALTER TABLE N_SOP_Item_Versi ADD Tanggal_Obsolete date NULL;
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-036-sop-versi-tanggal-obsolete')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DDL-2026-036-sop-versi-tanggal-obsolete', 'DDL', 'Tanggal obsolete pada versi', 'Kolom tanggal digantikannya sebuah versi; dicap di jalur pemindahan tahap DAN jalur otomatis saat versi lain terbit.', 'database/ddl/DDL-2026-036-sop-versi-tanggal-obsolete/forward.sql', 'docs/changes/DDL-2026-036-sop-versi-tanggal-obsolete/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'tanggal-obsolete');
GO
