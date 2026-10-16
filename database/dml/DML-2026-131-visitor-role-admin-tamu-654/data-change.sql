/* Change ID: DML-2026-131-visitor-role-admin-tamu-654 */
/*
    Beri dasar pada akses yang sudah dipegang.

    UserId_Web 654 memegang kunci halaman visitorMasterDataTamuPage (dihibahkan
    2026-09-21 16:38) tanpa role ADMIN_TAMU. Tiga pemegang kunci itu lainnya
    (4, 624, 1885) semuanya punya rolenya. Uji PemisahanMasterDataTest menagih
    tepat hal ini: kunci domain harus punya dasar berupa role domainnya,
    sebab kunci per orang bisa dihibahkan manual sedangkan role tidak.

    Keputusan user 2026-09-22: aksesnya memang dikehendaki, jadi yang kurang
    adalah rolenya — bukan kuncinya yang perlu dicabut.

    Role dicari lewat Role_Code, BUKAN Id_Role yang ditempel. Id identity
    berbeda antar lingkungan, dan menempelkannya membuat artefak ini menulis
    role yang salah di production tanpa satu pun galat.

    Idempoten dan tahan baris kembar: N_HRIS_Role_User tidak punya kunci unik
    pada (UserId_Web, Id_Role), sehingga dijalankan dua kali tanpa penjaga
    NOT EXISTS akan meninggalkan dua baris yang sama.
*/
SET XACT_ABORT ON;
BEGIN TRANSACTION;

DECLARE @IdRole int = (
    SELECT Id_Role FROM N_HRIS_Role_Functional
     WHERE Role_Code = 'ADMIN_TAMU' AND Flag_Aktif = 'Y'
);

IF @IdRole IS NULL
    THROW 50000, 'Role ADMIN_TAMU tidak ditemukan atau tidak aktif.', 1;

/* ── 1. Baris role, hanya bila belum ada sama sekali ─────────────────── */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Role_User WHERE UserId_Web = 654 AND Id_Role = @IdRole)
BEGIN
    INSERT INTO N_HRIS_Role_User (UserId_Web, Id_Role, Assigned_At, Assigned_By, Flag_Aktif)
    VALUES (654, @IdRole, GETDATE(), 'DML-2026-131', 'Y');
END
ELSE
BEGIN
    /* Baris nonaktif dari masa lalu dinyalakan, bukan digandakan. */
    UPDATE N_HRIS_Role_User
       SET Flag_Aktif = 'Y'
     WHERE UserId_Web = 654 AND Id_Role = @IdRole AND (Flag_Aktif IS NULL OR Flag_Aktif <> 'Y');
END;

/* ── 2. Database Change Dashboard ────────────────────────────────────── */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-131-visitor-role-admin-tamu-654')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DML-2026-131-visitor-role-admin-tamu-654', 'DML', 'Role ADMIN_TAMU untuk pemegang kunci master data tamu', 'UserId_Web 654 memegang visitorMasterDataTamuPage tanpa role ADMIN_TAMU; rolenya ditambahkan agar aksesnya punya dasar dan penjaga pemisahan kunci kembali hijau.', 'database/dml/DML-2026-131-visitor-role-admin-tamu-654/data-change.sql', 'docs/changes/DML-2026-131-visitor-role-admin-tamu-654/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'visitor-role-admin-tamu');
END;

COMMIT TRANSACTION;

/* ── Verifikasi: harus mengembalikan 0 baris ─────────────────────────── */
/*
SELECT pa.UserID_Web
  FROM HRIS_Page_Access pa
 WHERE pa.Jenis_page = 'visitorMasterDataTamuPage'
   AND pa.Flag_Aktif = 'Y'
   AND NOT EXISTS (
        SELECT 1
          FROM N_HRIS_Role_User ru
          JOIN N_HRIS_Role_Functional rf ON rf.Id_Role = ru.Id_Role
         WHERE ru.UserId_Web = pa.UserID_Web
           AND ru.Flag_Aktif = 'Y' AND rf.Flag_Aktif = 'Y'
           AND rf.Role_Code = 'ADMIN_TAMU');
*/
