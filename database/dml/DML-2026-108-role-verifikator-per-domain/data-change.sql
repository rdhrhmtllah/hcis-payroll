/* Change ID: DML-2026-108-role-verifikator-per-domain */

/*
    Antrean verifikasi Visitor dipecah per domain, melengkapi pemisahan
    halamannya (DML-2026-105).

    Sebelum change ini kedua config approval menunjuk role yang SAMA:

        PERMIT APPROVAL -> SHE Admin (ROLE:SHE_ADMIN)
        TAMU APPROVAL   -> SHE Admin (ROLE:SHE_ADMIN)

    Akibatnya pemisahan halaman baru memisahkan SIAPA BOLEH MENEKAN TOMBOL,
    bukan KEPADA SIAPA PENGAJUAN DIKIRIM. Mencabut kunci halaman dari seseorang
    membuat antreannya menggantung, bukan berpindah.

    Sesudahnya:

        PERMIT APPROVAL -> Verifikator Permit (ROLE:ADMIN_PERMIT)
        TAMU APPROVAL   -> Verifikator Tamu   (ROLE:ADMIN_TAMU)

    Keanggotaan kedua role baru DISALIN dari anggota aktif SHE_ADMIN, bukan
    dikosongkan: antrean yang tiba-tiba tak berpemilik tidak menampilkan galat
    apa pun — pengajuannya hanya berhenti diproses. Pemangkasan per orang
    dilakukan user setelahnya.

    SHE_ADMIN sendiri TIDAK disentuh. Ia dipakai hal lain di modul SHE, dan
    mencabut keanggotaannya di sini akan mematikan sesuatu yang tidak ada
    hubungannya dengan pekerjaan ini.
*/

SET XACT_ABORT ON;
BEGIN TRANSACTION;

/* ── 1. Dua role verifikator ─────────────────────────────────────────────
      Id_Grup_Fitur 4 = VISITOR, mengikuti SHE_ADMIN. */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Role_Functional WHERE Role_Code = 'ADMIN_PERMIT')
BEGIN
    INSERT INTO N_HRIS_Role_Functional (Role_Code, Role_Name, Description, Flag_Aktif, Created_At, Created_By, Id_Grup_Fitur)
    VALUES ('ADMIN_PERMIT', 'Admin Verifikator Permit', 'Pool verifikator pengajuan permit kendaraan Visitor', 'Y', SYSDATETIME(), 'System', 4);
END;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Role_Functional WHERE Role_Code = 'ADMIN_TAMU')
BEGIN
    INSERT INTO N_HRIS_Role_Functional (Role_Code, Role_Name, Description, Flag_Aktif, Created_At, Created_By, Id_Grup_Fitur)
    VALUES ('ADMIN_TAMU', 'Admin Verifikator Tamu', 'Pool verifikator registrasi kunjungan tamu Visitor', 'Y', SYSDATETIME(), 'System', 4);
END;

/* ── 2. Dua jenis approval yang menunjuk role itu ────────────────────────*/
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Jenis_Approval WHERE Source_Type = 'ROLE' AND Source_Reference = 'ADMIN_PERMIT')
BEGIN
    INSERT INTO N_HRIS_Jenis_Approval (Nama_Approval, Source_Type, Source_Reference, Auto_Approve, Keterangan, Flag_Aktif, Created_At, Created_By)
    VALUES ('Verifikator Permit', 'ROLE', 'ADMIN_PERMIT', 'T', 'Approval oleh role ADMIN_PERMIT', 'Y', SYSDATETIME(), 'System');
END;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Jenis_Approval WHERE Source_Type = 'ROLE' AND Source_Reference = 'ADMIN_TAMU')
BEGIN
    INSERT INTO N_HRIS_Jenis_Approval (Nama_Approval, Source_Type, Source_Reference, Auto_Approve, Keterangan, Flag_Aktif, Created_At, Created_By)
    VALUES ('Verifikator Tamu', 'ROLE', 'ADMIN_TAMU', 'T', 'Approval oleh role ADMIN_TAMU', 'Y', SYSDATETIME(), 'System');
END;

/* ── 3. Config approval diarahkan ke jenis barunya ───────────────────────
      Id-nya dicari lewat kode, bukan ditulis angka: nilainya IDENTITY dan
      berbeda antara development dan production. */
UPDATE detail
   SET detail.Id_Jenis_Approval = (
           SELECT TOP 1 j.Id_Jenis_Approval FROM N_HRIS_Jenis_Approval j
            WHERE j.Source_Type = 'ROLE' AND j.Source_Reference = 'ADMIN_PERMIT'
       )
  FROM N_HRIS_Approval_Config_Detail detail
 INNER JOIN N_HRIS_Approval_Config cfg ON cfg.Id = detail.Id_Approval_Config
 WHERE cfg.Kode_Config = 'PERMIT';

UPDATE detail
   SET detail.Id_Jenis_Approval = (
           SELECT TOP 1 j.Id_Jenis_Approval FROM N_HRIS_Jenis_Approval j
            WHERE j.Source_Type = 'ROLE' AND j.Source_Reference = 'ADMIN_TAMU'
       )
  FROM N_HRIS_Approval_Config_Detail detail
 INNER JOIN N_HRIS_Approval_Config cfg ON cfg.Id = detail.Id_Approval_Config
 WHERE cfg.Kode_Config = 'TAMU';

/* ── 4. Keanggotaan disalin dari anggota AKTIF SHE_ADMIN ─────────────────
      DISTINCT dan penjaga NOT EXISTS keduanya wajib: (UserId_Web, Id_Role)
      pada N_HRIS_Role_User tidak unik, dan barisnya memang sudah pernah
      kembar di tabel ini. */
