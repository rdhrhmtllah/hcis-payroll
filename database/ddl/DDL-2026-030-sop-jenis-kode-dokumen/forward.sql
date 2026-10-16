/* Change ID: DDL-2026-030-sop-jenis-kode-dokumen */

/* Kode jenis dokumen — segmen PERTAMA nomor dokumen gabungan
   Jenis/Entitas/Departemen/RuangLingkup/NoAPQC/NoDokumen/Revisi.

   KENAPA KOLOM SENDIRI, BUKAN MEMAKAI ULANG Kode_Jenis. Keduanya menjawab
   pertanyaan yang berbeda dan berumur berbeda:

     Kode_Jenis          kunci mesin. Dipakai seed, test, dan integrasi untuk
                         menemukan baris yang dimaksud. Harus stabil; berubah
                         berarti memutus rujukan yang tak terlihat dari layar
                         mana pun.
     Kode_Jenis_Dokumen  identitas yang TERCETAK di dokumen. Milik bisnis,
                         bukan milik kode. Harus bebas diubah admin saat
                         kepanjangan resminya akhirnya ditetapkan.

   Menyatukan keduanya memaksa memilih salah satu sifat dan mengorbankan yang
   lain: entah admin tak bisa menyunting kodenya, atau setiap suntingan admin
   diam-diam memutus seed dan test. */

IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'N_SOP_Master_Jenis_Dokumen' AND COLUMN_NAME = 'Kode_Jenis_Dokumen'
)
    ALTER TABLE N_SOP_Master_Jenis_Dokumen ADD Kode_Jenis_Dokumen varchar(10) NULL;
GO

/* Unik, TAPI hanya untuk yang terisi.

   Unique index biasa di SQL Server menganggap seluruh NULL sama, sehingga baris
   kedua yang kodenya belum diisi akan ditolak — padahal kolomnya memang lahir
   kosong. Filtered index menjaga yang penting (dua jenis tak boleh memakai kode
   yang sama, karena nomor dokumennya jadi ambigu) tanpa melarang yang belum
   diputuskan. */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_SOP_Master_Jenis_Dokumen') AND name = 'UX_SOP_Jenis_Kode_Jenis_Dokumen'
)
    CREATE UNIQUE INDEX UX_SOP_Jenis_Kode_Jenis_Dokumen
        ON N_SOP_Master_Jenis_Dokumen (Kode_Jenis_Dokumen)
        WHERE Kode_Jenis_Dokumen IS NOT NULL;
GO

/* Pendaftaran ke Database Change Dashboard. */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-030-sop-jenis-kode-dokumen')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DDL-2026-030-sop-jenis-kode-dokumen', 'DDL', 'Kode jenis dokumen untuk nomor dokumen gabungan', 'Kolom identitas cetak yang terpisah dari Kode_Jenis (kunci mesin), plus filtered unique index.', 'database/ddl/DDL-2026-030-sop-jenis-kode-dokumen/forward.sql', 'docs/changes/DDL-2026-030-sop-jenis-kode-dokumen/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'kode-jenis-dokumen');
GO
