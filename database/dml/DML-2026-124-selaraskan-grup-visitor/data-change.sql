/* Change ID: DML-2026-124-selaraskan-grup-visitor */

/*
    Menyelaraskan data grup fitur Visitor production dengan kode aplikasi.

    Ditemukan 2026-09-18 saat pemegang ADMIN_PERMIT di production tetap melihat
    seluruh menu admin Tamu:

      1. Kode grup Visitor di production adalah 'VMS'; di development dan di
         seluruh kode aplikasi 'VISITOR'. Tidak satu baris kode pun memakai
         'VMS'. Akibatnya setiap pencarian grup lewat kodenya meleset tanpa
         galat — langkah 2 DML DDL-2026-110 mengubah NOL baris, halaman Menu &
         Matrix Visitor (MenuApprovalMatrixController, LOWER(Kode) = 'visitor')
         dan TAB_TAK_BERLAKU['VISITOR'] di ApprovalMatrixService tidak pernah
         cocok.

      2. Karena (1), hibah halaman admin Visitor tetap 'Y'. Pada 2026-09-18
         hibahnya dicabut MANUAL di production dengan
         UPDATE ... WHERE Id_Grup_Fitur = 4 (13 baris). Langkah 2 skrip ini
         mengulang pencabutan itu secara idempoten supaya tercatat; di
         production yang sudah dicabut manual hasilnya 0 baris.

    Grup dicari lewat ROLE ADMIN_PERMIT, bukan lewat kode maupun Id. Kode
    berbeda antar lingkungan (itulah masalahnya) dan Id tidak dijamin sama.

    Idempoten: di development (kode sudah 'VISITOR', hibah sudah 'T') kedua
    UPDATE mengubah 0 baris.

    Yang TIDAK disentuh:
      - visitorPermitPage dan visitorTamuPage tetap memberi hibah.
      - Is_Global_Menu kedua halaman itu. Di production keduanya 'T' (di
        development 'Y'); menjadikannya global adalah keputusan bisnis
        tersendiri, bukan penyelarasan.
      - Nama_Grup_Fitur.
*/

SET XACT_ABORT ON;
BEGIN TRANSACTION;

DECLARE @Id_Grup_Visitor int;
DECLARE @jumlah_grup int;

SELECT @jumlah_grup = COUNT(DISTINCT r.Id_Grup_Fitur),
       @Id_Grup_Visitor = MIN(r.Id_Grup_Fitur)
  FROM N_HRIS_Role_Functional r
 WHERE r.Role_Code IN ('ADMIN_PERMIT', 'ADMIN_TAMU')
   AND r.Id_Grup_Fitur IS NOT NULL;

/* ── Penjaga ─────────────────────────────────────────────────────────────── */
IF @jumlah_grup <> 1
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50124, 'ADMIN_PERMIT dan ADMIN_TAMU tidak menunjuk tepat satu grup fitur; grup Visitor tidak bisa dipastikan.', 1;
END;

IF NOT EXISTS (
    SELECT 1 FROM N_HRIS_Grup_Fitur_Map
     WHERE Id_Grup_Fitur = @Id_Grup_Visitor
       AND Jenis_page = 'visitorVerifikatorPermitPage'
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50124, 'Grup yang ditunjuk role Visitor tidak memetakan visitorVerifikatorPermitPage; bukan grup Visitor.', 1;
END;

/* Kode_Grup_Fitur dijaga indeks unik. Grup lain yang sudah memakai 'VISITOR'
   berarti datanya kusut dengan cara yang tidak boleh ditebak. */
IF EXISTS (
    SELECT 1 FROM N_HRIS_Master_Grup_Fitur
     WHERE Kode_Grup_Fitur = 'VISITOR'
       AND Id_Grup_Fitur <> @Id_Grup_Visitor
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50124, 'Kode VISITOR sudah dipakai grup lain.', 1;
END;

/* ── 1. Kode grup mengikuti kode aplikasi ────────────────────────────────── */
UPDATE N_HRIS_Master_Grup_Fitur
   SET Kode_Grup_Fitur = 'VISITOR'
 WHERE Id_Grup_Fitur = @Id_Grup_Visitor
   AND Kode_Grup_Fitur <> 'VISITOR';

PRINT CONCAT('Kode grup diselaraskan: ', @@ROWCOUNT, ' baris (production lama: 1, development: 0)');

/* ── 2. Cabut hibah halaman admin Visitor (idempoten) ────────────────────── */
UPDATE N_HRIS_Grup_Fitur_Map
   SET Flag_Hibah_Role = 'T'
 WHERE Id_Grup_Fitur = @Id_Grup_Visitor
   AND Jenis_page NOT IN ('visitorPermitPage', 'visitorTamuPage')
   AND Flag_Hibah_Role <> 'T';

PRINT CONCAT('Hibah dicabut: ', @@ROWCOUNT, ' baris (sudah dicabut manual: 0)');

/* ── 3. Pendaftaran ke Database Change Dashboard ─────────────────────────── */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-124-selaraskan-grup-visitor')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DML-2026-124-selaraskan-grup-visitor', 'DML', 'Selaraskan kode dan hibah grup Visitor production', 'Kode grup Visitor production VMS diselaraskan menjadi VISITOR seperti kode aplikasi, dan pencabutan hibah halaman admin Visitor (dilakukan manual 2026-09-18) dicatat secara idempoten.', 'database/dml/DML-2026-124-selaraskan-grup-visitor/data-change.sql', 'docs/changes/DML-2026-124-selaraskan-grup-visitor/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'visitor-grup-selaras');
END;

COMMIT TRANSACTION;

/* ── Verifikasi ──────────────────────────────────────────────────────────── */
SELECT g.Id_Grup_Fitur, g.Kode_Grup_Fitur, g.Nama_Grup_Fitur
  FROM N_HRIS_Master_Grup_Fitur g
 WHERE g.Id_Grup_Fitur IN (SELECT Id_Grup_Fitur FROM N_HRIS_Role_Functional WHERE Role_Code = 'ADMIN_PERMIT');

/* Harapan: 'Y' hanya visitorPermitPage dan visitorTamuPage. */
SELECT gfm.Flag_Hibah_Role, COUNT(*) AS jumlah
  FROM N_HRIS_Grup_Fitur_Map gfm
 WHERE gfm.Id_Grup_Fitur IN (SELECT Id_Grup_Fitur FROM N_HRIS_Role_Functional WHERE Role_Code = 'ADMIN_PERMIT')
 GROUP BY gfm.Flag_Hibah_Role;
