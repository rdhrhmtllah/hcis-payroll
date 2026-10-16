/* Change ID: DML-2026-136-visitor-matriks-role-admin-tamu */
/*
    Matriks tamu untuk role ADMIN, supaya matriks menjadi SATU-SATUNYA sumber
    kebenaran siapa boleh melihat kategori tamu apa.

    Sampai sekarang admin monitoring melihat semua tamu karena memegang KUNCI
    HALAMAN per orang, sementara departemen dibatasi lewat matriks. Dua
    mekanisme untuk satu pertanyaan yang sama, dan yang satu tidak tahu apa
    yang diputuskan yang lain: kunci dihibahkan tangan lalu terlupakan,
    sedangkan matriks ikut role sehingga merawat dirinya sendiri.

    Keempat pemegang kunci monitoring hari ini SEMUANYA pemegang ADMIN_TAMU,
    jadi baris di bawah membuat mereka tetap melihat persis seperti sekarang -
    tanpa satu pun yang kehilangan akses saat kuncinya berhenti dipakai
    sebagai jalan pintas.

    Flag_Ajukan sengaja 'T': yang diperluas di sini hanya HAK MELIHAT. Hak
    mengajukan tamu atas nama kategori tertentu adalah keputusan lain, dan
    menyelipkannya ke sini akan mengubah layar pendaftaran tanpa ada yang
    memintanya.

    Idempoten: dijalankan ulang menyentuh 0 baris.
*/
SET XACT_ABORT ON;
BEGIN TRANSACTION;

DECLARE @Rencana TABLE (Role_Code varchar(64), Kategori varchar(32));

/* Admin verifikator tamu: seluruh kategori. */
INSERT INTO @Rencana (Role_Code, Kategori) VALUES
    ('ADMIN_TAMU',     'REGULAR'),
    ('ADMIN_TAMU',     'B2B_PROCUREMENT'),
    ('ADMIN_TAMU',     'B2B_LOGISTIC'),
    /* Admin B2B: hanya dua kategori B2B - tamu reguler bukan wilayahnya. */
    ('ADMIN_TAMU_B2B', 'B2B_PROCUREMENT'),
    ('ADMIN_TAMU_B2B', 'B2B_LOGISTIC');

/* Role dicari lewat Role_Code, BUKAN Id yang ditempel: Id identity berbeda
   antar lingkungan, dan menempelkannya memberi hak kepada role yang salah di
   production tanpa satu pun galat. */
IF EXISTS (
    SELECT 1 FROM @Rencana r
     WHERE NOT EXISTS (
        SELECT 1 FROM N_HRIS_Role_Functional rf
         WHERE rf.Role_Code = r.Role_Code AND rf.Flag_Aktif = 'Y')
)
    THROW 50000, 'Ada Role_Code yang tidak ditemukan / tidak aktif; matriks tidak ditulis.', 1;

INSERT INTO N_VISITOR_Matriks_Tamu
    (Role_Id, Kategori_Tamu, Flag_Lihat, Flag_Ajukan, Flag_Aktif, Created_By)
SELECT rf.Id_Role, r.Kategori, 'Y', 'T', 'Y', 'DML-2026-136'
  FROM @Rencana r
  JOIN N_HRIS_Role_Functional rf ON rf.Role_Code = r.Role_Code AND rf.Flag_Aktif = 'Y'
 WHERE NOT EXISTS (
        SELECT 1 FROM N_VISITOR_Matriks_Tamu m
         WHERE m.Role_Id = rf.Id_Role AND m.Kategori_Tamu = r.Kategori);

/* Baris nonaktif dari masa lalu dinyalakan, bukan digandakan. */
UPDATE m
   SET m.Flag_Lihat = 'Y', m.Flag_Aktif = 'Y', m.Updated_At = GETDATE(), m.Updated_By = 'DML-2026-136'
  FROM N_VISITOR_Matriks_Tamu m
  JOIN N_HRIS_Role_Functional rf ON rf.Id_Role = m.Role_Id
  JOIN @Rencana r ON r.Role_Code = rf.Role_Code AND r.Kategori = m.Kategori_Tamu
 WHERE m.Flag_Aktif <> 'Y' OR m.Flag_Lihat <> 'Y';

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-136-visitor-matriks-role-admin-tamu')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DML-2026-136-visitor-matriks-role-admin-tamu', 'DML', 'Matriks tamu untuk role admin', 'ADMIN_TAMU dan ADMIN_TAMU_B2B diberi hak LIHAT pada matriks, supaya matriks menjadi satu-satunya sumber kebenaran dan kunci halaman berhenti dipakai sebagai jalan pintas.', 'database/dml/DML-2026-136-visitor-matriks-role-admin-tamu/data-change.sql', 'docs/changes/DML-2026-136-visitor-matriks-role-admin-tamu/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'visitor-matriks-admin');
END;

COMMIT TRANSACTION;

/* Verifikasi: harus 5 baris. */
/*
SELECT rf.Role_Code, m.Kategori_Tamu, m.Flag_Lihat, m.Flag_Ajukan
  FROM N_VISITOR_Matriks_Tamu m
  JOIN N_HRIS_Role_Functional rf ON rf.Id_Role = m.Role_Id
 WHERE rf.Role_Code IN ('ADMIN_TAMU','ADMIN_TAMU_B2B') AND m.Flag_Aktif = 'Y'
 ORDER BY rf.Role_Code, m.Kategori_Tamu;
*/
