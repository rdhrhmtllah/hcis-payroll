/* Change ID: DML-2026-109-anggota-verifikator-visitor */

/*
    Mengisi anggota role verifikator Visitor (DML-2026-108) beserta kunci
    halaman yang menyertainya (DML-2026-105).

        Arya  (C9,  user 624)  -> ADMIN_PERMIT + ADMIN_TAMU
        Ridho (H45, user 4)    -> ADMIN_PERMIT + ADMIN_TAMU   [login 'NOIR']
        Andre (B6,  user 662)  -> ADMIN_PERMIT
        Imam  (J31, user 1885) -> ADMIN_TAMU

    Role DAN kunci halaman diberikan bersamaan. Keduanya hal yang terpisah:
    role menentukan kepada siapa pengajuan DIKIRIM, kunci halaman menentukan
    siapa boleh MEMBUKANYA. Memberi salah satunya saja menghasilkan orang yang
    menerima antrean tanpa bisa memprosesnya — atau sebaliknya, membuka meja
    yang selamanya kosong. Dua-duanya tidak menampilkan galat apa pun.

    'Noir' pada permintaan user adalah USERNAME; pemiliknya Ridho (H45). Tidak
    ada karyawan bernama Noir di basis data, dan hanya satu baris login yang
    cocok. Dicatat di sini supaya keliru-orang bisa ditelusuri, bukan ditebak
    ulang kelak.

    Keanggotaan yang sudah ada TIDAK dicabut. Change ini murni penambahan;
    pencabutan adalah keputusan tersendiri dan pantas punya jejaknya sendiri.
*/

SET XACT_ABORT ON;
BEGIN TRANSACTION;

/* Sasarannya dituliskan sekali, dipakai dua kali: keanggotaan role dan kunci
   halaman harus berangkat dari daftar yang SAMA. Dua daftar terpisah akan
   menyimpang saat salah satunya disunting. */
DECLARE @sasaran TABLE (
    UserID_Web int NOT NULL,
    Role_Code varchar(100) NOT NULL,
    Jenis_page varchar(100) NOT NULL,
    Urutan_Menu int NULL
);

INSERT INTO @sasaran (UserID_Web, Role_Code, Jenis_page, Urutan_Menu)
VALUES
    (624,  'ADMIN_PERMIT', 'visitorVerifikatorPermitPage', 20),  /* Arya  */
    (624,  'ADMIN_TAMU',   'visitorVerifikatorTamuPage',   25),
    (4,    'ADMIN_PERMIT', 'visitorVerifikatorPermitPage', 20),  /* Ridho */
    (4,    'ADMIN_TAMU',   'visitorVerifikatorTamuPage',   25),
    (662,  'ADMIN_PERMIT', 'visitorVerifikatorPermitPage', 20),  /* Andre */
    (1885, 'ADMIN_TAMU',   'visitorVerifikatorTamuPage',   25);  /* Imam  */

/* ── Penjaga: seluruh sasaran harus orang yang benar-benar ada dan aktif ──
      Baris yang salah user-id akan lolos diam-diam sebagai keanggotaan yatim:
      role terisi, tetapi isRoleApprover() menolaknya karena JOIN ke Karyawan
      tidak menghasilkan baris — antreannya tampak berpemilik padahal tidak. */
