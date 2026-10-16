/* Change ID: DDL-2026-130-pa-rilis-hasil-ke-leader
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-078 (N_PA_Result).
   Scope: memisahkan "hasil boleh dilihat LEADER" dari "hasil boleh dilihat KARYAWAN".

   Kenapa kolom, bukan status baru: `Status_Hasil = 'TERBIT'` dibaca 35 tempat di 19 berkas
   (kurva, pa:verify, distribusi, kalibrasi, penerbitan). Menyisipkan status di antaranya
   menuntut seluruh pembaca itu ditinjau ulang, sementara yang dibutuhkan hanya satu
   pertanyaan tambahan: "sudah dirilis ke leader atau belum". Siklus lama tidak tersentuh —
   hasil yang sudah TERBIT tetap terlihat leader lewat aturan
   "penanda leader terisi ATAU Status_Hasil = 'TERBIT'".
*/

/* 1. Penanda rilis ke leader. NULL = belum pernah dirilis ke leader. */
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Result') AND name = 'Published_Leader_At')
    ALTER TABLE N_PA_Result ADD Published_Leader_At datetime2(0) NULL;
GO
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Result') AND name = 'Published_Leader_By')
    ALTER TABLE N_PA_Result ADD Published_Leader_By int NULL;
GO

/* 2. Dicabutnya rilis leader ikut bersebab dan berpelaku.
      Tanpa kolom ini, "kenapa leader tidak lagi melihat hasil ini" hanya bisa dijawab
      dengan menebak, dan pencabutan yang keliru tidak bisa dibedakan dari yang disengaja. */
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Result') AND name = 'Unpublished_Leader_At')
    ALTER TABLE N_PA_Result ADD Unpublished_Leader_At datetime2(0) NULL;
GO
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Result') AND name = 'Unpublished_Leader_By')
    ALTER TABLE N_PA_Result ADD Unpublished_Leader_By int NULL;
GO
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Result') AND name = 'Alasan_Cabut_Leader')
    ALTER TABLE N_PA_Result ADD Alasan_Cabut_Leader nvarchar(500) NULL;
GO

/* 3. Penyaring layar leader: hasil aktif yang sudah dirilis. Disaring sebagian supaya
      indeksnya sekecil populasi yang benar-benar dibaca halaman PA Tim. */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Result') AND name = 'IX_PA_Result_Published_Leader')
    CREATE INDEX IX_PA_Result_Published_Leader ON N_PA_Result (PA_Participant_Id)
        INCLUDE (Published_Leader_At, Status_Hasil)
        WHERE Flag_Aktif = 'Y' AND Published_Leader_At IS NOT NULL;
GO

/* 4. Pendaftaran perubahan. */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-130')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification,
        Catatan, Created_By
    )
    VALUES (
        'DDL-2026-130', 'DDL', 'Rilis hasil PA ke leader terpisah dari penerbitan ke karyawan',
        'N_PA_Result menyimpan penanda rilis ke leader (Published_Leader_At/By) beserta pencabutannya yang bersebab (Unpublished_Leader_At/By, Alasan_Cabut_Leader). Layar PA Tim menampilkan angka bila penanda leader terisi ATAU hasil sudah TERBIT; penerbitan ke karyawan tetap ditandai Status_Hasil = TERBIT.',
        'database/ddl/DDL-2026-130-pa-rilis-hasil-ke-leader/forward.sql',
        'docs/changes/DDL-2026-130-pa-rilis-hasil-ke-leader/README.md',
        'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING',
        'Aditif; tidak mengubah arti Status_Hasil. Hasil lama yang sudah TERBIT tetap terlihat leader tanpa DML.',
        NULL
    );
END;
GO