INSERT INTO N_HRIS_Role_User (UserId_Web, Id_Role, Assigned_At, Assigned_By, Flag_Aktif)
SELECT DISTINCT sumber.UserId_Web, tujuan.Id_Role, SYSDATETIME(), 'DML-2026-108', 'Y'
FROM N_HRIS_Role_User sumber
INNER JOIN N_HRIS_Role_Functional lama ON lama.Id_Role = sumber.Id_Role AND lama.Role_Code = 'SHE_ADMIN'
CROSS JOIN N_HRIS_Role_Functional tujuan
WHERE sumber.Flag_Aktif = 'Y'
  AND tujuan.Role_Code IN ('ADMIN_PERMIT', 'ADMIN_TAMU')
  AND NOT EXISTS (
      SELECT 1 FROM N_HRIS_Role_User ada
       WHERE ada.Id_Role = tujuan.Id_Role
         AND ada.UserId_Web = sumber.UserId_Web
         AND ada.Flag_Aktif = 'Y'
  );

/* ── 5. Pengajuan yang SEDANG menggantung ikut dipindahkan ───────────────
      Tanpa langkah ini, 17 pengajuan yang sudah terbit tetap menunjuk
      SHE_ADMIN. Pemisahannya baru berlaku bagi pengajuan baru, dan begitu
      keanggotaan SHE_ADMIN dipangkas, sisa antrean itu tidak dapat disetujui
      siapa pun — tanpa satu pun galat yang menjelaskan sebabnya.

      Domainnya dikenali dari PREFIKS nomor transaksinya, bukan dari JOIN ke
      tabel transaksi: tiga pengajuan tamu di development sudah tidak punya
      baris kunjungan lagi, dan JOIN akan melewatkannya begitu saja. */
UPDATE ar
   SET ar.Id_Jenis_Approval = (
           SELECT TOP 1 j.Id_Jenis_Approval FROM N_HRIS_Jenis_Approval j
            WHERE j.Source_Type = 'ROLE' AND j.Source_Reference = 'ADMIN_PERMIT'
       ),
       ar.Group_Reference_Id = (
           SELECT TOP 1 r.Id_Role FROM N_HRIS_Role_Functional r WHERE r.Role_Code = 'ADMIN_PERMIT'
       ),
       ar.update_at = SYSDATETIME()
  FROM HRIS_Approval_Request ar
 INNER JOIN N_HRIS_Role_Functional lama ON lama.Id_Role = ar.Group_Reference_Id AND lama.Role_Code = 'SHE_ADMIN'
 WHERE ar.Flag_Approval IS NULL
   AND ar.No_Transaksi LIKE 'PMT%';

UPDATE ar
   SET ar.Id_Jenis_Approval = (
           SELECT TOP 1 j.Id_Jenis_Approval FROM N_HRIS_Jenis_Approval j
            WHERE j.Source_Type = 'ROLE' AND j.Source_Reference = 'ADMIN_TAMU'
       ),
       ar.Group_Reference_Id = (
           SELECT TOP 1 r.Id_Role FROM N_HRIS_Role_Functional r WHERE r.Role_Code = 'ADMIN_TAMU'
       ),
       ar.update_at = SYSDATETIME()
  FROM HRIS_Approval_Request ar
 INNER JOIN N_HRIS_Role_Functional lama ON lama.Id_Role = ar.Group_Reference_Id AND lama.Role_Code = 'SHE_ADMIN'
 WHERE ar.Flag_Approval IS NULL
   AND ar.No_Transaksi LIKE 'VSR%';

/* ── 6. Pendaftaran ke Database Change Dashboard ─────────────────────────*/
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-108-role-verifikator-per-domain')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DML-2026-108-role-verifikator-per-domain', 'DML', 'Role verifikator Visitor dipisah per domain', 'Role ADMIN_PERMIT dan ADMIN_TAMU beserta jenis approval-nya; config PERMIT dan TAMU diarahkan ke masing-masing; keanggotaan disalin dari SHE_ADMIN; pengajuan yang masih menggantung ikut dipindahkan per domain.', 'database/dml/DML-2026-108-role-verifikator-per-domain/data-change.sql', 'docs/changes/DML-2026-108-role-verifikator-per-domain/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'visitor-verifikator-role-split');
END;

COMMIT TRANSACTION;

/* ── Verifikasi ──────────────────────────────────────────────────────────*/
SELECT cfg.Kode_Config, d.Approval_Order, j.Nama_Approval, j.Source_Type, j.Source_Reference
  FROM N_HRIS_Approval_Config cfg
  INNER JOIN N_HRIS_Approval_Config_Detail d ON d.Id_Approval_Config = cfg.Id
  LEFT JOIN N_HRIS_Jenis_Approval j ON j.Id_Jenis_Approval = d.Id_Jenis_Approval
 WHERE cfg.Kode_Config IN ('PERMIT', 'TAMU')
 ORDER BY cfg.Kode_Config, d.Approval_Order;

SELECT r.Role_Code, COUNT(ru.Id) AS anggota_aktif
  FROM N_HRIS_Role_Functional r
  LEFT JOIN N_HRIS_Role_User ru ON ru.Id_Role = r.Id_Role AND ru.Flag_Aktif = 'Y'
 WHERE r.Role_Code IN ('SHE_ADMIN', 'ADMIN_PERMIT', 'ADMIN_TAMU')
 GROUP BY r.Role_Code
 ORDER BY r.Role_Code;

SELECT r.Role_Code, COUNT(*) AS pengajuan_menggantung
  FROM HRIS_Approval_Request ar
  INNER JOIN N_HRIS_Role_Functional r ON r.Id_Role = ar.Group_Reference_Id
 WHERE ar.Flag_Approval IS NULL
 GROUP BY r.Role_Code
 ORDER BY r.Role_Code;
