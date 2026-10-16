/* =====================================================================
   38 — Cabut kolom Flag_Kritis dari master prioritas
   =====================================================================

   KEPUTUSAN 15 Agu 2026. Kolom ini diperkenalkan bersama master prioritas
   (skrip 35) sebagai penanda "tingkat ini ikut dihitung di angka SOP kritis
   belum selesai". Niatnya supaya tingkat buatan admin bisa ikut terhitung.

   Dicabut karena satu alasan sederhana: ia menambah satu konsep yang harus
   dipahami admin untuk sesuatu yang seharusnya cuma daftar tingkat. Layar
   masternya jadi punya kotak centang yang akibatnya tak terlihat dari layar itu
   sendiri — dan penanda yang akibatnya tak terlihat adalah penanda yang salah
   dicentang.

   YANG BERUBAH PERILAKUNYA
   ------------------------
   Metrik "SOP kritis belum selesai" (SopMonitoringPeopleService), chip
   penyaring, dan ikon api kembali mencocokkan kode 'KRITIS' APA ADANYA — persis
   seperti sebelum master prioritas ada. Konsekuensinya diterima secara sadar:
   tingkat baru bernama lain, misalnya SANGAT_TINGGI, TIDAK ikut terhitung di
   angka itu meski ia lebih mendesak daripada KRITIS.

   Kalau suatu saat itu terasa mengganggu, jawabannya BUKAN menghidupkan lagi
   kolom ini, melainkan memutuskan apakah metrik "kritis" itu sendiri masih
   berguna.

   KOLOMNYA DIJATUHKAN, bukan dibiarkan menganggur. Umurnya satu hari, tak
   pernah diisi manusia lewat layar mana pun, dan tak ada satu pun kode yang
   membacanya lagi. Kolom menganggur yang tak punya riwayat hanya jadi tebakan
   bagi orang berikutnya.

   AMAN DIJALANKAN BERULANG.
   ===================================================================== */

/* Default & CHECK-nya harus lepas lebih dulu — SQL Server menolak menjatuhkan
   kolom yang masih dirujuk constraint, dengan galat yang menyebut nama
   constraint-nya tanpa menyebut apa yang harus dilakukan. */
IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_N_SOP_Prioritas_Kritis')
BEGIN
    ALTER TABLE dbo.N_SOP_Prioritas DROP CONSTRAINT CK_N_SOP_Prioritas_Kritis;
    PRINT 'CK_N_SOP_Prioritas_Kritis dijatuhkan.';
END
GO

IF EXISTS (SELECT 1 FROM sys.default_constraints WHERE name = 'DF_N_SOP_Prioritas_Kritis')
BEGIN
    ALTER TABLE dbo.N_SOP_Prioritas DROP CONSTRAINT DF_N_SOP_Prioritas_Kritis;
    PRINT 'DF_N_SOP_Prioritas_Kritis dijatuhkan.';
END
GO

/* Indeksnya menyertakan kolom ini di INCLUDE — dibangun ulang tanpa kolom itu,
   kalau tidak DROP COLUMN-nya ditolak. */
IF EXISTS (
    SELECT 1 FROM sys.index_columns ic
      JOIN sys.indexes i ON i.object_id = ic.object_id AND i.index_id = ic.index_id
      JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
     WHERE i.name = 'IX_N_SOP_Prioritas_Urutan' AND c.name = 'Flag_Kritis'
)
BEGIN
    DROP INDEX IX_N_SOP_Prioritas_Urutan ON dbo.N_SOP_Prioritas;

    CREATE NONCLUSTERED INDEX IX_N_SOP_Prioritas_Urutan
        ON dbo.N_SOP_Prioritas (Flag_Aktif, Urutan) INCLUDE (Kode, Nama, Warna);

    PRINT 'IX_N_SOP_Prioritas_Urutan dibangun ulang tanpa Flag_Kritis.';
END
GO

IF EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('dbo.N_SOP_Prioritas') AND name = 'Flag_Kritis'
)
BEGIN
    ALTER TABLE dbo.N_SOP_Prioritas DROP COLUMN Flag_Kritis;
    PRINT 'Kolom Flag_Kritis dijatuhkan.';
END
ELSE
    PRINT 'Kolom Flag_Kritis sudah tidak ada — dilewati.';
GO

/* Skrip 35 sudah ikut disesuaikan: environment yang menjalankannya dari awal
   tak akan pernah membuat kolom ini, jadi skrip ini hanya perlu di database
   yang telanjur menjalankan versi sebelumnya. */