IF EXISTS (
    SELECT 1 FROM @sasaran s
     WHERE NOT EXISTS (
         SELECT 1 FROM Karyawan k
          WHERE k.UserID_Web = s.UserID_Web AND k.Aktif = 'Y'
     )
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50109, 'Ada sasaran yang bukan karyawan aktif; periksa kembali UserID_Web-nya.', 1;
END;

IF EXISTS (
    SELECT 1 FROM @sasaran s
     WHERE NOT EXISTS (
         SELECT 1 FROM N_HRIS_Role_Functional r
          WHERE r.Role_Code = s.Role_Code AND r.Flag_Aktif = 'Y'
     )
)
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50109, 'Ada Role_Code yang tidak ada atau nonaktif; jalankan DML-2026-108 lebih dulu.', 1;
END;

/* ── 1. Keanggotaan role ─────────────────────────────────────────────────
      DISTINCT dan penjaga NOT EXISTS keduanya wajib: (UserId_Web, Id_Role)
      pada N_HRIS_Role_User tidak unik, dan barisnya memang sudah pernah kembar
      di tabel ini. */
INSERT INTO N_HRIS_Role_User (UserId_Web, Id_Role, Assigned_At, Assigned_By, Flag_Aktif)
SELECT DISTINCT s.UserID_Web, r.Id_Role, SYSDATETIME(), 'DML-2026-109', 'Y'
FROM @sasaran s
INNER JOIN N_HRIS_Role_Functional r ON r.Role_Code = s.Role_Code
WHERE NOT EXISTS (
    SELECT 1 FROM N_HRIS_Role_User ada
     WHERE ada.Id_Role = r.Id_Role
       AND ada.UserId_Web = s.UserID_Web
       AND ada.Flag_Aktif = 'Y'
);

/* ── 2. Kunci halaman yang menyertainya ──────────────────────────────────
      Dimensi perusahaan/level/divisi diambil dari baris akses milik ORANGNYA
      SENDIRI, bukan ditulis tangan: nilainya berbeda per orang, dan angka yang
      dikarang membuat barisnya tidak pernah cocok dengan penyaring menu. */
INSERT INTO HRIS_Page_Access
(Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Urutan_Menu, Flag_Aktif, Created_At)
SELECT DISTINCT
    dimensi.Kode_Perusahaan,
    dimensi.ID_Level,
    dimensi.ID_Divisi,
    s.Jenis_page,
    s.UserID_Web,
    s.Urutan_Menu,
    'Y',
    SYSDATETIME()
FROM @sasaran s
CROSS APPLY (
    SELECT TOP 1 pa.Kode_Perusahaan, pa.ID_Level, pa.ID_Divisi
      FROM HRIS_Page_Access pa
     WHERE pa.UserID_Web = s.UserID_Web
       AND pa.Flag_Aktif = 'Y'
     ORDER BY pa.Id_Page_Access
) AS dimensi
WHERE NOT EXISTS (
    SELECT 1 FROM HRIS_Page_Access ada
     WHERE ada.Jenis_page = s.Jenis_page
       AND ada.UserID_Web = s.UserID_Web
       AND ada.Flag_Aktif = 'Y'
);

/* ── 3. Pendaftaran ke Database Change Dashboard ─────────────────────────*/
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-109-anggota-verifikator-visitor')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DML-2026-109-anggota-verifikator-visitor', 'DML', 'Anggota role verifikator Visitor beserta kunci halamannya', 'Arya dan Ridho pada kedua role; Andre pada ADMIN_PERMIT; Imam pada ADMIN_TAMU. Kunci halaman verifikator diberikan seiring rolenya.', 'database/dml/DML-2026-109-anggota-verifikator-visitor/data-change.sql', 'docs/changes/DML-2026-109-anggota-verifikator-visitor/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'visitor-verifikator-membership');
END;

COMMIT TRANSACTION;

/* ── Verifikasi ──────────────────────────────────────────────────────────*/
SELECT r.Role_Code, k.Kode_Karyawan, k.Nama, ru.UserId_Web
  FROM N_HRIS_Role_User ru
  INNER JOIN N_HRIS_Role_Functional r ON r.Id_Role = ru.Id_Role
  LEFT JOIN Karyawan k ON k.UserID_Web = ru.UserId_Web
 WHERE r.Role_Code IN ('ADMIN_PERMIT', 'ADMIN_TAMU')
   AND ru.Flag_Aktif = 'Y'
 ORDER BY r.Role_Code, k.Nama;

SELECT pa.Jenis_page, k.Nama, pa.UserID_Web, pa.ID_Level, pa.ID_Divisi
  FROM HRIS_Page_Access pa
  LEFT JOIN Karyawan k ON k.UserID_Web = pa.UserID_Web
 WHERE pa.Jenis_page IN ('visitorVerifikatorPermitPage', 'visitorVerifikatorTamuPage')
   AND pa.Flag_Aktif = 'Y'
 ORDER BY pa.Jenis_page, k.Nama;
